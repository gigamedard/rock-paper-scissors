<?php

namespace App\Http\Controllers;

use App\Helpers\Web3Helper;
use App\Services\PublicStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * Statistiques publiques (vitrine) — SANS AUTHENTIFICATION.
 *
 * Contrainte Octane : aucune propriété d'instance (le controller est réutilisé
 * entre requêtes sous Swoole). Tout l'état reste local aux méthodes.
 *
 * Cache dual-niveau :
 * - stats_public_v1 (45s)  : agrégats DB -> décharge MySQL
 * - stats_onchain_v1 (60s) : lecture bridge/contrat -> décharge le bridge Node
 *   avec une fenêtre de tolérance de panne indépendante (Web3Helper::getChainStats).
 */
class PublicStatsController extends Controller
{
    public function index(): JsonResponse
    {
        // 1) Snapshot on-chain : cache dédié (60s) pour NE PAS hériter des 45s des
        //    stats DB. En cas d'échec ou de bridge ancien (payload sans clé
        //    contractBalance), tvl/fees sortent à null -> degraded à l'étape 3.
        $onChain = Cache::remember('stats_onchain_v1', 60, function () {
            $nodeUrl = (string) config('app.NODE_WORKER_URL', 'http://127.0.0.1:3000');
            $stats = Web3Helper::getChainStats($nodeUrl);

            return [
                // Châssis string|null pour rester JSON-stable dans le cache.
                'tvl' => isset($stats['tvl']) && is_numeric($stats['tvl']) ? (string) $stats['tvl'] : null,
                'fees' => isset($stats['fees']) && is_numeric($stats['fees']) ? (string) $stats['fees'] : null,
            ];
        });

        // 2) Agrégats DB (45s), enrichis avec le snapshot on-chain résolu ci-dessus.
        $payload = Cache::remember('stats_public_v1', 45, function () use ($onChain) {
            return app(PublicStatsService::class)->get($onChain['tvl'], $onChain['fees']);
        });

        // 3) degraded : recalculé à CHAQUE réponse (hors cache). Null => dégradé.
        $tvl = isset($payload['on_chain']['tvl_snt']) && is_numeric($payload['on_chain']['tvl_snt'])
            ? (float) $payload['on_chain']['tvl_snt'] : null;
        $fees = isset($payload['on_chain']['fees_snt']) && is_numeric($payload['on_chain']['fees_snt'])
            ? (float) $payload['on_chain']['fees_snt'] : null;

        $payload['on_chain'] = [
            'tvl_snt' => $tvl,
            'fees_snt' => $fees,
            // Dégradé = au moins une métrique indisponible (bridge down ou payload
            // du bridge trop ancien, sans la clé contractBalance).
            'degraded' => $tvl === null || $fees === null,
        ];

        return response()
            ->json($payload)
            ->withHeaders([
                'Cache-Control' => 'public, max-age=30, s-maxage=45',
            ]);
    }
}