import { ref, computed } from 'vue'
import api from '@/services/api'

const notifications = ref([])
const unreadCount = ref(0)
const loading = ref(false)
const pollingInterval = ref(null)

export function useNotifications() {
  
  async function fetchNotifications() {
    try {
      const response = await api.get('/notifications')
      notifications.value = response.data
    } catch (err) {
      console.error('Erreur chargement notifications:', err)
    }
  }

  async function fetchUnreadCount() {
    try {
      const response = await api.get('/notifications/unread-count')
      unreadCount.value = response.data.count
    } catch (err) {
      console.error('Erreur compteur notifications:', err)
    }
  }

  async function markAsRead(notificationId) {
    try {
      await api.post(`/notifications/${notificationId}/read`)
      const notif = notifications.value.find(n => n.id === notificationId)
      if (notif) {
        notif.is_read = true
        notif.read_at = new Date().toISOString()
      }
      unreadCount.value = Math.max(0, unreadCount.value - 1)
    } catch (err) {
      console.error('Erreur marquage notification:', err)
    }
  }

  async function markAllAsRead() {
    try {
      await api.post('/notifications/mark-all-read')
      notifications.value.forEach(n => {
        n.is_read = true
        n.read_at = new Date().toISOString()
      })
      unreadCount.value = 0
    } catch (err) {
      console.error('Erreur marquage toutes notifications:', err)
    }
  }

  async function deleteNotification(notificationId) {
    try {
      await api.delete(`/notifications/${notificationId}`)
      const index = notifications.value.findIndex(n => n.id === notificationId)
      if (index > -1) {
        const notif = notifications.value[index]
        if (!notif.is_read) {
          unreadCount.value = Math.max(0, unreadCount.value - 1)
        }
        notifications.value.splice(index, 1)
      }
    } catch (err) {
      console.error('Erreur suppression notification:', err)
    }
  }

  async function clearReadNotifications() {
    try {
      await api.post('/notifications/clear-read')
      notifications.value = notifications.value.filter(n => !n.is_read)
    } catch (err) {
      console.error('Erreur suppression notifications lues:', err)
    }
  }

  async function generateNotifications() {
    try {
      loading.value = true
      await api.post('/notifications/generate')
      await fetchNotifications()
      await fetchUnreadCount()
    } catch (err) {
      console.error('Erreur génération notifications:', err)
    } finally {
      loading.value = false
    }
  }

  function startPolling(intervalMs = 60000) {
    stopPolling()
    fetchUnreadCount()
    pollingInterval.value = setInterval(fetchUnreadCount, intervalMs)
  }

  function stopPolling() {
    if (pollingInterval.value) {
      clearInterval(pollingInterval.value)
      pollingInterval.value = null
    }
  }

  const unreadNotifications = computed(() => 
    notifications.value.filter(n => !n.is_read)
  )

  const readNotifications = computed(() => 
    notifications.value.filter(n => n.is_read)
  )

  return {
    notifications,
    unreadCount,
    loading,
    unreadNotifications,
    readNotifications,
    fetchNotifications,
    fetchUnreadCount,
    markAsRead,
    markAllAsRead,
    deleteNotification,
    clearReadNotifications,
    generateNotifications,
    startPolling,
    stopPolling,
  }
}
