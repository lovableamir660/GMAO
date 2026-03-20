<script setup>
import { ref, onMounted, computed } from 'vue'
import api from '@/services/api'

const clients = ref([])
const loading = ref(true)
const showModal = ref(false)
const showDetailModal = ref(false)
const editingClient = ref(null)
const selectedClient = ref(null)
const search = ref('')
const activeFilter = ref('')
const cityFilter = ref('')
const activeView = ref('table')
const pagination = ref({})

const form = ref({
  name: '',
  contact_name: '',
  contact_email: '',
  contact_phone: '',
  address: '',
  city: '',
  postal_code: '',
  country: 'Algérie',
  website: '',
  notes: '',
  is_active: true,
})

const saving = ref(false)
const error = ref('')

// Stats calculées
const stats = computed(() => {
  const all = clients.value
  return {
    total: pagination.value.total || all.length,
    active: all.filter(c => c.is_active).length,
    inactive: all.filter(c => !c.is_active).length,
    withHabilitations: all.filter(c => c.habilitations_count > 0).length,
    totalHabilitations: all.reduce((sum, c) => sum + (c.habilitations_count || 0), 0),
    totalMissions: all.reduce((sum, c) => sum + (c.missions_count || 0), 0),
  }
})

// Liste des villes uniques
const cities = computed(() => {
  const cityList = clients.value
    .map(c => c.city)
    .filter(Boolean)
  return [...new Set(cityList)].sort()
})

// Grouper par ville
const clientsByCity = computed(() => {
  const grouped = {}
  clients.value.forEach(c => {
    const city = c.city || 'Non définie'
    if (!grouped[city]) grouped[city] = []
    grouped[city].push(c)
  })
  return grouped
})

