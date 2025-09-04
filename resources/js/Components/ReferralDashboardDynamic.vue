<template>
    <div class="referral-dashboard">
        <!-- En-tête -->
        <div class="header">
            <h1>📊 Dashboard de Parrainage</h1>
            <p>Gagnez 100 SNT pour chaque parrainage validé</p>
        </div>

        <!-- Statistiques personnelles -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number">{{ personalStats.referrals }}</div>
                <div class="stat-label">Mes Parrainages</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">{{ personalStats.validated }}</div>
                <div class="stat-label">Validés</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">{{ personalStats.rewards.toLocaleString() }}</div>
                <div class="stat-label">SNT Gagnés</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">#{{ personalStats.rank }}</div>
                <div class="stat-label">Mon Rang</div>
            </div>
        </div>

        <!-- Code de parrainage -->
        <div class="referral-section">
            <h2>🔗 Votre Code de Parrainage</h2>
            <p>Partagez ce code pour gagner 100 SNT par utilisateur qui s'inscrit et effectue sa première transaction.</p>
            <div class="referral-code-container">
                <span class="referral-code">{{ referralCode }}</span>
                <button @click="copyReferralCode" class="copy-button">
                    {{ copyButtonText }}
                </button>
            </div>
            <p class="referral-link">
                <strong>Lien de parrainage :</strong> 
                <span>{{ referralLink }}</span>
            </p>
        </div>

        <!-- Classement -->
        <div class="leaderboard">
            <div class="leaderboard-header">
                <h2>🏆 Classement des Parraineurs</h2>
                <p>Top des utilisateurs avec le plus de parrainages validés</p>
            </div>
            
            <div v-if="loading" class="loading">
                <div class="spinner"></div>
                Chargement du classement...
            </div>
            
            <div v-else-if="error" class="error">
                <strong>Erreur de chargement</strong><br>
                {{ error }}
            </div>
            
            <div v-else>
                <div 
                    v-for="user in leaderboard" 
                    :key="user.rank"
                    class="leaderboard-item"
                    :class="{ 'current-user': user.isCurrentUser }"
                >
                    <div class="rank">#{{ user.rank }}</div>
                    <div class="user-info">
                        <div class="user-name">
                            {{ user.name }}
                            <span v-if="user.isCurrentUser">(Vous)</span>
                        </div>
                        <div class="user-address">{{ user.wallet_address }}</div>
                    </div>
                    <div class="user-stats">
                        <div class="referral-count">{{ user.referral_count }} parrainages</div>
                        <div class="reward-amount">{{ user.total_rewards.toLocaleString() }} SNT</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { usePage } from '@inertiajs/vue3'

// Props
const props = defineProps({
    userAddress: {
        type: String,
        default: null
    }
})

// Reactive data
const loading = ref(true)
const error = ref(null)
const leaderboard = ref([])
const copyButtonText = ref('📋 Copier')
let refreshInterval = null

// Données utilisateur simulées (à remplacer par les vraies données)
const personalStats = ref({
    referrals: 15,
    validated: 12,
    rewards: 1200,
    rank: 6
})

const referralCode = ref('REF-USER01')
const referralLink = ref(`https://rockpaperscissors.com/register?ref=${referralCode.value}`)

