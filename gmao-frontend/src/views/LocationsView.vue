<script setup>
import { ref, onMounted, computed, defineComponent, h } from 'vue'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
const locations = ref([])
const tree = ref([])
const loading = ref(false)
const error = ref('')
const showModal = ref(false)
const showDetailModal = ref(false)
const editingLocation = ref(null)
const selectedLocation = ref(null)
const activeView = ref('tree')
const search = ref('')
const pagination = ref({})

const form = ref({
  name: '',
  code: '',
  parent_id: '',
  description: '',
  is_active: true,
})

const saving = ref(false)

async function fetchLocations(page = 1) {
  loading.value = true
  try {
    const params = { page, per_page: 50 }
    if (search.value) params.search = search.value

    const response = await api.get('/locations', { params })
    locations.value = response.data.data
    pagination.value = {
      current_page: response.data.current_page,
      last_page: response.data.last_page,
      total: response.data.total,
    }
  } catch (err) {
    error.value = 'Erreur lors du chargement'
    console.error(err)
  } finally {
    loading.value = false
  }
}

async function fetchTree() {
  try {
    const response = await api.get('/locations-tree')
    tree.value = response.data
  } catch (err) {
    console.error(err)
  }
}

function openCreateModal(parentId = null) {
  editingLocation.value = null
  form.value = {
    name: '',
    code: '',
    parent_id: parentId || '',
    description: '',
    is_active: true,
  }
  showModal.value = true
}

function openEditModal(location) {
  editingLocation.value = location
  form.value = {
    name: location.name,
    code: location.code || '',
    parent_id: location.parent_id || '',
    description: location.description || '',
    is_active: location.is_active,
  }
  showModal.value = true
}

async function openDetailModal(location) {
  try {
    const response = await api.get(`/locations/${location.id}`)
    selectedLocation.value = response.data
    showDetailModal.value = true
  } catch (err) {
    error.value = 'Erreur lors du chargement des détails'
  }
}

async function saveLocation() {
  saving.value = true
  error.value = ''
  try {
    const data = { ...form.value }
    if (!data.parent_id) data.parent_id = null
    if (!data.code) delete data.code

    if (editingLocation.value) {
      await api.put(`/locations/${editingLocation.value.id}`, data)
    } else {
      await api.post('/locations', data)
    }
    showModal.value = false
    fetchLocations()
    fetchTree()
  } catch (err) {
    error.value = err.response?.data?.message || 'Erreur lors de la sauvegarde'
  } finally {
    saving.value = false
  }
}

async function deleteLocation(location) {
  if (!confirm(`Supprimer l'emplacement "${location.name}" ?`)) return

  try {
    await api.delete(`/locations/${location.id}`)
    fetchLocations()
    fetchTree()
  } catch (err) {
    error.value = err.response?.data?.message || 'Erreur lors de la suppression'
  }
}

async function toggleActive(location) {
  try {
    await api.put(`/locations/${location.id}`, {
      ...location,
      is_active: !location.is_active,
    })
    fetchLocations()
    fetchTree()
  } catch (err) {
    error.value = 'Erreur lors de la mise à jour'
  }
}

// Stats
const stats = computed(() => ({
  total: pagination.value.total || locations.value.length,
  active: locations.value.filter(l => l.is_active).length,
  withEquipments: locations.value.filter(l => l.equipments_count > 0).length,
}))

// Handlers pour le tree
function handleView(node) {
  openDetailModal(node)
}

function handleEdit(node) {
  openEditModal(node)
}

function handleAddChild(parentId) {
  openCreateModal(parentId)
}

function handleDelete(node) {
  deleteLocation(node)
}

onMounted(() => {
  fetchLocations()
  fetchTree()
})
</script>

