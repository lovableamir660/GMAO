<script setup>
import { ref, onMounted, computed } from 'vue'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
const parts = ref([])
const loading = ref(false)
const error = ref('')
const showModal = ref(false)
const showStockModal = ref(false)
const editingPart = ref(null)
const selectedPart = ref(null)
const activeView = ref('table') // table, grid
const pagination = ref({})

// Filtres
const search = ref('')
const categoryFilter = ref('')
const stockFilter = ref('')
const categories = ref([])

// Stats
const stats = ref({
  total: 0,
  lowStock: 0,
  outOfStock: 0,
  totalValue: 0,
})

const form = ref({
  code: '',
  name: '',
  description: '',
  category: '',
  unit: 'unité',
  unit_price: 0,
  quantity_in_stock: 0,
  minimum_stock: 0,
  location_in_warehouse: '',
  manufacturer: '',
})

const stockForm = ref({
  type: 'in',
  quantity: 1,
  reason: '',
  notes: '',
})

const units = [
  { value: 'unité', label: 'Unité' },
  { value: 'kg', label: 'Kilogramme' },
  { value: 'litre', label: 'Litre' },
  { value: 'mètre', label: 'Mètre' },
  { value: 'pièce', label: 'Pièce' },
  { value: 'boîte', label: 'Boîte' },
  { value: 'rouleau', label: 'Rouleau' },
]

const stockReasons = {
  in: [
    { value: 'purchase', label: 'Achat' },
    { value: 'return', label: 'Retour' },
    { value: 'adjustment', label: 'Ajustement inventaire' },
    { value: 'transfer_in', label: 'Transfert entrant' },
  ],
  out: [
    { value: 'work_order', label: 'Ordre de travail' },
    { value: 'damage', label: 'Dommage/Perte' },
    { value: 'adjustment', label: 'Ajustement inventaire' },
    { value: 'transfer_out', label: 'Transfert sortant' },
  ],
}

async function fetchParts(page = 1) {
  loading.value = true
  error.value = ''
  try {
    const params = { page, per_page: 20 }
    if (search.value) params.search = search.value
    if (categoryFilter.value) params.category = categoryFilter.value
    if (stockFilter.value) params.stock_status = stockFilter.value

    const response = await api.get('/parts', { params })
    parts.value = response.data.data
    pagination.value = {
      current_page: response.data.current_page,
      last_page: response.data.last_page,
      total: response.data.total,
    }
    calculateStats()
  } catch (err) {
    error.value = 'Erreur lors du chargement des pièces'
    console.error(err)
  } finally {
    loading.value = false
  }
}

async function fetchCategories() {
  try {
    const response = await api.get('/parts')
    const allParts = response.data.data
    const cats = [...new Set(allParts.map(p => p.category).filter(Boolean))]
    categories.value = cats
  } catch (err) {
    console.error(err)
  }
}

function calculateStats() {
  stats.value = {
    total: parts.value.length,
    lowStock: parts.value.filter(p => p.quantity_in_stock > 0 && p.quantity_in_stock <= p.minimum_stock).length,
    outOfStock: parts.value.filter(p => p.quantity_in_stock <= 0).length,
    totalValue: parts.value.reduce((sum, p) => sum + (p.quantity_in_stock * p.unit_price), 0),
  }
}

function openCreateModal() {
  editingPart.value = null
  form.value = {
    code: '',
    name: '',
    description: '',
    category: '',
    unit: 'unité',
    unit_price: 0,
    quantity_in_stock: 0,
    minimum_stock: 0,
    location_in_warehouse: '',
    manufacturer: '',
  }
  showModal.value = true
}

function openEditModal(part) {
  editingPart.value = part
  form.value = { ...part }
  showModal.value = true
}

function openStockModal(part, type = 'in') {
  selectedPart.value = part
  stockForm.value = {
    type,
    quantity: 1,
    reason: type === 'in' ? 'purchase' : 'work_order',
    notes: '',
  }
  showStockModal.value = true
}

async function savePart() {
  try {
    if (editingPart.value) {
      await api.put(`/parts/${editingPart.value.id}`, form.value)
    } else {
      await api.post('/parts', form.value)
    }
    showModal.value = false
    fetchParts()
  } catch (err) {
    error.value = err.response?.data?.message || 'Erreur lors de la sauvegarde'
  }
}

