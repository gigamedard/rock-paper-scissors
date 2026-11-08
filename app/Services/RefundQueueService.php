<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Fonds de recouvrement + file d'attente FIFO.
 *
 * Le fonds est crédité par les frais. Les joueurs qui perdent les 2 sessions
 * sont ajoutés à la file. Un batch paie les premiers de la file dès que le
 * fonds couvre `trigger_pct` de la dette totale, en payant au plus `batch_pct`
 * de la file. Le solde ne devient JAMAIS négatif et le FIFO est strict.
 */
class RefundQueueService
{
    /**
     * Planificateur pur (sans DB), testable indépendamment.
     *
     * @param  float  $fund  solde disponible
     * @param  array<int,array{id:mixed,amount:float}>  $queue  file ordonnée FIFO
     * @return array{payouts:array<int,array{id:mixed,amount:float}>,paid:float,remaining:float,triggered:bool,batch:int}
     */
    public static function planPayouts(float $fund, array $queue, float $triggerPct = 0.10, float $batchPct = 0.05): array
    {
        $queue = array_values($queue);
        $total = 0.0;
        foreach ($queue as $item) {
            $total += (float) $item['amount'];
        }

        if (empty($queue) || $total <= 0.0 || $fund <= 0.0) {
            return ['payouts' => [], 'paid' => 0.0, 'remaining' => max(0.0, $fund), 'triggered' => false, 'batch' => 0];
        }

        if ($fund + 1e-9 < $triggerPct * $total) {
            return ['payouts' => [], 'paid' => 0.0, 'remaining' => $fund, 'triggered' => false, 'batch' => 0];
        }

        $batch = max(1, (int) ceil($batchPct * count($queue)));
        $payouts = [];
        $paid = 0.0;
        foreach ($queue as $item) {
            if (count($payouts) >= $batch) {
                break;
            }
            $amount = (float) $item['amount'];
            if ($fund - $paid + 1e-9 >= $amount) {
                $payouts[] = ['id' => $item['id'], 'amount' => $amount];
                $paid += $amount;
            } else {
                break; // FIFO strict : on ne saute pas la tête de file
            }
        }

        return [
            'payouts' => $payouts,
            'paid' => $paid,
            'remaining' => $fund - $paid,
            'triggered' => true,
            'batch' => $batch,
        ];
    }

    public function balance(): float
    {
        $row = DB::table('refund_fund_state')->where('id', 1)->first();

        return $row ? (float) $row->balance : 0.0;
    }

    /** Crédite le fonds (frais encaissés). */
    public function credit(float $amount, string $reason = 'fee', ?string $reference = null): void
    {
        if ($amount <= 0) {
            return;
        }

        DB::transaction(function () use ($amount, $reason, $reference) {
            $this->ensureState();
            DB::table('refund_fund_state')->where('id', 1)->increment('balance', $amount, ['updated_at' => now()]);
            DB::table('refund_ledger')->insert([
                'type' => 'credit',
                'amount' => $amount,
                'reason' => $reason,
                'reference' => $reference,
                'created_at' => now(),
            ]);
        });
    }

    /** Ajoute un joueur échoué à la file FIFO. Retourne l'id de la ligne. */
    public function enqueue(int $userId, float $amountOwed, array $snapshot = []): int
    {
        return DB::table('refund_queue')->insertGetId([
            'user_id' => $userId,
            'amount_owed' => $amountOwed,
            'status' => 'waiting',
            'refund_pct' => $snapshot['refund_pct'] ?? null,
            'target_multiplier' => $snapshot['target_multiplier'] ?? null,
            'fee_rate' => $snapshot['fee_rate'] ?? null,
            'snapshot' => $snapshot ? json_encode($snapshot) : null,
            'created_at' => now(),
        ]);
    }

