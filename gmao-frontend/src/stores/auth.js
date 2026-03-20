import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/services/api'
import { getCsrfCookie } from '@/services/csrf'
import { updateAbility, resetAbility } from '@/services/ability'

export const useAuthStore = defineStore('auth', () => {
  // State
  const user = ref(null)
  const isLoading = ref(false)
  const isAuthenticated = computed(() => !!user.value)

  // Getters
  const currentSite = computed(() => user.value?.current_site)
  const roles = computed(() => user.value?.roles || [])
  const permissions = computed(() => user.value?.permissions || [])
  const authorizedSites = computed(() => user.value?.authorized_sites || [])

  // Actions
  async function login(email, password) {
    isLoading.value = true
    try {
      await getCsrfCookie()
      const response = await api.post('/login', { email, password })
      user.value = response.data.user
      updateAbility(response.data.user.permissions)
      return { success: true }
    } catch (error) {
      console.error('Login error:', error)
      return {
        success: false,
        message: error.response?.data?.message || 'Erreur de connexion',
      }
    } finally {
      isLoading.value = false
    }
  }

  async function logout() {
    try {
      await api.post('/logout')
    } catch (error) {
      console.error('Erreur logout:', error)
    } finally {
      user.value = null
      resetAbility()
    }
  }

  async function fetchUser() {
    if (isLoading.value) return

    isLoading.value = true
    try {
      await getCsrfCookie()
      const response = await api.get('/user')
      user.value = response.data.user
      updateAbility(response.data.user.permissions)
    } catch (error) {
      user.value = null
      resetAbility()
    } finally {
      isLoading.value = false
    }
  }

  // ✅ CORRIGÉ : siteId dans l'URL au lieu du body
  async function switchSite(siteId) {
    try {
      const response = await api.post(`/switch-site/${siteId}`)
      user.value = response.data.user
      updateAbility(response.data.user.permissions)
      return { success: true }
    } catch (error) {
      return {
        success: false,
        message: error.response?.data?.message || 'Erreur lors du changement de site',
      }
    }
  }

  function hasRole(roleName) {
    return roles.value.includes(roleName)
  }

  function hasPermission(permissionName) {
    return permissions.value.includes(permissionName)
  }

  function hasAnyPermission(permissionNames) {
    return permissionNames.some(p => permissions.value.includes(p))
  }

  return {
    // State
    user,
    isLoading,
    isAuthenticated,
    // Getters
    currentSite,
    roles,
    permissions,
    authorizedSites,
    // Actions
    login,
    logout,
    fetchUser,
    switchSite,
    hasRole,
    hasPermission,
    hasAnyPermission,
  }
})
