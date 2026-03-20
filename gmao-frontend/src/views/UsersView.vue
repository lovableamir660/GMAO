<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
const users = ref([])
const sites = ref([])
const roles = ref([])
const loading = ref(false)
const error = ref('')
const successMessage = ref('')
const searchQuery = ref('')
const filterSite = ref('')
const filterRole = ref('')

// Modals
const showModal = ref(false)
const showRolesModal = ref(false)
const showPasswordModal = ref(false)
const showDetailModal = ref(false)
const showDeleteConfirm = ref(false)

const editingUser = ref(null)
const selectedUser = ref(null)
const saving = ref(false)

const form = ref({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  current_site_id: '',
  roles: [],
})

const rolesForm = ref({
  site_id: '',
  roles: [],
})

const passwordForm = ref({
  current_password: '',
  password: '',
  password_confirmation: '',
})

// Filtrage des utilisateurs
const filteredUsers = computed(() => {
  return users.value.filter(user => {
    const matchSearch = !searchQuery.value || 
      user.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      user.email.toLowerCase().includes(searchQuery.value.toLowerCase())
    
    const matchSite = !filterSite.value || 
      user.current_site_id === parseInt(filterSite.value)
    
    const matchRole = !filterRole.value || 
      user.roles?.some(r => r.name === filterRole.value)
    
    return matchSearch && matchSite && matchRole
  })
})

// Couleurs des rôles
const roleColors = {
  SuperAdmin: { bg: '#9b59b6', text: 'white' },
  AdminSite: { bg: '#3498db', text: 'white' },
  Planificateur: { bg: '#1abc9c', text: 'white' },
  Technicien: { bg: '#e67e22', text: 'white' },
  Magasinier: { bg: '#27ae60', text: 'white' },
  Lecteur: { bg: '#95a5a6', text: 'white' },
}

function getRoleStyle(roleName) {
  const color = roleColors[roleName] || { bg: '#bdc3c7', text: '#2c3e50' }
  return {
    backgroundColor: color.bg,
    color: color.text,
  }
}

function getInitials(name) {
  return name
    .split(' ')
    .map(n => n[0])
    .join('')
    .toUpperCase()
    .substring(0, 2)
}

function getAvatarColor(name) {
  const colors = ['#3498db', '#9b59b6', '#e74c3c', '#27ae60', '#f39c12', '#1abc9c', '#e67e22', '#2c3e50']
  let hash = 0
  for (let i = 0; i < name.length; i++) {
    hash = name.charCodeAt(i) + ((hash << 5) - hash)
  }
  return colors[Math.abs(hash) % colors.length]
}

function showSuccess(message) {
  successMessage.value = message
  setTimeout(() => { successMessage.value = '' }, 3000)
}

function showError(message) {
  error.value = message
  setTimeout(() => { error.value = '' }, 5000)
}

async function fetchUsers() {
  loading.value = true
  error.value = ''
  try {
    const response = await api.get('/users?per_page=100')
    users.value = response.data.data
  } catch (err) {
    showError('Erreur lors du chargement des utilisateurs')
    console.error(err)
  } finally {
    loading.value = false
  }
}

async function fetchSites() {
  try {
    const response = await api.get('/sites?per_page=100')
    sites.value = response.data.data
  } catch (err) {
    console.error(err)
  }
}

async function fetchRoles() {
  try {
    const response = await api.get('/roles')
    roles.value = response.data
  } catch (err) {
    console.error(err)
  }
}

function openCreateModal() {
  editingUser.value = null
  form.value = {
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    current_site_id: authStore.user.current_site_id,
    roles: ['Lecteur'],
  }
  showModal.value = true
}

function openEditModal(user) {
  editingUser.value = user
  form.value = {
    name: user.name,
    email: user.email,
    password: '',
    password_confirmation: '',
    current_site_id: user.current_site_id,
    roles: user.roles?.map(r => r.name) || [],
  }
  showModal.value = true
}