async function fetchClients(page = 1) {
  loading.value = true
  try {
    const params = { page, per_page: 20 }
    if (search.value) params.search = search.value
    if (activeFilter.value !== '') params.is_active = activeFilter.value
    if (cityFilter.value) params.city = cityFilter.value

    const response = await api.get('/clients', { params })
    clients.value = response.data.data
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

function openModal(client = null) {
  editingClient.value = client
  if (client) {
    form.value = {
      name: client.name,
      contact_name: client.contact_name || '',
      contact_email: client.contact_email || '',
      contact_phone: client.contact_phone || '',
      address: client.address || '',
      city: client.city || '',
      postal_code: client.postal_code || '',
      country: client.country || 'Algérie',
      website: client.website || '',
      notes: client.notes || '',
      is_active: client.is_active,
    }
  } else {
    form.value = {
      name: '',
      contact_name: '',
      contact_email: '',
      contact_phone: '',
      address: '',
      city: '',
      postal_code: '',
      country: 'Algérie',
      website: '',
      notes: '',
      is_active: true,
    }
  }
  error.value = ''
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  editingClient.value = null
}

async function openDetailModal(client) {
  selectedClient.value = client
  showDetailModal.value = true
  
  // Charger les détails complets
  try {
    const response = await api.get(`/clients/${client.id}`)
    selectedClient.value = response.data
  } catch (err) {
    console.error('Erreur:', err)
  }
}

function closeDetailModal() {
  showDetailModal.value = false
  selectedClient.value = null
}

async function saveClient() {
  saving.value = true
  error.value = ''

  try {
    if (editingClient.value) {
      await api.put(`/clients/${editingClient.value.id}`, form.value)
    } else {
      await api.post('/clients', form.value)
    }
    closeModal()
    fetchClients()
  } catch (err) {
    error.value = err.response?.data?.message || 'Erreur lors de la sauvegarde'
  } finally {
    saving.value = false
  }
}

async function deleteClient(client) {
  if (client.habilitations_count > 0 || client.missions_count > 0) {
    alert(`Impossible de supprimer : ce client a ${client.habilitations_count || 0} habilitation(s) et ${client.missions_count || 0} mission(s) associée(s).`)
    return
  }
  if (!confirm(`Supprimer le client "${client.name}" ?`)) return

  try {
    await api.delete(`/clients/${client.id}`)
    fetchClients()
  } catch (err) {
    alert(err.response?.data?.message || 'Erreur lors de la suppression')
  }
}

async function toggleActive(client) {
  try {
    await api.put(`/clients/${client.id}`, {
      ...client,
      is_active: !client.is_active,
    })
    fetchClients()
  } catch (err) {
    alert('Erreur lors de la mise à jour')
  }
}

function applyFilters() {
  fetchClients()
}

function resetFilters() {
  search.value = ''
  activeFilter.value = ''
  cityFilter.value = ''
  fetchClients()
}

function getInitials(name) {
  return name
    .split(' ')
    .map(word => word.charAt(0))
    .join('')
    .substring(0, 2)
    .toUpperCase()
}

function getClientColor(client) {
  const colors = ['blue', 'green', 'purple', 'orange', 'cyan', 'red', 'teal']
  const index = client.id % colors.length
  return colors[index]
}

function copyToClipboard(text) {
  navigator.clipboard.writeText(text)
  // Optionnel : afficher un toast
}

onMounted(() => {
  fetchClients()
})
</script>

<template>
  <div class="clients-page">
    <header class="page-header">
      <div>
        <h1>🏢 Clients</h1>
        <p class="subtitle">Gestion de votre portefeuille clients</p>
      </div>
      <button class="btn btn-primary" @click="openModal()">
        + Nouveau client
      </button>
    </header>

    <!-- Stats Cards -->
    <div class="stats-cards">
      <div class="stat-card" @click="activeFilter = ''; applyFilters()">
        <div class="stat-icon blue">🏢</div>
        <div class="stat-content">
          <div class="stat-value">{{ stats.total }}</div>
          <div class="stat-label">Total clients</div>
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
      <div class="stat-card info">
        <div class="stat-icon purple">📜</div>
        <div class="stat-content">
          <div class="stat-value">{{ stats.totalHabilitations }}</div>
          <div class="stat-label">Habilitations</div>
        </div>
      </div>
      <div class="stat-card">
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
      <select v-model="cityFilter" @change="applyFilters" v-if="cities.length > 0">
        <option value="">Toutes les villes</option>
        <option v-for="city in cities" :key="city" :value="city">
          📍 {{ city }}
        </option>
      </select>
      <button class="btn btn-secondary btn-sm" @click="resetFilters" v-if="search || activeFilter !== '' || cityFilter">
        ✕ Reset
      </button>
      <div class="view-toggle">
        <button :class="{ active: activeView === 'table' }" @click="activeView = 'table'" title="Vue tableau">☰</button>
        <button :class="{ active: activeView === 'grid' }" @click="activeView = 'grid'" title="Vue grille">▦</button>
        <button :class="{ active: activeView === 'city' }" @click="activeView = 'city'" title="Par ville">📍</button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <p>Chargement...</p>
    </div>

    <!-- Table View -->
    <div v-else-if="activeView === 'table'" class="table-container">
      <table class="clients-table" v-if="clients.length">
        <thead>
          <tr>
            <th>Client</th>
            <th>Contact</th>
            <th>Localisation</th>
            <th>Habilitations</th>
            <th>Missions</th>
            <th>Statut</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="client in clients" :key="client.id" :class="{ inactive: !client.is_active }">
            <td class="client-cell">
              <div class="client-info" @click="openDetailModal(client)">
                <div class="client-avatar" :class="getClientColor(client)">
                  {{ getInitials(client.name) }}
                </div>
                <div>
                  <div class="client-name">{{ client.name }}</div>
                  <div class="client-code">{{ client.code }}</div>
                </div>
              </div>
            </td>
            <td class="contact-cell">
              <div v-if="client.contact_name" class="contact-name">
                👤 {{ client.contact_name }}
              </div>
              <div class="contact-details">
                <span v-if="client.contact_phone" class="contact-item" @click="copyToClipboard(client.contact_phone)" title="Copier">
                  📱 {{ client.contact_phone }}
                </span>
                <span v-if="client.contact_email" class="contact-item" @click="copyToClipboard(client.contact_email)" title="Copier">
                  ✉️ {{ client.contact_email }}
                </span>
              </div>
              <div v-if="!client.contact_name && !client.contact_phone && !client.contact_email" class="text-muted">
                -
              </div>
            </td>
            <td class="location-cell">
              <div class="location-info" v-if="client.city || client.address">
                <span class="city-badge" v-if="client.city">📍 {{ client.city }}</span>
                <span class="address-text" v-if="client.address">{{ client.address }}</span>
              </div>
              <span v-else class="text-muted">-</span>
            </td>
            <td>
              <div class="count-badge" :class="{ empty: !client.habilitations_count, has: client.habilitations_count > 0 }">
                <span class="count-icon">📜</span>
                <span class="count-value">{{ client.habilitations_count || 0 }}</span>
              </div>
            </td>
            <td>
              <div class="count-badge" :class="{ empty: !client.missions_count, has: client.missions_count > 0 }">
                <span class="count-icon">🚛</span>
                <span class="count-value">{{ client.missions_count || 0 }}</span>
              </div>
            </td>
            <td>
              <span class="status-badge" :class="client.is_active ? 'active' : 'inactive'">
                {{ client.is_active ? '🟢 Actif' : '🔴 Inactif' }}
              </span>
            </td>
            <td class="actions-cell">
              <div class="action-buttons">
                <button class="btn-icon primary" @click="openDetailModal(client)" title="Détails">
                  👁️
                </button>
                <button class="btn-icon warning" @click="openModal(client)" title="Modifier">
                  ✏️
                </button>
                <button class="btn-icon" @click="toggleActive(client)" :title="client.is_active ? 'Désactiver' : 'Activer'">
                  {{ client.is_active ? '🔒' : '🔓' }}
                </button>
                <button class="btn-icon danger" @click="deleteClient(client)" title="Supprimer">
                  🗑️
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-else class="empty-state">
        <span class="empty-icon">🏢</span>
        <h3>Aucun client trouvé</h3>
        <p>Ajoutez des clients ou modifiez vos filtres</p>
      </div>
    </div>

    <!-- Grid View -->
    <div v-else-if="activeView === 'grid'" class="clients-grid">
      <div
        v-for="client in clients"
        :key="client.id"
        class="client-card"
        :class="{ inactive: !client.is_active }"
        @click="openDetailModal(client)"
      >
        <div class="card-header">
          <div class="client-avatar large" :class="getClientColor(client)">
            {{ getInitials(client.name) }}
          </div>
          <div class="status-dot" :class="client.is_active ? 'active' : 'inactive'"></div>
        </div>

        <div class="card-body">
          <div class="client-code">{{ client.code }}</div>
          <h3 class="client-name">{{ client.name }}</h3>
          
          <div class="client-location" v-if="client.city">
            📍 {{ client.city }}
          </div>

          <div class="client-contact" v-if="client.contact_name">
            <span class="contact-icon">👤</span>
            <span>{{ client.contact_name }}</span>
          </div>

          <div class="client-stats">
            <div class="client-stat">
              <span class="stat-value">{{ client.habilitations_count || 0 }}</span>
              <span class="stat-label">Habilitations</span>
            </div>
            <div class="client-stat">
              <span class="stat-value">{{ client.missions_count || 0 }}</span>
              <span class="stat-label">Missions</span>
            </div>
          </div>
        </div>

        <div class="card-actions" @click.stop>
          <button class="btn-action" @click="openDetailModal(client)" title="Détails">👁️</button>
          <button class="btn-action" @click="openModal(client)" title="Modifier">✏️</button>
          <button class="btn-action" @click="toggleActive(client)" :title="client.is_active ? 'Désactiver' : 'Activer'">
            {{ client.is_active ? '🔒' : '🔓' }}
          </button>
          <button class="btn-action danger" @click="deleteClient(client)" title="Supprimer">🗑️</button>
        </div>
      </div>

      <div v-if="clients.length === 0" class="empty-state full-width">
        <span class="empty-icon">🏢</span>
        <h3>Aucun client trouvé</h3>
        <p>Ajoutez des clients ou modifiez vos filtres</p>
      </div>
    </div>

    <!-- City View -->
    <div v-else-if="activeView === 'city'" class="grouped-view">
      <div v-for="(cityClients, city) in clientsByCity" :key="city" class="group-section">
        <div class="group-header">
          <span class="group-icon">📍</span>
          <h3>{{ city }}</h3>
          <span class="group-count">{{ cityClients.length }}</span>
        </div>
        <div class="group-items">
          <div
            v-for="client in cityClients"
            :key="client.id"
            class="group-item"
            :class="{ inactive: !client.is_active }"
            @click="openDetailModal(client)"
          >
            <div class="item-avatar" :class="getClientColor(client)">
              {{ getInitials(client.name) }}
            </div>
            <div class="item-main">
              <span class="item-code">{{ client.code }}</span>
              <span class="item-name">{{ client.name }}</span>
              <span class="inactive-dot" v-if="!client.is_active">🔴</span>
            </div>
            <div class="item-stats">
              <span class="item-stat">📜 {{ client.habilitations_count || 0 }}</span>
              <span class="item-stat">🚛 {{ client.missions_count || 0 }}</span>
            </div>
            <div class="item-contact" v-if="client.contact_name">
              👤 {{ client.contact_name }}
            </div>
            <div class="item-actions" @click.stop>
              <button class="btn-mini" @click="openModal(client)">✏️</button>
              <button class="btn-mini" @click="deleteClient(client)">🗑️</button>
            </div>
          </div>
        </div>
      </div>

      <div v-if="Object.keys(clientsByCity).length === 0" class="empty-state">
        <span class="empty-icon">🏢</span>
        <h3>Aucun client trouvé</h3>
      </div>
    </div>

    <!-- Pagination -->
    <div class="pagination" v-if="pagination.last_page > 1">
      <button
        v-for="page in pagination.last_page"
        :key="page"
        :class="{ active: page === pagination.current_page }"
        @click="fetchClients(page)"
      >
        {{ page }}
      </button>
    </div>

    <!-- Modal Création/Édition -->
    <div class="modal-overlay" v-if="showModal" @click.self="closeModal">
      <div class="modal modal-lg">
        <div class="modal-header">
          <h2>{{ editingClient ? '✏️ Modifier' : '➕ Nouveau' }} client</h2>
          <button class="close-btn" @click="closeModal">×</button>
        </div>

        <form @submit.prevent="saveClient" class="modal-body">
          <div class="form-section">
            <h3>Informations générales</h3>
            <div class="form-group">
              <label>Nom du client *</label>
              <input type="text" v-model="form.name" required placeholder="Nom de l'entreprise" />
            </div>
          </div>

          <div class="form-section">
            <h3>Contact</h3>
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

            <div class="form-group">
              <label>🌐 Site web</label>
              <input type="url" v-model="form.website" placeholder="https://..." />
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
            </div>

            <div class="form-group">
              <label>Pays</label>
              <input type="text" v-model="form.country" placeholder="Pays" />
            </div>
          </div>

          <div class="form-section">
            <h3>Notes</h3>
            <div class="form-group">
              <textarea v-model="form.notes" rows="3" placeholder="Informations complémentaires..."></textarea>
            </div>
          </div>

          <div class="form-section">
            <div class="form-group">
              <label class="checkbox-label" :class="{ checked: form.is_active }">
                <input type="checkbox" v-model="form.is_active" />
                <span class="checkbox-content">
                  <span class="checkbox-icon">{{ form.is_active ? '🟢' : '🔴' }}</span>
                  <span class="checkbox-text">
                    <strong>Client actif</strong>
                    <small>Le client peut être sélectionné pour les missions</small>
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
          <h2>📋 Fiche client</h2>
          <button class="close-btn" @click="closeDetailModal">×</button>
        </div>

        <div class="modal-body" v-if="selectedClient">
          <div class="detail-header">
            <div class="detail-avatar" :class="getClientColor(selectedClient)">
              {{ getInitials(selectedClient.name) }}
            </div>
            <div class="detail-title">
              <div class="detail-code">{{ selectedClient.code }}</div>
              <h2>{{ selectedClient.name }}</h2>
              <div class="detail-badges">
                <span class="status-badge" :class="selectedClient.is_active ? 'active' : 'inactive'">
                  {{ selectedClient.is_active ? '🟢 Actif' : '🔴 Inactif' }}
                </span>
                <span class="location-badge" v-if="selectedClient.city">
                  📍 {{ selectedClient.city }}
                </span>
              </div>
            </div>
          </div>

          <!-- Stats -->
          <div class="detail-stats">
            <div class="detail-stat">
              <div class="detail-stat-icon">📜</div>
              <div class="detail-stat-value">{{ selectedClient.habilitations_count || 0 }}</div>
              <div class="detail-stat-label">Habilitations</div>
            </div>
            <div class="detail-stat">
              <div class="detail-stat-icon">🚛</div>
              <div class="detail-stat-value">{{ selectedClient.missions_count || 0 }}</div>
              <div class="detail-stat-label">Missions</div>
            </div>
            <div class="detail-stat" v-if="selectedClient.mandatory_habilitations_count">
              <div class="detail-stat-icon">⚠️</div>
              <div class="detail-stat-value">{{ selectedClient.mandatory_habilitations_count }}</div>
              <div class="detail-stat-label">Obligatoires</div>
            </div>
          </div>

          <div class="detail-grid">
            <!-- Contact -->
            <div class="detail-section">
              <h4>👤 Contact</h4>
              <div class="detail-row" v-if="selectedClient.contact_name">
                <span class="detail-label">Nom</span>
                <span class="detail-value">{{ selectedClient.contact_name }}</span>
              </div>
              <div class="detail-row" v-if="selectedClient.contact_phone">
                <span class="detail-label">Téléphone</span>
                <span class="detail-value clickable" @click="copyToClipboard(selectedClient.contact_phone)">
                  📱 {{ selectedClient.contact_phone }}
                </span>
              </div>
              <div class="detail-row" v-if="selectedClient.contact_email">
                <span class="detail-label">Email</span>
                <span class="detail-value clickable" @click="copyToClipboard(selectedClient.contact_email)">
                  ✉️ {{ selectedClient.contact_email }}
                </span>
              </div>
              <div class="detail-row" v-if="selectedClient.website">
                <span class="detail-label">Site web</span>
                <a :href="selectedClient.website" target="_blank" class="detail-value link">
                  🌐 {{ selectedClient.website }}
                </a>
              </div>
              <div v-if="!selectedClient.contact_name && !selectedClient.contact_phone && !selectedClient.contact_email" class="empty-section">
                Aucun contact renseigné
              </div>
            </div>

            <!-- Adresse -->
            <div class="detail-section">
              <h4>📍 Adresse</h4>
              <div class="address-block" v-if="selectedClient.address || selectedClient.city">
                <p v-if="selectedClient.address">{{ selectedClient.address }}</p>
                <p v-if="selectedClient.postal_code || selectedClient.city">
                  {{ selectedClient.postal_code }} {{ selectedClient.city }}
                </p>
                <p v-if="selectedClient.country">{{ selectedClient.country }}</p>
              </div>
              <div v-else class="empty-section">
                Aucune adresse renseignée
              </div>
            </div>

            <!-- Habilitations -->
            <div class="detail-section full-width" v-if="selectedClient.habilitations?.length">
              <h4>📜 Habilitations requises</h4>
              <div class="habilitations-list">
                <div 
                  v-for="hab in selectedClient.habilitations" 
                  :key="hab.id" 
                  class="hab-item"
                  :class="{ mandatory: hab.is_mandatory }"
                >
                  <span class="hab-code">{{ hab.code }}</span>
                  <span class="hab-name">{{ hab.name }}</span>
                  <span class="hab-badge mandatory" v-if="hab.is_mandatory">Obligatoire</span>
                  <span class="hab-badge optional" v-else>Optionnelle</span>
                </div>
              </div>
            </div>

            <!-- Notes -->
            <div class="detail-section full-width" v-if="selectedClient.notes">
              <h4>📝 Notes</h4>
              <p class="detail-text">{{ selectedClient.notes }}</p>
            </div>

            <!-- Infos système -->
            <div class="detail-section full-width">
              <h4>ℹ️ Informations</h4>
              <div class="info-row">
                <span>Créé le</span>
                <span>{{ new Date(selectedClient.created_at).toLocaleDateString('fr-FR') }}</span>
              </div>
              <div class="info-row" v-if="selectedClient.updated_at !== selectedClient.created_at">
                <span>Modifié le</span>
                <span>{{ new Date(selectedClient.updated_at).toLocaleDateString('fr-FR') }}</span>
              </div>
            </div>
          </div>

          <div class="detail-actions">
            <button class="btn btn-warning" @click="openModal(selectedClient); closeDetailModal()">
              ✏️ Modifier
            </button>
            <button class="btn btn-secondary" @click="toggleActive(selectedClient); closeDetailModal()">
              {{ selectedClient.is_active ? '🔒 Désactiver' : '🔓 Activer' }}
            </button>
            <a v-if="selectedClient.contact_phone" :href="'tel:' + selectedClient.contact_phone" class="btn btn-primary">
              📞 Appeler
            </a>
            <a v-if="selectedClient.contact_email" :href="'mailto:' + selectedClient.contact_email" class="btn btn-primary">
              ✉️ Email
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.clients-page {
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

/* Table View */
.table-container {
  background: white;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.clients-table {
  width: 100%;
  border-collapse: collapse;
}

.clients-table th {
  text-align: left;
  padding: 15px;
  background: #f8f9fa;
  font-size: 12px;
  text-transform: uppercase;
  color: #7f8c8d;
  font-weight: 600;
}

.clients-table td {
  padding: 15px;
  border-top: 1px solid #eee;
  vertical-align: middle;
}

.clients-table tr:hover {
  background: #f8f9fa;
}

.clients-table tr.inactive {
  opacity: 0.6;
}

.client-cell {
  cursor: pointer;
}

.client-info {
  display: flex;
  align-items: center;
  gap: 12px;
}

.client-avatar {
  width: 42px;
  height: 42px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 14px;
  color: white;
}

.client-avatar.large {
  width: 60px;
  height: 60px;
  font-size: 20px;
}

.client-avatar.blue { background: linear-gradient(135deg, #3498db, #2980b9); }
.client-avatar.green { background: linear-gradient(135deg, #27ae60, #1e8449); }
.client-avatar.purple { background: linear-gradient(135deg, #9b59b6, #7d3c98); }
.client-avatar.orange { background: linear-gradient(135deg, #f39c12, #d68910); }
.client-avatar.cyan { background: linear-gradient(135deg, #1abc9c, #16a085); }
.client-avatar.red { background: linear-gradient(135deg, #e74c3c, #c0392b); }
.client-avatar.teal { background: linear-gradient(135deg, #17a2b8, #138496); }

.client-name {
  font-weight: 600;
  color: #2c3e50;
}

.client-code {
  font-family: monospace;
  font-size: 11px;
  color: #7f8c8d;
}

.contact-cell {
  max-width: 200px;
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

.contact-item {
  font-size: 12px;
  color: #7f8c8d;
  cursor: pointer;
  transition: color 0.2s;
}

.contact-item:hover {
  color: #3498db;
}

.location-cell {
  max-width: 180px;
}

.location-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.city-badge {
  background: #e8f4fd;
  color: #2980b9;
  padding: 3px 8px;
  border-radius: 10px;
  font-size: 11px;
  display: inline-block;
}

.address-text {
  font-size: 12px;
  color: #7f8c8d;
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

/* Grid View */
.clients-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 20px;
}

.client-card {
  background: white;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
  cursor: pointer;
  transition: all 0.2s;
}

.client-card:hover {
  box-shadow: 0 8px 25px rgba(0,0,0,0.12);
  transform: translateY(-3px);
}

.client-card.inactive {
  opacity: 0.6;
}

.card-header {
  position: relative;
  padding: 20px;
  display: flex;
  justify-content: center;
  background: linear-gradient(135deg, #f8f9fa, #e9ecef);
}

.status-dot {
  position: absolute;
  top: 15px;
  right: 15px;
  width: 12px;
  height: 12px;
  border-radius: 50%;
  border: 2px solid white;
}

.status-dot.active { background: #27ae60; }
.status-dot.inactive { background: #e74c3c; }

.card-body {
  padding: 15px 20px;
  text-align: center;
}

.card-body .client-code {
  margin-bottom: 5px;
}

.card-body .client-name {
  font-size: 16px;
  margin: 0 0 10px;
}

.client-location {
  font-size: 13px;
  color: #7f8c8d;
  margin-bottom: 8px;
}

.client-contact {
  font-size: 12px;
  color: #555;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 5px;
  margin-bottom: 15px;
}

.client-stats {
  display: flex;
  justify-content: center;
  gap: 20px;
  padding: 12px;
  background: #f8f9fa;
  border-radius: 8px;
}

.client-stat {
  text-align: center;
}

.client-stat .stat-value {
  font-size: 20px;
  font-weight: 700;
  color: #2c3e50;
}

.client-stat .stat-label {
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
  width: 36px;
  height: 36px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  font-size: 12px;
  color: white;
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

.inactive-dot {
  font-size: 10px;
}

.item-stats {
  display: flex;
  gap: 10px;
  font-size: 12px;
  color: #7f8c8d;
}

.item-contact {
  font-size: 12px;
  color: #555;
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
  padding: 20px;
  background: #f8f9fa;
  border-radius: 12px;
  margin-bottom: 20px;
}

.detail-avatar {
  width: 80px;
  height: 80px;
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 28px;
  color: white;
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
  gap: 10px;
  flex-wrap: wrap;
}

.location-badge {
  background: #e8f4fd;
  color: #2980b9;
  padding: 4px 10px;
  border-radius: 15px;
  font-size: 11px;
}

.detail-stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
  gap: 15px;
  margin-bottom: 20px;
}

.detail-stat {
  text-align: center;
  padding: 15px;
  background: #f8f9fa;
  border-radius: 10px;
}

.detail-stat-icon {
  font-size: 24px;
  margin-bottom: 5px;
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

.detail-value.clickable {
  cursor: pointer;
  transition: color 0.2s;
}

.detail-value.clickable:hover {
  color: #3498db;
}

.detail-value.link {
  color: #3498db;
  text-decoration: none;
}

.detail-value.link:hover {
  text-decoration: underline;
}

.address-block {
  font-size: 14px;
  color: #555;
  line-height: 1.6;
}

.address-block p {
  margin: 0;
}

.empty-section {
  color: #95a5a6;
  font-size: 13px;
  font-style: italic;
}

.habilitations-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.hab-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px;
  background: white;
  border-radius: 6px;
}

.hab-item.mandatory {
  border-left: 3px solid #e74c3c;
}

.hab-code {
  font-family: monospace;
  font-size: 11px;
  color: #7f8c8d;
  background: #f0f0f0;
  padding: 2px 8px;
  border-radius: 4px;
}

.hab-name {
  flex: 1;
  font-weight: 500;
  color: #2c3e50;
}

.hab-badge {
  padding: 3px 8px;
  border-radius: 10px;
  font-size: 10px;
  font-weight: 600;
}

.hab-badge.mandatory {
  background: #f8d7da;
  color: #721c24;
}

.hab-badge.optional {
  background: #d4edda;
  color: #155724;
}

.detail-text {
  margin: 0;
  font-size: 14px;
  color: #555;
  line-height: 1.5;
}

.info-row {
  display: flex;
  justify-content: space-between;
  padding: 6px 0;
  font-size: 12px;
  color: #7f8c8d;
}

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

  .detail-header {
    flex-direction: column;
    text-align: center;
  }
}
</style>
