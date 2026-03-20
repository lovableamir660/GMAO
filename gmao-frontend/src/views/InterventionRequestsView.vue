<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { useSettings } from '@/composables/useSettings'

const authStore = useAuthStore()
const { fetchGroup } = useSettings()

const requests = ref([])
const equipments = ref([])
const trucks = ref([])
const users = ref([])
const stats = ref({
  pending: 0,
  approved: 0,
  rejected: 0,
  converted: 0,
  machine_stopped: 0,
  by_asset_type: { equipment: 0, truck: 0 }
})
const loading = ref(false)
const error = ref('')
const successMessage = ref('')
const searchQuery = ref('')
const filterStatus = ref('')
const filterUrgency = ref('')
const filterAssetType = ref('')
const settingsLoaded = ref(false)

// Modals
const showCreateModal = ref(false)
const showDetailModal = ref(false)
const showValidateModal = ref(false)
const showConvertModal = ref(false)
const selectedRequest = ref(null)
const saving = ref(false)

const form = ref({
  asset_type: 'equipment',
  equipment_id: '',
  truck_id: '',
  title: '',
  description: '',
  urgency: 'medium',
  machine_stopped: false,
  location_details: '',
  contact_phone: '',
})

const validateForm = ref({
  action: 'approve',
  comment: '',
  reason: '',
})

const convertForm = ref({
  type: 'corrective',
  priority: 'medium',
  assigned_to: '',
  scheduled_start: '',
})

// ═══ Données dynamiques depuis les paramètres ═══
const diTypesMap = ref({})       // { corrective: "Correctif", ... }
const diUrgenciesMap = ref({})   // { low: "Basse", medium: "Moyenne", ... }
const autoApprove = ref(false)

// On charge aussi les settings OT pour la modal de conversion
const woTypesMap = ref({})       // { corrective: "Correctif", ... }
const woPrioritiesMap = ref({})  // { low: "Basse", ... }

// ═══ Statuts = workflow, restent hardcodés ═══
const statusLabels = {
  pending: 'En attente',
  approved: 'Approuvée',
  rejected: 'Rejetée',
  converted: 'Convertie en OT',
  cancelled: 'Annulée',
}

const statusColors = {
  pending: { bg: '#fff3cd', text: '#856404', border: '#ffc107' },
  approved: { bg: '#d4edda', text: '#155724', border: '#28a745' },
  rejected: { bg: '#f8d7da', text: '#721c24', border: '#dc3545' },
  converted: { bg: '#d1ecf1', text: '#0c5460', border: '#17a2b8' },
  cancelled: { bg: '#e9ecef', text: '#6c757d', border: '#adb5bd' },
}

// ═══ Métadonnées UI urgences (couleurs) — restent côté frontend ═══
const urgencyMeta = {
  low:      { bg: '#d4edda', text: '#155724' },
  medium:   { bg: '#fff3cd', text: '#856404' },
  high:     { bg: '#ffe5d0', text: '#c45200' },
  critical: { bg: '#f8d7da', text: '#721c24' },
}

const assetTypeLabels = { equipment: 'Équipement', truck: 'Camion' }
const assetTypeIcons = { equipment: '⚙️', truck: '🚚' }

// ═══ Labels dynamiques (computed) ═══
const urgencyLabels = computed(() => {
  const map = {}
  for (const [key, label] of Object.entries(diUrgenciesMap.value)) {
    map[key] = {
      label,
      bg: urgencyMeta[key]?.bg || '#e9ecef',
      text: urgencyMeta[key]?.text || '#495057',
    }
  }
  return map
})

// ═══ Options pour les selects et filtres ═══
const urgencyOptions = computed(() => {
  return Object.entries(diUrgenciesMap.value).map(([value, label]) => ({ value, label }))
})

// Options OT pour la modal de conversion
const woTypeOptions = computed(() => {
  return Object.entries(woTypesMap.value).map(([value, label]) => ({ value, label }))
})

const woPriorityOptions = computed(() => {
  return Object.entries(woPrioritiesMap.value).map(([value, label]) => ({ value, label }))
})

// ═══ Helpers : résoudre clé → label ═══
function getUrgencyLabel(urgencyKey) {
  if (!urgencyKey) return '-'
  return diUrgenciesMap.value[urgencyKey] || urgencyKey
}

// Computed
const filteredRequests = computed(() => {
  return requests.value.filter(req => {
    const assetName = req.asset_type === 'truck'
      ? (req.truck?.registration_number || req.truck?.code || '')
      : (req.equipment?.name || req.equipment?.code || '')

    const matchSearch = !searchQuery.value ||
      req.title.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      req.code.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      assetName.toLowerCase().includes(searchQuery.value.toLowerCase())

    const matchStatus = !filterStatus.value || req.status === filterStatus.value
    const matchUrgency = !filterUrgency.value || req.urgency === filterUrgency.value
    const matchAssetType = !filterAssetType.value || req.asset_type === filterAssetType.value

    return matchSearch && matchStatus && matchUrgency && matchAssetType
  })
})

const selectedAssets = computed(() => {
  if (form.value.asset_type === 'truck') {
    return trucks.value
  }
  return equipments.value
})

function showSuccess(message) {
  successMessage.value = message
  setTimeout(() => { successMessage.value = '' }, 3000)
}

function showError(message) {
  error.value = message
  setTimeout(() => { error.value = '' }, 5000)
}

