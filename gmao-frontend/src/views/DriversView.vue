<script setup>
import { ref, onMounted, computed } from 'vue'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
const drivers = ref([])
const loading = ref(true)
const showModal = ref(false)
const showHabModal = ref(false)
const showDetailModal = ref(false)
const editingDriver = ref(null)
const selectedDriver = ref(null)
const search = ref('')
const statusFilter = ref('')
const habStatusFilter = ref('')
const licenseTypeFilter = ref('')
const activeView = ref('table')
const pagination = ref({})

const form = ref({
  first_name: '',
  last_name: '',
  phone: '',
  email: '',
  license_number: '',
  license_type: '',
  license_expiry_date: '',
  medical_checkup_date: '',
  hire_date: '',
  status: 'active',
  address: '',
  emergency_contact_name: '',
  emergency_contact_phone: '',
  notes: '',
})

const habForm = ref({
  habilitation_id: '',
  obtained_date: '',
  expiry_date: '',
  certificate_number: '',
  notes: '',
})

const habilitations = ref([])
const saving = ref(false)
const error = ref('')

const licenseTypes = ['B', 'C', 'C1', 'CE', 'C1E', 'D', 'D1', 'DE']

const statusLabels = {
  active: { label: 'Actif', class: 'success', icon: '🟢' },
  inactive: { label: 'Inactif', class: 'danger', icon: '🔴' },
  suspended: { label: 'Suspendu', class: 'warning', icon: '🟠' },
}

const habStatusLabels = {
  valid: { label: 'Valide', class: 'success' },
  expired: { label: 'Expirée', class: 'danger' },
  expiring_soon: { label: 'Expire bientôt', class: 'warning' },
}

// Stats calculées
const stats = computed(() => {
  const all = drivers.value
  return {
    total: pagination.value.total || all.length,
    active: all.filter(d => d.status === 'active').length,
    inactive: all.filter(d => d.status === 'inactive').length,
    suspended: all.filter(d => d.status === 'suspended').length,
    habExpired: all.filter(d => d.habilitations_status === 'expired').length,
    habExpiring: all.filter(d => d.habilitations_status === 'expiring_soon').length,
    licenseExpiring: all.filter(d => isExpiringSoon(d.license_expiry_date)).length,
    medicalExpiring: all.filter(d => isExpiringSoon(d.medical_checkup_date)).length,
  }
})

