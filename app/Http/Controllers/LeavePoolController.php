<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Jobs\ProcessPayoutJob;

/**
 * SORTIE VOLONTAIRE DE POOL (2026-10-06, demande utilisateur bêta) —
 * « Quitter la pool » : le joueur seul dans une pool (ou qui veut sortir,
 * sans adversaire en prod bêta) récupère son stake on-chain.
 *
 * Sécurité :
 *  - token.auth (l'utilisateur ne peut sortir QUE lui-même, pas d'IDOR) ;
 *  - verrou Cache::lock (pas de double-leave concurrent) ;
 *  - refuse si payout_signature posée (d'abord CLAIM, sinon double-sortie) ;
 *  - le payout passe par ProcessPayoutJob → bridge updateUserBalance+payOut —
 *    la même primitive audited que l'auto-payout des bots (n'OUVRE PAS
 *    /internal/payout arbitraire, cf. api.php:190-192).
 */
class LeavePoolController extends Controller
{
    public function leave(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $lock = Cache::lock('leave_pool_' . $user->id, 5);
        if (!$lock->get()) {
            return response()->json(['error' => 'Action already in progress'], 429);
        }

        try {
            return DB::transaction(function () use ($user) {
                // 1. D'abord CLAIM : une signature posée = gains à claim d'abord.
                if ($user->payout_signature) {
                    return response()->json([
                        'error' => 'Claim vos gains d\'abord (bouton CLAIM), puis quittez la pool.',
                    ], 409);
                }

                // 2. Rien à sortir : pas en pool / pas de stake battle.
                $inPoolDb = in_array($user->status, ['in_pool', 'waiting'])
                    || $user->battle_balance > 0
                    || $user->pool_id;

                if (!$inPoolDb) {
                    // Peut être marqué in_pool ON-CHAIN sans session DB (tx
                    // minée, front figé — cas du compte 2, journal 2026-10-06
                    // 14:07) : le payOut bridge libérera isUserInAnyPool.
                    if ((float) $user->balance <= 0) {
                        return response()->json(['error' => 'Aucun stake actif'], 400);
                    }
                }

                // 3. Sortie DB (miroir refundNoFightUser, SessionManager:88).
                $refunded = (float) $user->battle_balance;
                $payoutAmount = $refunded > 0 ? $refunded : (float) $user->balance;

                $user->balance = 0; // toute la balance part en payout volontaire
                $user->battle_balance = 0;
                $user->pool_id = null;
                $user->status = 'stopped';
                $user->autoplay_active = false;
                $user->session_started = false;
                $user->save();

                // 4. Payout volontaire réel (bridge payOut libère on-chain).
                ProcessPayoutJob::dispatch($user->wallet_address, $payoutAmount);

                Log::channel('business')->info('LEAVE_POOL', [
                    'user' => $user->id, 'wallet' => $user->wallet_address,
                    'payout' => $payoutAmount,
                ]);

                $user->refresh();

                return response()->json([
                    'message' => 'Pool quittée — votre stake revient dans votre wallet.',
                    'payout_amount' => $payoutAmount,
                    'status' => $user->status,
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('[LEAVE_POOL] Échec user ' . $user->id . ': ' . $e->getMessage());
            return response()->json(['error' => 'Échec de la sortie : ' . $e->getMessage()], 500);
        } finally {
            $lock->release();
        }
    }
}