// ═══ Helper : parser la valeur d'un setting ═══
function parseSettingValue(setting) {
  if (!setting) return null
  const { value, type } = setting
  if (type === 'json' || type === 'list') {
    if (typeof value === 'string') {
      try { return JSON.parse(value) } catch { return {} }
    }
    return value || {}
  }
  if (type === 'boolean') return value === '1' || value === 'true' || value === true
  if (type === 'integer') return parseInt(value) || 0
  return value
}

// ═══ Chargement des paramètres DI depuis l'API ═══
async function loadDISettings() {
  try {
    const data = await fetchGroup('intervention_request')

    if (data && Array.isArray(data)) {
      data.forEach(setting => {
        const val = parseSettingValue(setting)
        switch (setting.key) {
          case 'types':
            diTypesMap.value = val || {}
            break
          case 'urgencies':
            diUrgenciesMap.value = val || {}
            break
          case 'auto_approve':
            autoApprove.value = !!val
            break
        }
      })
    }

    // Mettre à jour la valeur par défaut du formulaire
    const urgencyKeys = Object.keys(diUrgenciesMap.value)
    if (urgencyKeys.includes('medium')) {
      form.value.urgency = 'medium'
    } else if (urgencyKeys.length) {
      form.value.urgency = urgencyKeys[0]
    }

    settingsLoaded.value = true
  } catch (err) {
    console.error('Erreur chargement paramètres DI:', err)
    // Fallback identique au seeder
    diTypesMap.value = {
      corrective: 'Correctif',
      preventive: 'Préventif',
      improvement: 'Amélioratif',
      inspection: 'Inspection',
    }
    diUrgenciesMap.value = {
      low: 'Basse',
      medium: 'Moyenne',
      high: 'Haute',
      critical: 'Critique',
    }
    settingsLoaded.value = true
  }
}

// ═══ Chargement des paramètres OT (pour la modal de conversion) ═══
async function loadWOSettings() {
  try {
    const data = await fetchGroup('work_order')
    if (data && Array.isArray(data)) {
      data.forEach(setting => {
        const val = parseSettingValue(setting)
        switch (setting.key) {
          case 'types':
            woTypesMap.value = val || {}
            break
          case 'priorities':
            woPrioritiesMap.value = val || {}
            break
        }
      })
    }
  } catch (err) {
    console.error('Erreur chargement paramètres OT:', err)
    woTypesMap.value = {
      corrective: 'Correctif',
      preventive: 'Préventif',
      improvement: 'Amélioratif',
      inspection: 'Inspection',
    }
    woPrioritiesMap.value = {
      low: 'Basse',
      medium: 'Moyenne',
      high: 'Haute',
      critical: 'Critique',
    }
  }
}

// API calls
async function fetchRequests() {
  loading.value = true
  try {
    const params = new URLSearchParams({ per_page: '100' })
    if (filterAssetType.value) params.append('asset_type', filterAssetType.value)

    const response = await api.get(`/intervention-requests?${params}`)
    requests.value = response.data.data
  } catch (err) {
    showError('Erreur lors du chargement des demandes')
  } finally {
    loading.value = false
  }
}

async function fetchStats() {
  try {
    const response = await api.get('/intervention-requests/stats')
    stats.value = response.data
  } catch (err) {
    console.error(err)
  }
}

async function fetchEquipments() {
  try {
    const response = await api.get('/equipments?per_page=100&is_active=true')
    equipments.value = response.data.data
  } catch (err) { console.error(err) }
}

async function fetchTrucks() {
  try {
    const response = await api.get('/trucks-list')
    trucks.value = response.data
  } catch (err) { console.error(err) }
}

async function fetchUsers() {
  try {
    const response = await api.get('/users?per_page=100')
    users.value = response.data.data
  } catch (err) { console.error(err) }
}

function openCreateModal() {
  const urgencyKeys = Object.keys(diUrgenciesMap.value)
  form.value = {
    asset_type: 'equipment',
    equipment_id: '',
    truck_id: '',
    title: '',
    description: '',
    urgency: urgencyKeys.includes('medium') ? 'medium' : (urgencyKeys[0] || 'medium'),
    machine_stopped: false,
    location_details: '',
    contact_phone: '',
  }
  showCreateModal.value = true
}

function onAssetTypeChange() {
  form.value.equipment_id = ''
  form.value.truck_id = ''
}

async function createRequest() {
  saving.value = true
  try {
    const payload = {
      asset_type: form.value.asset_type,
      title: form.value.title,
      description: form.value.description,
      urgency: form.value.urgency, // ← CLÉ envoyée (ex: "medium")
      machine_stopped: form.value.machine_stopped,
      location_details: form.value.location_details,
      contact_phone: form.value.contact_phone,
    }

    if (form.value.asset_type === 'equipment') {
      payload.equipment_id = form.value.equipment_id
    } else {
      payload.truck_id = form.value.truck_id
    }

    await api.post('/intervention-requests', payload)
    showCreateModal.value = false
    showSuccess('Demande créée avec succès')
    fetchRequests()
    fetchStats()
  } catch (err) {
    showError(err.response?.data?.message || 'Erreur lors de la création')
  } finally {
    saving.value = false
  }
}

