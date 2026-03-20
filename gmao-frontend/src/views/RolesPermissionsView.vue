<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
const roles = ref([])
const allPermissions = ref([])
const groupedPermissions = ref({})
const loading = ref(false)
const error = ref('')
const successMessage = ref('')

const showCreateModal = ref(false)
const showEditModal = ref(false)
const showDetailModal = ref(false)
const saving = ref(false)
const selectedRole = ref(null)

const form = ref({
    name: '',
    permissions: [],
})

const moduleIcons = {
    site: '🏭', user: '👥', role: '🔑', equipment: '⚙️',
    location: '📍', workorder_request: '📝', workorder: '🔧',
    part: '🔩', stock: '📦', report: '📊', settings: '⚙️',
    intervention_request: '📋', preventive: '🛡️', driver: '👷',
    truck: '🚛', client: '🏢', habilitation: '📜',
    assignment: '🔄', notification: '🔔', dashboard: '📊',
}

function humanize(str) {
    if (!str) return 'Inconnu'
    return str.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())
}

function getModuleLabel(module) {
    return `${moduleIcons[module] || '📂'} ${humanize(module)}`
}

// ✅ Extraire l'action depuis perm.action OU depuis perm.name (part:view → view)
function getPermAction(perm) {
    if (perm.action) return perm.action
    const parts = (perm.name || '').split(':')
    return parts.length > 1 ? parts[1] : parts[0]
}

function getActionLabel(perm) {
    return humanize(getPermAction(perm))
}

const protectedRoles = ['SuperAdmin', 'AdminSite', 'Technicien', 'Planificateur', 'Magasinier', 'Lecteur']

const sortedModules = computed(() => {
    return Object.keys(groupedPermissions.value).sort((a, b) => {
        return humanize(a).localeCompare(humanize(b))
    })
})

function showSuccess(message) {
    successMessage.value = message
    setTimeout(() => { successMessage.value = '' }, 3000)
}

function showError(msg) {
    error.value = msg
    setTimeout(() => { error.value = '' }, 5000)
}

async function fetchRoles() {
    loading.value = true
    try {
        const response = await api.get('/roles')
        roles.value = response.data.map(role => ({
            ...role,
            permissions: Array.isArray(role.permissions)
                ? role.permissions
                : Object.values(role.permissions || {})
        }))
    } catch (err) {
        showError('Erreur lors du chargement des rôles')
    } finally {
        loading.value = false
    }
}

async function fetchPermissions() {
    try {
        const response = await api.get('/permissions')
        allPermissions.value = Array.isArray(response.data.permissions)
            ? response.data.permissions
            : Object.values(response.data.permissions || {})

        const raw = response.data.grouped || {}
        const normalized = {}
        for (const [module, perms] of Object.entries(raw)) {
            normalized[module] = Array.isArray(perms) ? perms : Object.values(perms)
        }
        groupedPermissions.value = normalized
    } catch (err) {
        console.error(err)
    }
}

function getRolePermissionNames(role) {
    return role.permissions?.map(p => p.name) || []
}

function getRolePermissionCount(role) {
    return role.permissions?.length || 0
}

function openCreateModal() {
    form.value = { name: '', permissions: [] }
    showCreateModal.value = true
}

async function createRole() {
    saving.value = true
    try {
        await api.post('/roles', form.value)
        showCreateModal.value = false
        showSuccess('Rôle créé avec succès')
        fetchRoles()
    } catch (err) {
        showError(err.response?.data?.message || 'Erreur lors de la création')
    } finally {
        saving.value = false
    }
}

function openEditModal(role) {
    selectedRole.value = role
    form.value = {
        name: role.name,
        permissions: [...getRolePermissionNames(role)],
    }
    showEditModal.value = true
}

async function updateRole() {
    saving.value = true
    try {
        await api.put(`/roles/${selectedRole.value.id}`, form.value)
        showEditModal.value = false
        showSuccess('Rôle mis à jour avec succès')
        fetchRoles()
    } catch (err) {
        showError(err.response?.data?.message || 'Erreur lors de la mise à jour')
    } finally {
        saving.value = false
    }
}

function openDetailModal(role) {
    selectedRole.value = role
    showDetailModal.value = true
}

async function deleteRole(role) {
    if (!confirm(`Supprimer le rôle "${role.name}" ?`)) return
    try {
        await api.delete(`/roles/${role.id}`)
        showSuccess('Rôle supprimé')
        fetchRoles()
    } catch (err) {
        showError(err.response?.data?.message || 'Erreur lors de la suppression')
    }
}