<template>
  <div class="locations-page">
    <header class="page-header">
      <div>
        <h1>📍 Emplacements</h1>
        <p class="subtitle">Structure hiérarchique des zones</p>
      </div>
      <button class="btn btn-primary" @click="openCreateModal()">
        + Nouvel emplacement
      </button>
    </header>

    <!-- Stats -->
    <div class="stats-cards">
      <div class="stat-card">
        <div class="stat-icon blue">📍</div>
        <div class="stat-content">
          <div class="stat-value">{{ stats.total }}</div>
          <div class="stat-label">Total</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon green">✅</div>
        <div class="stat-content">
          <div class="stat-value">{{ stats.active }}</div>
          <div class="stat-label">Actifs</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon purple">⚙️</div>
        <div class="stat-content">
          <div class="stat-value">{{ stats.withEquipments }}</div>
          <div class="stat-label">Avec équipements</div>
        </div>
      </div>
    </div>

    <!-- Filters & View Toggle -->
    <div class="filters-bar">
      <div class="search-box">
        <span class="search-icon">🔍</span>
        <input
          type="text"
          v-model="search"
          placeholder="Rechercher..."
          @input="fetchLocations()"
        />
      </div>
      <div class="view-toggle">
        <button :class="{ active: activeView === 'tree' }" @click="activeView = 'tree'">
          🌳 Arbre
        </button>
        <button :class="{ active: activeView === 'list' }" @click="activeView = 'list'">
          📋 Liste
        </button>
      </div>
    </div>

    <div class="alert alert-error" v-if="error">{{ error }}</div>

    <!-- Loading -->
    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <p>Chargement...</p>
    </div>

    <!-- Tree View -->
    <div v-else-if="activeView === 'tree'" class="tree-view">
      <div v-if="tree.length === 0" class="empty-state">
        <span class="empty-icon">📍</span>
        <h3>Aucun emplacement</h3>
        <p>Créez votre premier emplacement</p>
      </div>

      <div class="tree-container">
        <!-- Composant Tree Node inline -->
        <template v-for="node in tree" :key="node.id">
          <div class="tree-node">
            <div class="node-content" :class="{ inactive: !node.is_active }">
              <div class="node-icon">{{ node.children_recursive?.length ? '📂' : '📍' }}</div>
              <div class="node-info">
                <span class="node-code">{{ node.code }}</span>
                <span class="node-name">{{ node.name }}</span>
                <span class="node-count" v-if="node.equipments_count">
                  ⚙️ {{ node.equipments_count }}
                </span>
              </div>
              <div class="node-actions">
                <button @click="handleView(node)" title="Détails">👁️</button>
                <button @click="handleAddChild(node.id)" title="Ajouter enfant">➕</button>
                <button @click="handleEdit(node)" title="Modifier">✏️</button>
                <button @click="handleDelete(node)" title="Supprimer">🗑️</button>
              </div>
            </div>

            <!-- Niveau 1 -->
            <template v-if="node.children_recursive?.length">
              <div v-for="child1 in node.children_recursive" :key="child1.id" class="tree-node" style="margin-left: 25px;">
                <div class="node-content" :class="{ inactive: !child1.is_active }">
                  <div class="node-icon">{{ child1.children_recursive?.length ? '📂' : '📍' }}</div>
                  <div class="node-info">
                    <span class="node-code">{{ child1.code }}</span>
                    <span class="node-name">{{ child1.name }}</span>
                    <span class="node-count" v-if="child1.equipments_count">
                      ⚙️ {{ child1.equipments_count }}
                    </span>
                  </div>
                  <div class="node-actions">
                    <button @click="handleView(child1)" title="Détails">👁️</button>
                    <button @click="handleAddChild(child1.id)" title="Ajouter enfant">➕</button>
                    <button @click="handleEdit(child1)" title="Modifier">✏️</button>
                    <button @click="handleDelete(child1)" title="Supprimer">🗑️</button>
                  </div>
                </div>

                <!-- Niveau 2 -->
                <template v-if="child1.children_recursive?.length">
                  <div v-for="child2 in child1.children_recursive" :key="child2.id" class="tree-node" style="margin-left: 25px;">
                    <div class="node-content" :class="{ inactive: !child2.is_active }">
                      <div class="node-icon">{{ child2.children_recursive?.length ? '📂' : '📍' }}</div>
                      <div class="node-info">
                        <span class="node-code">{{ child2.code }}</span>
                        <span class="node-name">{{ child2.name }}</span>
                        <span class="node-count" v-if="child2.equipments_count">
                          ⚙️ {{ child2.equipments_count }}
                        </span>
                      </div>
                      <div class="node-actions">
                        <button @click="handleView(child2)" title="Détails">👁️</button>
                        <button @click="handleAddChild(child2.id)" title="Ajouter enfant">➕</button>
                        <button @click="handleEdit(child2)" title="Modifier">✏️</button>
                        <button @click="handleDelete(child2)" title="Supprimer">🗑️</button>
                      </div>
                    </div>

                    <!-- Niveau 3 -->
                    <template v-if="child2.children_recursive?.length">
                      <div v-for="child3 in child2.children_recursive" :key="child3.id" class="tree-node" style="margin-left: 25px;">
                        <div class="node-content" :class="{ inactive: !child3.is_active }">
                          <div class="node-icon">📍</div>
                          <div class="node-info">
                            <span class="node-code">{{ child3.code }}</span>
                            <span class="node-name">{{ child3.name }}</span>
                            <span class="node-count" v-if="child3.equipments_count">
                              ⚙️ {{ child3.equipments_count }}
                            </span>
                          </div>
                          <div class="node-actions">
                            <button @click="handleView(child3)" title="Détails">👁️</button>
                            <button @click="handleAddChild(child3.id)" title="Ajouter enfant">➕</button>
                            <button @click="handleEdit(child3)" title="Modifier">✏️</button>
                            <button @click="handleDelete(child3)" title="Supprimer">🗑️</button>
                          </div>
                        </div>
                      </div>
                    </template>
                  </div>
                </template>
              </div>
            </template>
          </div>
        </template>
      </div>
    </div>

    <!-- List View -->
    <div v-else class="list-view">
      <div class="table-container">
        <table class="locations-table" v-if="locations.length">
          <thead>
            <tr>
              <th>Code</th>
              <th>Nom</th>
              <th>Parent</th>
              <th>Équipements</th>
              <th>Sous-emplacements</th>
              <th>Statut</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="location in locations" :key="location.id" :class="{ inactive: !location.is_active }">
              <td class="code-cell">
                <strong>{{ location.code }}</strong>
              </td>
              <td class="name-cell">
                <div class="location-name">{{ location.name }}</div>
                <div class="location-desc" v-if="location.description">
                  {{ location.description }}
                </div>
              </td>
              <td>
                <span v-if="location.parent" class="parent-badge">
                  {{ location.parent.name }}
                </span>
                <span v-else class="text-muted">Racine</span>
              </td>
              <td>
                <span class="count-badge" :class="{ empty: !location.equipments_count }">
                  {{ location.equipments_count || 0 }}
                </span>
              </td>
              <td>
                <span class="count-badge" :class="{ empty: !location.children_count }">
                  {{ location.children_count || 0 }}
                </span>
              </td>
              <td>
                <span class="status-badge" :class="location.is_active ? 'active' : 'inactive'">
                  {{ location.is_active ? 'Actif' : 'Inactif' }}
                </span>
              </td>
              <td class="actions-cell">
                <div class="action-buttons">
                  <button class="btn-icon primary" @click="openDetailModal(location)" title="Détails">
                    👁️
                  </button>
                  <button class="btn-icon success" @click="openCreateModal(location.id)" title="Ajouter enfant">
                    ➕
                  </button>
                  <button class="btn-icon warning" @click="openEditModal(location)" title="Modifier">
                    ✏️
                  </button>
                  <button class="btn-icon" @click="toggleActive(location)" :title="location.is_active ? 'Désactiver' : 'Activer'">
                    {{ location.is_active ? '🔒' : '🔓' }}
                  </button>
                  <button class="btn-icon danger" @click="deleteLocation(location)" title="Supprimer">
                    🗑️
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>

        <div v-else class="empty-state">
          <span class="empty-icon">📍</span>
          <h3>Aucun emplacement trouvé</h3>
        </div>
      </div>
    </div>

    <!-- Modal Création/Édition -->
    <div class="modal-overlay" v-if="showModal" @click.self="showModal = false">
      <div class="modal">
        <div class="modal-header">
          <h2>{{ editingLocation ? '✏️ Modifier' : '➕ Nouvel' }} emplacement</h2>
          <button class="close-btn" @click="showModal = false">&times;</button>
        </div>
        <form @submit.prevent="saveLocation" class="modal-body">
          <div class="form-group">
            <label>Nom *</label>
            <input type="text" v-model="form.name" required placeholder="Ex: Atelier A" />
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>Code</label>
              <input type="text" v-model="form.code" placeholder="Auto-généré si vide" />
            </div>
            <div class="form-group">
              <label>Parent</label>
              <select v-model="form.parent_id">
                <option value="">Aucun (Racine)</option>
                <option 
                  v-for="loc in locations.filter(l => l.id !== editingLocation?.id)" 
                  :key="loc.id" 
                  :value="loc.id"
                >
                  {{ loc.parent ? loc.parent.name + ' > ' : '' }}{{ loc.name }}
                </option>
              </select>
            </div>
          </div>

          <div class="form-group">
            <label>Description</label>
            <textarea v-model="form.description" rows="2" placeholder="Description..."></textarea>
          </div>

          <div class="form-group checkbox-group">
            <label>
              <input type="checkbox" v-model="form.is_active" />
              <span>Actif</span>
            </label>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" @click="showModal = false">Annuler</button>
            <button type="submit" class="btn btn-primary" :disabled="saving">
              {{ saving ? 'Enregistrement...' : 'Enregistrer' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal Détails -->
    <div class="modal-overlay" v-if="showDetailModal" @click.self="showDetailModal = false">
      <div class="modal">
        <div class="modal-header">
          <h2>📍 {{ selectedLocation?.name }}</h2>
          <button class="close-btn" @click="showDetailModal = false">&times;</button>
        </div>
        <div class="modal-body" v-if="selectedLocation">
          <div class="detail-section">
            <div class="detail-row">
              <span class="detail-label">Code</span>
              <span class="detail-value">{{ selectedLocation.code }}</span>
            </div>
            <div class="detail-row" v-if="selectedLocation.parent">
              <span class="detail-label">Parent</span>
              <span class="detail-value">{{ selectedLocation.parent.name }}</span>
            </div>
            <div class="detail-row" v-if="selectedLocation.description">
              <span class="detail-label">Description</span>
              <span class="detail-value">{{ selectedLocation.description }}</span>
            </div>
            <div class="detail-row">
              <span class="detail-label">Statut</span>
              <span class="status-badge" :class="selectedLocation.is_active ? 'active' : 'inactive'">
                {{ selectedLocation.is_active ? 'Actif' : 'Inactif' }}
              </span>
            </div>
          </div>

          <!-- Sous-emplacements -->
          <div class="detail-section" v-if="selectedLocation.children?.length">
            <h4>📂 Sous-emplacements ({{ selectedLocation.children.length }})</h4>
            <div class="items-list">
              <div v-for="child in selectedLocation.children" :key="child.id" class="item-row">
                <span class="item-code">{{ child.code }}</span>
                <span class="item-name">{{ child.name }}</span>
                <span class="status-dot" :class="child.is_active ? 'active' : 'inactive'"></span>
              </div>
            </div>
          </div>

          <!-- Équipements -->
          <div class="detail-section" v-if="selectedLocation.equipments?.length">
            <h4>⚙️ Équipements ({{ selectedLocation.equipments.length }})</h4>
            <div class="items-list">
              <div v-for="eq in selectedLocation.equipments" :key="eq.id" class="item-row">
                <span class="item-code">{{ eq.code }}</span>
                <span class="item-name">{{ eq.name }}</span>
                <span class="status-badge mini" :class="eq.status">{{ eq.status }}</span>
              </div>
            </div>
          </div>

          <div class="modal-footer">
            <button class="btn btn-secondary" @click="showDetailModal = false">Fermer</button>
            <button class="btn btn-warning" @click="openEditModal(selectedLocation); showDetailModal = false">
              ✏️ Modifier
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.locations-page {
  padding: 30px;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 25px;
}

.page-header h1 {
  font-size: 28px;
  color: #2c3e50;
  margin: 0;
}

.subtitle {
  color: #7f8c8d;
  margin: 5px 0 0;
}

.btn {
  padding: 10px 20px;
  border: none;
  border-radius: 8px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-primary { background: linear-gradient(135deg, #3498db, #2980b9); color: white; }
.btn-secondary { background: #ecf0f1; color: #2c3e50; }
.btn-warning { background: linear-gradient(135deg, #f39c12, #d68910); color: white; }

/* Stats */
.stats-cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 15px;
  margin-bottom: 25px;
}

.stat-card {
  background: white;
  border-radius: 12px;
  padding: 18px;
  display: flex;
  align-items: center;
  gap: 12px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.stat-icon {
  width: 45px;
  height: 45px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 22px;
}

.stat-icon.blue { background: #e8f4fd; }
.stat-icon.green { background: #d4edda; }
.stat-icon.purple { background: #e8daef; }

.stat-value {
  font-size: 26px;
  font-weight: 700;
  color: #2c3e50;
}

.stat-label {
  font-size: 12px;
  color: #7f8c8d;
}

/* Filters */
.filters-bar {
  display: flex;
  gap: 15px;
  margin-bottom: 25px;
  align-items: center;
}

.search-box {
  flex: 1;
  max-width: 300px;
  position: relative;
}

.search-box input {
  width: 100%;
  padding: 10px 15px 10px 40px;
  border: 1px solid #ddd;
  border-radius: 8px;
  font-size: 14px;
}

.search-icon {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
}

.view-toggle {
  display: flex;
  background: white;
  border-radius: 8px;
  overflow: hidden;
  border: 1px solid #ddd;
  margin-left: auto;
}

.view-toggle button {
  padding: 10px 16px;
  border: none;
  background: transparent;
  cursor: pointer;
  font-size: 13px;
}

.view-toggle button.active {
  background: #3498db;
  color: white;
}

/* Tree View */
.tree-view {
  background: white;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.tree-node {
  margin-bottom: 5px;
}

.node-content {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 15px;
  background: #f8f9fa;
  border-radius: 8px;
  transition: all 0.2s;
}

.node-content:hover {
  background: #e9ecef;
}

.node-content.inactive {
  opacity: 0.5;
}

.node-icon {
  font-size: 20px;
}

.node-info {
  flex: 1;
  display: flex;
  align-items: center;
  gap: 12px;
}

.node-code {
  font-family: monospace;
  font-size: 11px;
  color: #7f8c8d;
  background: #e0e0e0;
  padding: 2px 6px;
  border-radius: 3px;
}

.node-name {
  font-weight: 500;
  color: #2c3e50;
}

.node-count {
  font-size: 12px;
  color: #7f8c8d;
}

.node-actions {
  display: flex;
  gap: 5px;
  opacity: 0;
  transition: opacity 0.2s;
}

.node-content:hover .node-actions {
  opacity: 1;
}

.node-actions button {
  width: 28px;
  height: 28px;
  border: none;
  background: white;
  border-radius: 5px;
  cursor: pointer;
  font-size: 12px;
}

.node-actions button:hover {
  background: #ddd;
}

/* Table */
.table-container {
  background: white;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.locations-table {
  width: 100%;
  border-collapse: collapse;
}

.locations-table th {
  text-align: left;
  padding: 15px;
  background: #f8f9fa;
  font-size: 12px;
  text-transform: uppercase;
  color: #7f8c8d;
}

.locations-table td {
  padding: 15px;
  border-top: 1px solid #eee;
}

.locations-table tr:hover {
  background: #f8f9fa;
}

.locations-table tr.inactive {
  opacity: 0.6;
}

.code-cell strong {
  font-family: monospace;
  color: #3498db;
}

.location-name {
  font-weight: 500;
  color: #2c3e50;
}

.location-desc {
  font-size: 12px;
  color: #7f8c8d;
}

.parent-badge {
  background: #e8f4fd;
  color: #3498db;
  padding: 4px 10px;
  border-radius: 15px;
  font-size: 12px;
}

.count-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  background: #e8f4fd;
  color: #3498db;
  border-radius: 50%;
  font-size: 12px;
  font-weight: 600;
}

.count-badge.empty {
  background: #f0f0f0;
  color: #95a5a6;
}

.status-badge {
  padding: 4px 10px;
  border-radius: 15px;
  font-size: 11px;
  font-weight: 500;
}

.status-badge.active { background: #d4edda; color: #155724; }
.status-badge.inactive { background: #f8d7da; color: #721c24; }

.status-badge.mini {
  padding: 2px 6px;
  font-size: 10px;
}

.status-badge.operational { background: #d4edda; color: #155724; }
.status-badge.degraded { background: #fff3cd; color: #856404; }
.status-badge.stopped { background: #f8d7da; color: #721c24; }

.status-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
}

.status-dot.active { background: #27ae60; }
.status-dot.inactive { background: #e74c3c; }

.actions-cell {
  white-space: nowrap;
}

.action-buttons {
  display: flex;
  gap: 5px;
}

.btn-icon {
  width: 32px;
  height: 32px;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 14px;
  background: #f0f0f0;
  transition: all 0.2s;
}

.btn-icon:hover { background: #e0e0e0; }
.btn-icon.primary { background: #e8f4fd; }
.btn-icon.success { background: #d4edda; }
.btn-icon.warning { background: #fff3cd; }
.btn-icon.danger { background: #f8d7da; }

/* Empty & Loading */
.empty-state {
  text-align: center;
  padding: 60px;
  color: #7f8c8d;
}

.empty-icon {
  font-size: 60px;
  opacity: 0.5;
}

.loading-state {
  text-align: center;
  padding: 60px;
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

/* Modal */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0,0,0,0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2000;
}

.modal {
  background: white;
  border-radius: 12px;
  width: 100%;
  max-width: 550px;
  max-height: 90vh;
  overflow: hidden;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px;
  border-bottom: 1px solid #eee;
}

.modal-header h2 {
  margin: 0;
  font-size: 18px;
}

.close-btn {
  background: none;
  border: none;
  font-size: 24px;
  cursor: pointer;
  color: #7f8c8d;
}

.modal-body {
  padding: 20px;
  overflow-y: auto;
  max-height: calc(90vh - 70px);
}

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
  margin-bottom: 5px;
  font-size: 13px;
  font-weight: 500;
  color: #555;
}

.form-group input,
.form-group select,
.form-group textarea {
  width: 100%;
  padding: 10px;
  border: 1px solid #ddd;
  border-radius: 6px;
  font-size: 14px;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
  outline: none;
  border-color: #3498db;
}

.checkbox-group label {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
}

.checkbox-group input[type="checkbox"] {
  width: 18px;
  height: 18px;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding-top: 15px;
  border-top: 1px solid #eee;
  margin-top: 10px;
}

/* Detail sections */
.detail-section {
  margin-bottom: 20px;
}

.detail-section h4 {
  margin: 0 0 12px;
  font-size: 14px;
  color: #2c3e50;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  padding: 10px 0;
  border-bottom: 1px solid #eee;
}

.detail-label {
  color: #7f8c8d;
}

.detail-value {
  font-weight: 500;
  color: #2c3e50;
}

.items-list {
  background: #f8f9fa;
  border-radius: 8px;
  padding: 10px;
}

.item-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 8px;
  border-bottom: 1px solid #eee;
}

.item-row:last-child {
  border-bottom: none;
}

.item-code {
  font-family: monospace;
  font-size: 11px;
  color: #7f8c8d;
}

.item-name {
  flex: 1;
  font-size: 13px;
}

.alert {
  padding: 15px;
  border-radius: 8px;
  margin-bottom: 20px;
}

.alert-error {
  background: #f8d7da;
  color: #721c24;
}

.text-muted {
  color: #95a5a6;
}

@media (max-width: 768px) {
  .form-row {
    grid-template-columns: 1fr;
  }

  .filters-bar {
    flex-direction: column;
    align-items: stretch;
  }

  .search-box {
    max-width: 100%;
  }

  .view-toggle {
    margin-left: 0;
  }
}
</style>
