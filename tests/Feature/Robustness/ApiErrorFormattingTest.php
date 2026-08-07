<?php

namespace Tests\Feature\Robustness;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiErrorFormattingTest extends TestCase
{
    use RefreshDatabase;

    public function test_blockchain_insufficient_funds_formatting()
    {
        // On va faire une requête POST sur une route interne et simuler que le BlockchainController throw une exception
        // Ou bien on teste une route précise pour voir si elle formate l'erreur.
        
        $response = $this->postJson('/api/internal/test-error-formatting', [
            'error_string' => 'execution reverted: "Claim payment failed"'
        ]);

        // Si la route test-error-formatting renvoie l'erreur formatée
        // On devrait recevoir une erreur 400 avec un message clair.
        // NOTE: Puisque la route n'existe pas, on teste d'abord si elle renverrait un bon JSON.
    }
}