async function openDetailModal(request) {
  try {
    const response = await api.get(`/intervention-requests/${request.id}`)
    selectedRequest.value = response.data
    showDetailModal.value = true
  } catch (err) {
    showError('Erreur lors du chargement des détails')
  }
}

function openValidateModal(request, action = 'approve') {
  selectedRequest.value = request
  validateForm.value = { action, comment: '', reason: '' }
  showValidateModal.value = true
}

async function validateRequest() {
  saving.value = true
  try {
    if (validateForm.value.action === 'approve') {
      await api.post(`/intervention-requests/${selectedRequest.value.id}/approve`, {
        comment: validateForm.value.comment
      })
    } else {
      await api.post(`/intervention-requests/${selectedRequest.value.id}/reject`, {
        reason: validateForm.value.reason || validateForm.value.comment
      })
    }

    showValidateModal.value = false
    showDetailModal.value = false
    showSuccess(validateForm.value.action === 'approve' ? 'Demande approuvée' : 'Demande rejetée')
    fetchRequests()
    fetchStats()
  } catch (err) {
    showError(err.response?.data?.message || 'Erreur lors de la validation')
  } finally {
    saving.value = false
  }
}

function openConvertModal(request) {
  selectedRequest.value = request

  // Mapper urgence DI → priorité OT
  const priorityKeys = Object.keys(woPrioritiesMap.value)
  let mappedPriority = request.urgency
  if (!priorityKeys.includes(mappedPriority)) {
    mappedPriority = priorityKeys.includes('medium') ? 'medium' : (priorityKeys[0] || 'medium')
  }

  const typeKeys = Object.keys(woTypesMap.value)
  convertForm.value = {
    type: typeKeys.includes('corrective') ? 'corrective' : (typeKeys[0] || 'corrective'),
    priority: mappedPriority,
    assigned_to: '',
    scheduled_start: '',
  }
  showConvertModal.value = true
}

async function convertRequest() {
  saving.value = true
  try {
    const response = await api.post(`/intervention-requests/${selectedRequest.value.id}/convert`, convertForm.value)
    showConvertModal.value = false
    showDetailModal.value = false
    showSuccess(`Demande convertie en OT: ${response.data.work_order.code}`)
    fetchRequests()
    fetchStats()
  } catch (err) {
    showError(err.response?.data?.message || 'Erreur lors de la conversion')
  } finally {
    saving.value = false
  }
}

async function cancelRequest(request) {
  if (!confirm('Annuler cette demande ?')) return
  try {
    await api.post(`/intervention-requests/${request.id}/cancel`)
    showSuccess('Demande annulée')
    fetchRequests()
    fetchStats()
  } catch (err) {
    showError(err.response?.data?.message || 'Erreur lors de l\'annulation')
  }
}

async function deleteRequest(request) {
  if (!confirm('Supprimer cette demande ?')) return
  try {
    await api.delete(`/intervention-requests/${request.id}`)
    showSuccess('Demande supprimée')
    fetchRequests()
    fetchStats()
  } catch (err) {
    showError(err.response?.data?.message || 'Erreur lors de la suppression')
  }
}

function getAssetName(request) {
  if (request.asset_type === 'truck') {
    const truck = request.truck
    if (!truck) return '-'
    return truck.registration_number || `${truck.brand} ${truck.model}`
  }
  return request.equipment?.name || '-'
}

function getAssetCode(request) {
  if (request.asset_type === 'truck') {
    return request.truck?.code || ''
  }
  return request.equipment?.code || ''
}

function formatDate(date) {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('fr-FR', {
    day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit',
  })
}

function getStatusStyle(status) {
  const color = statusColors[status] || statusColors.pending
  return { backgroundColor: color.bg, color: color.text, borderColor: color.border }
}

function getUrgencyStyle(urgencyKey) {
  const u = urgencyLabels.value[urgencyKey]
  if (u) return { backgroundColor: u.bg, color: u.text }
  return { backgroundColor: '#e9ecef', color: '#495057' }
}

// ═══ Montage : charger settings PUIS données ═══
onMounted(async () => {
  await Promise.all([loadDISettings(), loadWOSettings()])
  fetchRequests()
  fetchStats()
  fetchEquipments()
  fetchTrucks()
  fetchUsers()
})
</script>

