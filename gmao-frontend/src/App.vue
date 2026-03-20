<script setup>
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import NotificationDropdown from '@/components/NotificationDropdown.vue'

const router = useRouter()
const authStore = useAuthStore()

const isAuthenticated = computed(() => authStore.isAuthenticated)
const switchingSite = ref(false)

// Helper : l'utilisateur a au moins UNE des permissions listées
function anyPerm(...perms) {
  return perms.some(p => authStore.hasPermission(p))
}

// Visibilité des sections — accepter view_any OU view_own OU view
const showTechnique = computed(() =>
  anyPerm(
    'equipment:view_any', 'equipment:view_own', 'equipment:view',
    'location:view_any', 'location:view_own', 'location:view'
  )
)

const showMaintenance = computed(() =>
  anyPerm(
    'intervention_request:view_any', 'intervention_request:view_own', 'intervention_request:view',
    'workorder:view_any', 'workorder:view_own', 'workorder:view',
    'preventive:view_any', 'preventive:view_own', 'preventive:view'
  )
)

const showTransport = computed(() =>
  anyPerm(
    'driver:view_any', 'driver:view_own', 'driver:view',
    'truck:view_any', 'truck:view_own', 'truck:view',
    'assignment:view_any', 'assignment:view_own', 'assignment:view',
    'client:view_any', 'client:view_own', 'client:view',
    'habilitation:view_any', 'habilitation:view_own', 'habilitation:view'
  )
)

const showStock = computed(() =>
  anyPerm('part:view_any', 'part:view_own', 'part:view')
)

const showAnalyses = computed(() =>
  anyPerm('report:view_any', 'report:view_own', 'report:view')
)

const showAdmin = computed(() =>
  anyPerm(
    'site:view_any', 'user:view_any', 'role:view_any',
    'setting:view_any', 'setting:view'
  )
)

// Changement de site
async function onSwitchSite(siteId) {
  if (siteId === authStore.user?.current_site_id) return
  switchingSite.value = true
  const result = await authStore.switchSite(siteId)
  switchingSite.value = false
  if (!result.success) {
    alert(result.message)
  }
}


async function handleLogout() {
  await authStore.logout()
  router.push('/login')
}
</script>

