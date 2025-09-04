<template>
  <div class="bg-white rounded-lg shadow-lg p-6">
    <div class="flex justify-between items-center mb-6">
      <h2 class="text-2xl font-bold text-gray-800">Marketplace P2P</h2>
      <button
        @click="showCreateForm = true"
        class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition-colors flex items-center space-x-2"
      >
        <i class="fas fa-plus"></i>
        <span>Créer une Offre</span>
      </button>
    </div>

    <!-- Formulaire de création -->
    <div v-if="showCreateForm" class="mb-6 p-4 border border-gray-200 rounded-lg bg-gray-50">
      <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-semibold">Créer une nouvelle offre</h3>
        <button
          @click="showCreateForm = false"
          class="text-gray-500 hover:text-gray-700"
        >
          <i class="fas fa-times"></i>
        </button>
      </div>
      
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            Montant SNT à vendre
          </label>
          <input
            type="number"
            v-model="newTrade.sntAmount"
            class="w-full px-3 py-2 border border-gray-300 rounded-md"
            placeholder="100"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            Prix en AVAX souhaité
          </label>
          <input
            type="number"
            v-model="newTrade.avaxAmount"
            class="w-full px-3 py-2 border border-gray-300 rounded-md"
            placeholder="1.5"
          />
        </div>
      </div>
      
      <button
        @click="createTrade"
        :disabled="loading"
        class="w-full bg-green-600 text-white py-2 px-4 rounded-md hover:bg-green-700 disabled:opacity-50 transition-colors"
      >
        {{ loading ? 'Création...' : 'Créer l\'Offre' }}
      </button>
    </div>

    <!-- Liste des trades -->
    <div class="space-y-4">
      <div v-if="trades.length === 0" class="text-center py-8 text-gray-500">
        Aucune offre disponible pour le moment
      </div>
      
      <div 
        v-for="trade in trades" 
        :key="trade.id" 
        class="border border-gray-200 rounded-lg p-4"
      >
        <div class="flex items-center justify-between">
          <div class="flex items-center space-x-4">
            <div class="text-center">
              <p class="text-lg font-semibold text-blue-600">{{ trade.sntAmount }} SNT</p>
              <p class="text-sm text-gray-500">À vendre</p>
            </div>
            <i class="fas fa-exchange-alt text-gray-400"></i>
            <div class="text-center">
              <p class="text-lg font-semibold text-green-600">{{ trade.avaxAmount }} AVAX</p>
              <p class="text-sm text-gray-500">Prix demandé</p>
            </div>
          </div>
          
          <div class="flex items-center space-x-2">
            <div class="text-right">
              <p class="text-sm text-gray-500">Vendeur</p>
              <p class="text-xs font-mono">{{ formatAddress(trade.seller) }}</p>
            </div>
            
            <button
              v-if="trade.seller.toLowerCase() !== userAddress?.toLowerCase()"
              @click="acceptTrade(trade.id, trade.avaxAmount)"
              :disabled="loading"
              class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 disabled:opacity-50 transition-colors"
            >
              Acheter
            </button>
            
            <button
              v-else
              @click="cancelTrade(trade.id)"
              :disabled="loading"
              class="bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700 disabled:opacity-50 transition-colors"
            >
              Annuler
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="trades.length > 0" class="mt-6 flex justify-center space-x-2">
      <button
        @click="loadTrades(currentPage - 1)"
        :disabled="currentPage <= 1"
        class="px-3 py-1 bg-gray-200 text-gray-700 rounded disabled:opacity-50"
      >
        Précédent
      </button>
      <span class="px-3 py-1 text-gray-700">Page {{ currentPage }}</span>
      <button
        @click="loadTrades(currentPage + 1)"
        :disabled="trades.length < pageSize"
        class="px-3 py-1 bg-gray-200 text-gray-700 rounded disabled:opacity-50"
      >
        Suivant
      </button>
    </div>

    <!-- Message de statut -->
    <div v-if="statusMessage" class="mt-4 p-3 rounded-md" :class="statusClass">
      <p class="text-sm">{{ statusMessage }}</p>
    </div>
  </div>
</template>

<script>
import { ref, onMounted } from 'vue'
import axios from 'axios'

