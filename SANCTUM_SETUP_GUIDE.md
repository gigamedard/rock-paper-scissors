# 🔧 Configuration Sanctum Complète pour Rock Paper Scissors

## 📋 Étape 1: Installation Sanctum

```bash
# Installer Sanctum
composer require laravel/sanctum

# Publier la configuration et les migrations
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"

# Exécuter les migrations
php artisan migrate
```

## 📋 Étape 2: Configuration des Fichiers

### 1. **app/Models/User.php** - Ajouter HasApiTokens

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens; // ← AJOUTER CETTE LIGNE

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable; // ← AJOUTER HasApiTokens

    protected $fillable = [
        'name',
        'email',
        'password',
        'wallet_address',
        'language',
        'referral_code',
        'balance',
        'is_online',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_online' => 'boolean',
    ];

    // Relations existantes...
    public function referrals()
    {
        return $this->hasMany(Referral::class, 'referrer_id');
    }

    public function referredBy()
    {
        return $this->belongsTo(Referral::class, 'referred_id');
    }
}
```

### 2. **bootstrap/app.php** - Configuration Sanctum (Laravel 11)

```php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Ajouter Sanctum middleware pour les API
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->alias([
            'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
```

### 3. **config/sanctum.php** - Configuration CORS

```php
<?php

return [
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s',
        'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
        Illuminate\Support\Str::startsWith(app()->environment(), 'local') ? ',localhost:8080,localhost:8000' : ''
    ))),

    'guard' => ['web'],

    'expiration' => null,

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => App\Http\Middleware\EncryptCookies::class,
        'validate_csrf_token' => App\Http\Middleware\VerifyCsrfToken::class,
    ],
];
```

### 4. **routes/api.php** - Routes API avec protection Sanctum

```php
<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WalletAuthController;
use App\Http\Controllers\ReferralController;
use App\Http\Controllers\InfluencerController;
use App\Http\Controllers\EscrowController;

// Routes d'authentification (publiques)
Route::post('/wallet/generate-message', [WalletAuthController::class, 'generateMessage']);
Route::post('/wallet/verify-signature', [WalletAuthController::class, 'verifySignature']);

// Routes publiques pour les API
Route::get('/referral/leaderboard', [ReferralController::class, 'leaderboard']);
Route::post('/referral/validate', [ReferralController::class, 'validate']);
Route::get('/whitelist', [ReferralController::class, 'whitelist']);
Route::get('/influencer/pools', [InfluencerController::class, 'pools']);
Route::get('/influencer/leaderboard', [InfluencerController::class, 'leaderboard']);
Route::get('/escrow/stats', [EscrowController::class, 'stats']);
Route::get('/escrow/trades', [EscrowController::class, 'trades']);

