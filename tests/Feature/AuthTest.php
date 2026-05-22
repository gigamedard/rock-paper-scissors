<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test nonce generation.
     */
    public function test_generatemessage()
    {
        $walletAddress = '0x1234567890abcdef1234567890abcdef12345678';

        // Make a request to generateNonce with wallet_address
        $response = $this->postJson('/api/wallet/generate-message', [
            'wallet_address' => $walletAddress,
        ]);

        // Assert the response structure
        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
            ]);

        // Assert that the challenge message is stored in the cache
        $cacheKey = 'login_challenge:' . strtolower($walletAddress);
        $this->assertTrue(Cache::has($cacheKey));
    }

    /**
     * Test successful signature verification using TEST_BYPASS.
     */
    public function test_verify_signature_success()
    {
        $walletAddress = '0x1234567890abcdef1234567890abcdef12345678';

        // Make a request to verifySignature using TEST_BYPASS signature
        $response = $this->postJson('/api/wallet/verify-signature', [
            'wallet_address' => $walletAddress,
            'signature' => 'TEST_BYPASS',
        ]);

        // Assert the response
        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'token',
                'user',
            ])
            ->assertJson([
                'message' => 'Authenticated successfully',
                'user' => [
                    'wallet_address' => strtolower($walletAddress),
                ]
            ]);
    }

    /**
     * Test expired nonce/missing cache.
     */
    public function test_verify_signature_expired_nonce()
    {
        $walletAddress = '0x1234567890abcdef1234567890abcdef12345678';

        // Make a request to verifySignature with a dummy signature (not TEST_BYPASS)
        // without generating a message first (cache is empty)
        $response = $this->postJson('/api/wallet/verify-signature', [
            'wallet_address' => $walletAddress,
            'signature' => '0x' . str_repeat('a', 128) . '1b',
        ]);

        // Assert the response
        $response->assertStatus(400)
            ->assertJson([
                'message' => 'Nonce expired or invalid',
            ]);
    }

    /**
     * Test invalid/tampered signature.
     */
    public function test_verify_signature_tampered_nonce()
    {
        $walletAddress = '0x1234567890abcdef1234567890abcdef12345678';

        // Seed a challenge into the cache
        Cache::put('login_challenge:' . strtolower($walletAddress), 'Sign this message to verify your wallet: dummy_nonce', 300);

        // Make a request with a dummy invalid signature that fails recovery or doesn't match
        $response = $this->postJson('/api/wallet/verify-signature', [
            'wallet_address' => $walletAddress,
            'signature' => '0x' . str_repeat('a', 128) . '1b',
        ]);

        // It should return 401 Unauthorized because signature is invalid/malformed
        $response->assertStatus(401);
    }
}
