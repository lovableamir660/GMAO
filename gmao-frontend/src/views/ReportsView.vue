<script setup>
import { ref, onMounted, computed, nextTick, watch } from 'vue'
import { Chart, registerables } from 'chart.js'
import ChartDataLabels from 'chartjs-plugin-datalabels'
import api from '@/services/api'

Chart.register(...registerables, ChartDataLabels)

const loading = ref(false)
const error = ref('')
const activeTab = ref('overview')

// Données
const kpis = ref(null)
const woTrend = ref([])
const woByType = ref([])
const woByStatus = ref([])
const topFailures = ref([])
const costsByEquipment = ref([])
const costsTrend = ref([])
const technicianPerformance = ref([])
const criticalStock = ref([])
const partsConsumption = ref([])

// Filtres
const period = ref('month')

// Charts instances
let trendChart = null
let typeChart = null
let statusChart = null
let costChart = null

const periodDates = computed(() => {
  const now = new Date()
  let start, end
  
  switch (period.value) {
    case 'week':
      start = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000)
      end = new Date()
      break
    case 'month':
      start = new Date(now.getFullYear(), now.getMonth(), 1)
      end = new Date()
      break
    case 'quarter':
      start = new Date(now.getFullYear(), Math.floor(now.getMonth() / 3) * 3, 1)
      end = new Date()
      break
    case 'year':
      start = new Date(now.getFullYear(), 0, 1)
      end = new Date()
      break
    default:
      start = new Date(now.getFullYear(), now.getMonth(), 1)
      end = new Date()
  }
  
  return {
    start: start.toISOString().split('T')[0],
    end: end.toISOString().split('T')[0]
  }
})

async function fetchKPIs() {
  try {
    const response = await api.get('/reports/kpis', {
      params: { start_date: periodDates.value.start, end_date: periodDates.value.end }
    })
    kpis.value = response.data
  } catch (err) { console.error('KPIs error:', err) }
}

async function fetchWOTrend() {
  try {
    const response = await api.get('/reports/work-orders/trend', { params: { months: 12 } })
    woTrend.value = response.data
  } catch (err) { console.error('Trend error:', err) }
}

async function fetchWOByType() {
  try {
    const response = await api.get('/reports/work-orders/by-type', {
      params: { start_date: periodDates.value.start, end_date: periodDates.value.end }
    })
    woByType.value = response.data
  } catch (err) { console.error('Type error:', err) }
}

async function fetchWOByStatus() {
  try {
    const response = await api.get('/reports/work-orders/by-status')
    woByStatus.value = response.data
  } catch (err) { console.error('Status error:', err) }
}

async function fetchTopFailures() {
  try {
    const response = await api.get('/reports/equipments/top-failures', {
      params: { start_date: periodDates.value.start, end_date: periodDates.value.end, limit: 10 }
    })
    topFailures.value = response.data
  } catch (err) { console.error('Failures error:', err) }
}

async function fetchCostsByEquipment() {
  try {
    const response = await api.get('/reports/equipments/costs', {
      params: { start_date: periodDates.value.start, end_date: periodDates.value.end, limit: 10 }
    })
    costsByEquipment.value = response.data
  } catch (err) { console.error('Costs by eq error:', err) }
}

async function fetchCostsTrend() {
  try {
    const response = await api.get('/reports/costs/trend', { params: { months: 12 } })
    costsTrend.value = response.data
  } catch (err) { console.error('Costs trend error:', err) }
}

async function fetchTechnicianPerformance() {
  try {
    const response = await api.get('/reports/technicians/performance', {
      params: { start_date: periodDates.value.start, end_date: periodDates.value.end }
    })
    technicianPerformance.value = response.data
  } catch (err) { console.error('Technician error:', err) }
}

async function fetchCriticalStock() {
  try {
    const response = await api.get('/reports/stock/critical')
    criticalStock.value = response.data
  } catch (err) { console.error('Stock error:', err) }
}