// Routes protégées par Sanctum (nécessitent token Bearer)
Route::middleware('auth:sanctum')->group(function () {
    // User info
    Route::get('/user', function (Request $request) {
        return response()->json([
            'user' => $request->user(),
            'locale' => $request->user()->language
        ]);
    });

    // Logout
    Route::post('/logout', [WalletAuthController::class, 'logout']);

    // Referral system (protégé)
    Route::post('/referral/apply', [ReferralController::class, 'applyCodeFromAuthUser']);
    Route::get('/referral/status', [ReferralController::class, 'getStatus']);

    // Influencer system (protégé)
    Route::post('/influencer/claim-reward', [InfluencerController::class, 'claimReward']);

    // Escrow system (protégé)
    Route::post('/escrow/create-trade', [EscrowController::class, 'createTrade']);
});
```

### 5. **app/Http/Controllers/WalletAuthController.php** - Mise à jour avec Sanctum

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class WalletAuthController extends Controller
{
    public function generateMessage(Request $request)
    {
        $validated = $request->validate([
            'wallet_address' => 'required|string|regex:/^0x[a-fA-F0-9]{40}$/',
            'locale' => 'nullable|string|max:10',
        ]);

        $nonce = bin2hex(random_bytes(16));
        $nonceHash = hash('sha256', $nonce);

        $sessionData = [
            'nonce_hash' => $nonceHash,
            'nonce_timestamp' => time(),
            'nonce' => $nonce,
        ];

        if (!empty($validated['locale'])) {
            $sessionData['locale'] = $validated['locale'];
        }

        session($sessionData);

        return response()->json([
            'message' => "Sign this message to verify your wallet: {$nonce}",
            'nonce' => $nonce,
        ]);
    }

    public function verifySignature(Request $request)
    {
        $validated = $request->validate([
            'wallet_address' => 'required|string|regex:/^0x[a-fA-F0-9]{40}$/',
            'signature' => 'required|string|regex:/^0x[a-fA-F0-9]{130}$/',
            'locale' => 'nullable|string|max:10',
        ]);

        $nonceHash = session('nonce_hash');
        $nonceTimestamp = session('nonce_timestamp');

        if (!$nonceHash || !$nonceTimestamp || (time() - $nonceTimestamp > 300)) {
            return response()->json(['message' => 'Nonce expired or invalid'], 400);
        }

        $nonce = session('nonce');
        $message = "Sign this message to verify your wallet: {$nonce}";

        if (!hash_equals($nonceHash, hash('sha256', $nonce))) {
            return response()->json(['message' => 'Invalid or tampered nonce'], 400);
        }

        session()->forget(['nonce', 'nonce_hash', 'nonce_timestamp']);

        try {
            $recoveredAddress = $this->recoverAddressFromSignature($message, $validated['signature']);
            
            if (hash_equals(strtolower($recoveredAddress), strtolower($validated['wallet_address']))) {
                // Trouve ou crée l'utilisateur
                $user = User::firstOrCreate(
                    ['wallet_address' => strtolower($recoveredAddress)],
                    [
                        'name' => $this->generateReadableName($recoveredAddress),
                        'email' => $this->fromUsername($this->generateReadableName($recoveredAddress)),
                        'password' => bcrypt(hash('sha256', $recoveredAddress)),
                    ]
                );

                $user->update(['is_online' => true]);

                // Gestion de la langue
                $locale = $validated['locale'] ?? session('locale') ?? $user->language;
                if (!empty($locale)) {
                    $user->language = $locale;
                    $user->save();
                    session(['locale' => $locale]);
                }

                // Connexion pour les sessions Blade
                Auth::login($user);

                // ✅ CRÉATION DU TOKEN SANCTUM
                $token = $user->createToken('auth_token')->plainTextToken;

                return response()->json([
                    'message' => 'Authenticated successfully',
                    'token' => $token, // ← TOKEN SANCTUM
                    'user' => $user,
                    'locale' => $user->language,
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

    // ✅ NOUVELLE MÉTHODE LOGOUT
    public function logout(Request $request)
    {
        // Révoquer le token actuel
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully'
        ]);
    }

    // Méthodes helper existantes...
    private function recoverAddressFromSignature($message, $signature)
    {
        // Votre implémentation existante
        // ...
    }

    private function generateReadableName($address)
    {
        // Votre implémentation existante
        // ...
    }

    private function fromUsername($username)
    {
        // Votre implémentation existante
        // ...
    }
}
```

### 6. **app/Http/Controllers/ReferralController.php** - Mise à jour pour Sanctum

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Referral;

class ReferralController extends Controller
{
    // ✅ MÉTHODE PROTÉGÉE - Nécessite auth:sanctum
    public function applyCodeFromAuthUser(Request $request)
    {
        $validated = $request->validate([
            'referral_code' => 'required|string|max:20',
        ]);

        $user = $request->user(); // ← Maintenant disponible grâce à Sanctum

        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }

        // Vérifier si l'utilisateur a déjà un parrainage
        $existingReferral = Referral::where('referred_id', $user->id)->first();
        if ($existingReferral) {
            return response()->json(['message' => 'User already has a referral'], 400);
        }

        // Trouver le parrain
        $referrer = User::where('referral_code', $validated['referral_code'])->first();
        if (!$referrer) {
            return response()->json(['message' => 'Invalid referral code'], 400);
        }

        if ($referrer->id === $user->id) {
            return response()->json(['message' => 'Cannot refer yourself'], 400);
        }

