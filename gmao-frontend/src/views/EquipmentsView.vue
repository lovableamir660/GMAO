<script setup>
import { ref, onMounted, computed } from 'vue'
import api from '@/services/api'

const equipments   = ref([])
const locations    = ref([])
const loading      = ref(true)
const showModal    = ref(false)
const showDetail   = ref(false)
const showImport   = ref(false)
const editingEq    = ref(null)
const selectedEq   = ref(null)
const exporting    = ref(false)
const importing    = ref(false)
const importFile   = ref(null)
const importResult = ref(null)
const importError  = ref('')
const search       = ref('')
const statusFilter = ref('')
const typeFilter   = ref('')
const critFilter   = ref('')
const catFilter    = ref('')
const locFilter    = ref('')
const activeView   = ref('table')
const pagination   = ref({})

const form = ref({
  code: '', name: '', type: '', category: '', brand: '', model: '',
  serial_number: '', year: '', status: 'operational', location_id: '',
  department: '', criticality: 'medium', installation_date: '',
  warranty_expiry_date: '', acquisition_date: '', acquisition_cost: '',
  hour_counter: 0, description: '', notes: '', is_active: true,
})

const saving = ref(false)
const error  = ref('')

const equipmentTypes = [
  'Compresseur', 'Pompe', 'Moteur', 'Convoyeur', 'Robot', 'Automate',
  'Vérin', 'Ventilateur', 'Transformateur', 'Groupe électrogène',
  'Chariot élévateur', 'Pont roulant', 'Machine-outil', 'Autre',
]

const statusLabels = {
  operational:    { label: 'Opérationnel',   class: 'success', icon: '🟢' },
  degraded:       { label: 'Dégradé',         class: 'warning', icon: '🟠' },
  stopped:        { label: 'Arrêté',           class: 'danger',  icon: '🔴' },
  maintenance:    { label: 'En maintenance',   class: 'info',    icon: '🔧' },
  repair:         { label: 'En réparation',    class: 'warning', icon: '🛠️' },
  out_of_service: { label: 'Hors service',     class: 'danger',  icon: '⛔' },
  standby:        { label: 'En veille',        class: '',        icon: '💤' },
}

const criticalityLabels = {
  low:      { label: 'Faible',   color: '#22c55e' },
  medium:   { label: 'Moyenne',  color: '#f59e0b' },
  high:     { label: 'Haute',    color: '#f97316' },
  critical: { label: 'Critique', color: '#ef4444' },
}

// ── Stats ─────────────────────────────────────────────────────────────────────
const stats = computed(() => {
  const all = equipments.value
  return {
    total:       pagination.value.total || all.length,
    operational: all.filter(e => e.status === 'operational').length,
    maintenance: all.filter(e => ['maintenance', 'repair'].includes(e.status)).length,
    stopped:     all.filter(e => ['stopped', 'out_of_service'].includes(e.status)).length,
    critical:    all.filter(e => e.criticality === 'critical').length,
  }
})

// ── Utilitaires ───────────────────────────────────────────────────────────────
function formatDate(date) {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('fr-FR')
}

function formatDateForInput(date) {
  if (!date) return ''
  const d = new Date(date)
  if (isNaN(d.getTime())) return ''
  return d.toISOString().split('T')[0]
}

function formatNumber(num) {
  if (!num && num !== 0) return '0'
  return num.toLocaleString('fr-FR')
}

// ── Fetch ─────────────────────────────────────────────────────────────────────
async function fetchEquipments(page = 1) {
  loading.value = true
  try {
    const params = { page, per_page: 15 }
    if (search.value)       params.search      = search.value
    if (statusFilter.value) params.status      = statusFilter.value
    if (typeFilter.value)   params.type        = typeFilter.value
    if (critFilter.value)   params.criticality = critFilter.value
    if (catFilter.value)    params.category    = catFilter.value
    if (locFilter.value)    params.location_id = locFilter.value

    const response = await api.get('/equipments', { params })
    equipments.value = response.data.data
    pagination.value = {
      current_page: response.data.current_page,
      last_page:    response.data.last_page,
      total:        response.data.total,
    }
  } catch (err) {
    console.error('Erreur fetchEquipments:', err)
  } finally {
    loading.value = false
  }
}

async function fetchLocations() {
  try {
    const response = await api.get('/locations-list')
    locations.value = response.data
  } catch (err) {
    console.error('Erreur locations:', err)
  }
}

// ── Export Excel ──────────────────────────────────────────────────────────────
async function exportEquipments() {
  exporting.value = true
  try {
    const params = new URLSearchParams()
    if (search.value)       params.append('search',      search.value)
    if (statusFilter.value) params.append('status',      statusFilter.value)
    if (typeFilter.value)   params.append('type',        typeFilter.value)
    if (critFilter.value)   params.append('criticality', critFilter.value)
    if (catFilter.value)    params.append('category',    catFilter.value)
    if (locFilter.value)    params.append('location_id', locFilter.value)

    const downloadUrl = `${api.defaults.baseURL}/equipments/export?${params.toString()}`
    window.location.href = downloadUrl
  } catch (err) {
    alert('Erreur lors de l\'exportation : ' + (err.message || ''))
  } finally {
    exporting.value = false
  }
}

// ── Import Excel / CSV ────────────────────────────────────────────────────────
function openImportModal() {
  showImport.value  = true
  importFile.value  = null
  importResult.value = null
  importError.value  = ''
}

function closeImportModal() {
  showImport.value  = false
  importFile.value  = null
  importResult.value = null
  importError.value  = ''
}

function handleImportFile(event) {
  importFile.value = event.target.files?.[0] || null
}

