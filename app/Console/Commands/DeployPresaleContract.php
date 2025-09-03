<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;

class DeployPresaleContract extends Command
{
    protected $signature = 'presale:deploy {--merkle-file=whitelist.json}';
    protected $description = 'Deploy presale contract and set Merkle root';

    public function handle()
    {
        $this->info('Déploiement du contrat de prévente...');

        // Charger les données Merkle
        $merkleFile = $this->option('merkle-file');
        
        if (!Storage::disk('public')->exists($merkleFile)) {
            $this->error('Fichier Merkle non trouvé: ' . $merkleFile);
            $this->info('Exécutez d\'abord: php artisan merkle:generate');
            return 1;
        }

        $merkleData = json_decode(Storage::disk('public')->get($merkleFile), true);
        $merkleRoot = $merkleData['root'];

        $this->info('Racine Merkle: ' . $merkleRoot);

        // Déployer le contrat via le bridge Node.js
        $deploymentResult = $this->deployContract();
        
        if (!$deploymentResult['success']) {
            $this->error('Échec du déploiement: ' . $deploymentResult['error']);
            return 1;
        }

        $contractAddress = $deploymentResult['address'];
        $this->info('Contrat déployé à l\'adresse: ' . $contractAddress);

        // Configurer la racine Merkle
        $configResult = $this->setMerkleRoot($contractAddress, $merkleRoot);
        
        if (!$configResult['success']) {
            $this->error('Échec de la configuration: ' . $configResult['error']);
            return 1;
        }

        $this->info('Configuration terminée avec succès!');
        
        // Sauvegarder l'adresse du contrat
        $this->saveContractAddress($contractAddress);
        
        return 0;
    }

    private function deployContract(): array
    {
        try {
            $response = Http::post(env('NODE_URL') . '/deploy-presale', [
                'sntTokenAddress' => env('SNT_TOKEN_ADDRESS')
            ]);

            return $response->json();
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function setMerkleRoot(string $contractAddress, string $merkleRoot): array
    {
        try {
            $response = Http::post(env('NODE_URL') . '/set-merkle-root', [
                'contractAddress' => $contractAddress,
                'merkleRoot' => $merkleRoot
            ]);

            return $response->json();
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function saveContractAddress(string $address): void
    {
        $envFile = base_path('.env');
        $envContent = file_get_contents($envFile);
        
        if (strpos($envContent, 'PRESALE_CONTRACT_ADDRESS=') !== false) {
            $envContent = preg_replace(
                '/PRESALE_CONTRACT_ADDRESS=.*/',
                'PRESALE_CONTRACT_ADDRESS=' . $address,
                $envContent
            );
        } else {
            $envContent .= "\nPRESALE_CONTRACT_ADDRESS=" . $address;
        }
        
        file_put_contents($envFile, $envContent);
        $this->info('Adresse du contrat sauvegardée dans .env');
    }
}

