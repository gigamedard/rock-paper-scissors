<template>
  <div class="bg-white rounded-lg shadow-lg p-6">
    <h2 class="text-2xl font-bold mb-6 text-gray-800">Programme de Parrainage</h2>
    
    <!-- Code de parrainage -->
    <div class="mb-6">
      <label class="block text-sm font-medium text-gray-700 mb-2">
        Votre code de parrainage
      </label>
      <div class="flex items-center space-x-2">
        <input
          type="text"
          :value="referralData.code"
          readonly
          class="flex-1 px-3 py-2 border border-gray-300 rounded-md bg-gray-50"
        />
        <button
          @click="copyReferralLink"
          class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors flex items-center space-x-2"
        >
          <i class="fas fa-copy"></i>
          <span>{{ copied ? 'Copié !' : 'Copier' }}</span>
        </button>
        <button
          @click="shareReferralLink"
          class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors"
        >
          <i class="fas fa-share-alt"></i>
        </button>
      </div>
    </div>

    <!-- Statistiques -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="bg-blue-50 p-4 rounded-lg">
        <div class="flex items-center space-x-2">
          <i class="fas fa-users text-blue-600"></i>
          <span class="text-sm font-medium text-gray-700">Total Parrainages</span>
        </div>
        <p class="text-2xl font-bold text-blue-600">{{ referralData.totalReferrals }}</p>
      </div>
      
      <div class="bg-yellow-50 p-4 rounded-lg">
        <div class="flex items-center space-x-2">
          <i class="fas fa-clock text-yellow-600"></i>
          <span class="text-sm font-medium text-gray-700">En Attente</span>
        </div>
        <p class="text-2xl font-bold text-yellow-600">{{ referralData.pendingReferrals }}</p>
      </div>
      
      <div class="bg-green-50 p-4 rounded-lg">
        <div class="flex items-center space-x-2">
          <i class="fas fa-check-circle text-green-600"></i>
          <span class="text-sm font-medium text-gray-700">Validés</span>
        </div>
        <p class="text-2xl font-bold text-green-600">{{ referralData.validatedReferrals }}</p>
      </div>
      
      <div class="bg-purple-50 p-4 rounded-lg">
        <div class="flex items-center space-x-2">
          <i class="fas fa-gift text-purple-600"></i>
          <span class="text-sm font-medium text-gray-700">Récompenses SNT</span>
        </div>
        <p class="text-2xl font-bold text-purple-600">{{ referralData.totalRewards }}</p>
      </div>
    </div>

    <!-- Instructions -->
    <div class="mt-6 p-4 bg-gray-50 rounded-lg">
      <h3 class="font-semibold text-gray-800 mb-2">Comment ça marche ?</h3>
      <ol class="list-decimal list-inside text-sm text-gray-600 space-y-1">
        <li>Partagez votre code de parrainage avec vos amis</li>
        <li>Ils s'inscrivent en utilisant votre code</li>
        <li>Quand ils font leur première transaction AVAX, vous recevez 100 SNT !</li>
      </ol>
    </div>
  </div>
</template>

<script>
import { ref, onMounted } from 'vue'
import axios from 'axios'

export default {
  name: 'ReferralDashboard',
  setup() {
    const referralData = ref({
      code: '',
      totalReferrals: 0,
      pendingReferrals: 0,
      validatedReferrals: 0,
      totalRewards: 0
    })
    
    const copied = ref(false)

    const fetchReferralData = async () => {
      try {
        const response = await axios.get('/api/referral/status')
        referralData.value = response.data
      } catch (error) {
        console.error('Erreur lors du chargement des données de parrainage:', error)
      }
    }

    const copyReferralLink = () => {
      const referralLink = `${window.location.origin}/register?ref=${referralData.value.code}`
      navigator.clipboard.writeText(referralLink)
      copied.value = true
      setTimeout(() => copied.value = false, 2000)
    }

    const shareReferralLink = () => {
      const referralLink = `${window.location.origin}/register?ref=${referralData.value.code}`
      if (navigator.share) {
        navigator.share({
          title: 'Rejoignez-moi sur ce jeu Web3 !',
          text: 'Utilisez mon code de parrainage pour commencer',
          url: referralLink
        })
      }
    }

    onMounted(() => {
      fetchReferralData()
    })

    return {
      referralData,
      copied,
      copyReferralLink,
      shareReferralLink
    }
  }
}
</script>

