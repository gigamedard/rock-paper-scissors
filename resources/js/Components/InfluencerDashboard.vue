<template>
  <div class="bg-white rounded-lg shadow-lg p-6">
    <h2 class="text-2xl font-bold mb-6 text-gray-800">Dashboard Influenceur</h2>
    
    <!-- Informations du pool -->
    <div class="mb-6 p-4 bg-gradient-to-r from-purple-50 to-blue-50 rounded-lg">
      <h3 class="text-lg font-semibold text-gray-800 mb-2">Pool: {{ stats.poolName }}</h3>
      <div class="flex items-center space-x-4">
        <div class="flex items-center space-x-2">
          <i class="fas fa-award text-purple-600"></i>
          <span class="text-sm text-gray-600">
            Statut: {{ stats.isEligible ? 'Éligible' : 'Non éligible' }}
          </span>
        </div>
        <div class="flex items-center space-x-2">
          <i class="fas fa-dollar-sign text-green-600"></i>
          <span class="text-sm text-gray-600">
            Pool de récompenses: {{ stats.rewardPoolSize }} AVAX
          </span>
        </div>
      </div>
    </div>

    <!-- Statistiques personnelles -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
      <div class="bg-blue-50 p-4 rounded-lg">
        <div class="flex items-center justify-between mb-2">
          <span class="text-sm font-medium text-gray-700">Objectif Personnel</span>
          <i class="fas fa-users text-blue-600"></i>
        </div>
        <div class="mb-2">
          <div class="flex justify-between text-sm text-gray-600">
            <span>{{ stats.referralCount }} / {{ stats.personalGoal }}</span>
            <span>{{ personalProgress.toFixed(1) }}%</span>
          </div>
          <div class="w-full bg-gray-200 rounded-full h-2">
            <div 
              class="bg-blue-600 h-2 rounded-full transition-all duration-300"
              :style="{ width: `${Math.min(personalProgress, 100)}%` }"
            ></div>
          </div>
        </div>
        <p class="text-xs text-gray-500">Parrainages validés requis</p>
      </div>

      <div class="bg-green-50 p-4 rounded-lg">
        <div class="flex items-center justify-between mb-2">
          <span class="text-sm font-medium text-gray-700">Objectif du Pool</span>
          <i class="fas fa-chart-line text-green-600"></i>
        </div>
        <div class="mb-2">
          <div class="flex justify-between text-sm text-gray-600">
            <span>{{ stats.poolProgress }} / {{ stats.poolGoal }}</span>
            <span>{{ poolProgress.toFixed(1) }}%</span>
          </div>
          <div class="w-full bg-gray-200 rounded-full h-2">
            <div 
              class="bg-green-600 h-2 rounded-full transition-all duration-300"
              :style="{ width: `${Math.min(poolProgress, 100)}%` }"
            ></div>
          </div>
        </div>
        <p class="text-xs text-gray-500">Parrainages totaux du pool</p>
      </div>
    </div>

    <!-- Actions -->
    <div class="space-y-4">
      <button 
        v-if="stats.canClaim"
        @click="claimReward"
        :disabled="claiming"
        class="w-full bg-green-600 text-white py-3 px-4 rounded-md hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
      >
        {{ claiming ? 'Réclamation...' : 'Réclamer ma Récompense' }}
      </button>
      
      <div v-else class="p-4 bg-yellow-50 border border-yellow-200 rounded-md">
        <h4 class="font-semibold text-yellow-800 mb-2">Conditions pour réclamer:</h4>
        <ul class="text-sm text-yellow-700 space-y-1">
          <li :class="{ 'line-through': personalProgress >= 100 }">
            ✓ Atteindre {{ stats.personalGoal }} parrainages personnels
          </li>
          <li :class="{ 'line-through': poolProgress >= 100 }">
            ✓ Le pool doit atteindre {{ stats.poolGoal }} parrainages totaux
          </li>
          <li :class="{ 'line-through': stats.isEligible }">
            ✓ Être marqué comme éligible par l'administrateur
          </li>
        </ul>
      </div>
    </div>

    <!-- Message de statut -->
    <div v-if="statusMessage" class="mt-4 p-3 rounded-md" :class="statusClass">
      <p class="text-sm">{{ statusMessage }}</p>
    </div>
  </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'

export default {
  name: 'InfluencerDashboard',
  setup() {
    const stats = ref({
      referralCount: 0,
      poolProgress: 0,
      rewardPoolSize: 0,
      isEligible: false,
      poolName: '',
      personalGoal: 5000,
      poolGoal: 30000,
      canClaim: false
    })
    
    const claiming = ref(false)
    const statusMessage = ref('')
    const statusType = ref('')

    const personalProgress = computed(() => {
      return (stats.value.referralCount / stats.value.personalGoal) * 100
    })

    const poolProgress = computed(() => {
      return (stats.value.poolProgress / stats.value.poolGoal) * 100
    })

    const statusClass = computed(() => {
      return {
        'bg-green-50 border border-green-200 text-green-800': statusType.value === 'success',
        'bg-red-50 border border-red-200 text-red-800': statusType.value === 'error',
        'bg-blue-50 border border-blue-200 text-blue-800': statusType.value === 'info'
      }
    })

    const fetchInfluencerStats = async () => {
      try {
        const response = await axios.get('/api/influencer/stats')
        stats.value = response.data
      } catch (error) {
        console.error('Erreur lors du chargement des statistiques:', error)
        if (error.response?.status === 404) {
          statusMessage.value = 'Vous n\'êtes pas encore inscrit comme influenceur.'
          statusType.value = 'error'
        }
      }
    }

    const claimReward = async () => {
      claiming.value = true
      statusMessage.value = ''
      
      try {
        const response = await axios.post('/api/influencer/claim-reward')
        statusMessage.value = `Récompense réclamée avec succès ! Montant: ${response.data.amount} AVAX`
        statusType.value = 'success'
        
        // Refresh stats
        await fetchInfluencerStats()
      } catch (error) {
        console.error('Erreur lors de la réclamation:', error)
        statusMessage.value = error.response?.data?.error || 'Erreur lors de la réclamation de la récompense'
        statusType.value = 'error'
      } finally {
        claiming.value = false
      }
    }

    onMounted(() => {
      fetchInfluencerStats()
    })

    return {
      stats,
      claiming,
      statusMessage,
      statusClass,
      personalProgress,
      poolProgress,
      claimReward
    }
  }
}
</script>

