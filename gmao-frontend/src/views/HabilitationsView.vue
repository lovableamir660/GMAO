<script setup>
import { ref, onMounted, computed } from 'vue'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
const habilitations = ref([])
const clients = ref([])
const categories = ref([])
const loading = ref(true)
const showModal = ref(false)
const showDetailModal = ref(false)
const editingHab = ref(null)
const selectedHab = ref(null)
const search = ref('')
const clientFilter = ref('')
const categoryFilter = ref('')
const mandatoryFilter = ref('')
const activeFilter = ref('')
const activeView = ref('table')
const pagination = ref({})

const form = ref({
  client_id: '',
  name: '',
  description: '',
  category: '',
  validity_months: '',
  renewal_notice_days: 30,
  is_mandatory: false,
  is_active: true,
})

const saving = ref(false)
const error = ref('')

const commonCategories = [
  'Sécurité',
  'Produit dangereux',
  'Site industriel',
  'Transport',
  'Environnement',
  'Qualité',
  'Manutention',
  'Électrique',
  'Travail en hauteur'
]

const categoryIcons = {
  'Sécurité': '🛡️',
  'Produit dangereux': '☢️',
  'Site industriel': '🏭',
  'Transport': '🚛',
  'Environnement': '🌿',
  'Qualité': '✅',
  'Manutention': '📦',
  'Électrique': '⚡',
  'Travail en hauteur': '🏗️',
}

const categoryColors = {
  'Sécurité': 'orange',
  'Produit dangereux': 'red',
  'Site industriel': 'gray',
  'Transport': 'blue',
  'Environnement': 'green',
  'Qualité': 'teal',
  'Manutention': 'purple',
  'Électrique': 'yellow',
  'Travail en hauteur': 'cyan',
}

// Stats calculées
const stats = computed(() => {
  const all = habilitations.value
  return {
    total: pagination.value.total || all.length,
    active: all.filter(h => h.is_active).length,
    inactive: all.filter(h => !h.is_active).length,
    mandatory: all.filter(h => h.is_mandatory).length,
    general: all.filter(h => !h.client_id).length,
    clientSpecific: all.filter(h => h.client_id).length,
    totalDrivers: all.reduce((sum, h) => sum + (h.drivers_count || 0), 0),
    expiringSoon: all.reduce((sum, h) => sum + (h.expiring_soon_count || 0), 0),
    expired: all.reduce((sum, h) => sum + (h.expired_count || 0), 0),
  }
})

// Grouper par catégorie
const habilitationsByCategory = computed(() => {
  const grouped = {}
  habilitations.value.forEach(h => {
    const cat = h.category || 'Autre'
    if (!grouped[cat]) grouped[cat] = []
    grouped[cat].push(h)
  })
  return grouped
})

// Grouper par client
const habilitationsByClient = computed(() => {
  const grouped = { 'Générales': [] }
  habilitations.value.forEach(h => {
    if (!h.client_id) {
      grouped['Générales'].push(h)
    } else {
      const clientName = h.client?.name || 'Client inconnu'
      if (!grouped[clientName]) grouped[clientName] = []
      grouped[clientName].push(h)
    }
  })
  return grouped
})

function getCategoryIcon(category) {
  return categoryIcons[category] || '📋'
}

function getCategoryColor(category) {
  return categoryColors[category] || 'gray'
}

