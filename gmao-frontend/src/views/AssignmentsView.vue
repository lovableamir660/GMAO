<script setup>
import { ref, onMounted, computed } from 'vue'
import { useAssignments } from '@/composables/useAssignments'
import AssignmentCard from '@/components/assignments/AssignmentCard.vue'
import AssignmentFilters from '@/components/assignments/AssignmentFilters.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import ToastNotification from '@/components/ToastNotification.vue'
import PaginationBar from '@/components/PaginationBar.vue'

const {
  assignments, activeAssignments, stats, drivers, trucks,
  loading, pagination, error, saving, searchQuery,
  filters, assignForm, unassignForm,
  assignmentReasons, unassignmentReasons,
  computedStats, filteredActiveAssignments, availableTrucks, availableDrivers,
  fetchAssignments, fetchAll, submitAssignment, submitUnassignment, exportCSV,
  resetFilters, resetAssignForm, resetUnassignForm, onTruckSelect,
  getReasonLabel, getReasonIcon, formatDate, formatDateShort, formatNumber, getDurationClass,
} = useAssignments()

const showAssignModal = ref(false)
const showUnassignModal = ref(false)
const showDetailModal = ref(false)
const showConfirmDialog = ref(false)
const selectedAssignment = ref(null)
const activeTab = ref('active')
const activeView = ref('list') // ← BUG FIX : était 'table'

// Toast
const toast = ref({ show: false, message: '', type: 'success' })
function showToast(message, type = 'success') {
  toast.value = { show: true, message, type }
}

// Sort (tri colonnes historique)
const sortField = ref('')
const sortDirection = ref('asc')

const sortedAssignments = computed(() => {
  if (!sortField.value) return assignments.value
  const list = [...assignments.value]
  list.sort((a, b) => {
    let valA, valB
    switch (sortField.value) {
      case 'truck':
        valA = a.truck?.registration_number || ''
        valB = b.truck?.registration_number || ''
        break
      case 'driver':
        valA = `${a.driver?.first_name} ${a.driver?.last_name}`
        valB = `${b.driver?.first_name} ${b.driver?.last_name}`
        break
      case 'start':
        valA = a.assigned_at || ''
        valB = b.assigned_at || ''
        break
      case 'end':
        valA = a.unassigned_at || ''
        valB = b.unassigned_at || ''
        break
      case 'distance':
        valA = a.distance || 0
        valB = b.distance || 0
        break
      default:
        return 0
    }
    if (typeof valA === 'string') {
      return sortDirection.value === 'asc' ? valA.localeCompare(valB) : valB.localeCompare(valA)
    }
    return sortDirection.value === 'asc' ? valA - valB : valB - valA
  })
  return list
})

function toggleSort(field) {
  if (sortField.value === field) {
    sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortField.value = field
    sortDirection.value = 'asc'
  }
}

function sortIcon(field) {
  if (sortField.value !== field) return '↕️'
  return sortDirection.value === 'asc' ? '↑' : '↓'
}

// Filtres actifs ?
const hasActiveFilters = computed(() => Object.values(filters.value).some(v => v))

// Modals
function openAssignModal() {
  resetAssignForm()
  showAssignModal.value = true
}

function closeAssignModal() {
  showAssignModal.value = false
}

async function handleSubmitAssignment() {
  const success = await submitAssignment()
  if (success) {
    closeAssignModal()
    showToast('Attribution créée avec succès !')
  }
}

function openUnassignModal(assignment) {
  selectedAssignment.value = assignment
  resetUnassignForm(assignment)
  showUnassignModal.value = true
}

function closeUnassignModal() {
  showUnassignModal.value = false
  selectedAssignment.value = null
}

// Confirmation avant désattribution
function confirmUnassignment() {
  showUnassignModal.value = false
  showConfirmDialog.value = true
}

async function handleConfirmedUnassignment() {
  showConfirmDialog.value = false
  const success = await submitUnassignment(selectedAssignment.value.id)
  if (success) {
    selectedAssignment.value = null
    showToast('Attribution terminée avec succès !')
  } else {
    showUnassignModal.value = true
  }
}

function openDetailModal(assignment) {
  selectedAssignment.value = assignment
  showDetailModal.value = true
}

function closeDetailModal() {
  showDetailModal.value = false
  selectedAssignment.value = null
}

// Lazy load stats
function onTabChange(tab) {
  activeTab.value = tab
}

onMounted(() => fetchAll())
</script>

