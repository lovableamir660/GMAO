<script setup>
import { ref, onMounted, computed } from 'vue'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
const sites = ref([])
const loading = ref(true)
const error = ref('')
const showModal = ref(false)
const showDetailModal = ref(false)
const editingSite = ref(null)
const selectedSite = ref(null)
const search = ref('')
const activeFilter = ref('')
const cityFilter = ref('')
const typeFilter = ref('')
const activeView = ref('table')
const pagination = ref({})

const form = ref({
  name: '',
  address: '',
  city: '',
  postal_code: '',
  country: 'Algérie',
  latitude: '',
  longitude: '',
  contact_name: '',
  contact_phone: '',
  contact_email: '',
  site_type: 'warehouse',
  capacity: '',
  operating_hours: '',
  notes: '',
  is_active: true,
})

const saving = ref(false)
const formError = ref('')

const siteTypes = [
  { value: 'warehouse', label: 'Entrepôt', icon: '🏭', color: 'blue' },
  { value: 'depot', label: 'Dépôt', icon: '📦', color: 'orange' },
  { value: 'terminal', label: 'Terminal', icon: '🚛', color: 'purple' },
  { value: 'office', label: 'Bureau', icon: '🏢', color: 'green' },
  { value: 'client', label: 'Site client', icon: '🏪', color: 'cyan' },
  { value: 'supplier', label: 'Fournisseur', icon: '�شرکت', color: 'teal' },
  { value: 'other', label: 'Autre', icon: '📍', color: 'gray' },
]

// Stats calculées
const stats = computed(() => {
  const all = sites.value
  return {
    total: pagination.value.total || all.length,
    active: all.filter(s => s.is_active).length,
    inactive: all.filter(s => !s.is_active).length,
    byType: siteTypes.map(type => ({
      ...type,
      count: all.filter(s => s.site_type === type.value).length
    })).filter(t => t.count > 0),
    totalMissions: all.reduce((sum, s) => sum + (s.missions_count || 0), 0),
  }
})

// Liste des villes uniques
const cities = computed(() => {
  const cityList = sites.value.map(s => s.city).filter(Boolean)
  return [...new Set(cityList)].sort()
})

// Grouper par ville
const sitesByCity = computed(() => {
  const grouped = {}
  sites.value.forEach(s => {
    const city = s.city || 'Non définie'
    if (!grouped[city]) grouped[city] = []
    grouped[city].push(s)
  })
  return grouped
})

// Grouper par type
const sitesByType = computed(() => {
  const grouped = {}
  sites.value.forEach(s => {
    const type = s.site_type || 'other'
    if (!grouped[type]) grouped[type] = []
    grouped[type].push(s)
  })
  return grouped
})

async function fetchSites(page = 1) {
  loading.value = true
  error.value = ''
  try {
    const params = { page, per_page: 20 }
    if (search.value) params.search = search.value
    if (activeFilter.value !== '') params.is_active = activeFilter.value
    if (cityFilter.value) params.city = cityFilter.value
    if (typeFilter.value) params.site_type = typeFilter.value

    const response = await api.get('/sites', { params })
    sites.value = response.data.data
    pagination.value = {
      current_page: response.data.current_page,
      last_page: response.data.last_page,
      total: response.data.total,
    }
  } catch (err) {
    error.value = 'Erreur lors du chargement des sites'
    console.error(err)
  } finally {
    loading.value = false
  }
}

function openModal(site = null) {
  if (!authStore.hasPermission('site:create') && !site) return
  if (!authStore.hasPermission('site:edit') && site) return

  editingSite.value = site
  if (site) {
    form.value = {
      name: site.name,
      address: site.address || '',
      city: site.city || '',
      postal_code: site.postal_code || '',
      country: site.country || 'Algérie',
      latitude: site.latitude || '',
      longitude: site.longitude || '',
      contact_name: site.contact_name || '',
      contact_phone: site.contact_phone || '',
      contact_email: site.contact_email || '',
      site_type: site.site_type || 'warehouse',
      capacity: site.capacity || '',
      operating_hours: site.operating_hours || '',
      notes: site.notes || '',
      is_active: site.is_active,
    }
  } else {
    form.value = {
      name: '',
      address: '',
      city: '',
      postal_code: '',
      country: 'Algérie',
      latitude: '',
      longitude: '',
      contact_name: '',
      contact_phone: '',
      contact_email: '',
      site_type: 'warehouse',
      capacity: '',
      operating_hours: '',
      notes: '',
      is_active: true,
    }
  }
  formError.value = ''
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  editingSite.value = null
}

async function openDetailModal(site) {
  selectedSite.value = site
  showDetailModal.value = true

  // Charger les détails complets
  try {
    const response = await api.get(`/sites/${site.id}`)
    selectedSite.value = response.data
  } catch (err) {
    console.error('Erreur:', err)
  }
}

function closeDetailModal() {
  showDetailModal.value = false
  selectedSite.value = null
}

async function saveSite() {
  saving.value = true
  formError.value = ''

  try {
    if (editingSite.value) {
      await api.put(`/sites/${editingSite.value.id}`, form.value)
    } else {
      await api.post('/sites', form.value)
    }
    closeModal()
    fetchSites()
  } catch (err) {
    formError.value = err.response?.data?.message || 'Erreur lors de la sauvegarde'
  } finally {
    saving.value = false
  }
}

async function deleteSite(site) {
  if (!authStore.hasPermission('site:delete')) return

  if (site.missions_count > 0) {
    alert(`Impossible de supprimer : ce site a ${site.missions_count} mission(s) associée(s).`)
    return
  }

  if (!confirm(`Supprimer le site "${site.name}" ?`)) return

  try {
    await api.delete(`/sites/${site.id}`)
    fetchSites()
  } catch (err) {
    alert(err.response?.data?.message || 'Erreur lors de la suppression')
  }
}