async function updateStock() {
  try {
    const newQuantity = stockForm.value.type === 'in'
      ? selectedPart.value.quantity_in_stock + parseInt(stockForm.value.quantity)
      : selectedPart.value.quantity_in_stock - parseInt(stockForm.value.quantity)

    if (newQuantity < 0) {
      error.value = 'Stock insuffisant'
      return
    }

    await api.put(`/parts/${selectedPart.value.id}`, {
      ...selectedPart.value,
      quantity_in_stock: newQuantity,
    })

    showStockModal.value = false
    fetchParts()
  } catch (err) {
    error.value = err.response?.data?.message || 'Erreur lors de la mise à jour du stock'
  }
}

async function deletePart(part) {
  if (!confirm(`Supprimer la pièce "${part.name}" ?`)) return

  try {
    await api.delete(`/parts/${part.id}`)
    fetchParts()
  } catch (err) {
    error.value = 'Erreur lors de la suppression'
  }
}

function getStockStatus(part) {
  if (part.quantity_in_stock <= 0) return { class: 'danger', text: 'Rupture', icon: '🔴' }
  if (part.quantity_in_stock <= part.minimum_stock) return { class: 'warning', text: 'Stock bas', icon: '🟠' }
  return { class: 'success', text: 'OK', icon: '🟢' }
}

function getStockPercent(part) {
  if (part.minimum_stock === 0) return 100
  const percent = (part.quantity_in_stock / (part.minimum_stock * 2)) * 100
  return Math.min(percent, 100)
}

function formatCurrency(value) {
  return new Intl.NumberFormat('fr-DZ', {
    style: 'decimal',
    minimumFractionDigits: 2,
  }).format(value) + ' DA'
}

function applyFilters() {
  fetchParts()
}

function resetFilters() {
  search.value = ''
  categoryFilter.value = ''
  stockFilter.value = ''
  fetchParts()
}

onMounted(() => {
  fetchParts()
  fetchCategories()
})
</script>

