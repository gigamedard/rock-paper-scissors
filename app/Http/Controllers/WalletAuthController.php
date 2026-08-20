<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\ApiToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Elliptic\EC;
use kornrunner\Keccak;

class WalletAuthController extends Controller
{
    private $ec;

    public function __construct()
    {
        $this->ec = new EC('secp256k1');
    }

    /**
     * Generate challenge message
     */
    public function generateMessage(Request $request)
    {   
        
        $validated = $request->validate([
            'wallet_address' => 'required|string|regex:/^0x[a-fA-F0-9]{40}$/',
            'locale' => 'nullable|string|max:10',
        ]);
       
        try {
            $nonce = bin2hex(random_bytes(16));
            $message = "Sign this message to verify your wallet: {$nonce}";
            $address = strtolower($validated['wallet_address']);

            Cache::put("login_challenge:$address", $message, 300);
            if (!empty($validated['locale'])) {
            Cache::put("login_locale:$address", $validated['locale'], 300);
            }

            return response()->json(['message' => $message]);
        } catch (\Exception $e) {
            Log::error('Failed to generate message: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to generate message'], 500);
        }
    }

    /**
     * Verify signature & issue API token
     */
    public function verifySignature(Request $request)
    {
        $validated = $request->validate([
            'wallet_address' => 'required|string|regex:/^0x[a-fA-F0-9]{40}$/',
            'signature'      => 'required|string',
            'locale'         => 'nullable|string|max:10',
        ]);

        $address = strtolower($validated['wallet_address']);
        try {
            if ($validated['signature'] === 'TEST_BYPASS' && app()->environment('local', 'testing')) {
            $recovered = $address;
            Log::info("test_point_3: signature bypassed for testing (ENV=" . app()->environment() . ")");
        } else {
            $message = Cache::pull("login_challenge:$address");

            if (!$message) {
                return response()->json(['message' => 'Nonce expired or invalid'], 400);
            }

            Log::info("test_point_1: recovering address");
            $recovered = $this->recoverAddressFromSignature($message, $validated['signature']);
            Log::info("test_point_2: address recovered as " . $recovered);
            
            if (!hash_equals(strtolower($address), strtolower($recovered))) {
                usleep(random_int(100000, 300000)); 
                return response()->json(['message' => 'Invalid signature'], 401);
            }
            Log::info("test_point_3: signature valid");
        }

            // Find or create user
            $user = User::firstOrCreate(
                ['wallet_address' => $address],
                [
                    'name'     => $this->generateReadableName($address),
                    'email'    => $this->fromUsername($this->generateReadableName($address)),
                    'password' => bcrypt(hash('sha256', $address)),
                    'email_verified_at' => now(), // Mark as verified immediately
                ]
            );
            $user->update(['is_online' => true]);

            // Save locale
            $locale = $validated['locale'] ?? Cache::pull("login_locale:$address") ?? $user->language;
            if ($locale) {
                $user->language = $locale;
                $user->save();
            }

            // Issue custom API token
            $token = ApiToken::generateForUser($user, 60 * 24); // valid 24h

            if (!$user->referral_code) {
                $user->generateReferralCode();
            }

            $user->save();

            // Check if user has already applied a referral code
            $user->has_used_referral_code = $user->referredBy()->exists();

            return response()->json([
                'message' => 'Authenticated successfully',
                'token'   => $token,
                'user'    => $user,
                'locale'  => $user->language,
                'is_admin'=> $user->is_admin // Explicitly return this
            ]);

        } catch (\Exception $e) {
            Log::error('Signature verification failed', [
                'error' => $e->getMessage(),
                'wallet' => $address ?? 'unknown'
            ]);
            return response()->json(['message' => 'Authentication failed. Please try again.'], 401);
        }
    }

    
    
    public function logout(Request $request)
    {
        // Révoquer le token Sanctum actuel
        $request->user()->currentAccessToken()->delete();
        
        // Logout de la session aussi (pour compatibilité Blade)
        Auth::logout();
        
        return response()->json(['message' => 'Logged out successfully']);
    }

    private function recoverAddressFromSignature($message, $signature)
    {
        $signature = str_replace('0x', '', $signature);
        $r = substr($signature, 0, 64);
        $s = substr($signature, 64, 64);
        $v = substr($signature, 128, 2);

        $recid = hexdec($v) - 27;
        $msglen = strlen($message);
        $ethMessage = "\x19Ethereum Signed Message:\n" . $msglen . $message;
        $hash = Keccak::hash($ethMessage, 256);

        $pubkey = $this->ec->recoverPubKey($hash, [
            'r' => gmp_init($r, 16),
            's' => gmp_init($s, 16)
        ], $recid);

        $pubkeyHex = $pubkey->encode('hex');
        return $this->publicKeyToAddress($pubkeyHex);
    }

    private function publicKeyToAddress($pubkeyHex)
    {
        if (strpos($pubkeyHex, '04') === 0) {
            $pubkeyHex = substr($pubkeyHex, 2);
        }
        $hash = Keccak::hash(hex2bin($pubkeyHex), 256);
        return '0x' . substr($hash, -40);
    }

    private function generateReadableName(string $address, string $prefix = 'user'): string
    {
        $short = substr(str_replace('0x', '', strtolower($address)), 0, 6);
        return $prefix . '_' . $short . rand(100, 999);
    }

    private function fromUsername(string $username): string
    {
        return sprintf('%s@game.web3', strtolower($username));
    }

    public function loginByToken(Request $request)
    {
        $token = $request->query('token');

        if (!$token) {
            abort(401, 'Token required');
        }

        // Validate using custom ApiToken model
        $hashed = hash('sha256', $token);
        $accessToken = \App\Models\ApiToken::where('token', $hashed)->first();

        if (!$accessToken || !$accessToken->isValid()) {
             abort(401, 'Invalid token or expired');
        }

        // Log the user in via session guard
        Auth::login($accessToken->user);

        return redirect()->route('admin.settings.index');
    }

    /**
     * Simplified login for UI/Simulation (Bypass signature in local)
     * SECURITY: restricted to local/testing only. Production must use
     * verify-signature (real ECDSA signature) instead.
     */
    public function login(Request $request)
    {
        if (!app()->environment('local', 'testing')) {
            abort(403, 'Direct login is disabled in this environment. Use signature verification.');
        }

        $validated = $request->validate([
            'wallet_address' => 'required|string|regex:/^0x[a-fA-F0-9]{40}$/',
        ]);

        $address = strtolower($validated['wallet_address']);

        // Find or create user
        $user = User::firstOrCreate(
            ['wallet_address' => $address],
            [
                'name'     => $this->generateReadableName($address),
                'email'    => $this->fromUsername($this->generateReadableName($address)),
                'password' => bcrypt(hash('sha256', $address)),
                'email_verified_at' => now(),
            ]
        );

        $user->update(['is_online' => true]);

        if (!$user->referral_code) {
            $user->generateReferralCode();
        }

        // Issue custom API token
        $token = ApiToken::generateForUser($user, 60 * 24);

        return response()->json([
            'message' => 'Authenticated successfully',
            'token'   => $token,
            'user'    => $user,
        ]);
    }

    /**
     * Dev login sans MetaMask — utilise les comptes Hardhat déterministes.
     * GET /api/wallet/dev-login?player=1
     * player=0 → Admin (Hardhat #0), player=1-6 → comptes de test
     */
    public function devLogin(Request $request)
    {
        if (!app()->environment('local', 'testing')) {
            abort(403, 'Dev login only available in local/testing environment');
        }

        $player = (int) $request->query('player', 1);
        $accounts = [
            0 => '0xf39Fd6e51aad88F6F4ce6aB8827279cffFb92266',
            1 => '0x70997970C51812dc3A010C7d01b50e0d17dc79C8',
            2 => '0x3C44CdDdB6a900fa2b585dd299e03d12FA4293BC',
            3 => '0x90F79bf6EB2c4f870365E785982E1f101E93b906',
            4 => '0x15d34AAf54267DB7D7c367839AAf71A00a2C6A65',
            5 => '0x9965507D1a55bcC2695C58ba16FB37d819B0A4dc',
            6 => '0x976EA74026E726554dB657fA54763abd0C3a0aa9',
        ];

        if (!isset($accounts[$player])) {
            return response()->json(['message' => 'Invalid player index. Use 0-6.'], 400);
        }

        $address = strtolower($accounts[$player]);
        $locale = $request->query('locale', 'fr');

        $user = User::firstOrCreate(
            ['wallet_address' => $address],
            [
                'name'     => $this->generateReadableName($address),
                'email'    => $this->fromUsername($this->generateReadableName($address)),
                'password' => bcrypt(hash('sha256', $address)),
                'email_verified_at' => now(),
            ]
        );
        $user->update(['is_online' => true, 'language' => $locale]);

        $token = ApiToken::generateForUser($user, 60 * 24);

        if (!$user->referral_code) {
            $user->generateReferralCode();
        }
        $user->save();

        return response()->json([
            'message' => 'Dev login OK',
            'token'   => $token,
            'user'    => $user,
            'locale'  => $user->language,
            'is_admin'=> $user->is_admin,
        ]);
    }
}