async function toggleActive(site) {
  if (!authStore.hasPermission('site:edit')) return

  try {
    await api.put(`/sites/${site.id}`, {
      ...site,
      is_active: !site.is_active,
    })
    fetchSites()
  } catch (err) {
    alert('Erreur lors de la mise à jour')
  }
}

function applyFilters() {
  fetchSites()
}

function resetFilters() {
  search.value = ''
  activeFilter.value = ''
  cityFilter.value = ''
  typeFilter.value = ''
  fetchSites()
}

function getSiteType(type) {
  return siteTypes.find(t => t.value === type) || siteTypes[siteTypes.length - 1]
}

function getInitials(name) {
  return name
    .split(' ')
    .map(word => word.charAt(0))
    .join('')
    .substring(0, 2)
    .toUpperCase()
}

function copyToClipboard(text) {
  navigator.clipboard.writeText(text)
}

function openInMaps(site) {
  if (site.latitude && site.longitude) {
    window.open(`https://www.google.com/maps?q=${site.latitude},${site.longitude}`, '_blank')
  } else if (site.address && site.city) {
    window.open(`https://www.google.com/maps/search/${encodeURIComponent(site.address + ', ' + site.city)}`, '_blank')
  }
}

onMounted(() => {
  fetchSites()
})
</script>