<template>
  <div class="parts-page">
    <header class="page-header">
      <div>
        <h1>🔩 Pièces détachées</h1>
        <p class="subtitle">Gestion du catalogue et des stocks</p>
      </div>
      <button class="btn btn-primary" @click="openCreateModal" v-if="authStore.hasPermission('part:create')">
        + Nouvelle pièce
      </button>
    </header>

    <!-- Stats Cards -->
    <div class="stats-cards">
      <div class="stat-card">
        <div class="stat-icon blue">📦</div>
        <div class="stat-content">
          <div class="stat-value">{{ pagination.total || 0 }}</div>
          <div class="stat-label">Références</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon green">💰</div>
        <div class="stat-content">
          <div class="stat-value">{{ formatCurrency(stats.totalValue) }}</div>
          <div class="stat-label">Valeur stock</div>
        </div>
      </div>
      <div class="stat-card warning" @click="stockFilter = 'low'; applyFilters()">
        <div class="stat-icon orange">⚠️</div>
        <div class="stat-content">
          <div class="stat-value">{{ stats.lowStock }}</div>
          <div class="stat-label">Stock bas</div>
        </div>
      </div>
      <div class="stat-card danger" @click="stockFilter = 'out'; applyFilters()">
        <div class="stat-icon red">🚨</div>
        <div class="stat-content">
          <div class="stat-value">{{ stats.outOfStock }}</div>
          <div class="stat-label">Ruptures</div>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="filters-bar">
      <div class="search-box">
        <span class="search-icon">🔍</span>
        <input
          type="text"
          v-model="search"
          placeholder="Rechercher par code, nom, fabricant..."
          @input="applyFilters"
        />
      </div>
      <select v-model="categoryFilter" @change="applyFilters">
        <option value="">Toutes catégories</option>
        <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
      </select>
      <select v-model="stockFilter" @change="applyFilters">
        <option value="">Tous les stocks</option>
        <option value="ok">Stock OK</option>
        <option value="low">Stock bas</option>
        <option value="out">Rupture</option>
      </select>
      <button class="btn btn-secondary" @click="resetFilters" v-if="search || categoryFilter || stockFilter">
        ✕ Reset
      </button>
      <div class="view-toggle">
        <button :class="{ active: activeView === 'table' }" @click="activeView = 'table'">☰</button>
        <button :class="{ active: activeView === 'grid' }" @click="activeView = 'grid'">▦</button>
      </div>
    </div>

    <div class="alert alert-error" v-if="error">{{ error }}</div>

    <!-- Loading -->
    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <p>Chargement...</p>
    </div>

    <!-- Table View -->
    <div v-else-if="activeView === 'table'" class="table-container">
      <table class="parts-table" v-if="parts.length">
        <thead>
          <tr>
            <th>Référence</th>
            <th>Désignation</th>
            <th>Catégorie</th>
            <th>Emplacement</th>
            <th>Stock</th>
            <th>Statut</th>
            <th>Prix unitaire</th>
            <th>Valeur</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="part in parts" :key="part.id" :class="{ 'low-stock': getStockStatus(part).class !== 'success' }">
            <td class="code-cell">
              <strong>{{ part.code }}</strong>
            </td>
            <td class="name-cell">
              <div class="part-name">{{ part.name }}</div>
              <div class="part-manufacturer" v-if="part.manufacturer">{{ part.manufacturer }}</div>
            </td>
            <td>
              <span class="category-badge" v-if="part.category">{{ part.category }}</span>
              <span v-else class="text-muted">-</span>
            </td>
            <td>
              <span v-if="part.location_in_warehouse" class="location-badge">
                📍 {{ part.location_in_warehouse }}
              </span>
              <span v-else class="text-muted">-</span>
            </td>
            <td class="stock-cell">
              <div class="stock-info">
                <span class="stock-quantity">{{ part.quantity_in_stock }}</span>
                <span class="stock-unit">{{ part.unit }}</span>
              </div>
              <div class="stock-bar">
                <div 
                  class="stock-bar-fill" 
                  :class="getStockStatus(part).class"
                  :style="{ width: getStockPercent(part) + '%' }"
                ></div>
              </div>
              <div class="stock-min">Min: {{ part.minimum_stock }}</div>
            </td>
            <td>
              <span class="status-badge" :class="getStockStatus(part).class">
                {{ getStockStatus(part).icon }} {{ getStockStatus(part).text }}
              </span>
            </td>
            <td class="price-cell">{{ formatCurrency(part.unit_price) }}</td>
            <td class="value-cell">{{ formatCurrency(part.quantity_in_stock * part.unit_price) }}</td>
            <td class="actions-cell">
              <div class="action-buttons">
                <button class="btn-icon success" @click="openStockModal(part, 'in')" title="Entrée stock">
                  ➕
                </button>
                <button class="btn-icon warning" @click="openStockModal(part, 'out')" title="Sortie stock">
                  ➖
                </button>
                <button class="btn-icon primary" @click="openEditModal(part)" title="Modifier" v-if="authStore.hasPermission('part:update')">
                  ✏️
                </button>
                <button class="btn-icon danger" @click="deletePart(part)" title="Supprimer" v-if="authStore.hasPermission('part:delete')">
                  🗑️
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
      <div v-else class="empty-state">
        <span class="empty-icon">📦</span>
        <h3>Aucune pièce trouvée</h3>
        <p>Ajoutez des pièces ou modifiez vos filtres</p>
      </div>
    </div>

    <!-- Grid View -->
    <div v-else class="parts-grid">
      <div v-for="part in parts" :key="part.id" class="part-card" :class="getStockStatus(part).class">
        <div class="part-card-header">
          <span class="part-code">{{ part.code }}</span>
          <span class="status-dot" :class="getStockStatus(part).class"></span>
        </div>
        
        <h3 class="part-title">{{ part.name }}</h3>
        <p class="part-manufacturer" v-if="part.manufacturer">{{ part.manufacturer }}</p>
        
        <div class="part-stock">
          <div class="stock-display">
            <span class="stock-number">{{ part.quantity_in_stock }}</span>
            <span class="stock-unit">{{ part.unit }}</span>
          </div>
          <div class="stock-bar large">
            <div 
              class="stock-bar-fill" 
              :class="getStockStatus(part).class"
              :style="{ width: getStockPercent(part) + '%' }"
            ></div>
          </div>
          <div class="stock-meta">
            <span>Min: {{ part.minimum_stock }}</span>
            <span>{{ getStockStatus(part).text }}</span>
          </div>
        </div>

        <div class="part-details">
          <div class="detail-row" v-if="part.category">
            <span class="detail-label">Catégorie</span>
            <span class="detail-value">{{ part.category }}</span>
          </div>
          <div class="detail-row" v-if="part.location_in_warehouse">
            <span class="detail-label">Emplacement</span>
            <span class="detail-value">📍 {{ part.location_in_warehouse }}</span>
          </div>
          <div class="detail-row">
            <span class="detail-label">Prix unitaire</span>
            <span class="detail-value">{{ formatCurrency(part.unit_price) }}</span>
          </div>
          <div class="detail-row">
            <span class="detail-label">Valeur stock</span>
            <span class="detail-value bold">{{ formatCurrency(part.quantity_in_stock * part.unit_price) }}</span>
          </div>
        </div>

        <div class="part-card-actions">
          <button class="btn-sm success" @click="openStockModal(part, 'in')">➕ Entrée</button>
          <button class="btn-sm warning" @click="openStockModal(part, 'out')">➖ Sortie</button>
          <button class="btn-sm primary" @click="openEditModal(part)" v-if="authStore.hasPermission('part:update')">✏️</button>
        </div>
      </div>

      <div v-if="parts.length === 0" class="empty-state full-width">
        <span class="empty-icon">📦</span>
        <h3>Aucune pièce trouvée</h3>
        <p>Ajoutez des pièces ou modifiez vos filtres</p>
      </div>
    </div>

    <!-- Pagination -->
    <div class="pagination" v-if="pagination.last_page > 1">
      <button
        v-for="page in pagination.last_page"
        :key="page"
        :class="{ active: page === pagination.current_page }"
        @click="fetchParts(page)"
      >
        {{ page }}
      </button>
    </div>

    <!-- Modal Pièce -->
    <div class="modal-overlay" v-if="showModal" @click.self="showModal = false">
      <div class="modal">
        <div class="modal-header">
          <h2>{{ editingPart ? '✏️ Modifier la pièce' : '➕ Nouvelle pièce' }}</h2>
          <button class="close-btn" @click="showModal = false">&times;</button>
        </div>
        <form @submit.prevent="savePart" class="modal-body">
          <div class="form-section">
            <h3>Informations générales</h3>
            <div class="form-row">
              <div class="form-group">
                <label>Code *</label>
                <input type="text" v-model="form.code" required :disabled="!!editingPart" placeholder="REF-001" />
              </div>
              <div class="form-group">
                <label>Désignation *</label>
                <input type="text" v-model="form.name" required placeholder="Nom de la pièce" />
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label>Catégorie</label>
                <input type="text" v-model="form.category" list="categories-list" placeholder="Ex: Électrique" />
                <datalist id="categories-list">
                  <option v-for="cat in categories" :key="cat" :value="cat" />
                </datalist>
              </div>
              <div class="form-group">
                <label>Fabricant</label>
                <input type="text" v-model="form.manufacturer" placeholder="Ex: Siemens" />
              </div>
            </div>

            <div class="form-group">
              <label>Description</label>
              <textarea v-model="form.description" rows="2" placeholder="Description détaillée..."></textarea>
            </div>
          </div>

          <div class="form-section">
            <h3>Stock & Prix</h3>
            <div class="form-row">
              <div class="form-group">
                <label>Unité</label>
                <select v-model="form.unit">
                  <option v-for="unit in units" :key="unit.value" :value="unit.value">
                    {{ unit.label }}
                  </option>
                </select>
              </div>
              <div class="form-group">
                <label>Prix unitaire (DA)</label>
                <input type="number" v-model="form.unit_price" step="0.01" min="0" />
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label>Quantité en stock</label>
                <input type="number" v-model="form.quantity_in_stock" min="0" :disabled="!!editingPart" />
                <small v-if="editingPart" class="form-hint">Utilisez les boutons +/- pour modifier le stock</small>
              </div>
              <div class="form-group">
                <label>Stock minimum (alerte)</label>
                <input type="number" v-model="form.minimum_stock" min="0" />
              </div>
            </div>

            <div class="form-group">
              <label>Emplacement magasin</label>
              <input type="text" v-model="form.location_in_warehouse" placeholder="Ex: Étagère A3, Bac 12" />
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" @click="showModal = false">Annuler</button>
            <button type="submit" class="btn btn-primary">
              {{ editingPart ? 'Enregistrer' : 'Créer' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal Stock -->
    <div class="modal-overlay" v-if="showStockModal" @click.self="showStockModal = false">
      <div class="modal modal-sm">
        <div class="modal-header">
          <h2>{{ stockForm.type === 'in' ? '➕ Entrée stock' : '➖ Sortie stock' }}</h2>
          <button class="close-btn" @click="showStockModal = false">&times;</button>
        </div>
        <form @submit.prevent="updateStock" class="modal-body">
          <div class="part-summary" v-if="selectedPart">
            <div class="summary-name">{{ selectedPart.name }}</div>
            <div class="summary-stock">
              Stock actuel: <strong>{{ selectedPart.quantity_in_stock }} {{ selectedPart.unit }}</strong>
            </div>
          </div>

          <div class="stock-type-toggle">
            <button 
              type="button" 
              :class="{ active: stockForm.type === 'in' }"
              @click="stockForm.type = 'in'; stockForm.reason = 'purchase'"
            >
              ➕ Entrée
            </button>
            <button 
              type="button" 
              :class="{ active: stockForm.type === 'out' }"
              @click="stockForm.type = 'out'; stockForm.reason = 'work_order'"
            >
              ➖ Sortie
            </button>
          </div>

          <div class="form-group">
            <label>Quantité *</label>
            <input type="number" v-model="stockForm.quantity" min="1" required />
          </div>

          <div class="form-group">
            <label>Raison</label>
            <select v-model="stockForm.reason">
              <option 
                v-for="reason in stockReasons[stockForm.type]" 
                :key="reason.value" 
                :value="reason.value"
              >
                {{ reason.label }}
              </option>
            </select>
          </div>

          <div class="form-group">
            <label>Notes</label>
            <textarea v-model="stockForm.notes" rows="2" placeholder="Commentaire..."></textarea>
          </div>

          <div class="stock-preview">
            <span>Nouveau stock:</span>
            <strong :class="stockForm.type === 'in' ? 'text-success' : 'text-warning'">
              {{ stockForm.type === 'in' 
                ? selectedPart.quantity_in_stock + parseInt(stockForm.quantity || 0)
                : selectedPart.quantity_in_stock - parseInt(stockForm.quantity || 0)
              }} {{ selectedPart?.unit }}
            </strong>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" @click="showStockModal = false">Annuler</button>
            <button type="submit" class="btn" :class="stockForm.type === 'in' ? 'btn-success' : 'btn-warning'">
              {{ stockForm.type === 'in' ? 'Ajouter' : 'Retirer' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<style scoped>
.parts-page {
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
  font-size: 14px;
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

.btn-primary {
  background: linear-gradient(135deg, #3498db, #2980b9);
  color: white;
}

.btn-success {
  background: linear-gradient(135deg, #27ae60, #1e8449);
  color: white;
}

.btn-warning {
  background: linear-gradient(135deg, #f39c12, #d68910);
  color: white;
}

.btn-secondary {
  background: #ecf0f1;
  color: #2c3e50;
}

.btn-danger {
  background: linear-gradient(135deg, #e74c3c, #c0392b);
  color: white;
}

/* Stats Cards */
.stats-cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 20px;
  margin-bottom: 25px;
}

.stat-card {
  background: white;
  border-radius: 12px;
  padding: 20px;
  display: flex;
  align-items: center;
  gap: 15px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
  cursor: pointer;
  transition: all 0.2s;
}

.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.stat-card.warning {
  border-left: 4px solid #f39c12;
}

.stat-card.danger {
  border-left: 4px solid #e74c3c;
}

.stat-icon {
  width: 50px;
  height: 50px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
}

.stat-icon.blue { background: #e8f4fd; }
.stat-icon.green { background: #d4edda; }
.stat-icon.orange { background: #fff3cd; }
.stat-icon.red { background: #f8d7da; }

.stat-value {
  font-size: 24px;
  font-weight: 700;
  color: #2c3e50;
}

.stat-label {
  font-size: 13px;
  color: #7f8c8d;
}

/* Filters */
.filters-bar {
  display: flex;
  gap: 15px;
  margin-bottom: 25px;
  flex-wrap: wrap;
  align-items: center;
}

.search-box {
  flex: 1;
  min-width: 250px;
  max-width: 400px;
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

.filters-bar select {
  padding: 10px 15px;
  border: 1px solid #ddd;
  border-radius: 8px;
  font-size: 14px;
  min-width: 150px;
}

.view-toggle {
  display: flex;
  background: white;
  border-radius: 8px;
  overflow: hidden;
  border: 1px solid #ddd;
}

.view-toggle button {
  padding: 8px 12px;
  border: none;
  background: transparent;
  cursor: pointer;
  font-size: 16px;
}

.view-toggle button.active {
  background: #3498db;
  color: white;
}

/* Table */
.table-container {
  background: white;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.parts-table {
  width: 100%;
  border-collapse: collapse;
}

.parts-table th {
  text-align: left;
  padding: 15px;
  background: #f8f9fa;
  font-size: 12px;
  text-transform: uppercase;
  color: #7f8c8d;
  font-weight: 600;
}

.parts-table td {
  padding: 15px;
  border-top: 1px solid #eee;
  vertical-align: middle;
}

.parts-table tr:hover {
  background: #f8f9fa;
}

.parts-table tr.low-stock {
  background: #fff9e6;
}

.code-cell strong {
  font-family: monospace;
  color: #3498db;
}

.part-name {
  font-weight: 500;
  color: #2c3e50;
}

.part-manufacturer {
  font-size: 12px;
  color: #7f8c8d;
}

.category-badge {
  padding: 4px 10px;
  background: #e8f4fd;
  color: #3498db;
  border-radius: 15px;
  font-size: 12px;
}

.location-badge {
  font-size: 12px;
  color: #7f8c8d;
}

.stock-cell {
  min-width: 120px;
}

.stock-info {
  display: flex;
  align-items: baseline;
  gap: 5px;
  margin-bottom: 5px;
}

.stock-quantity {
  font-size: 18px;
  font-weight: 700;
  color: #2c3e50;
}

.stock-unit {
  font-size: 12px;
  color: #7f8c8d;
}

.stock-bar {
  height: 6px;
  background: #ecf0f1;
  border-radius: 3px;
  overflow: hidden;
  margin-bottom: 4px;
}

.stock-bar.large {
  height: 10px;
  border-radius: 5px;
}

.stock-bar-fill {
  height: 100%;
  border-radius: 3px;
  transition: width 0.3s;
}

.stock-bar-fill.success { background: #27ae60; }
.stock-bar-fill.warning { background: #f39c12; }
.stock-bar-fill.danger { background: #e74c3c; }

.stock-min {
  font-size: 11px;
  color: #95a5a6;
}

.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 5px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 500;
}

.status-badge.success { background: #d4edda; color: #155724; }
.status-badge.warning { background: #fff3cd; color: #856404; }
.status-badge.danger { background: #f8d7da; color: #721c24; }

.price-cell,
.value-cell {
  font-family: monospace;
  white-space: nowrap;
}

.value-cell {
  font-weight: 600;
  color: #2c3e50;
}

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
  transition: all 0.2s;
}

.btn-icon.primary { background: #e8f4fd; }
.btn-icon.primary:hover { background: #cce5ff; }
.btn-icon.success { background: #d4edda; }
.btn-icon.success:hover { background: #c3e6cb; }
.btn-icon.warning { background: #fff3cd; }
.btn-icon.warning:hover { background: #ffeeba; }
.btn-icon.danger { background: #f8d7da; }
.btn-icon.danger:hover { background: #f5c6cb; }

/* Grid View */
.parts-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 20px;
}

.part-card {
  background: white;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
  border-top: 4px solid #27ae60;
  transition: all 0.2s;
}

.part-card:hover {
  box-shadow: 0 4px 15px rgba(0,0,0,0.1);
  transform: translateY(-2px);
}

.part-card.warning { border-top-color: #f39c12; }
.part-card.danger { border-top-color: #e74c3c; }

.part-card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 10px;
}

.part-code {
  font-family: monospace;
  font-size: 12px;
  color: #3498db;
  background: #e8f4fd;
  padding: 3px 8px;
  border-radius: 4px;
}

.status-dot {
  width: 12px;
  height: 12px;
  border-radius: 50%;
}

.status-dot.success { background: #27ae60; }
.status-dot.warning { background: #f39c12; }
.status-dot.danger { background: #e74c3c; }

.part-title {
  font-size: 16px;
  color: #2c3e50;
  margin: 0 0 5px;
}

.part-card .part-manufacturer {
  font-size: 13px;
  color: #7f8c8d;
  margin-bottom: 15px;
}

.part-stock {
  background: #f8f9fa;
  padding: 15px;
  border-radius: 8px;
  margin-bottom: 15px;
}

.stock-display {
  display: flex;
  align-items: baseline;
  gap: 8px;
  margin-bottom: 10px;
}

.stock-number {
  font-size: 32px;
  font-weight: 700;
  color: #2c3e50;
}

.stock-meta {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
  color: #7f8c8d;
  margin-top: 8px;
}

.part-details {
  margin-bottom: 15px;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  padding: 8px 0;
  border-bottom: 1px solid #eee;
  font-size: 13px;
}

.detail-row:last-child {
  border-bottom: none;
}

.detail-label {
  color: #7f8c8d;
}

.detail-value {
  color: #2c3e50;
}

.detail-value.bold {
  font-weight: 600;
}

.part-card-actions {
  display: flex;
  gap: 8px;
}

.btn-sm {
  padding: 8px 12px;
  border: none;
  border-radius: 6px;
  font-size: 12px;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-sm.success { background: #d4edda; color: #155724; }
.btn-sm.warning { background: #fff3cd; color: #856404; }
.btn-sm.primary { background: #e8f4fd; color: #3498db; }

.btn-sm:hover {
  opacity: 0.8;
}

/* Empty State */
.empty-state {
  text-align: center;
  padding: 60px;
  color: #7f8c8d;
}

.empty-state.full-width {
  grid-column: 1 / -1;
}

.empty-icon {
  font-size: 60px;
  opacity: 0.5;
}

.empty-state h3 {
  margin: 15px 0 5px;
  color: #2c3e50;
}

/* Loading */
.loading-state {
  text-align: center;
  padding: 60px;
  color: #7f8c8d;
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

/* Pagination */
.pagination {
  display: flex;
  justify-content: center;
  gap: 5px;
  margin-top: 25px;
}

.pagination button {
  padding: 8px 14px;
  border: 1px solid #ddd;
  background: white;
  border-radius: 6px;
  cursor: pointer;
}

.pagination button.active {
  background: #3498db;
  color: white;
  border-color: #3498db;
}

/* Alert */
.alert {
  padding: 15px;
  border-radius: 8px;
  margin-bottom: 20px;
}

.alert-error {
  background: #f8d7da;
  color: #721c24;
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
}

.modal {
  background: white;
  border-radius: 12px;
  width: 100%;
  max-width: 650px;
  max-height: 90vh;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.modal-sm {
  max-width: 450px;
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
  color: #2c3e50;
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
}

.form-section {
  margin-bottom: 25px;
}

.form-section h3 {
  font-size: 14px;
  color: #7f8c8d;
  text-transform: uppercase;
  margin: 0 0 15px;
  padding-bottom: 8px;
  border-bottom: 1px solid #eee;
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

.form-group input:disabled {
  background: #f8f9fa;
  cursor: not-allowed;
}

.form-hint {
  font-size: 11px;
  color: #95a5a6;
  margin-top: 4px;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding-top: 15px;
  border-top: 1px solid #eee;
  margin-top: 10px;
}

/* Stock Modal */
.part-summary {
  background: #f8f9fa;
  padding: 15px;
  border-radius: 8px;
  margin-bottom: 20px;
  text-align: center;
}

.summary-name {
  font-weight: 600;
  color: #2c3e50;
  margin-bottom: 5px;
}

.summary-stock {
  font-size: 14px;
  color: #7f8c8d;
}

.stock-type-toggle {
  display: flex;
  gap: 10px;
  margin-bottom: 20px;
}

.stock-type-toggle button {
  flex: 1;
  padding: 12px;
  border: 2px solid #ddd;
  border-radius: 8px;
  background: white;
  cursor: pointer;
  font-weight: 500;
  transition: all 0.2s;
}

.stock-type-toggle button.active {
  border-color: #3498db;
  background: #e8f4fd;
  color: #3498db;
}

.stock-preview {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 15px;
  background: #f8f9fa;
  border-radius: 8px;
  margin: 20px 0;
}

.text-success {
  color: #27ae60;
}

.text-warning {
  color: #f39c12;
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
}
</style>
