<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Jobs\ProcessFaucetJob;

/**
 * AIRDROP DE BIENVENUE (faucet) — 2026-10-06.
 * Un nouvel utilisateur connecté par wallet peut réclamer UNE seule fois
 * une dotation de 50 TST natifs Pingala. Les fonds partent du portefeuille
 * faucet (bridge) vers le wallet du joueur via une raw transaction native.
 *
 * Sécurité / anti-abuse :
 *  - token.auth (l'utilisateur ne peut réclamer QUE pour lui-même) ;
 *  - verrou Cache::lock (pas de double-claim concurrent) ;
 *  - flag has_received_airdrop en DB (idempotence définitive, verrouillé FOR UPDATE) ;
 *  - le transfert est asynchrone (ProcessFaucetJob) et le hash est stocké pour audit.
 */
class FaucetController extends Controller
{
    public function claim(Request $request): JsonResponse
    {
        if (!config('economy.airdrop.enabled', true)) {
            return response()->json(['error' => 'Airdrop disabled'], 403);
        }

        /** @var User $user */
        $user = $request->user();

        $lock = Cache::lock('faucet_claim_' . $user->id, 10);
        if (!$lock->get()) {
            return response()->json(['error' => 'Action already in progress'], 429);
        }

        try {
            return DB::transaction(function () use ($user) {
                $locked = User::where('id', $user->id)->lockForUpdate()->first();

                if ($locked->has_received_airdrop) {
                    return response()->json(['error' => 'Airdrop already claimed'], 409);
                }

                if (!$locked->wallet_address) {
                    return response()->json(['error' => 'No wallet address'], 400);
                }

                $amount = (float) config('economy.airdrop.amount_tst', 50);

                // Le flag n'est PAS posé ici : c'est ProcessFaucetJob qui le pose
                // uniquement après un transfert on-chain réussi (évite de bloquer
                // l'utilisateur si le faucet est vide ou le réseau indisponible).
                // Le lockForUpdate ci-dessus + le flag final garantissent
                // l'idempotence face aux double-clics concurrents.
                ProcessFaucetJob::dispatch($locked->id, $locked->wallet_address, $amount);

                Log::channel('business')->info('FAUCET_CLAIM', [
                    'user' => $locked->id,
                    'wallet' => $locked->wallet_address,
                    'amount' => $amount,
                ]);

                return response()->json([
                    'message' => 'Airdrop requested — ' . $amount . ' TST will arrive shortly.',
                    'amount' => $amount,
                    'has_received_airdrop' => false,
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('[FAUCET] Échec user ' . $user->id . ': ' . $e->getMessage());
            return response()->json(['error' => 'Échec de la réclamation : ' . $e->getMessage()], 500);
        } finally {
            $lock->release();
        }
    }
}
