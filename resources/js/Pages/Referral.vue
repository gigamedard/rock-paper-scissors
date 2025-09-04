<template>
  <Head title="Programme de Parrainage" />

  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Programme de Parrainage
      </h2>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="space-y-6">
          <!-- Dashboard de parrainage -->
          <ReferralDashboard />
          
          <!-- Classement des parrainages -->
          <div class="bg-white rounded-lg shadow-lg p-6">
            <h3 class="text-xl font-bold mb-4 text-gray-800">Classement des Parraineurs</h3>
            
            <div v-if="leaderboard.length === 0" class="text-center py-8 text-gray-500">
              Aucun parrainage validé pour le moment
            </div>
            
            <div v-else class="space-y-3">
              <div 
                v-for="(user, index) in leaderboard" 
                :key="index"
                class="flex items-center justify-between p-3 bg-gray-50 rounded-lg"
              >
                <div class="flex items-center space-x-3">
                  <div class="flex items-center justify-center w-8 h-8 rounded-full" :class="getRankClass(index)">
                    <span class="text-sm font-bold text-white">{{ index + 1 }}</span>
                  </div>
                  <div>
                    <p class="font-semibold text-gray-800">{{ user.name }}</p>
                    <p class="text-xs text-gray-500 font-mono">{{ user.wallet_address }}</p>
                  </div>
                </div>
                <div class="text-right">
                  <p class="font-semibold text-blue-600">{{ user.referral_count }} parrainages</p>
                  <p class="text-sm text-green-600">{{ user.rewards_earned }} SNT</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script>
import { ref, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import ReferralDashboard from '@/Components/ReferralDashboard.vue'
import axios from 'axios'

export default {
  name: 'Referral',
  components: {
    Head,
    AuthenticatedLayout,
    ReferralDashboard
  },
  setup() {
    const leaderboard = ref([])

    const fetchLeaderboard = async () => {
      try {
        const response = await axios.get('/api/referral/leaderboard')
        leaderboard.value = response.data
      } catch (error) {
        console.error('Erreur lors du chargement du classement:', error)
      }
    }

    const getRankClass = (index) => {
      switch (index) {
        case 0:
          return 'bg-yellow-500' // Or
        case 1:
          return 'bg-gray-400' // Argent
        case 2:
          return 'bg-yellow-600' // Bronze
        default:
          return 'bg-blue-500'
      }
    }

    onMounted(() => {
      fetchLeaderboard()
    })

    return {
      leaderboard,
      getRankClass
    }
  }
}
</script>