<template>
  <div class="assignments-page">
    <!-- Toast -->
    <ToastNotification
      :show="toast.show"
      :message="toast.message"
      :type="toast.type"
      @close="toast.show = false"
    />

    <!-- Confirm Dialog -->
    <ConfirmDialog
      :show="showConfirmDialog"
      title="Terminer l'attribution ?"
      :message="`Voulez-vous vraiment terminer l'attribution du camion ${selectedAssignment?.truck?.registration_number} au chauffeur ${selectedAssignment?.driver?.first_name} ${selectedAssignment?.driver?.last_name} ?`"
      confirmText="⏹️ Terminer"
      @confirm="handleConfirmedUnassignment"
      @cancel="showConfirmDialog = false"
    />

    <header class="page-header">
      <div>
        <h1>🔄 Attributions</h1>
        <p class="subtitle">Gestion des affectations camions / chauffeurs</p>
      </div>
      <button class="btn btn-primary" @click="openAssignModal">
        + Nouvelle attribution
      </button>
    </header>

    <!-- Erreur globale -->
    <div class="global-error" v-if="error && !showAssignModal && !showUnassignModal">
      <span>⚠️ {{ error }}</span>
      <button @click="error = ''">×</button>
    </div>

    <!-- Stats Cards -->
    <div class="stats-cards" v-if="computedStats">
      <div class="stat-card active" @click="onTabChange('active')">
        <div class="stat-icon green">🔄</div>
        <div class="stat-content">
          <div class="stat-value">{{ computedStats.active_assignments || activeAssignments.length }}</div>
          <div class="stat-label">Actives</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon blue">🚛</div>
        <div class="stat-content">
          <div class="stat-value">{{ computedStats.trucksAvailable }}</div>
          <div class="stat-label">Camions dispos</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon purple">👷</div>
        <div class="stat-content">
          <div class="stat-value">{{ computedStats.driversAvailable }}</div>
          <div class="stat-label">Chauffeurs dispos</div>
        </div>
      </div>
      <div class="stat-card" @click="onTabChange('history')">
        <div class="stat-icon gray">📊</div>
        <div class="stat-content">
          <div class="stat-value">{{ computedStats.total_assignments || 0 }}</div>
          <div class="stat-label">Total historique</div>
        </div>
      </div>
      <div class="stat-card info">
        <div class="stat-icon cyan">🛣️</div>
        <div class="stat-content">
          <div class="stat-value">{{ formatNumber(computedStats.total_distance) }}</div>
          <div class="stat-label">Km parcourus</div>
        </div>
      </div>
    </div>

    <!-- Tabs -->
    <div class="tabs-container">
      <div class="tabs">
        <button :class="{ active: activeTab === 'active' }" @click="onTabChange('active')">
          🟢 Actives <span class="tab-count">{{ activeAssignments.length }}</span>
        </button>
        <button :class="{ active: activeTab === 'history' }" @click="onTabChange('history')">
          📜 Historique
        </button>
        <button :class="{ active: activeTab === 'stats' }" @click="onTabChange('stats')">
          📊 Statistiques
        </button>
      </div>

      <!-- Barre de recherche + toggle vue -->
      <div class="tabs-right" v-if="activeTab === 'active'">
        <div class="search-box">
          <span class="search-icon">🔍</span>
          <input
            type="text"
            v-model="searchQuery"
            placeholder="Rechercher camion, chauffeur..."
            aria-label="Rechercher dans les attributions actives"
          />
          <button v-if="searchQuery" class="search-clear" @click="searchQuery = ''">×</button>
        </div>
        <div class="view-toggle">
          <button :class="{ active: activeView === 'grid' }" @click="activeView = 'grid'" title="Vue grille">▦</button>
          <button :class="{ active: activeView === 'list' }" @click="activeView = 'list'" title="Vue liste">☰</button>
        </div>
      </div>
    </div>

    <!-- ========== ONGLET ACTIVES ========== -->
    <div v-if="activeTab === 'active'" class="active-section">
      <div v-if="filteredActiveAssignments.length === 0 && !searchQuery" class="empty-state">
        <span class="empty-icon">🚛</span>
        <h3>Aucune attribution active</h3>
        <p>Créez une nouvelle attribution pour commencer</p>
        <button class="btn btn-primary" @click="openAssignModal">+ Nouvelle attribution</button>
      </div>

      <div v-else-if="filteredActiveAssignments.length === 0 && searchQuery" class="empty-state">
        <span class="empty-icon">🔍</span>
        <h3>Aucun résultat</h3>
        <p>Aucune attribution ne correspond à « {{ searchQuery }} »</p>
        <button class="btn btn-secondary" @click="searchQuery = ''">Effacer la recherche</button>
      </div>

      <!-- Grid View -->
      <div v-else-if="activeView === 'grid'" class="assignments-grid">
        <AssignmentCard
          v-for="assignment in filteredActiveAssignments"
          :key="assignment.id"
          :assignment="assignment"
          :formatDateShort="formatDateShort"
          :formatNumber="formatNumber"
          :getReasonIcon="getReasonIcon"
          :getReasonLabel="getReasonLabel"
          :getDurationClass="getDurationClass"
          @detail="openDetailModal"
          @unassign="openUnassignModal"
        />
      </div>

      <!-- List View -->
      <div v-else class="assignments-list">
        <div
          v-for="assignment in filteredActiveAssignments"
          :key="assignment.id"
          class="assignment-row"
          @click="openDetailModal(assignment)"
        >
          <div class="row-status"><span class="status-dot active"></span></div>
          <div class="row-truck">
            <span class="row-icon">🚛</span>
            <div class="row-info">
              <div class="row-primary">{{ assignment.truck?.registration_number }}</div>
              <div class="row-secondary">{{ assignment.truck?.brand }}</div>
            </div>
          </div>
          <div class="row-connector">↔️</div>
          <div class="row-driver">
            <span class="row-icon">👷</span>
            <div class="row-info">
              <div class="row-primary">{{ assignment.driver?.first_name }} {{ assignment.driver?.last_name }}</div>
              <div class="row-secondary">{{ assignment.driver?.code }}</div>
            </div>
          </div>
          <div class="row-date">
            <div class="row-primary">{{ formatDateShort(assignment.assigned_at) }}</div>
            <div class="row-secondary">{{ assignment.duration || "Aujourd'hui" }}</div>
          </div>
          <div class="row-mileage">
            <span class="mileage-badge">{{ formatNumber(assignment.start_mileage) }} km</span>
          </div>
          <div class="row-reason">
            <span class="reason-badge">
              {{ getReasonIcon(assignment.assignment_reason) }} {{ getReasonLabel(assignment.assignment_reason) }}
            </span>
          </div>
          <div class="row-actions" @click.stop>
            <button class="btn-icon danger" @click="openUnassignModal(assignment)" title="Terminer">⏹️</button>
          </div>
        </div>
      </div>
    </div>

    <!-- ========== ONGLET HISTORIQUE ========== -->
    <div v-if="activeTab === 'history'" class="history-section">
      <AssignmentFilters
        :filters="filters"
        :trucks="trucks"
        :drivers="drivers"
        :assignmentReasons="assignmentReasons"
        :hasActiveFilters="hasActiveFilters"
        @apply="fetchAssignments()"
        @reset="resetFilters"
        @export="exportCSV"
      />

      <div class="table-container" v-if="!loading">
        <table class="history-table" v-if="sortedAssignments.length">
          <thead>
            <tr>
              <th>Statut</th>
              <th class="sortable" @click="toggleSort('truck')">Camion {{ sortIcon('truck') }}</th>
              <th class="sortable" @click="toggleSort('driver')">Chauffeur {{ sortIcon('driver') }}</th>
              <th class="sortable" @click="toggleSort('start')">Début {{ sortIcon('start') }}</th>
              <th class="sortable" @click="toggleSort('end')">Fin {{ sortIcon('end') }}</th>
              <th>Durée</th>
              <th class="sortable" @click="toggleSort('distance')">Distance {{ sortIcon('distance') }}</th>
              <th>Raison</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="item in sortedAssignments"
              :key="item.id"
              :class="{ active: !item.unassigned_at }"
              @click="openDetailModal(item)"
            >
              <td>
                <span class="status-badge" :class="item.unassigned_at ? 'completed' : 'active'">
                  {{ item.unassigned_at ? '✅ Terminée' : '🟢 En cours' }}
                </span>
              </td>
              <td>
                <div class="cell-entity">
                  <span class="cell-icon">🚛</span>
                  <div>
                    <div class="cell-primary">{{ item.truck?.registration_number }}</div>
                    <div class="cell-secondary">{{ item.truck?.brand }} {{ item.truck?.model }}</div>
                  </div>
                </div>
              </td>
              <td>
                <div class="cell-entity">
                  <span class="cell-icon">👷</span>
                  <div>
                    <div class="cell-primary">{{ item.driver?.first_name }} {{ item.driver?.last_name }}</div>
                    <div class="cell-secondary">{{ item.driver?.code }}</div>
                  </div>
                </div>
              </td>
              <td>
                <div class="cell-date">
                  <div class="cell-primary">{{ formatDateShort(item.assigned_at) }}</div>
                  <div class="cell-secondary">{{ formatNumber(item.start_mileage) }} km</div>
                </div>
              </td>
              <td>
                <div class="cell-date" v-if="item.unassigned_at">
                  <div class="cell-primary">{{ formatDateShort(item.unassigned_at) }}</div>
                  <div class="cell-secondary">{{ formatNumber(item.end_mileage) }} km</div>
                </div>
                <span v-else class="text-muted">-</span>
              </td>
              <td>
                <span class="duration-badge" :class="getDurationClass(item.duration)">
                  {{ item.duration || '-' }}
                </span>
              </td>
              <td>
                <span v-if="item.distance !== null" class="distance-badge">
                  {{ formatNumber(item.distance) }} km
                </span>
                <span v-else class="text-muted">-</span>
              </td>
              <td>
                <span class="reason-tag">
                  {{ getReasonIcon(item.assignment_reason) }}
                  {{ getReasonLabel(item.assignment_reason) }}
                </span>
              </td>
              <td class="actions-cell" @click.stop>
                <div class="action-buttons">
                  <button class="btn-icon primary" @click="openDetailModal(item)" title="Détails">👁️</button>
                  <button
                    v-if="!item.unassigned_at"
                    class="btn-icon danger"
                    @click="openUnassignModal(item)"
                    title="Terminer"
                  >⏹️</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>

        <div v-else class="empty-state">
          <span class="empty-icon">📜</span>
          <h3>Aucun historique trouvé</h3>
          <p>Modifiez vos filtres ou créez une attribution</p>
        </div>
      </div>

      <!-- Skeleton loader -->
      <div v-else class="skeleton-container">
        <div class="skeleton-row" v-for="i in 5" :key="i">
          <div class="skeleton-cell small"></div>
          <div class="skeleton-cell medium"></div>
          <div class="skeleton-cell medium"></div>
          <div class="skeleton-cell small"></div>
          <div class="skeleton-cell small"></div>
          <div class="skeleton-cell tiny"></div>
          <div class="skeleton-cell small"></div>
          <div class="skeleton-cell medium"></div>
          <div class="skeleton-cell tiny"></div>
        </div>
      </div>

      <PaginationBar
        v-if="pagination.last_page > 1"
        :currentPage="pagination.current_page"
        :lastPage="pagination.last_page"
        :total="pagination.total"
        @page-change="fetchAssignments"
      />
    </div>

    <!-- ========== ONGLET STATS ========== -->
    <div v-if="activeTab === 'stats'" class="stats-section">
      <div class="overview-cards">
        <div class="overview-card">
          <div class="overview-icon">📈</div>
          <div class="overview-content">
            <div class="overview-value">{{ formatNumber(stats?.avg_distance_per_assignment) }}</div>
            <div class="overview-label">Km / Attribution (moy)</div>
          </div>
        </div>
        <div class="overview-card">
          <div class="overview-icon">⏱️</div>
          <div class="overview-content">
            <div class="overview-value">{{ stats?.avg_duration || '-' }}</div>
            <div class="overview-label">Durée moyenne</div>
          </div>
        </div>
        <div class="overview-card">
          <div class="overview-icon">📅</div>
          <div class="overview-content">
            <div class="overview-value">{{ stats?.assignments_this_month || 0 }}</div>
            <div class="overview-label">Ce mois</div>
          </div>
        </div>
      </div>

      <div class="stats-grid">
        <!-- Top Chauffeurs -->
        <div class="stats-widget">
          <div class="widget-header">
            <h3>🏆 Top Chauffeurs</h3>
            <span class="widget-subtitle">Par distance parcourue</span>
          </div>
          <div class="ranking-list">
            <div v-for="(driver, index) in stats?.top_drivers" :key="driver.id" class="ranking-item">
              <span class="rank" :class="'rank-' + (index + 1)">{{ index + 1 }}</span>
              <div class="rank-avatar">👷</div>
              <div class="rank-info">
                <div class="rank-name">{{ driver.first_name }} {{ driver.last_name }}</div>
                <div class="rank-detail">{{ driver.assignments_count || 0 }} attributions</div>
              </div>
              <div class="rank-stats">
                <div class="rank-value">{{ formatNumber(driver.total_distance) }}</div>
                <div class="rank-unit">km</div>
              </div>
            </div>
            <div v-if="!stats?.top_drivers?.length" class="empty-ranking"><span>📊</span><p>Pas encore de données</p></div>
          </div>
        </div>

        <!-- Top Camions -->
        <div class="stats-widget">
          <div class="widget-header">
            <h3>🚛 Camions les plus utilisés</h3>
            <span class="widget-subtitle">Par nombre d'attributions</span>
          </div>
          <div class="ranking-list">
            <div v-for="(truck, index) in stats?.top_trucks" :key="truck.id" class="ranking-item">
              <span class="rank" :class="'rank-' + (index + 1)">{{ index + 1 }}</span>
              <div class="rank-avatar truck">🚛</div>
              <div class="rank-info">
                <div class="rank-name">{{ truck.registration_number }}</div>
                <div class="rank-detail">{{ truck.brand }} {{ truck.model }}</div>
              </div>
              <div class="rank-stats">
                <div class="rank-value">{{ truck.assignments_count }}</div>
                <div class="rank-unit">attrib.</div>
              </div>
            </div>
            <div v-if="!stats?.top_trucks?.length" class="empty-ranking"><span>📊</span><p>Pas encore de données</p></div>
          </div>
        </div>

        <!-- Raisons -->
        <div class="stats-widget">
          <div class="widget-header">
            <h3>📋 Raisons d'attribution</h3>
            <span class="widget-subtitle">Répartition</span>
          </div>
          <div class="reasons-list">
            <div v-for="reason in stats?.reasons_breakdown" :key="reason.reason" class="reason-item">
              <div class="reason-info">
                <span class="reason-icon">{{ getReasonIcon(reason.reason) }}</span>
                <span class="reason-name">{{ getReasonLabel(reason.reason) }}</span>
              </div>
              <div class="reason-bar-container">
                <div class="reason-bar" :style="{ width: (stats.total_assignments > 0 ? (reason.count / stats.total_assignments * 100) : 0) + '%' }"></div>
              </div>
              <span class="reason-count">{{ reason.count }}</span>
            </div>
            <div v-if="!stats?.reasons_breakdown?.length" class="empty-ranking"><span>📊</span><p>Pas encore de données</p></div>
          </div>
        </div>

        <!-- Activité récente -->
        <div class="stats-widget">
          <div class="widget-header">
            <h3>📅 Activité récente</h3>
            <span class="widget-subtitle">7 derniers jours</span>
          </div>
          <div class="activity-list">
            <div v-for="day in stats?.daily_activity" :key="day.date" class="activity-item">
              <span class="activity-date">{{ formatDateShort(day.date) }}</span>
              <div class="activity-bar-container">
                <div class="activity-bar" :style="{ width: (Math.max(...stats.daily_activity.map(d => d.count)) > 0 ? (day.count / Math.max(...stats.daily_activity.map(d => d.count)) * 100) : 0) + '%' }"></div>
              </div>
              <span class="activity-count">{{ day.count }}</span>
            </div>
            <div v-if="!stats?.daily_activity?.length" class="empty-ranking"><span>📊</span><p>Pas encore de données</p></div>
          </div>
        </div>
      </div>
    </div>

    <!-- ========== MODAL ATTRIBUTION ========== -->
    <div class="modal-overlay" v-if="showAssignModal" @click.self="closeAssignModal">
      <div class="modal">
        <div class="modal-header">
          <h2>➕ Nouvelle attribution</h2>
          <button class="close-btn" @click="closeAssignModal">×</button>
        </div>
        <form @submit.prevent="handleSubmitAssignment" class="modal-body">
          <div class="availability-summary">
            <div class="avail-item">
              <span class="avail-icon">🚛</span>
              <span class="avail-count">{{ availableTrucks.length }}</span>
              <span class="avail-label">camion(s) disponible(s)</span>
            </div>
            <div class="avail-item">
              <span class="avail-icon">👷</span>
              <span class="avail-count">{{ availableDrivers.length }}</span>
              <span class="avail-label">chauffeur(s) disponible(s)</span>
            </div>
          </div>
          <div class="form-section">
            <h3>Affectation</h3>
            <div class="form-group">
              <label for="assign-truck">🚛 Camion *</label>
              <select id="assign-truck" v-model="assignForm.truck_id" required @change="onTruckSelect">
                <option value="">Sélectionner un camion</option>
                <option v-for="truck in availableTrucks" :key="truck.id" :value="truck.id">
                  {{ truck.registration_number }} - {{ truck.brand }} {{ truck.model }}
                </option>
              </select>
            </div>
            <div class="form-group">
              <label for="assign-driver">👷 Chauffeur *</label>
              <select id="assign-driver" v-model="assignForm.driver_id" required>
                <option value="">Sélectionner un chauffeur</option>
                <option v-for="driver in availableDrivers" :key="driver.id" :value="driver.id">
                  {{ driver.first_name }} {{ driver.last_name }} ({{ driver.code }})
                </option>
              </select>
            </div>
            <div class="form-group">
              <label for="assign-km">🛣️ Kilométrage de départ</label>
              <input id="assign-km" type="number" v-model="assignForm.start_mileage" min="0" placeholder="Km actuels du camion" />
              <small class="form-hint">Automatiquement rempli si le camion a un kilométrage enregistré</small>
            </div>
          </div>
          <div class="form-section">
            <h3>Détails</h3>
            <div class="form-group">
              <label>📋 Raison de l'attribution</label>
              <div class="reason-selector">
                <label
                  v-for="reason in assignmentReasons"
                  :key="reason.value"
                  class="reason-option"
                  :class="{ selected: assignForm.assignment_reason === reason.value }"
                >
                  <input type="radio" v-model="assignForm.assignment_reason" :value="reason.value" />
                  <span class="reason-icon">{{ reason.icon }}</span>
                  <span class="reason-label">{{ reason.label }}</span>
                </label>
              </div>
            </div>
            <div class="form-group">
              <label for="assign-notes">📝 Notes</label>
              <textarea id="assign-notes" v-model="assignForm.notes" rows="2" placeholder="Informations complémentaires..."></textarea>
            </div>
          </div>
          <div class="form-error" v-if="error">{{ error }}</div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" @click="closeAssignModal">Annuler</button>
            <button type="submit" class="btn btn-primary" :disabled="saving || !assignForm.truck_id || !assignForm.driver_id">
              {{ saving ? 'Attribution...' : '🔗 Attribuer' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- ========== MODAL FIN ATTRIBUTION ========== -->
    <div class="modal-overlay" v-if="showUnassignModal" @click.self="closeUnassignModal">
      <div class="modal">
        <div class="modal-header">
          <h2>⏹️ Terminer l'attribution</h2>
          <button class="close-btn" @click="closeUnassignModal">×</button>
        </div>
        <form @submit.prevent="confirmUnassignment" class="modal-body">
          <div class="assignment-summary" v-if="selectedAssignment">
            <div class="summary-header">Attribution en cours</div>
            <div class="summary-pair">
              <div class="summary-entity">
                <span class="entity-icon">🚛</span>
                <span>{{ selectedAssignment.truck?.registration_number }}</span>
              </div>
              <span class="summary-connector">↔️</span>
              <div class="summary-entity">
                <span class="entity-icon">👷</span>
                <span>{{ selectedAssignment.driver?.first_name }} {{ selectedAssignment.driver?.last_name }}</span>
              </div>
            </div>
            <div class="summary-details">
              <div class="summary-item">
                <span class="summary-label">Début</span>
                <span class="summary-value">{{ formatDate(selectedAssignment.assigned_at) }}</span>
              </div>
              <div class="summary-item">
                <span class="summary-label">Km départ</span>
                <span class="summary-value">{{ formatNumber(selectedAssignment.start_mileage) }} km</span>
              </div>
              <div class="summary-item">
                <span class="summary-label">Durée</span>
                <span class="summary-value">{{ selectedAssignment.duration || "Aujourd'hui" }}</span>
              </div>
            </div>
          </div>
          <div class="form-section">
            <h3>Fin de l'attribution</h3>
            <div class="form-group">
              <label for="unassign-km">🛣️ Kilométrage de fin</label>
              <input id="unassign-km" type="number" v-model="unassignForm.end_mileage" :min="selectedAssignment?.start_mileage" placeholder="Km actuels" />
              <div class="mileage-calc" v-if="unassignForm.end_mileage && selectedAssignment?.start_mileage">
                <span class="calc-label">Distance parcourue:</span>
                <span class="calc-value">{{ formatNumber(unassignForm.end_mileage - selectedAssignment.start_mileage) }} km</span>
              </div>
            </div>
            <div class="form-group">
              <label>📋 Raison de fin</label>
              <div class="reason-selector">
                <label
                  v-for="reason in unassignmentReasons"
                  :key="reason.value"
                  class="reason-option"
                  :class="{ selected: unassignForm.unassignment_reason === reason.value }"
                >
                  <input type="radio" v-model="unassignForm.unassignment_reason" :value="reason.value" />
                  <span class="reason-icon">{{ reason.icon }}</span>
                  <span class="reason-label">{{ reason.label }}</span>
                </label>
              </div>
            </div>
            <div class="form-group">
              <label for="unassign-notes">📝 Notes</label>
              <textarea id="unassign-notes" v-model="unassignForm.notes" rows="2" placeholder="Commentaires..."></textarea>
            </div>
          </div>
          <div class="form-error" v-if="error">{{ error }}</div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" @click="closeUnassignModal">Annuler</button>
            <button type="submit" class="btn btn-danger" :disabled="saving">
              {{ saving ? 'Traitement...' : '⏹️ Terminer' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- ========== MODAL DÉTAILS ========== -->
    <div class="modal-overlay" v-if="showDetailModal" @click.self="closeDetailModal">
      <div class="modal modal-lg">
        <div class="modal-header">
          <h2>📋 Détails de l'attribution</h2>
          <button class="close-btn" @click="closeDetailModal">×</button>
        </div>
        <div class="modal-body" v-if="selectedAssignment">
          <div class="detail-status-banner" :class="selectedAssignment.unassigned_at ? 'completed' : 'active'">
            <span class="status-icon">{{ selectedAssignment.unassigned_at ? '✅' : '🟢' }}</span>
            <span class="status-text">{{ selectedAssignment.unassigned_at ? 'Attribution terminée' : 'Attribution en cours' }}</span>
            <span class="status-duration">{{ selectedAssignment.duration || "Aujourd'hui" }}</span>
          </div>
          <div class="detail-pair-section">
            <div class="detail-entity truck">
              <div class="detail-entity-header">
                <span class="detail-entity-icon">🚛</span>
                <span class="detail-entity-label">Camion</span>
              </div>
              <div class="detail-entity-name">{{ selectedAssignment.truck?.registration_number }}</div>
              <div class="detail-entity-info">{{ selectedAssignment.truck?.brand }} {{ selectedAssignment.truck?.model }}</div>
              <div class="detail-entity-code">{{ selectedAssignment.truck?.code }}</div>
            </div>
            <div class="detail-connector">
              <div class="connector-vertical"></div>
              <span class="connector-symbol">🔗</span>
              <div class="connector-vertical"></div>
            </div>
            <div class="detail-entity driver">
              <div class="detail-entity-header">
                <span class="detail-entity-icon">👷</span>
                <span class="detail-entity-label">Chauffeur</span>
              </div>
              <div class="detail-entity-name">{{ selectedAssignment.driver?.first_name }} {{ selectedAssignment.driver?.last_name }}</div>
              <div class="detail-entity-info">{{ selectedAssignment.driver?.phone }}</div>
              <div class="detail-entity-code">{{ selectedAssignment.driver?.code }}</div>
            </div>
          </div>
          <div class="detail-stats-row">
            <div class="detail-stat-item">
              <div class="detail-stat-icon">📅</div>
              <div class="detail-stat-content">
                <div class="detail-stat-label">Début</div>
                <div class="detail-stat-value">{{ formatDate(selectedAssignment.assigned_at) }}</div>
              </div>
            </div>
            <div class="detail-stat-item" v-if="selectedAssignment.unassigned_at">
              <div class="detail-stat-icon">🏁</div>
              <div class="detail-stat-content">
                <div class="detail-stat-label">Fin</div>
                <div class="detail-stat-value">{{ formatDate(selectedAssignment.unassigned_at) }}</div>
              </div>
            </div>
            <div class="detail-stat-item">
              <div class="detail-stat-icon">🛣️</div>
              <div class="detail-stat-content">
                <div class="detail-stat-label">Km départ</div>
                <div class="detail-stat-value">{{ formatNumber(selectedAssignment.start_mileage) }}</div>
              </div>
            </div>
            <div class="detail-stat-item" v-if="selectedAssignment.end_mileage">
              <div class="detail-stat-icon">🛣️</div>
              <div class="detail-stat-content">
                <div class="detail-stat-label">Km fin</div>
                <div class="detail-stat-value">{{ formatNumber(selectedAssignment.end_mileage) }}</div>
              </div>
            </div>
            <div class="detail-stat-item highlight" v-if="selectedAssignment.distance !== null">
              <div class="detail-stat-icon">📏</div>
              <div class="detail-stat-content">
                <div class="detail-stat-label">Distance</div>
                <div class="detail-stat-value">{{ formatNumber(selectedAssignment.distance) }} km</div>
              </div>
            </div>
          </div>
          <div class="detail-info-grid">
            <div class="detail-info-section">
              <h4>📋 Attribution</h4>
              <div class="info-row">
                <span class="info-label">Raison</span>
                <span class="info-value">{{ getReasonIcon(selectedAssignment.assignment_reason) }} {{ getReasonLabel(selectedAssignment.assignment_reason) }}</span>
              </div>
              <div class="info-row" v-if="selectedAssignment.notes">
                <span class="info-label">Notes</span>
                <span class="info-value">{{ selectedAssignment.notes }}</span>
              </div>
            </div>
            <div class="detail-info-section" v-if="selectedAssignment.unassigned_at">
              <h4>🏁 Fin d'attribution</h4>
              <div class="info-row">
                <span class="info-label">Raison</span>
                <span class="info-value">{{ getReasonIcon(selectedAssignment.unassignment_reason, 'unassignment') }} {{ getReasonLabel(selectedAssignment.unassignment_reason, 'unassignment') }}</span>
              </div>
              <div class="info-row" v-if="selectedAssignment.unassignment_notes">
                <span class="info-label">Notes</span>
                <span class="info-value">{{ selectedAssignment.unassignment_notes }}</span>
              </div>
            </div>
          </div>
          <div class="detail-actions" v-if="!selectedAssignment.unassigned_at">
            <button class="btn btn-danger" @click="openUnassignModal(selectedAssignment); closeDetailModal()">
              ⏹️ Terminer l'attribution
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* ===================== */
/* PAGE LAYOUT           */
/* ===================== */
.assignments-page { padding: 30px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
.page-header h1 { font-size: 28px; color: #2c3e50; margin: 0; }
.subtitle { color: #7f8c8d; margin: 5px 0 0; }

/* ===================== */
/* BOUTONS               */
/* ===================== */
.btn { padding: 10px 20px; border: none; border-radius: 8px; font-weight: 500; cursor: pointer; transition: all 0.2s; }
.btn:hover { filter: brightness(1.05); }
.btn-primary { background: linear-gradient(135deg, #3498db, #2980b9); color: white; }
.btn-secondary { background: #ecf0f1; color: #2c3e50; }
.btn-secondary:hover { background: #dde1e3; }
.btn-danger { background: linear-gradient(135deg, #e74c3c, #c0392b); color: white; }
.btn-sm { padding: 6px 12px; font-size: 12px; }

/* ===================== */
/* ERREUR GLOBALE        */
/* ===================== */
.global-error {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 18px;
  background: #fff3cd;
  border: 1px solid #ffc107;
  border-radius: 8px;
  margin-bottom: 20px;
  color: #856404;
  font-size: 14px;
}
.global-error button {
  background: none;
  border: none;
  font-size: 18px;
  cursor: pointer;
  color: #856404;
}

/* ===================== */
/* STATS CARDS           */
/* ===================== */
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
.stat-card.active { border-left-color: #27ae60; }
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
.stat-icon.green { background: #d4edda; }
.stat-icon.blue { background: #e8f4fd; }
.stat-icon.purple { background: #e8daef; }
.stat-icon.gray { background: #e9ecef; }
.stat-icon.cyan { background: #d1ecf1; }
.stat-value { font-size: 26px; font-weight: 700; color: #2c3e50; }
.stat-label { font-size: 12px; color: #7f8c8d; }

/* ===================== */
/* TABS + SEARCH         */
/* ===================== */
.tabs-container {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 25px;
}
.tabs {
  display: flex;
  gap: 5px;
  background: white;
  padding: 5px;
  border-radius: 10px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}
.tabs button {
  padding: 10px 20px;
  border: none;
  background: transparent;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 500;
  color: #7f8c8d;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  gap: 8px;
}
.tabs button.active { background: #3498db; color: white; }
.tab-count {
  background: rgba(255,255,255,0.2);
  padding: 2px 8px;
  border-radius: 10px;
  font-size: 12px;
}
.tabs button.active .tab-count { background: rgba(255,255,255,0.3); }

.tabs-right {
  display: flex;
  align-items: center;
  gap: 12px;
}
.search-box {
  display: flex;
  align-items: center;
  background: white;
  border: 1px solid #ddd;
  border-radius: 8px;
  padding: 0 12px;
  height: 38px;
  min-width: 250px;
  transition: border-color 0.2s, box-shadow 0.2s;
}
.search-box:focus-within {
  border-color: #3498db;
  box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
}
.search-icon { font-size: 14px; margin-right: 8px; }
.search-box input {
  border: none;
  outline: none;
  font-size: 13px;
  flex: 1;
  background: transparent;
}
.search-clear {
  background: none;
  border: none;
  font-size: 18px;
  cursor: pointer;
  color: #95a5a6;
  padding: 0 4px;
}
.search-clear:hover { color: #e74c3c; }

.view-toggle {
  display: flex;
  background: white;
  border-radius: 8px;
  overflow: hidden;
  border: 1px solid #ddd;
}
.view-toggle button {
  padding: 8px 12px;
  border: none;
  background: transparent;
  cursor: pointer;
  font-size: 14px;
  transition: all 0.2s;
}
.view-toggle button.active { background: #3498db; color: white; }

/* ===================== */
/* GRID VIEW (cards)     */
/* ===================== */
.assignments-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
  gap: 20px;
}

/* ===================== */
/* LIST VIEW             */
/* ===================== */
.assignments-list {
  background: white;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}
.assignment-row {
  display: flex;
  align-items: center;
  gap: 15px;
  padding: 15px 20px;
  border-bottom: 1px solid #eee;
  cursor: pointer;
  transition: background 0.2s;
}
.assignment-row:hover { background: #f8f9fa; }
.assignment-row:last-child { border-bottom: none; }
.row-status { width: 30px; }
.status-dot {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  display: inline-block;
  animation: pulse 2s infinite;
}
.status-dot.active { background: #27ae60; }
@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.5; }
}
.row-truck, .row-driver {
  flex: 1;
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 150px;
}
.row-icon { font-size: 20px; }
.row-info { min-width: 0; }
.row-primary {
  font-weight: 500;
  color: #2c3e50;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.row-secondary { font-size: 11px; color: #7f8c8d; }
.row-connector { font-size: 16px; }
.row-date { min-width: 100px; text-align: center; }
.row-mileage { min-width: 100px; }
.mileage-badge {
  background: #e8f4fd;
  color: #2980b9;
  padding: 4px 10px;
  border-radius: 15px;
  font-size: 12px;
  font-weight: 500;
}
.row-reason { min-width: 150px; }
.reason-badge { font-size: 12px; color: #555; }
.row-actions { width: 40px; }
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
.btn-icon.danger { background: #f8d7da; }

/* ===================== */
/* HISTORIQUE            */
/* ===================== */
.history-section {
  background: white;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}
.table-container { overflow-x: auto; }
.history-table { width: 100%; border-collapse: collapse; }
.history-table th {
  text-align: left;
  padding: 12px 15px;
  background: #f8f9fa;
  font-size: 11px;
  text-transform: uppercase;
  color: #7f8c8d;
  font-weight: 600;
}
.history-table th.sortable {
  cursor: pointer;
  user-select: none;
  white-space: nowrap;
}
.history-table th.sortable:hover { color: #3498db; }
.history-table td {
  padding: 12px 15px;
  border-top: 1px solid #eee;
  font-size: 13px;
}
.history-table tr { cursor: pointer; transition: background 0.2s; }
.history-table tr:hover { background: #f8f9fa; }
.history-table tr.active { background: #f0fff4; }
.cell-entity { display: flex; align-items: center; gap: 10px; }
.cell-icon { font-size: 18px; }
.cell-primary { font-weight: 500; color: #2c3e50; }
.cell-secondary { font-size: 11px; color: #7f8c8d; }
.cell-date { text-align: left; }
.status-badge {
  padding: 4px 10px;
  border-radius: 15px;
  font-size: 11px;
  font-weight: 500;
  white-space: nowrap;
}
.status-badge.active { background: #d4edda; color: #155724; }
.status-badge.completed { background: #e9ecef; color: #495057; }
.duration-badge {
  padding: 3px 8px;
  border-radius: 10px;
  font-size: 11px;
  background: #f8f9fa;
  color: #555;
}
.duration-badge.long { background: #fff3cd; color: #856404; }
.duration-badge.medium { background: #d1ecf1; color: #0c5460; }
.distance-badge {
  background: #d4edda;
  color: #155724;
  padding: 4px 10px;
  border-radius: 15px;
  font-size: 11px;
  font-weight: 500;
}
.reason-tag { font-size: 12px; color: #555; }
.actions-cell { white-space: nowrap; }
.action-buttons { display: flex; gap: 5px; }
.text-muted { color: #95a5a6; }

/* ===================== */
/* SKELETON LOADER       */
/* ===================== */
.skeleton-container { padding: 15px 0; }
.skeleton-row {
  display: flex;
  gap: 15px;
  padding: 15px 0;
  border-bottom: 1px solid #eee;
}
.skeleton-cell {
  height: 18px;
  background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
  border-radius: 4px;
}
.skeleton-cell.tiny { width: 50px; }
.skeleton-cell.small { width: 100px; }
.skeleton-cell.medium { width: 150px; }
@keyframes shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* ===================== */
/* EMPTY & LOADING       */
/* ===================== */
.empty-state { text-align: center; padding: 60px; color: #7f8c8d; }
.empty-icon { font-size: 60px; opacity: 0.5; }
.empty-state h3 { margin: 15px 0 5px; color: #2c3e50; }
.empty-state .btn { margin-top: 20px; }

/* ===================== */
/* STATS SECTION         */
/* ===================== */
.stats-section { display: flex; flex-direction: column; gap: 25px; }
.overview-cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 15px;
}
.overview-card {
  background: white;
  border-radius: 12px;
  padding: 20px;
  display: flex;
  align-items: center;
  gap: 15px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}
.overview-icon { font-size: 36px; }
.overview-value { font-size: 28px; font-weight: 700; color: #2c3e50; }
.overview-label { font-size: 12px; color: #7f8c8d; }
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
  gap: 20px;
}
.stats-widget {
  background: white;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}
.widget-header { margin-bottom: 20px; }
.widget-header h3 { margin: 0 0 5px; font-size: 16px; color: #2c3e50; }
.widget-subtitle { font-size: 12px; color: #7f8c8d; }
.ranking-list { display: flex; flex-direction: column; gap: 12px; }
.ranking-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px;
  background: #f8f9fa;
  border-radius: 10px;
  transition: all 0.2s;
}
.ranking-item:hover { background: #e9ecef; }
.rank {
  width: 28px;
  height: 28px;
  background: #3498db;
  color: white;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 12px;
}
.rank.rank-1 { background: linear-gradient(135deg, #f1c40f, #d4a012); }
.rank.rank-2 { background: linear-gradient(135deg, #bdc3c7, #95a5a6); }
.rank.rank-3 { background: linear-gradient(135deg, #cd6133, #b5541e); }
.rank-avatar { font-size: 24px; }
.rank-info { flex: 1; min-width: 0; }
.rank-name {
  font-weight: 600;
  color: #2c3e50;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.rank-detail { font-size: 11px; color: #7f8c8d; }
.rank-stats { text-align: right; }
.rank-value { font-weight: 700; color: #2c3e50; font-size: 16px; }
.rank-unit { font-size: 10px; color: #7f8c8d; }

.reasons-list, .activity-list { display: flex; flex-direction: column; gap: 12px; }
.reason-item, .activity-item { display: flex; align-items: center; gap: 12px; }
.reason-info { display: flex; align-items: center; gap: 8px; min-width: 150px; }
.reason-icon { font-size: 16px; }
.reason-name { font-size: 13px; color: #555; }
.reason-bar-container, .activity-bar-container {
  flex: 1;
  height: 8px;
  background: #e9ecef;
  border-radius: 4px;
  overflow: hidden;
}
.reason-bar {
  height: 100%;
  background: linear-gradient(135deg, #3498db, #2980b9);
  border-radius: 4px;
  transition: width 0.5s ease;
}
.activity-bar {
  height: 100%;
  background: linear-gradient(135deg, #27ae60, #1e8449);
  border-radius: 4px;
  transition: width 0.5s ease;
}
.reason-count, .activity-count { min-width: 30px; text-align: right; font-weight: 600; color: #2c3e50; }
.activity-date { min-width: 80px; font-size: 12px; color: #555; }
.empty-ranking { text-align: center; padding: 30px; color: #95a5a6; }
.empty-ranking span { font-size: 40px; opacity: 0.5; }
.empty-ranking p { margin: 10px 0 0; }

/* ===================== */
/* MODALS                */
/* ===================== */
.modal-overlay {
  position: fixed;
  top: 0; left: 0; right: 0; bottom: 0;
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
.modal-lg { max-width: 700px; }
.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px;
  border-bottom: 1px solid #eee;
}
.modal-header h2 { margin: 0; font-size: 18px; color: #2c3e50; }
.close-btn {
  background: none;
  border: none;
  font-size: 24px;
  cursor: pointer;
  color: #7f8c8d;
  transition: color 0.2s;
}
.close-btn:hover { color: #e74c3c; }
.modal-body { padding: 20px; overflow-y: auto; }
.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding-top: 15px;
  border-top: 1px solid #eee;
  margin-top: 10px;
}

/* ===================== */
/* ASSIGN MODAL          */
/* ===================== */
.availability-summary {
  display: flex;
  gap: 20px;
  padding: 15px;
  background: linear-gradient(135deg, #e8f4fd, #d1ecf1);
  border-radius: 10px;
  margin-bottom: 20px;
}
.avail-item { display: flex; align-items: center; gap: 8px; }
.avail-icon { font-size: 20px; }
.avail-count { font-size: 20px; font-weight: 700; color: #2c3e50; }
.avail-label { font-size: 12px; color: #555; }

.form-section { margin-bottom: 20px; }
.form-section h3 {
  font-size: 13px;
  color: #7f8c8d;
  text-transform: uppercase;
  margin: 0 0 12px;
  padding-bottom: 8px;
  border-bottom: 1px solid #eee;
}
.form-group { margin-bottom: 15px; }
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
  box-sizing: border-box;
}
.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
  outline: none;
  border-color: #3498db;
}
.form-hint { font-size: 11px; color: #95a5a6; margin-top: 4px; display: block; }

.reason-selector {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
  gap: 8px;
}
.reason-option {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px;
  background: #f8f9fa;
  border-radius: 8px;
  cursor: pointer;
  border: 2px solid transparent;
  transition: all 0.2s;
}
.reason-option:hover { background: #e9ecef; }
.reason-option.selected { background: #e8f4fd; border-color: #3498db; }
.reason-option input { display: none; }
.reason-option .reason-icon { font-size: 16px; }
.reason-option .reason-label { font-size: 12px; color: #2c3e50; }

.form-error {
  background: #f8d7da;
  color: #721c24;
  padding: 10px;
  border-radius: 6px;
  margin-bottom: 15px;
  font-size: 13px;
}

/* ===================== */
/* UNASSIGN MODAL        */
/* ===================== */
.assignment-summary {
  background: #f8f9fa;
  border-radius: 12px;
  padding: 20px;
  margin-bottom: 20px;
}
.summary-header {
  font-size: 11px;
  text-transform: uppercase;
  color: #7f8c8d;
  margin-bottom: 12px;
}
.summary-pair {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 20px;
  margin-bottom: 15px;
}
.summary-entity { display: flex; align-items: center; gap: 8px; font-weight: 600; color: #2c3e50; }
.entity-icon { font-size: 20px; }
.summary-connector { font-size: 20px; }
.summary-details {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 10px;
  padding-top: 15px;
  border-top: 1px solid #e9ecef;
}
.summary-item { text-align: center; }
.summary-label { display: block; font-size: 10px; color: #7f8c8d; text-transform: uppercase; }
.summary-value { font-size: 12px; font-weight: 500; color: #2c3e50; }

.mileage-calc {
  margin-top: 8px;
  padding: 8px 12px;
  background: #d4edda;
  border-radius: 6px;
  display: flex;
  align-items: center;
  gap: 10px;
}
.calc-label { font-size: 12px; color: #155724; }
.calc-value { font-weight: 700; color: #155724; }

/* ===================== */
/* DETAIL MODAL          */
/* ===================== */
.detail-status-banner {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 15px 20px;
  border-radius: 10px;
  margin-bottom: 20px;
}
.detail-status-banner.active { background: linear-gradient(135deg, #d4edda, #c3e6cb); }
.detail-status-banner.completed { background: linear-gradient(135deg, #e9ecef, #dee2e6); }
.status-icon { font-size: 24px; }
.detail-status-banner .status-text { font-weight: 600; color: #2c3e50; flex: 1; }
.status-duration {
  font-size: 12px;
  padding: 4px 12px;
  background: rgba(255,255,255,0.5);
  border-radius: 15px;
}

.detail-pair-section {
  display: flex;
  align-items: stretch;
  gap: 20px;
  margin-bottom: 20px;
}
.detail-entity {
  flex: 1;
  background: #f8f9fa;
  border-radius: 12px;
  padding: 20px;
  text-align: center;
}
.detail-entity.truck { border-top: 4px solid #3498db; }
.detail-entity.driver { border-top: 4px solid #9b59b6; }
.detail-entity-header {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  margin-bottom: 10px;
}
.detail-entity-icon { font-size: 24px; }
.detail-entity-label { font-size: 11px; text-transform: uppercase; color: #7f8c8d; }
.detail-entity-name { font-size: 18px; font-weight: 700; color: #2c3e50; margin-bottom: 5px; }
.detail-entity-info { font-size: 13px; color: #555; }
.detail-entity-code { font-size: 11px; color: #7f8c8d; font-family: monospace; margin-top: 5px; }

.detail-connector {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}
.connector-vertical {
  width: 2px;
  height: 30px;
  background: linear-gradient(to bottom, transparent, #3498db, transparent);
}
.connector-symbol { font-size: 24px; margin: 5px 0; }

.detail-stats-row {
  display: flex;
  flex-wrap: wrap;
  gap: 15px;
  margin-bottom: 20px;
}
.detail-stat-item {
  flex: 1;
  min-width: 100px;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 15px;
  background: #f8f9fa;
  border-radius: 10px;
}
.detail-stat-item.highlight { background: #d4edda; }
.detail-stat-icon { font-size: 20px; }
.detail-stat-label { font-size: 10px; color: #7f8c8d; text-transform: uppercase; }
.detail-stat-value { font-weight: 600; color: #2c3e50; }

.detail-info-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}
.detail-info-section {
  background: #f8f9fa;
  border-radius: 10px;
  padding: 15px;
}
.detail-info-section h4 {
  margin: 0 0 12px;
  font-size: 12px;
  text-transform: uppercase;
  color: #7f8c8d;
}
.info-row {
  display: flex;
  justify-content: space-between;
  padding: 8px 0;
  border-bottom: 1px solid #e9ecef;
  font-size: 13px;
}
.info-row:last-child { border-bottom: none; }
.info-label { color: #7f8c8d; }
.info-value { color: #2c3e50; font-weight: 500; }

.detail-actions {
  margin-top: 20px;
  padding-top: 20px;
  border-top: 1px solid #eee;
  text-align: center;
}

/* ===================== */
/* RESPONSIVE            */
/* ===================== */
@media (max-width: 768px) {
  .assignments-page { padding: 15px; }
  .assignments-grid { grid-template-columns: 1fr; }
  .detail-pair-section { flex-direction: column; }
  .detail-connector { flex-direction: row; }
  .connector-vertical { width: 30px; height: 2px; }
  .detail-info-grid { grid-template-columns: 1fr; }
  .tabs-container { flex-direction: column; gap: 15px; }
  .tabs-right { flex-direction: column; width: 100%; }
  .search-box { min-width: unset; width: 100%; }
  .stats-grid { grid-template-columns: 1fr; }
  .summary-pair { flex-direction: column; gap: 10px; }
  .summary-details { grid-template-columns: 1fr; }
  .availability-summary { flex-direction: column; }
  .assignment-row { flex-wrap: wrap; }
}
</style>