export default {
  name: 'P2PMarketplace',
  props: {
    userAddress: {
      type: String,
      default: null
    }
  },
  setup(props) {
    const trades = ref([])
    const showCreateForm = ref(false)
    const loading = ref(false)
    const currentPage = ref(1)
    const pageSize = ref(20)
    const statusMessage = ref('')
    const statusType = ref('')
    
    const newTrade = ref({
      sntAmount: '',
      avaxAmount: ''
    })

    const statusClass = computed(() => {
      return {
        'bg-green-50 border border-green-200 text-green-800': statusType.value === 'success',
        'bg-red-50 border border-red-200 text-red-800': statusType.value === 'error',
        'bg-blue-50 border border-blue-200 text-blue-800': statusType.value === 'info'
      }
    })

    const loadTrades = async (page = 1) => {
      try {
        const start = (page - 1) * pageSize.value
        const response = await axios.get('/api/escrow/trades', {
          params: { start, limit: pageSize.value }
        })
        trades.value = response.data
        currentPage.value = page
      } catch (error) {
        console.error('Erreur lors du chargement des trades:', error)
        statusMessage.value = 'Erreur lors du chargement des offres'
        statusType.value = 'error'
      }
    }

    const createTrade = async () => {
      if (!newTrade.value.sntAmount || !newTrade.value.avaxAmount) {
        statusMessage.value = 'Veuillez remplir tous les champs'
        statusType.value = 'error'
        return
      }

      if (!props.userAddress) {
        statusMessage.value = 'Veuillez connecter votre portefeuille'
        statusType.value = 'error'
        return
      }

      loading.value = true
      statusMessage.value = ''

      try {
        await axios.post('/api/escrow/create-trade', {
          snt_amount: newTrade.value.sntAmount,
          avax_amount: newTrade.value.avaxAmount,
          wallet_address: props.userAddress
        })

        showCreateForm.value = false
        newTrade.value = { sntAmount: '', avaxAmount: '' }
        statusMessage.value = 'Offre créée avec succès !'
        statusType.value = 'success'
        
        // Recharger les trades
        await loadTrades(currentPage.value)
      } catch (error) {
        console.error('Erreur lors de la création du trade:', error)
        statusMessage.value = error.response?.data?.error || 'Erreur lors de la création de l\'offre'
        statusType.value = 'error'
      } finally {
        loading.value = false
      }
    }

    const acceptTrade = async (tradeId, avaxAmount) => {
      if (!props.userAddress) {
        statusMessage.value = 'Veuillez connecter votre portefeuille'
        statusType.value = 'error'
        return
      }

      loading.value = true
      statusMessage.value = ''

      try {
        await axios.post(`/api/escrow/accept-trade/${tradeId}`, {
          wallet_address: props.userAddress
        })

        statusMessage.value = 'Trade accepté avec succès !'
        statusType.value = 'success'
        
        // Recharger les trades
        await loadTrades(currentPage.value)
      } catch (error) {
        console.error('Erreur lors de l\'acceptation du trade:', error)
        statusMessage.value = error.response?.data?.error || 'Erreur lors de l\'acceptation du trade'
        statusType.value = 'error'
      } finally {
        loading.value = false
      }
    }

    const cancelTrade = async (tradeId) => {
      if (!props.userAddress) {
        statusMessage.value = 'Veuillez connecter votre portefeuille'
        statusType.value = 'error'
        return
      }

      loading.value = true
      statusMessage.value = ''

      try {
        await axios.post(`/api/escrow/cancel-trade/${tradeId}`, {
          wallet_address: props.userAddress
        })

        statusMessage.value = 'Trade annulé avec succès !'
        statusType.value = 'success'
        
        // Recharger les trades
        await loadTrades(currentPage.value)
      } catch (error) {
        console.error('Erreur lors de l\'annulation du trade:', error)
        statusMessage.value = error.response?.data?.error || 'Erreur lors de l\'annulation du trade'
        statusType.value = 'error'
      } finally {
        loading.value = false
      }
    }

    const formatAddress = (address) => {
      return `${address.slice(0, 6)}...${address.slice(-4)}`
    }

    onMounted(() => {
      loadTrades()
    })

    return {
      trades,
      showCreateForm,
      loading,
      currentPage,
      pageSize,
      statusMessage,
      statusClass,
      newTrade,
      loadTrades,
      createTrade,
      acceptTrade,
      cancelTrade,
      formatAddress
    }
  }
}
</script>