async function fetchPartsConsumption() {
  try {
    const response = await api.get('/reports/parts/consumption', {
      params: { start_date: periodDates.value.start, end_date: periodDates.value.end, limit: 10 }
    })
    partsConsumption.value = response.data
  } catch (err) { console.error('Parts error:', err) }
}

function renderTrendChart() {
  const ctx = document.getElementById('trendChart')
  if (!ctx || woTrend.value.length === 0) return
  
  if (trendChart) {
    trendChart.destroy()
    trendChart = null
  }
  
  trendChart = new Chart(ctx, {
    type: 'line',
    data: {
      labels: woTrend.value.map(d => d.month_short),
      datasets: [
        {
          label: 'Créés',
          data: woTrend.value.map(d => d.created),
          borderColor: '#3498db',
          backgroundColor: 'rgba(52, 152, 219, 0.1)',
          fill: true,
          tension: 0.3,
        },
        {
          label: 'Terminés',
          data: woTrend.value.map(d => d.completed),
          borderColor: '#27ae60',
          backgroundColor: 'rgba(39, 174, 96, 0.1)',
          fill: true,
          tension: 0.3,
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { position: 'bottom' },
        datalabels: {
          display: (context) => context.dataset.data[context.dataIndex] > 0,
          color: '#2c3e50',
          font: { weight: 'bold', size: 11 },
          anchor: 'end',
          align: 'top',
        }
      },
      scales: { y: { beginAtZero: true } }
    }
  })
}

function renderTypeChart() {
  const ctx = document.getElementById('typeChart')
  if (!ctx) return
  
  const hasData = woByType.value.some(d => d.count > 0)
  if (!hasData) return
  
  if (typeChart) {
    typeChart.destroy()
    typeChart = null
  }
  
  const colors = {
    corrective: '#e74c3c',
    preventive: '#27ae60',
    improvement: '#3498db',
    inspection: '#f39c12'
  }
  
  const filteredData = woByType.value.filter(d => d.count > 0)
  
  typeChart = new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: filteredData.map(d => d.label),
      datasets: [{
        data: filteredData.map(d => d.count),
        backgroundColor: filteredData.map(d => colors[d.type] || '#95a5a6'),
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { position: 'bottom' },
        datalabels: {
          color: '#fff',
          font: { weight: 'bold', size: 14 },
          formatter: (value, context) => {
            const total = context.dataset.data.reduce((a, b) => a + b, 0)
            const percent = Math.round((value / total) * 100)
            return `${value}\n(${percent}%)`
          },
          textAlign: 'center',
        }
      }
    }
  })
}

function renderStatusChart() {
  const ctx = document.getElementById('statusChart')
  if (!ctx || woByStatus.value.length === 0) return
  
  if (statusChart) {
    statusChart.destroy()
    statusChart = null
  }
  
  const colors = {
    pending: '#f39c12',
    approved: '#3498db',
    in_progress: '#27ae60',
    on_hold: '#95a5a6',
    completed: '#17a2b8',
    cancelled: '#e74c3c'
  }
  
  statusChart = new Chart(ctx, {
    type: 'pie',
    data: {
      labels: woByStatus.value.map(d => d.label),
      datasets: [{
        data: woByStatus.value.map(d => d.count),
        backgroundColor: woByStatus.value.map(d => colors[d.status] || '#95a5a6'),
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { position: 'bottom' },
        datalabels: {
          color: '#fff',
          font: { weight: 'bold', size: 14 },
          formatter: (value, context) => {
            const total = context.dataset.data.reduce((a, b) => a + b, 0)
            const percent = Math.round((value / total) * 100)
            return `${value}\n(${percent}%)`
          },
          textAlign: 'center',
        }
      }
    }
  })
}

function renderCostChart() {
  const ctx = document.getElementById('costChart')
  if (!ctx || costsTrend.value.length === 0) return
  
  if (costChart) {
    costChart.destroy()
    costChart = null
  }
  
  costChart = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: costsTrend.value.map(d => d.month_short),
      datasets: [
        {
          label: 'Main d\'œuvre',
          data: costsTrend.value.map(d => d.labor),
          backgroundColor: '#3498db',
        },
        {
          label: 'Pièces',
          data: costsTrend.value.map(d => d.parts),
          backgroundColor: '#e74c3c',
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { position: 'bottom' },
        datalabels: {
          display: (context) => context.dataset.data[context.dataIndex] > 0,
          color: '#fff',
          font: { weight: 'bold', size: 10 },
          anchor: 'center',
          formatter: (value) => value > 1000 ? Math.round(value/1000) + 'k' : value,
        }
      },
      scales: { 
        x: { stacked: true }, 
        y: { stacked: true, beginAtZero: true } 
      }
    }
  })
}

