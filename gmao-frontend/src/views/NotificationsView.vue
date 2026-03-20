<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useNotifications } from '@/composables/useNotifications'

const router = useRouter()
const {
  notifications,
  unreadCount,
  loading,
  fetchNotifications,
  markAsRead,
  markAllAsRead,
  deleteNotification,
  clearReadNotifications,
  generateNotifications,
} = useNotifications()

const activeTab = ref('all')
const filteredNotifications = ref([])

function filterNotifications() {
  switch (activeTab.value) {
    case 'unread':
      filteredNotifications.value = notifications.value.filter(n => !n.is_read)
      break
    case 'read':
      filteredNotifications.value = notifications.value.filter(n => n.is_read)
      break
    default:
      filteredNotifications.value = notifications.value
  }
}

function changeTab(tab) {
  activeTab.value = tab
  filterNotifications()
}

async function handleNotificationClick(notification) {
  if (!notification.is_read) {
    await markAsRead(notification.id)
    filterNotifications()
  }
  if (notification.link) {
    router.push(notification.link)
  }
}

async function handleDelete(notification, event) {
  event.stopPropagation()
  await deleteNotification(notification.id)
  filterNotifications()
}

async function handleMarkAllRead() {
  await markAllAsRead()
  filterNotifications()
}

async function handleClearRead() {
  if (confirm('Supprimer toutes les notifications lues ?')) {
    await clearReadNotifications()
    filterNotifications()
  }
}

async function handleGenerate() {
  await generateNotifications()
  filterNotifications()
}

function getColorClass(color) {
  const colors = {
    danger: 'notification-danger',
    warning: 'notification-warning',
    success: 'notification-success',
    info: 'notification-info',
  }
  return colors[color] || 'notification-info'
}

function getTypeLabel(type) {
  const labels = {
    stock_critical: 'Stock critique',
    wo_overdue: 'OT en retard',
    wo_assigned: 'OT assigné',
    pm_upcoming: 'Maintenance à venir',
    equipment_down: 'Équipement en panne',
    wo_completed: 'OT terminé',
    wo_created: 'Nouvel OT',
  }
  return labels[type] || type
}

onMounted(async () => {
  await fetchNotifications()
  filterNotifications()
})
</script>

<template>
  <div class="notifications-page">
    <header class="page-header">
      <div>
        <h1>🔔 Notifications</h1>
        <p class="subtitle">{{ unreadCount }} non lue(s)</p>
      </div>
      <div class="header-actions">
        <button class="btn btn-secondary" @click="handleGenerate" :disabled="loading">
          🔄 {{ loading ? 'Génération...' : 'Actualiser' }}
        </button>
        <button 
          v-if="unreadCount > 0" 
          class="btn btn-secondary" 
          @click="handleMarkAllRead"
        >
          ✓ Tout marquer comme lu
        </button>
        <button 
          v-if="notifications.some(n => n.is_read)" 
          class="btn btn-danger" 
          @click="handleClearRead"
        >
          🗑️ Supprimer les lues
        </button>
      </div>
    </header>

    <!-- Tabs -->
    <div class="tabs">
      <button 
        :class="{ active: activeTab === 'all' }" 
        @click="changeTab('all')"
      >
        Toutes ({{ notifications.length }})
      </button>
      <button 
        :class="{ active: activeTab === 'unread' }" 
        @click="changeTab('unread')"
      >
        Non lues ({{ unreadCount }})
      </button>
      <button 
        :class="{ active: activeTab === 'read' }" 
        @click="changeTab('read')"
      >
        Lues ({{ notifications.length - unreadCount }})
      </button>
    </div>

    <!-- Liste des notifications -->
    <div class="notifications-list">
      <div v-if="loading" class="loading-state">
        <div class="spinner"></div>
        <p>Chargement...</p>
      </div>

      <div v-else-if="filteredNotifications.length === 0" class="empty-state">
        <span class="empty-icon">🔔</span>
        <h3>Aucune notification</h3>
        <p v-if="activeTab === 'unread'">Vous êtes à jour !</p>
        <p v-else-if="activeTab === 'read'">Aucune notification lue</p>
        <p v-else>Aucune notification pour le moment</p>
      </div>

      <div 
        v-for="notification in filteredNotifications" 
        :key="notification.id"
        class="notification-card"
        :class="[
          getColorClass(notification.color),
          { unread: !notification.is_read }
        ]"
        @click="handleNotificationClick(notification)"
      >
        <div class="notification-icon">{{ notification.icon }}</div>
        
        <div class="notification-body">
          <div class="notification-header">
            <span class="notification-title">{{ notification.title }}</span>
            <span class="notification-type">{{ getTypeLabel(notification.type) }}</span>
          </div>
          <p class="notification-message">{{ notification.message }}</p>
          <div class="notification-footer">
            <span class="notification-time">{{ notification.time_ago }}</span>
            <span v-if="notification.link" class="notification-link">
              Cliquer pour voir →
            </span>
          </div>
        </div>

        <div class="notification-actions">
          <button 
            v-if="!notification.is_read"
            class="action-btn read-btn"
            @click.stop="markAsRead(notification.id); filterNotifications()"
            title="Marquer comme lu"
          >
            ✓
          </button>
          <button 
            class="action-btn delete-btn"
            @click="handleDelete(notification, $event)"
            title="Supprimer"
          >
            ✕
          </button>
        </div>

        <span v-if="!notification.is_read" class="unread-indicator"></span>
      </div>
    </div>
  </div>