// ✅ Toggle UNE permission — remplacement immutable du tableau
function togglePermission(permName) {
    const current = form.value.permissions
    if (current.includes(permName)) {
        form.value.permissions = current.filter(p => p !== permName)
    } else {
        form.value.permissions = [...current, permName]
    }
}

function isPermChecked(permName) {
    return form.value.permissions.includes(permName)
}

// ✅ Toggle TOUT un module — remplacement immutable du tableau
function toggleModule(module) {
    const perms = groupedPermissions.value[module] || []
    const permNames = perms.map(p => p.name)
    const allChecked = permNames.every(n => form.value.permissions.includes(n))

    if (allChecked) {
        form.value.permissions = form.value.permissions.filter(p => !permNames.includes(p))
    } else {
        const newPerms = [...form.value.permissions]
        permNames.forEach(n => {
            if (!newPerms.includes(n)) newPerms.push(n)
        })
        form.value.permissions = newPerms
    }
}

function isModuleFullyChecked(module) {
    const perms = groupedPermissions.value[module] || []
    return perms.length > 0 && perms.every(p => form.value.permissions.includes(p.name))
}

function isModulePartiallyChecked(module) {
    const perms = groupedPermissions.value[module] || []
    const checked = perms.filter(p => form.value.permissions.includes(p.name))
    return checked.length > 0 && checked.length < perms.length
}

function moduleCheckedCount(module) {
    const perms = groupedPermissions.value[module] || []
    return perms.filter(p => form.value.permissions.includes(p.name)).length
}

function selectAllPermissions() {
    form.value.permissions = allPermissions.value.map(p => p.name)
}

function deselectAllPermissions() {
    form.value.permissions = []
}

function getGroupedRolePermissions(role) {
    const grouped = {}
    const perms = role.permissions || []
    perms.forEach(p => {
        const parts = p.name.split(':')
        const module = parts[0] || 'other'
        if (!grouped[module]) grouped[module] = []
        grouped[module].push(parts[1] || p.name)
    })
    return grouped
}

onMounted(() => {
    fetchRoles()
    fetchPermissions()
})
</script>

