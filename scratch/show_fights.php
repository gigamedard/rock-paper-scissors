<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Fight;

$userId = 6;
$fights = Fight::where('user1_id', $userId)->orWhere('user2_id', $userId)->get();

echo "===========================================\n";
echo "⚔️ HISTORIQUE DES COMBATS (USER #$userId)\n";
echo "===========================================\n";

if ($fights->isEmpty()) {
    echo "Aucun combat trouvé.\n";
} else {
    foreach ($fights as $f) {
        $isUser1 = ($f->user1_id == $userId);
        $opponentId = $isUser1 ? $f->user2_id : $f->user1_id;
        $userMove = $isUser1 ? $f->user1_chosed : $f->user2_chosed;
        $oppMove = $isUser1 ? $f->user2_chosed : $f->user1_chosed;
        
        $resultText = '';
        if ($f->result == 'draw') {
            $resultText = 'Égalité 🤝';
        } elseif (($isUser1 && $f->result == 'user1_win') || (!$isUser1 && $f->result == 'user2_win')) {
            $resultText = 'VICTOIRE 🏆';
        } else {
            $resultText = 'DÉFAITE 💀';
        }
        
        echo sprintf("Combat #%d | vs Bot #%d | Coups: %-8s vs %-8s | %s\n", 
            $f->id, 
            $opponentId - 1, // Normalizing bot ID
            strtoupper($userMove), 
            strtoupper($oppMove), 
            $resultText
        );
    }
}
echo "===========================================\n";
