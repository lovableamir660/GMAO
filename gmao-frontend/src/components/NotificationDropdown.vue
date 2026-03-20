<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useNotifications } from '@/composables/useNotifications'

const router = useRouter()
const {
  notifications,
  unreadCount,
  fetchNotifications,
  fetchUnreadCount,
  markAsRead,
  markAllAsRead,
  startPolling,
  stopPolling,
} = useNotifications()

const isOpen = ref(false)
const dropdownRef = ref(null)

function toggleDropdown() {
  isOpen.value = !isOpen.value
  if (isOpen.value) {
    fetchNotifications()
  }
}

function closeDropdown() {
  isOpen.value = false
}

async function handleNotificationClick(notification) {
  if (!notification.is_read) {
    await markAsRead(notification.id)
  }
  closeDropdown()
  if (notification.link) {
    router.push(notification.link)
  }
}

async function handleMarkAllRead() {
  await markAllAsRead()
}

function handleClickOutside(event) {
  if (dropdownRef.value && !dropdownRef.value.contains(event.target)) {
    closeDropdown()
  }
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

onMounted(() => {
  fetchUnreadCount()
  startPolling(30000) // Toutes les 30 secondes
  document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  stopPolling()
  document.removeEventListener('click', handleClickOutside)
})
</script>

<template>
  <div class="notification-dropdown" ref="dropdownRef">
    <button class="notification-btn" @click="toggleDropdown">
      <span class="bell-icon">🔔</span>
      <span v-if="unreadCount > 0" class="badge">
        {{ unreadCount > 99 ? '99+' : unreadCount }}
      </span>
    </button>

    <Transition name="dropdown">
      <div v-if="isOpen" class="dropdown-menu">
        <div class="dropdown-header">
          <h3>Notifications</h3>
          <button 
            v-if="unreadCount > 0" 
            class="mark-all-btn"
            @click="handleMarkAllRead"
          >
            Tout marquer comme lu
          </button>
        </div>

        <div class="dropdown-body">
          <div v-if="notifications.length === 0" class="empty-state">
            <span class="empty-icon">🔔</span>
            <p>Aucune notification</p>
          </div>

          <div 
            v-for="notification in notifications.slice(0, 10)" 
            :key="notification.id"
            class="notification-item"
            :class="[
              getColorClass(notification.color),
              { unread: !notification.is_read }
            ]"
            @click="handleNotificationClick(notification)"
          >
            <span class="notification-icon">{{ notification.icon }}</span>
            <div class="notification-content">
              <div class="notification-title">{{ notification.title }}</div>
              <div class="notification-message">{{ notification.message }}</div>
              <div class="notification-time">{{ notification.time_ago }}</div>
            </div>
            <span v-if="!notification.is_read" class="unread-dot"></span>
          </div>
        </div>

        <div class="dropdown-footer">
          <router-link to="/notifications" @click="closeDropdown">
            Voir toutes les notifications
          </router-link>
        </div>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.notification-dropdown {
  position: relative;
}

.notification-btn {
  position: relative;
  background: none;
  border: none;
  cursor: pointer;
  padding: 8px;
  border-radius: 8px;
  transition: background 0.2s;
}

.notification-btn:hover {
  background: rgba(0, 0, 0, 0.05);
}

.bell-icon {
  font-size: 20px;
}

.badge {
  position: absolute;
  top: 2px;
  right: 2px;
  background: #e74c3c;
  color: white;
  font-size: 10px;
  font-weight: bold;
  padding: 2px 5px;
  border-radius: 10px;
  min-width: 18px;
  text-align: center;
}

.dropdown-menu {
  position: absolute;
  top: 100%;
  right: 0;
  width: 380px;
  max-height: 500px;
  background: white;
  border-radius: 12px;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
  z-index: 1000;
  overflow: hidden;
  margin-top: 8px;
}

.dropdown-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 15px 20px;
  border-bottom: 1px solid #eee;
}

.dropdown-header h3 {
  margin: 0;
  font-size: 16px;
  color: #2c3e50;
}

.mark-all-btn {
  background: none;
  border: none;
  color: #3498db;
  font-size: 12px;
  cursor: pointer;
}

.mark-all-btn:hover {
  text-decoration: underline;
}

.dropdown-body {
  max-height: 350px;
  overflow-y: auto;
}

.empty-state {
  padding: 40px 20px;
  text-align: center;
  color: #7f8c8d;
}

.empty-icon {
  font-size: 40px;
  opacity: 0.5;
}

.empty-state p {
  margin: 10px 0 0;
}

.notification-item {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 15px 20px;
  cursor: pointer;
  transition: background 0.2s;
  border-left: 3px solid transparent;
  position: relative;
}

.notification-item:hover {
  background: #f8f9fa;
}

.notification-item.unread {
  background: #f0f7ff;
}

.notification-item.notification-danger {
  border-left-color: #e74c3c;
}

.notification-item.notification-warning {
  border-left-color: #f39c12;
}

.notification-item.notification-success {
  border-left-color: #27ae60;
}

.notification-item.notification-info {
  border-left-color: #3498db;
}

.notification-icon {
  font-size: 24px;
  flex-shrink: 0;
}

.notification-content {
  flex: 1;
  min-width: 0;
}

.notification-title {
  font-weight: 600;
  color: #2c3e50;
  font-size: 13px;
  margin-bottom: 4px;
}

.notification-message {
  color: #7f8c8d;
  font-size: 12px;
  line-height: 1.4;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.notification-time {
  color: #95a5a6;
  font-size: 11px;
  margin-top: 5px;
}

.unread-dot {
  width: 8px;
  height: 8px;
  background: #3498db;
  border-radius: 50%;
  flex-shrink: 0;
}

.dropdown-footer {
  padding: 12px 20px;
  border-top: 1px solid #eee;
  text-align: center;
}

.dropdown-footer a {
  color: #3498db;
  text-decoration: none;
  font-size: 13px;
  font-weight: 500;
}

.dropdown-footer a:hover {
  text-decoration: underline;
}

/* Animation */
.dropdown-enter-active,
.dropdown-leave-active {
  transition: all 0.2s ease;
}

.dropdown-enter-from,
.dropdown-leave-to {
  opacity: 0;
  transform: translateY(-10px);
}
</style>