    /** Remboursements en attente pour un joueur (chacun distinct, dans l'ordre). */
    public function pendingForUser(int $userId): array
    {
        return DB::table('refund_queue')
            ->where('user_id', $userId)
            ->where('status', 'waiting')
            ->orderBy('id')
            ->get(['id', 'amount_owed', 'created_at'])
            ->all();
    }

    public function pendingTotalForUser(int $userId): float
    {
        return (float) DB::table('refund_queue')
            ->where('user_id', $userId)
            ->where('status', 'waiting')
            ->sum('amount_owed');
    }

    /**
     * Exécute un batch FIFO si le fonds atteint le seuil.
     *
     * @return array{queue_total:float,waiting:int,fund_before:float,paid:float,paid_count:int,fund_after:float,triggered:bool,batch:int}
     */
    public function processBatch(?float $triggerPct = null, ?float $batchPct = null): array
    {
        $triggerPct = $triggerPct ?? (float) config('refund.trigger_pct', 0.10);
        $batchPct = $batchPct ?? (float) config('refund.batch_pct', 0.05);

        return DB::transaction(function () use ($triggerPct, $batchPct) {
            $rows = DB::table('refund_queue')
                ->where('status', 'waiting')
                ->orderBy('id')
                ->get(['id', 'user_id', 'amount_owed']);

            // Chaque ligne est une dette DISTINCTE (un joueur peut en avoir plusieurs).
            $userIdById = $rows->pluck('user_id', 'id')->all();
            $queue = $rows->map(fn ($r) => ['id' => $r->id, 'amount' => (float) $r->amount_owed])->all();
            $total = array_sum(array_map(fn ($r) => $r['amount'], $queue));
            $fund = $this->balance();

            $plan = self::planPayouts($fund, $queue, $triggerPct, $batchPct);

            if (empty($plan['payouts'])) {
                return [
                    'queue_total' => $total,
                    'waiting' => count($queue),
                    'fund_before' => $fund,
                    'paid' => 0.0,
                    'paid_count' => 0,
                    'fund_after' => $fund,
                    'triggered' => $plan['triggered'],
                    'batch' => $plan['batch'],
                ];
            }

            $ids = array_column($plan['payouts'], 'id');
            DB::table('refund_queue')->whereIn('id', $ids)->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);

            // Crédite le solde du joueur (il peut rejouer indépendamment).
            if ((bool) config('refund.credit_balance_on_payout', true)) {
                foreach ($plan['payouts'] as $payout) {
                    $userId = $userIdById[$payout['id']] ?? null;
                    if (! $userId) {
                        continue;
                    }
                    DB::table('users')->where('id', $userId)->increment('balance', (float) $payout['amount']);
                    $newBalance = (float) DB::table('users')->where('id', $userId)->value('balance');
                    DB::table('users')->where('id', $userId)->update([
                        'max_balance' => DB::raw('GREATEST(COALESCE(max_balance, 0), ' . sprintf('%.8F', $newBalance) . ')'),
                    ]);
                }
            }

            $this->ensureState();
            DB::table('refund_fund_state')->where('id', 1)->decrement('balance', $plan['paid'], ['updated_at' => now()]);
            DB::table('refund_ledger')->insert([
                'type' => 'debit',
                'amount' => $plan['paid'],
                'reason' => 'refund_batch',
                'reference' => implode(',', $ids),
                'created_at' => now(),
            ]);

            return [
                'queue_total' => $total,
                'waiting' => count($queue),
                'fund_before' => $fund,
                'paid' => $plan['paid'],
                'paid_count' => count($ids),
                'fund_after' => $plan['remaining'],
                'triggered' => $plan['triggered'],
                'batch' => $plan['batch'],
            ];
        });
    }

    private function ensureState(): void
    {
        if (! DB::table('refund_fund_state')->where('id', 1)->exists()) {
            DB::table('refund_fund_state')->insert(['id' => 1, 'balance' => 0, 'updated_at' => now()]);
        }
    }
}