// Méthodes
const loadLeaderboard = async () => {
    try {
        loading.value = true
        error.value = null
        
        const response = await fetch('/api/referral/leaderboard.php')
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`)
        }
        
        const data = await response.json()
        
        if (!Array.isArray(data) || data.length === 0) {
            error.value = 'Aucune donnée disponible'
            return
        }
        
        // Marquer l'utilisateur actuel
        leaderboard.value = data.map(user => ({
            ...user,
            isCurrentUser: user.wallet_address === props.userAddress
        }))
        
    } catch (err) {
        console.error('Erreur lors du chargement du classement:', err)
        error.value = `Impossible de charger le classement: ${err.message}`
    } finally {
        loading.value = false
    }
}

const copyReferralCode = async () => {
    try {
        if (navigator.clipboard) {
            await navigator.clipboard.writeText(referralCode.value)
        } else {
            // Fallback pour les navigateurs plus anciens
            const textArea = document.createElement('textarea')
            textArea.value = referralCode.value
            document.body.appendChild(textArea)
            textArea.select()
            document.execCommand('copy')
            document.body.removeChild(textArea)
        }
        
        copyButtonText.value = '✅ Copié !'
        setTimeout(() => {
            copyButtonText.value = '📋 Copier'
        }, 2000)
        
    } catch (err) {
        console.error('Erreur lors de la copie:', err)
        alert(`Impossible de copier automatiquement. Code: ${referralCode.value}`)
    }
}

// Lifecycle hooks
onMounted(() => {
    loadLeaderboard()
    
    // Actualisation automatique toutes les 30 secondes
    refreshInterval = setInterval(loadLeaderboard, 30000)
})

onUnmounted(() => {
    if (refreshInterval) {
        clearInterval(refreshInterval)
    }
})
</script>

<style scoped>
.referral-dashboard {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.header {
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    color: white;
    padding: 30px;
    border-radius: 15px;
    text-align: center;
    margin-bottom: 30px;
}

.header h1 {
    font-size: 2.5em;
    margin-bottom: 10px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
    border: 2px solid #0ea5e9;
    border-radius: 15px;
    padding: 25px;
    text-align: center;
}

.stat-number {
    font-size: 2.5em;
    font-weight: bold;
    color: #0ea5e9;
    margin-bottom: 5px;
}

.stat-label {
    color: #64748b;
    font-size: 1.1em;
}

.referral-section {
    background: #f8fafc;
    border: 2px solid #e2e8f0;
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 30px;
}

.referral-code-container {
    margin: 15px 0;
}

.referral-code {
    background: #4f46e5;
    color: white;
    padding: 15px 25px;
    border-radius: 10px;
    font-family: monospace;
    font-size: 1.2em;
    font-weight: bold;
    display: inline-block;
}

.copy-button {
    background: #059669;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    margin-left: 15px;
    transition: all 0.2s;
}

.copy-button:hover {
    background: #047857;
    transform: translateY(-1px);
}

.referral-link {
    margin-top: 15px;
    color: #64748b;
}

.referral-link span {
    font-family: monospace;
}

.leaderboard {
    background: white;
    border: 2px solid #e2e8f0;
    border-radius: 15px;
    overflow: hidden;
}

.leaderboard-header {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
    padding: 20px;
    text-align: center;
}

.leaderboard-item {
    display: flex;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid #e2e8f0;
    transition: all 0.2s;
}

.leaderboard-item:hover {
    background: #f8fafc;
}

.leaderboard-item.current-user {
    background: #fef3c7;
}

.leaderboard-item:last-child {
    border-bottom: none;
}

.rank {
    font-size: 1.5em;
    font-weight: bold;
    color: #f59e0b;
    width: 50px;
}

.user-info {
    flex: 1;
    margin-left: 15px;
}

.user-name {
    font-weight: bold;
    color: #1e293b;
    margin-bottom: 5px;
}

.user-address {
    color: #64748b;
    font-family: monospace;
    font-size: 0.9em;
}

.user-stats {
    text-align: right;
}

.referral-count {
    font-size: 1.2em;
    font-weight: bold;
    color: #4f46e5;
}

.reward-amount {
    color: #059669;
    font-size: 0.9em;
}

.loading {
    text-align: center;
    padding: 40px;
    color: #64748b;
}

.error {
    background: #fef2f2;
    border: 2px solid #dc2626;
    color: #dc2626;
    padding: 20px;
    border-radius: 10px;
    margin: 20px;
}

.spinner {
    border: 3px solid #f3f4f6;
    border-top: 3px solid #4f46e5;
    border-radius: 50%;
    width: 30px;
    height: 30px;
    animation: spin 1s linear infinite;
    margin: 0 auto 15px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>