<template>
  <div class="sites-page">
    <header class="page-header">
      <div>
        <h1>📍 Sites</h1>
        <p class="subtitle">Gestion des emplacements et infrastructures</p>
      </div>
      <button 
        class="btn btn-primary" 
        @click="openModal()"
        v-if="authStore.hasPermission('site:create')"
      >
        + Nouveau site
      </button>
    </header>

    <!-- Stats Cards -->
    <div class="stats-cards">
      <div class="stat-card" @click="activeFilter = ''; applyFilters()">
        <div class="stat-icon blue">📍</div>
        <div class="stat-content">
          <div class="stat-value">{{ stats.total }}</div>
          <div class="stat-label">Total sites</div>
        </div>
      </div>
      <div class="stat-card success" @click="activeFilter = '1'; applyFilters()">
        <div class="stat-icon green">✅</div>
        <div class="stat-content">
          <div class="stat-value">{{ stats.active }}</div>
          <div class="stat-label">Actifs</div>
        </div>
      </div>
      <div class="stat-card danger" @click="activeFilter = '0'; applyFilters()">
        <div class="stat-icon red">🔴</div>
        <div class="stat-content">
          <div class="stat-value">{{ stats.inactive }}</div>
          <div class="stat-label">Inactifs</div>
        </div>
      </div>
      <div 
        v-for="typeStats in stats.byType.slice(0, 3)" 
        :key="typeStats.value" 
        class="stat-card"
        @click="typeFilter = typeStats.value; applyFilters()"
      >
        <div class="stat-icon" :class="typeStats.color">{{ typeStats.icon }}</div>
        <div class="stat-content">
          <div class="stat-value">{{ typeStats.count }}</div>
          <div class="stat-label">{{ typeStats.label }}</div>
        </div>
      </div>
      <div class="stat-card info">
        <div class="stat-icon cyan">🚛</div>
        <div class="stat-content">
          <div class="stat-value">{{ stats.totalMissions }}</div>
          <div class="stat-label">Missions</div>
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
          placeholder="Rechercher par nom, code, ville..."
          @input="applyFilters"
        />
      </div>
      <select v-model="activeFilter" @change="applyFilters">
        <option value="">Tous les statuts</option>
        <option value="1">🟢 Actifs</option>
        <option value="0">🔴 Inactifs</option>
      </select>
      <select v-model="typeFilter" @change="applyFilters">
        <option value="">Tous les types</option>
        <option v-for="type in siteTypes" :key="type.value" :value="type.value">
          {{ type.icon }} {{ type.label }}
        </option>
      </select>
      <select v-model="cityFilter" @change="applyFilters" v-if="cities.length > 0">
        <option value="">Toutes les villes</option>
        <option v-for="city in cities" :key="city" :value="city">
          📍 {{ city }}
        </option>
      </select>
      <button 
        class="btn btn-secondary btn-sm" 
        @click="resetFilters" 
        v-if="search || activeFilter !== '' || cityFilter || typeFilter"
      >
        ✕ Reset
      </button>
      <div class="view-toggle">
        <button :class="{ active: activeView === 'grid' }" @click="activeView = 'grid'" title="Vue grille">▦</button>
        <button :class="{ active: activeView === 'table' }" @click="activeView = 'table'" title="Vue tableau">☰</button>
        <button :class="{ active: activeView === 'city' }" @click="activeView = 'city'" title="Par ville">📍</button>
        <button :class="{ active: activeView === 'type' }" @click="activeView = 'type'" title="Par type">🏷️</button>
      </div>
    </div>

    <!-- Alert Error -->
    <div class="alert alert-error" v-if="error">
      <span class="alert-icon">⚠️</span>
      {{ error }}
      <button class="alert-close" @click="error = ''">×</button>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <p>Chargement des sites...</p>
    </div>

    <!-- Grid View -->
    <div v-else-if="activeView === 'grid'" class="sites-grid">
      <div
        v-for="site in sites"
        :key="site.id"
        class="site-card"
        :class="{ inactive: !site.is_active }"
        @click="openDetailModal(site)"
      >
        <div class="card-header" :class="getSiteType(site.site_type).color">
          <div class="site-type-badge">
            <span class="type-icon">{{ getSiteType(site.site_type).icon }}</span>
            <span class="type-label">{{ getSiteType(site.site_type).label }}</span>
          </div>
          <div class="status-dot" :class="site.is_active ? 'active' : 'inactive'"></div>
        </div>

        <div class="card-body">
          <div class="site-code">{{ site.code }}</div>
          <h3 class="site-name">{{ site.name }}</h3>
          
          <div class="site-location" v-if="site.city || site.address">
            <span class="location-icon">📍</span>
            <div class="location-text">
              <span class="location-city" v-if="site.city">{{ site.city }}</span>
              <span class="location-address" v-if="site.address">{{ site.address }}</span>
            </div>
          </div>

          <div class="site-contact" v-if="site.contact_name || site.contact_phone">
            <div class="contact-item" v-if="site.contact_name">
              <span>👤</span> {{ site.contact_name }}
            </div>
            <div class="contact-item" v-if="site.contact_phone">
              <span>📱</span> {{ site.contact_phone }}
            </div>
          </div>

          <div class="site-stats">
            <div class="site-stat" v-if="site.missions_count !== undefined">
              <span class="stat-value">{{ site.missions_count || 0 }}</span>
              <span class="stat-label">Missions</span>
            </div>
            <div class="site-stat" v-if="site.capacity">
              <span class="stat-value">{{ site.capacity }}</span>
              <span class="stat-label">Capacité</span>
            </div>
          </div>
        </div>

        <div class="card-actions" @click.stop>
          <button class="btn-action" @click="openDetailModal(site)" title="Détails">👁️</button>
          <button 
            class="btn-action" 
            @click="openModal(site)" 
            title="Modifier"
            v-if="authStore.hasPermission('site:edit')"
          >✏️</button>
          <button 
            class="btn-action" 
            @click="openInMaps(site)" 
            title="Voir sur la carte"
            v-if="site.latitude || site.address"
          >🗺️</button>
          <button 
            class="btn-action" 
            @click="toggleActive(site)" 
            :title="site.is_active ? 'Désactiver' : 'Activer'"
            v-if="authStore.hasPermission('site:edit')"
          >
            {{ site.is_active ? '🔒' : '🔓' }}
          </button>
          <button 
            class="btn-action danger" 
            @click="deleteSite(site)" 
            title="Supprimer"
            v-if="authStore.hasPermission('site:delete')"
          >🗑️</button>
        </div>
      </div>

      <div v-if="sites.length === 0" class="empty-state full-width">
        <span class="empty-icon">📍</span>
        <h3>Aucun site trouvé</h3>
        <p>Ajoutez des sites ou modifiez vos filtres</p>
        <button 
          class="btn btn-primary" 
          @click="openModal()"
          v-if="authStore.hasPermission('site:create')"
        >
          + Ajouter un site
        </button>
      </div>
    </div>

    <!-- Table View -->
    <div v-else-if="activeView === 'table'" class="table-container">
      <table class="sites-table" v-if="sites.length">
        <thead>
          <tr>
            <th>Site</th>
            <th>Type</th>
            <th>Localisation</th>
            <th>Contact</th>
            <th>Missions</th>
            <th>Statut</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr 
            v-for="site in sites" 
            :key="site.id" 
            :class="{ inactive: !site.is_active }"
            @click="openDetailModal(site)"
          >
            <td class="site-cell">
              <div class="site-info">
                <div class="site-avatar" :class="getSiteType(site.site_type).color">
                  {{ getSiteType(site.site_type).icon }}
                </div>
                <div>
                  <div class="site-name">{{ site.name }}</div>
                  <div class="site-code">{{ site.code }}</div>
                </div>
              </div>
            </td>
            <td>
              <span class="type-badge" :class="getSiteType(site.site_type).color">
                {{ getSiteType(site.site_type).icon }} {{ getSiteType(site.site_type).label }}
              </span>
            </td>
            <td class="location-cell">
              <div class="location-info" v-if="site.city || site.address">
                <span class="city-name" v-if="site.city">📍 {{ site.city }}</span>
                <span class="address-text" v-if="site.address">{{ site.address }}</span>
              </div>
              <span v-else class="text-muted">-</span>
            </td>
            <td class="contact-cell">
              <div v-if="site.contact_name" class="contact-name">{{ site.contact_name }}</div>
              <div class="contact-details">
                <span v-if="site.contact_phone" class="contact-item clickable" @click.stop="copyToClipboard(site.contact_phone)">
                  📱 {{ site.contact_phone }}
                </span>
              </div>
              <span v-if="!site.contact_name && !site.contact_phone" class="text-muted">-</span>
            </td>
            <td>
              <div class="count-badge" :class="{ empty: !site.missions_count, has: site.missions_count > 0 }">
                <span class="count-icon">🚛</span>
                <span class="count-value">{{ site.missions_count || 0 }}</span>
              </div>
            </td>
            <td>
              <span class="status-badge" :class="site.is_active ? 'active' : 'inactive'">
                {{ site.is_active ? '🟢 Actif' : '🔴 Inactif' }}
              </span>
            </td>
            <td class="actions-cell" @click.stop>
              <div class="action-buttons">
                <button class="btn-icon primary" @click="openDetailModal(site)" title="Détails">👁️</button>
                <button 
                  class="btn-icon warning" 
                  @click="openModal(site)" 
                  title="Modifier"
                  v-if="authStore.hasPermission('site:edit')"
                >✏️</button>
                <button 
                  class="btn-icon" 
                  @click="toggleActive(site)" 
                  :title="site.is_active ? 'Désactiver' : 'Activer'"
                  v-if="authStore.hasPermission('site:edit')"
                >
                  {{ site.is_active ? '🔒' : '🔓' }}
                </button>
                <button 
                  class="btn-icon danger" 
                  @click="deleteSite(site)" 
                  title="Supprimer"
                  v-if="authStore.hasPermission('site:delete')"
                >🗑️</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-else class="empty-state">
        <span class="empty-icon">📍</span>
        <h3>Aucun site trouvé</h3>
        <p>Ajoutez des sites ou modifiez vos filtres</p>
      </div>
    </div>

    <!-- City View -->
    <div v-else-if="activeView === 'city'" class="grouped-view">
      <div v-for="(citySites, city) in sitesByCity" :key="city" class="group-section">
        <div class="group-header">
          <span class="group-icon">📍</span>
          <h3>{{ city }}</h3>
          <span class="group-count">{{ citySites.length }}</span>
        </div>
        <div class="group-items">
          <div
            v-for="site in citySites"
            :key="site.id"
            class="group-item"
            :class="{ inactive: !site.is_active }"
            @click="openDetailModal(site)"
          >
            <div class="item-avatar" :class="getSiteType(site.site_type).color">
              {{ getSiteType(site.site_type).icon }}
            </div>
            <div class="item-main">
              <span class="item-code">{{ site.code }}</span>
              <span class="item-name">{{ site.name }}</span>
              <span class="inactive-badge" v-if="!site.is_active">Inactif</span>
            </div>
            <div class="item-type">
              <span class="type-tag">{{ getSiteType(site.site_type).label }}</span>
            </div>
            <div class="item-stats">
              <span class="item-stat">🚛 {{ site.missions_count || 0 }}</span>
            </div>
            <div class="item-actions" @click.stop>
              <button class="btn-mini" @click="openModal(site)" v-if="authStore.hasPermission('site:edit')">✏️</button>
              <button class="btn-mini" @click="openInMaps(site)" v-if="site.latitude || site.address">🗺️</button>
            </div>
          </div>
        </div>
      </div>

      <div v-if="Object.keys(sitesByCity).length === 0" class="empty-state">
        <span class="empty-icon">📍</span>
        <h3>Aucun site trouvé</h3>
      </div>
    </div>

    <!-- Type View -->
    <div v-else-if="activeView === 'type'" class="grouped-view">
      <div v-for="(typeSites, type) in sitesByType" :key="type" class="group-section">
        <div class="group-header" :class="getSiteType(type).color">
          <span class="group-icon">{{ getSiteType(type).icon }}</span>
          <h3>{{ getSiteType(type).label }}</h3>
          <span class="group-count">{{ typeSites.length }}</span>
        </div>
        <div class="group-items">
          <div
            v-for="site in typeSites"
            :key="site.id"
            class="group-item"
            :class="{ inactive: !site.is_active }"
            @click="openDetailModal(site)"
          >
            <div class="item-avatar" :class="getSiteType(site.site_type).color">
              {{ getInitials(site.name) }}
            </div>
            <div class="item-main">
              <span class="item-code">{{ site.code }}</span>
              <span class="item-name">{{ site.name }}</span>
              <span class="inactive-badge" v-if="!site.is_active">Inactif</span>
            </div>
            <div class="item-location" v-if="site.city">
              <span>📍 {{ site.city }}</span>
            </div>
            <div class="item-stats">
              <span class="item-stat">🚛 {{ site.missions_count || 0 }}</span>
            </div>
            <div class="item-actions" @click.stop>
              <button class="btn-mini" @click="openModal(site)" v-if="authStore.hasPermission('site:edit')">✏️</button>
              <button class="btn-mini" @click="openInMaps(site)" v-if="site.latitude || site.address">🗺️</button>
            </div>
          </div>
        </div>
      </div>

      <div v-if="Object.keys(sitesByType).length === 0" class="empty-state">
        <span class="empty-icon">📍</span>
        <h3>Aucun site trouvé</h3>
      </div>
    </div>

    <!-- Pagination -->
    <div class="pagination" v-if="pagination.last_page > 1">
      <button
        v-for="page in pagination.last_page"
        :key="page"
        :class="{ active: page === pagination.current_page }"
        @click="fetchSites(page)"
      >
        {{ page }}
      </button>
    </div>

    <!-- Modal Création/Édition -->
    <div class="modal-overlay" v-if="showModal" @click.self="closeModal">
      <div class="modal modal-lg">
        <div class="modal-header">
          <h2>{{ editingSite ? '✏️ Modifier' : '➕ Nouveau' }} site</h2>
          <button class="close-btn" @click="closeModal">×</button>
        </div>

        <form @submit.prevent="saveSite" class="modal-body">
          <div class="form-section">
            <h3>Informations générales</h3>
            <div class="form-row">
              <div class="form-group flex-2">
                <label>Nom du site *</label>
                <input type="text" v-model="form.name" required placeholder="Nom du site" />
              </div>
              <div class="form-group">
                <label>Type de site *</label>
                <select v-model="form.site_type" required>
                  <option v-for="type in siteTypes" :key="type.value" :value="type.value">
                    {{ type.icon }} {{ type.label }}
                  </option>
                </select>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label>Capacité</label>
                <input type="text" v-model="form.capacity" placeholder="Ex: 500 palettes" />
              </div>
              <div class="form-group">
                <label>Horaires d'ouverture</label>
                <input type="text" v-model="form.operating_hours" placeholder="Ex: 8h-18h" />
              </div>
            </div>
          </div>

          <div class="form-section">
            <h3>Adresse</h3>
            <div class="form-group">
              <label>Adresse</label>
              <textarea v-model="form.address" rows="2" placeholder="Rue, numéro..."></textarea>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label>Ville</label>
                <input type="text" v-model="form.city" placeholder="Ville" />
              </div>
              <div class="form-group">
                <label>Code postal</label>
                <input type="text" v-model="form.postal_code" placeholder="Code postal" />
              </div>
              <div class="form-group">
                <label>Pays</label>
                <input type="text" v-model="form.country" placeholder="Pays" />
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label>🌐 Latitude</label>
                <input type="text" v-model="form.latitude" placeholder="Ex: 36.7538" />
              </div>
              <div class="form-group">
                <label>🌐 Longitude</label>
                <input type="text" v-model="form.longitude" placeholder="Ex: 3.0588" />
              </div>
            </div>
          </div>

          <div class="form-section">
            <h3>Contact sur site</h3>
            <div class="form-group">
              <label>Nom du contact</label>
              <input type="text" v-model="form.contact_name" placeholder="Prénom et nom" />
            </div>

            <div class="form-row">
              <div class="form-group">
                <label>📱 Téléphone</label>
                <input type="tel" v-model="form.contact_phone" placeholder="+213..." />
              </div>
              <div class="form-group">
                <label>✉️ Email</label>
                <input type="email" v-model="form.contact_email" placeholder="email@exemple.com" />
              </div>
            </div>
          </div>

          <div class="form-section">
            <h3>Notes</h3>
            <div class="form-group">
              <textarea v-model="form.notes" rows="3" placeholder="Informations complémentaires, instructions d'accès..."></textarea>
            </div>
          </div>

          <div class="form-section">
            <div class="form-group">
              <label class="checkbox-label" :class="{ checked: form.is_active }">
                <input type="checkbox" v-model="form.is_active" />
                <span class="checkbox-content">
                  <span class="checkbox-icon">{{ form.is_active ? '🟢' : '🔴' }}</span>
                  <span class="checkbox-text">
                    <strong>Site actif</strong>
                    <small>Le site peut être utilisé pour les missions</small>
                  </span>
                </span>
              </label>
            </div>
          </div>

          <div class="form-error" v-if="formError">{{ formError }}</div>

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
          <h2>📋 Fiche site</h2>
          <button class="close-btn" @click="closeDetailModal">×</button>
        </div>

        <div class="modal-body" v-if="selectedSite">
          <div class="detail-header" :class="getSiteType(selectedSite.site_type).color">
            <div class="detail-avatar">
              {{ getSiteType(selectedSite.site_type).icon }}
            </div>
            <div class="detail-title">
              <div class="detail-type">
                {{ getSiteType(selectedSite.site_type).label }}
              </div>
              <div class="detail-code">{{ selectedSite.code }}</div>
              <h2>{{ selectedSite.name }}</h2>
              <div class="detail-badges">
                <span class="status-badge" :class="selectedSite.is_active ? 'active' : 'inactive'">
                  {{ selectedSite.is_active ? '🟢 Actif' : '🔴 Inactif' }}
                </span>
                <span class="location-badge" v-if="selectedSite.city">
                  📍 {{ selectedSite.city }}
                </span>
              </div>
            </div>
          </div>

          <!-- Stats -->
          <div class="detail-stats" v-if="selectedSite.missions_count !== undefined || selectedSite.capacity">
            <div class="detail-stat" v-if="selectedSite.missions_count !== undefined">
              <div class="detail-stat-icon">🚛</div>
              <div class="detail-stat-value">{{ selectedSite.missions_count || 0 }}</div>
              <div class="detail-stat-label">Missions</div>
            </div>
            <div class="detail-stat" v-if="selectedSite.capacity">
              <div class="detail-stat-icon">📦</div>
              <div class="detail-stat-value">{{ selectedSite.capacity }}</div>
              <div class="detail-stat-label">Capacité</div>
            </div>
            <div class="detail-stat" v-if="selectedSite.operating_hours">
              <div class="detail-stat-icon">🕐</div>
              <div class="detail-stat-value">{{ selectedSite.operating_hours }}</div>
              <div class="detail-stat-label">Horaires</div>
            </div>
          </div>

          <div class="detail-grid">
            <!-- Adresse -->
            <div class="detail-section">
              <h4>📍 Adresse</h4>
              <div class="address-block" v-if="selectedSite.address || selectedSite.city">
                <p v-if="selectedSite.address">{{ selectedSite.address }}</p>
                <p v-if="selectedSite.postal_code || selectedSite.city">
                  {{ selectedSite.postal_code }} {{ selectedSite.city }}
                </p>
                <p v-if="selectedSite.country">{{ selectedSite.country }}</p>
                <button 
                  class="btn btn-sm btn-secondary map-btn" 
                  @click="openInMaps(selectedSite)"
                  v-if="selectedSite.latitude || selectedSite.address"
                >
                  🗺️ Voir sur la carte
                </button>
              </div>
              <div v-else class="empty-section">
                Aucune adresse renseignée
              </div>
              
              <div class="coordinates" v-if="selectedSite.latitude && selectedSite.longitude">
                <span class="coord-label">Coordonnées GPS</span>
                <span class="coord-value">{{ selectedSite.latitude }}, {{ selectedSite.longitude }}</span>
              </div>
            </div>

            <!-- Contact -->
            <div class="detail-section">
              <h4>👤 Contact sur site</h4>
              <div class="detail-row" v-if="selectedSite.contact_name">
                <span class="detail-label">Nom</span>
                <span class="detail-value">{{ selectedSite.contact_name }}</span>
              </div>
              <div class="detail-row" v-if="selectedSite.contact_phone">
                <span class="detail-label">Téléphone</span>
                <span class="detail-value clickable" @click="copyToClipboard(selectedSite.contact_phone)">
                  📱 {{ selectedSite.contact_phone }}
                </span>
              </div>
              <div class="detail-row" v-if="selectedSite.contact_email">
                <span class="detail-label">Email</span>
                <span class="detail-value clickable" @click="copyToClipboard(selectedSite.contact_email)">
                  ✉️ {{ selectedSite.contact_email }}
                </span>
              </div>
              <div v-if="!selectedSite.contact_name && !selectedSite.contact_phone && !selectedSite.contact_email" class="empty-section">
                Aucun contact renseigné
              </div>
            </div>

            <!-- Notes -->
            <div class="detail-section full-width" v-if="selectedSite.notes">
              <h4>📝 Notes</h4>
              <p class="detail-text">{{ selectedSite.notes }}</p>
            </div>

            <!-- Missions récentes -->
            <div class="detail-section full-width" v-if="selectedSite.recent_missions?.length">
              <h4>🚛 Missions récentes</h4>
              <div class="missions-list">
                <div v-for="mission in selectedSite.recent_missions" :key="mission.id" class="mission-item">
                  <span class="mission-code">{{ mission.code }}</span>
                  <span class="mission-date">{{ new Date(mission.date).toLocaleDateString('fr-FR') }}</span>
                  <span class="mission-status" :class="mission.status">{{ mission.status }}</span>
                </div>
              </div>
            </div>

            <!-- Infos système -->
            <div class="detail-section full-width">
              <h4>ℹ️ Informations</h4>
              <div class="info-row">
                <span>Créé le</span>
                <span>{{ new Date(selectedSite.created_at).toLocaleDateString('fr-FR') }}</span>
              </div>
              <div class="info-row" v-if="selectedSite.updated_at !== selectedSite.created_at">
                <span>Modifié le</span>
                <span>{{ new Date(selectedSite.updated_at).toLocaleDateString('fr-FR') }}</span>
              </div>
            </div>
          </div>

          <div class="detail-actions">
            <button 
              class="btn btn-warning" 
              @click="openModal(selectedSite); closeDetailModal()"
              v-if="authStore.hasPermission('site:edit')"
            >
              ✏️ Modifier
            </button>
            <button 
              class="btn btn-secondary" 
              @click="toggleActive(selectedSite); closeDetailModal()"
              v-if="authStore.hasPermission('site:edit')"
            >
              {{ selectedSite.is_active ? '🔒 Désactiver' : '🔓 Activer' }}
            </button>
            <a 
              v-if="selectedSite.contact_phone" 
              :href="'tel:' + selectedSite.contact_phone" 
              class="btn btn-primary"
            >
              📞 Appeler
            </a>
            <button 
              class="btn btn-primary" 
              @click="openInMaps(selectedSite)"
              v-if="selectedSite.latitude || selectedSite.address"
            >
              🗺️ Carte
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.sites-page {
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
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 5px;
}