async function renderAllCharts() {
  await nextTick()
  renderTrendChart()
  renderTypeChart()
  renderStatusChart()
  renderCostChart()
}

async function exportCSV() {
  try {
    const response = await api.get('/reports/work-orders/export', {
      params: { start_date: periodDates.value.start, end_date: periodDates.value.end },
      responseType: 'blob'
    })
    
    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `interventions_${new Date().toISOString().split('T')[0]}.csv`)
    document.body.appendChild(link)
    link.click()
    link.remove()
  } catch (err) {
    error.value = 'Erreur lors de l\'export'
  }
}

async function loadAllData() {
  loading.value = true
  
  try {
    await Promise.all([
      fetchKPIs(),
      fetchWOTrend(),
      fetchWOByType(),
      fetchWOByStatus(),
      fetchTopFailures(),
      fetchCostsByEquipment(),
      fetchCostsTrend(),
      fetchTechnicianPerformance(),
      fetchCriticalStock(),
      fetchPartsConsumption(),
    ])
  } catch (err) {
    console.error('Load error:', err)
  }
  
  loading.value = false
  
  await renderAllCharts()
}

function changePeriod(newPeriod) {
  period.value = newPeriod
  loadAllData()
}

function formatCost(value) {
  if (value === null || value === undefined) return '0 DA'
  return new Intl.NumberFormat('fr-DZ', { style: 'decimal', minimumFractionDigits: 2 }).format(value) + ' DA'
}

watch(activeTab, async (newTab) => {
  await nextTick()
  if (newTab === 'overview') {
    renderTrendChart()
    renderTypeChart()
    renderStatusChart()
  } else if (newTab === 'costs') {
    renderCostChart()
  }
})

onMounted(() => {
  loadAllData()
})
</script>