function openDetailModal(user) {
  selectedUser.value = user
  showDetailModal.value = true
}

async function saveUser() {
  saving.value = true
  error.value = ''
  try {
    if (editingUser.value) {
      const payload = { ...form.value }
      if (!payload.password) {
        delete payload.password
        delete payload.password_confirmation
      }
      await api.put(`/users/${editingUser.value.id}`, payload)
      showSuccess('Utilisateur modifié avec succès')
    } else {
      await api.post('/users', form.value)
      showSuccess('Utilisateur créé avec succès')
    }
    showModal.value = false
    fetchUsers()
  } catch (err) {
    showError(err.response?.data?.message || 'Erreur lors de la sauvegarde')
  } finally {
    saving.value = false
  }
}

function confirmDelete(user) {
  selectedUser.value = user
  showDeleteConfirm.value = true
}

async function deleteUser() {
  try {
    await api.delete(`/users/${selectedUser.value.id}`)
    showDeleteConfirm.value = false
    showSuccess('Utilisateur supprimé avec succès')
    fetchUsers()
  } catch (err) {
    showError(err.response?.data?.message || 'Erreur lors de la suppression')
  }
}

function openRolesModal(user) {
  selectedUser.value = user
  rolesForm.value = {
    site_id: user.current_site_id,
    roles: user.roles?.map(r => r.name) || [],
  }
  showRolesModal.value = true
}

async function updateRoles() {
  saving.value = true
  try {
    await api.post(`/users/${selectedUser.value.id}/roles`, rolesForm.value)
    showRolesModal.value = false
    showSuccess('Rôles mis à jour avec succès')
    fetchUsers()
  } catch (err) {
    showError(err.response?.data?.message || 'Erreur lors de la mise à jour des rôles')
  } finally {
    saving.value = false
  }
}

function openPasswordModal(user) {
  selectedUser.value = user
  passwordForm.value = {
    current_password: '',
    password: '',
    password_confirmation: '',
  }
  showPasswordModal.value = true
}

async function changePassword() {
  saving.value = true
  try {
    const payload = { ...passwordForm.value }
    if (selectedUser.value.id !== authStore.user.id) {
      delete payload.current_password
    }
    await api.post(`/users/${selectedUser.value.id}/change-password`, payload)
    showPasswordModal.value = false
    showSuccess('Mot de passe changé avec succès')
  } catch (err) {
    showError(err.response?.data?.message || 'Erreur lors du changement de mot de passe')
  } finally {
    saving.value = false
  }
}

function toggleRole(roleName, formRef) {
  const index = formRef.roles.indexOf(roleName)
  if (index > -1) {
    formRef.roles.splice(index, 1)
  } else {
    formRef.roles.push(roleName)
  }
}

function clearFilters() {
  searchQuery.value = ''
  filterSite.value = ''
  filterRole.value = ''
}

onMounted(() => {
  fetchUsers()
  fetchSites()
  fetchRoles()
})
</script>