.btn-primary { background: linear-gradient(135deg, #3498db, #2980b9); color: white; }
.btn-secondary { background: #ecf0f1; color: #2c3e50; }
.btn-warning { background: linear-gradient(135deg, #f39c12, #d68910); color: white; }
.btn-sm { padding: 6px 12px; font-size: 12px; }

.btn-primary:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(52, 152, 219, 0.4); }

/* Stats Cards */
.stats-cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
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
.stat-card.info { border-left-color: #17a2b8; }

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
.stat-icon.orange { background: #fff3cd; }
.stat-icon.purple { background: #e8daef; }
.stat-icon.cyan { background: #d1ecf1; }
.stat-icon.teal { background: #d1f2eb; }
.stat-icon.gray { background: #e9ecef; }

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

/* Alert */
.alert {
  padding: 15px 20px;
  border-radius: 8px;
  margin-bottom: 20px;
  display: flex;
  align-items: center;
  gap: 10px;
}

.alert-error {
  background: #f8d7da;
  color: #721c24;
  border: 1px solid #f5c6cb;
}

.alert-icon {
  font-size: 20px;
}

.alert-close {
  margin-left: auto;
  background: none;
  border: none;
  font-size: 20px;
  cursor: pointer;
  color: inherit;
}

/* Grid View */
.sites-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 20px;
}

.site-card {
  background: white;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
  cursor: pointer;
  transition: all 0.2s;
}

.site-card:hover {
  box-shadow: 0 8px 25px rgba(0,0,0,0.12);
  transform: translateY(-3px);
}

.site-card.inactive {
  opacity: 0.6;
}

.card-header {
  padding: 15px 20px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.card-header.blue { background: linear-gradient(135deg, #e8f4fd, #d6eaf8); }
.card-header.orange { background: linear-gradient(135deg, #fff3cd, #ffeeba); }
.card-header.purple { background: linear-gradient(135deg, #e8daef, #d7bde2); }
.card-header.green { background: linear-gradient(135deg, #d4edda, #c3e6cb); }
.card-header.cyan { background: linear-gradient(135deg, #d1ecf1, #bee5eb); }
.card-header.teal { background: linear-gradient(135deg, #d1f2eb, #a3e4d7); }
.card-header.gray { background: linear-gradient(135deg, #e9ecef, #dee2e6); }

.site-type-badge {
  display: flex;
  align-items: center;
  gap: 8px;
}

.type-icon {
  font-size: 24px;
}

.type-label {
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
  color: #555;
}

.status-dot {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  border: 2px solid white;
}

.status-dot.active { background: #27ae60; }
.status-dot.inactive { background: #e74c3c; }

.card-body {
  padding: 20px;
}

.site-code {
  font-family: monospace;
  font-size: 11px;
  color: #7f8c8d;
  margin-bottom: 5px;
}

.site-name {
  font-size: 18px;
  font-weight: 600;
  color: #2c3e50;
  margin: 0 0 15px;
}

.site-location {
  display: flex;
  gap: 10px;
  margin-bottom: 12px;
  padding: 10px;
  background: #f8f9fa;
  border-radius: 8px;
}

.location-icon {
  font-size: 18px;
}

.location-text {
  display: flex;
  flex-direction: column;
}

.location-city {
  font-weight: 500;
  color: #2c3e50;
}

.location-address {
  font-size: 12px;
  color: #7f8c8d;
}

.site-contact {
  margin-bottom: 15px;
}

.contact-item {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: #555;
  margin-bottom: 4px;
}

.site-stats {
  display: flex;
  justify-content: center;
  gap: 25px;
  padding: 12px;
  background: #f8f9fa;
  border-radius: 8px;
}

.site-stat {
  text-align: center;
}

.site-stat .stat-value {
  font-size: 20px;
  font-weight: 700;
  color: #2c3e50;
}

.site-stat .stat-label {
  font-size: 10px;
  color: #7f8c8d;
  text-transform: uppercase;
}

.card-actions {
  display: flex;
  gap: 5px;
  padding: 15px 20px;
  background: #f8f9fa;
  border-top: 1px solid #eee;
  justify-content: center;
}

.btn-action {
  width: 36px;
  height: 36px;
  border: none;
  border-radius: 8px;
  background: white;
  cursor: pointer;
  font-size: 16px;
  transition: all 0.2s;
  box-shadow: 0 1px 3px rgba(0,0,0,0.1);
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

.sites-table {
  width: 100%;
  border-collapse: collapse;
}

.sites-table th {
  text-align: left;
  padding: 15px;
  background: #f8f9fa;
  font-size: 12px;
  text-transform: uppercase;
  color: #7f8c8d;
  font-weight: 600;
}

.sites-table td {
  padding: 15px;
  border-top: 1px solid #eee;
  vertical-align: middle;
}

.sites-table tr {
  cursor: pointer;
  transition: background 0.2s;
}

.sites-table tr:hover {
  background: #f8f9fa;
}

.sites-table tr.inactive {
  opacity: 0.6;
}

.site-cell {
  min-width: 200px;
}

.site-info {
  display: flex;
  align-items: center;
  gap: 12px;
}

.site-avatar {
  width: 42px;
  height: 42px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
}

.site-avatar.blue { background: #e8f4fd; }
.site-avatar.orange { background: #fff3cd; }
.site-avatar.purple { background: #e8daef; }
.site-avatar.green { background: #d4edda; }
.site-avatar.cyan { background: #d1ecf1; }
.site-avatar.teal { background: #d1f2eb; }
.site-avatar.gray { background: #e9ecef; }

.site-info .site-name {
  font-weight: 600;
  color: #2c3e50;
  font-size: 14px;
  margin: 0;
}

.site-info .site-code {
  font-family: monospace;
  font-size: 11px;
  color: #7f8c8d;
  margin: 0;
}

.type-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 5px 10px;
  border-radius: 15px;
  font-size: 11px;
  font-weight: 500;
}

.type-badge.blue { background: #e8f4fd; color: #2980b9; }
.type-badge.orange { background: #fff3cd; color: #d68910; }
.type-badge.purple { background: #e8daef; color: #7d3c98; }
.type-badge.green { background: #d4edda; color: #1e8449; }
.type-badge.cyan { background: #d1ecf1; color: #0c5460; }
.type-badge.teal { background: #d1f2eb; color: #0e6251; }
.type-badge.gray { background: #e9ecef; color: #495057; }

.location-cell {
  max-width: 200px;
}

.location-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.city-name {
  font-weight: 500;
  color: #2c3e50;
}

.address-text {
  font-size: 12px;
  color: #7f8c8d;
}

.contact-cell {
  max-width: 180px;
}

.contact-name {
  font-weight: 500;
  color: #2c3e50;
  margin-bottom: 4px;
}

.contact-details {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.contact-details .contact-item {
  font-size: 12px;
  color: #7f8c8d;
  margin: 0;
}

.clickable {
  cursor: pointer;
}

.clickable:hover {
  color: #3498db;
}

.count-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 5px 10px;
  background: #e8f4fd;
  border-radius: 15px;
  font-size: 12px;
}

.count-badge.empty {
  background: #f8f9fa;
  color: #95a5a6;
}

.count-badge.has {
  background: #d4edda;
  color: #155724;
}

.count-value {
  font-weight: 600;
}

.status-badge {
  padding: 5px 12px;
  border-radius: 15px;
  font-size: 11px;
  font-weight: 500;
}

.status-badge.active {
  background: #d4edda;
  color: #155724;
}

.status-badge.inactive {
  background: #f8d7da;
  color: #721c24;
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
.btn-icon.warning { background: #fff3cd; }
.btn-icon.danger { background: #f8d7da; }
.btn-icon.danger:hover { background: #f5c6cb; }

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

.group-header.blue { background: linear-gradient(135deg, #e8f4fd, #d6eaf8); }
.group-header.orange { background: linear-gradient(135deg, #fff3cd, #ffeeba); }
.group-header.purple { background: linear-gradient(135deg, #e8daef, #d7bde2); }
.group-header.green { background: linear-gradient(135deg, #d4edda, #c3e6cb); }
.group-header.cyan { background: linear-gradient(135deg, #d1ecf1, #bee5eb); }
.group-header.teal { background: linear-gradient(135deg, #d1f2eb, #a3e4d7); }
.group-header.gray { background: linear-gradient(135deg, #e9ecef, #dee2e6); }

.group-icon {
  font-size: 24px;
}

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
  gap: 12px;
  padding: 12px 15px;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s;
}

.group-item:hover {
  background: #f8f9fa;
}

.group-item.inactive {
  opacity: 0.5;
}

.item-avatar {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  font-size: 14px;
}

.item-main {
  flex: 1;
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
}

.item-code {
  font-family: monospace;
  font-size: 11px;
  color: #7f8c8d;
  background: #f0f0f0;
  padding: 2px 8px;
  border-radius: 4px;
}

.item-name {
  font-weight: 500;
  color: #2c3e50;
}

.inactive-badge {
  font-size: 10px;
  padding: 2px 6px;
  background: #f8d7da;
  color: #721c24;
  border-radius: 4px;
}

.item-type {
  min-width: 100px;
}

.type-tag {
  font-size: 12px;
  color: #7f8c8d;
}

.item-location {
  min-width: 120px;
  font-size: 12px;
  color: #555;
}

.item-stats {
  display: flex;
  gap: 10px;
  font-size: 12px;
  color: #7f8c8d;
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

.empty-state h3 {
  margin: 15px 0 5px;
  color: #2c3e50;
}

.empty-state .btn {
  margin-top: 20px;
}

.loading-state {
  text-align: center;
  padding: 60px;
  background: white;
  border-radius: 12px;
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
  font-size: 12px;
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
  margin-bottom: 20px;
}

.form-section h3 {
  font-size: 13px;
  color: #7f8c8d;
  text-transform: uppercase;
  margin: 0 0 12px;
  padding-bottom: 8px;
  border-bottom: 1px solid #eee;
}

.form-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 15px;
}

.form-group {
  margin-bottom: 15px;
}

.form-group.flex-2 {
  grid-column: span 2;
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

.checkbox-label {
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

.checkbox-label.checked {
  background: #d4edda;
  border-color: #27ae60;
}

.checkbox-label input {
  width: auto;
  margin-top: 3px;
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
  color: #2c3e50;
}

.checkbox-text small {
  color: #7f8c8d;
  font-size: 12px;
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
  padding: 25px;
  border-radius: 12px;
  margin-bottom: 20px;
}

.detail-header.blue { background: linear-gradient(135deg, #e8f4fd, #d6eaf8); }
.detail-header.orange { background: linear-gradient(135deg, #fff3cd, #ffeeba); }
.detail-header.purple { background: linear-gradient(135deg, #e8daef, #d7bde2); }
.detail-header.green { background: linear-gradient(135deg, #d4edda, #c3e6cb); }
.detail-header.cyan { background: linear-gradient(135deg, #d1ecf1, #bee5eb); }
.detail-header.teal { background: linear-gradient(135deg, #d1f2eb, #a3e4d7); }
.detail-header.gray { background: linear-gradient(135deg, #e9ecef, #dee2e6); }

.detail-avatar {
  width: 70px;
  height: 70px;
  background: white;
  border-radius: 15px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 36px;
  box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.detail-title {
  flex: 1;
}

.detail-type {
  font-size: 11px;
  text-transform: uppercase;
  color: #7f8c8d;
  font-weight: 600;
  margin-bottom: 2px;
}

.detail-code {
  font-family: monospace;
  font-size: 12px;
  color: #555;
  margin-bottom: 5px;
}

.detail-title h2 {
  margin: 0 0 10px;
  font-size: 22px;
  color: #2c3e50;
}

.detail-badges {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.location-badge {
  background: white;
  padding: 4px 10px;
  border-radius: 15px;
  font-size: 12px;
  color: #555;
}

.detail-stats {
  display: flex;
  gap: 20px;
  margin-bottom: 20px;
  padding: 15px;
  background: #f8f9fa;
  border-radius: 10px;
  justify-content: center;
}

.detail-stat {
  text-align: center;
  padding: 10px 20px;
}

.detail-stat-icon {
  font-size: 24px;
  margin-bottom: 5px;
}

.detail-stat-value {
  font-size: 20px;
  font-weight: 700;
  color: #2c3e50;
}

.detail-stat-label {
  font-size: 11px;
  color: #7f8c8d;
  text-transform: uppercase;
}

.detail-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}

.detail-section {
  background: #f8f9fa;
  border-radius: 10px;
  padding: 15px;
}

.detail-section.full-width {
  grid-column: 1 / -1;
}

.detail-section h4 {
  margin: 0 0 12px;
  font-size: 13px;
  color: #7f8c8d;
}

.address-block {
  color: #2c3e50;
}

.address-block p {
  margin: 0 0 5px;
}

.map-btn {
  margin-top: 10px;
}

.coordinates {
  margin-top: 12px;
  padding-top: 12px;
  border-top: 1px solid #e9ecef;
  display: flex;
  justify-content: space-between;
  font-size: 12px;
}

.coord-label {
  color: #7f8c8d;
}

.coord-value {
  font-family: monospace;
  color: #555;
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
  color: #555;
  margin: 0;
  line-height: 1.6;
}

.empty-section {
  color: #95a5a6;
  font-size: 13px;
  font-style: italic;
}

.missions-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.mission-item {
  display: flex;
  align-items: center;
  gap: 15px;
  padding: 10px;
  background: white;
  border-radius: 6px;
}

.mission-code {
  font-family: monospace;
  font-weight: 500;
  color: #2c3e50;
}

.mission-date {
  color: #7f8c8d;
  font-size: 12px;
}

.mission-status {
  margin-left: auto;
  padding: 3px 8px;
  border-radius: 10px;
  font-size: 11px;
}

.mission-status.completed { background: #d4edda; color: #155724; }
.mission-status.pending { background: #fff3cd; color: #856404; }
.mission-status.in_progress { background: #d1ecf1; color: #0c5460; }

.info-row {
  display: flex;
  justify-content: space-between;
  padding: 8px 0;
  border-bottom: 1px solid #e9ecef;
  font-size: 13px;
  color: #555;
}

.info-row:last-child {
  border-bottom: none;
}

.detail-actions {
  margin-top: 20px;
  padding-top: 20px;
  border-top: 1px solid #eee;
  display: flex;
  gap: 10px;
  justify-content: center;
  flex-wrap: wrap;
}

@media (max-width: 768px) {
  .sites-grid {
    grid-template-columns: 1fr;
  }

  .stats-cards {
    grid-template-columns: repeat(2, 1fr);
  }

  .filters-bar {
    flex-direction: column;
  }

  .search-box {
    max-width: 100%;
  }

  .view-toggle {
    margin-left: 0;
    width: 100%;
    justify-content: center;
  }

  .detail-grid {
    grid-template-columns: 1fr;
  }

  .detail-header {
    flex-direction: column;
    text-align: center;
  }

  .form-row {
    grid-template-columns: 1fr;
  }

  .form-group.flex-2 {
    grid-column: span 1;
  }
}
</style>