        // Créer le parrainage
        $referral = Referral::create([
            'referrer_id' => $referrer->id,
            'referred_id' => $user->id,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Referral applied successfully',
            'referral' => $referral,
        ]);
    }

    // ✅ MÉTHODE PROTÉGÉE - Status utilisateur
    public function getStatus(Request $request)
    {
        $user = $request->user();

        $referralStats = [
            'user_id' => $user->id,
            'referral_code' => $user->referral_code,
            'total_referrals' => Referral::where('referrer_id', $user->id)->count(),
            'validated_referrals' => Referral::where('referrer_id', $user->id)
                ->where('status', 'validated')->count(),
            'pending_referrals' => Referral::where('referrer_id', $user->id)
                ->where('status', 'pending')->count(),
        ];

        return response()->json($referralStats);
    }

    // Méthodes publiques (pas de changement)
    public function leaderboard()
    {
        // Votre implémentation existante...
    }

    public function validate(Request $request)
    {
        // Votre implémentation existante...
    }

    public function whitelist()
    {
        // Votre implémentation existante...
    }
}
```

## 📋 Étape 3: Test Frontend

### **Mise à jour du JavaScript Frontend**

```javascript
// Dans votre fichier HTML/JS existant
async function loginWithWallet(locale) {
    if (!window.ethereum) {
        alert('Please install MetaMask!');
        return;
    }

    const web3 = new Web3(window.ethereum);
    const API_URL = 'http://127.0.0.1:8000/api';

    try {
        // 1. Connexion wallet
        await window.ethereum.request({ method: 'eth_requestAccounts' });
        const accounts = await web3.eth.getAccounts();
        const walletAddress = accounts[0];

        // 2. Générer message
        const messageResponse = await fetch(`${API_URL}/wallet/generate-message`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                wallet_address: walletAddress,
                locale: locale 
            }),
        });

        const { message } = await messageResponse.json();

        // 3. Signer message
        const signature = await web3.eth.personal.sign(message, walletAddress);

        // 4. Vérifier signature
        const verifyResponse = await fetch(`${API_URL}/wallet/verify-signature`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                wallet_address: walletAddress,
                signature: signature,
                locale: locale
            }),
        });

        const data = await verifyResponse.json();

        if (verifyResponse.ok && data.token) {
            // ✅ STOCKER LE TOKEN SANCTUM
            localStorage.setItem('auth_token', data.token);
            localStorage.setItem('user', JSON.stringify(data.user));
            localStorage.setItem('locale', data.locale);

            console.log('Login successful!', data);
            
            // Test d'une API protégée
            await testProtectedAPI();
        }
    } catch (error) {
        console.error('Login error:', error);
    }
}

// ✅ FONCTION POUR TESTER LES API PROTÉGÉES
async function testProtectedAPI() {
    const token = localStorage.getItem('auth_token');
    const API_URL = 'http://127.0.0.1:8000/api';

    try {
        // Test de l'API user
        const userResponse = await fetch(`${API_URL}/user`, {
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            }
        });

        if (userResponse.ok) {
            const userData = await userResponse.json();
            console.log('✅ Protected API working!', userData);
        }

        // Test d'application de code de parrainage
        const referralResponse = await fetch(`${API_URL}/referral/apply`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                referral_code: 'REF-TEST01'
            })
        });

        const referralData = await referralResponse.json();
        console.log('Referral API response:', referralData);

    } catch (error) {
        console.error('Protected API error:', error);
    }
}

// ✅ FONCTION LOGOUT
async function logout() {
    const token = localStorage.getItem('auth_token');
    const API_URL = 'http://127.0.0.1:8000/api';

    try {
        await fetch(`${API_URL}/logout`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            }
        });

        // Nettoyer le localStorage
        localStorage.removeItem('auth_token');
        localStorage.removeItem('user');
        localStorage.removeItem('locale');

        console.log('Logged out successfully');
    } catch (error) {
        console.error('Logout error:', error);
    }
}
```

## 🎯 Résultat Final

Après ces modifications :

✅ **Sanctum installé et configuré**
✅ **Tokens Bearer fonctionnels**
✅ **$request->user() disponible dans les contrôleurs protégés**
✅ **Plus de redirections 302**
✅ **Système de parrainage fonctionnel**
✅ **Logout avec révocation de token**

## 🧪 Test Rapide

```bash
# 1. Installer Sanctum
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate

# 2. Démarrer le serveur
php artisan serve

# 3. Tester avec votre frontend HTML
```

Votre authentification wallet + système de parrainage devrait maintenant fonctionner parfaitement ! 🚀

