<?php 

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Web3\Utils;
use Elliptic\EC;
use kornrunner\Keccak;

class WalletAuthController extends Controller
{   


    private const EMAIL_DOMAIN = '';
    private const MIN_USERNAME_LENGTH = 0;
    private const MAX_USERNAME_LENGTH = 0;
    private $ec;

    public function __construct()
    {
        $this->ec = new EC('secp256k1');
        $this->EMAIL_DOMAIN = 'game.web3';
        $this->MIN_USERNAME_LENGTH = 9;
        $this->MAX_USERNAME_LENGTH = 20;
    }

    public function generateMessage(Request $request)
    {
        $validated = $request->validate([
            'wallet_address' => 'required|string',
        ]);

        $nonce = bin2hex(random_bytes(16));
        session(['nonce' => $nonce]);
        return response()->json([
            'message' => "Sign this message to verify your wallet: $nonce",
            'nonce' => $nonce,
        ]);
    }

    public function verifySignature(Request $request)
    {
        $validated = $request->validate([
            'wallet_address' => 'required|string',
            'signature' => 'required|string'
        ]);

        $nonce = session('nonce');
        if (!$nonce) {
            return response()->json(['message' => 'Invalid session'], 400);
        }

        $message = "Sign this message to verify your wallet: $nonce";

        try
        {
            $recoveredAddress = $this->recoverAddressFromSignature($message, $validated['signature']);
            
            if (strtolower($recoveredAddress) === strtolower($validated['wallet_address']))
            {

                //=============================================================================================================

                // Find or create user

                $user = User::where('wallet_address', strtolower($recoveredAddress))->first();

                if($user)
                {
                    // Update existing user
                    $user->update([
                        'is_online' => true,
                    ]);
                }
                else
                {
                    // Generate username and email for new users
                    $username = $this->generateReadableName($recoveredAddress);
                    while (User::where('name', $username)->exists()) {
                        $username = $this->generateReadableName($recoveredAddress);
                    }

                    $email = $this->fromUsername($username);

                    $user = User::create([
                        'wallet_address' => strtolower($recoveredAddress),
                        'name' => $username,
                        'email' => $email,
                        'password' => '$2y$12$joxkkYZUOEw7vlqGxcTsxu3zCaCaplc7jSfWJaP03AJmPtNwcfLPW',
                        'is_online' => true,
                    ]);
                }  
                    
                Auth::login($user);


                //=============================================================================================================

                return response()->json([
                    'message' => 'Authenticated successfully',
                    'recovered_address' => $recoveredAddress
                ]);
                
            }
            
            return response()->json(['message' => 'Invalid signature'], 401);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Verification failed',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function logout()
    {
        Auth::logout();
        return response()->json(['message' => 'Logged out successfully']);
    }

    private function recoverAddressFromSignature($message, $signature)
    {
        $signature = str_replace('0x', '', $signature);
    
        $r = substr($signature, 0, 64);
        $s = substr($signature, 64, 64);
        $v = substr($signature, 128, 2);
    
        if (!ctype_xdigit($r) || strlen($r) !== 64) {
            throw new \Exception("Invalid R value");
        }
    
        if (!ctype_xdigit($s) || strlen($s) !== 64) {
            throw new \Exception("Invalid S value");
        }
    
        if (!ctype_xdigit($v) || strlen($v) !== 2) {
            throw new \Exception("Invalid V value");
        }
    
        try {
            $rGmp = gmp_init($r, 16);  
            $sGmp = gmp_init($s, 16);
            $vInt = hexdec($v);
    
            $recid = $vInt - 27;
            if ($recid !== 0 && $recid !== 1) {
                throw new \Exception('Invalid recovery ID');
            }
    
            $msglen = strlen($message);
            $ethMessage = "\x19Ethereum Signed Message:\n" . $msglen . $message;
            $messageHash = Keccak::hash($ethMessage, 256);

            $pubkey = $this->ec->recoverPubKey(
                $messageHash,
                [
                    'r' => gmp_init($r, 16),
                    's' => gmp_init($s, 16)
                ],
                $recid
            );
    
            $pubkeyHex = $pubkey->encode('hex');
            return $this->publicKeyToAddress($pubkeyHex);
    
        } catch (\Exception $e) {
            throw new \Exception('Address recovery failed: ' . $e->getMessage());
        }
    }

    private function publicKeyToAddress($pubkeyHex)
    {
        if (strpos($pubkeyHex, '04') === 0) {
            $pubkeyHex = substr($pubkeyHex, 2);
        }
    
        try {
            $hash = Keccak::hash(hex2bin($pubkeyHex), 256);
            return '0x' . substr($hash, -40);
        } catch (\Exception $e) {
            throw new \Exception('Address conversion failed: ' . $e->getMessage());
        }
    }

    public function testRecovery(Request $request)
    {
        $signature = "0x316bde63906ec06d8062a5ed2b49c6ef5f2027a7bbe939f2b466ddc06507bbfe7e559a754cf5d360996c38de162ab15c0ede016c0c8b333f5907c9cb9dfda1fd1c";
        $expectedAddress = "0x150e7Fa493CfF859052b4cDE48B5Cb0A89764BAd";
        $nonce = $request->input('nonce', '');
        $message = "Sign this message to verify your wallet: " . $nonce;

        try {
            $recoveredAddress = $this->recoverAddressFromSignature($message, $signature);

            return response()->json([
                'success' => true,
                'recovered_address' => $recoveredAddress,
                'expected_address' => $expectedAddress,
                'matches' => strtolower($recoveredAddress) === strtolower($expectedAddress)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //===========================================HELPERS =======================================================================================================

    public function generateReadableName(string $walletAddress,string $prefix = 'user'): string 
    {
        // Get the first 6 characters of the address (excluding '0x')
        $address = strtolower(trim(str_replace('0x', '', $walletAddress)));
        $shortHash = substr($address, 0, 6);
        
        return $prefix . '_' . $shortHash.= rand(100, 999);
    }


    public function fromUsername(string $username,bool $includeRandomness = false): string
    {
        // Clean and validate username
        $cleanUsername = $this->sanitizeUsername($username);
        
        if ($includeRandomness) {
            $cleanUsername .= rand(100, 999);
        }

        return $this->generateEmail($cleanUsername);
    }


    public function generateEmail(string $username): string
    {
        return sprintf('%s@%s', $username, $this->EMAIL_DOMAIN);
    }

    public function sanitizeUsername(string $username): string
    {
        // Convert to lowercase and remove unwanted characters
        $clean = strtolower(trim($username));
        $clean = preg_replace('/[^a-z0-9.]/', '', $clean);
        
        // Validate length
        if (strlen($clean) < $this->MIN_USERNAME_LENGTH) {
            throw new \InvalidArgumentException(
                sprintf('Username must be at least %d characters long', $this->MIN_USERNAME_LENGTH)
            );
        }

        // Truncate if too long
        if (strlen($clean) > $this->MAX_USERNAME_LENGTH) {
            $clean = substr($clean, 0, $this->MAX_USERNAME_LENGTH);
        }

        return $clean;
    }
    //=======================================================================================================================================================





}