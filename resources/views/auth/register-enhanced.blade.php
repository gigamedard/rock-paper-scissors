<x-guest-layout>
    <div class="min-h-screen bg-gradient-to-br from-purple-900 via-blue-900 to-indigo-900 flex items-center justify-center p-4">
        <div class="max-w-md w-full bg-white/10 backdrop-blur-lg rounded-2xl shadow-2xl p-8 border border-white/20">
            
            <!-- En-tête avec logo -->
            <div class="text-center mb-8">
                <div class="mx-auto w-16 h-16 bg-gradient-to-r from-purple-500 to-blue-500 rounded-full flex items-center justify-center mb-4">
                    <span class="text-2xl">🎮</span>
                </div>
                <h1 class="text-3xl font-bold text-white mb-2">Rejoignez-nous !</h1>
                <p class="text-gray-300">Créez votre compte Rock Paper Scissors</p>
            </div>

            <form method="POST" action="{{ route('register') }}" id="registration-form">
                @csrf
                
                <!-- Sélection de langue -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-200 mb-3">
                        🌍 Choisissez votre langue
                    </label>
                    <div class="grid grid-cols-2 gap-3">
                        <button type="button" class="language-btn active" data-lang="fr" data-flag="🇫🇷">
                            <span class="text-2xl">🇫🇷</span>
                            <span>Français</span>
                        </button>
                        <button type="button" class="language-btn" data-lang="en" data-flag="🇺🇸">
                            <span class="text-2xl">🇺🇸</span>
                            <span>English</span>
                        </button>
                        <button type="button" class="language-btn" data-lang="es" data-flag="🇪🇸">
                            <span class="text-2xl">🇪🇸</span>
                            <span>Español</span>
                        </button>
                        <button type="button" class="language-btn" data-lang="de" data-flag="🇩🇪">
                            <span class="text-2xl">🇩🇪</span>
                            <span>Deutsch</span>
                        </button>
                    </div>
                    <input type="hidden" name="preferred_language" id="preferred_language" value="fr">
                </div>

                <!-- Code de parrainage -->
                <div class="mb-6">
                    <label for="referral_code" class="block text-sm font-medium text-gray-200 mb-2">
                        🎁 Code de parrainage (optionnel)
                    </label>
                    <div class="relative">
                        <input 
                            type="text" 
                            id="referral_code" 
                            name="referral_code" 
                            value="{{ request('ref') }}"
                            placeholder="REF-XXXXX"
                            class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                        />
                        <div class="absolute inset-y-0 right-0 flex items-center pr-3">
                            <span class="text-gray-400">💰</span>
                        </div>
                    </div>
                    <p class="text-xs text-gray-400 mt-1">
                        Gagnez 100 SNT bonus avec un code de parrainage valide !
                    </p>
                </div>

                <!-- Nom d'utilisateur -->
                <div class="mb-4">
                    <label for="name" class="block text-sm font-medium text-gray-200 mb-2">
                        👤 Nom d'utilisateur
                    </label>
                    <input 
                        type="text" 
                        id="name" 
                        name="name" 
                        value="{{ old('name') }}" 
                        required 
                        autofocus 
                        autocomplete="name"
                        class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                        placeholder="Votre nom d'utilisateur"
                    />
                    <x-input-error :messages="$errors->get('name')" class="mt-2 text-red-400" />
                </div>

                <!-- Email -->
                <div class="mb-4">
                    <label for="email" class="block text-sm font-medium text-gray-200 mb-2">
                        📧 Adresse email
                    </label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        value="{{ old('email') }}" 
                        required 
                        autocomplete="username"
                        class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                        placeholder="votre@email.com"
                    />
                    <x-input-error :messages="$errors->get('email')" class="mt-2 text-red-400" />
                </div>

                <!-- Mot de passe -->
                <div class="mb-4">
                    <label for="password" class="block text-sm font-medium text-gray-200 mb-2">
                        🔒 Mot de passe
                    </label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        required 
                        autocomplete="new-password"
                        class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                        placeholder="Votre mot de passe"
                    />
                    <x-input-error :messages="$errors->get('password')" class="mt-2 text-red-400" />
                </div>

                <!-- Confirmation mot de passe -->
                <div class="mb-6">
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-200 mb-2">
                        🔒 Confirmer le mot de passe
                    </label>
                    <input 
                        type="password" 
                        id="password_confirmation" 
                        name="password_confirmation" 
                        required 
                        autocomplete="new-password"
                        class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                        placeholder="Confirmez votre mot de passe"
                    />
                    <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2 text-red-400" />
                </div>

                <!-- Avantages du parrainage -->
                <div class="mb-6 p-4 bg-gradient-to-r from-purple-500/20 to-blue-500/20 rounded-lg border border-purple-500/30">
                    <h3 class="text-white font-semibold mb-2">🎉 Avantages de l'inscription :</h3>
                    <ul class="text-sm text-gray-300 space-y-1">
                        <li>• 🎁 Bonus de bienvenue : 500 SNT</li>
                        <li>• 💰 Avec code parrainage : +100 SNT</li>
                        <li>• 🏆 Accès aux pools d'influenceurs</li>
                        <li>• 💱 Trading P2P SNT ↔ AVAX</li>
                    </ul>
                </div>

                <!-- Bouton d'inscription -->
                <button 
                    type="submit" 
                    class="w-full bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-700 hover:to-blue-700 text-white font-semibold py-3 px-4 rounded-lg transition-all duration-200 transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 focus:ring-offset-transparent"
                >
                    🚀 Créer mon compte
                </button>

                <!-- Lien de connexion -->
                <div class="text-center mt-6">
                    <p class="text-gray-400 text-sm">
                        Déjà inscrit ? 
                        <a href="{{ route('login') }}" class="text-purple-400 hover:text-purple-300 font-medium">
                            Se connecter
                        </a>
                    </p>
                </div>
            </form>
        </div>
    </div>

    <style>
        .language-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem;
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 0.75rem;
            color: white;
            transition: all 0.2s;
            cursor: pointer;
        }

        .language-btn:hover {
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(147, 51, 234, 0.5);
            transform: translateY(-1px);
        }

        .language-btn.active {
            background: rgba(147, 51, 234, 0.3);
            border-color: rgb(147, 51, 234);
            box-shadow: 0 0 20px rgba(147, 51, 234, 0.3);
        }

        .language-btn span:last-child {
            font-size: 0.875rem;
            font-weight: 500;
        }
    </style>

    <script>
        // Gestion de la sélection de langue
        document.querySelectorAll('.language-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                // Retirer la classe active de tous les boutons
                document.querySelectorAll('.language-btn').forEach(b => b.classList.remove('active'));
                
                // Ajouter la classe active au bouton cliqué
                this.classList.add('active');
                
                // Mettre à jour le champ caché
                document.getElementById('preferred_language').value = this.dataset.lang;
            });
        });

        // Pré-remplir le code de parrainage depuis l'URL
        const urlParams = new URLSearchParams(window.location.search);
        const refCode = urlParams.get('ref');
        if (refCode) {
            document.getElementById('referral_code').value = refCode;
            
            // Afficher une notification de parrainage
            const notification = document.createElement('div');
            notification.className = 'fixed top-4 right-4 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg z-50';
            notification.innerHTML = `🎉 Code de parrainage détecté : ${refCode}`;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 5000);
        }

        // Validation du formulaire
        document.getElementById('registration-form').addEventListener('submit', function(e) {
            const referralCode = document.getElementById('referral_code').value;
            
            // Validation du format du code de parrainage (optionnel)
            if (referralCode && !referralCode.match(/^REF-[A-Z0-9]{6}$/)) {
                e.preventDefault();
                alert('Le code de parrainage doit avoir le format REF-XXXXXX');
                return false;
            }
        });
    </script>
</x-guest-layout>

