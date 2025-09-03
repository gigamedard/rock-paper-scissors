<template>
  <Head title="Dashboard Influenceur" />

  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Dashboard Influenceur
      </h2>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="space-y-6">
          <!-- Dashboard influenceur -->
          <InfluencerDashboard />
          
          <!-- Informations sur les pools -->
          <div class="bg-white rounded-lg shadow-lg p-6">
            <h3 class="text-xl font-bold mb-4 text-gray-800">Pools d'Influenceurs Actifs</h3>
            
            <div v-if="pools.length === 0" class="text-center py-8 text-gray-500">
              Aucun pool actif pour le moment
            </div>
            
            <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              <div 
                v-for="pool in pools" 
                :key="pool.id"
                class="border border-gray-200 rounded-lg p-4"
              >
                <div class="mb-3">
                  <h4 class="font-semibold text-gray-800">{{ pool.name }}</h4>
                  <p class="text-sm text-gray-600">{{ pool.language }}</p>
                </div>
                
                <div class="space-y-2 text-sm">
                  <div class="flex justify-between">
                    <span class="text-gray-600">Objectif individuel:</span>
                    <span class="font-semibold">{{ pool.milestone }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-600">Objectif du pool:</span>
                    <span class="font-semibold">{{ pool.pool_milestone }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-600">Récompense:</span>
                    <span class="font-semibold text-green-600">{{ pool.reward_amount }} AVAX</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-600">Progrès:</span>
                    <span class="font-semibold">{{ pool.total_referrals }} / {{ pool.pool_milestone }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-600">Influenceurs:</span>
                    <span class="font-semibold">{{ pool.influencer_count }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-600">Éligibles:</span>
                    <span class="font-semibold text-blue-600">{{ pool.eligible_count }}</span>
                  </div>
                </div>
                
                <!-- Barre de progression -->
                <div class="mt-3">
                  <div class="w-full bg-gray-200 rounded-full h-2">
                    <div 
                      class="bg-blue-600 h-2 rounded-full transition-all duration-300"
                      :style="{ width: `${Math.min((pool.total_referrals / pool.pool_milestone) * 100, 100)}%` }"
                    ></div>
                  </div>
                  <p class="text-xs text-gray-500 mt-1">
                    {{ ((pool.total_referrals / pool.pool_milestone) * 100).toFixed(1) }}% complété
                  </p>
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
import InfluencerDashboard from '@/Components/InfluencerDashboard.vue'
import axios from 'axios'

export default {
  name: 'Influencer',
  components: {
    Head,
    AuthenticatedLayout,
    InfluencerDashboard
  },
  setup() {
    const pools = ref([])

    const fetchPools = async () => {
      try {
        const response = await axios.get('/api/influencer/pools')
        pools.value = response.data
      } catch (error) {
        console.error('Erreur lors du chargement des pools:', error)
      }
    }

    onMounted(() => {
      fetchPools()
    })

    return {
      pools
    }
  }
}
</script>