async function importEquipments() {
  if (!importFile.value) {
    importError.value = 'Veuillez sélectionner un fichier.'
    return
  }

  importing.value   = true
  importError.value  = ''
  importResult.value = null

  try {
    const formData = new FormData()
    formData.append('file', importFile.value)

    const response = await api.post('/equipments/import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })

    importResult.value = response.data
    fetchEquipments()
  } catch (err) {
    importError.value = err.response?.data?.message || 'Erreur lors de l\'importation.'
  } finally {
    importing.value = false
  }
}

// ── Modal Création / Édition ──────────────────────────────────────────────────
function openModal(eq = null) {
  editingEq.value = eq
  fetchLocations()

  if (eq) {
    form.value = {
      code:                 eq.code || '',
      name:                 eq.name || '',
      type:                 eq.type || '',
      category:             eq.category || '',
      brand:                eq.brand || '',
      model:                eq.model || '',
      serial_number:        eq.serial_number || '',
      year:                 eq.year || '',
      status:               eq.status || 'operational',
      location_id:          eq.location_id || '',
      department:           eq.department || '',
      criticality:          eq.criticality || 'medium',
      installation_date:    formatDateForInput(eq.installation_date),
      warranty_expiry_date: formatDateForInput(eq.warranty_expiry_date || eq.warranty_expiry),
      acquisition_date:     formatDateForInput(eq.acquisition_date),
      acquisition_cost:     eq.acquisition_cost || '',
      hour_counter:         eq.hour_counter || 0,
      description:          eq.description || '',
      notes:                eq.notes || '',
      is_active:            eq.is_active !== false,
    }
  } else {
    form.value = {
      code: '', name: '', type: '', category: '', brand: '', model: '',
      serial_number: '', year: new Date().getFullYear(),
      status: 'operational', location_id: '', department: '',
      criticality: 'medium', installation_date: '', warranty_expiry_date: '',
      acquisition_date: '', acquisition_cost: '', hour_counter: 0,
      description: '', notes: '', is_active: true,
    }
  }
  error.value = ''
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  editingEq.value = null
}

function openDetail(eq) {
  selectedEq.value = eq
  showDetail.value = true
}

function closeDetail() {
  showDetail.value = false
  selectedEq.value = null
}

async function saveEquipment() {
  saving.value = true
  error.value  = ''
  try {
    const data = { ...form.value }
    if (!data.location_id)          data.location_id          = null
    if (!data.year)                 data.year                 = null
    if (!data.acquisition_cost)     data.acquisition_cost     = null
    if (!data.installation_date)    data.installation_date    = null
    if (!data.warranty_expiry_date) data.warranty_expiry_date = null
    if (!data.acquisition_date)     data.acquisition_date     = null

    if (editingEq.value) {
      await api.put(`/equipments/${editingEq.value.id}`, data)
    } else {
      await api.post('/equipments', data)
    }
    closeModal()
    fetchEquipments()
  } catch (err) {
    error.value = err.response?.data?.message || 'Erreur lors de la sauvegarde'
  } finally {
    saving.value = false
  }
}

async function deleteEquipment(eq) {
  if (!confirm(`Supprimer l'équipement "${eq.name}" ?`)) return
  try {
    await api.delete(`/equipments/${eq.id}`)
    fetchEquipments()
  } catch (err) {
    alert('Erreur lors de la suppression')
  }
}

async function changeStatus(eq, newStatus) {
  try {
    await api.post(`/equipments/${eq.id}/change-status`, { status: newStatus })
    fetchEquipments()
  } catch (err) {
    alert('Erreur lors de la mise à jour du statut')
  }
}

function applyFilters() { fetchEquipments() }

function resetFilters() {
  search.value = ''
  statusFilter.value = ''
  typeFilter.value   = ''
  critFilter.value   = ''
  catFilter.value    = ''
  locFilter.value    = ''
  fetchEquipments()
}

onMounted(() => {
  fetchEquipments()
  fetchLocations()
})
</script>

<template>
  <div class="equipments-page">

    <!-- ── En-tête ──────────────────────────────────────────────────────── -->
    <header class="page-header">
      <div>
        <h1>⚙️ Équipements</h1>
        <p class="subtitle">Gestion du parc d'équipements</p>
      </div>
      <div class="header-actions">
        <button class="btn btn-secondary" @click="openImportModal()">
          📥 Importer
        </button>
        <button class="btn btn-secondary" :disabled="exporting" @click="exportEquipments()">
          {{ exporting ? 'Export en cours...' : '📤 Exporter Excel' }}
        </button>
        <button class="btn btn-primary" @click="openModal()">
          + Nouvel équipement
        </button>
      </div>
    </header>

    <!-- ── Stats ────────────────────────────────────────────────────────── -->
    <div class="stats-cards">
      <div class="stat-card" @click="statusFilter = ''; applyFilters()">
        <div class="stat-icon blue">⚙️</div>
        <div class="stat-content">
          <div class="stat-value">{{ stats.total }}</div>
          <div class="stat-label">Total</div>
        </div>
      </div>
      <div class="stat-card success" @click="statusFilter = 'operational'; applyFilters()">
        <div class="stat-icon green">🟢</div>
        <div class="stat-content">
          <div class="stat-value">{{ stats.operational }}</div>
          <div class="stat-label">Opérationnels</div>
        </div>
      </div>
      <div class="stat-card info" @click="statusFilter = 'maintenance'; applyFilters()">
        <div class="stat-icon cyan">🔧</div>
        <div class="stat-content">
          <div class="stat-value">{{ stats.maintenance }}</div>
          <div class="stat-label">En maintenance</div>
        </div>
      </div>
      <div class="stat-card danger" @click="statusFilter = 'stopped'; applyFilters()">
        <div class="stat-icon red">🔴</div>
        <div class="stat-content">
          <div class="stat-value">{{ stats.stopped }}</div>
          <div class="stat-label">Arrêtés</div>
        </div>
      </div>
      <div class="stat-card crit" @click="critFilter = 'critical'; applyFilters()">
        <div class="stat-icon orange">⚠️</div>
        <div class="stat-content">
          <div class="stat-value">{{ stats.critical }}</div>
          <div class="stat-label">Critiques</div>
        </div>
      </div>
    </div>

    <!-- ── Filtres ───────────────────────────────────────────────────────── -->
    <div class="filters-bar">
      <div class="search-box">
        <span class="search-icon">🔍</span>
        <input
          type="text"
          v-model="search"
          placeholder="Rechercher par code, nom, marque..."
          @input="applyFilters"
        />
      </div>
      <select v-model="statusFilter" @change="applyFilters">
        <option value="">Tous les statuts</option>
        <option v-for="(v, k) in statusLabels" :key="k" :value="k">{{ v.icon }} {{ v.label }}</option>
      </select>
      <select v-model="typeFilter" @change="applyFilters">
        <option value="">Tous les types</option>
        <option v-for="t in equipmentTypes" :key="t" :value="t">{{ t }}</option>
      </select>
      <select v-model="critFilter" @change="applyFilters">
        <option value="">Toutes criticités</option>
        <option v-for="(v, k) in criticalityLabels" :key="k" :value="k">{{ v.label }}</option>
      </select>
      <button
        class="btn btn-secondary btn-sm"
        @click="resetFilters"
        v-if="search || statusFilter || typeFilter || critFilter || catFilter || locFilter"
      >
        ✕ Reset
      </button>
      <div class="view-toggle">
        <button :class="{ active: activeView === 'grid' }"  @click="activeView = 'grid'"  title="Vue grille">▦</button>
        <button :class="{ active: activeView === 'table' }" @click="activeView = 'table'" title="Vue tableau">☰</button>
      </div>
    </div>

    <!-- ── Chargement ───────────────────────────────────────────────────── -->
    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <p>Chargement...</p>
    </div>

    <!-- ── Vue Grille ───────────────────────────────────────────────────── -->
    <div v-else-if="activeView === 'grid'" class="equipments-grid">
      <div
        v-for="eq in equipments"
        :key="eq.id"
        class="eq-card"
        :class="statusLabels[eq.status]?.class"
        @click="openDetail(eq)"
      >
        <div class="eq-header">
          <span class="eq-icon">⚙️</span>
          <div class="eq-info">
            <h3>{{ eq.name }}</h3>
            <span class="eq-code">{{ eq.code }}</span>
          </div>
          <span class="status-badge" :class="statusLabels[eq.status]?.class">
            {{ statusLabels[eq.status]?.icon }} {{ statusLabels[eq.status]?.label }}
          </span>
        </div>

        <div class="eq-specs">
          <div class="spec-item" v-if="eq.type"><span>🏭</span><span>{{ eq.type }}</span></div>
          <div class="spec-item" v-if="eq.brand || eq.model"><span>🔩</span><span>{{ eq.brand }} {{ eq.model }}</span></div>
          <div class="spec-item" v-if="eq.location"><span>📍</span><span>{{ eq.location?.name ?? eq.location }}</span></div>
          <div class="spec-item" v-if="eq.hour_counter"><span>⏱️</span><span>{{ formatNumber(eq.hour_counter) }} h</span></div>
        </div>

        <div class="eq-criticality" v-if="eq.criticality">
          <span
            class="crit-badge"
            :style="{ backgroundColor: criticalityLabels[eq.criticality]?.color + '22', color: criticalityLabels[eq.criticality]?.color }"
          >
            {{ criticalityLabels[eq.criticality]?.label }}
          </span>
        </div>

        <div class="eq-alerts" v-if="eq.alerts?.length > 0">
          <div v-for="(alert, i) in eq.alerts.slice(0, 2)" :key="i" class="mini-alert" :class="alert.type">
            ⚠️ {{ alert.message }}
          </div>
        </div>

        <div class="eq-actions" @click.stop>
          <button class="btn-action" @click="openDetail(eq)" title="Détails">👁️</button>
          <button class="btn-action" @click="openModal(eq)" title="Modifier">✏️</button>
          <div class="status-dropdown">
            <button class="btn-action" title="Changer statut">⚡</button>
            <div class="dropdown-menu">
              <button @click="changeStatus(eq, 'operational')">🟢 Opérationnel</button>
              <button @click="changeStatus(eq, 'maintenance')">🔧 Maintenance</button>
              <button @click="changeStatus(eq, 'stopped')">🔴 Arrêté</button>
              <button @click="changeStatus(eq, 'out_of_service')">⛔ Hors service</button>
              <button @click="changeStatus(eq, 'standby')">💤 Veille</button>
            </div>
          </div>
          <button class="btn-action danger" @click="deleteEquipment(eq)" title="Supprimer">🗑️</button>
        </div>
      </div>

      <div v-if="equipments.length === 0" class="empty-state full-width">
        <span class="empty-icon">⚙️</span>
        <h3>Aucun équipement trouvé</h3>
        <p>Ajoutez des équipements ou modifiez vos filtres</p>
      </div>
    </div>

    <!-- ── Vue Tableau ──────────────────────────────────────────────────── -->
    <div v-else class="table-container">
      <table class="eq-table" v-if="equipments.length">
        <thead>
          <tr>
            <th>Code</th>
            <th>Nom</th>
            <th>Type</th>
            <th>Marque / Modèle</th>
            <th>Emplacement</th>
            <th>Compteur (h)</th>
            <th>Criticité</th>
            <th>Statut</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="eq in equipments" :key="eq.id">
            <td><span class="code-badge">{{ eq.code }}</span></td>
            <td>
              <div class="eq-name-cell">
                <span class="eq-name">{{ eq.name }}</span>
                <span class="eq-cat" v-if="eq.category">{{ eq.category }}</span>
              </div>
            </td>
            <td>
              <span class="type-badge" v-if="eq.type">{{ eq.type }}</span>
              <span v-else class="text-muted">-</span>
            </td>
            <td>
              <span v-if="eq.brand || eq.model">{{ eq.brand }} {{ eq.model }}</span>
              <span v-else class="text-muted">-</span>
            </td>
            <td>
              <span v-if="eq.location">{{ eq.location?.name ?? eq.location }}</span>
              <span v-else class="text-muted">-</span>
            </td>
            <td>
              <span v-if="eq.hour_counter" class="counter-value">{{ formatNumber(eq.hour_counter) }} h</span>
              <span v-else class="text-muted">-</span>
            </td>
            <td>
              <span
                v-if="eq.criticality"
                class="crit-badge"
                :style="{ backgroundColor: criticalityLabels[eq.criticality]?.color + '22', color: criticalityLabels[eq.criticality]?.color }"
              >
                {{ criticalityLabels[eq.criticality]?.label }}
              </span>
              <span v-else class="text-muted">-</span>
            </td>
            <td>
              <span class="status-badge" :class="statusLabels[eq.status]?.class">
                {{ statusLabels[eq.status]?.icon }} {{ statusLabels[eq.status]?.label }}
              </span>
            </td>
            <td class="actions-cell">
              <div class="action-buttons">
                <button class="btn-icon primary" @click="openDetail(eq)" title="Détails">👁️</button>
                <button class="btn-icon warning" @click="openModal(eq)"  title="Modifier">✏️</button>
                <button class="btn-icon danger"  @click="deleteEquipment(eq)" title="Supprimer">🗑️</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-else class="empty-state">
        <span class="empty-icon">⚙️</span>
        <h3>Aucun équipement trouvé</h3>
      </div>
    </div>

    <!-- ── Pagination ───────────────────────────────────────────────────── -->
    <div class="pagination" v-if="pagination.last_page > 1">
      <button
        v-for="page in pagination.last_page"
        :key="page"
        :class="{ active: page === pagination.current_page }"
        @click="fetchEquipments(page)"
      >
        {{ page }}
      </button>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <!-- Modal Import                                                        -->
    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <div class="modal-overlay" v-if="showImport" @click.self="closeImportModal">
      <div class="modal modal-md">
        <div class="modal-header">
          <h2>📥 Importer des équipements</h2>
          <button class="close-btn" @click="closeImportModal">×</button>
        </div>

        <div class="modal-body">

          <!-- Instructions -->
          <div class="import-info">
            <p>
              Importez un fichier <strong>Excel (.xlsx / .xls)</strong> ou <strong>CSV</strong>
              contenant vos équipements.
            </p>
            <div class="import-rules">
              <div class="rule">
                <span class="rule-icon">✅</span>
                <span>Le champ <strong>nom</strong> est obligatoire</span>
              </div>
              <div class="rule">
                <span class="rule-icon">🔄</span>
                <span>Si un équipement avec le même <strong>code</strong> ou <strong>N° série</strong> existe déjà, il sera mis à jour</span>
              </div>
              <div class="rule">
                <span class="rule-icon">📋</span>
                <span>Colonnes reconnues : code, nom, type, catégorie, marque, modèle, numéro_série, année, statut, criticité, département, date_installation, date_acquisition, coût_acquisition, garantie, compteur_horaire, description, notes</span>
              </div>
              <div class="rule">
                <span class="rule-icon">💡</span>
                <span>Statuts acceptés : Opérationnel, En maintenance, Arrêté, Hors service, Dégradé, En veille</span>
              </div>
              <div class="rule">
                <span class="rule-icon">💡</span>
                <span>Criticités acceptées : Faible, Moyenne, Haute, Critique</span>
              </div>
            </div>

            <!-- Bouton télécharger modèle -->
            <button class="btn btn-outline btn-sm" @click="exportEquipments()" style="margin-top:10px;">
              📄 Télécharger le fichier actuel comme modèle
            </button>
          </div>

          <!-- Sélection fichier -->
          <div class="form-group" style="margin-top:20px;">
            <label>Fichier Excel / CSV *</label>
            <div class="file-drop-zone" :class="{ 'has-file': importFile }">
              <input
                type="file"
                accept=".xlsx,.xls,.csv"
                @change="handleImportFile"
                id="import-file-input"
                style="display:none"
              />
              <label for="import-file-input" class="file-drop-label">
                <span v-if="!importFile">
                  <span class="drop-icon">📂</span>
                  <span>Cliquez pour sélectionner un fichier</span>
                  <span class="drop-hint">.xlsx, .xls, .csv acceptés</span>
                </span>
                <span v-else class="file-selected">
                  <span class="drop-icon">📊</span>
                  <span>{{ importFile.name }}</span>
                  <span class="drop-hint">{{ (importFile.size / 1024).toFixed(1) }} Ko</span>
                </span>
              </label>
            </div>
          </div>

          <!-- Résultat de l'import -->
          <div class="import-result" v-if="importResult">
            <div class="result-header">✅ Import terminé</div>
            <div class="result-stats">
              <div class="result-stat created">
                <span class="rs-value">{{ importResult.imported }}</span>
                <span class="rs-label">Créés</span>
              </div>
              <div class="result-stat updated">
                <span class="rs-value">{{ importResult.updated }}</span>
                <span class="rs-label">Mis à jour</span>
              </div>
              <div class="result-stat skipped">
                <span class="rs-value">{{ importResult.skipped }}</span>
                <span class="rs-label">Ignorés</span>
              </div>
            </div>
            <div class="result-errors" v-if="importResult.errors?.length">
              <p><strong>Erreurs détectées :</strong></p>
              <ul>
                <li v-for="(err, i) in importResult.errors" :key="i">{{ err }}</li>
              </ul>
            </div>
          </div>

          <div class="form-error" v-if="importError">{{ importError }}</div>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" @click="closeImportModal">
              {{ importResult ? 'Fermer' : 'Annuler' }}
            </button>
            <button
              type="button"
              class="btn btn-primary"
              :disabled="importing || !importFile"
              @click="importEquipments"
              v-if="!importResult"
            >
              {{ importing ? '⏳ Importation...' : '📥 Lancer l\'import' }}
            </button>
            <button
              type="button"
              class="btn btn-secondary"
              @click="importFile = null; importResult = null; importError = ''"
              v-if="importResult"
            >
              Nouvel import
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <!-- Modal Création / Édition                                            -->
    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <div class="modal-overlay" v-if="showModal" @click.self="closeModal">
      <div class="modal modal-lg">
        <div class="modal-header">
          <h2>{{ editingEq ? '✏️ Modifier' : '➕ Nouvel' }} équipement</h2>
          <button class="close-btn" @click="closeModal">×</button>
        </div>

        <form @submit.prevent="saveEquipment" class="modal-body">

          <div class="form-section">
            <h3>Identification</h3>
            <div class="form-row">
              <div class="form-group">
                <label>Code</label>
                <input type="text" v-model="form.code" placeholder="Généré automatiquement" />
              </div>
              <div class="form-group">
                <label>Nom *</label>
                <input type="text" v-model="form.name" required placeholder="Nom de l'équipement" />
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>Type</label>
                <select v-model="form.type">
                  <option value="">Sélectionner</option>
                  <option v-for="t in equipmentTypes" :key="t" :value="t">{{ t }}</option>
                </select>
              </div>
              <div class="form-group">
                <label>Catégorie</label>
                <input type="text" v-model="form.category" placeholder="Ex: Production, Utilités..." />
              </div>
              <div class="form-group">
                <label>Département</label>
                <input type="text" v-model="form.department" placeholder="Ex: Mécanique" />
              </div>
            </div>
          </div>

          <div class="form-section">
            <h3>Caractéristiques</h3>
            <div class="form-row">
              <div class="form-group">
                <label>Marque</label>
                <input type="text" v-model="form.brand" placeholder="Ex: Siemens" />
              </div>
              <div class="form-group">
                <label>Modèle</label>
                <input type="text" v-model="form.model" placeholder="Ex: 1LA7163" />
              </div>
              <div class="form-group">
                <label>N° Série</label>
                <input type="text" v-model="form.serial_number" />
              </div>
              <div class="form-group">
                <label>Année</label>
                <input type="number" v-model="form.year" min="1900" :max="new Date().getFullYear() + 1" />
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>Compteur horaire (h)</label>
                <input type="number" v-model="form.hour_counter" min="0" />
              </div>
              <div class="form-group">
                <label>Emplacement</label>
                <select v-model="form.location_id">
                  <option value="">Aucun</option>
                  <option v-for="loc in locations" :key="loc.id" :value="loc.id">{{ loc.name }}</option>
                </select>
              </div>
            </div>
          </div>

          <div class="form-section">
            <h3>État</h3>
            <div class="form-row">
              <div class="form-group">
                <label>Statut</label>
                <div class="radio-group">
                  <label class="radio-item success" :class="{ selected: form.status === 'operational' }">
                    <input type="radio" v-model="form.status" value="operational" /><span>🟢 Opérationnel</span>
                  </label>
                  <label class="radio-item warning" :class="{ selected: form.status === 'degraded' }">
                    <input type="radio" v-model="form.status" value="degraded" /><span>🟠 Dégradé</span>
                  </label>
                  <label class="radio-item info" :class="{ selected: form.status === 'maintenance' }">
                    <input type="radio" v-model="form.status" value="maintenance" /><span>🔧 Maintenance</span>
                  </label>
                  <label class="radio-item danger" :class="{ selected: form.status === 'stopped' }">
                    <input type="radio" v-model="form.status" value="stopped" /><span>🔴 Arrêté</span>
                  </label>
                  <label class="radio-item danger" :class="{ selected: form.status === 'out_of_service' }">
                    <input type="radio" v-model="form.status" value="out_of_service" /><span>⛔ Hors service</span>
                  </label>
                </div>
              </div>
              <div class="form-group">
                <label>Criticité</label>
                <div class="radio-group">
                  <label class="radio-item" :class="{ selected: form.criticality === 'low' }">
                    <input type="radio" v-model="form.criticality" value="low" /><span style="color:#22c55e">🟢 Faible</span>
                  </label>
                  <label class="radio-item" :class="{ selected: form.criticality === 'medium' }">
                    <input type="radio" v-model="form.criticality" value="medium" /><span style="color:#f59e0b">🟡 Moyenne</span>
                  </label>
                  <label class="radio-item" :class="{ selected: form.criticality === 'high' }">
                    <input type="radio" v-model="form.criticality" value="high" /><span style="color:#f97316">🟠 Haute</span>
                  </label>
                  <label class="radio-item" :class="{ selected: form.criticality === 'critical' }">
                    <input type="radio" v-model="form.criticality" value="critical" /><span style="color:#ef4444">🔴 Critique</span>
                  </label>
                </div>
              </div>
            </div>
          </div>

          <div class="form-section">
            <h3>Dates & Finances</h3>
            <div class="form-row">
              <div class="form-group">
                <label>Date installation</label>
                <input type="date" v-model="form.installation_date" />
              </div>
              <div class="form-group">
                <label>Date acquisition</label>
                <input type="date" v-model="form.acquisition_date" />
              </div>
              <div class="form-group">
                <label>Coût acquisition (DA)</label>
                <input type="number" v-model="form.acquisition_cost" min="0" step="0.01" />
              </div>
              <div class="form-group">
                <label>Expiration garantie</label>
                <input type="date" v-model="form.warranty_expiry_date" />
              </div>
            </div>
          </div>

          <div class="form-group">
            <label>Description</label>
            <textarea v-model="form.description" rows="2" placeholder="Description..."></textarea>
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

    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <!-- Modal Détails                                                       -->
    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <div class="modal-overlay" v-if="showDetail" @click.self="closeDetail">
      <div class="modal modal-lg">
        <div class="modal-header">
          <h2>📋 Détails de l'équipement</h2>
          <button class="close-btn" @click="closeDetail">×</button>
        </div>

        <div class="modal-body" v-if="selectedEq">
          <div class="detail-header">
            <span class="detail-icon">⚙️</span>
            <div class="detail-title">
              <h2>{{ selectedEq.name }}</h2>
              <div class="detail-subtitle">{{ selectedEq.brand }} {{ selectedEq.model }} {{ selectedEq.year || '' }}</div>
              <div class="detail-badges">
                <span class="status-badge" :class="statusLabels[selectedEq.status]?.class">
                  {{ statusLabels[selectedEq.status]?.icon }} {{ statusLabels[selectedEq.status]?.label }}
                </span>
                <span
                  v-if="selectedEq.criticality"
                  class="crit-badge"
                  :style="{ backgroundColor: criticalityLabels[selectedEq.criticality]?.color + '22', color: criticalityLabels[selectedEq.criticality]?.color }"
                >
                  {{ criticalityLabels[selectedEq.criticality]?.label }}
                </span>
                <span class="code-badge-detail">{{ selectedEq.code }}</span>
              </div>
            </div>
            <div class="detail-counter" v-if="selectedEq.hour_counter">
              <div class="counter-big">{{ formatNumber(selectedEq.hour_counter) }}</div>
              <div class="counter-label">heures</div>
            </div>
          </div>

          <div v-if="selectedEq.alerts?.length > 0" class="detail-alerts">
            <div v-for="(alert, i) in selectedEq.alerts" :key="i" class="detail-alert" :class="alert.type">
              ⚠️ {{ alert.message }}
            </div>
          </div>

          <div class="detail-grid">
            <div class="detail-section">
              <h4>Identification</h4>
              <div class="detail-row"><span class="detail-label">Code</span><span class="detail-value">{{ selectedEq.code }}</span></div>
              <div class="detail-row"><span class="detail-label">Type</span><span class="detail-value">{{ selectedEq.type || '-' }}</span></div>
              <div class="detail-row"><span class="detail-label">Catégorie</span><span class="detail-value">{{ selectedEq.category || '-' }}</span></div>
              <div class="detail-row"><span class="detail-label">N° Série</span><span class="detail-value">{{ selectedEq.serial_number || '-' }}</span></div>
              <div class="detail-row"><span class="detail-label">Département</span><span class="detail-value">{{ selectedEq.department || '-' }}</span></div>
              <div class="detail-row"><span class="detail-label">Emplacement</span><span class="detail-value">{{ selectedEq.location?.name ?? selectedEq.location ?? '-' }}</span></div>
            </div>

            <div class="detail-section">
              <h4>Dates & Finances</h4>
              <div class="detail-row"><span class="detail-label">Installation</span><span class="detail-value">{{ formatDate(selectedEq.installation_date) }}</span></div>
              <div class="detail-row"><span class="detail-label">Acquisition</span><span class="detail-value">{{ formatDate(selectedEq.acquisition_date) }}</span></div>
              <div class="detail-row">
                <span class="detail-label">Coût acquisition</span>
                <span class="detail-value">{{ selectedEq.acquisition_cost ? formatNumber(selectedEq.acquisition_cost) + ' DA' : '-' }}</span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Garantie expire</span>
                <span class="detail-value">{{ formatDate(selectedEq.warranty_expiry_date || selectedEq.warranty_expiry) }}</span>
              </div>
              <div class="detail-row"><span class="detail-label">Compteur horaire</span><span class="detail-value">{{ formatNumber(selectedEq.hour_counter) }} h</span></div>
            </div>

            <div class="detail-section full-width" v-if="selectedEq.description">
              <h4>Description</h4>
              <p class="detail-text">{{ selectedEq.description }}</p>
            </div>

            <div class="detail-section full-width" v-if="selectedEq.notes">
              <h4>Notes</h4>
              <p class="detail-text">{{ selectedEq.notes }}</p>
            </div>
          </div>

          <div class="detail-actions">
            <button class="btn btn-warning" @click="openModal(selectedEq); closeDetail()">✏️ Modifier</button>
            <button
              class="btn btn-info"
              @click="changeStatus(selectedEq, 'maintenance'); closeDetail()"
              v-if="selectedEq.status === 'operational'"
            >
              🔧 Mettre en maintenance
            </button>
            <button
              class="btn btn-success"
              @click="changeStatus(selectedEq, 'operational'); closeDetail()"
              v-if="selectedEq.status === 'maintenance'"
            >
              🟢 Remettre opérationnel
            </button>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<style scoped>
.equipments-page { padding: 30px; }

.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
.page-header h1 { font-size: 28px; color: #2c3e50; margin: 0; }
.subtitle { color: #7f8c8d; margin: 5px 0 0; }
.header-actions { display: flex; gap: 10px; }

.btn { padding: 10px 20px; border: none; border-radius: 8px; font-weight: 500; cursor: pointer; transition: all 0.2s; }
.btn-primary   { background: linear-gradient(135deg, #3498db, #2980b9); color: white; }
.btn-secondary { background: #ecf0f1; color: #2c3e50; }
.btn-success   { background: linear-gradient(135deg, #27ae60, #1e8449); color: white; }
.btn-warning   { background: linear-gradient(135deg, #f39c12, #d68910); color: white; }
.btn-info      { background: linear-gradient(135deg, #3498db, #2980b9); color: white; }
.btn-outline   { background: transparent; border: 1px solid #3498db; color: #3498db; }
.btn-sm        { padding: 6px 12px; font-size: 12px; }
.btn:disabled  { opacity: 0.6; cursor: not-allowed; }

/* Stats */
.stats-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 25px; }
.stat-card { background: white; border-radius: 12px; padding: 18px; display: flex; align-items: center; gap: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); cursor: pointer; transition: all 0.2s; border-left: 4px solid transparent; }
.stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
.stat-card.success { border-left-color: #27ae60; }
.stat-card.info    { border-left-color: #3498db; }
.stat-card.danger  { border-left-color: #e74c3c; }
.stat-card.crit    { border-left-color: #f39c12; }
.stat-icon { width: 45px; height: 45px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 22px; }
.stat-icon.blue   { background: #e8f4fd; }
.stat-icon.green  { background: #d4edda; }
.stat-icon.cyan   { background: #d1ecf1; }
.stat-icon.red    { background: #f8d7da; }
.stat-icon.orange { background: #fff3cd; }
.stat-value { font-size: 26px; font-weight: 700; color: #2c3e50; }
.stat-label { font-size: 12px; color: #7f8c8d; }

/* Filters */
.filters-bar { display: flex; gap: 12px; margin-bottom: 25px; flex-wrap: wrap; align-items: center; }
.search-box { flex: 1; min-width: 250px; max-width: 350px; position: relative; }
.search-box input { width: 100%; padding: 10px 15px 10px 40px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; box-sizing: border-box; }
.search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); }
.filters-bar select { padding: 10px 15px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; min-width: 140px; }
.view-toggle { display: flex; background: white; border-radius: 8px; overflow: hidden; border: 1px solid #ddd; margin-left: auto; }
.view-toggle button { padding: 8px 12px; border: none; background: transparent; cursor: pointer; font-size: 16px; }
.view-toggle button.active { background: #3498db; color: white; }

/* Grid */
.equipments-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); gap: 20px; }
.eq-card { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); transition: all 0.2s; cursor: pointer; border-top: 4px solid #27ae60; }
.eq-card:hover { box-shadow: 0 8px 25px rgba(0,0,0,0.12); transform: translateY(-3px); }
.eq-card.success { border-top-color: #27ae60; }
.eq-card.warning  { border-top-color: #f39c12; }
.eq-card.info     { border-top-color: #3498db; }
.eq-card.danger   { border-top-color: #e74c3c; }
.eq-header { display: flex; align-items: center; gap: 12px; margin-bottom: 15px; }
.eq-icon { font-size: 36px; }
.eq-info { flex: 1; }
.eq-info h3 { margin: 0; font-size: 17px; color: #2c3e50; }
.eq-code { font-size: 12px; color: #7f8c8d; font-family: monospace; }
.eq-specs { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
.spec-item { display: flex; align-items: center; gap: 5px; font-size: 12px; color: #555; background: #f8f9fa; padding: 5px 10px; border-radius: 6px; }
.eq-criticality { margin-bottom: 10px; }
.crit-badge { padding: 4px 12px; border-radius: 15px; font-size: 11px; font-weight: 600; }
.eq-alerts { margin-bottom: 12px; }
.mini-alert { padding: 6px 10px; border-radius: 6px; font-size: 11px; margin-bottom: 4px; }
.mini-alert.danger  { background: #f8d7da; color: #721c24; }
.mini-alert.warning { background: #fff3cd; color: #856404; }

.status-badge { display: inline-flex; align-items: center; gap: 4px; padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 500; }
.status-badge.success { background: #d4edda; color: #155724; }
.status-badge.info    { background: #d1ecf1; color: #0c5460; }
.status-badge.warning { background: #fff3cd; color: #856404; }
.status-badge.danger  { background: #f8d7da; color: #721c24; }

.eq-actions { display: flex; gap: 8px; justify-content: flex-end; border-top: 1px solid #eee; padding-top: 15px; }
.btn-action { width: 36px; height: 36px; border: none; border-radius: 8px; background: #f8f9fa; cursor: pointer; font-size: 16px; transition: all 0.2s; }
.btn-action:hover { background: #e9ecef; }
.btn-action.danger:hover { background: #fee; }
.status-dropdown { position: relative; }
.status-dropdown .dropdown-menu { display: none; position: absolute; bottom: 100%; right: 0; background: white; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.15); padding: 5px; margin-bottom: 5px; z-index: 100; }
.status-dropdown:hover .dropdown-menu { display: block; }
.dropdown-menu button { display: block; width: 100%; padding: 8px 15px; border: none; background: none; text-align: left; cursor: pointer; white-space: nowrap; border-radius: 4px; font-size: 13px; }
.dropdown-menu button:hover { background: #f0f0f0; }

/* Table */
.table-container { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
.eq-table { width: 100%; border-collapse: collapse; }
.eq-table th { text-align: left; padding: 15px; background: #f8f9fa; font-size: 12px; text-transform: uppercase; color: #7f8c8d; font-weight: 600; }
.eq-table td { padding: 15px; border-top: 1px solid #eee; vertical-align: middle; }
.eq-table tr:hover { background: #f8f9fa; }
.eq-name-cell { display: flex; flex-direction: column; }
.eq-name { font-weight: 500; color: #2c3e50; }
.eq-cat  { font-size: 11px; color: #7f8c8d; }
.type-badge { padding: 4px 10px; background: #e8f4fd; color: #3498db; border-radius: 15px; font-size: 11px; }
.code-badge { background: #f0f4f8; color: #4a5568; padding: 4px 10px; border-radius: 15px; font-size: 12px; font-family: monospace; }
.counter-value { font-weight: 600; color: #2c3e50; }
.text-muted { color: #95a5a6; }
.actions-cell { white-space: nowrap; }
.action-buttons { display: flex; gap: 5px; }
.btn-icon { width: 32px; height: 32px; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; background: #f0f0f0; }
.btn-icon.primary { background: #e8f4fd; }
.btn-icon.warning { background: #fff3cd; }
.btn-icon.danger  { background: #f8d7da; }

/* Empty & Loading */
.empty-state { text-align: center; padding: 60px; color: #7f8c8d; }
.empty-state.full-width { grid-column: 1 / -1; background: white; border-radius: 12px; }
.empty-icon { font-size: 60px; opacity: 0.5; display: block; margin-bottom: 10px; }
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
.modal-lg { max-width: 780px; }
.modal-md { max-width: 580px; }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 20px; border-bottom: 1px solid #eee; }
.modal-header h2 { margin: 0; font-size: 18px; color: #2c3e50; }
.close-btn { background: none; border: none; font-size: 24px; cursor: pointer; color: #7f8c8d; }
.modal-body { padding: 20px; overflow-y: auto; }
.modal-footer { display: flex; justify-content: flex-end; gap: 10px; padding-top: 15px; border-top: 1px solid #eee; margin-top: 10px; }

/* Import Modal */
.import-info { background: #f8f9fa; border-radius: 10px; padding: 15px; }
.import-info p { margin: 0 0 12px; font-size: 14px; color: #555; }
.import-rules { display: flex; flex-direction: column; gap: 8px; }
.rule { display: flex; align-items: flex-start; gap: 8px; font-size: 13px; color: #555; }
.rule-icon { font-size: 14px; flex-shrink: 0; margin-top: 1px; }

.file-drop-zone { border: 2px dashed #ddd; border-radius: 10px; transition: all 0.2s; }
.file-drop-zone.has-file { border-color: #3498db; background: #f0f8ff; }
.file-drop-label { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 30px; cursor: pointer; gap: 6px; }
.drop-icon { font-size: 32px; }
.file-drop-label span { font-size: 14px; color: #555; }
.drop-hint { font-size: 11px; color: #999; }
.file-selected { display: flex; flex-direction: column; align-items: center; gap: 6px; }
.file-selected span { color: #3498db; }

.import-result { background: #f0fdf4; border: 1px solid #86efac; border-radius: 10px; padding: 15px; margin-top: 15px; }
.result-header { font-weight: 600; color: #16a34a; margin-bottom: 12px; font-size: 15px; }
.result-stats { display: flex; gap: 15px; margin-bottom: 10px; }
.result-stat { display: flex; flex-direction: column; align-items: center; padding: 10px 20px; background: white; border-radius: 8px; min-width: 80px; }
.result-stat.created { border-top: 3px solid #27ae60; }
.result-stat.updated { border-top: 3px solid #3498db; }
.result-stat.skipped { border-top: 3px solid #f39c12; }
.rs-value { font-size: 22px; font-weight: 700; color: #2c3e50; }
.rs-label { font-size: 11px; color: #7f8c8d; }
.result-errors { margin-top: 10px; font-size: 13px; color: #721c24; }
.result-errors ul { margin: 5px 0 0 18px; padding: 0; }
.result-errors li { margin-bottom: 4px; }

/* Form Sections */
.form-section { margin-bottom: 25px; }
.form-section h3 { font-size: 13px; color: #7f8c8d; text-transform: uppercase; margin: 0 0 15px; padding-bottom: 8px; border-bottom: 1px solid #eee; }
.form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 15px; }
.form-group { margin-bottom: 15px; }
.form-group label { display: block; margin-bottom: 5px; font-size: 13px; font-weight: 500; color: #555; }
.form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; box-sizing: border-box; }
.form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #3498db; }
.radio-group { display: flex; flex-wrap: wrap; gap: 8px; }
.radio-item { display: flex; align-items: center; gap: 6px; padding: 7px 12px; border: 2px solid #ddd; border-radius: 8px; cursor: pointer; font-size: 13px; }
.radio-item input { display: none; }
.radio-item.selected { border-color: #3498db; background: #e8f4fd; }
.radio-item.selected.success { border-color: #27ae60; background: #d4edda; }
.radio-item.selected.warning { border-color: #f39c12; background: #fff3cd; }
.radio-item.selected.info    { border-color: #3498db; background: #d1ecf1; }
.radio-item.selected.danger  { border-color: #e74c3c; background: #f8d7da; }
.form-error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 6px; margin-bottom: 15px; }

/* Detail Modal */
.detail-header { display: flex; align-items: center; gap: 20px; padding: 20px; background: #f8f9fa; border-radius: 12px; margin-bottom: 20px; }
.detail-icon { font-size: 56px; }
.detail-title { flex: 1; }
.detail-title h2 { margin: 0 0 5px; font-size: 22px; color: #2c3e50; }
.detail-subtitle { font-size: 14px; color: #7f8c8d; margin-bottom: 10px; }
.detail-badges { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
.code-badge-detail { background: #e9ecef; color: #6c757d; padding: 4px 10px; border-radius: 15px; font-size: 11px; font-family: monospace; }
.detail-counter { text-align: center; padding: 15px 20px; background: white; border-radius: 10px; }
.counter-big { font-size: 26px; font-weight: 700; color: #2c3e50; }
.counter-label { font-size: 11px; color: #7f8c8d; }
.detail-alerts { margin-bottom: 20px; }
.detail-alert { padding: 10px 15px; border-radius: 8px; margin-bottom: 8px; font-size: 13px; }
.detail-alert.danger  { background: #f8d7da; color: #721c24; }
.detail-alert.warning { background: #fff3cd; color: #856404; }
.detail-alert.info    { background: #d1ecf1; color: #0c5460; }
.detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
.detail-section { background: #f8f9fa; padding: 15px; border-radius: 8px; }
.detail-section.full-width { grid-column: 1 / -1; }
.detail-section h4 { margin: 0 0 12px; font-size: 12px; text-transform: uppercase; color: #7f8c8d; }
.detail-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #e9ecef; font-size: 13px; }
.detail-row:last-child { border-bottom: none; }
.detail-label { color: #7f8c8d; }
.detail-value { color: #2c3e50; font-weight: 500; }
.detail-text { margin: 0; font-size: 14px; color: #555; line-height: 1.5; }
.detail-actions { display: flex; gap: 10px; margin-top: 25px; padding-top: 20px; border-top: 1px solid #eee; }

@media (max-width: 768px) {
  .form-row, .detail-grid { grid-template-columns: 1fr; }
  .filters-bar { flex-direction: column; align-items: stretch; }
  .search-box { max-width: 100%; }
  .view-toggle { margin-left: 0; }
  .detail-header { flex-direction: column; text-align: center; }
  .result-stats { flex-wrap: wrap; }
}
</style>