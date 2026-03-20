<script setup>
import { ref, onMounted, computed } from 'vue'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
const trucks = ref([])
const drivers = ref([])
const loading = ref(true)
const showModal = ref(false)
const showDetailModal = ref(false)
const showImportModal = ref(false)
const editingTruck = ref(null)
const selectedTruck = ref(null)
const importFile = ref(null)
const importing = ref(false)
const importResult = ref(null)
const importError = ref('')
const exporting = ref(false)
const search = ref('')
const statusFilter = ref('')
const typeFilter = ref('')
const fuelFilter = ref('')
const activeView = ref('table')
const pagination = ref({})

const form = ref({
  internal_code: '',
  registration_number: '',
  brand: '',
  model: '',
  year: '',
  type: '',
  capacity: '',
  capacity_unit: 'tonnes',
  fuel_type: '',
  mileage: 0,
  status: 'available',
  registration_date: '',
  insurance_expiry_date: '',
  technical_inspection_date: '',
  next_maintenance_date: '',
  current_driver_id: '',
  notes: '',
})

const saving = ref(false)
const error = ref('')

const truckTypes = ['Porteur', 'Semi-remorque', 'Citerne', 'Benne', 'Plateau', 'Frigorifique', 'Fourgon', 'Autre']
const fuelTypes = ['Diesel', 'Essence', 'Électrique', 'Hybride', 'GNL']

const statusLabels = {
  available: { label: 'Disponible', class: 'success', icon: '🟢' },
  in_use: { label: 'En service', class: 'info', icon: '🔵' },
  maintenance: { label: 'Maintenance', class: 'warning', icon: '🟠' },
  out_of_service: { label: 'Hors service', class: 'danger', icon: '🔴' },
}

// Stats calculées
const stats = computed(() => {
  const all = trucks.value
  return {
    total: pagination.value.total || all.length,
    available: all.filter(t => t.status === 'available').length,
    inUse: all.filter(t => t.status === 'in_use').length,
    maintenance: all.filter(t => t.status === 'maintenance').length,
    outOfService: all.filter(t => t.status === 'out_of_service').length,
    withAlerts: all.filter(t => t.alerts?.length > 0).length,
  }
})

// Alertes globales
const globalAlerts = computed(() => {
  const items = []
  trucks.value.forEach(t => {
    if (isExpired(t.insurance_expiry_date)) {
      items.push({ truck: t, type: 'danger', message: 'Assurance expirée', icon: '📋' })
    } else if (isExpiringSoon(t.insurance_expiry_date)) {
      items.push({ truck: t, type: 'warning', message: `Assurance expire dans ${getDaysUntil(t.insurance_expiry_date)}j`, icon: '📋' })
    }
    if (isExpired(t.technical_inspection_date)) {
      items.push({ truck: t, type: 'danger', message: 'Contrôle technique expiré', icon: '🔧' })
    } else if (isExpiringSoon(t.technical_inspection_date)) {
      items.push({ truck: t, type: 'warning', message: `CT expire dans ${getDaysUntil(t.technical_inspection_date)}j`, icon: '🔧' })
    }
    if (t.next_maintenance_date && isExpired(t.next_maintenance_date)) {
      items.push({ truck: t, type: 'danger', message: 'Maintenance en retard', icon: '🛠️' })
    } else if (isExpiringSoon(t.next_maintenance_date)) {
      items.push({ truck: t, type: 'info', message: `Maintenance prévue dans ${getDaysUntil(t.next_maintenance_date)}j`, icon: '🛠️' })
    }
  })
  return items.slice(0, 6)
})

function isExpired(date) {
  if (!date) return false
  return new Date(date) < new Date()
}

function isExpiringSoon(date) {
  if (!date) return false
  const expiry = new Date(date)
  const today = new Date()
  const diffDays = Math.ceil((expiry - today) / (1000 * 60 * 60 * 24))
  return diffDays > 0 && diffDays <= 30
}

function getDaysUntil(date) {
  if (!date) return null
  const expiry = new Date(date)
  const today = new Date()
  return Math.ceil((expiry - today) / (1000 * 60 * 60 * 24))
}

function formatDate(date) {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('fr-FR')
}

function formatNumber(num) {
  if (!num && num !== 0) return '0'
  return num.toLocaleString('fr-FR')
}

// Formater la date pour les inputs type="date" (YYYY-MM-DD)
function formatDateForInput(date) {
  if (!date) return ''
  const d = new Date(date)
  if (isNaN(d.getTime())) return ''
  return d.toISOString().split('T')[0]
}

async function fetchTrucks(page = 1) {
  loading.value = true
  try {
    const params = { page, per_page: 15 }
    if (search.value) params.search = search.value
    if (statusFilter.value) params.status = statusFilter.value
    if (typeFilter.value) params.type = typeFilter.value
    if (fuelFilter.value) params.fuel_type = fuelFilter.value

    const response = await api.get('/trucks', { params })
    trucks.value = response.data.data
    pagination.value = {
      current_page: response.data.current_page,
      last_page: response.data.last_page,
      total: response.data.total,
    }
  } catch (err) {
    console.error('Erreur:', err)
  } finally {
    loading.value = false
  }
}

async function fetchDrivers() {
  try {
    const response = await api.get('/drivers-list')
    drivers.value = response.data
  } catch (err) {
    console.error('Erreur:', err)
  }
}

function openModal(truck = null) {
  editingTruck.value = truck
  fetchDrivers()

  if (truck) {
    form.value = {
      internal_code: truck.internal_code || '',
      registration_number: truck.registration_number || '',
      brand: truck.brand || '',
      model: truck.model || '',
      year: truck.year || '',
      type: truck.type || '',
      capacity: truck.capacity || '',
      capacity_unit: truck.capacity_unit || 'tonnes',
      fuel_type: truck.fuel_type || '',
      mileage: truck.mileage || 0,
      status: truck.status || 'available',
      registration_date: formatDateForInput(truck.registration_date),
      insurance_expiry_date: formatDateForInput(truck.insurance_expiry_date),
      technical_inspection_date: formatDateForInput(truck.technical_inspection_date),
      next_maintenance_date: formatDateForInput(truck.next_maintenance_date),
      current_driver_id: truck.current_driver_id || '',
      notes: truck.notes || '',
    }
  } else {
    form.value = {
      internal_code: '',
      registration_number: '',
      brand: '',
      model: '',
      year: new Date().getFullYear(),
      type: '',
      capacity: '',
      capacity_unit: 'tonnes',
      fuel_type: 'Diesel',
      mileage: 0,
      status: 'available',
      registration_date: '',
      insurance_expiry_date: '',
      technical_inspection_date: '',
      next_maintenance_date: '',
      current_driver_id: '',
      notes: '',
    }
  }
  error.value = ''
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  editingTruck.value = null
}

function openImportModal() {
  showImportModal.value = true
  importFile.value = null
  importResult.value = null
  importError.value = ''
}

