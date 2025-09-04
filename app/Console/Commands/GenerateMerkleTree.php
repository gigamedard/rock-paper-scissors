<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GenerateMerkleTree extends Command
{
    protected $signature = 'merkle:generate {--output=whitelist.json}';
    protected $description = 'Generate Merkle tree for whitelisted addresses';

    public function handle()
    {
        $this->info('Génération de l\'arbre Merkle...');

        // Récupérer les adresses éligibles (exemple: utilisateurs avec au moins une transaction)
        $addresses = $this->getEligibleAddresses();
        
        if (empty($addresses)) {
            $this->error('Aucune adresse éligible trouvée.');
            return 1;
        }

        $this->info('Nombre d\'adresses éligibles: ' . count($addresses));

        // Générer l'arbre Merkle
        $merkleData = $this->generateMerkleTree($addresses);
        
        // Sauvegarder les données
        $outputFile = $this->option('output');
        Storage::disk('public')->put($outputFile, json_encode($merkleData, JSON_PRETTY_PRINT));
        
        $this->info('Arbre Merkle généré avec succès!');
        $this->info('Root: ' . $merkleData['root']);
        $this->info('Fichier sauvegardé: storage/app/public/' . $outputFile);
        
        return 0;
    }

    private function getEligibleAddresses(): array
    {
        // Exemple: récupérer tous les utilisateurs qui ont fait au moins une transaction
        $users = DB::table('users')
            ->whereNotNull('wallet_address')
            ->where('balance', '>', 0) // ou tout autre critère d'éligibilité
            ->pluck('wallet_address')
            ->map(function ($address) {
                return strtolower($address);
            })
            ->unique()
            ->values()
            ->toArray();

        return $users;
    }

    private function generateMerkleTree(array $addresses): array
    {
        // Trier les adresses pour assurer la reproductibilité
        sort($addresses);
        
        // Créer les feuilles (hashes des adresses)
        $leaves = array_map(function ($address) {
            return $this->keccak256(pack('H*', substr($address, 2))); // Retirer le '0x'
        }, $addresses);

        // Construire l'arbre
        $tree = $this->buildMerkleTree($leaves);
        
        // Générer les preuves pour chaque adresse
        $proofs = [];
        foreach ($addresses as $index => $address) {
            $proofs[$address] = $this->generateProof($leaves, $index);
        }

        return [
            'root' => '0x' . bin2hex($tree[0]),
            'addresses' => $addresses,
            'proofs' => $proofs,
            'leaves' => array_map(function ($leaf) {
                return '0x' . bin2hex($leaf);
            }, $leaves)
        ];
    }

    private function buildMerkleTree(array $leaves): array
    {
        if (count($leaves) === 0) {
            return [];
        }

        $tree = [$leaves];
        $currentLevel = $leaves;

        while (count($currentLevel) > 1) {
            $nextLevel = [];
            
            for ($i = 0; $i < count($currentLevel); $i += 2) {
                $left = $currentLevel[$i];
                $right = isset($currentLevel[$i + 1]) ? $currentLevel[$i + 1] : $left;
                
                // Trier les hashes pour assurer la cohérence
                if (strcmp(bin2hex($left), bin2hex($right)) > 0) {
                    $temp = $left;
                    $left = $right;
                    $right = $temp;
                }
                
                $combined = $left . $right;
                $nextLevel[] = $this->keccak256($combined);
            }
            
            $tree[] = $nextLevel;
            $currentLevel = $nextLevel;
        }

        return array_reverse($tree);
    }

    private function generateProof(array $leaves, int $index): array
    {
        $proof = [];
        $currentIndex = $index;
        $currentLevel = $leaves;

        while (count($currentLevel) > 1) {
            $nextLevel = [];
            
            for ($i = 0; $i < count($currentLevel); $i += 2) {
                $left = $currentLevel[$i];
                $right = isset($currentLevel[$i + 1]) ? $currentLevel[$i + 1] : $left;
                
                if ($i === $currentIndex || $i + 1 === $currentIndex) {
                    // Ajouter le sibling à la preuve
                    if ($currentIndex % 2 === 0) {
                        // L'index est pair, donc on prend le frère de droite
                        $proof[] = '0x' . bin2hex($right);
                    } else {
                        // L'index est impair, donc on prend le frère de gauche
                        $proof[] = '0x' . bin2hex($left);
                    }
                }
                
                // Trier les hashes
                if (strcmp(bin2hex($left), bin2hex($right)) > 0) {
                    $temp = $left;
                    $left = $right;
                    $right = $temp;
                }
                
                $combined = $left . $right;
                $nextLevel[] = $this->keccak256($combined);
            }
            
            $currentIndex = intval($currentIndex / 2);
            $currentLevel = $nextLevel;
        }

        return $proof;
    }

    private function keccak256(string $data): string
    {
        // Implémentation alternative sans la bibliothèque kornrunner/keccak
        // Utilise hash() avec sha3-256 comme alternative
        return hash('sha3-256', $data, true);
    }
}

