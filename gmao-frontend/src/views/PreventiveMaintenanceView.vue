<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
const plans = ref([])
const equipments = ref([])
const trucks = ref([])
const users = ref([])
const stats = ref({ 
  total: 0, 
  active: 0, 
  due_this_week: 0, 
  overdue: 0,
  by_asset_type: { equipment: 0, truck: 0 }
})
const calendarEvents = ref([])
const loading = ref(false)
const error = ref('')
const successMessage = ref('')
const searchQuery = ref('')
const filterActive = ref('')
const filterAssetType = ref('')

// Modals
const showCreateModal = ref(false)
const showDetailModal = ref(false)
const showEditModal = ref(false)
const selectedPlan = ref(null)
const saving = ref(false)
const currentMonth = ref(new Date())

// ✅ Modal assignation pour génération OT
const showAssignModal = ref(false)
const planToGenerate = ref(null)
const generateForm = ref({
  assigned_to: '',
})

const form = ref({
  asset_type: 'equipment',
  equipment_id: '',
  truck_id: '',
  name: '',
  description: '',
  frequency_type: 'monthly',
  frequency_value: 1,
  counter_threshold: null,
  counter_unit: '',
  start_date: '',
  end_date: '',
  priority: 'medium',
  estimated_duration: null,
  assigned_to: '',
  advance_days: 7,
  tasks: [],
})

const newTask = ref({ description: '', estimated_duration: null, instructions: '' })

// Labels
const frequencyLabels = {
  daily: 'Quotidien',
  weekly: 'Hebdomadaire',
  monthly: 'Mensuel',
  yearly: 'Annuel',
  counter: 'Compteur',
}

const priorityLabels = { low: 'Basse', medium: 'Moyenne', high: 'Haute', urgent: 'Urgente' }
const priorityColors = {
  low: { bg: '#d4edda', text: '#155724' },
  medium: { bg: '#fff3cd', text: '#856404' },
  high: { bg: '#ffe5d0', text: '#c45200' },
  urgent: { bg: '#f8d7da', text: '#721c24' },
}

const assetTypeLabels = { equipment: 'Équipement', truck: 'Camion' }
const assetTypeIcons = { equipment: '⚙️', truck: '🚚' }

const truckCounterUnits = [
  { value: 'km', label: 'Kilomètres' },
  { value: 'hours', label: 'Heures moteur' },
]

// Computed
const filteredPlans = computed(() => {
  return plans.value.filter(plan => {
    const assetName = plan.asset_type === 'truck'
      ? (plan.truck?.registration_number || plan.truck?.code || '')
      : (plan.equipment?.name || plan.equipment?.code || '')

    const matchSearch = !searchQuery.value ||
      plan.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      plan.code.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      assetName.toLowerCase().includes(searchQuery.value.toLowerCase())

    const matchActive = filterActive.value === '' ||
      plan.is_active === (filterActive.value === 'true')

    const matchAssetType = !filterAssetType.value || plan.asset_type === filterAssetType.value

    return matchSearch && matchActive && matchAssetType
  })
})

const calendarDays = computed(() => {
  const year = currentMonth.value.getFullYear()
  const month = currentMonth.value.getMonth()
  const firstDay = new Date(year, month, 1)
  const lastDay = new Date(year, month + 1, 0)
  const days = []

  const startPadding = firstDay.getDay() === 0 ? 6 : firstDay.getDay() - 1
  for (let i = startPadding; i > 0; i--) {
    const d = new Date(year, month, 1 - i)
    days.push({ date: d, isCurrentMonth: false, events: [] })
  }

  for (let i = 1; i <= lastDay.getDate(); i++) {
    const d = new Date(year, month, i)
    const dateStr = d.toISOString().split('T')[0]
    const events = calendarEvents.value.filter(e => e.date === dateStr)
    days.push({ date: d, isCurrentMonth: true, isToday: isToday(d), events })
  }

  const endPadding = 42 - days.length
  for (let i = 1; i <= endPadding; i++) {
    const d = new Date(year, month + 1, i)
    days.push({ date: d, isCurrentMonth: false, events: [] })
  }

  return days
})

const currentMonthLabel = computed(() => {
  return currentMonth.value.toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' })
})

// Helpers
function showSuccess(msg) {
  successMessage.value = msg
  setTimeout(() => { successMessage.value = '' }, 3000)
}

function showError(msg) {
  error.value = msg
  setTimeout(() => { error.value = '' }, 5000)
}

function formatDate(date) {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('fr-FR')
}

function formatMileage(km) {
  if (!km) return '-'
  return km.toLocaleString('fr-FR') + ' km'
}

function isToday(date) {
  const today = new Date()
  return date.toDateString() === today.toDateString()
}

function isOverdue(plan) {
  if (!plan.next_execution_date) return false
  return new Date(plan.next_execution_date) < new Date()
}

function isDueSoon(plan) {
  if (!plan.next_execution_date) return false
  const nextDate = new Date(plan.next_execution_date)
  const now = new Date()
  const daysUntil = Math.ceil((nextDate - now) / (1000 * 60 * 60 * 24))
  return daysUntil >= 0 && daysUntil <= 7
}

function getPriorityStyle(priority) {
  const c = priorityColors[priority] || priorityColors.medium
  return { backgroundColor: c.bg, color: c.text }
}

function getAssetName(plan) {
  if (plan.asset_type === 'truck') {
    const truck = plan.truck
    if (!truck) return '-'
    return truck.registration_number || `${truck.brand} ${truck.model}`
  }
  return plan.equipment?.name || '-'
}

function getAssetCode(plan) {
  if (plan.asset_type === 'truck') {
    return plan.truck?.code || ''
  }
  return plan.equipment?.code || ''
}

function prevMonth() {
  currentMonth.value = new Date(currentMonth.value.getFullYear(), currentMonth.value.getMonth() - 1, 1)
  fetchCalendar()
}