<template>
  <div class="app" :class="{ 'has-sidebar': isAuthenticated }">
    <!-- Sidebar -->
    <aside class="sidebar" v-if="isAuthenticated">
      <div class="sidebar-header">
        <h1 class="logo">GMAO</h1>
        <span class="version">v1.0</span>
      </div>

      <!-- Sélecteur de site multi-parcs -->
      <div class="sidebar-site">
        <span class="site-label">🏢 Site actif</span>

        <!-- Plusieurs sites → sélecteur -->
        <select v-if="authStore.authorizedSites.length > 1" :value="authStore.user?.current_site_id"
          @change="onSwitchSite(Number($event.target.value))" :disabled="switchingSite" class="site-select">
          <option v-for="site in authStore.authorizedSites" :key="site.id" :value="site.id">
            {{ site.name }}
          </option>
        </select>

        <!-- Un seul site → texte simple -->
        <span v-else-if="authStore.currentSite" class="site-name">
          {{ authStore.currentSite.name }}
        </span>
        <span v-else class="site-name">Aucun site</span>

        <span v-if="switchingSite" class="site-switching">Changement en cours...</span>
      </div>

      <nav class="sidebar-nav">
        <!-- Principal (toujours visible) -->
        <div class="nav-section">
          <span class="nav-section-title">Principal</span>
          <router-link to="/" class="nav-item">
            <span class="nav-icon">📊</span>
            <span>Tableau de bord</span>
          </router-link>
        </div>

        <!-- Gestion technique -->
        <div class="nav-section" v-if="showTechnique">
          <span class="nav-section-title">Gestion technique</span>
          <router-link to="/equipments" class="nav-item"
            v-if="anyPerm('equipment:view_any', 'equipment:view_own', 'equipment:view')">
            <span class="nav-icon">⚙️</span>
            <span>Équipements</span>
          </router-link>
          <router-link to="/locations" class="nav-item"
            v-if="anyPerm('location:view_any', 'location:view_own', 'location:view')">
            <span class="nav-icon">📍</span>
            <span>Emplacements</span>
          </router-link>
        </div>

        <!-- Maintenance -->
        <div class="nav-section" v-if="showMaintenance">
          <span class="nav-section-title">Maintenance</span>
          <router-link to="/intervention-requests" class="nav-item"
            v-if="anyPerm('intervention_request:view_any', 'intervention_request:view_own', 'intervention_request:view')">
            <span class="nav-icon">📋</span>
            <span>Demandes (DI)</span>
          </router-link>
          <router-link to="/work-orders" class="nav-item"
            v-if="anyPerm('workorder:view_any', 'workorder:view_own', 'workorder:view')">
            <span class="nav-icon">🔧</span>
            <span>Interventions</span>
          </router-link>
          <router-link to="/preventive-maintenance" class="nav-item"
            v-if="anyPerm('preventive:view_any', 'preventive:view_own', 'preventive:view')">
            <span class="nav-icon">📅</span>
            <span>Préventive</span>
          </router-link>
        </div>

        <!-- Transport -->
        <div class="nav-section" v-if="showTransport">
          <span class="nav-section-title">Transport</span>
          <router-link to="/drivers" class="nav-item"
            v-if="anyPerm('driver:view_any', 'driver:view_own', 'driver:view')">
            <span class="nav-icon">👷</span>
            <span>Chauffeurs</span>
          </router-link>
          <router-link to="/trucks" class="nav-item" v-if="anyPerm('truck:view_any', 'truck:view_own', 'truck:view')">
            <span class="nav-icon">🚛</span>
            <span>Camions</span>
          </router-link>
          <router-link to="/assignments" class="nav-item"
            v-if="anyPerm('assignment:view_any', 'assignment:view_own', 'assignment:view')">
            <span class="nav-icon">🔄</span>
            <span>Attributions</span>
          </router-link>
          <router-link to="/clients" class="nav-item"
            v-if="anyPerm('client:view_any', 'client:view_own', 'client:view')">
            <span class="nav-icon">🏢</span>
            <span>Clients</span>
          </router-link>
          <router-link to="/habilitations" class="nav-item"
            v-if="anyPerm('habilitation:view_any', 'habilitation:view_own', 'habilitation:view')">
            <span class="nav-icon">📜</span>
            <span>Habilitations</span>
          </router-link>
        </div>

        <!-- Stock -->
        <div class="nav-section" v-if="showStock">
          <span class="nav-section-title">Stock</span>
          <router-link to="/parts" class="nav-item" v-if="anyPerm('part:view_any', 'part:view_own', 'part:view')">
            <span class="nav-icon">🔩</span>
            <span>Pièces détachées</span>
          </router-link>
        </div>

        <!-- Analyses -->
        <div class="nav-section" v-if="showAnalyses">
          <span class="nav-section-title">Analyses</span>
          <router-link to="/reports" class="nav-item"
            v-if="anyPerm('report:view_any', 'report:view_own', 'report:view')">
            <span class="nav-icon">📊</span>
            <span>Rapports</span>
          </router-link>
        </div>

        <!-- Administration -->
        <div class="nav-section" v-if="showAdmin">
          <span class="nav-section-title">Administration</span>
          <router-link to="/sites" class="nav-item" v-if="authStore.hasPermission('site:view_any')">
            <span class="nav-icon">🏭</span>
            <span>Sites</span>
          </router-link>
          <router-link to="/users" class="nav-item" v-if="authStore.hasPermission('user:view_any')">
            <span class="nav-icon">👥</span>
            <span>Utilisateurs</span>
          </router-link>
          <router-link to="/roles" class="nav-item" v-if="authStore.hasPermission('role:view_any')">
            <span class="nav-icon">🔑</span>
            <span>Rôles & Permissions</span>
          </router-link>
          <router-link to="/settings" class="nav-item"
            v-if="anyPerm('setting:view_any', 'setting:view')">
            <span class="nav-icon">⚙️</span>
            <span>Paramètres</span>
          </router-link>
        </div>
      </nav>

      <div class="sidebar-footer">
        <div class="user-card">
          <div class="user-avatar">
            {{ authStore.user?.name?.charAt(0).toUpperCase() }}
          </div>
          <div class="user-details">
            <span class="user-name">{{ authStore.user?.name }}</span>
            <span class="user-role">{{ authStore.roles[0] || 'Utilisateur' }}</span>
          </div>
        </div>
        <button @click="handleLogout" class="logout-btn">
          🚪 Déconnexion
        </button>
      </div>
    </aside>

    <!-- Contenu principal -->
    <main class="main-content">
      <header class="top-header" v-if="isAuthenticated">
        <div class="header-left">
          <span class="current-date">{{ new Date().toLocaleDateString('fr-FR', {
            weekday: 'long', year: 'numeric',
            month: 'long', day: 'numeric'
          }) }}</span>
        </div>
        <div class="header-right">
          <NotificationDropdown />
          <div class="header-user">
            <span>{{ authStore.user?.name }}</span>
          </div>
        </div>
      </header>

      <div class="page-content">
        <router-view :key="authStore.user?.current_site_id" />
      </div>
    </main>
  </div>
</template>

<style scoped>
.app {
  min-height: 100vh;
  display: flex;
}

