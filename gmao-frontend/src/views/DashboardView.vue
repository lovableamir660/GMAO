<script setup>
import { ref, onMounted, onUnmounted, computed, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import { Chart, registerables } from 'chart.js'
import ChartDataLabels from 'chartjs-plugin-datalabels'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'

Chart.register(...registerables, ChartDataLabels)

const router = useRouter()
const authStore = useAuthStore()

const loading = ref(true)
const chartsReady = ref(false)
const dashboard = ref(null)
const currentTime = ref(new Date())
const refreshInterval = ref(null)
const timeInterval = ref(null)

let trendChart = null
let statusChart = null
let equipmentChart = null
let truckChart = null
let diChart = null
let preventiveChart = null

const greeting = computed(() => {
  const hour = currentTime.value.getHours()
  if (hour < 12) return 'Bonjour'
  if (hour < 18) return 'Bon après-midi'
  return 'Bonsoir'
})

const formattedTime = computed(() =>
  currentTime.value.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
)

const formattedDate = computed(() =>
  currentTime.value.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
)

async function fetchDashboard() {
  try {
    const response = await api.get('/dashboard')
    dashboard.value = response.data
    loading.value = false
    await nextTick()
    requestAnimationFrame(() => {
      renderCharts()
      chartsReady.value = true
    })
  } catch (err) {
    console.error('Erreur dashboard:', err)
    loading.value = false
  }
}

function renderCharts() {
  renderTrendChart()
  renderStatusChart()
  renderEquipmentChart()
  renderTruckChart()
  renderDIChart()
  renderPreventiveChart()
}

function renderTrendChart() {
  const ctx = document.getElementById('trendChart')
  if (!ctx || !dashboard.value?.monthly_trend?.length) return
  if (trendChart) trendChart.destroy()
  trendChart = new Chart(ctx, {
    type: 'line',
    data: {
      labels: dashboard.value.monthly_trend.map(d => d.month),
      datasets: [
        {
          label: 'Créés', data: dashboard.value.monthly_trend.map(d => d.created),
          borderColor: '#3498db', backgroundColor: 'rgba(52,152,219,0.1)',
          fill: true, tension: 0.4, pointRadius: 4, pointHoverRadius: 6,
        },
        {
          label: 'Terminés', data: dashboard.value.monthly_trend.map(d => d.completed),
          borderColor: '#27ae60', backgroundColor: 'rgba(39,174,96,0.1)',
          fill: true, tension: 0.4, pointRadius: 4, pointHoverRadius: 6,
        }
      ]
    },
    options: {
      responsive: true, maintainAspectRatio: false, animation: { duration: 500 },
      plugins: { legend: { position: 'bottom' }, datalabels: { display: false } },
      scales: { y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } }, x: { grid: { display: false } } },
      interaction: { intersect: false, mode: 'index' }
    }
  })
}

function renderStatusChart() {
  const ctx = document.getElementById('statusChart')
  if (!ctx || !dashboard.value?.work_orders?.length) return
  if (statusChart) statusChart.destroy()
  const data = dashboard.value.work_orders.filter(d => d.count > 0)
  if (!data.length) return
  statusChart = new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: data.map(d => d.label),
      datasets: [{ data: data.map(d => d.count), backgroundColor: data.map(d => d.color), borderWidth: 0, hoverOffset: 10 }]
    },
    options: {
      responsive: true, maintainAspectRatio: false, animation: { duration: 500 }, cutout: '65%',
      plugins: {
        legend: { display: false },
        datalabels: { color: '#fff', font: { weight: 'bold', size: 12 }, formatter: v => v > 0 ? v : '' }
      }
    }
  })
}

function renderEquipmentChart() {
  const ctx = document.getElementById('equipmentChart')
  if (!ctx || !dashboard.value?.equipment_status?.length) return
  if (equipmentChart) equipmentChart.destroy()
  const data = dashboard.value.equipment_status
  equipmentChart = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: data.map(d => d.label),
      datasets: [{ data: data.map(d => d.count), backgroundColor: data.map(d => d.color), borderRadius: 6, barThickness: 40 }]
    },
    options: {
      responsive: true, maintainAspectRatio: false, animation: { duration: 500 }, indexAxis: 'y',
      plugins: {
        legend: { display: false },
        datalabels: { anchor: 'end', align: 'end', color: '#2c3e50', font: { weight: 'bold' } }
      },
      scales: { x: { display: false }, y: { grid: { display: false } } }
    }
  })
}

function renderTruckChart() {
  const ctx = document.getElementById('truckChart')
  if (!ctx || !dashboard.value?.trucks_stats?.by_status?.length) return
  if (truckChart) truckChart.destroy()
  const data = dashboard.value.trucks_stats.by_status
  truckChart = new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: data.map(d => d.label),
      datasets: [{ data: data.map(d => d.count), backgroundColor: data.map(d => d.color), borderWidth: 0, hoverOffset: 8 }]
    },
    options: {
      responsive: true, maintainAspectRatio: false, animation: { duration: 500 }, cutout: '60%',
      plugins: {
        legend: { display: false },
        datalabels: { color: '#fff', font: { weight: 'bold', size: 11 }, formatter: v => v > 0 ? v : '' }
      }
    }
  })
}