</template>

<style scoped>
.notifications-page {
  padding: 30px;
  max-width: 900px;
  margin: 0 auto;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 25px;
  flex-wrap: wrap;
  gap: 15px;
}

.page-header h1 {
  font-size: 28px;
  color: #2c3e50;
  margin-bottom: 5px;
}

.subtitle {
  color: #7f8c8d;
  font-size: 14px;
}

.header-actions {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.btn {
  padding: 10px 16px;
  border: none;
  border-radius: 8px;
  font-weight: 500;
  cursor: pointer;
  font-size: 13px;
  transition: all 0.2s;
}

.btn-secondary {
  background: #ecf0f1;
  color: #2c3e50;
}

.btn-secondary:hover {
  background: #d5dbdb;
}

.btn-danger {
  background: #fee;
  color: #e74c3c;
}

.btn-danger:hover {
  background: #fdd;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* Tabs */
.tabs {
  display: flex;
  gap: 5px;
  margin-bottom: 20px;
  background: white;
  padding: 5px;
  border-radius: 10px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.tabs button {
  padding: 12px 20px;
  border: none;
  background: transparent;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 500;
  color: #7f8c8d;
  transition: all 0.2s;
}

.tabs button:hover {
  background: #f8f9fa;
  color: #2c3e50;
}

.tabs button.active {
  background: #3498db;
  color: white;
}

/* Notifications List */
.notifications-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.notification-card {
  display: flex;
  align-items: flex-start;
  gap: 15px;
  padding: 20px;
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
  cursor: pointer;
  transition: all 0.2s;
  border-left: 4px solid transparent;
  position: relative;
}

.notification-card:hover {
  box-shadow: 0 4px 15px rgba(0,0,0,0.1);
  transform: translateY(-2px);
}

.notification-card.unread {
  background: linear-gradient(to right, #f0f7ff, white);
}

.notification-card.notification-danger {
  border-left-color: #e74c3c;
}

.notification-card.notification-warning {
  border-left-color: #f39c12;
}

.notification-card.notification-success {
  border-left-color: #27ae60;
}

.notification-card.notification-info {
  border-left-color: #3498db;
}

.notification-icon {
  font-size: 32px;
  flex-shrink: 0;
}

.notification-body {
  flex: 1;
  min-width: 0;
}

.notification-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.notification-title {
  font-weight: 600;
  color: #2c3e50;
  font-size: 15px;
}

.notification-type {
  font-size: 11px;
  padding: 3px 8px;
  background: #ecf0f1;
  border-radius: 10px;
  color: #7f8c8d;
}

.notification-message {
  color: #555;
  font-size: 14px;
  line-height: 1.5;
  margin: 0 0 10px;
}

.notification-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.notification-time {
  color: #95a5a6;
  font-size: 12px;
}

.notification-link {
  color: #3498db;
  font-size: 12px;
}

.notification-actions {
  display: flex;
  gap: 5px;
  opacity: 0;
  transition: opacity 0.2s;
}

.notification-card:hover .notification-actions {
  opacity: 1;
}

.action-btn {
  width: 30px;
  height: 30px;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 14px;
  transition: all 0.2s;
}

.read-btn {
  background: #d4edda;
  color: #155724;
}

.read-btn:hover {
  background: #c3e6cb;
}

.delete-btn {
  background: #f8d7da;
  color: #721c24;
}

.delete-btn:hover {
  background: #f5c6cb;
}

.unread-indicator {
  position: absolute;
  top: 20px;
  right: 20px;
  width: 10px;
  height: 10px;
  background: #3498db;
  border-radius: 50%;
}

/* Empty & Loading States */
.empty-state,
.loading-state {
  text-align: center;
  padding: 60px 20px;
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

.empty-state p {
  color: #7f8c8d;
  margin: 0;
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

@media (max-width: 600px) {
  .notification-card {
    flex-wrap: wrap;
  }
  
  .notification-actions {
    opacity: 1;
    width: 100%;
    justify-content: flex-end;
    margin-top: 10px;
  }
}
</style>
