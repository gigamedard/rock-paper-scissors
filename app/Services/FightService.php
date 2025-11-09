<?php
namespace App\Services;

use App\Models\Fight;
use App\Models\User;
use App\Models\PreMove;
use App\Models\FHist;
use App\Services\HistoricalFightService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class FightService
{
    protected HistoricalFightService $historicalFightService;
    protected $rockPaperScissors;

    // Injection de dépendance !
    // Plus de "new HistoricalFightService()" dans le constructeur
    public function __construct(HistoricalFightService $historicalFightService)
    {
        $this->historicalFightService = $historicalFightService;
        
        // La logique de jeu, extraite de ArrayIndex.php
        $this->rockPaperScissors = [
            'rock'     => ['rock' => 'draw', 'paper' => 'user2_win', 'scissors' => 'user1_win'],
            'paper'    => ['rock' => 'user1_win', 'paper' => 'draw', 'scissors' => 'user2_win'],
            'scissors' => ['rock' => 'user2_win', 'paper' => 'user1_win', 'scissors' => 'draw'],
        ];
    }

    /**
     * Point d'entrée principal pour gérer un combat.
     * C'est la logique de ton ancien "handlePoolAutoplayFight".
     */
    public function handleFight(Fight $fight): void
    {
        Log::info("FightService: Prise en charge du combat ID {$fight->id} entre user {$fight->user1_id} et {$fight->user2_id}");

        DB::transaction(function () use ($fight) {
            try {
                // 1. Récupérer les joueurs
                $user1 = $fight->user1;
                $user2 = $fight->user2;

                // 2. Récupérer leurs coups
                $moves = $this->getMoves($user1, $user2);
                $fight->user1_chosed = $moves['move1'];
                $fight->user2_chosed = $moves['move2'];

                // 3. Déterminer le résultat
                $fight->result = $this->determineWinner($fight->user1_chosed, $fight->user2_chosed);
                
                // 4. Mettre à jour les soldes (battle_balance)
                $this->updateBalances($fight, $user1, $user2);
                
                // 5. Mettre à jour le statut du combat et sauvegarder
                $fight->status = 'completed';
                $fight->save();

                // 6. Archiver le combat dans FHist
                $this->archiveFight($fight);

                // 7. Gérer le perdant (le remettre en file d'attente)
                $this->handleLoser($fight, $user1, $user2);

            } catch (\Exception $e) {
                Log::error("Erreur lors du traitement du combat {$fight->id}: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
                // En cas d'erreur, on remet les utilisateurs "available" pour qu'ils ne soient pas bloqués
                $fight->user1->update(['status' => 'available']);
                $fight->user2->update(['status' => 'available']);
                $fight->update(['status' => 'failed']); // Marquer le combat comme échoué
            }
        });
    }

    /**
     * Récupère les pre-moves actuels pour les deux joueurs.
     */
    private function getMoves(User $user1, User $user2): array
    {
        $preMove1 = $user1->preMove;
        $preMove2 = $user2->preMove;

        if (!$preMove1 || !$preMove2) {
            throw new \Exception("Pre-moves non trouvés pour un des utilisateurs du combat {$user1->id} vs {$user2->id}");
        }

        $move1 = $preMove1->moves[$preMove1->current_index];
        $move2 = $preMove2->moves[$preMove2->current_index];

        // Incrémenter les index
        $preMove1->increment('current_index');
        $preMove2->increment('current_index');

        return ['move1' => $move1, 'move2' => $move2];
    }

    /**
     * Détermine le gagnant en fonction des coups.
     */
    private function determineWinner(string $move1, string $move2): string
    {
        if ($move1 === 'nothing' || $move2 === 'nothing') {
            return 'draw'; // Gérer les cas "nothing"
        }
        return $this->rockPaperScissors[$move1][$move2] ?? 'draw';
    }

    /**
     * Met à jour le battle_balance des utilisateurs en fonction du résultat.
     */
    private function updateBalances(Fight $fight, User $user1, User $user2): void
    {
        $bet = $fight->base_bet_amount;

        if ($fight->result === 'user1_win') {
            $user1->battle_balance += $bet;
            $user2->battle_balance -= $bet;
        } elseif ($fight->result === 'user2_win') {
            $user1->battle_balance -= $bet;
            $user2->battle_balance += $bet;
        }
        // En cas de 'draw', personne ne perd rien

        $user1->save();
        $user2->save();
    }

    /**
     * Crée un enregistrement dans FHist.
     */
    private function archiveFight(Fight $fight): void
    {
        // La logique de HistoricalFightService est d'archiver un *pool entier*.
        // Ici, nous archivons un *seul* combat.
        // C'est la logique de ton ancien "archiveFight" dans Fight.php
        FHist::create([
            'pool_id'       => $fight->pool_id,
            'fight_id'      => $fight->id,
            'user1_id'      => $fight->user1_id,
            'user1_address' => $fight->user1->wallet_address,
            'user1_move'    => $fight->user1_chosed,
            'user1_battle_balance' => $fight->user1->battle_balance,
            'user2_id'      => $fight->user2_id,
            'user2_address' => $fight->user2->wallet_address,
            'user2_move'    => $fight->user2_chosed,
            'user2_battle_balance' => $fight->user2->battle_balance,
            // (Ajoute les autres champs de FHist si nécessaire, comme les gains)
        ]);
    }

    /**
     * Gère le perdant (ou les deux en cas d'égalité).
     */
    private function handleLoser(Fight $fight, User $user1, User $user2): void
    {
        // Logique de ton ancien "addUserToNewPool"
        // Le gagnant continue (reste 'locked'), le perdant est libéré.
        
        if ($fight->result === 'user1_win') {
            // User1 gagne, User2 perd
            $this->addUserToQueue($user2->id, $fight->base_bet_amount);
        } elseif ($fight->result === 'user2_win') {
            // User2 gagne, User1 perd
            $this->addUserToQueue($user1->id, $fight->base_bet_amount);
        } else {
            // Draw, les deux sont "perdants" (sortent du pool)
            $this->addUserToQueue($user1->id, $fight->base_bet_amount);
            $this->addUserToQueue($user2->id, $fight->base_bet_amount);
        }
    }

    /**
     * Remet un utilisateur en file d'attente.
     */
    private function addUserToQueue(int $userId, $lastBaseBet): void
    {
        // Remettre l'utilisateur comme 'available'
        User::where('id', $userId)->update(['status' => 'available']);
        Log::info("FightService: Utilisateur $userId remis en file d'attente (status: available).");
        
        // TODO: Implémenter la logique de martingale si nécessaire
        // (ex: créer une tâche pour le remettre dans un pool)
    }
}