.sidebar {
  width: 260px;
  background: linear-gradient(180deg, #1a252f 0%, #2c3e50 100%);
  color: white;
  display: flex;
  flex-direction: column;
  position: fixed;
  top: 0;
  left: 0;
  height: 100vh;
  z-index: 1000;
  overflow-y: auto;
}

.sidebar-header {
  padding: 20px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.logo {
  font-size: 24px;
  font-weight: bold;
  color: #3498db;
  margin: 0;
}

.version {
  font-size: 11px;
  background: rgba(255, 255, 255, 0.1);
  padding: 2px 8px;
  border-radius: 10px;
  color: rgba(255, 255, 255, 0.6);
}

.sidebar-site {
  padding: 15px 20px;
  background: rgba(52, 152, 219, 0.2);
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.site-label {
  display: block;
  font-size: 10px;
  text-transform: uppercase;
  color: rgba(255, 255, 255, 0.5);
  margin-bottom: 6px;
}

.site-name {
  font-size: 14px;
  font-weight: 600;
  color: #3498db;
}

.site-select {
  width: 100%;
  padding: 8px 10px;
  background: rgba(255, 255, 255, 0.1);
  border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: 8px;
  color: white;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  appearance: none;
  -webkit-appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='white'%3E%3Cpath d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 10px center;
}

.site-select:focus {
  outline: none;
  border-color: #3498db;
  background-color: rgba(255, 255, 255, 0.15);
}

.site-select:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.site-select option {
  background: #1a252f;
  color: white;
}

.site-switching {
  display: block;
  font-size: 11px;
  color: #3498db;
  margin-top: 4px;
  animation: pulse 1s infinite;
}

@keyframes pulse {

  0%,
  100% {
    opacity: 1;
  }

  50% {
    opacity: 0.4;
  }
}

.sidebar-nav {
  flex: 1;
  padding: 15px 0;
  overflow-y: auto;
}

.nav-section {
  margin-bottom: 20px;
}

.nav-section-title {
  display: block;
  padding: 0 20px;
  font-size: 11px;
  text-transform: uppercase;
  color: rgba(255, 255, 255, 0.4);
  margin-bottom: 8px;
  letter-spacing: 0.5px;
}

.nav-item {
  display: flex;
  align-items: center;
  padding: 12px 20px;
  color: rgba(255, 255, 255, 0.7);
  text-decoration: none;
  transition: all 0.2s;
  border-left: 3px solid transparent;
}

.nav-item:hover {
  background: rgba(255, 255, 255, 0.05);
  color: white;
  text-decoration: none;
}

.nav-item.router-link-active {
  background: rgba(52, 152, 219, 0.2);
  color: #3498db;
  border-left-color: #3498db;
}

.nav-item.disabled {
  opacity: 0.5;
  pointer-events: none;
}

.nav-icon {
  margin-right: 12px;
  font-size: 18px;
}

.badge-soon {
  margin-left: auto;
  font-size: 9px;
  background: rgba(255, 255, 255, 0.1);
  padding: 2px 6px;
  border-radius: 8px;
  color: rgba(255, 255, 255, 0.5);
}

.sidebar-footer {
  padding: 15px;
  border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.user-card {
  display: flex;
  align-items: center;
  padding: 10px;
  background: rgba(255, 255, 255, 0.05);
  border-radius: 8px;
  margin-bottom: 10px;
}

.user-avatar {
  width: 40px;
  height: 40px;
  background: #3498db;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: bold;
  font-size: 18px;
  margin-right: 12px;
}

.user-details {
  display: flex;
  flex-direction: column;
}

.user-name {
  font-size: 14px;
  font-weight: 600;
}

.user-role {
  font-size: 11px;
  color: rgba(255, 255, 255, 0.5);
}

.logout-btn {
  width: 100%;
  padding: 10px;
  background: rgba(231, 76, 60, 0.2);
  color: #e74c3c;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 13px;
  transition: background 0.2s;
}

.logout-btn:hover {
  background: rgba(231, 76, 60, 0.3);
}

.main-content {
  flex: 1;
  min-height: 100vh;
  background: #f5f7fa;
  display: flex;
  flex-direction: column;
}

.app.has-sidebar .main-content {
  margin-left: 260px;
}

.top-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 15px 30px;
  background: white;
  border-bottom: 1px solid #eee;
  position: sticky;
  top: 0;
  z-index: 100;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 20px;
}

.current-date {
  color: #7f8c8d;
  font-size: 14px;
  text-transform: capitalize;
}

.header-right {
  display: flex;
  align-items: center;
  gap: 20px;
}

.header-user {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 15px;
  background: #f8f9fa;
  border-radius: 8px;
  font-size: 14px;
  color: #2c3e50;
}

.page-content {
  flex: 1;
}
</style>
