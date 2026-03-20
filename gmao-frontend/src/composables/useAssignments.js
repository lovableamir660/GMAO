import { ref, computed } from 'vue'
import api from '@/services/api'

export function useAssignments() {
  const assignments = ref([])
  const activeAssignments = ref([])
  const stats = ref(null)
  const drivers = ref([])
  const trucks = ref([])
  const loading = ref(true)
  const pagination = ref({})
  const error = ref('')
  const saving = ref(false)
  const searchQuery = ref('')

  const filters = ref({
    truck_id: '',
    driver_id: '',
    status: '',
    reason: '',
    from_date: '',
    to_date: '',
  })

  const assignForm = ref({
    truck_id: '',
    driver_id: '',
    start_mileage: '',
    assignment_reason: 'regular',
    notes: '',
  })

  const unassignForm = ref({
    end_mileage: '',
    unassignment_reason: 'end_mission',
    notes: '',
  })

  const assignmentReasons = [
    { value: 'regular', label: 'Attribution régulière', icon: '🔄' },
    { value: 'mission', label: 'Mission spécifique', icon: '🎯' },
    { value: 'replacement', label: 'Remplacement', icon: '🔁' },
    { value: 'training', label: 'Formation', icon: '📚' },
    { value: 'temporary', label: 'Temporaire', icon: '⏱️' },
  ]

  const unassignmentReasons = [
    { value: 'end_mission', label: 'Fin de mission', icon: '✅' },
    { value: 'breakdown', label: 'Panne véhicule', icon: '🔧' },
    { value: 'maintenance', label: 'Maintenance', icon: '🛠️' },
    { value: 'leave', label: 'Congé chauffeur', icon: '🏖️' },
    { value: 'reassignment', label: 'Réaffectation', icon: '🔀' },
    { value: 'termination', label: 'Fin de contrat', icon: '📋' },
  ]

  // Stats calculées
  const computedStats = computed(() => {
    if (!stats.value) return null
    const active = activeAssignments.value
    return {
      ...stats.value,
      trucksAssigned: active.length,
      trucksAvailable: trucks.value.filter(t =>
        !active.some(a => a.truck_id === t.id) && t.status !== 'out_of_service'
      ).length,
      driversAssigned: active.length,
      driversAvailable: drivers.value.filter(d =>
        !active.some(a => a.driver_id === d.id)
      ).length,
    }
  })

  // Recherche globale sur les attributions actives
  const filteredActiveAssignments = computed(() => {
    if (!searchQuery.value.trim()) return activeAssignments.value
    const q = searchQuery.value.toLowerCase().trim()
    return activeAssignments.value.filter(a => {
      const truck = a.truck?.registration_number?.toLowerCase() || ''
      const brand = a.truck?.brand?.toLowerCase() || ''
      const model = a.truck?.model?.toLowerCase() || ''
      const firstName = a.driver?.first_name?.toLowerCase() || ''
      const lastName = a.driver?.last_name?.toLowerCase() || ''
      const code = a.driver?.code?.toLowerCase() || ''
      return truck.includes(q) || brand.includes(q) || model.includes(q)
        || firstName.includes(q) || lastName.includes(q) || code.includes(q)
    })
  })

  // Camions et chauffeurs disponibles
  const availableTrucks = computed(() => {
    const assignedTruckIds = activeAssignments.value.map(a => a.truck_id)
    return trucks.value.filter(t => !assignedTruckIds.includes(t.id) && t.status !== 'out_of_service')
  })

  const availableDrivers = computed(() => {
    const assignedDriverIds = activeAssignments.value.map(a => a.driver_id)
    return drivers.value.filter(d => !assignedDriverIds.includes(d.id))
  })

  // --- Fetch ---
  async function fetchAssignments(page = 1) {
    loading.value = true
    error.value = ''
    try {
      const params = { page, per_page: 20 }
      Object.keys(filters.value).forEach(key => {
        if (filters.value[key]) params[key] = filters.value[key]
      })
      const response = await api.get('/assignments', { params })
      assignments.value = response.data.data
      pagination.value = {
        current_page: response.data.current_page,
        last_page: response.data.last_page,
        total: response.data.total,
      }
    } catch (err) {
      error.value = 'Impossible de charger l\'historique des attributions.'
      console.error('Erreur fetchAssignments:', err)
    } finally {
      loading.value = false
    }
  }

  async function fetchActiveAssignments() {
    try {
      const response = await api.get('/assignments/active')
      activeAssignments.value = response.data
    } catch (err) {
      error.value = 'Impossible de charger les attributions actives.'
      console.error('Erreur fetchActiveAssignments:', err)
    }
  }

  async function fetchStats() {
    try {
      const response = await api.get('/assignments/stats')
      stats.value = response.data
    } catch (err) {
      console.error('Erreur fetchStats:', err)
    }
  }

  async function fetchDrivers() {
    try {
      const response = await api.get('/drivers-list')
      drivers.value = response.data
    } catch (err) {
      console.error('Erreur fetchDrivers:', err)
    }
  }

  async function fetchTrucks() {
    try {
      const response = await api.get('/trucks-list')
      trucks.value = response.data
    } catch (err) {
      console.error('Erreur fetchTrucks:', err)
    }
  }

  async function fetchAll() {
    await Promise.all([
      fetchAssignments(),
      fetchActiveAssignments(),
      fetchStats(),
      fetchDrivers(),
      fetchTrucks(),
    ])
  }

  // --- Actions ---
  async function submitAssignment() {
    saving.value = true
    error.value = ''
    try {
      await api.post('/assignments/assign', assignForm.value)
      await Promise.all([fetchActiveAssignments(), fetchAssignments(), fetchStats()])
      return true
    } catch (err) {
      error.value = err.response?.data?.message || 'Erreur lors de l\'attribution'
      return false
    } finally {
      saving.value = false
    }
  }

  async function submitUnassignment(assignmentId) {
    saving.value = true
    error.value = ''
    try {
      await api.post(`/assignments/${assignmentId}/unassign`, unassignForm.value)
      await Promise.all([fetchActiveAssignments(), fetchAssignments(), fetchStats()])
      return true
    } catch (err) {
      error.value = err.response?.data?.message || 'Erreur lors de la fin d\'attribution'
      return false
    } finally {
      saving.value = false
    }
  }

  // --- Export CSV ---
  function exportCSV() {
    if (!assignments.value.length) return
    const headers = ['Statut', 'Camion', 'Chauffeur', 'Début', 'Fin', 'Durée', 'Distance (km)', 'Raison']
    const rows = assignments.value.map(a => [
      a.unassigned_at ? 'Terminée' : 'En cours',
      a.truck?.registration_number || '',
      `${a.driver?.first_name || ''} ${a.driver?.last_name || ''}`,
      a.assigned_at || '',
      a.unassigned_at || '',
      a.duration || '',
      a.distance ?? '',
      getReasonLabel(a.assignment_reason),
    ])
    const csv = [headers, ...rows].map(r => r.map(c => `"${c}"`).join(',')).join('\n')
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `attributions_${new Date().toISOString().slice(0, 10)}.csv`
    link.click()
    URL.revokeObjectURL(url)
  }

  // --- Helpers ---
  function resetFilters() {
    filters.value = { truck_id: '', driver_id: '', status: '', reason: '', from_date: '', to_date: '' }
    fetchAssignments()
  }

  function resetAssignForm() {
    assignForm.value = { truck_id: '', driver_id: '', start_mileage: '', assignment_reason: 'regular', notes: '' }
    error.value = ''
  }

  function resetUnassignForm(assignment) {
    unassignForm.value = {
      end_mileage: assignment?.truck?.mileage || assignment?.start_mileage || '',
      unassignment_reason: 'end_mission',
      notes: '',
    }
    error.value = ''
  }

  function onTruckSelect() {
    const truck = trucks.value.find(t => t.id === assignForm.value.truck_id)
    if (truck) assignForm.value.start_mileage = truck.mileage || ''
  }

  function getReasonLabel(reason, type = 'assignment') {
    const reasons = type === 'assignment' ? assignmentReasons : unassignmentReasons
    return reasons.find(r => r.value === reason)?.label || reason
  }

  function getReasonIcon(reason, type = 'assignment') {
    const reasons = type === 'assignment' ? assignmentReasons : unassignmentReasons
    return reasons.find(r => r.value === reason)?.icon || '📋'
  }

  function formatDate(date) {
    if (!date) return '-'
    return new Date(date).toLocaleDateString('fr-FR', {
      day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit',
    })
  }

  function formatDateShort(date) {
    if (!date) return '-'
    return new Date(date).toLocaleDateString('fr-FR', {
      day: '2-digit', month: 'short', year: 'numeric',
    })
  }

  function formatNumber(num) {
    if (!num && num !== 0) return '0'
    return num.toLocaleString('fr-FR')
  }

  function getDurationClass(duration) {
    if (!duration) return ''
    const days = parseInt(duration)
    if (days > 30) return 'long'
    if (days > 7) return 'medium'
    return 'short'
  }

  return {
    // State
    assignments, activeAssignments, stats, drivers, trucks,
    loading, pagination, error, saving, searchQuery,
    filters, assignForm, unassignForm,
    assignmentReasons, unassignmentReasons,
    // Computed
    computedStats, filteredActiveAssignments, availableTrucks, availableDrivers,
    // Actions
    fetchAssignments, fetchActiveAssignments, fetchStats, fetchAll,
    submitAssignment, submitUnassignment, exportCSV,
    // Helpers
    resetFilters, resetAssignForm, resetUnassignForm, onTruckSelect,
    getReasonLabel, getReasonIcon, formatDate, formatDateShort, formatNumber, getDurationClass,
  }
}