async function fetchHabilitations(page = 1) {
  loading.value = true
  try {
    const params = { page, per_page: 20 }
    if (search.value) params.search = search.value
    if (clientFilter.value) params.client_id = clientFilter.value
    if (categoryFilter.value) params.category = categoryFilter.value
    if (mandatoryFilter.value !== '') params.is_mandatory = mandatoryFilter.value
    if (activeFilter.value !== '') params.is_active = activeFilter.value

    const response = await api.get('/habilitations', { params })
    habilitations.value = response.data.data
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

async function fetchClients() {
  try {
    const response = await api.get('/clients-list')
    clients.value = response.data
  } catch (err) {
    console.error('Erreur:', err)
  }
}

async function fetchCategories() {
  try {
    const response = await api.get('/habilitations-categories')
    categories.value = response.data
  } catch (err) {
    console.error('Erreur:', err)
  }
}

function openModal(hab = null) {
  editingHab.value = hab
  fetchClients()

  if (hab) {
    form.value = {
      client_id: hab.client_id || '',
      name: hab.name,
      description: hab.description || '',
      category: hab.category || '',
      validity_months: hab.validity_months || '',
      renewal_notice_days: hab.renewal_notice_days || 30,
      is_mandatory: hab.is_mandatory,
      is_active: hab.is_active,
    }
  } else {
    form.value = {
      client_id: '',
      name: '',
      description: '',
      category: '',
      validity_months: 12,
      renewal_notice_days: 30,
      is_mandatory: false,
      is_active: true,
    }
  }
  error.value = ''
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  editingHab.value = null
}

function openDetailModal(hab) {
  selectedHab.value = hab
  showDetailModal.value = true
}

function closeDetailModal() {
  showDetailModal.value = false
  selectedHab.value = null
}

async function saveHabilitation() {
  saving.value = true
  error.value = ''

  try {
    const data = { ...form.value }
    if (!data.client_id) data.client_id = null
    if (!data.validity_months) data.validity_months = null

    if (editingHab.value) {
      await api.put(`/habilitations/${editingHab.value.id}`, data)
    } else {
      await api.post('/habilitations', data)
    }
    closeModal()
    fetchHabilitations()
    fetchCategories()
  } catch (err) {
    error.value = err.response?.data?.message || 'Erreur lors de la sauvegarde'
  } finally {
    saving.value = false
  }
}

async function deleteHabilitation(hab) {
  if (hab.drivers_count > 0) {
    alert(`Impossible de supprimer : ${hab.drivers_count} chauffeur(s) possède(nt) cette habilitation.`)
    return
  }
  if (!confirm(`Supprimer l'habilitation "${hab.name}" ?`)) return

  try {
    await api.delete(`/habilitations/${hab.id}`)
    fetchHabilitations()
  } catch (err) {
    alert('Erreur lors de la suppression')
  }
}

async function toggleActive(hab) {
  try {
    await api.put(`/habilitations/${hab.id}`, {
      ...hab,
      is_active: !hab.is_active,
    })
    fetchHabilitations()
  } catch (err) {
    alert('Erreur lors de la mise à jour')
  }
}

async function toggleMandatory(hab) {
  try {
    await api.put(`/habilitations/${hab.id}`, {
      ...hab,
      is_mandatory: !hab.is_mandatory,
    })
    fetchHabilitations()
  } catch (err) {
    alert('Erreur lors de la mise à jour')
  }
}

async function duplicateHabilitation(hab) {
  form.value = {
    client_id: '',
    name: hab.name + ' (copie)',
    description: hab.description || '',
    category: hab.category || '',
    validity_months: hab.validity_months || '',
    renewal_notice_days: hab.renewal_notice_days || 30,
    is_mandatory: hab.is_mandatory,
    is_active: true,
  }
  editingHab.value = null
  fetchClients()
  showModal.value = true
}

function applyFilters() {
  fetchHabilitations()
}

function resetFilters() {
  search.value = ''
  clientFilter.value = ''
  categoryFilter.value = ''
  mandatoryFilter.value = ''
  activeFilter.value = ''
  fetchHabilitations()
}

function getValidityDisplay(hab) {
  if (!hab.validity_months) return 'Permanente'
  return `${hab.validity_months} mois`
}

onMounted(() => {
  fetchHabilitations()
  fetchClients()
  fetchCategories()
})
</script>

<template>
  <div class="habilitations-page">
    <header class="page-header">
      <div>
        <h1>📜 Habilitations</h1>
        <p class="subtitle">Gestion des certifications par client</p>
      </div>
      <button class="btn btn-primary" @click="openModal()">
        + Nouvelle habilitation
      </button>
    </header>

    <!-- Stats Cards -->
    <div class="stats-cards">
      <div class="stat-card" @click="activeFilter = ''; mandatoryFilter = ''; applyFilters()">
        <div class="stat-icon blue">📜</div>
        <div class="stat-content">
          <div class="stat-value">{{ stats.total }}</div>
          <div class="stat-label">Total</div>
        </div>
      </div>
      <div class="stat-card success" @click="activeFilter = '1'; applyFilters()">
        <div class="stat-icon green">✅</div>
        <div class="stat-content">
          <div class="stat-value">{{ stats.active }}</div>
          <div class="stat-label">Actives</div>
        </div>
      </div>
      <div class="stat-card danger" @click="mandatoryFilter = '1'; applyFilters()">
        <div class="stat-icon red">⚠️</div>
        <div class="stat-content">
          <div class="stat-value">{{ stats.mandatory }}</div>
          <div class="stat-label">Obligatoires</div>
        </div>
      </div>
      <div class="stat-card info" @click="clientFilter = ''; applyFilters()">
        <div class="stat-icon purple">🌐</div>
        <div class="stat-content">
          <div class="stat-value">{{ stats.general }}</div>
          <div class="stat-label">Générales</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon cyan">👥</div>
        <div class="stat-content">
          <div class="stat-value">{{ stats.totalDrivers }}</div>
          <div class="stat-label">Attributions</div>
        </div>
      </div>
      <div class="stat-card warning" v-if="stats.expiringSoon > 0">
        <div class="stat-icon orange">⏰</div>
        <div class="stat-content">
          <div class="stat-value">{{ stats.expiringSoon }}</div>
          <div class="stat-label">Expirent bientôt</div>
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
          placeholder="Rechercher par nom, code..."
          @input="applyFilters"
        />
      </div>
      <select v-model="clientFilter" @change="applyFilters">
        <option value="">Tous les clients</option>
        <option value="null">🌐 Générales uniquement</option>
        <option v-for="client in clients" :key="client.id" :value="client.id">
          🏢 {{ client.name }}
        </option>
      </select>
      <select v-model="categoryFilter" @change="applyFilters">
        <option value="">Toutes catégories</option>
        <option v-for="cat in [...new Set([...commonCategories, ...categories])]" :key="cat" :value="cat">
          {{ getCategoryIcon(cat) }} {{ cat }}
        </option>
      </select>
      <select v-model="mandatoryFilter" @change="applyFilters">
        <option value="">Toutes</option>
        <option value="1">⚠️ Obligatoires</option>
        <option value="0">✅ Optionnelles</option>
      </select>
      <select v-model="activeFilter" @change="applyFilters">
        <option value="">Actives & Inactives</option>
        <option value="1">🟢 Actives</option>
        <option value="0">🔴 Inactives</option>
      </select>
      <button class="btn btn-secondary btn-sm" @click="resetFilters" v-if="search || clientFilter || categoryFilter || mandatoryFilter !== '' || activeFilter !== ''">
        ✕ Reset
      </button>
      <div class="view-toggle">
        <button :class="{ active: activeView === 'grid' }" @click="activeView = 'grid'" title="Vue grille">▦</button>
        <button :class="{ active: activeView === 'table' }" @click="activeView = 'table'" title="Vue tableau">☰</button>
        <button :class="{ active: activeView === 'category' }" @click="activeView = 'category'" title="Par catégorie">📂</button>
        <button :class="{ active: activeView === 'client' }" @click="activeView = 'client'" title="Par client">🏢</button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <p>Chargement...</p>
    </div>

    <!-- Grid View -->
    <div v-else-if="activeView === 'grid'" class="habilitations-grid">
      <div
        v-for="hab in habilitations"
        :key="hab.id"
        class="hab-card"
        :class="{
          inactive: !hab.is_active,
          mandatory: hab.is_mandatory,
          [getCategoryColor(hab.category)]: true
        }"
        @click="openDetailModal(hab)"
      >
        <div class="hab-header">
          <div class="hab-icon" :class="getCategoryColor(hab.category)">
            {{ getCategoryIcon(hab.category) }}
          </div>
          <div class="hab-info">
            <div class="hab-code">{{ hab.code }}</div>
            <h3 class="hab-name">{{ hab.name }}</h3>
          </div>
        </div>

        <div class="hab-badges">
          <span v-if="hab.is_mandatory" class="badge mandatory">⚠️ Obligatoire</span>
          <span class="badge" :class="hab.is_active ? 'active' : 'inactive'">
            {{ hab.is_active ? '🟢 Active' : '🔴 Inactive' }}
          </span>
        </div>

        <p class="hab-description" v-if="hab.description">
          {{ hab.description.length > 100 ? hab.description.substring(0, 100) + '...' : hab.description }}
        </p>

        <div class="hab-meta">
          <div class="meta-item" v-if="hab.client">
            <span class="meta-icon">🏢</span>
            <span class="client-name">{{ hab.client.name }}</span>
          </div>
          <div class="meta-item general" v-else>
            <span class="meta-icon">🌐</span>
            <span>Tous clients</span>
          </div>

          <div class="meta-item" v-if="hab.category">
            <span class="meta-icon">🏷️</span>
            <span>{{ hab.category }}</span>
          </div>

          <div class="meta-item">
            <span class="meta-icon">📅</span>
            <span>{{ getValidityDisplay(hab) }}</span>
          </div>
        </div>

        <!-- Stats -->
        <div class="hab-stats" v-if="hab.drivers_count > 0">
          <div class="hab-stat">
            <span class="hab-stat-value">{{ hab.drivers_count }}</span>
            <span class="hab-stat-label">Chauffeurs</span>
          </div>
          <div class="hab-stat valid" v-if="hab.valid_count">
            <span class="hab-stat-value">{{ hab.valid_count }}</span>
            <span class="hab-stat-label">Valides</span>
          </div>
          <div class="hab-stat warning" v-if="hab.expiring_soon_count">
            <span class="hab-stat-value">{{ hab.expiring_soon_count }}</span>
            <span class="hab-stat-label">Expirent</span>
          </div>
          <div class="hab-stat danger" v-if="hab.expired_count">
            <span class="hab-stat-value">{{ hab.expired_count }}</span>
            <span class="hab-stat-label">Expirées</span>
          </div>
        </div>
        <div class="no-drivers" v-else>
          <span>Aucun chauffeur attribué</span>
        </div>

        <div class="hab-actions" @click.stop>
          <button class="btn-action" @click="openDetailModal(hab)" title="Détails">
            👁️
          </button>
          <button class="btn-action" @click="toggleMandatory(hab)" :title="hab.is_mandatory ? 'Rendre optionnelle' : 'Rendre obligatoire'">
            {{ hab.is_mandatory ? '⭐' : '☆' }}
          </button>
          <button class="btn-action" @click="openModal(hab)" title="Modifier">
            ✏️
          </button>
          <button class="btn-action" @click="toggleActive(hab)" :title="hab.is_active ? 'Désactiver' : 'Activer'">
            {{ hab.is_active ? '🔒' : '🔓' }}
          </button>
          <button class="btn-action danger" @click="deleteHabilitation(hab)" title="Supprimer">
            🗑️
          </button>
        </div>
      </div>

      <div v-if="habilitations.length === 0" class="empty-state full-width">
        <span class="empty-icon">📜</span>
        <h3>Aucune habilitation trouvée</h3>
        <p>Créez des habilitations ou modifiez vos filtres</p>
      </div>
    </div>

    <!-- Table View -->
    <div v-else-if="activeView === 'table'" class="table-container">
      <table class="habilitations-table" v-if="habilitations.length">
        <thead>
          <tr>
            <th>Code</th>
            <th>Habilitation</th>
            <th>Client</th>
            <th>Catégorie</th>
            <th>Validité</th>
            <th>Statut</th>
            <th>Chauffeurs</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="hab in habilitations" :key="hab.id" :class="{ inactive: !hab.is_active }">
            <td class="code-cell">
              <strong>{{ hab.code }}</strong>
            </td>
            <td class="name-cell">
              <div class="hab-cell-info">
                <span class="hab-cell-icon" :class="getCategoryColor(hab.category)">
                  {{ getCategoryIcon(hab.category) }}
                </span>
                <div>
                  <div class="hab-cell-name">{{ hab.name }}</div>
                  <div class="hab-cell-desc" v-if="hab.description">
                    {{ hab.description.substring(0, 40) }}{{ hab.description.length > 40 ? '...' : '' }}
                  </div>
                </div>
              </div>
            </td>
            <td>
              <span class="client-badge" v-if="hab.client">
                🏢 {{ hab.client.name }}
              </span>
              <span class="general-badge" v-else>
                🌐 Tous clients
              </span>
            </td>
            <td>
              <span class="category-badge" :class="getCategoryColor(hab.category)" v-if="hab.category">
                {{ hab.category }}
              </span>
              <span v-else class="text-muted">-</span>
            </td>
            <td>
              <span class="validity-badge">
                {{ getValidityDisplay(hab) }}
              </span>
            </td>
            <td>
              <div class="status-badges">
                <span class="status-tag" :class="hab.is_active ? 'active' : 'inactive'">
                  {{ hab.is_active ? '🟢' : '🔴' }}
                </span>
                <span class="mandatory-tag" v-if="hab.is_mandatory">⚠️</span>
              </div>
            </td>
            <td>
              <div class="drivers-stats">
                <span class="driver-count">{{ hab.drivers_count || 0 }}</span>
                <div class="mini-stats" v-if="hab.drivers_count > 0">
                  <span class="mini-stat valid" v-if="hab.valid_count" title="Valides">{{ hab.valid_count }}</span>
                  <span class="mini-stat warning" v-if="hab.expiring_soon_count" title="Expirent">{{ hab.expiring_soon_count }}</span>
                  <span class="mini-stat danger" v-if="hab.expired_count" title="Expirées">{{ hab.expired_count }}</span>
                </div>
              </div>
            </td>
            <td class="actions-cell">
              <div class="action-buttons">
                <button class="btn-icon primary" @click="openDetailModal(hab)" title="Détails">👁️</button>
                <button class="btn-icon warning" @click="openModal(hab)" title="Modifier">✏️</button>
                <button class="btn-icon" @click="duplicateHabilitation(hab)" title="Dupliquer">📋</button>
                <button class="btn-icon danger" @click="deleteHabilitation(hab)" title="Supprimer">🗑️</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-else class="empty-state">
        <span class="empty-icon">📜</span>
        <h3>Aucune habilitation trouvée</h3>
      </div>
    </div>

    <!-- Category View -->
    <div v-else-if="activeView === 'category'" class="grouped-view">
      <div v-for="(items, category) in habilitationsByCategory" :key="category" class="group-section">
        <div class="group-header">
          <span class="group-icon" :class="getCategoryColor(category)">{{ getCategoryIcon(category) }}</span>
          <h3>{{ category }}</h3>
          <span class="group-count">{{ items.length }}</span>
        </div>
        <div class="group-items">
          <div
            v-for="hab in items"
            :key="hab.id"
            class="group-item"
            :class="{ inactive: !hab.is_active, mandatory: hab.is_mandatory }"
            @click="openDetailModal(hab)"
          >
            <div class="item-main">
              <span class="item-code">{{ hab.code }}</span>
              <span class="item-name">{{ hab.name }}</span>
              <span class="mandatory-dot" v-if="hab.is_mandatory">⚠️</span>
              <span class="inactive-dot" v-if="!hab.is_active">🔴</span>
            </div>
            <div class="item-meta">
              <span class="item-client" v-if="hab.client">🏢 {{ hab.client.name }}</span>
              <span class="item-client general" v-else>🌐 Tous</span>
              <span class="item-validity">📅 {{ getValidityDisplay(hab) }}</span>
              <span class="item-drivers">👥 {{ hab.drivers_count || 0 }}</span>
            </div>
            <div class="item-actions" @click.stop>
              <button class="btn-mini" @click="openModal(hab)">✏️</button>
              <button class="btn-mini" @click="deleteHabilitation(hab)">🗑️</button>
            </div>
          </div>
        </div>
      </div>

      <div v-if="Object.keys(habilitationsByCategory).length === 0" class="empty-state">
        <span class="empty-icon">📜</span>
        <h3>Aucune habilitation trouvée</h3>
      </div>
    </div>

    <!-- Client View -->
    <div v-else-if="activeView === 'client'" class="grouped-view">
      <div v-for="(items, clientName) in habilitationsByClient" :key="clientName" class="group-section">
        <div class="group-header" :class="{ general: clientName === 'Générales' }">
          <span class="group-icon" :class="clientName === 'Générales' ? 'global' : 'client'">
            {{ clientName === 'Générales' ? '🌐' : '🏢' }}
          </span>
          <h3>{{ clientName }}</h3>
          <span class="group-count">{{ items.length }}</span>
        </div>
        <div class="group-items">
          <div
            v-for="hab in items"
            :key="hab.id"
            class="group-item"
            :class="{ inactive: !hab.is_active, mandatory: hab.is_mandatory }"
            @click="openDetailModal(hab)"
          >
            <div class="item-main">
              <span class="item-icon" :class="getCategoryColor(hab.category)">{{ getCategoryIcon(hab.category) }}</span>
              <span class="item-code">{{ hab.code }}</span>
              <span class="item-name">{{ hab.name }}</span>
              <span class="mandatory-dot" v-if="hab.is_mandatory">⚠️</span>
            </div>
            <div class="item-meta">
              <span class="item-category" v-if="hab.category">🏷️ {{ hab.category }}</span>
              <span class="item-validity">📅 {{ getValidityDisplay(hab) }}</span>
              <span class="item-drivers">👥 {{ hab.drivers_count || 0 }}</span>
            </div>
            <div class="item-actions" @click.stop>
              <button class="btn-mini" @click="openModal(hab)">✏️</button>
              <button class="btn-mini" @click="deleteHabilitation(hab)">🗑️</button>
            </div>
          </div>
        </div>
      </div>

      <div v-if="Object.keys(habilitationsByClient).length === 0" class="empty-state">
        <span class="empty-icon">📜</span>
        <h3>Aucune habilitation trouvée</h3>
      </div>
    </div>

    <!-- Pagination -->
    <div class="pagination" v-if="pagination.last_page > 1">
      <button
        v-for="page in pagination.last_page"
        :key="page"
        :class="{ active: page === pagination.current_page }"
        @click="fetchHabilitations(page)"
      >
        {{ page }}
      </button>
    </div>

    <!-- Modal Création/Édition -->
    <div class="modal-overlay" v-if="showModal" @click.self="closeModal">
      <div class="modal modal-lg">
        <div class="modal-header">
          <h2>{{ editingHab ? '✏️ Modifier' : '➕ Nouvelle' }} habilitation</h2>
          <button class="close-btn" @click="closeModal">×</button>
        </div>

        <form @submit.prevent="saveHabilitation" class="modal-body">
          <div class="form-section">
            <h3>Identification</h3>
            <div class="form-group">
              <label>Nom de l'habilitation *</label>
              <input type="text" v-model="form.name" required placeholder="Ex: Formation ADR" />
            </div>

            <div class="form-group">
              <label>Description</label>
              <textarea v-model="form.description" rows="2" placeholder="Description détaillée de l'habilitation..."></textarea>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label>Client</label>
                <select v-model="form.client_id">
                  <option value="">🌐 Générale (tous clients)</option>
                  <option v-for="client in clients" :key="client.id" :value="client.id">
                    🏢 {{ client.name }}
                  </option>
                </select>
                <small class="form-hint">Laisser vide pour une habilitation applicable à tous les clients</small>
              </div>
              <div class="form-group">
                <label>Catégorie</label>
                <select v-model="form.category">
                  <option value="">Sélectionner</option>
                  <option v-for="cat in [...new Set([...commonCategories, ...categories])]" :key="cat" :value="cat">
                    {{ getCategoryIcon(cat) }} {{ cat }}
                  </option>
                </select>
              </div>
            </div>
          </div>

          <div class="form-section">
            <h3>Validité & Renouvellement</h3>
            <div class="form-row">
              <div class="form-group">
                <label>Durée de validité (mois)</label>
                <input
                  type="number"
                  v-model="form.validity_months"
                  min="1"
                  placeholder="Ex: 12"
                />
                <small class="form-hint">Laisser vide pour une habilitation permanente</small>
              </div>
              <div class="form-group">
                <label>Alerte renouvellement (jours avant)</label>
                <input
                  type="number"
                  v-model="form.renewal_notice_days"
                  min="1"
                  placeholder="30"
                />
              </div>
            </div>
          </div>

          <div class="form-section">
            <h3>Options</h3>
            <div class="form-checkboxes">
              <label class="checkbox-item" :class="{ checked: form.is_mandatory }">
                <input type="checkbox" v-model="form.is_mandatory" />
                <span class="checkbox-content">
                  <span class="checkbox-icon">⚠️</span>
                  <span class="checkbox-text">
                    <strong>Obligatoire</strong>
                    <small>Le chauffeur doit avoir cette habilitation pour intervenir</small>
                  </span>
                </span>
              </label>

              <label class="checkbox-item" :class="{ checked: form.is_active }">
                <input type="checkbox" v-model="form.is_active" />
                <span class="checkbox-content">
                  <span class="checkbox-icon">🟢</span>
                  <span class="checkbox-text">
                    <strong>Active</strong>
                    <small>L'habilitation peut être attribuée aux chauffeurs</small>
                  </span>
                </span>
              </label>
            </div>
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

    <!-- Modal Détails -->
    <div class="modal-overlay" v-if="showDetailModal" @click.self="closeDetailModal">
      <div class="modal modal-lg">
        <div class="modal-header">
          <h2>📋 Détails de l'habilitation</h2>
          <button class="close-btn" @click="closeDetailModal">×</button>
        </div>

        <div class="modal-body" v-if="selectedHab">
          <div class="detail-header">
            <div class="detail-icon" :class="getCategoryColor(selectedHab.category)">
              {{ getCategoryIcon(selectedHab.category) }}
            </div>
            <div class="detail-title">
              <div class="detail-code">{{ selectedHab.code }}</div>
              <h2>{{ selectedHab.name }}</h2>
              <div class="detail-badges">
                <span class="badge" :class="selectedHab.is_active ? 'active' : 'inactive'">
                  {{ selectedHab.is_active ? '🟢 Active' : '🔴 Inactive' }}
                </span>
                <span class="badge mandatory" v-if="selectedHab.is_mandatory">
                  ⚠️ Obligatoire
                </span>
                <span class="badge optional" v-else>
                  ✅ Optionnelle
                </span>
              </div>
            </div>
          </div>

          <div class="detail-description" v-if="selectedHab.description">
            <p>{{ selectedHab.description }}</p>
          </div>

          <!-- Client Info -->
          <div class="client-info-card">
            <div class="client-icon">{{ selectedHab.client ? '🏢' : '🌐' }}</div>
            <div class="client-details">
              <div class="client-label">{{ selectedHab.client ? 'Client spécifique' : 'Habilitation générale' }}</div>
              <div class="client-name">{{ selectedHab.client?.name || 'Applicable à tous les clients' }}</div>
            </div>
          </div>

          <!-- Stats -->
          <div class="detail-stats">
            <div class="detail-stat">
              <div class="detail-stat-value">{{ selectedHab.drivers_count || 0 }}</div>
              <div class="detail-stat-label">Chauffeurs</div>
            </div>
            <div class="detail-stat valid">
              <div class="detail-stat-value">{{ selectedHab.valid_count || 0 }}</div>
              <div class="detail-stat-label">Valides</div>
            </div>
            <div class="detail-stat warning">
              <div class="detail-stat-value">{{ selectedHab.expiring_soon_count || 0 }}</div>
              <div class="detail-stat-label">Expirent bientôt</div>
            </div>
            <div class="detail-stat danger">
              <div class="detail-stat-value">{{ selectedHab.expired_count || 0 }}</div>
              <div class="detail-stat-label">Expirées</div>
            </div>
          </div>

          <div class="detail-grid">
            <div class="detail-section">
              <h4>Informations</h4>
              <div class="detail-row">
                <span class="detail-label">Catégorie</span>
                <span class="detail-value">
                  <span v-if="selectedHab.category">{{ getCategoryIcon(selectedHab.category) }} {{ selectedHab.category }}</span>
                  <span v-else class="text-muted">-</span>
                </span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Validité</span>
                <span class="detail-value">{{ getValidityDisplay(selectedHab) }}</span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Alerte renouvellement</span>
                <span class="detail-value">{{ selectedHab.renewal_notice_days || 30 }} jours avant</span>
              </div>
            </div>

            <div class="detail-section">
              <h4>Statut</h4>
              <div class="detail-row">
                <span class="detail-label">État</span>
                <span class="detail-value">{{ selectedHab.is_active ? '🟢 Active' : '🔴 Inactive' }}</span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Obligatoire</span>
                <span class="detail-value">{{ selectedHab.is_mandatory ? '⚠️ Oui' : '✅ Non' }}</span>
              </div>
            </div>

            <!-- Liste des chauffeurs -->
            <div class="detail-section full-width" v-if="selectedHab.drivers?.length">
              <div class="section-header">
                <h4>👥 Chauffeurs avec cette habilitation</h4>
              </div>
              <div class="drivers-list">
                <div
                  v-for="driverHab in selectedHab.drivers"
                  :key="driverHab.id"
                  class="driver-hab-item"
                  :class="{
                    expired: driverHab.is_expired,
                    warning: driverHab.is_expiring_soon
                  }"
                >
                  <div class="driver-hab-avatar">
                    {{ driverHab.driver?.first_name?.charAt(0) }}{{ driverHab.driver?.last_name?.charAt(0) }}
                  </div>
                  <div class="driver-hab-info">
                    <div class="driver-hab-name">
                      {{ driverHab.driver?.first_name }} {{ driverHab.driver?.last_name }}
                    </div>
                    <div class="driver-hab-dates">
                      Obtenu: {{ new Date(driverHab.obtained_date).toLocaleDateString('fr-FR') }}
                      <span v-if="driverHab.expiry_date">
                        | Expire: {{ new Date(driverHab.expiry_date).toLocaleDateString('fr-FR') }}
                      </span>
                    </div>
                  </div>
                  <div class="driver-hab-status">
                    <span v-if="driverHab.is_expired" class="status-tag danger">Expirée</span>
                    <span v-else-if="driverHab.is_expiring_soon" class="status-tag warning">Expire bientôt</span>
                    <span v-else class="status-tag success">Valide</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="detail-actions">
            <button class="btn btn-warning" @click="openModal(selectedHab); closeDetailModal()">
              ✏️ Modifier
            </button>
            <button class="btn btn-secondary" @click="toggleMandatory(selectedHab); closeDetailModal()">
              {{ selectedHab.is_mandatory ? '☆ Rendre optionnelle' : '⭐ Rendre obligatoire' }}
            </button>
            <button class="btn btn-secondary" @click="toggleActive(selectedHab); closeDetailModal()">
              {{ selectedHab.is_active ? '🔒 Désactiver' : '🔓 Activer' }}
            </button>
            <button class="btn btn-primary" @click="duplicateHabilitation(selectedHab); closeDetailModal()">
              📋 Dupliquer
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.habilitations-page {
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
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
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
.stat-card.danger { border-left-color: #e74c3c; }
.stat-card.info { border-left-color: #9b59b6; }
.stat-card.warning { border-left-color: #f39c12; }

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
.stat-icon.red { background: #f8d7da; }
.stat-icon.purple { background: #e8daef; }
.stat-icon.orange { background: #fff3cd; }
.stat-icon.cyan { background: #d1ecf1; }

.stat-value {
  font-size: 26px;
  font-weight: 700;
  color: #2c3e50;
}

.stat-label {
  font-size: 12px;
  color: #7f8c8d;
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
  min-width: 200px;
  max-width: 300px;
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
  min-width: 150px;
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
  font-size: 14px;
}

.view-toggle button.active {
  background: #3498db;
  color: white;
}

/* Grid View */
.habilitations-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
  gap: 20px;
}

.hab-card {
  background: white;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
  border-left: 4px solid #3498db;
  transition: all 0.2s;
  cursor: pointer;
}

.hab-card:hover {
  box-shadow: 0 8px 25px rgba(0,0,0,0.12);
  transform: translateY(-3px);
}

.hab-card.mandatory { border-left-color: #e74c3c; }
.hab-card.inactive { opacity: 0.6; border-left-color: #95a5a6; }
.hab-card.orange { border-top: 3px solid #f39c12; }
.hab-card.red { border-top: 3px solid #e74c3c; }
.hab-card.blue { border-top: 3px solid #3498db; }
.hab-card.green { border-top: 3px solid #27ae60; }
.hab-card.purple { border-top: 3px solid #9b59b6; }
.hab-card.yellow { border-top: 3px solid #f1c40f; }
.hab-card.cyan { border-top: 3px solid #1abc9c; }
.hab-card.teal { border-top: 3px solid #16a085; }
.hab-card.gray { border-top: 3px solid #95a5a6; }

.hab-header {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  margin-bottom: 12px;
}

.hab-icon {
  width: 45px;
  height: 45px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 22px;
}

.hab-icon.blue { background: #e8f4fd; }
.hab-icon.orange { background: #fff3cd; }
.hab-icon.red { background: #f8d7da; }
.hab-icon.green { background: #d4edda; }
.hab-icon.purple { background: #e8daef; }
.hab-icon.yellow { background: #fef3cd; }
.hab-icon.cyan { background: #d1ecf1; }
.hab-icon.teal { background: #d1f2eb; }
.hab-icon.gray { background: #e9ecef; }

.hab-info {
  flex: 1;
}

.hab-code {
  font-family: monospace;
  font-size: 11px;
  color: #7f8c8d;
  background: #f0f0f0;
  padding: 2px 8px;
  border-radius: 4px;
  display: inline-block;
  margin-bottom: 5px;
}

.hab-name {
  font-size: 16px;
  color: #2c3e50;
  margin: 0;
}

.hab-badges {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: 12px;
}

.badge {
  padding: 4px 10px;
  border-radius: 15px;
  font-size: 10px;
  font-weight: 600;
}

.badge.mandatory { background: #f8d7da; color: #721c24; }
.badge.active { background: #d4edda; color: #155724; }
.badge.inactive { background: #e9ecef; color: #6c757d; }
.badge.optional { background: #d4edda; color: #155724; }

.hab-description {
  font-size: 13px;
  color: #7f8c8d;
  margin: 0 0 12px;
  line-height: 1.4;
}

.hab-meta {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-bottom: 12px;
}

.meta-item {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12px;
  color: #555;
}

.meta-item.general {
  color: #3498db;
}

.client-name {
  font-weight: 500;
  color: #2c3e50;
}

.hab-stats {
  display: flex;
  gap: 10px;
  padding: 10px;
  background: #f8f9fa;
  border-radius: 8px;
  margin-bottom: 12px;
}

.hab-stat {
  text-align: center;
  flex: 1;
}

.hab-stat-value {
  font-size: 18px;
  font-weight: 700;
  color: #2c3e50;
}

.hab-stat-label {
  font-size: 9px;
  color: #7f8c8d;
  text-transform: uppercase;
}

.hab-stat.valid .hab-stat-value { color: #27ae60; }
.hab-stat.warning .hab-stat-value { color: #f39c12; }
.hab-stat.danger .hab-stat-value { color: #e74c3c; }

.no-drivers {
  padding: 10px;
  background: #f8f9fa;
  border-radius: 8px;
  text-align: center;
  font-size: 12px;
  color: #95a5a6;
  margin-bottom: 12px;
}

.hab-actions {
  display: flex;
  gap: 5px;
  justify-content: flex-end;
  border-top: 1px solid #eee;
  padding-top: 12px;
}

.btn-action {
  width: 34px;
  height: 34px;
  border: none;
  border-radius: 8px;
  background: #f8f9fa;
  cursor: pointer;
  font-size: 14px;
  transition: all 0.2s;
}

.btn-action:hover {
  background: #e9ecef;
  transform: scale(1.05);
}

.btn-action.danger:hover {
  background: #fee;
}

/* Table View */
.table-container {
  background: white;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.habilitations-table {
  width: 100%;
  border-collapse: collapse;
}

.habilitations-table th {
  text-align: left;
  padding: 15px;
  background: #f8f9fa;
  font-size: 12px;
  text-transform: uppercase;
  color: #7f8c8d;
  font-weight: 600;
}

.habilitations-table td {
  padding: 15px;
  border-top: 1px solid #eee;
  vertical-align: middle;
}

.habilitations-table tr:hover {
  background: #f8f9fa;
}

.habilitations-table tr.inactive {
  opacity: 0.6;
}

.code-cell strong {
  font-family: monospace;
  color: #3498db;
}

.hab-cell-info {
  display: flex;
  align-items: center;
  gap: 12px;
}

.hab-cell-icon {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
}

.hab-cell-name {
  font-weight: 500;
  color: #2c3e50;
}

.hab-cell-desc {
  font-size: 11px;
  color: #7f8c8d;
}

.client-badge {
  background: #e8f4fd;
  color: #2980b9;
  padding: 4px 10px;
  border-radius: 15px;
  font-size: 11px;
}

.general-badge {
  background: #d4edda;
  color: #155724;
  padding: 4px 10px;
  border-radius: 15px;
  font-size: 11px;
}

.category-badge {
  padding: 4px 10px;
  border-radius: 15px;
  font-size: 11px;
}

.category-badge.blue { background: #e8f4fd; color: #2980b9; }
.category-badge.orange { background: #fff3cd; color: #d68910; }
.category-badge.red { background: #f8d7da; color: #c0392b; }
.category-badge.green { background: #d4edda; color: #1e8449; }
.category-badge.purple { background: #e8daef; color: #7d3c98; }
.category-badge.yellow { background: #fef3cd; color: #b7950b; }
.category-badge.cyan { background: #d1ecf1; color: #0c5460; }
.category-badge.teal { background: #d1f2eb; color: #148f77; }
.category-badge.gray { background: #e9ecef; color: #6c757d; }

.validity-badge {
  background: #e8f4fd;
  color: #2980b9;
  padding: 4px 10px;
  border-radius: 15px;
  font-size: 11px;
}

.status-badges {
  display: flex;
  align-items: center;
  gap: 5px;
}

.status-tag {
  font-size: 14px;
}

.mandatory-tag {
  font-size: 12px;
}

.drivers-stats {
  display: flex;
  align-items: center;
  gap: 8px;
}

.driver-count {
  font-weight: 600;
  color: #2c3e50;
}

.mini-stats {
  display: flex;
  gap: 4px;
}

.mini-stat {
  width: 20px;
  height: 20px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 10px;
  font-weight: 600;
}

.mini-stat.valid { background: #d4edda; color: #155724; }
.mini-stat.warning { background: #fff3cd; color: #856404; }
.mini-stat.danger { background: #f8d7da; color: #721c24; }

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
.btn-icon.warning { background: #fff3cd; }
.btn-icon.danger { background: #f8d7da; }

/* Grouped View */
.grouped-view {
  display: flex;
  flex-direction: column;
  gap: 25px;
}

.group-section {
  background: white;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.group-header {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 15px 20px;
  background: #f8f9fa;
  border-bottom: 1px solid #eee;
}

.group-header.general {
  background: linear-gradient(135deg, #d4edda, #c3e6cb);
}

.group-icon {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
}

.group-icon.global { background: #d4edda; }
.group-icon.client { background: #e8f4fd; }
.group-icon.blue { background: #e8f4fd; }
.group-icon.orange { background: #fff3cd; }
.group-icon.red { background: #f8d7da; }
.group-icon.green { background: #d4edda; }
.group-icon.purple { background: #e8daef; }
.group-icon.yellow { background: #fef3cd; }
.group-icon.cyan { background: #d1ecf1; }
.group-icon.teal { background: #d1f2eb; }
.group-icon.gray { background: #e9ecef; }

.group-header h3 {
  margin: 0;
  flex: 1;
  font-size: 16px;
  color: #2c3e50;
}

.group-count {
  background: #3498db;
  color: white;
  padding: 3px 10px;
  border-radius: 15px;
  font-size: 12px;
  font-weight: 600;
}

.group-items {
  padding: 10px;
}

.group-item {
  display: flex;
  align-items: center;
  gap: 15px;
  padding: 12px 15px;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s;
}

.group-item:hover {
  background: #f8f9fa;
}

.group-item.mandatory {
  border-left: 3px solid #e74c3c;
}

.group-item.inactive {
  opacity: 0.5;
}

.item-main {
  flex: 1;
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
}

.item-icon {
  width: 30px;
  height: 30px;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  flex-shrink: 0;
}

.item-code {
  font-family: monospace;
  font-size: 11px;
  color: #7f8c8d;
  background: #f0f0f0;
  padding: 2px 8px;
  border-radius: 4px;
  flex-shrink: 0;
}

.item-name {
  font-weight: 500;
  color: #2c3e50;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.mandatory-dot,
.inactive-dot {
  font-size: 12px;
  flex-shrink: 0;
}

.item-meta {
  display: flex;
  gap: 15px;
  font-size: 12px;
  color: #7f8c8d;
  flex-shrink: 0;
}

.item-client.general {
  color: #27ae60;
}

.item-actions {
  display: flex;
  gap: 5px;
  opacity: 0;
  transition: opacity 0.2s;
}

.group-item:hover .item-actions {
  opacity: 1;
}

.btn-mini {
  width: 28px;
  height: 28px;
  border: none;
  border-radius: 6px;
  background: #f0f0f0;
  cursor: pointer;
  font-size: 12px;
}

.btn-mini:hover {
  background: #e0e0e0;
}

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

.text-muted {
  color: #95a5a6;
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
  max-width: 550px;
  max-height: 90vh;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.modal-lg {
  max-width: 700px;
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

.form-hint {
  display: block;
  font-size: 11px;
  color: #95a5a6;
  margin-top: 4px;
}

.form-checkboxes {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.checkbox-item {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 15px;
  background: #f8f9fa;
  border-radius: 8px;
  cursor: pointer;
  border: 2px solid transparent;
  transition: all 0.2s;
}

.checkbox-item.checked {
  background: #fff3cd;
  border-color: #f39c12;
}

.checkbox-item input[type="checkbox"] {
  width: 18px;
  height: 18px;
  margin-top: 2px;
  flex-shrink: 0;
}

.checkbox-content {
  display: flex;
  align-items: flex-start;
  gap: 10px;
}

.checkbox-icon {
  font-size: 20px;
}

.checkbox-text {
  display: flex;
  flex-direction: column;
}

.checkbox-text strong {
  font-size: 14px;
  color: #2c3e50;
}

.checkbox-text small {
  font-size: 12px;
  color: #7f8c8d;
  margin-top: 2px;
}

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

/* Detail Modal */
.detail-header {
  display: flex;
  align-items: center;
  gap: 20px;
  padding: 20px;
  background: #f8f9fa;
  border-radius: 12px;
  margin-bottom: 20px;
}

.detail-icon {
  width: 70px;
  height: 70px;
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 36px;
}

.detail-title {
  flex: 1;
}

.detail-code {
  font-family: monospace;
  font-size: 12px;
  color: #7f8c8d;
  margin-bottom: 5px;
}

.detail-title h2 {
  margin: 0 0 10px;
  font-size: 22px;
  color: #2c3e50;
}

.detail-badges {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.detail-description {
  padding: 15px;
  background: #f8f9fa;
  border-radius: 8px;
  margin-bottom: 20px;
}

.detail-description p {
  margin: 0;
  color: #555;
  font-size: 14px;
  line-height: 1.5;
}

.client-info-card {
  display: flex;
  align-items: center;
  gap: 15px;
  padding: 15px;
  background: linear-gradient(135deg, #e8f4fd, #d1ecf1);
  border-radius: 10px;
  margin-bottom: 20px;
}

.client-icon {
  font-size: 30px;
}

.client-label {
  font-size: 11px;
  color: #7f8c8d;
  text-transform: uppercase;
}

.client-details .client-name {
  font-size: 16px;
  font-weight: 600;
  color: #2c3e50;
}

.detail-stats {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 15px;
  margin-bottom: 20px;
}

.detail-stat {
  text-align: center;
  padding: 15px;
  background: #f8f9fa;
  border-radius: 10px;
}

.detail-stat-value {
  font-size: 28px;
  font-weight: 700;
  color: #2c3e50;
}

.detail-stat-label {
  font-size: 11px;
  color: #7f8c8d;
  text-transform: uppercase;
}

.detail-stat.valid { background: #d4edda; }
.detail-stat.valid .detail-stat-value { color: #155724; }
.detail-stat.warning { background: #fff3cd; }
.detail-stat.warning .detail-stat-value { color: #856404; }
.detail-stat.danger { background: #f8d7da; }
.detail-stat.danger .detail-stat-value { color: #721c24; }

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

/* Drivers List */
.drivers-list {
  max-height: 300px;
  overflow-y: auto;
}

.driver-hab-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px;
  background: white;
  border-radius: 8px;
  margin-bottom: 8px;
}

.driver-hab-item.expired {
  background: #f8d7da;
}

.driver-hab-item.warning {
  background: #fff3cd;
}

.driver-hab-avatar {
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

.driver-hab-info {
  flex: 1;
}

.driver-hab-name {
  font-weight: 500;
  color: #2c3e50;
}

.driver-hab-dates {
  font-size: 11px;
  color: #7f8c8d;
}

.driver-hab-status {
  display: flex;
  align-items: center;
}

.status-tag.success { background: #d4edda; color: #155724; padding: 4px 10px; border-radius: 15px; font-size: 10px; }
.status-tag.warning { background: #fff3cd; color: #856404; padding: 4px 10px; border-radius: 15px; font-size: 10px; }
.status-tag.danger { background: #f8d7da; color: #721c24; padding: 4px 10px; border-radius: 15px; font-size: 10px; }

.detail-actions {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  margin-top: 25px;
  padding-top: 20px;
  border-top: 1px solid #eee;
}

@media (max-width: 768px) {
  .form-row,
  .detail-grid,
  .detail-stats {
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

  .detail-header {
    flex-direction: column;
    text-align: center;
  }
}
</style>
