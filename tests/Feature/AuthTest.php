<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test nonce generation.
     */
    public function test_generatemessage()
    {
        // Make a request to generateNonce
        $response = $this->postJson('/wallet/generate-message');

        // Assert the response structure
        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
            ]);

        // Assert that the nonce_hash and timestamp are stored in the session
        $this->assertNotNull(session('nonce_hash'));
        $this->assertNotNull(session('nonce_timestamp'));
    }

    /**
     * Test successful signature verification.
     */
    public function test_verify_signature_success()
    {
        // Mock the nonce and store it in the session
        $nonce = bin2hex(random_bytes(16));
        $nonceHash = hash('sha256', $nonce);

        Session::put([
            'nonce' => $nonce,
            'nonce_hash' => $nonceHash,
            'nonce_timestamp' => time(),
        ]);

        // Prepare the data for verification
        $walletAddress = '0x1234567890abcdef1234567890abcdef12345678';
        $message = "Sign this message to verify your wallet: $nonce";
        $signature = $this->mockSignature($message, $walletAddress);

        // Make a request to verifySignature
        $response = $this->postJson('/wallet/verify-signature', [
            'wallet_address' => $walletAddress,
            'signature' => $signature,
        ]);

        // Assert the response
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Authenticated successfully',
                'recovered_address' => strtolower($walletAddress),
            ]);

        // Assert session data was cleared
        $this->assertNull(session('nonce_hash'));
        $this->assertNull(session('nonce_timestamp'));
    }

    /**
     * Test expired nonce.
     */
    public function test_verify_signature_expired_nonce()
    {
        // Mock the nonce and store it in the session with an old timestamp
        $nonce = bin2hex(random_bytes(16));
        $nonceHash = hash('sha256', $nonce);

        Session::put([
            'nonce' => $nonce,
            'nonce_hash' => $nonceHash,
            'nonce_timestamp' => time() - 301, // Expired (more than 300 seconds ago)
        ]);

        // Prepare the data for verification
        $walletAddress = '0x1234567890abcdef1234567890abcdef12345678';
        $message = "Sign this message to verify your wallet: $nonce";
        $signature = $this->mockSignature($message, $walletAddress);

        // Make a request to verifySignature
        $response = $this->postJson('/wallet/verify-signature', [
            'wallet_address' => $walletAddress,
            'signature' => $signature,
        ]);

        // Assert the response
        $response->assertStatus(400)
            ->assertJson([
                'message' => 'Nonce expired or invalid',
            ]);
    }

    /**
     * Test tampered nonce.
     */
    public function test_verify_signature_tampered_nonce()
    {
        // Mock the nonce and store it in the session
        $nonce = bin2hex(random_bytes(16));
        $nonceHash = hash('sha256', $nonce);

        Session::put([
            'nonce' => $nonce, // The real nonce
            'nonce_hash' => $nonceHash, // The real nonce hash
            'nonce_timestamp' => time(),
        ]);

        // Prepare the data with a tampered nonce
        $walletAddress = '0x1234567890abcdef1234567890abcdef12345678';
        $tamperedMessage = "Sign this message to verify your wallet: tampered_nonce";
        $signature = $this->mockSignature($tamperedMessage, $walletAddress);

        // Make a request to verifySignature
        $response = $this->postJson('/wallet/verify-signature', [
            'wallet_address' => $walletAddress,
            'signature' => $signature,
        ]);

        // Assert the response
        $response->assertStatus(400)
            ->assertJson([
                'message' => 'Invalid or tampered nonce',
            ]);
    }

    /**
     * Helper method to mock a signature for testing purposes.
     * (Replace with real signature generation logic or mock)
     */
    private function mockSignature($message, $walletAddress)
    {
        // Mock signature generation for testing (use a real method in production)
        // This is just a placeholder and won't work for real signing
        return '0x' . str_repeat('a', 130); // Return a dummy signature
    }
}