function renderDIChart() {
  const ctx = document.getElementById('diChart')
  if (!ctx || !dashboard.value?.intervention_stats) return
  if (diChart) diChart.destroy()
  const stats = dashboard.value.intervention_stats
  const items = [
    { label: 'Soumises', value: stats.submitted, color: '#f39c12' },
    { label: 'En revue', value: stats.under_review, color: '#3498db' },
    { label: 'Approuvées', value: stats.approved, color: '#27ae60' },
    { label: 'Converties', value: stats.converted, color: '#8e44ad' },
    { label: 'Rejetées', value: stats.rejected, color: '#e74c3c' },
  ].filter(i => i.value > 0)
  if (!items.length) return
  diChart = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: items.map(i => i.label),
      datasets: [{ data: items.map(i => i.value), backgroundColor: items.map(i => i.color), borderRadius: 6, barThickness: 30 }]
    },
    options: {
      responsive: true, maintainAspectRatio: false, animation: { duration: 500 },
      plugins: {
        legend: { display: false },
        datalabels: { anchor: 'end', align: 'top', color: '#2c3e50', font: { weight: 'bold' } }
      },
      scales: { y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } }, x: { grid: { display: false } } }
    }
  })
}

function renderPreventiveChart() {
  const ctx = document.getElementById('preventiveChart')
  if (!ctx || !dashboard.value?.preventive_stats?.total) return
  if (preventiveChart) preventiveChart.destroy()
  const s = dashboard.value.preventive_stats
  const items = [
    { label: 'À jour', value: s.on_track, color: '#27ae60' },
    { label: 'À venir', value: s.due_soon, color: '#f39c12' },
    { label: 'En retard', value: s.overdue, color: '#e74c3c' },
  ].filter(i => i.value > 0)
  if (!items.length) return
  preventiveChart = new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: items.map(i => i.label),
      datasets: [{ data: items.map(i => i.value), backgroundColor: items.map(i => i.color), borderWidth: 0 }]
    },
    options: {
      responsive: true, maintainAspectRatio: false, animation: { duration: 500 }, cutout: '60%',
      plugins: {
        legend: { display: false },
        datalabels: { color: '#fff', font: { weight: 'bold', size: 11 }, formatter: v => v > 0 ? v : '' }
      }
    }
  })
}

function getKPIColor(color) {
  const colors = {
    blue: 'linear-gradient(135deg, #3498db, #2980b9)',
    green: 'linear-gradient(135deg, #27ae60, #1e8449)',
    purple: 'linear-gradient(135deg, #9b59b6, #8e44ad)',
    orange: 'linear-gradient(135deg, #f39c12, #d68910)',
    red: 'linear-gradient(135deg, #e74c3c, #c0392b)',
    teal: 'linear-gradient(135deg, #1abc9c, #16a085)',
    indigo: 'linear-gradient(135deg, #5c6bc0, #3f51b5)',
    cyan: 'linear-gradient(135deg, #00bcd4, #0097a7)',
  }
  return colors[color] || colors.blue
}

function getPriorityClass(p) {
  return { urgent: 'priority-urgent', high: 'priority-high', medium: 'priority-medium', low: 'priority-low' }[p] || ''
}

function getPriorityLabel(p) {
  return { urgent: 'Urgente', high: 'Haute', medium: 'Moyenne', low: 'Basse' }[p] || p
}

function formatCost(v) {
  if (!v) return '0 DA'
  return Number(v).toLocaleString('fr-FR', { maximumFractionDigits: 0 }) + ' DA'
}

function navigateTo(link) { if (link) router.push(link) }

onMounted(() => {
  fetchDashboard()
  refreshInterval.value = setInterval(fetchDashboard, 120000)
  timeInterval.value = setInterval(() => { currentTime.value = new Date() }, 60000)
})

onUnmounted(() => {
  if (refreshInterval.value) clearInterval(refreshInterval.value)
  if (timeInterval.value) clearInterval(timeInterval.value)
  ;[trendChart, statusChart, equipmentChart, truckChart, diChart, preventiveChart].forEach(c => c?.destroy())
})
</script>

