import { ref, computed } from 'vue'
import api from '@/services/api'

const allSettings = ref({})
const loading = ref(false)
const saving = ref(false)
const error = ref(null)

export function useSettings() {
  // ─── Fetch toutes les settings groupées ───
  const fetchSettings = async () => {
    loading.value = true
    error.value = null
    try {
      const { data } = await api.get('/settings')
      allSettings.value = data
    } catch (e) {
      error.value = e.response?.data?.message || 'Erreur de chargement'
      console.error('Erreur settings:', e)
    } finally {
      loading.value = false
    }
  }

  // ─── Fetch settings d'un seul groupe ───
  const fetchGroup = async (group) => {
    loading.value = true
    try {
      const { data } = await api.get(`/settings/group/${group}`)
      allSettings.value[group] = data
      return data
    } catch (e) {
      error.value = e.response?.data?.message || 'Erreur de chargement'
    } finally {
      loading.value = false
    }
  }

  // ─── Mettre à jour un paramètre ───
  const updateSetting = async (setting) => {
    saving.value = true
    error.value = null
    try {
      const { data } = await api.put(`/settings/${setting.id}`, {
        value: setting.type === 'json' || setting.type === 'list'
          ? (typeof setting.value === 'string' ? JSON.parse(setting.value) : setting.value)
          : setting.value,
      })
      return data
    } catch (e) {
      error.value = e.response?.data?.message || 'Erreur de sauvegarde'
      throw e
    } finally {
      saving.value = false
    }
  }

  // ─── Sauvegarde en lot ───
  const bulkUpdate = async (settings) => {
    saving.value = true
    error.value = null
    try {
      const payload = settings.map(s => ({
        id: s.id,
        value: (s.type === 'json' || s.type === 'list')
          ? (typeof s.value === 'string' ? JSON.parse(s.value) : s.value)
          : s.value,
      }))
      const { data } = await api.put('/settings/bulk', { settings: payload })
      return data
    } catch (e) {
      error.value = e.response?.data?.message || 'Erreur de sauvegarde'
      throw e
    } finally {
      saving.value = false
    }
  }

  // ─── Créer un paramètre ───
  const createSetting = async (setting) => {
    saving.value = true
    try {
      const { data } = await api.post('/settings', setting)
      return data
    } catch (e) {
      error.value = e.response?.data?.message || 'Erreur de création'
      throw e
    } finally {
      saving.value = false
    }
  }

  // ─── Supprimer un paramètre ───
  const deleteSetting = async (id) => {
    try {
      await api.delete(`/settings/${id}`)
    } catch (e) {
      error.value = e.response?.data?.message || 'Erreur de suppression'
      throw e
    }
  }

  // ─── Réinitialiser un groupe ───
  const resetGroup = async (group) => {
    saving.value = true
    try {
      const { data } = await api.post(`/settings/reset/${group}`)
      await fetchSettings()
      return data
    } catch (e) {
      error.value = e.response?.data?.message || 'Erreur de réinitialisation'
      throw e
    } finally {
      saving.value = false
    }
  }

  // ─── Récupérer les options d'une liste (pour les selects) ───
  const fetchOptions = async (group, key) => {
    try {
      const { data } = await api.get(`/settings/options/${group}/${key}`)
      return data
    } catch (e) {
      console.error(`Erreur options ${group}.${key}:`, e)
      return []
    }
  }

  // ─── Groupes disponibles ───
  const groups = computed(() => Object.keys(allSettings.value))

  return {
    allSettings,
    loading,
    saving,
    error,
    groups,
    fetchSettings,
    fetchGroup,
    updateSetting,
    bulkUpdate,
    createSetting,
    deleteSetting,
    resetGroup,
    fetchOptions,
  }
}