<template>
  <div class="di-page">
    <header class="page-header">
      <div>
        <h1>📋 Demandes d'intervention</h1>
        <p class="subtitle">Signalement de problèmes et demandes de maintenance</p>
      </div>
      <button class="btn btn-success" @click="openCreateModal"
        v-if="authStore.hasPermission('intervention_request:create')">
        <span class="btn-icon">+</span> Nouvelle demande
      </button>
    </header>

    <!-- Messages -->
    <div class="alert alert-success" v-if="successMessage">✅ {{ successMessage }}</div>
    <div class="alert alert-error" v-if="error">❌ {{ error }}</div>

    <!-- Stats cards -->
    <div class="stats-grid">
      <div class="stat-card pending">
        <div class="stat-value">{{ stats.by_status?.pending || stats.pending || 0 }}</div>
        <div class="stat-label">En attente</div>
      </div>
      <div class="stat-card approved">
        <div class="stat-value">{{ stats.by_status?.approved || stats.approved || 0 }}</div>
        <div class="stat-label">Approuvées</div>
      </div>
      <div class="stat-card converted">
        <div class="stat-value">{{ stats.by_status?.converted || stats.converted || 0 }}</div>
        <div class="stat-label">Converties</div>
      </div>
      <div class="stat-card equipment">
        <div class="stat-value">{{ stats.by_asset_type?.equipment || 0 }}</div>
        <div class="stat-label">⚙️ Équipements</div>
      </div>
      <div class="stat-card truck">
        <div class="stat-value">{{ stats.by_asset_type?.truck || 0 }}</div>
        <div class="stat-label">🚚 Camions</div>
      </div>
      <div class="stat-card stopped" v-if="stats.machine_stopped > 0">
        <div class="stat-value">{{ stats.machine_stopped }}</div>
        <div class="stat-label">⚠️ Arrêtés</div>
      </div>
    </div>

    <!-- Filtres (dynamiques) -->
    <div class="filters-card">
      <div class="filters-row">
        <div class="filter-group search-group">
          <span class="filter-icon">🔍</span>
          <input type="text" v-model="searchQuery" placeholder="Rechercher..." class="search-input" />
        </div>
        <div class="filter-group">
          <select v-model="filterAssetType" @change="fetchRequests">
            <option value="">Tous les types</option>
            <option value="equipment">⚙️ Équipements</option>
            <option value="truck">🚚 Camions</option>
          </select>
        </div>
        <div class="filter-group">
          <select v-model="filterStatus">
            <option value="">Tous les statuts</option>
            <option v-for="(label, key) in statusLabels" :key="key" :value="key">{{ label }}</option>
          </select>
        </div>
        <!-- Urgences dynamiques depuis settings -->
        <div class="filter-group">
          <select v-model="filterUrgency">
            <option value="">Toutes urgences</option>
            <option v-for="opt in urgencyOptions" :key="opt.value" :value="opt.value">
              {{ opt.label }}
            </option>
          </select>
        </div>
      </div>
    </div>

    <!-- Liste -->
    <div class="requests-list" v-if="!loading && filteredRequests.length">
      <div class="request-card" v-for="req in filteredRequests" :key="req.id"
        :class="{ 'machine-stopped': req.machine_stopped }">
        <div class="request-header">
          <div class="request-code">
            <span class="asset-type-icon">{{ assetTypeIcons[req.asset_type] }}</span>
            {{ req.code }}
          </div>
          <div class="request-badges">
            <span class="badge asset-badge" :class="req.asset_type">
              {{ assetTypeLabels[req.asset_type] }}
            </span>
            <!-- Urgence : affiche le LABEL dynamique -->
            <span class="badge urgency-badge" :style="getUrgencyStyle(req.urgency)">
              {{ getUrgencyLabel(req.urgency) }}
            </span>
            <span class="badge status-badge" :style="getStatusStyle(req.status)">
              {{ statusLabels[req.status] }}
            </span>
            <span class="badge machine-badge" v-if="req.machine_stopped">🛑 Arrêté</span>
          </div>
        </div>

        <h3 class="request-title">{{ req.title }}</h3>

        <div class="request-meta">
          <div class="meta-item">
            <span class="meta-icon">{{ assetTypeIcons[req.asset_type] }}</span>
            <span>
              <strong>{{ getAssetName(req) }}</strong>
              <small v-if="getAssetCode(req)"> ({{ getAssetCode(req) }})</small>
            </span>
          </div>
          <div class="meta-item">
            <span class="meta-icon">👤</span>
            <span>{{ req.requested_by?.name }}</span>
          </div>
          <div class="meta-item">
            <span class="meta-icon">📅</span>
            <span>{{ formatDate(req.created_at) }}</span>
          </div>
        </div>

        <div class="request-actions">
          <button class="btn btn-sm btn-primary" @click="openDetailModal(req)">
            Voir détails
          </button>
          <template v-if="req.status === 'pending'">
            <button class="btn btn-sm btn-success" @click="openValidateModal(req, 'approve')"
              v-if="authStore.hasPermission('intervention_request:validate')">
              ✓ Valider
            </button>
            <button class="btn btn-sm btn-warning" @click="cancelRequest(req)"
              v-if="authStore.hasPermission('intervention_request:update')">
              Annuler
            </button>
            <button class="btn btn-sm btn-danger" @click="deleteRequest(req)"
              v-if="authStore.hasPermission('intervention_request:delete')">
              Suppr.
            </button>
          </template>
          <button class="btn btn-sm btn-info" @click="openConvertModal(req)"
            v-if="req.status === 'approved' && authStore.hasPermission('intervention_request:convert')">
            → Créer OT
          </button>
        </div>
      </div>
    </div>

    <!-- Empty & Loading -->
    <div class="loading-state" v-if="loading">
      <div class="spinner"></div>
      <p>Chargement...</p>
    </div>
    <div class="empty-state" v-if="!loading && !filteredRequests.length">
      <div class="empty-icon">📋</div>
      <h3>Aucune demande trouvée</h3>
    </div>

    <!-- Modal Création -->
    <div class="modal-overlay" v-if="showCreateModal" @click.self="showCreateModal = false">
      <div class="modal">
        <div class="modal-header">
          <h2>📝 Nouvelle demande d'intervention</h2>
          <button class="close-btn" @click="showCreateModal = false">&times;</button>
        </div>
        <form @submit.prevent="createRequest" class="modal-body">
          <!-- Choix du type d'asset -->
          <div class="form-group">
            <label>Type d'actif *</label>
            <div class="asset-type-selector">
              <label class="asset-type-option" :class="{ active: form.asset_type === 'equipment' }">
                <input type="radio" v-model="form.asset_type" value="equipment" @change="onAssetTypeChange" />
                <span class="option-content">
                  <span class="option-icon">⚙️</span>
                  <span class="option-label">Équipement</span>
                </span>
              </label>
              <label class="asset-type-option" :class="{ active: form.asset_type === 'truck' }">
                <input type="radio" v-model="form.asset_type" value="truck" @change="onAssetTypeChange" />
                <span class="option-content">
                  <span class="option-icon">🚚</span>
                  <span class="option-label">Camion</span>
                </span>
              </label>
            </div>
          </div>

          <!-- Sélection de l'asset -->
          <div class="form-group" v-if="form.asset_type === 'equipment'">
            <label>Équipement concerné *</label>
            <select v-model="form.equipment_id" required>
              <option value="">-- Sélectionner un équipement --</option>
              <option v-for="eq in equipments" :key="eq.id" :value="eq.id">
                {{ eq.code }} - {{ eq.name }}
              </option>
            </select>
          </div>

          <div class="form-group" v-if="form.asset_type === 'truck'">
            <label>Camion concerné *</label>
            <select v-model="form.truck_id" required>
              <option value="">-- Sélectionner un camion --</option>
              <option v-for="truck in trucks" :key="truck.id" :value="truck.id">
                {{ truck.internal_code }} - {{ truck.registration_number }} ({{ truck.brand }} {{ truck.model }})
              </option>
            </select>
          </div>

          <div class="form-group">
            <label>Titre du problème *</label>
            <input type="text" v-model="form.title" required placeholder="Ex: Bruit anormal, fuite d'huile..." />
          </div>

          <div class="form-group">
            <label>Description détaillée *</label>
            <textarea v-model="form.description" required rows="4"
              placeholder="Décrivez le problème observé..."></textarea>
          </div>

          <div class="form-row">
            <!-- Urgence : value = CLÉ, affichage = LABEL dynamique -->
            <div class="form-group">
              <label>Niveau d'urgence *</label>
              <select v-model="form.urgency" required>
                <option v-for="opt in urgencyOptions" :key="opt.value" :value="opt.value">
                  {{ opt.label }}
                </option>
              </select>
            </div>
            <div class="form-group">
              <label>Téléphone de contact</label>
              <input type="tel" v-model="form.contact_phone" placeholder="Ex: 0555 XX XX XX" />
            </div>
          </div>

          <div class="form-group">
            <label>Précisions sur l'emplacement</label>
            <input type="text" v-model="form.location_details"
              :placeholder="form.asset_type === 'truck' ? 'Ex: Parking principal, sur route...' : 'Ex: Près de l\'entrée, niveau 2...'" />
          </div>

          <div class="form-group">
            <label class="checkbox-label">
              <input type="checkbox" v-model="form.machine_stopped" />
              <span class="checkbox-text">
                🛑 {{ form.asset_type === 'truck' ? 'Le camion est immobilisé' : 'La machine est à l\'arrêt' }}
              </span>
            </label>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" @click="showCreateModal = false">Annuler</button>
            <button type="submit" class="btn btn-primary" :disabled="saving">
              {{ saving ? 'Envoi...' : 'Envoyer la demande' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal Détail -->
    <div class="modal-overlay" v-if="showDetailModal" @click.self="showDetailModal = false">
      <div class="modal modal-large">
        <div class="modal-header">
          <h2>
            <span class="asset-type-icon">{{ assetTypeIcons[selectedRequest?.asset_type] }}</span>
            {{ selectedRequest?.code }}
          </h2>
          <button class="close-btn" @click="showDetailModal = false">&times;</button>
        </div>
        <div class="modal-body" v-if="selectedRequest">
          <div class="detail-badges">
            <span class="badge asset-badge" :class="selectedRequest.asset_type">
              {{ assetTypeLabels[selectedRequest.asset_type] }}
            </span>
            <!-- Urgence : LABEL dynamique -->
            <span class="badge urgency-badge" :style="getUrgencyStyle(selectedRequest.urgency)">
              {{ getUrgencyLabel(selectedRequest.urgency) }}
            </span>
            <span class="badge status-badge" :style="getStatusStyle(selectedRequest.status)">
              {{ statusLabels[selectedRequest.status] }}
            </span>
            <span class="badge machine-badge" v-if="selectedRequest.machine_stopped">
              🛑 {{ selectedRequest.asset_type === 'truck' ? 'Camion immobilisé' : 'Machine arrêtée' }}
            </span>
          </div>

          <h3 class="detail-title">{{ selectedRequest.title }}</h3>

          <div class="detail-section">
            <h4>Description</h4>
            <p class="detail-description">{{ selectedRequest.description }}</p>
          </div>

          <div class="detail-grid">
            <div class="detail-item asset-detail"
              v-if="selectedRequest.asset_type === 'equipment' && selectedRequest.equipment">
              <span class="detail-label">⚙️ Équipement</span>
              <span class="detail-value">
                {{ selectedRequest.equipment.name }}
                <small class="detail-code">({{ selectedRequest.equipment.code }})</small>
              </span>
              <span class="detail-sub" v-if="selectedRequest.equipment.location">
                📍 {{ selectedRequest.equipment.location.name }}
              </span>
            </div>

            <div class="detail-item asset-detail"
              v-if="selectedRequest.asset_type === 'truck' && selectedRequest.truck">
              <span class="detail-label">🚚 Camion</span>
              <span class="detail-value">
                {{ selectedRequest.truck.registration_number }}
                <small class="detail-code">({{ selectedRequest.truck.code }})</small>
              </span>
              <span class="detail-sub">
                {{ selectedRequest.truck.brand }} {{ selectedRequest.truck.model }}
              </span>
              <span class="detail-sub" v-if="selectedRequest.truck.mileage">
                📏 {{ selectedRequest.truck.mileage?.toLocaleString() }} km
              </span>
              <span class="detail-sub" v-if="selectedRequest.truck.current_driver">
                👤 {{ selectedRequest.truck.current_driver.name }}
              </span>
            </div>

            <div class="detail-item">
              <span class="detail-label">Demandé par</span>
              <span class="detail-value">{{ selectedRequest.requested_by?.name }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Date</span>
              <span class="detail-value">{{ formatDate(selectedRequest.created_at) }}</span>
            </div>
            <div class="detail-item" v-if="selectedRequest.contact_phone">
              <span class="detail-label">Téléphone</span>
              <span class="detail-value">{{ selectedRequest.contact_phone }}</span>
            </div>
            <div class="detail-item" v-if="selectedRequest.location_details">
              <span class="detail-label">Emplacement</span>
              <span class="detail-value">{{ selectedRequest.location_details }}</span>
            </div>
          </div>

          <!-- Validation info -->
          <div class="detail-section" v-if="selectedRequest.validated_by">
            <h4>✅ Approbation</h4>
            <div class="detail-grid">
              <div class="detail-item">
                <span class="detail-label">Validé par</span>
                <span class="detail-value">{{ selectedRequest.validated_by?.name }}</span>
              </div>
              <div class="detail-item">
                <span class="detail-label">Date</span>
                <span class="detail-value">{{ formatDate(selectedRequest.validated_at) }}</span>
              </div>
            </div>
            <p v-if="selectedRequest.validation_comment" class="validation-comment">
              💬 {{ selectedRequest.validation_comment }}
            </p>
          </div>

          <!-- Rejet info -->
          <div class="detail-section rejection-section" v-if="selectedRequest.rejected_by">
            <h4>❌ Rejet</h4>
            <div class="detail-grid">
              <div class="detail-item">
                <span class="detail-label">Rejeté par</span>
                <span class="detail-value">{{ selectedRequest.rejected_by?.name }}</span>
              </div>
              <div class="detail-item">
                <span class="detail-label">Date</span>
                <span class="detail-value">{{ formatDate(selectedRequest.rejected_at) }}</span>
              </div>
            </div>
            <p v-if="selectedRequest.rejection_reason" class="rejection-comment">
              💬 {{ selectedRequest.rejection_reason }}
            </p>
          </div>

          <!-- OT lié -->
          <div class="detail-section" v-if="selectedRequest.work_order">
            <h4>🔧 Ordre de travail créé</h4>
            <div class="ot-link">
              <span class="ot-code">{{ selectedRequest.work_order.code }}</span>
              <span class="ot-title">{{ selectedRequest.work_order.title }}</span>
              <span class="ot-status" :style="getStatusStyle(selectedRequest.work_order.status)">
                {{ statusLabels[selectedRequest.work_order.status] || selectedRequest.work_order.status }}
              </span>
            </div>
          </div>

          <!-- Actions -->
          <div class="detail-actions"
            v-if="selectedRequest.status === 'pending' || selectedRequest.status === 'approved'">
            <button class="btn btn-success" @click="openValidateModal(selectedRequest, 'approve')"
              v-if="selectedRequest.status === 'pending' && authStore.hasPermission('intervention_request:validate')">
              ✓ Approuver
            </button>
            <button class="btn btn-danger" @click="openValidateModal(selectedRequest, 'reject')"
              v-if="selectedRequest.status === 'pending' && authStore.hasPermission('intervention_request:validate')">
              ✕ Rejeter
            </button>
            <button class="btn btn-info" @click="openConvertModal(selectedRequest)"
              v-if="selectedRequest.status === 'approved' && authStore.hasPermission('intervention_request:convert')">
              → Convertir en OT
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Validation -->
    <div class="modal-overlay" v-if="showValidateModal" @click.self="showValidateModal = false">
      <div class="modal modal-small">
        <div class="modal-header" :class="{ 'danger': validateForm.action === 'reject' }">
          <h2>{{ validateForm.action === 'approve' ? '✓ Approuver' : '✕ Rejeter' }} la demande</h2>
          <button class="close-btn" @click="showValidateModal = false">&times;</button>
        </div>
        <form @submit.prevent="validateRequest" class="modal-body">
          <div class="form-group">
            <label>Décision</label>
            <div class="radio-group">
              <label class="radio-label">
                <input type="radio" v-model="validateForm.action" value="approve" />
                <span>✅ Approuver</span>
              </label>
              <label class="radio-label">
                <input type="radio" v-model="validateForm.action" value="reject" />
                <span>❌ Rejeter</span>
              </label>
            </div>
          </div>

          <div class="form-group" v-if="validateForm.action === 'approve'">
            <label>Commentaire (optionnel)</label>
            <textarea v-model="validateForm.comment" rows="3" placeholder="Commentaire optionnel..."></textarea>
          </div>

          <div class="form-group" v-if="validateForm.action === 'reject'">
            <label>Raison du rejet *</label>
            <textarea v-model="validateForm.reason" rows="3" placeholder="Expliquez la raison du rejet..."
              required></textarea>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" @click="showValidateModal = false">Annuler</button>
            <button type="submit" class="btn" :class="validateForm.action === 'approve' ? 'btn-success' : 'btn-danger'"
              :disabled="saving">
              {{ saving ? 'Traitement...' : 'Confirmer' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal Conversion (types/priorités OT dynamiques) -->
    <div class="modal-overlay" v-if="showConvertModal" @click.self="showConvertModal = false">
      <div class="modal">
        <div class="modal-header">
          <h2>🔄 Convertir en ordre de travail</h2>
          <button class="close-btn" @click="showConvertModal = false">&times;</button>
        </div>
        <form @submit.prevent="convertRequest" class="modal-body">
          <div class="info-box">
            <div class="info-box-header">
              <span class="asset-type-icon">{{ assetTypeIcons[selectedRequest?.asset_type] }}</span>
              <strong>{{ selectedRequest?.code }}</strong>
            </div>
            <div class="info-box-content">
              {{ selectedRequest?.title }}
            </div>
            <div class="info-box-asset">
              {{ getAssetName(selectedRequest) }}
            </div>
          </div>

          <div class="form-row">
            <!-- Type OT : dynamique depuis work_order settings -->
            <div class="form-group">
              <label>Type d'intervention *</label>
              <select v-model="convertForm.type" required>
                <option v-for="opt in woTypeOptions" :key="opt.value" :value="opt.value">
                  {{ opt.label }}
                </option>
              </select>
            </div>
            <!-- Priorité OT : dynamique depuis work_order settings -->
            <div class="form-group">
              <label>Priorité *</label>
              <select v-model="convertForm.priority" required>
                <option v-for="opt in woPriorityOptions" :key="opt.value" :value="opt.value">
                  {{ opt.label }}
                </option>
              </select>
            </div>
          </div>

          <div class="form-group">
            <label>Assigner à</label>
            <select v-model="convertForm.assigned_to">
              <option value="">-- Non assigné --</option>
              <option v-for="user in users" :key="user.id" :value="user.id">
                {{ user.name }}
              </option>
            </select>
          </div>

          <div class="form-group">
            <label>Date de début planifiée</label>
            <input type="datetime-local" v-model="convertForm.scheduled_start" />
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" @click="showConvertModal = false">Annuler</button>
            <button type="submit" class="btn btn-primary" :disabled="saving">
              {{ saving ? 'Création...' : 'Créer l\'OT' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* ═══ Styles identiques à l'original — aucun changement ═══ */
.di-page { padding: 30px; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; }
.page-header h1 { font-size: 28px; color: #2c3e50; margin-bottom: 5px; }
.subtitle { color: #7f8c8d; font-size: 14px; }
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 15px; margin-bottom: 20px; }
.stat-card { background: white; border-radius: 12px; padding: 20px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
.stat-card.pending { border-left: 4px solid #ffc107; }
.stat-card.approved { border-left: 4px solid #28a745; }
.stat-card.converted { border-left: 4px solid #17a2b8; }
.stat-card.equipment { border-left: 4px solid #6c757d; }
.stat-card.truck { border-left: 4px solid #007bff; }
.stat-card.stopped { border-left: 4px solid #dc3545; background: #fff5f5; }
.stat-value { font-size: 28px; font-weight: bold; color: #2c3e50; }
.stat-label { font-size: 12px; color: #7f8c8d; }
.filters-card { background: white; border-radius: 12px; padding: 15px 20px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
.filters-row { display: flex; gap: 15px; flex-wrap: wrap; }
.filter-group { position: relative; }
.search-group { flex: 1; min-width: 200px; }
.filter-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); }
.search-input { width: 100%; padding: 10px 10px 10px 40px; border: 1px solid #ddd; border-radius: 8px; }
.filter-group select { padding: 10px 15px; border: 1px solid #ddd; border-radius: 8px; min-width: 150px; }
.asset-type-selector { display: flex; gap: 15px; }
.asset-type-option { flex: 1; cursor: pointer; }
.asset-type-option input { display: none; }
.option-content { display: flex; flex-direction: column; align-items: center; padding: 20px; border: 2px solid #e0e0e0; border-radius: 12px; transition: all 0.2s; }
.asset-type-option.active .option-content, .asset-type-option:hover .option-content { border-color: #3498db; background: #f0f7ff; }
.option-icon { font-size: 32px; margin-bottom: 8px; }
.option-label { font-weight: 500; color: #2c3e50; }
.requests-list { display: flex; flex-direction: column; gap: 15px; }
.request-card { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); transition: transform 0.2s; }
.request-card:hover { transform: translateX(5px); }
.request-card.machine-stopped { border-left: 4px solid #dc3545; background: linear-gradient(to right, #fff5f5, white); }
.request-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
.request-code { font-weight: bold; color: #7f8c8d; font-size: 13px; display: flex; align-items: center; gap: 6px; }
.asset-type-icon { font-size: 16px; }
.request-badges { display: flex; gap: 8px; flex-wrap: wrap; }
.badge { padding: 4px 10px; border-radius: 15px; font-size: 11px; font-weight: 600; }
.badge.asset-badge { background: #e9ecef; color: #495057; }
.badge.asset-badge.truck { background: #e3f2fd; color: #1565c0; }
.badge.asset-badge.equipment { background: #f3e5f5; color: #7b1fa2; }
.machine-badge { background: #f8d7da; color: #721c24; }
.request-title { font-size: 16px; color: #2c3e50; margin: 0 0 12px 0; }
.request-meta { display: flex; gap: 20px; margin-bottom: 15px; flex-wrap: wrap; }
.meta-item { display: flex; align-items: center; gap: 6px; font-size: 13px; color: #7f8c8d; }
.meta-item strong { color: #2c3e50; }
.meta-item small { color: #95a5a6; }
.request-actions { display: flex; gap: 8px; flex-wrap: wrap; }
.alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; }
.alert-success { background: #d4edda; color: #155724; }
.alert-error { background: #f8d7da; color: #721c24; }
.loading-state, .empty-state { text-align: center; padding: 60px; background: white; border-radius: 12px; }
.spinner { width: 40px; height: 40px; border: 3px solid #eee; border-top-color: #3498db; border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto 20px; }
@keyframes spin { to { transform: rotate(360deg); } }
.empty-icon { font-size: 60px; margin-bottom: 15px; }
.modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 2000; }
.modal { background: white; border-radius: 16px; width: 100%; max-width: 550px; max-height: 90vh; overflow-y: auto; }
.modal-large { max-width: 650px; }
.modal-small { max-width: 400px; }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 20px; border-bottom: 1px solid #eee; }
.modal-header.danger { background: #fff5f5; }
.modal-header h2 { margin: 0; font-size: 18px; color: #2c3e50; display: flex; align-items: center; gap: 8px; }
.close-btn { background: none; border: none; font-size: 24px; cursor: pointer; color: #7f8c8d; }
.modal-body { padding: 20px; }
.modal-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
.form-group { margin-bottom: 15px; }
.form-group label { display: block; margin-bottom: 6px; font-weight: 500; color: #2c3e50; font-size: 13px; }
.form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; }
.form-group textarea { resize: vertical; }
.checkbox-label { display: flex; align-items: center; cursor: pointer; padding: 12px; background: #fff5f5; border-radius: 8px; border: 1px solid #f8d7da; }
.checkbox-label input { margin-right: 10px; }
.checkbox-text { font-weight: 500; color: #721c24; }
.radio-group { display: flex; gap: 20px; }
.radio-label { display: flex; align-items: center; cursor: pointer; }
.radio-label input { margin-right: 8px; }
.info-box { background: #e3f2fd; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
.info-box-header { display: flex; align-items: center; gap: 8px; margin-bottom: 5px; }
.info-box-content { color: #1565c0; margin-bottom: 5px; }
.info-box-asset { font-size: 12px; color: #64b5f6; }
.detail-badges { display: flex; gap: 10px; margin-bottom: 15px; flex-wrap: wrap; }
.detail-title { font-size: 20px; color: #2c3e50; margin: 0 0 20px 0; }
.detail-section { margin-bottom: 20px; }
.detail-section h4 { font-size: 12px; text-transform: uppercase; color: #7f8c8d; margin-bottom: 10px; }
.detail-description { background: #f8f9fa; padding: 15px; border-radius: 8px; white-space: pre-wrap; }
.detail-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
.detail-item { background: #f8f9fa; padding: 12px; border-radius: 8px; }
.detail-item.asset-detail { background: #e3f2fd; }
.detail-label { display: block; font-size: 11px; color: #7f8c8d; margin-bottom: 4px; }
.detail-value { font-weight: 500; color: #2c3e50; }
.detail-code { color: #7f8c8d; font-weight: normal; }
.detail-sub { display: block; font-size: 12px; color: #64b5f6; margin-top: 4px; }
.validation-comment { background: #d4edda; padding: 12px; border-radius: 8px; margin-top: 10px; }
.rejection-section { background: #fff5f5; padding: 15px; border-radius: 8px; }
.rejection-comment { background: #f8d7da; padding: 12px; border-radius: 8px; margin-top: 10px; color: #721c24; }
.ot-link { background: #d1ecf1; padding: 12px 15px; border-radius: 8px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
.ot-code { font-weight: bold; color: #0c5460; }
.ot-status { padding: 2px 8px; border-radius: 10px; font-size: 11px; }
.detail-actions { display: flex; gap: 10px; margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee; }
.btn { padding: 10px 20px; border: none; border-radius: 8px; font-weight: 500; cursor: pointer; }
.btn-sm { padding: 6px 12px; font-size: 13px; }
.btn-primary { background: #3498db; color: white; }
.btn-success { background: #27ae60; color: white; }
.btn-danger { background: #e74c3c; color: white; }
.btn-warning { background: #f39c12; color: white; }
.btn-secondary { background: #95a5a6; color: white; }
.btn-info { background: #17a2b8; color: white; }
.btn:hover { opacity: 0.9; }
.btn:disabled { opacity: 0.6; cursor: not-allowed; }

@media (max-width: 768px) {
  .form-row { grid-template-columns: 1fr; }
  .stats-grid { grid-template-columns: repeat(2, 1fr); }
  .asset-type-selector { flex-direction: column; }
}
</style>