<template>
  <div class="dashboard">
    <!-- Header -->
    <header class="dashboard-header">
      <div class="header-greeting">
        <h1>{{ greeting }}, {{ authStore.user?.name?.split(' ')[0] }} 👋</h1>
        <p class="header-date">{{ formattedDate }}</p>
      </div>
      <div class="header-right">
        <div class="header-site" v-if="authStore.currentSite">
          <span class="site-icon">📍</span>
          <span class="site-name">{{ authStore.currentSite.name }}</span>
        </div>
        <div class="header-clock">
          <span class="clock-time">{{ formattedTime }}</span>
          <span class="clock-label">Heure locale</span>
        </div>
      </div>
    </header>

    <!-- Loading -->
    <div v-if="loading" class="loading-state">
      <div class="spinner-large"></div>
      <p>Chargement du tableau de bord...</p>
    </div>

    <template v-else-if="dashboard">
      <!-- KPIs -->
      <section class="kpi-section" v-if="dashboard.kpis?.length">
        <div v-for="kpi in dashboard.kpis" :key="kpi.key" class="kpi-card"
          :style="{ background: getKPIColor(kpi.color) }" @click="navigateTo(kpi.link)">
          <div class="kpi-icon">{{ kpi.icon }}</div>
          <div class="kpi-content">
            <div class="kpi-value">
              {{ kpi.value }}<span v-if="kpi.suffix" class="kpi-suffix">{{ kpi.suffix }}</span>
            </div>
            <div class="kpi-label">{{ kpi.label }}</div>
          </div>
          <div v-if="kpi.trend !== null && kpi.trend !== 0" class="kpi-trend"
            :class="{ positive: kpi.trend >= 0, negative: kpi.trend < 0 }">
            <span v-if="kpi.trend >= 0">↑</span><span v-else>↓</span>
            {{ Math.abs(kpi.trend) }}%
          </div>
        </div>
      </section>

      <!-- ROW 1: Trend + OT Status + Equipment -->
      <div class="dashboard-row">
        <div class="widget widget-wide" v-if="dashboard.monthly_trend?.length">
          <div class="widget-header">
            <h3>📈 Tendance des interventions</h3>
            <span class="widget-badge">6 derniers mois</span>
          </div>
          <div class="chart-container"><canvas id="trendChart"></canvas></div>
        </div>

        <div class="widget" v-if="dashboard.work_orders?.length">
          <div class="widget-header">
            <h3>📊 Ordres de travail</h3>
            <router-link to="/work-orders" class="widget-link">Voir tout →</router-link>
          </div>
          <div class="status-grid">
            <div class="chart-container-small"><canvas id="statusChart"></canvas></div>
            <div class="status-legend">
              <div v-for="item in dashboard.work_orders.filter(w => w.count > 0)" :key="item.status" class="legend-item">
                <span class="legend-color" :style="{ background: item.color }"></span>
                <span class="legend-label">{{ item.label }}</span>
                <span class="legend-value">{{ item.count }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ROW 2: Equipment + Trucks + Drivers -->
      <div class="dashboard-row three-cols">
        <div class="widget" v-if="dashboard.equipment_status?.length">
          <div class="widget-header">
            <h3>⚙️ État des équipements</h3>
            <router-link to="/equipments" class="widget-link">Voir →</router-link>
          </div>
          <div class="chart-container-small"><canvas id="equipmentChart"></canvas></div>
        </div>

        <div class="widget" v-if="dashboard.trucks_stats?.by_status?.length">
          <div class="widget-header">
            <h3>🚚 Flotte de camions</h3>
            <router-link to="/trucks" class="widget-link">Voir →</router-link>
          </div>
          <div class="truck-widget-body">
            <div class="chart-container-small"><canvas id="truckChart"></canvas></div>
            <div class="status-legend">
              <div v-for="item in dashboard.trucks_stats.by_status" :key="item.status" class="legend-item">
                <span class="legend-color" :style="{ background: item.color }"></span>
                <span class="legend-label">{{ item.icon }} {{ item.label }}</span>
                <span class="legend-value">{{ item.count }}</span>
              </div>
            </div>
            <div class="truck-alert" v-if="dashboard.trucks_stats.needing_maintenance > 0">
              ⚠️ {{ dashboard.trucks_stats.needing_maintenance }} camion(s) proche(s) d'une maintenance
            </div>
          </div>
        </div>

        <div class="widget" v-if="dashboard.drivers_stats">
          <div class="widget-header">
            <h3>👷 Chauffeurs</h3>
            <router-link to="/drivers" class="widget-link">Voir →</router-link>
          </div>
          <div class="driver-stats">
            <div class="driver-stat-item">
              <div class="driver-stat-value">{{ dashboard.drivers_stats.total }}</div>
              <div class="driver-stat-label">Total actifs</div>
            </div>
            <div class="driver-stat-item assigned">
              <div class="driver-stat-value">{{ dashboard.drivers_stats.assigned }}</div>
              <div class="driver-stat-label">Assignés</div>
            </div>
            <div class="driver-stat-item unassigned" v-if="dashboard.drivers_stats.unassigned > 0">
              <div class="driver-stat-value">{{ dashboard.drivers_stats.unassigned }}</div>
              <div class="driver-stat-label">Disponibles</div>
            </div>
          </div>

          <!-- Habilitations expirantes -->
          <div class="hab-section" v-if="dashboard.expiring_habilitations?.length">
            <h4 class="hab-title">📜 Habilitations expirant bientôt</h4>
            <div class="hab-list">
              <div v-for="hab in dashboard.expiring_habilitations" :key="`${hab.driver_id}-${hab.habilitation}`"
                class="hab-item" @click="navigateTo(`/drivers/${hab.driver_id}`)">
                <div class="hab-info">
                  <span class="hab-name">{{ hab.driver_name }}</span>
                  <span class="hab-label">{{ hab.habilitation }}</span>
                </div>
                <span class="hab-days" :class="{ critical: hab.days_remaining <= 7 }">
                  {{ hab.days_remaining }}j
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ROW 3: DI + Preventive + Stock -->
      <div class="dashboard-row three-cols">
        <!-- DI -->
        <div class="widget" v-if="dashboard.intervention_stats?.total > 0">
          <div class="widget-header">
            <h3>📋 Demandes d'intervention</h3>
            <router-link to="/intervention-requests" class="widget-link">Voir →</router-link>
          </div>
          <div class="chart-container-small"><canvas id="diChart"></canvas></div>
          <div class="pending-di-list" v-if="dashboard.pending_interventions?.length">
            <h4 class="sub-title">En attente de traitement</h4>
            <div v-for="di in dashboard.pending_interventions.slice(0, 4)" :key="di.id"
              class="di-item" @click="navigateTo(`/intervention-requests/${di.id}`)">
              <div class="di-info">
                <span class="di-code">{{ di.code }}</span>
                <span class="di-title">{{ di.title }}</span>
              </div>
              <div class="di-meta">
                <span class="priority-badge" :class="getPriorityClass(di.priority)">{{ getPriorityLabel(di.priority) }}</span>
                <span class="di-time">{{ di.created_at }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Preventive -->
        <div class="widget" v-if="dashboard.preventive_stats?.total > 0">
          <div class="widget-header">
            <h3>🔄 Maintenance préventive</h3>
            <router-link to="/preventive-maintenance" class="widget-link">Voir →</router-link>
          </div>
          <div class="preventive-body">
            <div class="chart-container-small"><canvas id="preventiveChart"></canvas></div>
            <div class="status-legend">
              <div class="legend-item">
                <span class="legend-color" style="background:#27ae60"></span>
                <span class="legend-label">À jour</span>
                <span class="legend-value">{{ dashboard.preventive_stats.on_track }}</span>
              </div>
              <div class="legend-item">
                <span class="legend-color" style="background:#f39c12"></span>
                <span class="legend-label">À venir (7j)</span>
                <span class="legend-value">{{ dashboard.preventive_stats.due_soon }}</span>
              </div>
              <div class="legend-item" v-if="dashboard.preventive_stats.overdue > 0">
                <span class="legend-color" style="background:#e74c3c"></span>
                <span class="legend-label">En retard</span>
                <span class="legend-value danger-text">{{ dashboard.preventive_stats.overdue }}</span>
              </div>
            </div>
          </div>
          <!-- Plans en retard -->
          <div class="overdue-plans" v-if="dashboard.preventive_stats.overdue_plans?.length">
            <h4 class="sub-title">⚠️ Plans en retard</h4>
            <div v-for="plan in dashboard.preventive_stats.overdue_plans" :key="plan.id"
              class="overdue-plan-item" @click="navigateTo('/preventive-maintenance')">
              <div class="plan-info">
                <span class="plan-code">{{ plan.code }}</span>
                <span class="plan-name">{{ plan.name }}</span>
                <span class="plan-asset">{{ plan.asset_type === 'truck' ? '🚚' : '⚙️' }} {{ plan.asset_name }}</span>
              </div>
              <span class="plan-overdue">-{{ plan.days_overdue }}j</span>
            </div>
          </div>
        </div>

        <!-- Stock critique -->
        <div class="widget" v-if="dashboard.critical_stock_items?.length">
          <div class="widget-header">
            <h3>📦 Stock critique</h3>
            <router-link to="/parts" class="widget-link">Voir →</router-link>
          </div>
          <div class="stock-list">
            <div v-for="part in dashboard.critical_stock_items" :key="part.id"
              class="stock-item" :class="{ empty: part.is_empty }">
              <div class="stock-info">
                <span class="stock-name">{{ part.name }}</span>
                <span class="stock-code">{{ part.code }}</span>
              </div>
              <div class="stock-qty">
                <div class="stock-bar">
                  <div class="stock-bar-fill" :style="{
                    width: Math.min((part.quantity / Math.max(part.minimum, 1)) * 100, 100) + '%',
                    background: part.is_empty ? '#e74c3c' : '#f39c12'
                  }"></div>
                </div>
                <span class="stock-numbers">
                  {{ part.quantity }} / {{ part.minimum }} {{ part.unit }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ROW 4: Coûts + Upcoming + Team + Activities -->
      <div class="dashboard-row four-cols">
        <!-- Coûts -->
        <div class="widget" v-if="dashboard.costs_summary?.total_this_month > 0 || dashboard.costs_summary?.total_last_month > 0">
          <div class="widget-header">
            <h3>💰 Coûts du mois</h3>
          </div>
          <div class="costs-body">
            <div class="cost-total">
              <span class="cost-value">{{ formatCost(dashboard.costs_summary.total_this_month) }}</span>
              <div class="cost-trend" v-if="dashboard.costs_summary.trend !== 0"
                :class="{ negative: dashboard.costs_summary.trend > 0, positive: dashboard.costs_summary.trend <= 0 }">
                {{ dashboard.costs_summary.trend > 0 ? '↑' : '↓' }} {{ Math.abs(dashboard.costs_summary.trend) }}%
                <span class="cost-trend-label">vs mois dernier</span>
              </div>
            </div>
            <div class="cost-breakdown">
              <div class="cost-item">
                <span class="cost-label">🔧 Main d'œuvre</span>
                <span>{{ formatCost(dashboard.costs_summary.labor_cost) }}</span>
              </div>
              <div class="cost-item">
                <span class="cost-label">📦 Pièces</span>
                <span>{{ formatCost(dashboard.costs_summary.parts_cost) }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Upcoming Maintenance -->
        <div class="widget" v-if="dashboard.upcoming_maintenance?.length">
          <div class="widget-header">
            <h3>📅 À venir (7 jours)</h3>
          </div>
          <div class="upcoming-list">
            <div v-for="item in dashboard.upcoming_maintenance" :key="`${item.type}-${item.id}`"
              class="upcoming-item" @click="navigateTo(item.type === 'work_order' ? `/work-orders/${item.id}` : '/preventive-maintenance')">
              <div class="upcoming-date">
                <span class="date-day">{{ item.due_date_formatted?.split('/')[0] }}</span>
                <span class="date-month">{{ ['Jan','Fév','Mar','Avr','Mai','Jun','Jul','Aoû','Sep','Oct','Nov','Déc'][parseInt(item.due_date_formatted?.split('/')[1]) - 1] }}</span>
              </div>
              <div class="upcoming-content">
                <div class="upcoming-title">{{ item.title || item.code }}</div>
                <div class="upcoming-equipment">{{ item.asset_type === 'truck' ? '🚚' : '⚙️' }} {{ item.equipment }}</div>
              </div>
              <div class="upcoming-type" :class="item.type">{{ item.type === 'preventive' ? 'MP' : 'OT' }}</div>
            </div>
          </div>
        </div>

        <!-- Top Techniciens -->
        <div class="widget" v-if="dashboard.team_performance?.length">
          <div class="widget-header">
            <h3>👥 Top techniciens</h3>
            <span class="widget-badge">Ce mois</span>
          </div>
          <div class="team-list">
            <div v-for="(tech, index) in dashboard.team_performance" :key="tech.id" class="team-item">
              <div class="team-rank">{{ index + 1 }}</div>
              <div class="team-avatar">{{ tech.initials }}</div>
              <div class="team-info">
                <div class="team-name">{{ tech.name }}</div>
                <div class="team-stats">{{ tech.completed }} OT terminés</div>
              </div>
              <div class="team-medal" v-if="index < 3">{{ ['🥇','🥈','🥉'][index] }}</div>
            </div>
          </div>
        </div>

        <!-- Activities -->
        <div class="widget" v-if="dashboard.recent_activities?.length">
          <div class="widget-header">
            <h3>🕐 Activités récentes</h3>
          </div>
          <div class="activities-list">
            <div v-for="(activity, index) in dashboard.recent_activities" :key="index"
              class="activity-item" @click="navigateTo(activity.link)">
              <span class="activity-icon">{{ activity.icon }}</span>
              <div class="activity-content">
                <div class="activity-title">{{ activity.title }}</div>
                <div class="activity-desc">{{ activity.description }}</div>
              </div>
              <span class="activity-time">{{ activity.time }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- ROW 5: Urgents + Notifications -->
      <div class="dashboard-row" v-if="dashboard.urgent_items?.length || dashboard.notifications?.length">
        <div class="widget widget-wide" v-if="dashboard.urgent_items?.length">
          <div class="widget-header">
            <h3>🚨 Éléments urgents</h3>
            <span class="widget-count danger">{{ dashboard.urgent_items.length }}</span>
          </div>
          <div class="urgent-grid">
            <div v-for="(item, index) in dashboard.urgent_items" :key="index" class="urgent-item"
              :class="item.urgency" @click="navigateTo(item.link)">
              <span class="urgent-icon">{{ item.icon }}</span>
              <div class="urgent-content">
                <div class="urgent-title">{{ item.title }}</div>
                <div class="urgent-subtitle">{{ item.subtitle }}</div>
                <div class="urgent-desc">{{ item.description }}</div>
              </div>
              <span v-if="item.priority" class="priority-badge" :class="getPriorityClass(item.priority)">{{ item.priority }}</span>
            </div>
          </div>
        </div>

        <div class="widget" v-if="dashboard.notifications?.length">
          <div class="widget-header">
            <h3>🔔 Dernières alertes</h3>
            <router-link to="/notifications" class="widget-link">Voir tout</router-link>
          </div>
          <div class="notifications-list">
            <div v-for="notif in dashboard.notifications" :key="notif.id" class="notif-item"
              :class="{ unread: !notif.is_read }" @click="navigateTo(notif.link)">
              <span class="notif-icon">{{ notif.icon }}</span>
              <div class="notif-content">
                <div class="notif-title">{{ notif.title }}</div>
                <div class="notif-message">{{ notif.message }}</div>
              </div>
              <span class="notif-time">{{ notif.time }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty state -->
      <div class="empty-dashboard"
        v-if="!dashboard.kpis?.length && !dashboard.work_orders?.length && !dashboard.equipment_status?.length && !dashboard.notifications?.length">
        <div class="empty-dashboard-content">
          <span class="empty-icon-large">🔒</span>
          <h2>Accès limité</h2>
          <p>Vous n'avez pas encore de permissions assignées.<br>Contactez votre administrateur.</p>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.dashboard { padding: 30px; background: linear-gradient(135deg, #f5f7fa, #e4e8eb); min-height: 100vh; }

/* Header */
.dashboard-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
.header-greeting h1 { font-size: 28px; color: #2c3e50; margin: 0 0 5px; font-weight: 700; }
.header-date { color: #7f8c8d; font-size: 14px; margin: 0; text-transform: capitalize; }
.header-right { display: flex; gap: 15px; align-items: center; }
.header-site { background: white; padding: 12px 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 8px; }
.site-icon { font-size: 18px; }
.site-name { font-weight: 600; color: #2c3e50; }
.header-clock { text-align: right; background: white; padding: 12px 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
.clock-time { display: block; font-size: 28px; font-weight: 700; color: #2c3e50; font-variant-numeric: tabular-nums; }
.clock-label { font-size: 10px; color: #95a5a6; text-transform: uppercase; }

/* KPIs */
.kpi-section { display: flex; gap: 15px; margin-bottom: 25px; overflow-x: auto; padding-bottom: 5px; }
.kpi-card { min-width: 170px; flex: 1; padding: 18px; border-radius: 14px; color: white; display: flex; align-items: center; gap: 12px; box-shadow: 0 8px 25px rgba(0,0,0,0.15); cursor: pointer; transition: transform 0.3s; position: relative; overflow: hidden; }
.kpi-card::before { content:''; position:absolute; top:-50%; right:-50%; width:100%; height:100%; background:radial-gradient(circle,rgba(255,255,255,0.15) 0%,transparent 60%); pointer-events:none; }
.kpi-card:hover { transform: translateY(-4px); }
.kpi-icon { font-size: 30px; opacity: 0.9; }
.kpi-content { flex: 1; }
.kpi-value { font-size: 26px; font-weight: 800; line-height: 1; }
.kpi-suffix { font-size: 14px; font-weight: 400; opacity: 0.8; }
.kpi-label { font-size: 11px; opacity: 0.9; margin-top: 4px; text-transform: uppercase; letter-spacing: 0.5px; }
.kpi-trend { font-size: 11px; font-weight: 600; padding: 3px 8px; border-radius: 20px; background: rgba(255,255,255,0.2); }
.kpi-trend.positive { color: #d4edda; }
.kpi-trend.negative { color: #f8d7da; }

/* Dashboard Rows */
.dashboard-row { display: grid; grid-template-columns: 1.5fr 1fr; gap: 20px; margin-bottom: 20px; }
.dashboard-row.three-cols { grid-template-columns: repeat(3, 1fr); }
.dashboard-row.four-cols { grid-template-columns: repeat(4, 1fr); }

/* Widget */
.widget { background: white; border-radius: 14px; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
.widget-wide { grid-column: span 1; }
.widget-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; }
.widget-header h3 { font-size: 14px; color: #2c3e50; margin: 0; font-weight: 600; }
.widget-badge { font-size: 10px; padding: 4px 10px; background: #ecf0f1; border-radius: 10px; color: #7f8c8d; }
.widget-count { font-size: 12px; font-weight: 600; padding: 4px 12px; border-radius: 10px; }
.widget-count.danger { background: #fee; color: #e74c3c; }
.widget-link { font-size: 12px; color: #3498db; text-decoration: none; }
.widget-link:hover { text-decoration: underline; }

/* Charts */
.chart-container { height: 240px; position: relative; }
.chart-container-small { height: 160px; position: relative; }

.status-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; align-items: center; }
.status-legend { display: flex; flex-direction: column; gap: 6px; }
.legend-item { display: flex; align-items: center; gap: 8px; font-size: 12px; }
.legend-color { width: 10px; height: 10px; border-radius: 3px; flex-shrink: 0; }
.legend-label { flex: 1; color: #7f8c8d; }
.legend-value { font-weight: 600; color: #2c3e50; }
.danger-text { color: #e74c3c !important; }

/* Trucks */
.truck-widget-body { display: flex; flex-direction: column; gap: 12px; }
.truck-alert { background: #fff3cd; color: #856404; padding: 8px 12px; border-radius: 8px; font-size: 12px; font-weight: 500; }

/* Drivers */
.driver-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 15px; }
.driver-stat-item { text-align: center; padding: 15px 10px; background: #f8f9fa; border-radius: 10px; }
.driver-stat-item.assigned { background: #e8f5e9; }
.driver-stat-item.unassigned { background: #fff3e0; }
.driver-stat-value { font-size: 24px; font-weight: 700; color: #2c3e50; }
.driver-stat-label { font-size: 11px; color: #7f8c8d; margin-top: 4px; }

.hab-section { margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee; }
.hab-title { font-size: 12px; color: #7f8c8d; margin: 0 0 10px; }
.hab-list { display: flex; flex-direction: column; gap: 6px; }
.hab-item { display: flex; justify-content: space-between; align-items: center; padding: 8px 10px; background: #fff8e1; border-radius: 6px; cursor: pointer; font-size: 12px; }
.hab-item:hover { background: #fff3cd; }
.hab-info { display: flex; flex-direction: column; }
.hab-name { font-weight: 600; color: #2c3e50; }
.hab-label { font-size: 11px; color: #7f8c8d; }
.hab-days { font-weight: 700; color: #f39c12; font-size: 13px; }
.hab-days.critical { color: #e74c3c; }

/* DI */
.pending-di-list { margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee; }
.sub-title { font-size: 12px; color: #7f8c8d; margin: 0 0 10px; }
.di-item { display: flex; justify-content: space-between; align-items: center; padding: 10px; background: #f8f9fa; border-radius: 8px; margin-bottom: 6px; cursor: pointer; }
.di-item:hover { background: #ecf0f1; }
.di-info { display: flex; flex-direction: column; }
.di-code { font-size: 11px; color: #7f8c8d; font-weight: 600; }
.di-title { font-size: 13px; color: #2c3e50; font-weight: 500; }
.di-meta { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; }
.di-time { font-size: 10px; color: #95a5a6; }

/* Preventive */
.preventive-body { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; align-items: center; }
.overdue-plans { margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee; }
.overdue-plan-item { display: flex; justify-content: space-between; align-items: center; padding: 8px 10px; background: #fff5f5; border-left: 3px solid #e74c3c; border-radius: 6px; margin-bottom: 6px; cursor: pointer; font-size: 12px; }
.overdue-plan-item:hover { background: #ffe8e8; }
.plan-info { display: flex; flex-direction: column; }
.plan-code { font-size: 10px; color: #7f8c8d; }
.plan-name { font-weight: 600; color: #2c3e50; }
.plan-asset { font-size: 11px; color: #7f8c8d; }
.plan-overdue { font-weight: 700; color: #e74c3c; font-size: 14px; }

/* Stock */
.stock-list { display: flex; flex-direction: column; gap: 10px; }
.stock-item { display: flex; justify-content: space-between; align-items: center; padding: 10px; background: #f8f9fa; border-radius: 8px; }
.stock-item.empty { background: #fff5f5; border-left: 3px solid #e74c3c; }
.stock-info { display: flex; flex-direction: column; }
.stock-name { font-size: 13px; font-weight: 600; color: #2c3e50; }
.stock-code { font-size: 11px; color: #7f8c8d; }
.stock-qty { text-align: right; min-width: 120px; }
.stock-bar { width: 100%; height: 6px; background: #eee; border-radius: 3px; margin-bottom: 4px; }
.stock-bar-fill { height: 100%; border-radius: 3px; transition: width 0.5s; }
.stock-numbers { font-size: 11px; color: #7f8c8d; }

/* Costs */
.costs-body { }
.cost-total { margin-bottom: 15px; }
.cost-value { font-size: 28px; font-weight: 700; color: #2c3e50; }
.cost-trend { display: inline-block; font-size: 12px; font-weight: 600; padding: 3px 10px; border-radius: 10px; margin-left: 10px; }
.cost-trend.positive { background: #d4edda; color: #155724; }
.cost-trend.negative { background: #f8d7da; color: #721c24; }
.cost-trend-label { font-weight: 400; font-size: 10px; }
.cost-breakdown { display: flex; flex-direction: column; gap: 8px; }
.cost-item { display: flex; justify-content: space-between; padding: 10px; background: #f8f9fa; border-radius: 8px; font-size: 13px; }
.cost-label { color: #7f8c8d; }

/* Urgent */
.urgent-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 10px; }
.urgent-item { display: flex; align-items: center; gap: 12px; padding: 12px; border-radius: 10px; cursor: pointer; transition: all 0.2s; }
.urgent-item.high { background: #fee; border-left: 3px solid #e74c3c; }
.urgent-item.medium { background: #fff8e6; border-left: 3px solid #f39c12; }
.urgent-item:hover { transform: translateX(5px); }
.urgent-icon { font-size: 22px; }
.urgent-content { flex: 1; min-width: 0; }
.urgent-title { font-weight: 600; color: #2c3e50; font-size: 13px; }
.urgent-subtitle { font-size: 12px; color: #7f8c8d; }
.urgent-desc { font-size: 11px; color: #95a5a6; }

/* Badges */
.priority-badge { font-size: 10px; padding: 3px 8px; border-radius: 10px; text-transform: uppercase; font-weight: 600; }
.priority-urgent { background: #e74c3c; color: white; }
.priority-high { background: #f39c12; color: white; }
.priority-medium { background: #3498db; color: white; }
.priority-low { background: #95a5a6; color: white; }

/* Team */
.team-list { display: flex; flex-direction: column; gap: 8px; }
.team-item { display: flex; align-items: center; gap: 10px; padding: 10px; background: #f8f9fa; border-radius: 10px; }
.team-rank { width: 22px; height: 22px; background: #ecf0f1; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 600; color: #7f8c8d; }
.team-avatar { width: 34px; height: 34px; background: linear-gradient(135deg, #3498db, #2980b9); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 600; color: white; }
.team-info { flex: 1; }
.team-name { font-weight: 600; font-size: 13px; color: #2c3e50; }
.team-stats { font-size: 11px; color: #7f8c8d; }
.team-medal { font-size: 18px; }

/* Upcoming */
.upcoming-list { display: flex; flex-direction: column; gap: 8px; }
.upcoming-item { display: flex; align-items: center; gap: 12px; padding: 10px; background: #f8f9fa; border-radius: 10px; cursor: pointer; transition: all 0.2s; }
.upcoming-item:hover { background: #ecf0f1; transform: translateX(3px); }
.upcoming-date { width: 46px; text-align: center; background: white; padding: 6px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
.date-day { display: block; font-size: 18px; font-weight: 700; color: #2c3e50; line-height: 1; }
.date-month { display: block; font-size: 9px; color: #7f8c8d; text-transform: uppercase; }
.upcoming-content { flex: 1; min-width: 0; }
.upcoming-title { font-weight: 600; font-size: 12px; color: #2c3e50; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.upcoming-equipment { font-size: 11px; color: #7f8c8d; }
.upcoming-type { font-size: 9px; padding: 3px 7px; border-radius: 10px; font-weight: 600; }
.upcoming-type.preventive { background: #d4edda; color: #155724; }
.upcoming-type.work_order { background: #cce5ff; color: #004085; }

/* Activities */
.activities-list { display: flex; flex-direction: column; gap: 6px; }
.activity-item { display: flex; align-items: center; gap: 10px; padding: 8px; border-radius: 8px; cursor: pointer; transition: background 0.2s; }
.activity-item:hover { background: #f8f9fa; }
.activity-icon { font-size: 18px; }
.activity-content { flex: 1; min-width: 0; }
.activity-title { font-weight: 600; font-size: 12px; color: #2c3e50; }
.activity-desc { font-size: 11px; color: #7f8c8d; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.activity-time { font-size: 10px; color: #95a5a6; white-space: nowrap; }

/* Notifications */
.notifications-list { display: flex; flex-direction: column; gap: 6px; }
.notif-item { display: flex; align-items: flex-start; gap: 10px; padding: 10px; border-radius: 8px; cursor: pointer; }
.notif-item:hover { background: #f8f9fa; }
.notif-item.unread { background: #f0f7ff; }
.notif-icon { font-size: 16px; }
.notif-content { flex: 1; min-width: 0; }
.notif-title { font-weight: 600; font-size: 12px; color: #2c3e50; }
.notif-message { font-size: 11px; color: #7f8c8d; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.notif-time { font-size: 10px; color: #95a5a6; white-space: nowrap; }

/* Empty */
.empty-dashboard { display: flex; justify-content: center; align-items: center; min-height: 300px; }
.empty-dashboard-content { text-align: center; background: white; padding: 50px 60px; border-radius: 16px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
.empty-icon-large { font-size: 60px; display: block; margin-bottom: 15px; }
.empty-dashboard-content h2 { font-size: 22px; color: #2c3e50; margin: 0 0 10px; }
.empty-dashboard-content p { color: #7f8c8d; font-size: 14px; line-height: 1.6; margin: 0; }

/* Loading */
.loading-state { text-align: center; padding: 100px; }
.spinner-large { width: 60px; height: 60px; border: 4px solid #ecf0f1; border-top-color: #3498db; border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto 20px; }
@keyframes spin { to { transform: rotate(360deg); } }

/* Responsive */
@media (max-width: 1400px) {
  .kpi-section { flex-wrap: wrap; }
  .kpi-card { min-width: 140px; }
  .dashboard-row { grid-template-columns: 1fr 1fr; }
  .dashboard-row.three-cols { grid-template-columns: 1fr 1fr; }
  .dashboard-row.four-cols { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 1000px) {
  .dashboard-row, .dashboard-row.three-cols, .dashboard-row.four-cols { grid-template-columns: 1fr; }
  .status-grid, .preventive-body { grid-template-columns: 1fr; }
  .driver-stats { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 600px) {
  .dashboard { padding: 15px; }
  .dashboard-header { flex-direction: column; gap: 15px; text-align: center; }
  .header-right { flex-direction: column; width: 100%; }
  .kpi-card { min-width: 100%; }
  .driver-stats { grid-template-columns: 1fr; }
}
</style>