function nextMonth() {
  currentMonth.value = new Date(currentMonth.value.getFullYear(), currentMonth.value.getMonth() + 1, 1)
  fetchCalendar()
}

// API
async function fetchPlans() {
  loading.value = true
  try {
    const params = new URLSearchParams({ per_page: '100' })
    if (filterAssetType.value) params.append('asset_type', filterAssetType.value)

    const response = await api.get(`/preventive-maintenances?${params}`)
    plans.value = response.data.data
  } catch (err) {
    showError('Erreur lors du chargement')
  } finally {
    loading.value = false
  }
}

async function fetchStats() {
  try {
    const response = await api.get('/preventive-maintenances/stats')
    stats.value = response.data
  } catch (err) { console.error(err) }
}

async function fetchCalendar() {
  try {
    const start = new Date(currentMonth.value.getFullYear(), currentMonth.value.getMonth(), 1)
    const end = new Date(currentMonth.value.getFullYear(), currentMonth.value.getMonth() + 2, 0)
    
    const params = {
      start_date: start.toISOString().split('T')[0],
      end_date: end.toISOString().split('T')[0],
    }
    if (filterAssetType.value) params.asset_type = filterAssetType.value

    const response = await api.get('/preventive-maintenances/calendar', { params })
    calendarEvents.value = response.data
  } catch (err) { console.error(err) }
}

async function fetchEquipments() {
  try {
    const response = await api.get('/equipments?per_page=100&is_active=true')
    equipments.value = response.data.data
  } catch (err) { console.error(err) }
}

// ✅ CORRIGÉ — utilise /trucks-list au lieu de /trucks?status=active
async function fetchTrucks() {
  try {
    const response = await api.get('/trucks-list')
    trucks.value = response.data
  } catch (err) { console.error(err) }
}

async function fetchUsers() {
  try {
    const response = await api.get('/users?per_page=100')
    users.value = response.data.data.filter(u =>
      u.roles?.some(r => ['SuperAdmin', 'AdminSite', 'Planificateur', 'Technicien'].includes(r.name))
    )
  } catch (err) { console.error(err) }
}

function openCreateModal() {
  form.value = {
    asset_type: 'equipment',
    equipment_id: '',
    truck_id: '',
    name: '',
    description: '',
    frequency_type: 'monthly',
    frequency_value: 1,
    counter_threshold: null,
    counter_unit: '',
    start_date: new Date().toISOString().split('T')[0],
    end_date: '',
    priority: 'medium',
    estimated_duration: null,
    assigned_to: '',
    advance_days: 7,
    tasks: [],
  }
  showCreateModal.value = true
}

function onAssetTypeChange() {
  form.value.equipment_id = ''
  form.value.truck_id = ''
  if (form.value.asset_type === 'truck' && form.value.frequency_type === 'counter') {
    form.value.counter_unit = 'km'
  }
}

function onFrequencyTypeChange() {
  if (form.value.frequency_type === 'counter') {
    form.value.frequency_value = null
    if (form.value.asset_type === 'truck') {
      form.value.counter_unit = 'km'
    }
  } else {
    form.value.counter_threshold = null
    form.value.counter_unit = ''
    form.value.frequency_value = 1
  }
}

function addTask() {
  if (!newTask.value.description.trim()) return
  form.value.tasks.push({ ...newTask.value })
  newTask.value = { description: '', estimated_duration: null, instructions: '' }
}

function removeTask(index) {
  form.value.tasks.splice(index, 1)
}

async function createPlan() {
  saving.value = true
  try {
    const payload = {
      asset_type: form.value.asset_type,
      name: form.value.name,
      description: form.value.description,
      frequency_type: form.value.frequency_type,
      frequency_value: form.value.frequency_value,
      counter_threshold: form.value.counter_threshold,
      counter_unit: form.value.counter_unit,
      start_date: form.value.start_date,
      end_date: form.value.end_date || null,
      priority: form.value.priority,
      estimated_duration: form.value.estimated_duration,
      assigned_to: form.value.assigned_to || null,
      advance_days: form.value.advance_days,
      tasks: form.value.tasks,
    }

    if (form.value.asset_type === 'equipment') {
      payload.equipment_id = form.value.equipment_id
    } else {
      payload.truck_id = form.value.truck_id
    }

    await api.post('/preventive-maintenances', payload)
    showCreateModal.value = false
    showSuccess('Plan créé avec succès')
    fetchPlans()
    fetchStats()
    fetchCalendar()
  } catch (err) {
    showError(err.response?.data?.message || 'Erreur')
  } finally {
    saving.value = false
  }
}

async function openDetailModal(plan) {
  try {
    const response = await api.get(`/preventive-maintenances/${plan.id}`)
    selectedPlan.value = response.data
    showDetailModal.value = true
  } catch (err) {
    showError('Erreur lors du chargement')
  }
}

async function toggleActive(plan) {
  try {
    await api.post(`/preventive-maintenances/${plan.id}/toggle-active`)
    showSuccess(plan.is_active ? 'Plan désactivé' : 'Plan activé')
    fetchPlans()
    fetchStats()
    if (selectedPlan.value?.id === plan.id) {
      selectedPlan.value.is_active = !plan.is_active
    }
  } catch (err) {
    showError(err.response?.data?.message || 'Erreur')
  }
}

// ✅ CORRIGÉ — Ouvrir le modal d'assignation au lieu de générer directement
function generateWorkOrder(plan) {
  planToGenerate.value = plan
  generateForm.value.assigned_to = plan.assigned_to?.id || plan.assigned_to || ''
  showAssignModal.value = true
}

// ✅ Confirmer la génération avec le technicien choisi
async function confirmGenerateWorkOrder() {
  if (!generateForm.value.assigned_to) {
    showError('Veuillez sélectionner un technicien')
    return
  }

  saving.value = true
  try {
    const response = await api.post(`/preventive-maintenances/${planToGenerate.value.id}/generate`, {
      assigned_to: generateForm.value.assigned_to,
    })
    showAssignModal.value = false
    showSuccess(`OT généré: ${response.data.work_order.code}`)
    fetchPlans()
    fetchStats()
    fetchCalendar()
    if (selectedPlan.value?.id === planToGenerate.value.id) {
      openDetailModal(planToGenerate.value)
    }
  } catch (err) {
    showError(err.response?.data?.message || 'Erreur lors de la génération')
  } finally {
    saving.value = false
  }
}

