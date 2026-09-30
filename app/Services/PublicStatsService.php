<?php

namespace App\Services;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Statistiques publiques (tableau de bord vitrine).
 *
 * Service STATELESS : aucune propriété d'instance (compatibilité Octane/Swoole).
 * Toutes les dates sont calculées côté PHP (Carbon) puis bindees en paramètres
 * SQL — jamais via des fonctions SQL (portabilité SQLite/MySQL).
 *
 * Coût : 2 requêtes agrégées au total (token_purchases + sous-selects scalaires).
 * NB : agrégats lus via DB::table() (stdClass) et non via le modèle Eloquent :
 * caster un Model en array expose ses propriétés protégées, pas les colonnes
 * d'agrégat calculées.
 */
class PublicStatsService
{
    /**
     * Fenêtres temporelles supportées : all / 30 jours / 7 jours / 24 heures.
     * 'all' => null : borne inférieure très ancienne pour couvrir tout l'historique
     * tout en gardant la meme forme de requête (index created_at utilisable).
     */
    private const WINDOWS = [
        'all' => null,
        '30d' => '-30 days',
        '7d'  => '-7 days',
        '24h' => '-24 hours',
    ];

    public function get(?string $onChainTvl = null, ?string $onChainFees = null): array
    {
        $now = CarbonImmutable::now();

        $windowData = $this->getTokenPurchasesPerWindow($now);
        $misc = $this->getMiscStats($now);

        $tokenPurchases = [];
        foreach (array_keys(self::WINDOWS) as $key) {
            $w = $windowData[$key];
            $unique = (int) $w['unique_players'];
            $total = (float) ($w['total'] ?? 0);

            $tokenPurchases[$key] = [
                'total_snt' => $total,
                'count' => (int) ($w['count'] ?? 0),
                'unique_players' => $unique,
                // Division protégée : 0 joueur unique -> 0, jamais NaN/INF.
                'avg_per_player' => $unique > 0 ? round($total / $unique, 2) : 0.0,
            ];
        }

        return [
            'generated_at' => $now->toIso8601String(),
            // Le "degraded" final est calculé par le controller (les deux valeurs
            // peuvent être null ensemble si le bridge est indisponible).
            'on_chain' => [
                'tvl_snt' => $onChainTvl,
                'fees_snt' => $onChainFees,
            ],
            'token_purchases' => $tokenPurchases,
            'fights' => [
                'total' => $misc['fights_total'],
                'last_24h' => $misc['fights_24h'],
            ],
            'players' => [
                'total' => $misc['users_total'],
                'online' => $misc['users_online'],
            ],
            'pools' => [
                'active' => $misc['pools_active'],
            ],
            'marketplace' => [
                'fulfilled_trades' => $misc['trades_fulfilled_count'],
                'total_snt' => $misc['trades_fulfilled_snt'],
                'total_avax' => $misc['trades_fulfilled_avax'],
            ],
        ];
    }

    /**
     * Agrégats token_purchases pour les 4 fenêtres — UNE SEULE requête
     * (CASE WHEN avec bornes bindees depuis Carbon).
     *
     * @return array<string, array{total: mixed, count: mixed, unique_players: mixed}>
     */
    private function getTokenPurchasesPerWindow(CarbonImmutable $now): array
    {
        $selects = [];
        $bindings = [];

        foreach (self::WINDOWS as $key => $offset) {
            // 'all' : borne ancienne fixe (pas de now()->subYears() pour rester
            // indexable et deterministe) ; les fenetres derivees de $now.
            $since = $offset === null
                ? Carbon::parse('2000-01-01 00:00:00')
                : $now->modify($offset);

            $selects[] = "COALESCE(SUM(CASE WHEN created_at >= ? THEN amount ELSE 0 END), 0) AS total_{$key}";
            $bindings[] = $since;

            $selects[] = "COALESCE(SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END), 0) AS count_{$key}";
            $bindings[] = $since;

            $selects[] = "COALESCE(COUNT(DISTINCT CASE WHEN created_at >= ? THEN user_id END), 0) AS unique_{$key}";
            $bindings[] = $since;
        }

        $row = (array) (DB::table('token_purchases')
            ->selectRaw(implode(', ', $selects), $bindings)
            ->first() ?? []);

        $result = [];
        foreach (array_keys(self::WINDOWS) as $key) {
            $result[$key] = [
                'total' => $row["total_{$key}"] ?? 0,
                'count' => $row["count_{$key}"] ?? 0,
                'unique_players' => $row["unique_{$key}"] ?? 0,
            ];
        }

        return $result;
    }

    /**
     * Fights, users, pools actives, trades fulfilled — UNE SEULE requête de
     * sous-selects scalaires, SANS clause FROM (fonctionne sous SQLite et MySQL).
     *
     * @return array<string, int|float>
     */
    private function getMiscStats(CarbonImmutable $now): array
    {
        $since24h = $now->subHours(24);
        // Arbitrage matrice : la table pools n'a pas de booleen "active", elle a un
        // ENUM de statuts de cycle de vie. "Actif" = cycle de vie non termine.
        // NB : AdminController::getStats requete status = 'active' qui ne matche
        // jamais cet ENUM (bug preexistant hors perimetre).
        $activeStatuses = ['from_blockchain_running', 'from_server_waitting', 'from_server_running', 'batched'];
        $statusPlaceholders = implode(', ', array_fill(0, count($activeStatuses), '?'));

        $row = (array) (DB::selectOne("
            SELECT
                COALESCE((SELECT COUNT(*) FROM fights), 0) AS fights_total,
                COALESCE((SELECT COUNT(*) FROM fights WHERE created_at >= ?), 0) AS fights_24h,
                COALESCE((SELECT COUNT(*) FROM users), 0) AS users_total,
                COALESCE((SELECT COUNT(*) FROM users WHERE is_online = 1), 0) AS users_online,
                COALESCE((SELECT COUNT(*) FROM pools WHERE status IN ({$statusPlaceholders})), 0) AS pools_active,
                COALESCE((SELECT COUNT(*) FROM trades WHERE status = ?), 0) AS trades_fulfilled_count,
                COALESCE((SELECT SUM(snt_amount) FROM trades WHERE status = ?), 0) AS trades_fulfilled_snt,
                COALESCE((SELECT SUM(avax_amount) FROM trades WHERE status = ?), 0) AS trades_fulfilled_avax
        ", array_merge([$since24h], $activeStatuses, ['fulfilled', 'fulfilled', 'fulfilled'])) ?? []);

        return [
            'fights_total' => (int) ($row['fights_total'] ?? 0),
            'fights_24h' => (int) ($row['fights_24h'] ?? 0),
            'users_total' => (int) ($row['users_total'] ?? 0),
            'users_online' => (int) ($row['users_online'] ?? 0),
            'pools_active' => (int) ($row['pools_active'] ?? 0),
            'trades_fulfilled_count' => (int) ($row['trades_fulfilled_count'] ?? 0),
            'trades_fulfilled_snt' => (float) ($row['trades_fulfilled_snt'] ?? 0),
            'trades_fulfilled_avax' => (float) ($row['trades_fulfilled_avax'] ?? 0),
        ];
    }
}