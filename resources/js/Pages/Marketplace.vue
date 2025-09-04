<template>
  <Head title="Marketplace P2P" />

  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Marketplace P2P
      </h2>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="space-y-6">
          <!-- Statistiques du marketplace -->
          <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-white rounded-lg shadow p-4">
              <div class="flex items-center">
                <div class="flex-shrink-0">
                  <i class="fas fa-chart-bar text-blue-600 text-2xl"></i>
                </div>
                <div class="ml-3">
                  <p class="text-sm font-medium text-gray-500">Total Trades</p>
                  <p class="text-lg font-semibold text-gray-900">{{ stats.total_trades }}</p>
                </div>
              </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-4">
              <div class="flex items-center">
                <div class="flex-shrink-0">
                  <i class="fas fa-clock text-yellow-600 text-2xl"></i>
                </div>
                <div class="ml-3">
                  <p class="text-sm font-medium text-gray-500">Trades Actifs</p>
                  <p class="text-lg font-semibold text-gray-900">{{ stats.active_trades }}</p>
                </div>
              </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-4">
              <div class="flex items-center">
                <div class="flex-shrink-0">
                  <i class="fas fa-coins text-purple-600 text-2xl"></i>
                </div>
                <div class="ml-3">
                  <p class="text-sm font-medium text-gray-500">Volume SNT</p>
                  <p class="text-lg font-semibold text-gray-900">{{ formatNumber(stats.total_volume_snt) }}</p>
                </div>
              </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-4">
              <div class="flex items-center">
                <div class="flex-shrink-0">
                  <i class="fas fa-gem text-green-600 text-2xl"></i>
                </div>
                <div class="ml-3">
                  <p class="text-sm font-medium text-gray-500">Volume AVAX</p>
                  <p class="text-lg font-semibold text-gray-900">{{ formatNumber(stats.total_volume_avax) }}</p>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Marketplace P2P -->
          <P2PMarketplace :user-address="userAddress" />
          
          <!-- Instructions -->
          <div class="bg-white rounded-lg shadow-lg p-6">
            <h3 class="text-xl font-bold mb-4 text-gray-800">Comment utiliser le Marketplace P2P</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <h4 class="font-semibold text-gray-800 mb-2">Pour Vendre des SNT:</h4>
                <ol class="list-decimal list-inside text-sm text-gray-600 space-y-1">
                  <li>Cliquez sur "Créer une Offre"</li>
                  <li>Spécifiez le montant de SNT à vendre</li>
                  <li>Définissez le prix en AVAX souhaité</li>
                  <li>Confirmez la transaction pour déposer vos SNT en escrow</li>
                  <li>Attendez qu'un acheteur accepte votre offre</li>
                </ol>
              </div>
              
              <div>
                <h4 class="font-semibold text-gray-800 mb-2">Pour Acheter des SNT:</h4>
                <ol class="list-decimal list-inside text-sm text-gray-600 space-y-1">
                  <li>Parcourez les offres disponibles</li>
                  <li>Cliquez sur "Acheter" pour l'offre qui vous intéresse</li>
                  <li>Confirmez la transaction avec le montant AVAX requis</li>
                  <li>Les SNT seront automatiquement transférés vers votre portefeuille</li>
                  <li>L'AVAX sera envoyé au vendeur (moins les frais)</li>
                </ol>
              </div>
            </div>
            
            <div class="mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
              <h4 class="font-semibold text-yellow-800 mb-2">⚠️ Important:</h4>
              <ul class="text-sm text-yellow-700 space-y-1">
                <li>• Des frais de 2,5% sont appliqués sur chaque transaction</li>
                <li>• Les offres expirent automatiquement après 24 heures</li>
                <li>• Assurez-vous d'avoir suffisamment de tokens/AVAX avant de créer ou accepter une offre</li>
                <li>• Les transactions sont irréversibles une fois confirmées</li>
              </ul>
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
import P2PMarketplace from '@/Components/P2PMarketplace.vue'
import axios from 'axios'

export default {
  name: 'Marketplace',
  components: {
    Head,
    AuthenticatedLayout,
    P2PMarketplace
  },
  props: {
    userAddress: {
      type: String,
      default: null
    }
  },
  setup() {
    const stats = ref({
      total_trades: 0,
      active_trades: 0,
      total_volume_snt: 0,
      total_volume_avax: 0
    })

    const fetchStats = async () => {
      try {
        const response = await axios.get('/api/escrow/stats')
        stats.value = response.data
      } catch (error) {
        console.error('Erreur lors du chargement des statistiques:', error)
      }
    }

    const formatNumber = (num) => {
      if (num >= 1000000) {
        return (num / 1000000).toFixed(1) + 'M'
      } else if (num >= 1000) {
        return (num / 1000).toFixed(1) + 'K'
      }
      return num.toString()
    }

    onMounted(() => {
      fetchStats()
    })

    return {
      stats,
      formatNumber
    }
  }
}
</script>