async function deletePlan(plan) {
  if (!confirm('Supprimer ce plan ? Cette action est irréversible.')) return
  try {
    await api.delete(`/preventive-maintenances/${plan.id}`)
    showSuccess('Plan supprimé')
    showDetailModal.value = false
    fetchPlans()
    fetchStats()
  } catch (err) {
    showError(err.response?.data?.message || 'Erreur')
  }
}

function getEventStyle(event) {
  const baseStyle = getPriorityStyle(event.priority)
  if (event.asset_type === 'truck') {
    return { ...baseStyle, borderLeft: '3px solid #007bff' }
  }
  return { ...baseStyle, borderLeft: '3px solid #6f42c1' }
}

function getFrequencyDisplay(plan) {
  if (plan.frequency_type === 'counter') {
    return `Tous les ${plan.counter_threshold?.toLocaleString('fr-FR')} ${plan.counter_unit}`
  }
  return plan.frequency_label || frequencyLabels[plan.frequency_type]
}

onMounted(() => {
  fetchPlans()
  fetchStats()
  fetchCalendar()
  fetchEquipments()
  fetchTrucks()
  fetchUsers()
})
</script>

<template>
  <div class="pm-page">
    <header class="page-header">
      <div>
        <h1>📅 Maintenance Préventive</h1>
        <p class="subtitle">Plans de maintenance et calendrier</p>
      </div>
      <button class="btn btn-success" @click="openCreateModal" v-if="authStore.hasPermission('preventive:create')">
        + Nouveau plan
      </button>
    </header>

    <!-- Messages -->
    <div class="alert alert-success" v-if="successMessage">✅ {{ successMessage }}</div>
    <div class="alert alert-error" v-if="error">❌ {{ error }}</div>

    <!-- Stats -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-value">{{ stats.total }}</div>
        <div class="stat-label">Plans total</div>
      </div>
      <div class="stat-card active">
        <div class="stat-value">{{ stats.active }}</div>
        <div class="stat-label">Actifs</div>
      </div>
      <div class="stat-card week">
        <div class="stat-value">{{ stats.due_this_week }}</div>
        <div class="stat-label">Cette semaine</div>
      </div>
      <div class="stat-card overdue" v-if="stats.overdue > 0">
        <div class="stat-value">{{ stats.overdue }}</div>
        <div class="stat-label">⚠️ En retard</div>
      </div>
      <div class="stat-card equipment">
        <div class="stat-value">{{ stats.by_asset_type?.equipment || 0 }}</div>
        <div class="stat-label">⚙️ Équipements</div>
      </div>
      <div class="stat-card truck">
        <div class="stat-value">{{ stats.by_asset_type?.truck || 0 }}</div>
        <div class="stat-label">🚚 Camions</div>
      </div>
    </div>

    <div class="content-grid">
      <!-- Calendrier -->
      <div class="calendar-section">
        <div class="calendar-header">
          <button class="btn-icon" @click="prevMonth">←</button>
          <h3>{{ currentMonthLabel }}</h3>
          <button class="btn-icon" @click="nextMonth">→</button>
        </div>

        <div class="calendar-legend">
          <span class="legend-item"><span class="legend-dot equipment"></span> Équipement</span>
          <span class="legend-item"><span class="legend-dot truck"></span> Camion</span>
        </div>

        <div class="calendar">
          <div class="calendar-weekdays">
            <div v-for="day in ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim']" :key="day">{{ day }}</div>
          </div>
          <div class="calendar-days">
            <div
              v-for="(day, index) in calendarDays"
              :key="index"
              class="calendar-day"
              :class="{
                'other-month': !day.isCurrentMonth,
                'today': day.isToday,
                'has-events': day.events.length > 0
              }"
            >
              <span class="day-number">{{ day.date.getDate() }}</span>
              <div class="day-events">
                <div
                  v-for="event in day.events.slice(0, 2)"
                  :key="event.id"
                  class="event-dot"
                  :class="event.asset_type"
                  :style="getEventStyle(event)"
                  :title="`${assetTypeIcons[event.asset_type]} ${event.title}`"
                  @click="openDetailModal(event)"
                >
                  {{ assetTypeIcons[event.asset_type] }} {{ event.title.substring(0, 8) }}...
                </div>
                <div class="more-events" v-if="day.events.length > 2">+{{ day.events.length - 2 }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Liste des plans -->
      <div class="plans-section">
        <div class="section-header">
          <h3>Plans de maintenance</h3>
          <div class="filters-inline">
            <input type="text" v-model="searchQuery" placeholder="Rechercher..." class="search-sm" />
            <select v-model="filterAssetType" @change="fetchPlans(); fetchCalendar()">
              <option value="">Tous types</option>
              <option value="equipment">⚙️ Équipements</option>
              <option value="truck">🚚 Camions</option>
            </select>
            <select v-model="filterActive">
              <option value="">Tous</option>
              <option value="true">Actifs</option>
              <option value="false">Inactifs</option>
            </select>
          </div>
        </div>

        <div class="plans-list" v-if="!loading && filteredPlans.length">
          <div
            class="plan-card"
            v-for="plan in filteredPlans"
            :key="plan.id"
            :class="{
              inactive: !plan.is_active,
              overdue: isOverdue(plan),
              'due-soon': isDueSoon(plan) && !isOverdue(plan)
            }"
            @click="openDetailModal(plan)"
          >
            <div class="plan-header">
              <div class="plan-header-left">
                <span class="asset-type-icon">{{ assetTypeIcons[plan.asset_type] }}</span>
                <span class="plan-code">{{ plan.code }}</span>
              </div>
              <div class="plan-badges">
                <span class="badge asset-badge" :class="plan.asset_type">
                  {{ assetTypeLabels[plan.asset_type] }}
                </span>
                <span class="badge" :style="getPriorityStyle(plan.priority)">
                  {{ priorityLabels[plan.priority] }}
                </span>
              </div>
            </div>

            <h4 class="plan-name">{{ plan.name }}</h4>

            <div class="plan-asset">
              <strong>{{ getAssetName(plan) }}</strong>
              <small v-if="getAssetCode(plan)">({{ getAssetCode(plan) }})</small>
            </div>

            <div class="plan-meta">
              <span>🔄 {{ getFrequencyDisplay(plan) }}</span>
              <span v-if="plan.asset_type === 'truck' && plan.truck?.mileage">
                📏 {{ formatMileage(plan.truck.mileage) }}
              </span>
            </div>

            <div class="plan-next" :class="{ overdue: isOverdue(plan), 'due-soon': isDueSoon(plan) && !isOverdue(plan) }">
              <span v-if="plan.frequency_type === 'counter' && plan.asset_type === 'truck'">
                📅 Prochain: {{ formatMileage(plan.next_counter_value) }}
              </span>
              <span v-else>
                📅 Prochaine: {{ formatDate(plan.next_execution_date) }}
              </span>
            </div>

            <div class="plan-actions" @click.stop>
              <button
                class="btn btn-xs btn-primary"
                @click="generateWorkOrder(plan)"
                v-if="plan.is_active && authStore.hasPermission('preventive:generate_wo')"
              >
                → OT
              </button>
              <button
                class="btn btn-xs"
                :class="plan.is_active ? 'btn-warning' : 'btn-success'"
                @click="toggleActive(plan)"
                v-if="authStore.hasPermission('preventive:update')"
              >
                {{ plan.is_active ? 'Désactiver' : 'Activer' }}
              </button>
            </div>
          </div>
        </div>

        <div class="loading-state-sm" v-if="loading">
          <div class="spinner-sm"></div>
        </div>
        <div class="empty-state-sm" v-else-if="!filteredPlans.length">Aucun plan trouvé</div>
      </div>
    </div>

    <!-- Modal Création -->
    <div class="modal-overlay" v-if="showCreateModal" @click.self="showCreateModal = false">
      <div class="modal modal-lg">
        <div class="modal-header">
          <h2>➕ Nouveau plan de maintenance</h2>
          <button class="close-btn" @click="showCreateModal = false">&times;</button>
        </div>
        <form @submit.prevent="createPlan" class="modal-body">
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

          <div class="form-row">
            <!-- Sélection équipement -->
            <div class="form-group" v-if="form.asset_type === 'equipment'">
              <label>Équipement *</label>
              <select v-model="form.equipment_id" required>
                <option value="">-- Sélectionner --</option>
                <option v-for="eq in equipments" :key="eq.id" :value="eq.id">
                  {{ eq.code }} - {{ eq.name }}
                </option>
              </select>
            </div>

            <!-- Sélection camion -->
            <div class="form-group" v-if="form.asset_type === 'truck'">
              <label>Camion *</label>
              <select v-model="form.truck_id" required>
                <option value="">-- Sélectionner --</option>
                <option v-for="truck in trucks" :key="truck.id" :value="truck.id">
                  {{ truck.code }} - {{ truck.registration_number }} ({{ truck.brand }} {{ truck.model }})
                </option>
              </select>
            </div>

            <div class="form-group">
              <label>Nom du plan *</label>
              <input type="text" v-model="form.name" required placeholder="Ex: Vidange mensuelle" />
            </div>
          </div>

          <div class="form-group">
            <label>Description</label>
            <textarea v-model="form.description" rows="2" placeholder="Description détaillée du plan..."></textarea>
          </div>

          <!-- Fréquence -->
          <div class="form-section">
            <h4>🔄 Fréquence</h4>
            <div class="form-row">
              <div class="form-group">
                <label>Type de fréquence *</label>
                <select v-model="form.frequency_type" required @change="onFrequencyTypeChange">
                  <option value="daily">Quotidien</option>
                  <option value="weekly">Hebdomadaire</option>
                  <option value="monthly">Mensuel</option>
                  <option value="yearly">Annuel</option>
                  <option value="counter">Basé compteur (km, heures...)</option>
                </select>
              </div>

              <div class="form-group" v-if="form.frequency_type !== 'counter'">
                <label>Tous les *</label>
                <div class="input-with-suffix">
                  <input type="number" v-model="form.frequency_value" min="1" required />
                  <span class="input-suffix">
                    {{ form.frequency_type === 'daily' ? 'jour(s)' :
                       form.frequency_type === 'weekly' ? 'semaine(s)' :
                       form.frequency_type === 'monthly' ? 'mois' : 'an(s)' }}
                  </span>
                </div>
              </div>

              <div class="form-group" v-if="form.frequency_type === 'counter'">
                <label>Seuil compteur *</label>
                <input type="number" v-model="form.counter_threshold" min="1" required placeholder="Ex: 10000" />
              </div>

              <div class="form-group" v-if="form.frequency_type === 'counter'">
                <label>Unité compteur *</label>
                <select v-model="form.counter_unit" required v-if="form.asset_type === 'truck'">
                  <option value="">-- Sélectionner --</option>
                  <option v-for="unit in truckCounterUnits" :key="unit.value" :value="unit.value">
                    {{ unit.label }}
                  </option>
                </select>
                <input v-else type="text" v-model="form.counter_unit" placeholder="heures, cycles..." required />
              </div>
            </div>

            <div class="info-box" v-if="form.frequency_type === 'counter' && form.asset_type === 'truck'">
              💡 Pour les camions, la maintenance sera déclenchée lorsque le kilométrage atteindra le seuil défini.
              Le kilométrage actuel sera comparé au dernier kilométrage d'intervention.
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>Date de début *</label>
              <input type="date" v-model="form.start_date" required />
            </div>
            <div class="form-group">
              <label>Date de fin (optionnel)</label>
              <input type="date" v-model="form.end_date" />
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>Priorité *</label>
              <select v-model="form.priority" required>
                <option v-for="(label, key) in priorityLabels" :key="key" :value="key">{{ label }}</option>
              </select>
            </div>
            <div class="form-group">
              <label>Assigner à</label>
              <select v-model="form.assigned_to">
                <option value="">-- Non assigné --</option>
                <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
              </select>
            </div>
            <div class="form-group">
              <label>Jours d'avance (alerte)</label>
              <input type="number" v-model="form.advance_days" min="0" max="30" />
            </div>
          </div>

          <div class="form-group">
            <label>Durée estimée (minutes)</label>
            <input type="number" v-model="form.estimated_duration" min="0" placeholder="Ex: 60" />
          </div>

          <!-- Tâches -->
          <div class="tasks-section">
            <h4>📋 Tâches à effectuer</h4>
            <div class="tasks-list" v-if="form.tasks.length">
              <div class="task-item" v-for="(task, index) in form.tasks" :key="index">
                <span class="task-order">{{ index + 1 }}</span>
                <span class="task-desc">{{ task.description }}</span>
                <span class="task-duration" v-if="task.estimated_duration">{{ task.estimated_duration }} min</span>
                <button type="button" class="btn-remove" @click="removeTask(index)">✕</button>
              </div>
            </div>
            <div class="add-task-form">
              <input type="text" v-model="newTask.description" placeholder="Description de la tâche" @keyup.enter="addTask" />
              <input type="number" v-model="newTask.estimated_duration" placeholder="Durée (min)" class="duration-input" />
              <button type="button" class="btn btn-sm btn-secondary" @click="addTask">+ Ajouter</button>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" @click="showCreateModal = false">Annuler</button>
            <button type="submit" class="btn btn-primary" :disabled="saving">{{ saving ? 'Création...' : 'Créer le plan' }}</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal Détail -->
    <div class="modal-overlay" v-if="showDetailModal" @click.self="showDetailModal = false">
      <div class="modal modal-lg">
        <div class="modal-header">
          <h2>
            <span class="asset-type-icon">{{ assetTypeIcons[selectedPlan?.asset_type] }}</span>
            {{ selectedPlan?.code }} - {{ selectedPlan?.name }}
          </h2>
          <button class="close-btn" @click="showDetailModal = false">&times;</button>
        </div>
        <div class="modal-body" v-if="selectedPlan">
          <div class="detail-badges">
            <span class="badge asset-badge" :class="selectedPlan.asset_type">
              {{ assetTypeIcons[selectedPlan.asset_type] }} {{ assetTypeLabels[selectedPlan.asset_type] }}
            </span>
            <span class="badge" :style="getPriorityStyle(selectedPlan.priority)">{{ priorityLabels[selectedPlan.priority] }}</span>
            <span class="badge" :class="selectedPlan.is_active ? 'badge-success' : 'badge-inactive'">
              {{ selectedPlan.is_active ? '✓ Actif' : 'Inactif' }}
            </span>
          </div>

          <!-- Info Asset -->
          <div class="detail-asset-card" :class="selectedPlan.asset_type">
            <div class="asset-card-header">
              <span class="asset-icon">{{ assetTypeIcons[selectedPlan.asset_type] }}</span>
              <span class="asset-type-label">{{ assetTypeLabels[selectedPlan.asset_type] }}</span>
            </div>
            <div class="asset-card-body" v-if="selectedPlan.asset_type === 'equipment' && selectedPlan.equipment">
              <div class="asset-info">
                <span class="asset-name">{{ selectedPlan.equipment.name }}</span>
                <span class="asset-code">{{ selectedPlan.equipment.code }}</span>
              </div>
              <div class="asset-meta">
                <span v-if="selectedPlan.equipment.location">📍 {{ selectedPlan.equipment.location.name }}</span>
              </div>
            </div>
            <div class="asset-card-body" v-if="selectedPlan.asset_type === 'truck' && selectedPlan.truck">
              <div class="asset-info">
                <span class="asset-name">{{ selectedPlan.truck.registration_number }}</span>
                <span class="asset-code">{{ selectedPlan.truck.code }}</span>
              </div>
              <div class="asset-meta">
                <span>🚛 {{ selectedPlan.truck.brand }} {{ selectedPlan.truck.model }}</span>
                <span>📏 {{ formatMileage(selectedPlan.truck.mileage) }}</span>
                <span v-if="selectedPlan.truck.current_driver">👤 {{ selectedPlan.truck.current_driver.name }}</span>
              </div>
            </div>
          </div>

          <div class="detail-grid">
            <div class="detail-item">
              <span class="label">Fréquence</span>
              <span>{{ getFrequencyDisplay(selectedPlan) }}</span>
            </div>
            <div class="detail-item" :class="{ overdue: isOverdue(selectedPlan), 'due-soon': isDueSoon(selectedPlan) }">
              <span class="label">Prochaine exécution</span>
              <span v-if="selectedPlan.frequency_type === 'counter' && selectedPlan.asset_type === 'truck'">
                {{ formatMileage(selectedPlan.next_counter_value) }}
              </span>
              <span v-else>{{ formatDate(selectedPlan.next_execution_date) }}</span>
            </div>
            <div class="detail-item">
              <span class="label">Dernière exécution</span>
              <span>{{ formatDate(selectedPlan.last_execution_date) }}</span>
            </div>
            <div class="detail-item" v-if="selectedPlan.last_counter_value && selectedPlan.asset_type === 'truck'">
              <span class="label">Dernier km intervention</span>
              <span>{{ formatMileage(selectedPlan.last_counter_value) }}</span>
            </div>
            <div class="detail-item">
              <span class="label">Assigné à</span>
              <span>{{ selectedPlan.assigned_to?.name || '-' }}</span>
            </div>
            <div class="detail-item">
              <span class="label">Créé par</span>
              <span>{{ selectedPlan.created_by?.name }}</span>
            </div>
            <div class="detail-item" v-if="selectedPlan.estimated_duration">
              <span class="label">Durée estimée</span>
              <span>{{ selectedPlan.estimated_duration }} min</span>
            </div>
            <div class="detail-item">
              <span class="label">Jours d'avance</span>
              <span>{{ selectedPlan.advance_days }} jours</span>
            </div>
          </div>

          <div class="detail-section" v-if="selectedPlan.description">
            <h4>📝 Description</h4>
            <p>{{ selectedPlan.description }}</p>
          </div>

          <div class="detail-section" v-if="selectedPlan.tasks?.length">
            <h4>📋 Tâches ({{ selectedPlan.tasks.length }})</h4>
            <div class="tasks-readonly">
              <div class="task-ro" v-for="task in selectedPlan.tasks" :key="task.id">
                <span class="task-order">{{ task.order }}</span>
                <div class="task-content">
                  <span class="task-desc">{{ task.description }}</span>
                  <span class="task-instructions" v-if="task.instructions">{{ task.instructions }}</span>
                </div>
                <span class="task-duration" v-if="task.estimated_duration">{{ task.estimated_duration }} min</span>
              </div>
            </div>
          </div>

          <div class="detail-section" v-if="selectedPlan.logs?.length">
            <h4>📜 Historique des exécutions</h4>
            <div class="logs-list">
              <div class="log-item" v-for="log in selectedPlan.logs.slice(0, 10)" :key="log.id">
                <span class="log-date">{{ formatDate(log.scheduled_date) }}</span>
                <span class="log-status" :class="log.status">{{ log.status }}</span>
                <span class="log-counter" v-if="log.counter_value && selectedPlan.asset_type === 'truck'">
                  📏 {{ formatMileage(log.counter_value) }}
                </span>
                <span class="log-wo" v-if="log.work_order">→ {{ log.work_order.code }}</span>
              </div>
            </div>
          </div>

          <div class="detail-actions">
            <button
              class="btn btn-primary"
              @click="generateWorkOrder(selectedPlan)"
              v-if="selectedPlan.is_active && authStore.hasPermission('preventive:generate_wo')"
              :disabled="saving"
            >
              {{ saving ? 'Génération...' : '→ Générer OT' }}
            </button>
            <button
              class="btn"
              :class="selectedPlan.is_active ? 'btn-warning' : 'btn-success'"
              @click="toggleActive(selectedPlan)"
              v-if="authStore.hasPermission('preventive:update')"
            >
              {{ selectedPlan.is_active ? '⏸ Désactiver' : '▶ Activer' }}
            </button>
            <button
              class="btn btn-danger"
              @click="deletePlan(selectedPlan)"
              v-if="authStore.hasPermission('preventive:delete')"
            >
              🗑 Supprimer
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ✅ Modal Assignation pour Génération OT -->
    <div class="modal-overlay" v-if="showAssignModal" @click.self="showAssignModal = false">
      <div class="modal">
        <div class="modal-header">
          <h2>🔧 Générer un Ordre de Travail</h2>
          <button class="close-btn" @click="showAssignModal = false">&times;</button>
        </div>
        <div class="modal-body">
          <!-- Résumé du plan -->
          <div class="plan-summary" v-if="planToGenerate">
            <div class="summary-item">
              <span class="label">Plan</span>
              <span>{{ planToGenerate.code }} - {{ planToGenerate.name }}</span>
            </div>
            <div class="summary-item">
              <span class="label">Actif</span>
              <span>
                {{ assetTypeIcons[planToGenerate.asset_type] }}
                {{ getAssetName(planToGenerate) }}
              </span>
            </div>
            <div class="summary-item">
              <span class="label">Priorité</span>
              <span class="badge" :style="getPriorityStyle(planToGenerate.priority)">
                {{ priorityLabels[planToGenerate.priority] }}
              </span>
            </div>
          </div>

          <!-- Sélection technicien -->
          <div class="form-group" style="margin-top: 20px;">
            <label>👷 Assigner à un technicien *</label>
            <select v-model="generateForm.assigned_to" required class="select-highlight">
              <option value="">-- Sélectionner un technicien --</option>
              <option v-for="user in users" :key="user.id" :value="user.id">
                {{ user.name }}
              </option>
            </select>
            <small class="form-hint">⚠️ L'OT doit être assigné à un technicien avant d'être validé.</small>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" @click="showAssignModal = false">Annuler</button>
            <button
              type="button"
              class="btn btn-primary"
              @click="confirmGenerateWorkOrder"
              :disabled="saving || !generateForm.assigned_to"
            >
              {{ saving ? 'Génération...' : '→ Générer l\'OT' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.pm-page { padding: 30px; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; }
.page-header h1 { font-size: 28px; color: #2c3e50; margin-bottom: 5px; }
.subtitle { color: #7f8c8d; font-size: 14px; }

/* Stats */
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 15px; margin-bottom: 20px; }
.stat-card { background: white; border-radius: 12px; padding: 20px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
.stat-card.active { border-left: 4px solid #27ae60; }
.stat-card.week { border-left: 4px solid #3498db; }
.stat-card.overdue { border-left: 4px solid #e74c3c; background: #fff5f5; }
.stat-card.equipment { border-left: 4px solid #6f42c1; }
.stat-card.truck { border-left: 4px solid #007bff; }
.stat-value { font-size: 28px; font-weight: bold; color: #2c3e50; }
.stat-label { font-size: 12px; color: #7f8c8d; }

/* Content Grid */
.content-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }

/* Calendar */
.calendar-section { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
.calendar-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
.calendar-header h3 { margin: 0; text-transform: capitalize; }
.btn-icon { background: #f8f9fa; border: none; width: 36px; height: 36px; border-radius: 8px; cursor: pointer; font-size: 18px; }
.btn-icon:hover { background: #eee; }

.calendar-legend { display: flex; gap: 15px; margin-bottom: 15px; font-size: 12px; color: #7f8c8d; }
.legend-item { display: flex; align-items: center; gap: 5px; }
.legend-dot { width: 12px; height: 12px; border-radius: 3px; }
.legend-dot.equipment { background: #f3e5f5; border-left: 3px solid #6f42c1; }
.legend-dot.truck { background: #e3f2fd; border-left: 3px solid #007bff; }

.calendar-weekdays { display: grid; grid-template-columns: repeat(7, 1fr); text-align: center; font-size: 12px; color: #7f8c8d; margin-bottom: 10px; }
.calendar-days { display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px; }
.calendar-day { min-height: 80px; padding: 5px; background: #f8f9fa; border-radius: 4px; font-size: 12px; }
.calendar-day.other-month { opacity: 0.4; }
.calendar-day.today { background: #e3f2fd; border: 2px solid #3498db; }
.calendar-day.has-events { background: #fff; }
.day-number { font-weight: 600; color: #2c3e50; }
.day-events { margin-top: 5px; }
.event-dot { font-size: 9px; padding: 2px 4px; border-radius: 3px; margin-bottom: 2px; cursor: pointer; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.event-dot.truck { border-left: 3px solid #007bff; }
.event-dot.equipment { border-left: 3px solid #6f42c1; }
.more-events { font-size: 10px; color: #7f8c8d; }

/* Plans List */
.plans-section { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); max-height: 600px; overflow-y: auto; }
.section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px; }
.section-header h3 { margin: 0; }
.filters-inline { display: flex; gap: 10px; flex-wrap: wrap; }
.search-sm { padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; width: 150px; }
.filters-inline select { padding: 8px; border: 1px solid #ddd; border-radius: 6px; }

.plans-list { display: flex; flex-direction: column; gap: 10px; }
.plan-card { background: #f8f9fa; border-radius: 10px; padding: 15px; cursor: pointer; transition: all 0.2s; }
.plan-card:hover { background: #eee; transform: translateX(3px); }
.plan-card.inactive { opacity: 0.6; }
.plan-card.overdue { border-left: 3px solid #e74c3c; background: linear-gradient(to right, #fff5f5, #f8f9fa); }
.plan-card.due-soon { border-left: 3px solid #f39c12; background: linear-gradient(to right, #fffbf0, #f8f9fa); }

.plan-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px; }
.plan-header-left { display: flex; align-items: center; gap: 8px; }
.asset-type-icon { font-size: 16px; }
.plan-code { font-size: 11px; color: #7f8c8d; font-weight: 600; }
.plan-badges { display: flex; gap: 6px; }
.plan-name { font-size: 14px; color: #2c3e50; margin: 0 0 5px 0; }
.plan-asset { font-size: 13px; color: #2c3e50; margin-bottom: 8px; }
.plan-asset small { color: #95a5a6; }
.plan-meta { display: flex; gap: 15px; font-size: 12px; color: #7f8c8d; margin-bottom: 8px; }
.plan-next { font-size: 12px; color: #2c3e50; }
.plan-next.overdue { color: #e74c3c; font-weight: 600; }
.plan-next.due-soon { color: #f39c12; font-weight: 600; }
.plan-actions { display: flex; gap: 8px; margin-top: 10px; }

.badge { padding: 3px 8px; border-radius: 10px; font-size: 10px; font-weight: 600; }
.badge.asset-badge { background: #e9ecef; color: #495057; }
.badge.asset-badge.truck { background: #e3f2fd; color: #1565c0; }
.badge.asset-badge.equipment { background: #f3e5f5; color: #7b1fa2; }
.badge-success { background: #d4edda; color: #155724; }
.badge-inactive { background: #e2e3e5; color: #383d41; }

/* Alerts */
.alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; }
.alert-success { background: #d4edda; color: #155724; }
.alert-error { background: #f8d7da; color: #721c24; }

/* Loading */
.loading-state-sm { text-align: center; padding: 30px; }
.spinner-sm { width: 30px; height: 30px; border: 3px solid #eee; border-top-color: #3498db; border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto; }
@keyframes spin { to { transform: rotate(360deg); } }
.empty-state-sm { text-align: center; padding: 30px; color: #7f8c8d; }

/* Modal */
.modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 2000; }
.modal { background: white; border-radius: 16px; width: 100%; max-width: 550px; max-height: 90vh; overflow-y: auto; }
.modal-lg { max-width: 750px; }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 20px; border-bottom: 1px solid #eee; }
.modal-header h2 { margin: 0; font-size: 18px; color: #2c3e50; display: flex; align-items: center; gap: 8px; }
.close-btn { background: none; border: none; font-size: 24px; cursor: pointer; color: #7f8c8d; }
.modal-body { padding: 20px; }
.modal-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee; }

/* Forms */
.form-section { background: #f8f9fa; border-radius: 10px; padding: 15px; margin-bottom: 15px; }
.form-section h4 { margin: 0 0 15px 0; font-size: 14px; color: #2c3e50; }
.form-row { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; }
.form-group { margin-bottom: 15px; }
.form-group label { display: block; margin-bottom: 6px; font-weight: 500; font-size: 13px; color: #2c3e50; }
.form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; }
.input-with-suffix { display: flex; align-items: center; }
.input-with-suffix input { border-radius: 8px 0 0 8px; }
.input-suffix { background: #f8f9fa; border: 1px solid #ddd; border-left: none; padding: 10px 12px; border-radius: 0 8px 8px 0; color: #7f8c8d; white-space: nowrap; }
.info-box { background: #e3f2fd; padding: 12px 15px; border-radius: 8px; margin-top: 10px; color: #1565c0; font-size: 13px; }

/* Asset type selector */
.asset-type-selector { display: flex; gap: 15px; }
.asset-type-option { flex: 1; cursor: pointer; }
.asset-type-option input { display: none; }
.option-content { display: flex; flex-direction: column; align-items: center; padding: 20px; border: 2px solid #e0e0e0; border-radius: 12px; transition: all 0.2s; }
.asset-type-option.active .option-content, .asset-type-option:hover .option-content { border-color: #3498db; background: #f0f7ff; }
.option-icon { font-size: 32px; margin-bottom: 8px; }
.option-label { font-weight: 500; color: #2c3e50; }

/* Tasks */
.tasks-section { background: #f8f9fa; border-radius: 10px; padding: 15px; margin-top: 10px; }
.tasks-section h4 { margin: 0 0 15px 0; font-size: 14px; }
.tasks-list { margin-bottom: 15px; }
.task-item { display: flex; align-items: center; gap: 10px; background: white; padding: 10px; border-radius: 6px; margin-bottom: 5px; }
.task-order { width: 24px; height: 24px; background: #3498db; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600; flex-shrink: 0; }
.task-desc { flex: 1; font-size: 13px; }
.task-duration { font-size: 11px; color: #7f8c8d; background: #f0f0f0; padding: 2px 6px; border-radius: 4px; }
.btn-remove { background: #f8d7da; color: #721c24; border: none; width: 24px; height: 24px; border-radius: 4px; cursor: pointer; flex-shrink: 0; }
.add-task-form { display: flex; gap: 10px; }
.add-task-form input { flex: 1; padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; }
.add-task-form .duration-input { width: 100px; flex: none; }

/* Detail */
.detail-badges { display: flex; gap: 8px; margin-bottom: 15px; flex-wrap: wrap; }

.detail-asset-card { background: #f8f9fa; border-radius: 12px; padding: 15px; margin-bottom: 20px; }
.detail-asset-card.truck { background: #e3f2fd; border-left: 4px solid #007bff; }
.detail-asset-card.equipment { background: #f3e5f5; border-left: 4px solid #6f42c1; }
.asset-card-header { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; }
.asset-icon { font-size: 24px; }
.asset-type-label { font-size: 12px; text-transform: uppercase; color: #7f8c8d; font-weight: 600; }
.asset-card-body { }
.asset-info { display: flex; align-items: baseline; gap: 10px; margin-bottom: 5px; }
.asset-name { font-size: 18px; font-weight: 600; color: #2c3e50; }
.asset-code { font-size: 12px; color: #7f8c8d; }
.asset-meta { display: flex; gap: 15px; font-size: 13px; color: #555; flex-wrap: wrap; }

.detail-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-bottom: 20px; }
.detail-item { background: #f8f9fa; padding: 12px; border-radius: 8px; }
.detail-item .label { display: block; font-size: 11px; color: #7f8c8d; margin-bottom: 4px; }
.detail-item.overdue { background: #fff5f5; border-left: 3px solid #e74c3c; }
.detail-item.overdue span:not(.label) { color: #e74c3c; font-weight: 600; }
.detail-item.due-soon { background: #fffbf0; border-left: 3px solid #f39c12; }
.detail-item.due-soon span:not(.label) { color: #f39c12; font-weight: 600; }

.detail-section { margin-bottom: 20px; }
.detail-section h4 { font-size: 13px; color: #7f8c8d; margin-bottom: 10px; }
.detail-section p { margin: 0; font-size: 14px; background: #f8f9fa; padding: 12px; border-radius: 8px; }

.tasks-readonly { display: flex; flex-direction: column; gap: 8px; }
.task-ro { display: flex; align-items: flex-start; gap: 10px; background: #f8f9fa; padding: 10px; border-radius: 6px; }
.task-content { flex: 1; }
.task-instructions { display: block; font-size: 11px; color: #7f8c8d; margin-top: 4px; }

.logs-list { display: flex; flex-direction: column; gap: 8px; max-height: 200px; overflow-y: auto; }
.log-item { display: flex; gap: 15px; font-size: 13px; padding: 8px; background: #f8f9fa; border-radius: 6px; align-items: center; flex-wrap: wrap; }
.log-date { font-weight: 500; }
.log-status { padding: 2px 8px; border-radius: 10px; font-size: 11px; }
.log-status.generated { background: #d4edda; color: #155724; }
.log-status.completed { background: #d1ecf1; color: #0c5460; }
.log-status.scheduled { background: #fff3cd; color: #856404; }
.log-status.skipped { background: #e2e3e5; color: #383d41; }
.log-counter { font-size: 11px; color: #7f8c8d; }
.log-wo { color: #17a2b8; font-weight: 500; }

.detail-actions { display: flex; gap: 10px; margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee; flex-wrap: wrap; }

/* ✅ Modal assignation OT */
.plan-summary { background: #f8f9fa; border-radius: 10px; padding: 15px; }
.summary-item { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #eee; }
.summary-item:last-child { border-bottom: none; }
.summary-item .label { font-size: 12px; color: #7f8c8d; font-weight: 600; text-transform: uppercase; }
.select-highlight { border: 2px solid #3498db !important; background: #f0f7ff; }
.form-hint { display: block; margin-top: 6px; font-size: 12px; color: #e67e22; }

/* Buttons */
.btn { padding: 10px 20px; border: none; border-radius: 8px; font-weight: 500; cursor: pointer; }
.btn:hover { opacity: 0.9; }
.btn:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-sm { padding: 6px 12px; font-size: 13px; }
.btn-xs { padding: 4px 8px; font-size: 11px; }
.btn-primary { background: #3498db; color: white; }
.btn-success { background: #27ae60; color: white; }
.btn-danger { background: #e74c3c; color: white; }
.btn-warning { background: #f39c12; color: white; }
.btn-secondary { background: #95a5a6; color: white; }

@media (max-width: 1024px) {
  .content-grid { grid-template-columns: 1fr; }
  .stats-grid { grid-template-columns: repeat(2, 1fr); }
  .form-row { grid-template-columns: 1fr; }
  .detail-grid { grid-template-columns: 1fr; }
  .asset-type-selector { flex-direction: column; }
}
</style>