<template>
  <div class="users-page">
    <header class="page-header">
      <div>
        <h1>👥 Gestion des utilisateurs</h1>
        <p class="subtitle">{{ filteredUsers.length }} utilisateur(s) sur {{ users.length }}</p>
      </div>
      <button class="btn btn-success" @click="openCreateModal" v-if="authStore.hasPermission('user:create')">
        <span class="btn-icon">+</span> Nouvel utilisateur
      </button>
    </header>

    <!-- Messages -->
    <div class="alert alert-success" v-if="successMessage">
      ✅ {{ successMessage }}
    </div>
    <div class="alert alert-error" v-if="error">
      ❌ {{ error }}
    </div>

    <!-- Filtres -->
    <div class="filters-card">
      <div class="filters-row">
        <div class="filter-group search-group">
          <span class="filter-icon">🔍</span>
          <input 
            type="text" 
            v-model="searchQuery" 
            placeholder="Rechercher par nom ou email..."
            class="search-input"
          />
        </div>
        
        <div class="filter-group">
          <select v-model="filterSite">
            <option value="">Tous les sites</option>
            <option v-for="site in sites" :key="site.id" :value="site.id">
              {{ site.name }}
            </option>
          </select>
        </div>

        <div class="filter-group">
          <select v-model="filterRole">
            <option value="">Tous les rôles</option>
            <option v-for="role in roles" :key="role.id" :value="role.name">
              {{ role.name }}
            </option>
          </select>
        </div>

        <button class="btn btn-secondary btn-sm" @click="clearFilters" v-if="searchQuery || filterSite || filterRole">
          ✕ Effacer
        </button>
      </div>
    </div>

    <!-- Liste des utilisateurs -->
    <div class="users-grid" v-if="!loading && filteredUsers.length">
      <div class="user-card" v-for="user in filteredUsers" :key="user.id">
        <div class="user-card-header">
          <div class="user-avatar" :style="{ backgroundColor: getAvatarColor(user.name) }">
            {{ getInitials(user.name) }}
          </div>
          <div class="user-info">
            <h3 class="user-name">
              {{ user.name }}
              <span class="badge badge-you" v-if="user.id === authStore.user.id">Vous</span>
            </h3>
            <p class="user-email">{{ user.email }}</p>
          </div>
        </div>

        <div class="user-card-body">
          <div class="user-meta">
            <div class="meta-item">
              <span class="meta-label">🏭 Site</span>
              <span class="meta-value">{{ user.current_site?.name || 'Non défini' }}</span>
            </div>
          </div>

          <div class="user-roles">
            <span 
              v-for="role in user.roles" 
              :key="role.id" 
              class="role-badge"
              :style="getRoleStyle(role.name)"
            >
              {{ role.name }}
            </span>
            <span v-if="!user.roles?.length" class="no-role">Aucun rôle</span>
          </div>
        </div>

        <div class="user-card-footer">
          <button class="action-btn view" @click="openDetailModal(user)" title="Voir détails">
            👁️
          </button>
          <button class="action-btn edit" @click="openEditModal(user)" v-if="authStore.hasPermission('user:update')" title="Modifier">
            ✏️
          </button>
          <button class="action-btn roles" @click="openRolesModal(user)" v-if="authStore.hasPermission('user:assign_roles')" title="Gérer les rôles">
            🔑
          </button>
          <button class="action-btn password" @click="openPasswordModal(user)" title="Mot de passe">
            🔒
          </button>
          <button 
            class="action-btn delete" 
            @click="confirmDelete(user)" 
            v-if="authStore.hasPermission('user:delete') && user.id !== authStore.user.id"
            title="Supprimer"
          >
            🗑️
          </button>
        </div>
      </div>
    </div>

    <!-- Loading -->
    <div class="loading-state" v-if="loading">
      <div class="spinner"></div>
      <p>Chargement des utilisateurs...</p>
    </div>

    <!-- Empty state -->
    <div class="empty-state" v-if="!loading && !filteredUsers.length">
      <div class="empty-icon">👥</div>
      <h3>Aucun utilisateur trouvé</h3>
      <p v-if="searchQuery || filterSite || filterRole">Essayez de modifier vos filtres</p>
      <p v-else>Commencez par créer un utilisateur</p>
    </div>

    <!-- Modal Création/Édition -->
    <div class="modal-overlay" v-if="showModal" @click.self="showModal = false">
      <div class="modal">
        <div class="modal-header">
          <h2>{{ editingUser ? '✏️ Modifier l\'utilisateur' : '➕ Nouvel utilisateur' }}</h2>
          <button class="close-btn" @click="showModal = false">&times;</button>
        </div>
        <form @submit.prevent="saveUser" class="modal-body">
          <div class="form-row">
            <div class="form-group">
              <label>Nom complet *</label>
              <input type="text" v-model="form.name" required placeholder="Ex: Ahmed Benali" />
            </div>
            <div class="form-group">
              <label>Email *</label>
              <input type="email" v-model="form.email" required placeholder="email@exemple.com" />
            </div>
          </div>

          <div class="form-group">
            <label>Site principal *</label>
            <select v-model="form.current_site_id" required>
              <option value="">-- Sélectionner un site --</option>
              <option v-for="site in sites" :key="site.id" :value="site.id">
                {{ site.name }}
              </option>
            </select>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>{{ editingUser ? 'Nouveau mot de passe' : 'Mot de passe *' }}</label>
              <input 
                type="password" 
                v-model="form.password" 
                :required="!editingUser"
                minlength="8"
                placeholder="Minimum 8 caractères"
              />
              <small v-if="editingUser" class="form-hint">Laisser vide pour ne pas changer</small>
            </div>
            <div class="form-group">
              <label>Confirmer le mot de passe {{ editingUser ? '' : '*' }}</label>
              <input 
                type="password" 
                v-model="form.password_confirmation" 
                :required="!!form.password"
                placeholder="Retaper le mot de passe"
              />
            </div>
          </div>

          <div class="form-group">
            <label>Rôles attribués</label>
            <div class="roles-grid">
              <label 
                v-for="role in roles" 
                :key="role.id" 
                class="role-checkbox"
                :class="{ selected: form.roles.includes(role.name) }"
              >
                <input 
                  type="checkbox" 
                  :checked="form.roles.includes(role.name)"
                  @change="toggleRole(role.name, form)"
                />
                <span class="role-badge-small" :style="getRoleStyle(role.name)">
                  {{ role.name }}
                </span>
              </label>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" @click="showModal = false">Annuler</button>
            <button type="submit" class="btn btn-primary" :disabled="saving">
              {{ saving ? 'Enregistrement...' : (editingUser ? 'Modifier' : 'Créer') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal Détails -->
    <div class="modal-overlay" v-if="showDetailModal" @click.self="showDetailModal = false">
      <div class="modal">
        <div class="modal-header">
          <h2>👤 Détails utilisateur</h2>
          <button class="close-btn" @click="showDetailModal = false">&times;</button>
        </div>
        <div class="modal-body" v-if="selectedUser">
          <div class="detail-header">
            <div class="detail-avatar" :style="{ backgroundColor: getAvatarColor(selectedUser.name) }">
              {{ getInitials(selectedUser.name) }}
            </div>
            <div>
              <h3>{{ selectedUser.name }}</h3>
              <p>{{ selectedUser.email }}</p>
            </div>
          </div>

          <div class="detail-section">
            <h4>Informations</h4>
            <div class="detail-grid">
              <div class="detail-item">
                <span class="detail-label">Site actuel</span>
                <span class="detail-value">{{ selectedUser.current_site?.name || '-' }}</span>
              </div>
              <div class="detail-item">
                <span class="detail-label">Créé le</span>
                <span class="detail-value">{{ new Date(selectedUser.created_at).toLocaleDateString('fr-FR') }}</span>
              </div>
            </div>
          </div>

          <div class="detail-section">
            <h4>Rôles</h4>
            <div class="detail-roles">
              <span 
                v-for="role in selectedUser.roles" 
                :key="role.id" 
                class="role-badge"
                :style="getRoleStyle(role.name)"
              >
                {{ role.name }}
              </span>
              <span v-if="!selectedUser.roles?.length" class="no-role">Aucun rôle attribué</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Gestion des rôles -->
    <div class="modal-overlay" v-if="showRolesModal" @click.self="showRolesModal = false">
      <div class="modal">
        <div class="modal-header">
          <h2>🔑 Gérer les rôles</h2>
          <button class="close-btn" @click="showRolesModal = false">&times;</button>
        </div>
        <form @submit.prevent="updateRoles" class="modal-body" v-if="selectedUser">
          <div class="detail-header compact">
            <div class="detail-avatar small" :style="{ backgroundColor: getAvatarColor(selectedUser.name) }">
              {{ getInitials(selectedUser.name) }}
            </div>
            <div>
              <h3>{{ selectedUser.name }}</h3>
              <p>{{ selectedUser.email }}</p>
            </div>
          </div>

          <div class="form-group">
            <label>Site concerné</label>
            <select v-model="rolesForm.site_id" required>
              <option v-for="site in sites" :key="site.id" :value="site.id">
                {{ site.name }}
              </option>
            </select>
          </div>

          <div class="form-group">
            <label>Rôles pour ce site</label>
            <div class="roles-grid">
              <label 
                v-for="role in roles" 
                :key="role.id" 
                class="role-checkbox"
                :class="{ selected: rolesForm.roles.includes(role.name) }"
              >
                <input 
                  type="checkbox" 
                  :checked="rolesForm.roles.includes(role.name)"
                  @change="toggleRole(role.name, rolesForm)"
                />
                <span class="role-badge-small" :style="getRoleStyle(role.name)">
                  {{ role.name }}
                </span>
              </label>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" @click="showRolesModal = false">Annuler</button>
            <button type="submit" class="btn btn-primary" :disabled="saving">
              {{ saving ? 'Mise à jour...' : 'Mettre à jour' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal Changement mot de passe -->
    <div class="modal-overlay" v-if="showPasswordModal" @click.self="showPasswordModal = false">
      <div class="modal modal-small">
        <div class="modal-header">
          <h2>🔒 Changer le mot de passe</h2>
          <button class="close-btn" @click="showPasswordModal = false">&times;</button>
        </div>
        <form @submit.prevent="changePassword" class="modal-body" v-if="selectedUser">
          <div class="detail-header compact">
            <div class="detail-avatar small" :style="{ backgroundColor: getAvatarColor(selectedUser.name) }">
              {{ getInitials(selectedUser.name) }}
            </div>
            <div>
              <h3>{{ selectedUser.name }}</h3>
            </div>
          </div>

          <div class="form-group" v-if="selectedUser.id === authStore.user.id">
            <label>Mot de passe actuel *</label>
            <input type="password" v-model="passwordForm.current_password" required />
          </div>

          <div class="form-group">
            <label>Nouveau mot de passe *</label>
            <input type="password" v-model="passwordForm.password" required minlength="8" />
          </div>

          <div class="form-group">
            <label>Confirmer *</label>
            <input type="password" v-model="passwordForm.password_confirmation" required />
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" @click="showPasswordModal = false">Annuler</button>
            <button type="submit" class="btn btn-primary" :disabled="saving">
              {{ saving ? 'Modification...' : 'Changer' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal Confirmation suppression -->
    <div class="modal-overlay" v-if="showDeleteConfirm" @click.self="showDeleteConfirm = false">
      <div class="modal modal-small">
        <div class="modal-header danger">
          <h2>⚠️ Confirmer la suppression</h2>
          <button class="close-btn" @click="showDeleteConfirm = false">&times;</button>
        </div>
        <div class="modal-body" v-if="selectedUser">
          <p class="confirm-text">
            Êtes-vous sûr de vouloir supprimer l'utilisateur <strong>{{ selectedUser.name }}</strong> ?
          </p>
          <p class="confirm-warning">Cette action est irréversible.</p>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" @click="showDeleteConfirm = false">Annuler</button>
            <button type="button" class="btn btn-danger" @click="deleteUser">
              Supprimer définitivement
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.users-page {
  padding: 30px;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 20px;
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

.btn-icon {
  margin-right: 5px;
}

/* Alerts */
.alert {
  padding: 15px 20px;
  border-radius: 8px;
  margin-bottom: 20px;
  animation: slideIn 0.3s ease;
}

@keyframes slideIn {
  from { transform: translateY(-10px); opacity: 0; }
  to { transform: translateY(0); opacity: 1; }
}

.alert-success {
  background: #d4edda;
  color: #155724;
  border: 1px solid #c3e6cb;
}

.alert-error {
  background: #f8d7da;
  color: #721c24;
  border: 1px solid #f5c6cb;
}

/* Filters */
.filters-card {
  background: white;
  border-radius: 12px;
  padding: 20px;
  margin-bottom: 20px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.filters-row {
  display: flex;
  gap: 15px;
  flex-wrap: wrap;
  align-items: center;
}

.filter-group {
  position: relative;
}

.search-group {
  flex: 1;
  min-width: 250px;
  position: relative;
}

.filter-icon {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  z-index: 1;
}

.search-input {
  width: 100%;
  padding: 10px 10px 10px 40px;
  border: 1px solid #ddd;
  border-radius: 8px;
  font-size: 14px;
}

.filter-group select {
  padding: 10px 15px;
  border: 1px solid #ddd;
  border-radius: 8px;
  font-size: 14px;
  min-width: 150px;
  background: white;
}

/* Users Grid */
.users-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 20px;
}

.user-card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  overflow: hidden;
  transition: transform 0.2s, box-shadow 0.2s;
}

.user-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.user-card-header {
  display: flex;
  align-items: center;
  padding: 20px;
  background: linear-gradient(135deg, #f8f9fa 0%, #fff 100%);
  border-bottom: 1px solid #eee;
}

.user-avatar {
  width: 50px;
  height: 50px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-weight: bold;
  font-size: 18px;
  margin-right: 15px;
  flex-shrink: 0;
}

.user-info {
  flex: 1;
  min-width: 0;
}

.user-name {
  font-size: 16px;
  font-weight: 600;
  color: #2c3e50;
  margin: 0 0 4px 0;
  display: flex;
  align-items: center;
  gap: 8px;
}

.badge-you {
  font-size: 10px;
  padding: 2px 6px;
  background: #3498db;
  color: white;
  border-radius: 10px;
  font-weight: 500;
}

.user-email {
  font-size: 13px;
  color: #7f8c8d;
  margin: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.user-card-body {
  padding: 15px 20px;
}

.user-meta {
  margin-bottom: 12px;
}

.meta-item {
  display: flex;
  justify-content: space-between;
  font-size: 13px;
}

.meta-label {
  color: #7f8c8d;
}

.meta-value {
  color: #2c3e50;
  font-weight: 500;
}

.user-roles {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.role-badge {
  padding: 4px 10px;
  border-radius: 15px;
  font-size: 11px;
  font-weight: 600;
}

.no-role {
  font-size: 12px;
  color: #95a5a6;
  font-style: italic;
}

.user-card-footer {
  display: flex;
  border-top: 1px solid #eee;
  padding: 10px;
  gap: 5px;
  justify-content: center;
}

.action-btn {
  width: 36px;
  height: 36px;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s;
  font-size: 16px;
  background: #f8f9fa;
}

.action-btn:hover {
  transform: scale(1.1);
}

.action-btn.view:hover { background: #d6eaf8; }
.action-btn.edit:hover { background: #d5f5e3; }
.action-btn.roles:hover { background: #fdebd0; }
.action-btn.password:hover { background: #e8daef; }
.action-btn.delete:hover { background: #fadbd8; }

/* Loading & Empty states */
.loading-state,
.empty-state {
  text-align: center;
  padding: 60px 20px;
  background: white;
  border-radius: 12px;
}

.spinner {
  width: 40px;
  height: 40px;
  border: 3px solid #eee;
  border-top-color: #3498db;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  margin: 0 auto 20px;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.empty-icon {
  font-size: 60px;
  margin-bottom: 15px;
}

.empty-state h3 {
  color: #2c3e50;
  margin-bottom: 10px;
}

.empty-state p {
  color: #7f8c8d;
}

/* Modal */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2000;
  animation: fadeIn 0.2s ease;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

.modal {
  background: white;
  border-radius: 16px;
  width: 100%;
  max-width: 550px;
  max-height: 90vh;
  overflow-y: auto;
  animation: slideUp 0.3s ease;
}

.modal-small {
  max-width: 400px;
}

@keyframes slideUp {
  from { transform: translateY(20px); opacity: 0; }
  to { transform: translateY(0); opacity: 1; }
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px;
  border-bottom: 1px solid #eee;
}

.modal-header.danger {
  background: #fff5f5;
}

.modal-header h2 {
  margin: 0;
  font-size: 18px;
  color: #2c3e50;
}

.close-btn {
  background: none;
  border: none;
  font-size: 24px;
  cursor: pointer;
  color: #7f8c8d;
  line-height: 1;
}

.close-btn:hover {
  color: #2c3e50;
}

.modal-body {
  padding: 20px;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 20px;
  padding-top: 20px;
  border-top: 1px solid #eee;
}

/* Forms */
.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 15px;
}

.form-group {
  margin-bottom: 15px;
}

.form-group label {
  display: block;
  margin-bottom: 6px;
  font-weight: 500;
  color: #2c3e50;
  font-size: 13px;
}

.form-group input,
.form-group select {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid #ddd;
  border-radius: 8px;
  font-size: 14px;
  transition: border-color 0.2s;
}

.form-group input:focus,
.form-group select:focus {
  outline: none;
  border-color: #3498db;
}

.form-hint {
  display: block;
  margin-top: 4px;
  font-size: 11px;
  color: #7f8c8d;
}

.roles-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 8px;
}

.role-checkbox {
  display: flex;
  align-items: center;
  padding: 10px;
  background: #f8f9fa;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s;
  border: 2px solid transparent;
}

.role-checkbox:hover {
  background: #eee;
}

.role-checkbox.selected {
  border-color: #3498db;
  background: #ebf5fb;
}

.role-checkbox input {
  display: none;
}

.role-badge-small {
  padding: 4px 10px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 500;
}

/* Detail Modal */
.detail-header {
  display: flex;
  align-items: center;
  margin-bottom: 20px;
  padding-bottom: 15px;
  border-bottom: 1px solid #eee;
}

.detail-header.compact {
  margin-bottom: 15px;
  padding-bottom: 15px;
}

.detail-avatar {
  width: 60px;
  height: 60px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-weight: bold;
  font-size: 22px;
  margin-right: 15px;
}

.detail-avatar.small {
  width: 45px;
  height: 45px;
  font-size: 16px;
}

.detail-header h3 {
  margin: 0 0 4px 0;
  color: #2c3e50;
}

.detail-header p {
  margin: 0;
  color: #7f8c8d;
  font-size: 14px;
}

.detail-section {
  margin-bottom: 20px;
}

.detail-section h4 {
  font-size: 12px;
  text-transform: uppercase;
  color: #7f8c8d;
  margin-bottom: 10px;
}

.detail-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

.detail-item {
  background: #f8f9fa;
  padding: 12px;
  border-radius: 8px;
}

.detail-label {
  display: block;
  font-size: 11px;
  color: #7f8c8d;
  margin-bottom: 4px;
}

.detail-value {
  font-weight: 500;
  color: #2c3e50;
}

.detail-roles {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

/* Confirm Modal */
.confirm-text {
  font-size: 15px;
  color: #2c3e50;
  margin-bottom: 10px;
}

.confirm-warning {
  font-size: 13px;
  color: #e74c3c;
}

/* Responsive */
@media (max-width: 768px) {
  .form-row {
    grid-template-columns: 1fr;
  }
  
  .roles-grid {
    grid-template-columns: 1fr;
  }
  
  .filters-row {
    flex-direction: column;
    align-items: stretch;
  }
  
  .search-group {
    min-width: 100%;
  }
  
  .users-grid {
    grid-template-columns: 1fr;
  }
}
</style>