// Alertes
const alerts = computed(() => {
  const items = []
  drivers.value.forEach(d => {
    if (isExpired(d.license_expiry_date)) {
      items.push({ driver: d, type: 'danger', message: `Permis expiré` })
    } else if (isExpiringSoon(d.license_expiry_date)) {
      items.push({ driver: d, type: 'warning', message: `Permis expire bientôt` })
    }
    if (isExpired(d.medical_checkup_date)) {
      items.push({ driver: d, type: 'danger', message: `Visite médicale expirée` })
    } else if (isExpiringSoon(d.medical_checkup_date)) {
      items.push({ driver: d, type: 'warning', message: `Visite médicale expire bientôt` })
    }
  })
  return items.slice(0, 5)
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

async function fetchDrivers(page = 1) {
  loading.value = true
  try {
    const params = { page, per_page: 15 }
    if (search.value) params.search = search.value
    if (statusFilter.value) params.status = statusFilter.value
    if (habStatusFilter.value) params.habilitations_status = habStatusFilter.value
    if (licenseTypeFilter.value) params.license_type = licenseTypeFilter.value

    const response = await api.get('/drivers', { params })
    drivers.value = response.data.data
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

async function fetchHabilitations() {
  try {
    const response = await api.get('/habilitations-list')
    habilitations.value = response.data
  } catch (err) {
    console.error('Erreur:', err)
  }
}

function openModal(driver = null) {
  editingDriver.value = driver
  if (driver) {
    form.value = {
      first_name: driver.first_name,
      last_name: driver.last_name,
      phone: driver.phone || '',
      email: driver.email || '',
      license_number: driver.license_number || '',
      license_type: driver.license_type || '',
      license_expiry_date: driver.license_expiry_date || '',
      medical_checkup_date: driver.medical_checkup_date || '',
      hire_date: driver.hire_date || '',
      status: driver.status,
      address: driver.address || '',
      emergency_contact_name: driver.emergency_contact_name || '',
      emergency_contact_phone: driver.emergency_contact_phone || '',
      notes: driver.notes || '',
    }
  } else {
    form.value = {
      first_name: '',
      last_name: '',
      phone: '',
      email: '',
      license_number: '',
      license_type: '',
      license_expiry_date: '',
      medical_checkup_date: '',
      hire_date: '',
      status: 'active',
      address: '',
      emergency_contact_name: '',
      emergency_contact_phone: '',
      notes: '',
    }
  }
  error.value = ''
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  editingDriver.value = null
}

function openDetailModal(driver) {
  selectedDriver.value = driver
  showDetailModal.value = true
}

function closeDetailModal() {
  showDetailModal.value = false
  selectedDriver.value = null
}

async function saveDriver() {
  saving.value = true
  error.value = ''

  try {
    if (editingDriver.value) {
      await api.put(`/drivers/${editingDriver.value.id}`, form.value)
    } else {
      await api.post('/drivers', form.value)
    }
    closeModal()
    fetchDrivers()
  } catch (err) {
    error.value = err.response?.data?.message || 'Erreur lors de la sauvegarde'
  } finally {
    saving.value = false
  }
}

async function deleteDriver(driver) {
  if (!confirm(`Supprimer le chauffeur ${driver.full_name} ?`)) return

  try {
    await api.delete(`/drivers/${driver.id}`)
    fetchDrivers()
  } catch (err) {
    alert('Erreur lors de la suppression')
  }
}

async function updateStatus(driver, newStatus) {
  try {
    await api.put(`/drivers/${driver.id}`, {
      ...driver,
      status: newStatus,
    })
    fetchDrivers()
  } catch (err) {
    alert('Erreur lors de la mise à jour')
  }
}

function openHabModal(driver) {
  selectedDriver.value = driver
  habForm.value = {
    habilitation_id: '',
    obtained_date: new Date().toISOString().split('T')[0],
    expiry_date: '',
    certificate_number: '',
    notes: '',
  }
  fetchHabilitations()
  showHabModal.value = true
}

function closeHabModal() {
  showHabModal.value = false
  selectedDriver.value = null
}

async function addHabilitation() {
  saving.value = true
  try {
    await api.post(`/drivers/${selectedDriver.value.id}/habilitations`, habForm.value)
    closeHabModal()
    fetchDrivers()
  } catch (err) {
    alert('Erreur lors de l\'ajout')
  } finally {
    saving.value = false
  }
}

async function removeHabilitation(driver, habId) {
  if (!confirm('Retirer cette habilitation ?')) return

  try {
    await api.delete(`/drivers/${driver.id}/habilitations/${habId}`)
    fetchDrivers()
  } catch (err) {
    alert('Erreur lors de la suppression')
  }
}

function getHabStatus(hab) {
  if (hab.is_expired) return 'expired'
  if (hab.is_expiring_soon) return 'expiring_soon'
  return 'valid'
}

function applyFilters() {
  fetchDrivers()
}

function resetFilters() {
  search.value = ''
  statusFilter.value = ''
  habStatusFilter.value = ''
  licenseTypeFilter.value = ''
  fetchDrivers()
}

onMounted(() => {
  fetchDrivers()
})
</script>

<template>
  <div class="drivers-page">
    <header class="page-header">
      <div>
        <h1>🚛 Chauffeurs</h1>
        <p class="subtitle">Gestion des chauffeurs et habilitations</p>
      </div>
      <button class="btn btn-primary" @click="openModal()">
        + Nouveau chauffeur
      </button>
    </header>

    <!-- Stats Cards -->
    <div class="stats-cards">
      <div class="stat-card" @click="statusFilter = ''; applyFilters()">
        <div class="stat-icon blue">👥</div>
        <div class="stat-content">
          <div class="stat-value">{{ stats.total }}</div>
          <div class="stat-label">Total</div>
        </div>
      </div>
      <div class="stat-card success" @click="statusFilter = 'active'; applyFilters()">
        <div class="stat-icon green">🟢</div>
        <div class="stat-content">
          <div class="stat-value">{{ stats.active }}</div>
          <div class="stat-label">Actifs</div>
        </div>
      </div>
      <div class="stat-card warning" @click="habStatusFilter = 'expiring_soon'; applyFilters()">
        <div class="stat-icon orange">⚠️</div>
        <div class="stat-content">
          <div class="stat-value">{{ stats.habExpiring }}</div>
          <div class="stat-label">Hab. expirent</div>
        </div>
      </div>
      <div class="stat-card danger" @click="habStatusFilter = 'expired'; applyFilters()">
        <div class="stat-icon red">🚨</div>
        <div class="stat-content">
          <div class="stat-value">{{ stats.habExpired }}</div>
          <div class="stat-label">Hab. expirées</div>
        </div>
      </div>
      <div class="stat-card info">
        <div class="stat-icon purple">🪪</div>
        <div class="stat-content">
          <div class="stat-value">{{ stats.licenseExpiring }}</div>
          <div class="stat-label">Permis à renouveler</div>
        </div>
      </div>
    </div>

    <!-- Alertes -->
    <div class="alerts-section" v-if="alerts.length">
      <div class="alerts-header">
        <span class="alerts-icon">🔔</span>
        <span>Alertes ({{ alerts.length }})</span>
      </div>
      <div class="alerts-list">
        <div v-for="(alert, index) in alerts" :key="index" class="alert-item" :class="alert.type">
          <span class="alert-driver">{{ alert.driver.full_name }}</span>
          <span class="alert-message">{{ alert.message }}</span>
          <button class="alert-action" @click="openDetailModal(alert.driver)">Voir</button>
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
          placeholder="Rechercher par nom, code, téléphone..."
          @input="applyFilters"
        />
      </div>
      <select v-model="statusFilter" @change="applyFilters">
        <option value="">Tous les statuts</option>
        <option value="active">Actif</option>
        <option value="inactive">Inactif</option>
        <option value="suspended">Suspendu</option>
      </select>
      <select v-model="licenseTypeFilter" @change="applyFilters">
        <option value="">Tous les permis</option>
        <option v-for="lt in licenseTypes" :key="lt" :value="lt">Permis {{ lt }}</option>
      </select>
      <select v-model="habStatusFilter" @change="applyFilters">
        <option value="">Toutes habilitations</option>
        <option value="valid">Habilitations OK</option>
        <option value="expiring_soon">Expirent bientôt</option>
        <option value="expired">Expirées</option>
      </select>
      <button class="btn btn-secondary btn-sm" @click="resetFilters" v-if="search || statusFilter || habStatusFilter || licenseTypeFilter">
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
    <div v-else-if="activeView === 'grid'" class="drivers-grid">
      <div
        v-for="driver in drivers"
        :key="driver.id"
        class="driver-card"
        :class="driver.habilitations_status"
        @click="openDetailModal(driver)"
      >
        <div class="driver-header">
          <div class="driver-avatar" :class="statusLabels[driver.status]?.class">
            {{ driver.first_name?.charAt(0) }}{{ driver.last_name?.charAt(0) }}
          </div>
          <div class="driver-info">
            <h3>{{ driver.full_name }}</h3>
            <span class="driver-code">{{ driver.code }}</span>
          </div>
          <span class="status-badge" :class="statusLabels[driver.status]?.class">
            {{ statusLabels[driver.status]?.icon }} {{ statusLabels[driver.status]?.label }}
          </span>
        </div>

        <div class="driver-details">
          <div class="detail-item" v-if="driver.phone">
            <span class="detail-icon">📱</span>
            <span>{{ driver.phone }}</span>
          </div>
          <div class="detail-item" v-if="driver.license_type">
            <span class="detail-icon">🪪</span>
            <span>Permis {{ driver.license_type }}</span>
            <span class="expiry-badge" :class="{
              danger: isExpired(driver.license_expiry_date),
              warning: isExpiringSoon(driver.license_expiry_date)
            }" v-if="driver.license_expiry_date">
              {{ isExpired(driver.license_expiry_date) ? 'Expiré' : 
                 isExpiringSoon(driver.license_expiry_date) ? `${getDaysUntil(driver.license_expiry_date)}j` : '' }}
            </span>
          </div>
          <div class="detail-item" v-if="driver.current_truck">
            <span class="detail-icon">🚛</span>
            <span>{{ driver.current_truck.registration_number }}</span>
          </div>
          <div class="detail-item" v-if="driver.email">
            <span class="detail-icon">✉️</span>
            <span>{{ driver.email }}</span>
          </div>
        </div>

        <!-- Medical Alert -->
        <div class="medical-alert" v-if="isExpired(driver.medical_checkup_date)">
          <span>🏥</span> Visite médicale expirée
        </div>
        <div class="medical-warning" v-else-if="isExpiringSoon(driver.medical_checkup_date)">
          <span>🏥</span> Visite médicale dans {{ getDaysUntil(driver.medical_checkup_date) }} jours
        </div>

        <!-- Habilitations -->
        <div class="driver-habilitations" v-if="driver.habilitations?.length">
          <div class="hab-title">
            <span>Habilitations</span>
            <span class="hab-count">{{ driver.habilitations.length }}</span>
          </div>
          <div class="hab-list">
            <div
              v-for="hab in driver.habilitations.slice(0, 4)"
              :key="hab.id"
              class="hab-badge"
              :class="getHabStatus(hab)"
              :title="`${hab.habilitation?.name} - Expire: ${formatDate(hab.expiry_date)}`"
            >
              {{ hab.habilitation?.code }}
              <button class="hab-remove" @click.stop="removeHabilitation(driver, hab.id)">×</button>
            </div>
            <div class="hab-more" v-if="driver.habilitations.length > 4">
              +{{ driver.habilitations.length - 4 }}
            </div>
          </div>
        </div>
        <div class="no-habilitations" v-else>
          <span>⚠️ Aucune habilitation</span>
        </div>

        <div class="driver-actions" @click.stop>
          <button class="btn-action" @click="openHabModal(driver)" title="Ajouter habilitation">
            🎓
          </button>
          <button class="btn-action" @click="openModal(driver)" title="Modifier">
            ✏️
          </button>
          <div class="status-dropdown">
            <button class="btn-action" title="Changer statut">⚡</button>
            <div class="dropdown-menu">
              <button @click="updateStatus(driver, 'active')">🟢 Actif</button>
              <button @click="updateStatus(driver, 'inactive')">🔴 Inactif</button>
              <button @click="updateStatus(driver, 'suspended')">🟠 Suspendu</button>
            </div>
          </div>
          <button class="btn-action danger" @click="deleteDriver(driver)" title="Supprimer">
            🗑️
          </button>
        </div>
      </div>

      <div v-if="drivers.length === 0" class="empty-state full-width">
        <span class="empty-icon">🚛</span>
        <h3>Aucun chauffeur trouvé</h3>
        <p>Ajoutez des chauffeurs ou modifiez vos filtres</p>
      </div>
    </div>

    <!-- Table View -->
    <div v-else class="table-container">
      <table class="drivers-table" v-if="drivers.length">
        <thead>
          <tr>
            <th>Chauffeur</th>
            <th>Contact</th>
            <th>Permis</th>
            <th>Visite médicale</th>
            <th>Habilitations</th>
            <th>Statut</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="driver in drivers" :key="driver.id" :class="driver.habilitations_status">
            <td class="driver-cell">
              <div class="driver-cell-content">
                <div class="avatar-sm" :class="statusLabels[driver.status]?.class">
                  {{ driver.first_name?.charAt(0) }}{{ driver.last_name?.charAt(0) }}
                </div>
                <div>
                  <div class="driver-name">{{ driver.full_name }}</div>
                  <div class="driver-code">{{ driver.code }}</div>
                </div>
              </div>
            </td>
            <td>
              <div class="contact-info">
                <span v-if="driver.phone">📱 {{ driver.phone }}</span>
                <span v-if="driver.email" class="email-text">✉️ {{ driver.email }}</span>
              </div>
            </td>
            <td>
              <div class="license-info" v-if="driver.license_type">
                <span class="license-type">{{ driver.license_type }}</span>
                <span class="license-expiry" :class="{
                  'text-danger': isExpired(driver.license_expiry_date),
                  'text-warning': isExpiringSoon(driver.license_expiry_date)
                }">
                  {{ formatDate(driver.license_expiry_date) }}
                  <span v-if="isExpired(driver.license_expiry_date)">⚠️</span>
                </span>
              </div>
              <span v-else class="text-muted">-</span>
            </td>
            <td>
              <span :class="{
                'text-danger': isExpired(driver.medical_checkup_date),
                'text-warning': isExpiringSoon(driver.medical_checkup_date)
              }">
                {{ formatDate(driver.medical_checkup_date) }}
                <span v-if="isExpired(driver.medical_checkup_date)">⚠️</span>
              </span>
            </td>
            <td>
              <div class="hab-cell" v-if="driver.habilitations?.length">
                <span
                  v-for="hab in driver.habilitations.slice(0, 3)"
                  :key="hab.id"
                  class="hab-mini"
                  :class="getHabStatus(hab)"
                  :title="hab.habilitation?.name"
                >
                  {{ hab.habilitation?.code }}
                </span>
                <span v-if="driver.habilitations.length > 3" class="hab-more-mini">
                  +{{ driver.habilitations.length - 3 }}
                </span>
              </div>
              <span v-else class="text-muted">Aucune</span>
            </td>
            <td>
              <span class="status-badge" :class="statusLabels[driver.status]?.class">
                {{ statusLabels[driver.status]?.icon }} {{ statusLabels[driver.status]?.label }}
              </span>
            </td>
            <td class="actions-cell">
              <div class="action-buttons">
                <button class="btn-icon primary" @click="openDetailModal(driver)" title="Détails">
                  👁️
                </button>
                <button class="btn-icon success" @click="openHabModal(driver)" title="Habilitation">
                  🎓
                </button>
                <button class="btn-icon warning" @click="openModal(driver)" title="Modifier">
                  ✏️
                </button>
                <button class="btn-icon danger" @click="deleteDriver(driver)" title="Supprimer">
                  🗑️
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-else class="empty-state">
        <span class="empty-icon">🚛</span>
        <h3>Aucun chauffeur trouvé</h3>
      </div>
    </div>

    <!-- Pagination -->
    <div class="pagination" v-if="pagination.last_page > 1">
      <button
        v-for="page in pagination.last_page"
        :key="page"
        :class="{ active: page === pagination.current_page }"
        @click="fetchDrivers(page)"
      >
        {{ page }}
      </button>
    </div>

    <!-- Modal Chauffeur -->
    <div class="modal-overlay" v-if="showModal" @click.self="closeModal">
      <div class="modal modal-lg">
        <div class="modal-header">
          <h2>{{ editingDriver ? '✏️ Modifier' : '➕ Nouveau' }} chauffeur</h2>
          <button class="close-btn" @click="closeModal">×</button>
        </div>

        <form @submit.prevent="saveDriver" class="modal-body">
          <div class="form-section">
            <h3>Informations personnelles</h3>
            <div class="form-row">
              <div class="form-group">
                <label>Prénom *</label>
                <input type="text" v-model="form.first_name" required placeholder="Jean" />
              </div>
              <div class="form-group">
                <label>Nom *</label>
                <input type="text" v-model="form.last_name" required placeholder="Dupont" />
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label>Téléphone</label>
                <input type="tel" v-model="form.phone" placeholder="06 12 34 56 78" />
              </div>
              <div class="form-group">
                <label>Email</label>
                <input type="email" v-model="form.email" placeholder="email@exemple.com" />
              </div>
            </div>

            <div class="form-group">
              <label>Adresse</label>
              <textarea v-model="form.address" rows="2" placeholder="Adresse complète..."></textarea>
            </div>
          </div>

          <div class="form-section">
            <h3>Permis de conduire</h3>
            <div class="form-row">
              <div class="form-group">
                <label>N° Permis</label>
                <input type="text" v-model="form.license_number" placeholder="123456789" />
              </div>
              <div class="form-group">
                <label>Type de permis</label>
                <select v-model="form.license_type">
                  <option value="">Sélectionner</option>
                  <option v-for="lt in licenseTypes" :key="lt" :value="lt">{{ lt }}</option>
                </select>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label>Date d'expiration</label>
                <input type="date" v-model="form.license_expiry_date" />
              </div>
              <div class="form-group">
                <label>Visite médicale</label>
                <input type="date" v-model="form.medical_checkup_date" />
              </div>
            </div>
          </div>

          <div class="form-section">
            <h3>Emploi</h3>
            <div class="form-row">
              <div class="form-group">
                <label>Date d'embauche</label>
                <input type="date" v-model="form.hire_date" />
              </div>
              <div class="form-group">
                <label>Statut</label>
                <div class="radio-group">
                  <label class="radio-item success" :class="{ selected: form.status === 'active' }">
                    <input type="radio" v-model="form.status" value="active" />
                    <span>🟢 Actif</span>
                  </label>
                  <label class="radio-item warning" :class="{ selected: form.status === 'suspended' }">
                    <input type="radio" v-model="form.status" value="suspended" />
                    <span>🟠 Suspendu</span>
                  </label>
                  <label class="radio-item danger" :class="{ selected: form.status === 'inactive' }">
                    <input type="radio" v-model="form.status" value="inactive" />
                    <span>🔴 Inactif</span>
                  </label>
                </div>
              </div>
            </div>
          </div>

          <div class="form-section">
            <h3>Contact d'urgence</h3>
            <div class="form-row">
              <div class="form-group">
                <label>Nom du contact</label>
                <input type="text" v-model="form.emergency_contact_name" placeholder="Nom du contact" />
              </div>
              <div class="form-group">
                <label>Téléphone d'urgence</label>
                <input type="tel" v-model="form.emergency_contact_phone" placeholder="06 12 34 56 78" />
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

    <!-- Modal Habilitation -->
    <div class="modal-overlay" v-if="showHabModal" @click.self="closeHabModal">
      <div class="modal">
        <div class="modal-header">
          <h2>🎓 Ajouter une habilitation</h2>
          <button class="close-btn" @click="closeHabModal">×</button>
        </div>

        <form @submit.prevent="addHabilitation" class="modal-body">
          <div class="driver-summary" v-if="selectedDriver">
            <div class="avatar-sm" :class="statusLabels[selectedDriver.status]?.class">
              {{ selectedDriver.first_name?.charAt(0) }}{{ selectedDriver.last_name?.charAt(0) }}
            </div>
            <div class="summary-info">
              <span class="summary-name">{{ selectedDriver.full_name }}</span>
              <span class="summary-code">{{ selectedDriver.code }}</span>
            </div>
          </div>

          <div class="form-group">
            <label>Habilitation *</label>
            <select v-model="habForm.habilitation_id" required>
              <option value="">Sélectionner une habilitation</option>
              <optgroup label="Obligatoires">
                <option v-for="hab in habilitations.filter(h => h.is_mandatory)" :key="hab.id" :value="hab.id">
                  {{ hab.code }} - {{ hab.name }}
                </option>
              </optgroup>
              <optgroup label="Optionnelles">
                <option v-for="hab in habilitations.filter(h => !h.is_mandatory)" :key="hab.id" :value="hab.id">
                  {{ hab.code }} - {{ hab.name }}
                </option>
              </optgroup>
            </select>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>Date d'obtention *</label>
              <input type="date" v-model="habForm.obtained_date" required />
            </div>
            <div class="form-group">
              <label>Date d'expiration</label>
              <input type="date" v-model="habForm.expiry_date" />
            </div>
          </div>

          <div class="form-group">
            <label>N° Certificat</label>
            <input type="text" v-model="habForm.certificate_number" placeholder="Numéro du certificat" />
          </div>

          <div class="form-group">
            <label>Notes</label>
            <textarea v-model="habForm.notes" rows="2" placeholder="Remarques..."></textarea>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" @click="closeHabModal">Annuler</button>
            <button type="submit" class="btn btn-primary" :disabled="saving">
              {{ saving ? 'Ajout...' : 'Ajouter' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal Détails -->
    <div class="modal-overlay" v-if="showDetailModal" @click.self="closeDetailModal">
      <div class="modal modal-lg">
        <div class="modal-header">
          <h2>📋 Détails du chauffeur</h2>
          <button class="close-btn" @click="closeDetailModal">×</button>
        </div>

        <div class="modal-body" v-if="selectedDriver">
          <div class="detail-header">
            <div class="detail-avatar" :class="statusLabels[selectedDriver.status]?.class">
              {{ selectedDriver.first_name?.charAt(0) }}{{ selectedDriver.last_name?.charAt(0) }}
            </div>
            <div class="detail-title">
              <h2>{{ selectedDriver.full_name }}</h2>
              <div class="detail-code">{{ selectedDriver.code }}</div>
              <div class="detail-badges">
                <span class="status-badge" :class="statusLabels[selectedDriver.status]?.class">
                  {{ statusLabels[selectedDriver.status]?.icon }} {{ statusLabels[selectedDriver.status]?.label }}
                </span>
              </div>
            </div>
          </div>

          <div class="detail-grid">
            <div class="detail-section">
              <h4>Contact</h4>
              <div class="detail-row">
                <span class="detail-label">Téléphone</span>
                <span class="detail-value">{{ selectedDriver.phone || '-' }}</span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Email</span>
                <span class="detail-value">{{ selectedDriver.email || '-' }}</span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Adresse</span>
                <span class="detail-value">{{ selectedDriver.address || '-' }}</span>
              </div>
            </div>

            <div class="detail-section">
              <h4>Permis de conduire</h4>
              <div class="detail-row">
                <span class="detail-label">Type</span>
                <span class="detail-value">{{ selectedDriver.license_type || '-' }}</span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Numéro</span>
                <span class="detail-value">{{ selectedDriver.license_number || '-' }}</span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Expiration</span>
                <span class="detail-value" :class="{
                  'text-danger': isExpired(selectedDriver.license_expiry_date),
                  'text-warning': isExpiringSoon(selectedDriver.license_expiry_date)
                }">
                  {{ formatDate(selectedDriver.license_expiry_date) }}
                  <span v-if="isExpired(selectedDriver.license_expiry_date)"> ⚠️ Expiré</span>
                  <span v-else-if="isExpiringSoon(selectedDriver.license_expiry_date)"> ⏰ Bientôt</span>
                </span>
              </div>
            </div>

            <div class="detail-section">
              <h4>Santé & Emploi</h4>
              <div class="detail-row">
                <span class="detail-label">Visite médicale</span>
                <span class="detail-value" :class="{
                  'text-danger': isExpired(selectedDriver.medical_checkup_date),
                  'text-warning': isExpiringSoon(selectedDriver.medical_checkup_date)
                }">
                  {{ formatDate(selectedDriver.medical_checkup_date) }}
                  <span v-if="isExpired(selectedDriver.medical_checkup_date)"> ⚠️ Expirée</span>
                </span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Date d'embauche</span>
                <span class="detail-value">{{ formatDate(selectedDriver.hire_date) }}</span>
              </div>
            </div>

            <div class="detail-section">
              <h4>Contact d'urgence</h4>
              <div class="detail-row">
                <span class="detail-label">Nom</span>
                <span class="detail-value">{{ selectedDriver.emergency_contact_name || '-' }}</span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Téléphone</span>
                <span class="detail-value">{{ selectedDriver.emergency_contact_phone || '-' }}</span>
              </div>
            </div>

            <!-- Habilitations -->
            <div class="detail-section full-width">
              <div class="section-header">
                <h4>🎓 Habilitations</h4>
                <button class="btn btn-sm btn-primary" @click="openHabModal(selectedDriver); closeDetailModal()">
                  + Ajouter
                </button>
              </div>
              <div class="habilitations-grid" v-if="selectedDriver.habilitations?.length">
                <div
                  v-for="hab in selectedDriver.habilitations"
                  :key="hab.id"
                  class="hab-card"
                  :class="getHabStatus(hab)"
                >
                  <div class="hab-card-header">
                    <span class="hab-code">{{ hab.habilitation?.code }}</span>
                    <span class="hab-status-badge" :class="getHabStatus(hab)">
                      {{ habStatusLabels[getHabStatus(hab)]?.label }}
                    </span>
                  </div>
                  <div class="hab-name">{{ hab.habilitation?.name }}</div>
                  <div class="hab-dates">
                    <span>Obtenu: {{ formatDate(hab.obtained_date) }}</span>
                    <span v-if="hab.expiry_date">Expire: {{ formatDate(hab.expiry_date) }}</span>
                  </div>
                  <div class="hab-cert" v-if="hab.certificate_number">
                    N° {{ hab.certificate_number }}
                  </div>
                  <button class="hab-card-remove" @click="removeHabilitation(selectedDriver, hab.id)">
                    🗑️
                  </button>
                </div>
              </div>
              <div class="no-data" v-else>
                Aucune habilitation attribuée
              </div>
            </div>

            <div class="detail-section full-width" v-if="selectedDriver.notes">
              <h4>Notes</h4>
              <p class="detail-text">{{ selectedDriver.notes }}</p>
            </div>
          </div>

          <div class="detail-actions">
            <button class="btn btn-primary" @click="openHabModal(selectedDriver); closeDetailModal()">
              🎓 Ajouter habilitation
            </button>
            <button class="btn btn-warning" @click="openModal(selectedDriver); closeDetailModal()">
              ✏️ Modifier
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.drivers-page {
  padding: 30px;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 25px;
}

.page-header h1 {
  font-size: 28px;
  color: #2c3e50;
  margin: 0;
}

.subtitle {
  color: #7f8c8d;
  margin: 5px 0 0;
}

.btn {
  padding: 10px 20px;
  border: none;
  border-radius: 8px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-primary { background: linear-gradient(135deg, #3498db, #2980b9); color: white; }
.btn-secondary { background: #ecf0f1; color: #2c3e50; }
.btn-warning { background: linear-gradient(135deg, #f39c12, #d68910); color: white; }
.btn-sm { padding: 6px 12px; font-size: 12px; }

/* Stats Cards */
.stats-cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 15px;
  margin-bottom: 25px;
}

.stat-card {
  background: white;
  border-radius: 12px;
  padding: 18px;
  display: flex;
  align-items: center;
  gap: 12px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
  cursor: pointer;
  transition: all 0.2s;
  border-left: 4px solid transparent;
}

.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.stat-card.success { border-left-color: #27ae60; }
.stat-card.warning { border-left-color: #f39c12; }
.stat-card.danger { border-left-color: #e74c3c; }
.stat-card.info { border-left-color: #9b59b6; }

.stat-icon {
  width: 45px;
  height: 45px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 22px;
}

.stat-icon.blue { background: #e8f4fd; }
.stat-icon.green { background: #d4edda; }
.stat-icon.orange { background: #fff3cd; }
.stat-icon.red { background: #f8d7da; }
.stat-icon.purple { background: #e8daef; }

.stat-value {
  font-size: 26px;
  font-weight: 700;
  color: #2c3e50;
}

.stat-label {
  font-size: 12px;
  color: #7f8c8d;
}

/* Alerts */
.alerts-section {
  background: white;
  border-radius: 12px;
  padding: 15px;
  margin-bottom: 25px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.alerts-header {
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 600;
  color: #2c3e50;
  margin-bottom: 10px;
}

.alerts-icon {
  font-size: 18px;
}

.alerts-list {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.alert-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 12px;
  border-radius: 8px;
  font-size: 13px;
}

.alert-item.warning {
  background: #fff3cd;
  color: #856404;
}

.alert-item.danger {
  background: #f8d7da;
  color: #721c24;
}

.alert-driver {
  font-weight: 600;
}

.alert-action {
  background: rgba(255,255,255,0.5);
  border: none;
  padding: 4px 10px;
  border-radius: 4px;
  cursor: pointer;
  font-size: 11px;
}

/* Filters */
.filters-bar {
  display: flex;
  gap: 12px;
  margin-bottom: 25px;
  flex-wrap: wrap;
  align-items: center;
}

.search-box {
  flex: 1;
  min-width: 250px;
  max-width: 350px;
  position: relative;
}

.search-box input {
  width: 100%;
  padding: 10px 15px 10px 40px;
  border: 1px solid #ddd;
  border-radius: 8px;
  font-size: 14px;
}

.search-icon {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
}

.filters-bar select {
  padding: 10px 15px;
  border: 1px solid #ddd;
  border-radius: 8px;
  font-size: 14px;
  min-width: 140px;
}

.view-toggle {
  display: flex;
  background: white;
  border-radius: 8px;
  overflow: hidden;
  border: 1px solid #ddd;
  margin-left: auto;
}

.view-toggle button {
  padding: 8px 12px;
  border: none;
  background: transparent;
  cursor: pointer;
  font-size: 16px;
}

.view-toggle button.active {
  background: #3498db;
  color: white;
}

/* Grid View */
.drivers-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
  gap: 20px;
}

.driver-card {
  background: white;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
  border-left: 4px solid #27ae60;
  transition: all 0.2s;
  cursor: pointer;
}

.driver-card:hover {
  box-shadow: 0 4px 15px rgba(0,0,0,0.1);
  transform: translateY(-2px);
}

.driver-card.expired { border-left-color: #e74c3c; }
.driver-card.expiring_soon { border-left-color: #f39c12; }

.driver-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 15px;
}

.driver-avatar {
  width: 50px;
  height: 50px;
  background: linear-gradient(135deg, #3498db, #2980b9);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-weight: 600;
  font-size: 18px;
}

.driver-avatar.success { background: linear-gradient(135deg, #27ae60, #1e8449); }
.driver-avatar.warning { background: linear-gradient(135deg, #f39c12, #d68910); }
.driver-avatar.danger { background: linear-gradient(135deg, #e74c3c, #c0392b); }

.driver-info {
  flex: 1;
}

.driver-info h3 {
  margin: 0;
  font-size: 16px;
  color: #2c3e50;
}

.driver-code {
  font-size: 12px;
  color: #7f8c8d;
}

.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 500;
}

.status-badge.success { background: #d4edda; color: #155724; }
.status-badge.warning { background: #fff3cd; color: #856404; }
.status-badge.danger { background: #f8d7da; color: #721c24; }

.driver-details {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 12px;
}

.detail-item {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 12px;
  color: #555;
  background: #f8f9fa;
  padding: 5px 10px;
  border-radius: 6px;
}

.expiry-badge {
  font-size: 10px;
  padding: 2px 6px;
  border-radius: 10px;
  margin-left: 4px;
}

.expiry-badge.danger { background: #f8d7da; color: #721c24; }
.expiry-badge.warning { background: #fff3cd; color: #856404; }

.medical-alert,
.medical-warning {
  padding: 8px 12px;
  border-radius: 6px;
  font-size: 12px;
  margin-bottom: 12px;
}

.medical-alert {
  background: #f8d7da;
  color: #721c24;
}

.medical-warning {
  background: #fff3cd;
  color: #856404;
}

/* Habilitations */
.driver-habilitations {
  margin-bottom: 15px;
}

.hab-title {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 11px;
  color: #7f8c8d;
  text-transform: uppercase;
  margin-bottom: 8px;
}

.hab-count {
  background: #e8f4fd;
  color: #3498db;
  padding: 2px 8px;
  border-radius: 10px;
  font-weight: 600;
}

.hab-list {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.hab-badge {
  display: flex;
  align-items: center;
  gap: 5px;
  padding: 4px 10px;
  border-radius: 15px;
  font-size: 11px;
  font-weight: 500;
}

.hab-badge.valid { background: #d4edda; color: #155724; }
.hab-badge.expired { background: #f8d7da; color: #721c24; }
.hab-badge.expiring_soon { background: #fff3cd; color: #856404; }

.hab-remove {
  background: none;
  border: none;
  cursor: pointer;
  font-size: 14px;
  opacity: 0.6;
  padding: 0;
  margin-left: 2px;
}

.hab-remove:hover {
  opacity: 1;
}

.hab-more {
  background: #e9ecef;
  color: #6c757d;
  padding: 4px 10px;
  border-radius: 15px;
  font-size: 11px;
}

.no-habilitations {
  font-size: 12px;
  color: #e74c3c;
  padding: 8px 12px;
  background: #fff5f5;
  border-radius: 6px;
  margin-bottom: 15px;
}

.driver-actions {
  display: flex;
  gap: 8px;
  justify-content: flex-end;
  border-top: 1px solid #eee;
  padding-top: 15px;
}

.btn-action {
  width: 36px;
  height: 36px;
  border: none;
  border-radius: 8px;
  background: #f8f9fa;
  cursor: pointer;
  font-size: 16px;
  transition: all 0.2s;
}

.btn-action:hover {
  background: #e9ecef;
  transform: scale(1.05);
}

.btn-action.danger:hover {
  background: #fee;
}

.status-dropdown {
  position: relative;
}

.status-dropdown .dropdown-menu {
  display: none;
  position: absolute;
  bottom: 100%;
  right: 0;
  background: white;
  border-radius: 8px;
  box-shadow: 0 4px 15px rgba(0,0,0,0.15);
  padding: 5px;
  margin-bottom: 5px;
  z-index: 100;
}

.status-dropdown:hover .dropdown-menu {
  display: block;
}

.dropdown-menu button {
  display: block;
  width: 100%;
  padding: 8px 15px;
  border: none;
  background: none;
  text-align: left;
  cursor: pointer;
  white-space: nowrap;
  border-radius: 4px;
  font-size: 13px;
}

.dropdown-menu button:hover {
  background: #f0f0f0;
}

/* Table View */
.table-container {
  background: white;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.drivers-table {
  width: 100%;
  border-collapse: collapse;
}

.drivers-table th {
  text-align: left;
  padding: 15px;
  background: #f8f9fa;
  font-size: 12px;
  text-transform: uppercase;
  color: #7f8c8d;
  font-weight: 600;
}

.drivers-table td {
  padding: 15px;
  border-top: 1px solid #eee;
  vertical-align: middle;
}

.drivers-table tr:hover {
  background: #f8f9fa;
}

.drivers-table tr.expired { background: #fff5f5; }
.drivers-table tr.expiring_soon { background: #fffbf0; }

.driver-cell-content {
  display: flex;
  align-items: center;
  gap: 12px;
}

.avatar-sm {
  width: 40px;
  height: 40px;
  background: linear-gradient(135deg, #3498db, #2980b9);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-weight: 600;
  font-size: 14px;
}

.avatar-sm.success { background: linear-gradient(135deg, #27ae60, #1e8449); }
.avatar-sm.warning { background: linear-gradient(135deg, #f39c12, #d68910); }
.avatar-sm.danger { background: linear-gradient(135deg, #e74c3c, #c0392b); }

.driver-name {
  font-weight: 500;
  color: #2c3e50;
}

.contact-info {
  display: flex;
  flex-direction: column;
  gap: 3px;
  font-size: 12px;
}

.email-text {
  color: #7f8c8d;
}

.license-info {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.license-type {
  font-weight: 600;
  color: #2c3e50;
}

.license-expiry {
  font-size: 12px;
  color: #7f8c8d;
}

.text-danger { color: #e74c3c !important; font-weight: 500; }
.text-warning { color: #f39c12 !important; font-weight: 500; }
.text-muted { color: #95a5a6; }

.hab-cell {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
}

.hab-mini {
  padding: 3px 8px;
  border-radius: 10px;
  font-size: 10px;
  font-weight: 500;
}

.hab-mini.valid { background: #d4edda; color: #155724; }
.hab-mini.expired { background: #f8d7da; color: #721c24; }
.hab-mini.expiring_soon { background: #fff3cd; color: #856404; }

.hab-more-mini {
  padding: 3px 8px;
  background: #e9ecef;
  color: #6c757d;
  border-radius: 10px;
  font-size: 10px;
}

.actions-cell {
  white-space: nowrap;
}

.action-buttons {
  display: flex;
  gap: 5px;
}

.btn-icon {
  width: 32px;
  height: 32px;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 14px;
  transition: all 0.2s;
  background: #f0f0f0;
}

.btn-icon:hover { background: #e0e0e0; }
.btn-icon.primary { background: #e8f4fd; }
.btn-icon.success { background: #d4edda; }
.btn-icon.warning { background: #fff3cd; }
.btn-icon.danger { background: #f8d7da; }

/* Empty & Loading */
.empty-state {
  text-align: center;
  padding: 60px;
  color: #7f8c8d;
}

.empty-state.full-width {
  grid-column: 1 / -1;
  background: white;
  border-radius: 12px;
}

.empty-icon {
  font-size: 60px;
  opacity: 0.5;
}

.loading-state {
  text-align: center;
  padding: 60px;
}

.spinner {
  width: 40px;
  height: 40px;
  border: 3px solid #eee;
  border-top-color: #3498db;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  margin: 0 auto 15px;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Pagination */
.pagination {
  display: flex;
  justify-content: center;
  gap: 5px;
  margin-top: 30px;
}

.pagination button {
  padding: 8px 14px;
  border: 1px solid #ddd;
  background: white;
  border-radius: 6px;
  cursor: pointer;
}

.pagination button.active {
  background: #3498db;
  color: white;
  border-color: #3498db;
}

/* Modal */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0,0,0,0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2000;
}

.modal {
  background: white;
  border-radius: 12px;
  width: 100%;
  max-width: 500px;
  max-height: 90vh;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.modal-lg {
  max-width: 750px;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px;
  border-bottom: 1px solid #eee;
}

.modal-header h2 {
  margin: 0;
  font-size: 18px;
  color: #2c3e50;
}

.close-btn {
  background: none;
  border: none;
  font-size: 24px;
  cursor: pointer;
  color: #7f8c8d;
}

.modal-body {
  padding: 20px;
  overflow-y: auto;
}

.form-section {
  margin-bottom: 25px;
}

.form-section h3 {
  font-size: 14px;
  color: #7f8c8d;
  text-transform: uppercase;
  margin: 0 0 15px;
  padding-bottom: 8px;
  border-bottom: 1px solid #eee;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 15px;
}

.form-group {
  margin-bottom: 15px;
}

.form-group label {
  display: block;
  margin-bottom: 5px;
  font-size: 13px;
  font-weight: 500;
  color: #555;
}

.form-group input,
.form-group select,
.form-group textarea {
  width: 100%;
  padding: 10px;
  border: 1px solid #ddd;
  border-radius: 6px;
  font-size: 14px;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
  outline: none;
  border-color: #3498db;
}

.radio-group {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.radio-item {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 12px;
  border: 2px solid #ddd;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s;
  font-size: 13px;
}

.radio-item input {
  display: none;
}

.radio-item.selected.success { border-color: #27ae60; background: #d4edda; }
.radio-item.selected.warning { border-color: #f39c12; background: #fff3cd; }
.radio-item.selected.danger { border-color: #e74c3c; background: #f8d7da; }

.form-error {
  background: #f8d7da;
  color: #721c24;
  padding: 10px;
  border-radius: 6px;
  margin-bottom: 15px;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding-top: 15px;
  border-top: 1px solid #eee;
  margin-top: 10px;
}

.driver-summary {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 15px;
  background: #f8f9fa;
  border-radius: 8px;
  margin-bottom: 20px;
}

.summary-info {
  display: flex;
  flex-direction: column;
}

.summary-name {
  font-weight: 600;
  color: #2c3e50;
}

.summary-code {
  font-size: 12px;
  color: #7f8c8d;
}

/* Detail Modal */
.detail-header {
  display: flex;
  align-items: center;
  gap: 20px;
  padding: 20px;
  background: #f8f9fa;
  border-radius: 12px;
  margin-bottom: 25px;
}

.detail-avatar {
  width: 70px;
  height: 70px;
  background: linear-gradient(135deg, #3498db, #2980b9);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-weight: 600;
  font-size: 24px;
}

.detail-avatar.success { background: linear-gradient(135deg, #27ae60, #1e8449); }
.detail-avatar.warning { background: linear-gradient(135deg, #f39c12, #d68910); }
.detail-avatar.danger { background: linear-gradient(135deg, #e74c3c, #c0392b); }

.detail-title h2 {
  margin: 0 0 5px;
  font-size: 22px;
  color: #2c3e50;
}

.detail-code {
  font-family: monospace;
  font-size: 12px;
  color: #7f8c8d;
  margin-bottom: 10px;
}

.detail-badges {
  display: flex;
  gap: 10px;
}

.detail-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}

.detail-section {
  background: #f8f9fa;
  padding: 15px;
  border-radius: 8px;
}

.detail-section.full-width {
  grid-column: 1 / -1;
}

.detail-section h4 {
  margin: 0 0 12px;
  font-size: 12px;
  text-transform: uppercase;
  color: #7f8c8d;
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 15px;
}

.section-header h4 {
  margin: 0;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  padding: 8px 0;
  border-bottom: 1px solid #e9ecef;
  font-size: 13px;
}

.detail-row:last-child {
  border-bottom: none;
}

.detail-label {
  color: #7f8c8d;
}

.detail-value {
  color: #2c3e50;
  font-weight: 500;
}

.detail-text {
  margin: 0;
  font-size: 14px;
  color: #555;
  line-height: 1.5;
}

/* Habilitations Grid */
.habilitations-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 12px;
}

.hab-card {
  background: white;
  border-radius: 8px;
  padding: 12px;
  border-left: 3px solid #27ae60;
  position: relative;
}

.hab-card.expired { border-left-color: #e74c3c; }
.hab-card.expiring_soon { border-left-color: #f39c12; }

.hab-card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.hab-code {
  font-weight: 700;
  color: #2c3e50;
}

.hab-status-badge {
  padding: 2px 8px;
  border-radius: 10px;
  font-size: 10px;
  font-weight: 500;
}

.hab-status-badge.valid { background: #d4edda; color: #155724; }
.hab-status-badge.expired { background: #f8d7da; color: #721c24; }
.hab-status-badge.expiring_soon { background: #fff3cd; color: #856404; }

.hab-name {
  font-size: 12px;
  color: #555;
  margin-bottom: 8px;
}

.hab-dates {
  display: flex;
  flex-direction: column;
  gap: 2px;
  font-size: 11px;
  color: #7f8c8d;
}

.hab-cert {
  font-size: 10px;
  color: #95a5a6;
  margin-top: 5px;
}

.hab-card-remove {
  position: absolute;
  top: 8px;
  right: 8px;
  background: none;
  border: none;
  cursor: pointer;
  opacity: 0;
  transition: opacity 0.2s;
}

.hab-card:hover .hab-card-remove {
  opacity: 1;
}

.no-data {
  text-align: center;
  padding: 20px;
  color: #95a5a6;
  font-style: italic;
}

.detail-actions {
  display: flex;
  gap: 10px;
  margin-top: 25px;
  padding-top: 20px;
  border-top: 1px solid #eee;
}

@media (max-width: 768px) {
  .form-row,
  .detail-grid {
    grid-template-columns: 1fr;
  }

  .filters-bar {
    flex-direction: column;
    align-items: stretch;
  }

  .search-box {
    max-width: 100%;
  }

  .view-toggle {
    margin-left: 0;
  }

  .radio-group {
    flex-direction: column;
  }
}
</style>