<template>
  <div class="reports-page">
    <header class="page-header">
      <div>
        <h1>📊 Rapports & KPIs</h1>
        <p class="subtitle">Indicateurs de performance et analyses</p>
      </div>
      <div class="header-actions">
        <div class="period-selector">
          <button :class="{ active: period === 'week' }" @click="changePeriod('week')">Semaine</button>
          <button :class="{ active: period === 'month' }" @click="changePeriod('month')">Mois</button>
          <button :class="{ active: period === 'quarter' }" @click="changePeriod('quarter')">Trimestre</button>
          <button :class="{ active: period === 'year' }" @click="changePeriod('year')">Année</button>
        </div>
        <button class="btn btn-primary" @click="exportCSV">📥 Export CSV</button>
      </div>
    </header>

    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <p>Chargement des données...</p>
    </div>

    <template v-else>
      <!-- KPIs Cards -->
      <div class="kpi-grid" v-if="kpis">
        <div class="kpi-card">
          <div class="kpi-icon mttr">⏱️</div>
          <div class="kpi-content">
            <div class="kpi-value">{{ kpis.kpis.mttr }} <span class="kpi-unit">h</span></div>
            <div class="kpi-label">MTTR</div>
            <div class="kpi-desc">Temps moyen de réparation</div>
          </div>
        </div>
        <div class="kpi-card">
          <div class="kpi-icon mtbf">📈</div>
          <div class="kpi-content">
            <div class="kpi-value">{{ kpis.kpis.mtbf }} <span class="kpi-unit">j</span></div>
            <div class="kpi-label">MTBF</div>
            <div class="kpi-desc">Temps moyen entre pannes</div>
          </div>
        </div>
        <div class="kpi-card">
          <div class="kpi-icon availability">✅</div>
          <div class="kpi-content">
            <div class="kpi-value">{{ kpis.kpis.availability }} <span class="kpi-unit">%</span></div>
            <div class="kpi-label">Disponibilité</div>
            <div class="kpi-desc">Taux de disponibilité</div>
          </div>
        </div>
        <div class="kpi-card">
          <div class="kpi-icon preventive">🔧</div>
          <div class="kpi-content">
            <div class="kpi-value">{{ kpis.kpis.preventive_ratio }} <span class="kpi-unit">%</span></div>
            <div class="kpi-label">Préventif</div>
            <div class="kpi-desc">Ratio maintenance préventive</div>
          </div>
        </div>
        <div class="kpi-card wide">
          <div class="kpi-icon cost">💰</div>
          <div class="kpi-content">
            <div class="kpi-value">{{ formatCost(kpis.costs.total) }}</div>
            <div class="kpi-label">Coût total</div>
            <div class="kpi-breakdown">
              <span>MO: {{ formatCost(kpis.costs.labor) }}</span>
              <span>Pièces: {{ formatCost(kpis.costs.parts) }}</span>
            </div>
          </div>
        </div>
        <div class="kpi-card">
          <div class="kpi-icon wo">📋</div>
          <div class="kpi-content">
            <div class="kpi-value">{{ kpis.work_orders.pending }}</div>
            <div class="kpi-label">OT en cours</div>
            <div class="kpi-breakdown">
              <span>Créés: {{ kpis.work_orders.created }}</span>
              <span>Terminés: {{ kpis.work_orders.completed }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Tabs -->
      <div class="tabs">
        <button :class="{ active: activeTab === 'overview' }" @click="activeTab = 'overview'">Vue d'ensemble</button>
        <button :class="{ active: activeTab === 'equipments' }" @click="activeTab = 'equipments'">Équipements</button>
        <button :class="{ active: activeTab === 'costs' }" @click="activeTab = 'costs'">Coûts</button>
        <button :class="{ active: activeTab === 'team' }" @click="activeTab = 'team'">Équipe</button>
        <button :class="{ active: activeTab === 'stock' }" @click="activeTab = 'stock'">Stock</button>
      </div>

      <!-- Tab: Overview -->
      <div v-if="activeTab === 'overview'" class="tab-content">
        <div class="charts-grid">
          <div class="chart-card">
            <h3>📈 Évolution des interventions</h3>
            <div class="chart-container">
              <canvas id="trendChart"></canvas>
            </div>
            <p v-if="woTrend.length === 0" class="no-data">Aucune donnée disponible</p>
          </div>
          <div class="chart-card">
            <h3>🔧 Répartition par type</h3>
            <div class="chart-container small">
              <canvas id="typeChart"></canvas>
            </div>
            <p v-if="woByType.every(d => d.count === 0)" class="no-data">Aucune donnée disponible</p>
          </div>
          <div class="chart-card">
            <h3>📊 Répartition par statut</h3>
            <div class="chart-container small">
              <canvas id="statusChart"></canvas>
            </div>
            <p v-if="woByStatus.length === 0" class="no-data">Aucune donnée disponible</p>
          </div>
        </div>
      </div>

      <!-- Tab: Equipments -->
      <div v-if="activeTab === 'equipments'" class="tab-content">
        <div class="tables-grid">
          <div class="table-card">
            <h3>🔴 Top équipements en panne</h3>
            <table class="data-table">
              <thead>
                <tr><th>Équipement</th><th>Code</th><th class="num">Pannes</th></tr>
              </thead>
              <tbody>
                <tr v-for="eq in topFailures" :key="eq.id">
                  <td>{{ eq.name }}</td>
                  <td><code>{{ eq.code }}</code></td>
                  <td class="num"><span class="badge danger">{{ eq.failure_count }}</span></td>
                </tr>
                <tr v-if="!topFailures.length"><td colspan="3" class="empty">Aucune donnée</td></tr>
              </tbody>
            </table>
          </div>
          <div class="table-card">
            <h3>💰 Coûts par équipement</h3>
            <table class="data-table">
              <thead>
                <tr><th>Équipement</th><th class="num">OT</th><th class="num">Coût</th></tr>
              </thead>
              <tbody>
                <tr v-for="eq in costsByEquipment" :key="eq.id">
                  <td>{{ eq.name }}</td>
                  <td class="num">{{ eq.wo_count }}</td>
                  <td class="num cost">{{ formatCost(eq.total_cost) }}</td>
                </tr>
                <tr v-if="!costsByEquipment.length"><td colspan="3" class="empty">Aucune donnée</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Tab: Costs -->
      <div v-if="activeTab === 'costs'" class="tab-content">
        <div class="chart-card full">
          <h3>💰 Évolution des coûts</h3>
          <div class="chart-container">
            <canvas id="costChart"></canvas>
          </div>
          <p v-if="costsTrend.every(d => d.total === 0)" class="no-data">Aucune donnée disponible</p>
        </div>
      </div>

      <!-- Tab: Team -->
      <div v-if="activeTab === 'team'" class="tab-content">
        <div class="table-card full">
          <h3>👥 Performance des techniciens</h3>
          <table class="data-table">
            <thead>
              <tr>
                <th>Technicien</th>
                <th class="num">OT terminés</th>
                <th class="num">Temps moyen</th>
                <th class="num">Coût total</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="tech in technicianPerformance" :key="tech.id">
                <td><strong>{{ tech.name }}</strong></td>
                <td class="num"><span class="badge success">{{ tech.completed_count }}</span></td>
                <td class="num">{{ tech.avg_duration_hours }}h</td>
                <td class="num cost">{{ formatCost(tech.total_cost) }}</td>
              </tr>
              <tr v-if="!technicianPerformance.length"><td colspan="4" class="empty">Aucune donnée</td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Tab: Stock -->
      <div v-if="activeTab === 'stock'" class="tab-content">
        <div class="tables-grid">
          <div class="table-card">
            <h3>⚠️ Stock critique</h3>
            <table class="data-table">
              <thead>
                <tr><th>Pièce</th><th class="num">Stock</th><th class="num">Min</th></tr>
              </thead>
              <tbody>
                <tr v-for="part in criticalStock" :key="part.id" class="critical-row">
                  <td>{{ part.name }}</td>
                  <td class="num"><span class="badge danger">{{ part.quantity_in_stock }} {{ part.unit }}</span></td>
                  <td class="num">{{ part.minimum_stock }} {{ part.unit }}</td>
                </tr>
                <tr v-if="!criticalStock.length"><td colspan="3" class="empty success">✅ Aucun stock critique</td></tr>
              </tbody>
            </table>
          </div>
          <div class="table-card">
            <h3>📦 Consommation de pièces</h3>
            <table class="data-table">
              <thead>
                <tr><th>Pièce</th><th class="num">Qté utilisée</th><th class="num">Coût</th></tr>
              </thead>
              <tbody>
                <tr v-for="part in partsConsumption" :key="part.id">
                  <td>{{ part.name }}</td>
                  <td class="num">{{ part.total_used }} {{ part.unit }}</td>
                  <td class="num cost">{{ formatCost(part.total_cost) }}</td>
                </tr>
                <tr v-if="!partsConsumption.length"><td colspan="3" class="empty">Aucune donnée</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.reports-page { padding: 30px; }

.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; }
.page-header h1 { font-size: 28px; color: #2c3e50; margin-bottom: 5px; }
.subtitle { color: #7f8c8d; font-size: 14px; }

.header-actions { display: flex; gap: 15px; align-items: center; flex-wrap: wrap; }
.period-selector { display: flex; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
.period-selector button { padding: 10px 16px; border: none; background: white; cursor: pointer; font-size: 13px; transition: all 0.2s; }
.period-selector button:hover { background: #f8f9fa; }
.period-selector button.active { background: #3498db; color: white; }

/* KPIs */
.kpi-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 15px; margin-bottom: 25px; }
.kpi-card { background: white; border-radius: 12px; padding: 20px; display: flex; gap: 15px; align-items: center; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
.kpi-card.wide { grid-column: span 2; }
.kpi-icon { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
.kpi-icon.mttr { background: #fff3cd; }
.kpi-icon.mtbf { background: #d4edda; }
.kpi-icon.availability { background: #d1ecf1; }
.kpi-icon.preventive { background: #e2e3e5; }
.kpi-icon.cost { background: #f8d7da; }
.kpi-icon.wo { background: #cce5ff; }
.kpi-content { flex: 1; }
.kpi-value { font-size: 28px; font-weight: bold; color: #2c3e50; line-height: 1; }
.kpi-unit { font-size: 14px; color: #7f8c8d; font-weight: normal; }
.kpi-label { font-size: 14px; font-weight: 600; color: #2c3e50; margin-top: 5px; }
.kpi-desc { font-size: 11px; color: #7f8c8d; }
.kpi-breakdown { display: flex; gap: 15px; font-size: 12px; color: #7f8c8d; margin-top: 5px; }

/* Tabs */
.tabs { display: flex; gap: 5px; margin-bottom: 20px; background: white; padding: 5px; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
.tabs button { padding: 12px 20px; border: none; background: transparent; border-radius: 8px; cursor: pointer; font-weight: 500; color: #7f8c8d; transition: all 0.2s; }
.tabs button:hover { background: #f8f9fa; color: #2c3e50; }
.tabs button.active { background: #3498db; color: white; }

/* Charts */
.charts-grid { display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 20px; }
.chart-card { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
.chart-card.full { grid-column: 1 / -1; }
.chart-card h3 { font-size: 14px; color: #2c3e50; margin: 0 0 15px 0; }
.chart-container { height: 300px; position: relative; }
.chart-container.small { height: 250px; }
.no-data { text-align: center; color: #7f8c8d; font-size: 13px; padding: 20px; }

/* Tables */
.tables-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
.table-card { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
.table-card.full { grid-column: 1 / -1; }
.table-card h3 { font-size: 14px; color: #2c3e50; margin: 0 0 15px 0; }
.data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.data-table th, .data-table td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
.data-table th { color: #7f8c8d; font-weight: 500; font-size: 12px; }
.data-table .num { text-align: right; }
.data-table .cost { font-weight: 600; color: #2c3e50; }
.data-table .empty { text-align: center; color: #7f8c8d; padding: 30px; }
.data-table .empty.success { color: #27ae60; }
.critical-row { background: #fff5f5; }
.data-table code { background: #f8f9fa; padding: 2px 6px; border-radius: 4px; font-size: 11px; }

.badge { padding: 4px 10px; border-radius: 10px; font-size: 12px; font-weight: 600; }
.badge.danger { background: #f8d7da; color: #721c24; }
.badge.success { background: #d4edda; color: #155724; }

/* Loading */
.loading-state { text-align: center; padding: 60px; background: white; border-radius: 12px; }
.spinner { width: 40px; height: 40px; border: 3px solid #eee; border-top-color: #3498db; border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto 15px; }
@keyframes spin { to { transform: rotate(360deg); } }

/* Buttons */
.btn { padding: 10px 20px; border: none; border-radius: 8px; font-weight: 500; cursor: pointer; }
.btn-primary { background: #3498db; color: white; }
.btn:hover { opacity: 0.9; }

@media (max-width: 1200px) {
  .kpi-grid { grid-template-columns: repeat(3, 1fr); }
  .kpi-card.wide { grid-column: span 1; }
  .charts-grid { grid-template-columns: 1fr; }
  .tables-grid { grid-template-columns: 1fr; }
}

@media (max-width: 768px) {
  .kpi-grid { grid-template-columns: repeat(2, 1fr); }
  .tabs { flex-wrap: wrap; }
}
</style>