function closeImportModal() {
  showImportModal.value = false
  importFile.value = null
  importResult.value = null
  importError.value = ''
}

function handleImportFile(event) {
  importFile.value = event.target.files?.[0] || null
}

async function importTrucks() {
  if (!importFile.value) {
    importError.value = 'Veuillez sélectionner un fichier CSV.'
    return
  }

  importing.value = true
  importError.value = ''
  importResult.value = null

  try {
    const formData = new FormData()
    formData.append('file', importFile.value)

    const response = await api.post('/trucks/import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })

    importResult.value = response.data
    fetchTrucks()
  } catch (err) {
    importError.value = err.response?.data?.message || 'Erreur lors de l\'importation.'
  } finally {
    importing.value = false
  }
}

async function exportTrucks() {
  exporting.value = true

  try {
    const params = new URLSearchParams()
    if (search.value) params.append('search', search.value)
    if (statusFilter.value) params.append('status', statusFilter.value)
    if (typeFilter.value) params.append('type', typeFilter.value)
    if (fuelFilter.value) params.append('fuel_type', fuelFilter.value)

    // Navigate to the export URL — the browser will download the file directly.
    const downloadUrl = `${api.defaults.baseURL}/trucks/export?${params.toString()}`
    window.location.href = downloadUrl
  } catch (err) {
    const status = err.response?.status
    const message = err.response?.data?.message || err.message || 'Erreur lors de l\'exportation du fichier.'
    alert(`Erreur ${status ?? ''} : ${message}`)
  } finally {
    exporting.value = false
  }
}

function openDetailModal(truck) {
  selectedTruck.value = truck
  showDetailModal.value = true
}

function closeDetailModal() {
  showDetailModal.value = false
  selectedTruck.value = null
}

async function saveTruck() {
  saving.value = true
  error.value = ''

  try {
    const data = { ...form.value }
    if (!data.current_driver_id) data.current_driver_id = null
    if (!data.capacity) data.capacity = null
    if (!data.year) data.year = null
    if (!data.registration_date) data.registration_date = null
    if (!data.insurance_expiry_date) data.insurance_expiry_date = null
    if (!data.technical_inspection_date) data.technical_inspection_date = null
    if (!data.next_maintenance_date) data.next_maintenance_date = null

    if (editingTruck.value) {
      await api.put(`/trucks/${editingTruck.value.id}`, data)
    } else {
      await api.post('/trucks', data)
    }
    closeModal()
    fetchTrucks()
  } catch (err) {
    error.value = err.response?.data?.message || 'Erreur lors de la sauvegarde'
  } finally {
    saving.value = false
  }
}

async function deleteTruck(truck) {
  if (!confirm(`Supprimer le camion ${truck.registration_number} ?`)) return

  try {
    await api.delete(`/trucks/${truck.id}`)
    fetchTrucks()
  } catch (err) {
    alert('Erreur lors de la suppression')
  }
}

async function updateStatus(truck, newStatus) {
  try {
    await api.put(`/trucks/${truck.id}`, {
      registration_number: truck.registration_number,
      status: newStatus,
    })
    fetchTrucks()
  } catch (err) {
    alert('Erreur lors de la mise à jour')
  }
}

function applyFilters() {
  fetchTrucks()
}

function resetFilters() {
  search.value = ''
  statusFilter.value = ''
  typeFilter.value = ''
  fuelFilter.value = ''
  fetchTrucks()
}

function getStatusColor(truck) {
  if (truck.alerts?.some(a => a.type === 'danger')) return 'danger'
  if (truck.alerts?.some(a => a.type === 'warning')) return 'warning'
  return statusLabels[truck.status]?.class || ''
}

onMounted(() => {
  fetchTrucks()
  fetchDrivers()
})
</script>

<template>
  <div class="trucks-page">
    <header class="page-header">
      <div>
        <h1>🚛 Flotte de camions</h1>
        <p class="subtitle">Gestion des véhicules et maintenance</p>
      </div>
      <div class="header-actions">
        <button class="btn btn-secondary" @click="openImportModal()">
          📥 Importer
        </button>
        <button class="btn btn-secondary" :disabled="exporting" @click="exportTrucks()">
          {{ exporting ? 'Export en cours...' : '📤 Exporter' }}
        </button>
        <button class="btn btn-primary" @click="openModal()">
          + Nouveau camion
        </button>
      </div>
    </header>

    <!-- Stats Cards -->
    <div class="stats-cards">
      <div class="stat-card" @click="statusFilter = ''; applyFilters()">
        <div class="stat-icon blue">🚛</div>
        <div class="stat-content">
          <div class="stat-value">{{ stats.total }}</div>
          <div class="stat-label">Total flotte</div>
        </div>
      </div>
      <div class="stat-card success" @click="statusFilter = 'available'; applyFilters()">
        <div class="stat-icon green">🟢</div>
        <div class="stat-content">
          <div class="stat-value">{{ stats.available }}</div>
          <div class="stat-label">Disponibles</div>
        </div>
      </div>
      <div class="stat-card info" @click="statusFilter = 'in_use'; applyFilters()">
        <div class="stat-icon cyan">🔵</div>
        <div class="stat-content">
          <div class="stat-value">{{ stats.inUse }}</div>
          <div class="stat-label">En service</div>
        </div>
      </div>
      <div class="stat-card warning" @click="statusFilter = 'maintenance'; applyFilters()">
        <div class="stat-icon orange">🔧</div>
        <div class="stat-content">
          <div class="stat-value">{{ stats.maintenance }}</div>
          <div class="stat-label">Maintenance</div>
        </div>
      </div>
      <div class="stat-card danger" @click="statusFilter = 'out_of_service'; applyFilters()">
        <div class="stat-icon red">🔴</div>
        <div class="stat-content">
          <div class="stat-value">{{ stats.outOfService }}</div>
          <div class="stat-label">Hors service</div>
        </div>
      </div>
      <div class="stat-card alert-card" v-if="stats.withAlerts > 0">
        <div class="stat-icon yellow">⚠️</div>
        <div class="stat-content">
          <div class="stat-value">{{ stats.withAlerts }}</div>
          <div class="stat-label">Avec alertes</div>
        </div>
      </div>
    </div>

    <!-- Alertes -->
    <div class="alerts-section" v-if="globalAlerts.length">
      <div class="alerts-header">
        <span class="alerts-icon">🔔</span>
        <span>Alertes & Échéances ({{ globalAlerts.length }})</span>
      </div>
      <div class="alerts-list">
        <div
          v-for="(alert, index) in globalAlerts"
          :key="index"
          class="alert-item"
          :class="alert.type"
        >
          <span class="alert-icon">{{ alert.icon }}</span>
          <span class="alert-truck">{{ alert.truck.internal_code || alert.truck.registration_number }}</span>
          <span class="alert-message">{{ alert.message }}</span>
          <button class="alert-action" @click="openDetailModal(alert.truck)">Voir</button>
        </div>
      </div>
    </div>

    <!-- Filtres -->
    <div class="filters-bar">
      <div class="search-box">
        <span class="search-icon">🔍</span>
        <input
          type="text"
          v-model="search"
          placeholder="Rechercher par N°, immatriculation, marque..."
          @input="applyFilters"
        />
      </div>
      <select v-model="statusFilter" @change="applyFilters">
        <option value="">Tous les statuts</option>
        <option value="available">Disponible</option>
        <option value="in_use">En service</option>
        <option value="maintenance">Maintenance</option>
        <option value="out_of_service">Hors service</option>
      </select>
      <select v-model="typeFilter" @change="applyFilters">
        <option value="">Tous les types</option>
        <option v-for="t in truckTypes" :key="t" :value="t">{{ t }}</option>
      </select>
      <select v-model="fuelFilter" @change="applyFilters">
        <option value="">Tous carburants</option>
        <option v-for="f in fuelTypes" :key="f" :value="f">{{ f }}</option>
      </select>
      <button class="btn btn-secondary btn-sm" @click="resetFilters" v-if="search || statusFilter || typeFilter || fuelFilter">
        ✕ Reset
      </button>
      <div class="view-toggle">
        <button :class="{ active: activeView === 'grid' }" @click="activeView = 'grid'" title="Vue grille">▦</button>
        <button :class="{ active: activeView === 'table' }" @click="activeView = 'table'" title="Vue tableau">☰</button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <p>Chargement...</p>
    </div>

    <!-- Grid View -->
    <div v-else-if="activeView === 'grid'" class="trucks-grid">
      <div
        v-for="truck in trucks"
        :key="truck.id"
        class="truck-card"
        :class="[getStatusColor(truck), { 'has-alerts': truck.alerts?.length > 0 }]"
        @click="openDetailModal(truck)"
      >
        <div class="truck-header">
          <div class="truck-visual">
            <span class="truck-icon">🚛</span>
            <span class="truck-type-badge" v-if="truck.type">{{ truck.type }}</span>
          </div>
          <div class="truck-info">
            <h3>
              <span class="internal-code" v-if="truck.internal_code">{{ truck.internal_code }} — </span>
              {{ truck.registration_number }}
            </h3>
            <span class="truck-code">{{ truck.code }}</span>
          </div>
          <span class="status-badge" :class="statusLabels[truck.status]?.class">
            {{ statusLabels[truck.status]?.icon }} {{ statusLabels[truck.status]?.label }}
          </span>
        </div>

        <div class="truck-specs">
          <div class="spec-item" v-if="truck.brand || truck.model">
            <span class="spec-icon">🏭</span>
            <span>{{ truck.brand }} {{ truck.model }}</span>
          </div>
          <div class="spec-item" v-if="truck.year">
            <span class="spec-icon">📅</span>
            <span>{{ truck.year }}</span>
          </div>
          <div class="spec-item" v-if="truck.capacity">
            <span class="spec-icon">📦</span>
            <span>{{ truck.capacity }} {{ truck.capacity_unit }}</span>
          </div>
          <div class="spec-item" v-if="truck.fuel_type">
            <span class="spec-icon">⛽</span>
            <span>{{ truck.fuel_type }}</span>
          </div>
        </div>

        <div class="truck-stats">
          <div class="stat-mini">
            <span class="stat-mini-value">{{ formatNumber(truck.mileage) }}</span>
            <span class="stat-mini-label">km</span>
          </div>
        </div>

        <!-- Chauffeur assigné -->
        <div class="truck-driver" v-if="truck.current_driver">
          <span class="driver-avatar">
            {{ truck.current_driver.first_name?.charAt(0) }}{{ truck.current_driver.last_name?.charAt(0) }}
          </span>
          <span class="driver-name">{{ truck.current_driver.first_name }} {{ truck.current_driver.last_name }}</span>
        </div>
        <div class="no-driver" v-else>
          <span>🚫 Aucun chauffeur assigné</span>
        </div>

        <!-- Alertes -->
        <div class="truck-alerts" v-if="truck.alerts?.length > 0">
          <div
            v-for="(alert, index) in truck.alerts.slice(0, 2)"
            :key="index"
            class="mini-alert"
            :class="alert.type"
          >
            {{ alert.type === 'danger' ? '⚠️' : '⏰' }} {{ alert.message }}
          </div>
          <div class="more-alerts" v-if="truck.alerts.length > 2">
            +{{ truck.alerts.length - 2 }} autre(s)
          </div>
        </div>

        <!-- Dates -->
        <div class="truck-dates">
          <div class="date-item" :class="{ expired: isExpired(truck.insurance_expiry_date), warning: isExpiringSoon(truck.insurance_expiry_date) }">
            <span class="date-icon">📋</span>
            <div>
              <span class="date-label">Assurance</span>
              <span class="date-value">{{ formatDate(truck.insurance_expiry_date) }}</span>
            </div>
          </div>
          <div class="date-item" :class="{ expired: isExpired(truck.technical_inspection_date), warning: isExpiringSoon(truck.technical_inspection_date) }">
            <span class="date-icon">🔧</span>
            <div>
              <span class="date-label">CT</span>
              <span class="date-value">{{ formatDate(truck.technical_inspection_date) }}</span>
            </div>
          </div>
        </div>

        <div class="truck-actions" @click.stop>
          <button class="btn-action" @click="openDetailModal(truck)" title="Détails">👁️</button>
          <button class="btn-action" @click="openModal(truck)" title="Modifier">✏️</button>
          <div class="status-dropdown">
            <button class="btn-action" title="Changer statut">⚡</button>
            <div class="dropdown-menu">
              <button @click="updateStatus(truck, 'available')">🟢 Disponible</button>
              <button @click="updateStatus(truck, 'in_use')">🔵 En service</button>
              <button @click="updateStatus(truck, 'maintenance')">🟠 Maintenance</button>
              <button @click="updateStatus(truck, 'out_of_service')">🔴 Hors service</button>
            </div>
          </div>
          <button class="btn-action danger" @click="deleteTruck(truck)" title="Supprimer">🗑️</button>
        </div>
      </div>

      <div v-if="trucks.length === 0" class="empty-state full-width">
        <span class="empty-icon">🚛</span>
        <h3>Aucun camion trouvé</h3>
        <p>Ajoutez des camions ou modifiez vos filtres</p>
      </div>
    </div>

    <!-- Table View -->
    <div v-else class="table-container">
      <table class="trucks-table" v-if="trucks.length">
        <thead>
          <tr>
            <th>N° Camion</th>
            <th>Véhicule</th>
            <th>Type</th>
            <th>Kilométrage</th>
            <th>Chauffeur</th>
            <th>Assurance</th>
            <th>CT</th>
            <th>Maintenance</th>
            <th>Statut</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="truck in trucks" :key="truck.id" :class="{ 'row-alert': truck.alerts?.length > 0 }">
            <td>
              <span class="code-badge" v-if="truck.internal_code">{{ truck.internal_code }}</span>
              <span v-else class="text-muted">{{ truck.code }}</span>
            </td>
            <td class="vehicle-cell">
              <div class="vehicle-info">
                <span class="vehicle-icon">🚛</span>
                <div>
                  <div class="vehicle-reg">{{ truck.registration_number }}</div>
                  <div class="vehicle-brand">{{ truck.brand }} {{ truck.model }} {{ truck.year || '' }}</div>
                </div>
              </div>
            </td>
            <td>
              <span class="type-badge" v-if="truck.type">{{ truck.type }}</span>
              <span v-else class="text-muted">-</span>
            </td>
            <td>
              <div class="mileage-info">
                <span class="mileage-value">{{ formatNumber(truck.mileage) }}</span>
                <span class="mileage-unit">km</span>
              </div>
            </td>
            <td>
              <div class="driver-cell" v-if="truck.current_driver">
                <span class="driver-avatar-sm">
                  {{ truck.current_driver.first_name?.charAt(0) }}{{ truck.current_driver.last_name?.charAt(0) }}
                </span>
                <span>{{ truck.current_driver.first_name }} {{ truck.current_driver.last_name }}</span>
              </div>
              <span v-else class="text-muted">Non assigné</span>
            </td>
            <td>
              <span :class="{
                'text-danger': isExpired(truck.insurance_expiry_date),
                'text-warning': isExpiringSoon(truck.insurance_expiry_date)
              }">
                {{ formatDate(truck.insurance_expiry_date) }}
                <span v-if="isExpired(truck.insurance_expiry_date)">⚠️</span>
              </span>
            </td>
            <td>
              <span :class="{
                'text-danger': isExpired(truck.technical_inspection_date),
                'text-warning': isExpiringSoon(truck.technical_inspection_date)
              }">
                {{ formatDate(truck.technical_inspection_date) }}
                <span v-if="isExpired(truck.technical_inspection_date)">⚠️</span>
              </span>
            </td>
            <td>
              <span :class="{
                'text-danger': isExpired(truck.next_maintenance_date),
                'text-warning': isExpiringSoon(truck.next_maintenance_date)
              }">
                {{ formatDate(truck.next_maintenance_date) }}
                <span v-if="isExpired(truck.next_maintenance_date)">⚠️</span>
              </span>
            </td>
            <td>
              <span class="status-badge" :class="statusLabels[truck.status]?.class">
                {{ statusLabels[truck.status]?.icon }} {{ statusLabels[truck.status]?.label }}
              </span>
            </td>
            <td class="actions-cell">
              <div class="action-buttons">
                <button class="btn-icon primary" @click="openDetailModal(truck)" title="Détails">👁️</button>
                <button class="btn-icon warning" @click="openModal(truck)" title="Modifier">✏️</button>
                <button class="btn-icon danger" @click="deleteTruck(truck)" title="Supprimer">🗑️</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-else class="empty-state">
        <span class="empty-icon">🚛</span>
        <h3>Aucun camion trouvé</h3>
      </div>
    </div>

    <!-- Pagination -->
    <div class="pagination" v-if="pagination.last_page > 1">
      <button
        v-for="page in pagination.last_page"
        :key="page"
        :class="{ active: page === pagination.current_page }"
        @click="fetchTrucks(page)"
      >
        {{ page }}
      </button>
    </div>

    <!-- Modal Création/Édition -->
    <div class="modal-overlay" v-if="showModal" @click.self="closeModal">
      <div class="modal modal-lg">
        <div class="modal-header">
          <h2>{{ editingTruck ? '✏️ Modifier' : '➕ Nouveau' }} camion</h2>
          <button class="close-btn" @click="closeModal">×</button>
        </div>

        <form @submit.prevent="saveTruck" class="modal-body">
          <div class="form-section">
            <h3>Identification</h3>
            <div class="form-row">
              <div class="form-group">
                <label>Code interne (N° camion)</label>
                <input type="text" v-model="form.internal_code" placeholder="Ex: C-001, CAM-12..." />
              </div>
              <div class="form-group">
                <label>Immatriculation *</label>
                <input type="text" v-model="form.registration_number" required placeholder="AA-123-BB" />
              </div>
              <div class="form-group">
                <label>Type de véhicule</label>
                <select v-model="form.type">
                  <option value="">Sélectionner</option>
                  <option v-for="t in truckTypes" :key="t" :value="t">{{ t }}</option>
                </select>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label>Marque</label>
                <input type="text" v-model="form.brand" placeholder="Ex: Renault" />
              </div>
              <div class="form-group">
                <label>Modèle</label>
                <input type="text" v-model="form.model" placeholder="Ex: T High" />
              </div>
              <div class="form-group">
                <label>Année</label>
                <input type="number" v-model="form.year" min="1900" :max="new Date().getFullYear() + 1" />
              </div>
            </div>
          </div>

          <div class="form-section">
            <h3>Caractéristiques techniques</h3>
            <div class="form-row">
              <div class="form-group">
                <label>Capacité</label>
                <input type="number" v-model="form.capacity" step="0.01" min="0" placeholder="Ex: 25" />
              </div>
              <div class="form-group">
                <label>Unité</label>
                <select v-model="form.capacity_unit">
                  <option value="tonnes">Tonnes</option>
                  <option value="m3">m³</option>
                  <option value="litres">Litres</option>
                  <option value="palettes">Palettes</option>
                </select>
              </div>
              <div class="form-group">
                <label>Carburant</label>
                <select v-model="form.fuel_type">
                  <option value="">Sélectionner</option>
                  <option v-for="ft in fuelTypes" :key="ft" :value="ft">{{ ft }}</option>
                </select>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label>Kilométrage actuel</label>
                <input type="number" v-model="form.mileage" min="0" placeholder="0" />
              </div>
            </div>
          </div>

          <div class="form-section">
            <h3>État & Affectation</h3>
            <div class="form-row">
              <div class="form-group">
                <label>Statut</label>
                <div class="radio-group">
                  <label class="radio-item success" :class="{ selected: form.status === 'available' }">
                    <input type="radio" v-model="form.status" value="available" />
                    <span>🟢 Disponible</span>
                  </label>
                  <label class="radio-item info" :class="{ selected: form.status === 'in_use' }">
                    <input type="radio" v-model="form.status" value="in_use" />
                    <span>🔵 En service</span>
                  </label>
                  <label class="radio-item warning" :class="{ selected: form.status === 'maintenance' }">
                    <input type="radio" v-model="form.status" value="maintenance" />
                    <span>🟠 Maintenance</span>
                  </label>
                  <label class="radio-item danger" :class="{ selected: form.status === 'out_of_service' }">
                    <input type="radio" v-model="form.status" value="out_of_service" />
                    <span>🔴 Hors service</span>
                  </label>
                </div>
              </div>
            </div>

            <div class="form-group">
              <label>Chauffeur assigné</label>
              <select v-model="form.current_driver_id">
                <option value="">Aucun chauffeur</option>
                <option v-for="d in drivers" :key="d.id" :value="d.id">
                  {{ d.first_name }} {{ d.last_name }} ({{ d.code }})
                </option>
              </select>
            </div>
          </div>

          <div class="form-section">
            <h3>Documents & Échéances</h3>
            <div class="form-row">
              <div class="form-group">
                <label>Date immatriculation</label>
                <input type="date" v-model="form.registration_date" />
              </div>
              <div class="form-group">
                <label>Expiration assurance</label>
                <input type="date" v-model="form.insurance_expiry_date" />
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>Contrôle technique (expiration)</label>
                <input type="date" v-model="form.technical_inspection_date" />
              </div>
              <div class="form-group">
                <label>Prochaine maintenance</label>
                <input type="date" v-model="form.next_maintenance_date" />
              </div>
            </div>
          </div>

          <div class="form-group">
            <label>Notes</label>
            <textarea v-model="form.notes" rows="2" placeholder="Remarques..."></textarea>
          </div>

          <div class="form-error" v-if="error">{{ error }}</div>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" @click="closeModal">Annuler</button>
            <button type="submit" class="btn btn-primary" :disabled="saving">
              {{ saving ? 'Enregistrement...' : 'Enregistrer' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal Importation -->
    <div class="modal-overlay" v-if="showImportModal" @click.self="closeImportModal">
      <div class="modal modal-md">
        <div class="modal-header">
          <h2>📥 Importer des camions</h2>
          <button class="close-btn" @click="closeImportModal">×</button>
        </div>

        <div class="modal-body">
          <p>Importez un fichier CSV contenant les informations des camions. Le champ <strong>immatriculation</strong> est obligatoire.</p>
          <div class="form-group">
            <label>Fichier CSV</label>
            <input type="file" accept=".xlsx,.xls,.csv,text/csv" @change="handleImportFile" />
          </div>

          <div class="form-group" v-if="importResult">
            <div class="import-summary">
              <p><strong>Import terminé</strong></p>
              <p>Ajoutés : {{ importResult.imported }}</p>
              <p>Mise à jour : {{ importResult.updated }}</p>
              <p>Ignorés : {{ importResult.skipped }}</p>
              <div v-if="importResult.errors && importResult.errors.length">
                <p><strong>Erreurs :</strong></p>
                <ul>
                  <li v-for="(err, idx) in importResult.errors" :key="idx">{{ err }}</li>
                </ul>
              </div>
            </div>
          </div>

          <div class="form-error" v-if="importError">{{ importError }}</div>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" @click="closeImportModal">Fermer</button>
            <button type="button" class="btn btn-primary" :disabled="importing" @click="importTrucks">
              {{ importing ? 'Importation...' : 'Lancer l\'import' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Détails -->
    <div class="modal-overlay" v-if="showDetailModal" @click.self="closeDetailModal">
      <div class="modal modal-lg">
        <div class="modal-header">
          <h2>📋 Détails du véhicule</h2>
          <button class="close-btn" @click="closeDetailModal">×</button>
        </div>

        <div class="modal-body" v-if="selectedTruck">
          <div class="detail-header">
            <div class="detail-visual">
              <span class="detail-icon">🚛</span>
              <span class="detail-type" v-if="selectedTruck.type">{{ selectedTruck.type }}</span>
            </div>
            <div class="detail-title">
              <h2>
                <span v-if="selectedTruck.internal_code" class="internal-code-lg">{{ selectedTruck.internal_code }}</span>
                {{ selectedTruck.registration_number }}
              </h2>
              <div class="detail-subtitle">
                {{ selectedTruck.brand }} {{ selectedTruck.model }} {{ selectedTruck.year || '' }}
              </div>
              <div class="detail-badges">
                <span class="status-badge" :class="statusLabels[selectedTruck.status]?.class">
                  {{ statusLabels[selectedTruck.status]?.icon }} {{ statusLabels[selectedTruck.status]?.label }}
                </span>
                <span class="code-badge-detail">{{ selectedTruck.code }}</span>
              </div>
            </div>
            <div class="detail-mileage">
              <div class="mileage-big">{{ formatNumber(selectedTruck.mileage) }}</div>
              <div class="mileage-label">kilomètres</div>
            </div>
          </div>

          <!-- Alertes -->
          <div class="detail-alerts" v-if="selectedTruck.alerts?.length">
            <div
              v-for="(alert, index) in selectedTruck.alerts"
              :key="index"
              class="detail-alert"
              :class="alert.type"
            >
              {{ alert.type === 'danger' ? '⚠️' : '⏰' }} {{ alert.message }}
            </div>
          </div>

          <div class="detail-grid">
            <div class="detail-section">
              <h4>Caractéristiques</h4>
              <div class="detail-row">
                <span class="detail-label">Code interne</span>
                <span class="detail-value">{{ selectedTruck.internal_code || '-' }}</span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Type</span>
                <span class="detail-value">{{ selectedTruck.type || '-' }}</span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Capacité</span>
                <span class="detail-value">
                  {{ selectedTruck.capacity ? `${selectedTruck.capacity} ${selectedTruck.capacity_unit}` : '-' }}
                </span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Carburant</span>
                <span class="detail-value">{{ selectedTruck.fuel_type || '-' }}</span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Marque / Modèle</span>
                <span class="detail-value">{{ selectedTruck.brand || '' }} {{ selectedTruck.model || '' }} {{ selectedTruck.year || '' }}</span>
              </div>
            </div>

            <div class="detail-section">
              <h4>Chauffeur assigné</h4>
              <div class="driver-card" v-if="selectedTruck.current_driver">
                <div class="driver-avatar-lg">
                  {{ selectedTruck.current_driver.first_name?.charAt(0) }}{{ selectedTruck.current_driver.last_name?.charAt(0) }}
                </div>
                <div class="driver-details">
                  <div class="driver-name-lg">
                    {{ selectedTruck.current_driver.first_name }} {{ selectedTruck.current_driver.last_name }}
                  </div>
                  <div class="driver-code">{{ selectedTruck.current_driver.code }}</div>
                  <div class="driver-phone" v-if="selectedTruck.current_driver.phone">
                    📱 {{ selectedTruck.current_driver.phone }}
                  </div>
                </div>
              </div>
              <div class="no-driver-card" v-else>
                <span>🚫</span>
                <p>Aucun chauffeur assigné</p>
                <button class="btn btn-sm btn-primary" @click="openModal(selectedTruck); closeDetailModal()">
                  Assigner
                </button>
              </div>
            </div>

            <div class="detail-section">
              <h4>📋 Documents & Échéances</h4>
              <div class="document-item" :class="{ expired: isExpired(selectedTruck.insurance_expiry_date), warning: isExpiringSoon(selectedTruck.insurance_expiry_date) }">
                <div class="doc-icon">🛡️</div>
                <div class="doc-info">
                  <span class="doc-label">Assurance</span>
                  <span class="doc-date">{{ formatDate(selectedTruck.insurance_expiry_date) }}</span>
                </div>
                <span class="doc-status" v-if="isExpired(selectedTruck.insurance_expiry_date)">Expirée</span>
                <span class="doc-status warning" v-else-if="isExpiringSoon(selectedTruck.insurance_expiry_date)">
                  {{ getDaysUntil(selectedTruck.insurance_expiry_date) }}j
                </span>
              </div>
              <div class="document-item" :class="{ expired: isExpired(selectedTruck.technical_inspection_date), warning: isExpiringSoon(selectedTruck.technical_inspection_date) }">
                <div class="doc-icon">🔧</div>
                <div class="doc-info">
                  <span class="doc-label">Contrôle technique</span>
                  <span class="doc-date">{{ formatDate(selectedTruck.technical_inspection_date) }}</span>
                </div>
                <span class="doc-status" v-if="isExpired(selectedTruck.technical_inspection_date)">Expiré</span>
                <span class="doc-status warning" v-else-if="isExpiringSoon(selectedTruck.technical_inspection_date)">
                  {{ getDaysUntil(selectedTruck.technical_inspection_date) }}j
                </span>
              </div>
              <div class="document-item" :class="{ expired: isExpired(selectedTruck.next_maintenance_date), warning: isExpiringSoon(selectedTruck.next_maintenance_date) }">
                <div class="doc-icon">🛠️</div>
                <div class="doc-info">
                  <span class="doc-label">Prochaine maintenance</span>
                  <span class="doc-date">{{ formatDate(selectedTruck.next_maintenance_date) }}</span>
                </div>
                <span class="doc-status" v-if="isExpired(selectedTruck.next_maintenance_date)">En retard</span>
                <span class="doc-status warning" v-else-if="isExpiringSoon(selectedTruck.next_maintenance_date)">
                  {{ getDaysUntil(selectedTruck.next_maintenance_date) }}j
                </span>
              </div>
            </div>

            <div class="detail-section">
              <h4>📅 Dates & Infos</h4>
              <div class="detail-row">
                <span class="detail-label">Immatriculation</span>
                <span class="detail-value">{{ formatDate(selectedTruck.registration_date) }}</span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Kilométrage</span>
                <span class="detail-value">{{ formatNumber(selectedTruck.mileage) }} km</span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Code système</span>
                <span class="detail-value" style="font-family: monospace;">{{ selectedTruck.code }}</span>
              </div>
            </div>

            <div class="detail-section full-width" v-if="selectedTruck.notes">
              <h4>Notes</h4>
              <p class="detail-text">{{ selectedTruck.notes }}</p>
            </div>
          </div>

          <div class="detail-actions">
            <button class="btn btn-warning" @click="openModal(selectedTruck); closeDetailModal()">
              ✏️ Modifier
            </button>
            <button class="btn btn-secondary" @click="updateStatus(selectedTruck, 'maintenance'); closeDetailModal()" v-if="selectedTruck.status !== 'maintenance'">
              🔧 Mettre en maintenance
            </button>
            <button class="btn btn-success" @click="updateStatus(selectedTruck, 'available'); closeDetailModal()" v-if="selectedTruck.status === 'maintenance'">
              🟢 Remettre disponible
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.trucks-page { padding: 30px; }

.page-header {
  display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;
}
.page-header .header-actions {
  display: flex;
  gap: 10px;
}
.page-header h1 { font-size: 28px; color: #2c3e50; margin: 0; }
.subtitle { color: #7f8c8d; margin: 5px 0 0; }

.btn { padding: 10px 20px; border: none; border-radius: 8px; font-weight: 500; cursor: pointer; transition: all 0.2s; }
.btn-primary { background: linear-gradient(135deg, #3498db, #2980b9); color: white; }
.btn-secondary { background: #ecf0f1; color: #2c3e50; }
.btn-success { background: linear-gradient(135deg, #27ae60, #1e8449); color: white; }
.btn-warning { background: linear-gradient(135deg, #f39c12, #d68910); color: white; }
.btn-sm { padding: 6px 12px; font-size: 12px; }

/* Stats Cards */
.stats-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 25px; }
.stat-card { background: white; border-radius: 12px; padding: 18px; display: flex; align-items: center; gap: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); cursor: pointer; transition: all 0.2s; border-left: 4px solid transparent; }
.stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
.stat-card.success { border-left-color: #27ae60; }
.stat-card.info { border-left-color: #3498db; }
.stat-card.warning { border-left-color: #f39c12; }
.stat-card.danger { border-left-color: #e74c3c; }
.stat-card.alert-card { border-left-color: #f1c40f; }
.stat-icon { width: 45px; height: 45px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 22px; }
.stat-icon.blue { background: #e8f4fd; }
.stat-icon.green { background: #d4edda; }
.stat-icon.cyan { background: #d1ecf1; }
.stat-icon.orange { background: #fff3cd; }
.stat-icon.red { background: #f8d7da; }
.stat-icon.yellow { background: #fef3cd; }
.stat-value { font-size: 26px; font-weight: 700; color: #2c3e50; }
.stat-label { font-size: 12px; color: #7f8c8d; }

/* Alerts */
.alerts-section { background: white; border-radius: 12px; padding: 15px; margin-bottom: 25px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
.alerts-header { display: flex; align-items: center; gap: 8px; font-weight: 600; color: #2c3e50; margin-bottom: 12px; }
.alerts-icon { font-size: 18px; }
.alerts-list { display: flex; flex-wrap: wrap; gap: 10px; }
.alert-item { display: flex; align-items: center; gap: 8px; padding: 8px 12px; border-radius: 8px; font-size: 13px; }
.alert-item.warning { background: #fff3cd; color: #856404; }
.alert-item.danger { background: #f8d7da; color: #721c24; }
.alert-item.info { background: #d1ecf1; color: #0c5460; }
.alert-icon { font-size: 14px; }
.alert-truck { font-weight: 600; }
.alert-action { background: rgba(255,255,255,0.5); border: none; padding: 4px 10px; border-radius: 4px; cursor: pointer; font-size: 11px; margin-left: auto; }

/* Filters */
.filters-bar { display: flex; gap: 12px; margin-bottom: 25px; flex-wrap: wrap; align-items: center; }
.search-box { flex: 1; min-width: 250px; max-width: 350px; position: relative; }
.search-box input { width: 100%; padding: 10px 15px 10px 40px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; }
.search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); }
.filters-bar select { padding: 10px 15px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; min-width: 140px; }
.view-toggle { display: flex; background: white; border-radius: 8px; overflow: hidden; border: 1px solid #ddd; margin-left: auto; }
.view-toggle button { padding: 8px 12px; border: none; background: transparent; cursor: pointer; font-size: 16px; }
.view-toggle button.active { background: #3498db; color: white; }

/* Grid View */
.trucks-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(380px, 1fr)); gap: 20px; }
.truck-card { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); transition: all 0.2s; cursor: pointer; border-top: 4px solid #27ae60; }
.truck-card:hover { box-shadow: 0 8px 25px rgba(0,0,0,0.12); transform: translateY(-3px); }
.truck-card.success { border-top-color: #27ae60; }
.truck-card.info { border-top-color: #3498db; }
.truck-card.warning { border-top-color: #f39c12; }
.truck-card.danger { border-top-color: #e74c3c; }
.truck-card.has-alerts { border-left: 4px solid #f39c12; }
.truck-header { display: flex; align-items: center; gap: 12px; margin-bottom: 15px; }
.truck-visual { position: relative; }
.truck-icon { font-size: 40px; }
.truck-type-badge { position: absolute; bottom: -5px; right: -5px; background: #3498db; color: white; font-size: 8px; padding: 2px 5px; border-radius: 4px; }
.truck-info { flex: 1; }
.truck-info h3 { margin: 0; font-size: 18px; color: #2c3e50; }
.truck-code { font-size: 12px; color: #7f8c8d; }
.internal-code { color: #e67e22; font-weight: 700; }

.status-badge { display: inline-flex; align-items: center; gap: 4px; padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 500; }
.status-badge.success { background: #d4edda; color: #155724; }
.status-badge.info { background: #d1ecf1; color: #0c5460; }
.status-badge.warning { background: #fff3cd; color: #856404; }
.status-badge.danger { background: #f8d7da; color: #721c24; }

.truck-specs { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
.spec-item { display: flex; align-items: center; gap: 5px; font-size: 12px; color: #555; background: #f8f9fa; padding: 5px 10px; border-radius: 6px; }
.spec-icon { font-size: 12px; }
.truck-stats { display: flex; gap: 15px; margin-bottom: 12px; }
.stat-mini { display: flex; align-items: baseline; gap: 4px; }
.stat-mini-value { font-size: 18px; font-weight: 700; color: #2c3e50; }
.stat-mini-label { font-size: 11px; color: #7f8c8d; }

.truck-driver { display: flex; align-items: center; gap: 10px; padding: 10px; background: #e8f4fd; border-radius: 8px; margin-bottom: 12px; }
.driver-avatar { width: 36px; height: 36px; background: linear-gradient(135deg, #3498db, #2980b9); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 12px; }
.driver-name { font-size: 13px; font-weight: 500; color: #2c3e50; }
.no-driver { padding: 10px; background: #f8f9fa; border-radius: 8px; font-size: 12px; color: #95a5a6; margin-bottom: 12px; text-align: center; }

.truck-alerts { margin-bottom: 12px; }
.mini-alert { padding: 6px 10px; border-radius: 6px; font-size: 11px; margin-bottom: 4px; }
.mini-alert.danger { background: #f8d7da; color: #721c24; }
.mini-alert.warning { background: #fff3cd; color: #856404; }
.more-alerts { font-size: 11px; color: #7f8c8d; padding-left: 10px; }

.truck-dates { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px; }
.date-item { display: flex; align-items: center; gap: 8px; padding: 8px 10px; background: #f8f9fa; border-radius: 6px; }
.date-item.expired { background: #f8d7da; }
.date-item.warning { background: #fff3cd; }
.date-icon { font-size: 16px; }
.date-label { display: block; font-size: 10px; color: #7f8c8d; }
.date-value { font-size: 12px; font-weight: 500; color: #2c3e50; }
.date-item.expired .date-value { color: #721c24; }
.date-item.warning .date-value { color: #856404; }

.truck-actions { display: flex; gap: 8px; justify-content: flex-end; border-top: 1px solid #eee; padding-top: 15px; }
.btn-action { width: 36px; height: 36px; border: none; border-radius: 8px; background: #f8f9fa; cursor: pointer; font-size: 16px; transition: all 0.2s; }
.btn-action:hover { background: #e9ecef; transform: scale(1.05); }
.btn-action.danger:hover { background: #fee; }
.status-dropdown { position: relative; }
.status-dropdown .dropdown-menu { display: none; position: absolute; bottom: 100%; right: 0; background: white; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.15); padding: 5px; margin-bottom: 5px; z-index: 100; }
.status-dropdown:hover .dropdown-menu { display: block; }
.dropdown-menu button { display: block; width: 100%; padding: 8px 15px; border: none; background: none; text-align: left; cursor: pointer; white-space: nowrap; border-radius: 4px; font-size: 13px; }
.dropdown-menu button:hover { background: #f0f0f0; }

/* Table View */
.table-container { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
.trucks-table { width: 100%; border-collapse: collapse; }
.trucks-table th { text-align: left; padding: 15px; background: #f8f9fa; font-size: 12px; text-transform: uppercase; color: #7f8c8d; font-weight: 600; }
.trucks-table td { padding: 15px; border-top: 1px solid #eee; vertical-align: middle; }
.trucks-table tr:hover { background: #f8f9fa; }
.trucks-table tr.row-alert { background: #fffbf0; }

.vehicle-cell { min-width: 200px; }
.vehicle-info { display: flex; align-items: center; gap: 12px; }
.vehicle-icon { font-size: 28px; }
.vehicle-reg { font-weight: 600; color: #2c3e50; font-size: 15px; }
.vehicle-brand { font-size: 12px; color: #7f8c8d; }
.type-badge { padding: 4px 10px; background: #e8f4fd; color: #3498db; border-radius: 15px; font-size: 11px; }
.code-badge { background: #fff3cd; color: #d68910; padding: 4px 10px; border-radius: 15px; font-size: 12px; font-weight: 600; font-family: monospace; }
.mileage-info { display: flex; align-items: baseline; gap: 4px; }
.mileage-value { font-weight: 600; color: #2c3e50; }
.mileage-unit { font-size: 11px; color: #7f8c8d; }
.driver-cell { display: flex; align-items: center; gap: 8px; }
.driver-avatar-sm { width: 30px; height: 30px; background: #3498db; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 10px; }

.text-danger { color: #e74c3c !important; font-weight: 500; }
.text-warning { color: #f39c12 !important; font-weight: 500; }
.text-muted { color: #95a5a6; }

.actions-cell { white-space: nowrap; }
.action-buttons { display: flex; gap: 5px; }
.btn-icon { width: 32px; height: 32px; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; transition: all 0.2s; background: #f0f0f0; }
.btn-icon:hover { background: #e0e0e0; }
.btn-icon.primary { background: #e8f4fd; }
.btn-icon.warning { background: #fff3cd; }
.btn-icon.danger { background: #f8d7da; }

/* Empty & Loading */
.empty-state { text-align: center; padding: 60px; color: #7f8c8d; }
.empty-state.full-width { grid-column: 1 / -1; background: white; border-radius: 12px; }
.empty-icon { font-size: 60px; opacity: 0.5; }
.loading-state { text-align: center; padding: 60px; }
.spinner { width: 40px; height: 40px; border: 3px solid #eee; border-top-color: #3498db; border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto 15px; }
@keyframes spin { to { transform: rotate(360deg); } }

/* Pagination */
.pagination { display: flex; justify-content: center; gap: 5px; margin-top: 30px; }
.pagination button { padding: 8px 14px; border: 1px solid #ddd; background: white; border-radius: 6px; cursor: pointer; }
.pagination button.active { background: #3498db; color: white; border-color: #3498db; }

/* Modal */
.modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 2000; }
.modal { background: white; border-radius: 12px; width: 100%; max-width: 500px; max-height: 90vh; overflow: hidden; display: flex; flex-direction: column; }
.modal-lg { max-width: 750px; }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 20px; border-bottom: 1px solid #eee; }
.modal-header h2 { margin: 0; font-size: 18px; color: #2c3e50; }
.close-btn { background: none; border: none; font-size: 24px; cursor: pointer; color: #7f8c8d; }
.modal-body { padding: 20px; overflow-y: auto; }

.form-section { margin-bottom: 25px; }
.form-section h3 { font-size: 14px; color: #7f8c8d; text-transform: uppercase; margin: 0 0 15px; padding-bottom: 8px; border-bottom: 1px solid #eee; }
.form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; }
.form-group { margin-bottom: 15px; }
.form-group label { display: block; margin-bottom: 5px; font-size: 13px; font-weight: 500; color: #555; }
.form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; }
.form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #3498db; }
.radio-group { display: flex; flex-wrap: wrap; gap: 10px; }
.radio-item { display: flex; align-items: center; gap: 6px; padding: 8px 12px; border: 2px solid #ddd; border-radius: 8px; cursor: pointer; transition: all 0.2s; font-size: 13px; }
.radio-item input { display: none; }
.radio-item.selected.success { border-color: #27ae60; background: #d4edda; }
.radio-item.selected.info { border-color: #3498db; background: #d1ecf1; }
.radio-item.selected.warning { border-color: #f39c12; background: #fff3cd; }
.radio-item.selected.danger { border-color: #e74c3c; background: #f8d7da; }
.form-error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 6px; margin-bottom: 15px; }
.import-summary { background: #f4f6f8; border-radius: 10px; padding: 12px; margin: 10px 0; }
.import-summary ul { margin: 6px 0 0 16px; padding: 0; }
.modal-footer { display: flex; justify-content: flex-end; gap: 10px; padding-top: 15px; border-top: 1px solid #eee; margin-top: 10px; }

/* Detail Modal */
.detail-header { display: flex; align-items: center; gap: 20px; padding: 20px; background: #f8f9fa; border-radius: 12px; margin-bottom: 20px; }
.detail-visual { position: relative; }
.detail-icon { font-size: 60px; }
.detail-type { position: absolute; bottom: -5px; right: -10px; background: #3498db; color: white; font-size: 10px; padding: 3px 8px; border-radius: 4px; }
.detail-title { flex: 1; }
.detail-title h2 { margin: 0 0 5px; font-size: 24px; color: #2c3e50; }
.detail-subtitle { font-size: 14px; color: #7f8c8d; margin-bottom: 10px; }
.detail-badges { display: flex; gap: 10px; flex-wrap: wrap; }
.code-badge-detail { background: #e9ecef; color: #6c757d; padding: 4px 10px; border-radius: 15px; font-size: 11px; font-family: monospace; }
.internal-code-lg { display: inline-block; background: #fef3cd; color: #d68910; padding: 2px 10px; border-radius: 6px; font-size: 18px; margin-right: 8px; font-weight: 700; }
.detail-mileage { text-align: center; padding: 15px 20px; background: white; border-radius: 10px; }
.mileage-big { font-size: 28px; font-weight: 700; color: #2c3e50; }
.mileage-label { font-size: 11px; color: #7f8c8d; }

.detail-alerts { margin-bottom: 20px; }
.detail-alert { padding: 10px 15px; border-radius: 8px; margin-bottom: 8px; font-size: 13px; }
.detail-alert.danger { background: #f8d7da; color: #721c24; }
.detail-alert.warning { background: #fff3cd; color: #856404; }
.detail-alert.info { background: #d1ecf1; color: #0c5460; }

.detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
.detail-section { background: #f8f9fa; padding: 15px; border-radius: 8px; }
.detail-section.full-width { grid-column: 1 / -1; }
.detail-section h4 { margin: 0 0 12px; font-size: 12px; text-transform: uppercase; color: #7f8c8d; }
.detail-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #e9ecef; font-size: 13px; }
.detail-row:last-child { border-bottom: none; }
.detail-label { color: #7f8c8d; }
.detail-value { color: #2c3e50; font-weight: 500; }
.detail-text { margin: 0; font-size: 14px; color: #555; line-height: 1.5; }

.driver-card { display: flex; align-items: center; gap: 12px; padding: 12px; background: white; border-radius: 8px; }
.driver-avatar-lg { width: 50px; height: 50px; background: linear-gradient(135deg, #3498db, #2980b9); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 16px; }
.driver-details { flex: 1; }
.driver-name-lg { font-weight: 600; color: #2c3e50; margin-bottom: 2px; }
.driver-code { font-size: 11px; color: #7f8c8d; }
.driver-phone { font-size: 12px; color: #555; margin-top: 4px; }
.no-driver-card { text-align: center; padding: 20px; background: white; border-radius: 8px; }
.no-driver-card span { font-size: 30px; display: block; margin-bottom: 8px; opacity: 0.5; }
.no-driver-card p { margin: 0 0 10px; color: #95a5a6; font-size: 13px; }

.document-item { display: flex; align-items: center; gap: 12px; padding: 10px; background: white; border-radius: 6px; margin-bottom: 8px; }
.document-item.expired { background: #f8d7da; }
.document-item.warning { background: #fff3cd; }
.doc-icon { font-size: 20px; }
.doc-info { flex: 1; }
.doc-label { display: block; font-size: 11px; color: #7f8c8d; }
.doc-date { font-size: 13px; font-weight: 500; color: #2c3e50; }
.document-item.expired .doc-date { color: #721c24; }
.document-item.warning .doc-date { color: #856404; }
.doc-status { font-size: 10px; font-weight: 600; padding: 3px 8px; border-radius: 10px; background: #f8d7da; color: #721c24; }
.doc-status.warning { background: #fff3cd; color: #856404; }

.detail-actions { display: flex; gap: 10px; margin-top: 25px; padding-top: 20px; border-top: 1px solid #eee; }

@media (max-width: 768px) {
  .form-row, .detail-grid { grid-template-columns: 1fr; }
  .filters-bar { flex-direction: column; align-items: stretch; }
  .search-box { max-width: 100%; }
  .view-toggle { margin-left: 0; }
  .radio-group { flex-direction: column; }
  .detail-header { flex-direction: column; text-align: center; }
  .detail-mileage { width: 100%; }
}
</style>
