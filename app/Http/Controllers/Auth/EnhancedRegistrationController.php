<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Referral;
use App\Models\InfluencerPool;
use App\Models\Influencer;
use App\Providers\RouteServiceProvider;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EnhancedRegistrationController extends Controller
{
    /**
     * Display the enhanced registration view.
     */
    public function create(): View
    {
        return view('auth.register-enhanced');
    }

    /**
     * Handle an incoming enhanced registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'preferred_language' => ['required', 'string', 'in:fr,en,es,de'],
            'referral_code' => ['nullable', 'string', 'regex:/^REF-[A-Z0-9]{6}$/'],
        ]);

        DB::beginTransaction();
        
        try {
            // Générer un code de parrainage unique pour le nouvel utilisateur
            $userReferralCode = $this->generateUniqueReferralCode();
            
            // Créer l'utilisateur
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'preferred_language' => $request->preferred_language,
                'referral_code' => $userReferralCode,
                'balance' => 500, // Bonus de bienvenue
                'autoplay_active' => false,
                'status' => 'available',
            ]);

            // Traiter le code de parrainage si fourni
            $referralBonus = 0;
            if ($request->filled('referral_code')) {
                $referralBonus = $this->processReferral($user, $request->referral_code);
            }

            // Assigner l'utilisateur au pool d'influenceurs approprié
            $this->assignToInfluencerPool($user, $request->preferred_language);

            DB::commit();

            event(new Registered($user));
            Auth::login($user);

            // Message de succès personnalisé
            $successMessage = 'Compte créé avec succès ! Bonus de bienvenue : 500 SNT';
            if ($referralBonus > 0) {
                $successMessage .= " + Bonus parrainage : {$referralBonus} SNT";
            }

            return redirect(RouteServiceProvider::HOME)->with('success', $successMessage);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur lors de l\'inscription améliorée: ' . $e->getMessage());
            
            return back()->withErrors([
                'registration' => 'Une erreur est survenue lors de l\'inscription. Veuillez réessayer.'
            ])->withInput();
        }
    }

    /**
     * Générer un code de parrainage unique
     */
    private function generateUniqueReferralCode(): string
    {
        do {
            $code = 'REF-' . strtoupper(Str::random(6));
        } while (User::where('referral_code', $code)->exists());

        return $code;
    }

    /**
     * Traiter le parrainage
     */
    private function processReferral(User $user, string $referralCode): int
    {
        // Trouver l'utilisateur parrain
        $referrer = User::where('referral_code', $referralCode)->first();
        
        if (!$referrer) {
            throw new \Exception('Code de parrainage invalide');
        }

        // Vérifier que l'utilisateur ne se parraine pas lui-même
        if ($referrer->id === $user->id) {
            throw new \Exception('Vous ne pouvez pas utiliser votre propre code de parrainage');
        }

        $bonusAmount = 100;

        // Créer l'enregistrement de parrainage
        Referral::create([
            'referrer_id' => $referrer->id,
            'referred_id' => $user->id,
            'referral_code' => $referralCode,
            'status' => 'pending', // Sera validé après la première transaction
            'reward_amount' => $bonusAmount,
            'validated_at' => null,
        ]);

        // Ajouter le bonus immédiat au nouvel utilisateur
        $user->increment('balance', $bonusAmount);

        Log::info("Parrainage créé: {$referrer->name} -> {$user->name} avec le code {$referralCode}");

        return $bonusAmount;
    }

    /**
     * Assigner l'utilisateur au pool d'influenceurs approprié
     */
    private function assignToInfluencerPool(User $user, string $language): void
    {
        // Mapping des langues vers les pools
        $poolMapping = [
            'fr' => 1, // Pool Français
            'en' => 2, // Pool English
            'es' => 3, // Pool Español
            'de' => 1, // Par défaut pool français si pas de pool allemand
        ];

        $poolId = $poolMapping[$language] ?? 1; // Par défaut pool français

        // Vérifier si le pool existe, sinon créer les pools par défaut
        $this->ensureInfluencerPoolsExist();
        
        $pool = InfluencerPool::find($poolId);
        
        if ($pool) {
            // Créer l'enregistrement influenceur
            Influencer::create([
                'user_id' => $user->id,
                'pool_id' => $poolId,
                'milestone' => 5000, // Objectif par défaut
                'current_referrals' => 0,
                'total_avax_spent' => 0,
                'conversion_rate' => 0,
                'has_claimed_reward' => false,
            ]);

            Log::info("Utilisateur {$user->name} assigné au pool d'influenceurs {$pool->name}");
        }
    }

    /**
     * S'assurer que les pools d'influenceurs par défaut existent
     */
    private function ensureInfluencerPoolsExist(): void
    {
        $defaultPools = [
            [
                'id' => 1,
                'name' => 'Influenceurs Français',
                'language' => 'français',
                'pool_milestone' => 30000,
                'reward_amount' => 10,
                'current_referrals' => 0,
                'is_active' => true,
            ],
            [
                'id' => 2,
                'name' => 'English Influencers',
                'language' => 'english',
                'pool_milestone' => 50000,
                'reward_amount' => 25,
                'current_referrals' => 0,
                'is_active' => true,
            ],
            [
                'id' => 3,
                'name' => 'Influenciadores Españoles',
                'language' => 'español',
                'pool_milestone' => 20000,
                'reward_amount' => 8,
                'current_referrals' => 0,
                'is_active' => true,
            ],
        ];

        foreach ($defaultPools as $poolData) {
            InfluencerPool::firstOrCreate(
                ['id' => $poolData['id']],
                $poolData
            );
        }
    }

    /**
     * Afficher la page d'inscription avec code de parrainage pré-rempli
     */
    public function createWithReferral(Request $request): View
    {
        $referralCode = $request->query('ref');
        
        // Valider le code de parrainage si fourni
        if ($referralCode) {
            $referrer = User::where('referral_code', $referralCode)->first();
            if (!$referrer) {
                return redirect()->route('register.enhanced')
                    ->withErrors(['referral_code' => 'Code de parrainage invalide']);
            }
        }

        return view('auth.register-enhanced', compact('referralCode'));
    }
}