<template>
    <div class="roles-page">
        <header class="page-header">
            <div>
                <h1>🔑 Rôles & Permissions</h1>
                <p class="subtitle">Gestion des rôles et de leurs permissions d'accès</p>
            </div>
            <button class="btn btn-success" @click="openCreateModal" v-if="authStore.hasPermission('role:create')">
                <span class="btn-icon">+</span> Nouveau rôle
            </button>
        </header>

        <div class="alert alert-success" v-if="successMessage">✅ {{ successMessage }}</div>
        <div class="alert alert-error" v-if="error">❌ {{ error }}</div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value">{{ roles.length }}</div>
                <div class="stat-label">Rôles</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">{{ allPermissions.length }}</div>
                <div class="stat-label">Permissions</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">{{ Object.keys(groupedPermissions).length }}</div>
                <div class="stat-label">Modules</div>
            </div>
        </div>

        <div class="roles-grid" v-if="!loading && roles.length">
            <div class="role-card" v-for="role in roles" :key="role.id">
                <div class="role-header">
                    <div class="role-name">
                        <span class="role-icon" :class="{ 'super': role.name === 'SuperAdmin' }">
                            {{ role.name === 'SuperAdmin' ? '👑' : '🛡️' }}
                        </span>
                        {{ role.name }}
                    </div>
                    <span class="badge protected-badge" v-if="protectedRoles.includes(role.name)">Système</span>
                </div>
                <div class="role-stats">
                    <div class="perm-count">
                        <span class="count-number">{{ getRolePermissionCount(role) }}</span>
                        <span class="count-label">permissions</span>
                    </div>
                    <div class="perm-bar">
                        <div class="perm-bar-fill"
                            :style="{ width: (getRolePermissionCount(role) / allPermissions.length * 100) + '%' }">
                        </div>
                    </div>
                </div>
                <div class="role-modules">
                    <span class="module-tag" v-for="(perms, mod) in getGroupedRolePermissions(role)" :key="mod">
                        {{ (moduleIcons[mod] || '📂') }} {{ humanize(mod) }} ({{ perms.length }})
                    </span>
                </div>
                <div class="role-actions">
                    <button class="btn btn-sm btn-primary" @click="openDetailModal(role)">Voir détails</button>
                    <button class="btn btn-sm btn-warning" @click="openEditModal(role)"
                        v-if="authStore.hasPermission('role:update') && role.name !== 'SuperAdmin'">
                        ✏️ Modifier
                    </button>
                    <button class="btn btn-sm btn-danger" @click="deleteRole(role)"
                        v-if="authStore.hasPermission('role:delete') && !protectedRoles.includes(role.name)">
                        Suppr.
                    </button>
                </div>
            </div>
        </div>

        <div class="loading-state" v-if="loading">
            <div class="spinner"></div>
            <p>Chargement...</p>
        </div>

        <!-- ==================== MODAL DÉTAIL ==================== -->
        <div class="modal-overlay" v-if="showDetailModal" @click.self="showDetailModal = false">
            <div class="modal modal-large">
                <div class="modal-header">
                    <h2>{{ selectedRole?.name === 'SuperAdmin' ? '👑' : '🛡️' }} {{ selectedRole?.name }}</h2>
                    <button class="close-btn" @click="showDetailModal = false">&times;</button>
                </div>
                <div class="modal-body" v-if="selectedRole">
                    <div class="detail-info">
                        <span class="perm-total">
                            {{ getRolePermissionCount(selectedRole) }} / {{ allPermissions.length }} permissions
                        </span>
                    </div>
                    <div class="permissions-detail">
                        <div class="module-section" v-for="module in sortedModules" :key="module">
                            <div class="module-header-detail">
                                <span class="module-title">{{ getModuleLabel(module) }}</span>
                                <span class="module-count-detail">
                                    {{ (getGroupedRolePermissions(selectedRole)[module] || []).length }}
                                    / {{ groupedPermissions[module]?.length || 0 }}
                                </span>
                            </div>
                            <div class="perm-tags">
                                <span class="perm-tag" v-for="perm in groupedPermissions[module]" :key="perm.name"
                                    :class="{
                                        'active': getRolePermissionNames(selectedRole).includes(perm.name),
                                        'inactive': !getRolePermissionNames(selectedRole).includes(perm.name)
                                    }">
                                    {{ getRolePermissionNames(selectedRole).includes(perm.name) ? '✅' : '❌' }}
                                    {{ getActionLabel(perm) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==================== MODAL CRÉATION ==================== -->
        <div class="modal-overlay" v-if="showCreateModal" @click.self="showCreateModal = false">
            <div class="modal modal-large">
                <div class="modal-header">
                    <h2>➕ Nouveau rôle</h2>
                    <button class="close-btn" @click="showCreateModal = false">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nom du rôle *</label>
                        <input type="text" v-model="form.name" placeholder="Ex: ChefEquipe, Superviseur..." />
                    </div>
                    <div class="form-group">
                        <label>Permissions</label>
                        <div class="perm-actions-bar">
                            <button type="button" class="btn btn-sm btn-primary" @click="selectAllPermissions">Tout sélectionner</button>
                            <button type="button" class="btn btn-sm btn-secondary" @click="deselectAllPermissions">Tout désélectionner</button>
                            <span class="selected-count">{{ form.permissions.length }} / {{ allPermissions.length }}</span>
                        </div>
                    </div>
                    <div class="permissions-editor">
                        <div class="module-block" v-for="module in sortedModules" :key="module">
                            <div class="module-header-row" @click.stop="toggleModule(module)">
                                <span class="module-check-icon">
                                    {{ isModuleFullyChecked(module) ? '☑' : (isModulePartiallyChecked(module) ? '◧' : '☐') }}
                                </span>
                                <span class="module-label">{{ getModuleLabel(module) }}</span>
                                <span class="module-count">{{ moduleCheckedCount(module) }} / {{ groupedPermissions[module]?.length || 0 }}</span>
                            </div>
                            <div class="module-perms">
                                <div
                                    v-for="perm in groupedPermissions[module]"
                                    :key="perm.name"
                                    class="perm-item"
                                    :class="{ 'perm-active': isPermChecked(perm.name) }"
                                    @click.stop="togglePermission(perm.name)"
                                >
                                    <span class="perm-check">{{ isPermChecked(perm.name) ? '✅' : '⬜' }}</span>
                                    <span class="perm-text">{{ getActionLabel(perm) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" @click="showCreateModal = false">Annuler</button>
                        <button type="button" class="btn btn-primary" :disabled="saving || !form.name" @click="createRole">
                            {{ saving ? 'Création...' : 'Créer le rôle' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==================== MODAL ÉDITION ==================== -->
        <div class="modal-overlay" v-if="showEditModal" @click.self="showEditModal = false">
            <div class="modal modal-large">
                <div class="modal-header">
                    <h2>✏️ Modifier : {{ selectedRole?.name }}</h2>
                    <button class="close-btn" @click="showEditModal = false">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group" v-if="!protectedRoles.includes(selectedRole?.name)">
                        <label>Nom du rôle *</label>
                        <input type="text" v-model="form.name" />
                    </div>
                    <div class="form-group">
                        <label>Permissions</label>
                        <div class="perm-actions-bar">
                            <button type="button" class="btn btn-sm btn-primary" @click="selectAllPermissions">Tout sélectionner</button>
                            <button type="button" class="btn btn-sm btn-secondary" @click="deselectAllPermissions">Tout désélectionner</button>
                            <span class="selected-count">{{ form.permissions.length }} / {{ allPermissions.length }}</span>
                        </div>
                    </div>
                    <div class="permissions-editor">
                        <div class="module-block" v-for="module in sortedModules" :key="module">
                            <div class="module-header-row" @click.stop="toggleModule(module)">
                                <span class="module-check-icon">
                                    {{ isModuleFullyChecked(module) ? '☑' : (isModulePartiallyChecked(module) ? '◧' : '☐') }}
                                </span>
                                <span class="module-label">{{ getModuleLabel(module) }}</span>
                                <span class="module-count">{{ moduleCheckedCount(module) }} / {{ groupedPermissions[module]?.length || 0 }}</span>
                            </div>
                            <div class="module-perms">
                                <div
                                    v-for="perm in groupedPermissions[module]"
                                    :key="perm.name"
                                    class="perm-item"
                                    :class="{ 'perm-active': isPermChecked(perm.name) }"
                                    @click.stop="togglePermission(perm.name)"
                                >
                                    <span class="perm-check">{{ isPermChecked(perm.name) ? '✅' : '⬜' }}</span>
                                    <span class="perm-text">{{ getActionLabel(perm) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" @click="showEditModal = false">Annuler</button>
                        <button type="button" class="btn btn-primary" :disabled="saving" @click="updateRole">
                            {{ saving ? 'Sauvegarde...' : 'Enregistrer' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.roles-page { padding: 30px; }

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 20px;
}
.page-header h1 { font-size: 28px; color: #2c3e50; margin-bottom: 5px; }
.subtitle { color: #7f8c8d; font-size: 14px; }

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 15px;
    margin-bottom: 25px;
}
.stat-card {
    background: white; border-radius: 12px; padding: 20px;
    text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    border-left: 4px solid #3498db;
}
.stat-value { font-size: 28px; font-weight: bold; color: #2c3e50; }
.stat-label { font-size: 12px; color: #7f8c8d; }

.roles-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 20px;
}
.role-card {
    background: white; border-radius: 12px; padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05); transition: transform 0.2s;
}
.role-card:hover { transform: translateY(-3px); box-shadow: 0 4px 15px rgba(0,0,0,0.1); }

.role-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
.role-name { font-size: 18px; font-weight: 600; color: #2c3e50; display: flex; align-items: center; gap: 8px; }
.role-icon { font-size: 20px; }
.role-icon.super { font-size: 24px; }
.protected-badge { background: #e9ecef; color: #6c757d; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }

.role-stats { margin-bottom: 12px; }
.perm-count { display: flex; align-items: baseline; gap: 6px; margin-bottom: 6px; }
.count-number { font-size: 22px; font-weight: bold; color: #3498db; }
.count-label { font-size: 12px; color: #7f8c8d; }
.perm-bar { height: 6px; background: #ecf0f1; border-radius: 3px; overflow: hidden; }
.perm-bar-fill { height: 100%; background: linear-gradient(90deg, #3498db, #2ecc71); border-radius: 3px; transition: width 0.3s; }

.role-modules { display: flex; flex-wrap: wrap; gap: 5px; margin-bottom: 15px; min-height: 28px; }
.module-tag { background: #f0f7ff; color: #3498db; padding: 3px 8px; border-radius: 10px; font-size: 11px; font-weight: 500; }

.role-actions { display: flex; gap: 8px; flex-wrap: wrap; border-top: 1px solid #f0f0f0; padding-top: 12px; }

.alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; }
.alert-success { background: #d4edda; color: #155724; }
.alert-error { background: #f8d7da; color: #721c24; }

.loading-state { text-align: center; padding: 60px; background: white; border-radius: 12px; }
.spinner { width: 40px; height: 40px; border: 3px solid #eee; border-top-color: #3498db; border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto 20px; }
@keyframes spin { to { transform: rotate(360deg); } }

/* ===== MODAL ===== */
.modal-overlay {
    position: fixed; top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.5); display: flex;
    align-items: center; justify-content: center; z-index: 2000;
}
.modal { background: white; border-radius: 16px; width: 100%; max-width: 550px; max-height: 90vh; overflow-y: auto; }
.modal-large { max-width: 800px; }
.modal-header {
    display: flex; justify-content: space-between; align-items: center;
    padding: 20px; border-bottom: 1px solid #eee;
    position: sticky; top: 0; background: white; z-index: 10;
    border-radius: 16px 16px 0 0;
}
.modal-header h2 { margin: 0; font-size: 18px; color: #2c3e50; }
.close-btn { background: none; border: none; font-size: 24px; cursor: pointer; color: #7f8c8d; }
.modal-body { padding: 20px; }
.modal-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee; }

.form-group { margin-bottom: 15px; }
.form-group > label { display: block; margin-bottom: 6px; font-weight: 500; color: #2c3e50; font-size: 13px; }
.form-group input[type="text"] { width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; box-sizing: border-box; }

.perm-actions-bar { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
.selected-count { margin-left: auto; font-size: 13px; color: #7f8c8d; font-weight: 500; }

/* ===== PERMISSIONS EDITOR ===== */
.permissions-editor {
    display: flex;
    flex-direction: column;
    gap: 16px;               /* ← espacement entre modules */
    max-height: 500px;
    overflow-y: auto;
    padding: 4px;
}

.module-block {
    background: #f8f9fa;
    border-radius: 10px;
    border: 1px solid #e0e0e0;
}

/* En-tête module = toggle tout le module */
.module-header-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    background: #e8ecef;
    cursor: pointer;
    user-select: none;
    border-radius: 10px 10px 0 0;
    transition: background 0.15s;
}
.module-header-row:hover { background: #dce1e6; }
.module-check-icon { font-size: 18px; width: 22px; text-align: center; flex-shrink: 0; }
.module-label { font-weight: 600; color: #2c3e50; font-size: 14px; flex: 1; }
.module-count { font-size: 12px; color: #7f8c8d; font-weight: 500; white-space: nowrap; }

/* Grille des permissions individuelles */
.module-perms {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    padding: 12px 16px;
}

/* Chaque permission = cliquable individuellement */
.perm-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 14px;
    background: white;
    border: 2px solid #ddd;
    border-radius: 8px;
    cursor: pointer;
    user-select: none;
    transition: all 0.15s;
    min-height: 36px;
}
.perm-item:hover {
    border-color: #3498db;
    background: #f0f7ff;
    transform: translateY(-1px);
}
.perm-item.perm-active {
    border-color: #27ae60;
    background: #eafaf1;
}
.perm-item.perm-active:hover {
    border-color: #e74c3c;
    background: #fdf0f0;
}

.perm-check { font-size: 16px; flex-shrink: 0; }
.perm-text { font-size: 13px; color: #555; white-space: nowrap; }
.perm-item.perm-active .perm-text { color: #1e8449; font-weight: 600; }

/* ===== DETAIL MODAL ===== */
.detail-info { margin-bottom: 20px; }
.perm-total { background: #e3f2fd; color: #1565c0; padding: 8px 15px; border-radius: 8px; font-weight: 500; font-size: 14px; display: inline-block; }
.permissions-detail { display: flex; flex-direction: column; gap: 16px; }
.module-section { background: #f8f9fa; border-radius: 10px; padding: 14px 16px; border: 1px solid #e9ecef; }
.module-header-detail { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
.module-title { font-weight: 600; color: #2c3e50; font-size: 14px; }
.module-count-detail { font-size: 12px; color: #7f8c8d; background: white; padding: 2px 8px; border-radius: 10px; }
.perm-tags { display: flex; flex-wrap: wrap; gap: 6px; }
.perm-tag { padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 500; }
.perm-tag.active { background: #d4edda; color: #155724; }
.perm-tag.inactive { background: #f8d7da; color: #721c24; opacity: 0.6; }

/* ===== BUTTONS ===== */
.btn { padding: 10px 20px; border: none; border-radius: 8px; font-weight: 500; cursor: pointer; }
.btn-sm { padding: 6px 12px; font-size: 13px; }
.btn-primary { background: #3498db; color: white; }
.btn-success { background: #27ae60; color: white; }
.btn-danger { background: #e74c3c; color: white; }
.btn-warning { background: #f39c12; color: white; }
.btn-secondary { background: #95a5a6; color: white; }
.btn:hover { opacity: 0.9; }
.btn:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-icon { font-size: 18px; margin-right: 5px; }

@media (max-width: 768px) {
    .roles-grid { grid-template-columns: 1fr; }
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
}
</style>
