<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Économie du jeu — Formule Q & frais
    |--------------------------------------------------------------------------
    |
    | Le seuil effectif d'une session utilisant `target_q` est compensé des
    | frais payés par le joueur pour rejoindre la pool :
    |
    |     seuil_effectif = target_q * (10000 + feeBasisPoints) / 10000
    |
    | Exemple : target_q = 2.0 et frais 2.5 % -> seuil 2.05, de sorte que le
    | joueur gagne réellement 2x ce qu'il a déposé (frais inclus).
    |
    | Les multiplicateurs de NIVEAU (config/game_levels.php) restent BRUTS :
    | ils ne sont PAS compensés des frais (décision : factorisation target_q only).
    |
    | Les frais de réseau (gas) ne sont PAS inclus.
    */

    // Taux de frais contrat, en pourcentage (doit correspondre à feeBasisPoints/100
    // du contrat Battlepool ; surchargeable par GameSetting 'smart_contract_fee_percentage').
    'fee_percent' => env('SMART_CONTRACT_FEE_PERCENT', 2.5),

    // Parité de référence XOF par AVAX (parité de jeu fixe, utilisée pour
    // l'affichage des mises en franc CFA d'Afrique de l'Ouest).
    'xof_per_avax' => env('XOF_PER_AVAX', 4900),

    'q' => [
        // Plafonds du target_q BRUT, selon le statut du joueur. Le seuil effectif
        // reste compensé des frais (voir plus haut).
        //   - max_q_base    : joueur sans condition (par défaut)
        //   - max_q_referral: joueur avec >= referrals_required_for_max filleuls validés
        //   - max_q_hard    : plafond absolu atteignable via cartes ceiling_increase
        //   - max_q_special : plafond pour les cas spéciaux (carte/récompense dédiée, admin)
        //
        // Règle métier : les valeurs intermédiaires (1.3, 1.5, 2.0) se débloquent
        // UNIQUEMENT par cartes ; le défaut est 1.2. Le 4.0 est réservé aux cas spéciaux.
        'max_q_base' => 1.2,
        'max_q_referral' => 2.0,
        'max_q_hard' => 3.0,
        'max_q_special' => 4.0,

        // Nombre de filleuls VALIDÉS requis pour débloquer max_q_referral.
        'referrals_required_for_max' => 2,

        // Valeur target_q par défaut (si non fournie). Doit rester <= max_q_base.
        'default_target_q' => 1.2,

        // Valeurs target_q proposées à l'UI (déblocage par cartes / statut joueur).
        'target_q_options' => [1.001, 1.2, 1.3, 1.5, 2, 3, 4],
    ],

    // Liste exhaustive des mises de base (point 4).
    // Référence XOF : 1 AVAX = 4900 XOF, K = 1000, frais = 2.5%.
    //   brut_AVAX = baseBet * K * (1 + frais)   ->   brut_XOF = brut_AVAX * R
    //   0.0004 AVAX -> 0.41  AVAX brut -> ~2 009 XOF
    //   0.15   AVAX -> 153.75 AVAX brut -> ~753 375 XOF
    'base_bets' => [0.0004, 0.15],

    // Cooldown FIXE par palier de mise (minutes). Le joueur subit exactement
    // cette durée ; seules les cartes cooldown_reduction la réduisent.
    //   0.0004 AVAX -> 3 jours
    //   0.15   AVAX -> 3 semaines
    'cooldowns' => [
        '0.0004' => 3 * 24 * 60,      // 4320 min
        '0.15' => 3 * 7 * 24 * 60,    // 30240 min
    ],
    'cooldown_default' => 3 * 24 * 60,

    /*
    |--------------------------------------------------------------------------
    | Parrainage
    |--------------------------------------------------------------------------
    |
    | Les bonus (filleul et parrain) sont crédités dans `locked_balance` :
    | un crédit de jeu NON VENDABLE, utilisable uniquement pour acheter des
    | cartes sur le shop. Il ne peut pas être retiré ni échangé.
    |
    | - referee_bonus : de quoi acheter 2 cartes de cooldown (Kaioken = 20 SNT).
    | - milestones    : récompense parrain par nombre de filleuls VALIDÉS.
    |
    | Le parrainage est validé à la PREMIÈRE SESSION du filleul.
    */
    'referral' => [
        // Bonus filleul : 2 x Kaioken (20 SNT) = 40 SNT
        'referee_bonus' => 40,

        // Bonus parrain par palier de filleuls validés
        'milestones' => [
            1 => 40,
            3 => 150,
            5 => 400,
            11 => 1000,
            50 => 5000,
            100 => 15000,
        ],
    ],

    // Prix en token des cartes (point 6). Source d'exécution = table `cards`
    // (CardSeeder.php) ; cette carte est la référence de lecture/tarification.
    // Peg indicatif : 1 PRANA = 100 XOF. Palier 2 = ×380 du palier 1.
    // Devise : SNT.
    'card_prices' => [
        // Passes cooldown — palier 1 (-3840 min : 3j -> 8h)
        'Kaioken' => 20,
        'Kaioken x10' => 50,
        'Kaioken x20' => 100,
        // Passes cooldown — palier 2 (-25920 min : 3 sem -> 3j)
        'Instant Transmission' => 7600,
        'Instant Transmission x10' => 19000,
        'Instant Transmission x100' => 38000,
        // Ceilings — palier 1 (+0.02..+1.00, 20 sessions)
        'Super Saiyan' => 10,
        'Super Saiyan 2' => 50,
        'Super Saiyan God' => 150,
        'Super Saiyan Blue' => 300,
        'Super Saiyan Blue Evolved' => 500,
        // Ceilings — palier 2 (10 sessions)
        'Super Saiyan Rosé' => 3800,
        'Super Saiyan Blue Kaioken' => 19000,
        'Ultra Ego' => 57000,
        'Gohan Beast' => 114000,
        'Mastered Ultra Instinct' => 190000,
        // Cas spécial (débloque 4.0)
        'Super Saiyan 4' => 5000,
    ],
];
