<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import api from '@/services/api'

// ─── State ───
const allSettings = ref({})
const loading = ref(false)
const saving = ref(false)
const error = ref(null)
const activeTab = ref('general')
const editValues = ref({})
const jsonEntries = ref({})
const originalValues = ref({})
const showSuccess = ref(false)
const successMessage = ref('')
const showResetModal = ref(false)

// ─── Onglets ───
const tabs = [
  { key: 'general', label: 'Général', icon: '🏢' },
  { key: 'equipment', label: 'Équipements', icon: '🔧' },
  { key: 'work_order', label: 'Ordres de travail', icon: '📋' },
  { key: 'intervention_request', label: 'Demandes intervention', icon: '🚨' },
  { key: 'preventive_maintenance', label: 'Maintenance préventive', icon: '🛡️' },
  { key: 'truck', label: 'Camions', icon: '🚛' },
  { key: 'driver', label: 'Chauffeurs', icon: '👤' },
  { key: 'assignment', label: 'Attributions', icon: '🔗' },
  { key: 'habilitation', label: 'Habilitations', icon: '📜' },
  { key: 'part', label: 'Pièces', icon: '⚙️' },
  { key: 'notification', label: 'Notifications', icon: '🔔' },
]

const typeLabels = {
  string: 'Texte',
  integer: 'Nombre',
  boolean: 'Oui/Non',
  json: 'Liste clé-valeur',
  list: 'Liste',
}

// ─── Computed ───
const currentTab = computed(() => tabs.find(t => t.key === activeTab.value))

const currentGroupSettings = computed(() => {
  return allSettings.value[activeTab.value] || []
})

const hasChanges = computed(() => {
  for (const setting of currentGroupSettings.value) {
    const current = editValues.value[setting.id]
    const original = originalValues.value[setting.id]
    if (JSON.stringify(current) !== JSON.stringify(original)) return true
  }
  return false
})

// ─── API ───
async function fetchSettings() {
  loading.value = true
  error.value = null
  try {
    const { data } = await api.get('/settings')
    allSettings.value = data
  } catch (e) {
    error.value = e.response?.data?.message || 'Erreur de chargement des paramètres'
  } finally {
    loading.value = false
  }
}

async function saveCurrentGroup() {
  saving.value = true
  error.value = null
  try {
    const toSave = currentGroupSettings.value.map(s => ({
      id: s.id,
      value: editValues.value[s.id],
      type: s.type,
    }))

    const payload = toSave.map(s => ({
      id: s.id,
      value: (s.type === 'json' || s.type === 'list')
        ? (typeof s.value === 'string' ? JSON.parse(s.value) : s.value)
        : s.value,
    }))

    await api.put('/settings/bulk', { settings: payload })
    await fetchSettings()
    initEditValues()
    successMessage.value = 'Paramètres sauvegardés avec succès'
    showSuccess.value = true
    setTimeout(() => (showSuccess.value = false), 3000)
  } catch (e) {
    error.value = e.response?.data?.message || 'Erreur lors de la sauvegarde'
  } finally {
    saving.value = false
  }
}

async function doResetGroup() {
  saving.value = true
  try {
    await api.post(`/settings/reset/${activeTab.value}`)
    await fetchSettings()
    initEditValues()
    showResetModal.value = false
    successMessage.value = 'Groupe réinitialisé avec succès'
    showSuccess.value = true
    setTimeout(() => (showSuccess.value = false), 3000)
  } catch (e) {
    error.value = e.response?.data?.message || 'Erreur de réinitialisation'
  } finally {
    saving.value = false
  }
}

// ─── Edit values ───
function initEditValues() {
  for (const setting of currentGroupSettings.value) {
    let val = setting.value
    if ((setting.type === 'json' || setting.type === 'list') && typeof val === 'string') {
      try { val = JSON.parse(val) } catch { val = {} }
    }
    if (setting.type === 'boolean') {
      val = val === true || val === '1' || val === 'true'
    }
    if (setting.type === 'integer' && typeof val === 'string') {
      val = parseInt(val) || 0
    }

    editValues.value[setting.id] = val
    originalValues.value[setting.id] = JSON.parse(JSON.stringify(val))

    // Init JSON entries
    if (setting.type === 'json' || setting.type === 'list') {
      if (val && typeof val === 'object' && !Array.isArray(val)) {
        jsonEntries.value[setting.id] = Object.entries(val).map(([key, label]) => ({ key, label }))
      } else if (Array.isArray(val)) {
        jsonEntries.value[setting.id] = val.map(v => ({ key: v, label: v }))
      } else {
        jsonEntries.value[setting.id] = []
      }
    }
  }
}

function cancelChanges() {
  initEditValues()
}

// ─── JSON entries management ───
function getJsonEntries(settingId) {
  return jsonEntries.value[settingId] || []
}

function syncJsonEntries(settingId) {
  const entries = jsonEntries.value[settingId] || []
  const obj = {}
  entries.forEach(e => {
    if (e.key) obj[e.key] = e.label || e.key
  })
  editValues.value[settingId] = obj
}

function addJsonEntry(settingId) {
  if (!jsonEntries.value[settingId]) jsonEntries.value[settingId] = []
  jsonEntries.value[settingId].push({ key: '', label: '' })
}

function removeJsonEntry(settingId, idx) {
  jsonEntries.value[settingId].splice(idx, 1)
  syncJsonEntries(settingId)
}

function getTypeClass(type) {
  const map = {
    string: 'type-string',
    integer: 'type-integer',
    boolean: 'type-boolean',
    json: 'type-json',
    list: 'type-json',
  }
  return map[type] || ''
}

// ─── Watchers ───
watch(activeTab, () => initEditValues())
watch(currentGroupSettings, () => initEditValues(), { deep: true })

// ─── Init ───
onMounted(async () => {
  await fetchSettings()
  initEditValues()
})
</script>

<template>
  <div class="settings-page">
    <!-- Header -->
    <header class="page-header">
      <div>
        <h1>⚙️ Paramètres</h1>
        <p class="subtitle">Configuration générale et variables de l'application</p>
      </div>
    </header>

    <!-- Contenu principal -->
    <div class="settings-container">
      <!-- Onglets -->
      <div class="tabs-bar">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          @click="activeTab = tab.key"
          class="tab-btn"
          :class="{ active: activeTab === tab.key }"
        >
          <span class="tab-icon">{{ tab.icon }}</span>
          <span class="tab-label">{{ tab.label }}</span>
        </button>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="loading-state">
        <div class="spinner"></div>
        <p>Chargement des paramètres...</p>
      </div>

      <!-- Erreur -->
      <div v-else-if="error" class="error-state">
        <span class="error-icon">⚠️</span>
        <p>{{ error }}</p>
        <button class="btn btn-primary btn-sm" @click="fetchSettings">Réessayer</button>
      </div>

      <!-- Contenu de l'onglet actif -->
      <div v-else-if="currentGroupSettings.length > 0" class="settings-content">
        <!-- Barre d'actions -->
        <div class="content-header">
          <h2>{{ currentTab?.icon }} {{ currentTab?.label }}</h2>
          <div class="content-actions">
            <button class="btn btn-warning btn-sm" @click="showResetModal = true">
              🔄 Réinitialiser
            </button>
            <button
              class="btn btn-primary btn-sm"
              @click="saveCurrentGroup"
              :disabled="saving || !hasChanges"
              :class="{ disabled: !hasChanges }"
            >
              {{ saving ? '⏳ Sauvegarde...' : '💾 Enregistrer' }}
            </button>
          </div>
        </div>

        <!-- Liste des paramètres -->
        <div class="settings-list">
          <div
            v-for="setting in currentGroupSettings"
            :key="setting.id"
            class="setting-card"
          >
            <!-- Header du paramètre -->
            <div class="setting-header">
              <div class="setting-info">
                <span class="setting-label">{{ setting.label }}</span>
                <span v-if="setting.description" class="setting-desc">{{ setting.description }}</span>
              </div>
              <div class="setting-badges">
                <span class="type-badge" :class="getTypeClass(setting.type)">
                  {{ typeLabels[setting.type] || setting.type }}
                </span>
                <span v-if="setting.is_system" class="system-badge">🔒 Système</span>
              </div>
            </div>

            <!-- Champ : String -->
            <div v-if="setting.type === 'string'" class="setting-field">
              <input
                type="text"
                v-model="editValues[setting.id]"
                class="field-input"
              />
            </div>

            <!-- Champ : Integer -->
            <div v-else-if="setting.type === 'integer'" class="setting-field">
              <input
                type="number"
                v-model.number="editValues[setting.id]"
                class="field-input field-number"
              />
            </div>

            <!-- Champ : Boolean -->
            <div v-else-if="setting.type === 'boolean'" class="setting-field">
              <div class="toggle-row">
                <button
                  class="toggle-btn"
                  :class="{ active: editValues[setting.id] }"
                  @click="editValues[setting.id] = !editValues[setting.id]"
                >
                  <span class="toggle-knob"></span>
                </button>
                <span class="toggle-label">
                  {{ editValues[setting.id] ? '✅ Activé' : '❌ Désactivé' }}
                </span>
              </div>
            </div>

            <!-- Champ : JSON / List -->
            <div v-else-if="setting.type === 'json' || setting.type === 'list'" class="setting-field">
              <div class="json-table">
                <!-- Header -->
                <div class="json-header">
                  <div class="json-col-key">Clé (valeur technique)</div>
                  <div class="json-col-label">Libellé (affiché)</div>
                  <div class="json-col-action">Action</div>
                </div>

                <!-- Lignes -->
                <div
                  v-for="(entry, idx) in getJsonEntries(setting.id)"
                  :key="idx"
                  class="json-row"
                >
                  <div class="json-col-key">
                    <input
                      type="text"
                      v-model="entry.key"
                      class="field-input field-mono"
                      placeholder="clé"
                      @input="syncJsonEntries(setting.id)"
                    />
                  </div>
                  <div class="json-col-label">
                    <input
                      type="text"
                      v-model="entry.label"
                      class="field-input"
                      placeholder="Libellé"
                      @input="syncJsonEntries(setting.id)"
                    />
                  </div>
                  <div class="json-col-action">
                    <button
                      class="btn-icon danger"
                      @click="removeJsonEntry(setting.id, idx)"
                      title="Supprimer"
                    >
                      ✕
                    </button>
                  </div>
                </div>

                <!-- Ajouter -->
                <div class="json-add">
                  <button class="btn-add" @click="addJsonEntry(setting.id)">
                    + Ajouter une entrée
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Barre sticky modifications non sauvegardées -->
        <div v-if="hasChanges" class="unsaved-bar">
          <span class="unsaved-text">⚠️ Modifications non sauvegardées</span>
          <div class="unsaved-actions">
            <button class="btn btn-secondary btn-sm" @click="cancelChanges">Annuler</button>
            <button class="btn btn-primary btn-sm" @click="saveCurrentGroup" :disabled="saving">
              💾 Enregistrer les modifications
            </button>
          </div>
        </div>
      </div>

      <!-- Aucun paramètre -->
      <div v-else class="empty-state">
        <span class="empty-icon">⚙️</span>
        <h3>Aucun paramètre pour ce groupe</h3>
        <p>Ce groupe n'a pas encore de paramètres configurés</p>
      </div>
    </div>

    <!-- Toast succès -->
    <Transition name="toast">
      <div v-if="showSuccess" class="toast-success">
        ✅ {{ successMessage }}
      </div>
    </Transition>

    <!-- Modal Reset -->
    <div class="modal-overlay" v-if="showResetModal" @click.self="showResetModal = false">
      <div class="modal">
        <div class="modal-header">
          <h2>🔄 Réinitialiser ce groupe ?</h2>
          <button class="close-btn" @click="showResetModal = false">×</button>
        </div>
        <div class="modal-body">
          <p>
            Tous les paramètres du groupe <strong>{{ currentTab?.label }}</strong>
            seront restaurés à leurs valeurs par défaut.
          </p>
          <p class="text-muted">Cette action est irréversible.</p>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="showResetModal = false">Annuler</button>
          <button class="btn btn-warning" @click="doResetGroup" :disabled="saving">
            {{ saving ? 'Réinitialisation...' : '🔄 Réinitialiser' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.settings-page {
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

/* ─── Boutons ─── */
.btn {
  padding: 10px 20px;
  border: none;
  border-radius: 8px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 14px;
}

.btn-primary {
  background: linear-gradient(135deg, #3498db, #2980b9);
  color: white;
}

.btn-primary:hover {
  background: linear-gradient(135deg, #2980b9, #2471a3);
}

.btn-secondary {
  background: #ecf0f1;
  color: #2c3e50;
}

.btn-secondary:hover {
  background: #d5dbdb;
}

.btn-warning {
  background: linear-gradient(135deg, #f39c12, #d68910);
  color: white;
}

.btn-warning:hover {
  background: linear-gradient(135deg, #d68910, #b7770d);
}

.btn-sm {
  padding: 8px 16px;
  font-size: 13px;
}

.btn.disabled,
.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* ─── Container ─── */
.settings-container {
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  overflow: hidden;
}

/* ─── Onglets ─── */
.tabs-bar {
  display: flex;
  overflow-x: auto;
  border-bottom: 1px solid #eee;
  background: #f8f9fa;
  padding: 0;
}

.tab-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 14px 18px;
  border: none;
  border-bottom: 3px solid transparent;
  background: transparent;
  cursor: pointer;
  white-space: nowrap;
  font-size: 13px;
  font-weight: 500;
  color: #7f8c8d;
  transition: all 0.2s;
}

.tab-btn:hover {
  color: #2c3e50;
  background: rgba(52, 152, 219, 0.05);
}

.tab-btn.active {
  color: #3498db;
  border-bottom-color: #3498db;
  background: white;
}

.tab-icon {
  font-size: 16px;
}

/* ─── Content ─── */
.settings-content {
  padding: 25px;
}

.content-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 25px;
}

.content-header h2 {
  font-size: 18px;
  color: #2c3e50;
  margin: 0;
}

.content-actions {
  display: flex;
  gap: 10px;
}

/* ─── Settings list ─── */
.settings-list {
  display: flex;
  flex-direction: column;
  gap: 15px;
}

.setting-card {
  background: #f8f9fa;
  border-radius: 10px;
  padding: 20px;
  border: 1px solid #eee;
  transition: border-color 0.2s;
}

.setting-card:hover {
  border-color: #d5dbdb;
}

.setting-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 12px;
}

.setting-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.setting-label {
  font-size: 14px;
  font-weight: 600;
  color: #2c3e50;
}

.setting-desc {
  font-size: 12px;
  color: #95a5a6;
}

.setting-badges {
  display: flex;
  gap: 8px;
  align-items: center;
  flex-shrink: 0;
}

.type-badge {
  padding: 3px 10px;
  border-radius: 15px;
  font-size: 11px;
  font-weight: 500;
}

.type-string {
  background: #e8f4fd;
  color: #2980b9;
}

.type-integer {
  background: #e8daef;
  color: #7d3c98;
}

.type-boolean {
  background: #d4edda;
  color: #155724;
}

.type-json {
  background: #fff3cd;
  color: #856404;
}

.system-badge {
  padding: 3px 10px;
  border-radius: 15px;
  font-size: 11px;
  font-weight: 500;
  background: #f8d7da;
  color: #721c24;
}

/* ─── Fields ─── */
.setting-field {
  margin-top: 8px;
}

.field-input {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid #ddd;
  border-radius: 6px;
  font-size: 14px;
  transition: border-color 0.2s;
  background: white;
}

.field-input:focus {
  outline: none;
  border-color: #3498db;
  box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
}

.field-number {
  width: 200px;
}

.field-mono {
  font-family: monospace;
  background: #fafafa;
}

/* ─── Toggle ─── */
.toggle-row {
  display: flex;
  align-items: center;
  gap: 12px;
}

.toggle-btn {
  position: relative;
  width: 48px;
  height: 26px;
  border-radius: 13px;
  border: none;
  background: #bdc3c7;
  cursor: pointer;
  transition: background 0.3s;
  padding: 0;
}

.toggle-btn.active {
  background: #3498db;
}

.toggle-knob {
  position: absolute;
  top: 3px;
  left: 3px;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: white;
  transition: transform 0.3s;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
}

.toggle-btn.active .toggle-knob {
  transform: translateX(22px);
}

.toggle-label {
  font-size: 13px;
  color: #555;
}

/* ─── JSON Table ─── */
.json-table {
  background: white;
  border: 1px solid #ddd;
  border-radius: 8px;
  overflow: hidden;
}

.json-header {
  display: grid;
  grid-template-columns: 1fr 1.5fr 80px;
  gap: 10px;
  padding: 10px 15px;
  background: #f0f0f0;
  font-size: 11px;
  font-weight: 600;
  color: #7f8c8d;
  text-transform: uppercase;
}

.json-row {
  display: grid;
  grid-template-columns: 1fr 1.5fr 80px;
  gap: 10px;
  padding: 8px 15px;
  border-top: 1px solid #eee;
  align-items: center;
}

.json-row .field-input {
  padding: 7px 10px;
  font-size: 13px;
}

.json-col-action {
  text-align: center;
}

.btn-icon {
  width: 30px;
  height: 30px;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 14px;
  transition: all 0.2s;
  background: #f0f0f0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.btn-icon:hover {
  background: #e0e0e0;
}

.btn-icon.danger {
  background: #f8d7da;
  color: #721c24;
}

.btn-icon.danger:hover {
  background: #f5c6cb;
}

.json-add {
  padding: 10px 15px;
  border-top: 1px solid #eee;
}

.btn-add {
  background: none;
  border: none;
  color: #3498db;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  padding: 5px 0;
  transition: color 0.2s;
}

.btn-add:hover {
  color: #2471a3;
}

/* ─── Unsaved bar ─── */
.unsaved-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 20px;
  padding: 15px 20px;
  background: #e8f4fd;
  border: 1px solid #bee5eb;
  border-radius: 10px;
}

.unsaved-text {
  font-size: 13px;
  font-weight: 500;
  color: #2980b9;
}

.unsaved-actions {
  display: flex;
  gap: 10px;
}

/* ─── Empty & Loading ─── */
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
  to {
    transform: rotate(360deg);
  }
}

.error-state {
  text-align: center;
  padding: 40px;
}

.error-icon {
  font-size: 40px;
}

.error-state p {
  color: #e74c3c;
  margin: 10px 0 15px;
}

/* ─── Toast ─── */
.toast-success {
  position: fixed;
  bottom: 30px;
  right: 30px;
  background: linear-gradient(135deg, #27ae60, #1e8449);
  color: white;
  padding: 14px 24px;
  border-radius: 10px;
  box-shadow: 0 8px 25px rgba(39, 174, 96, 0.3);
  font-size: 14px;
  font-weight: 500;
  z-index: 3000;
}

.toast-enter-active,
.toast-leave-active {
  transition: all 0.3s ease;
}

.toast-enter-from,
.toast-leave-to {
  opacity: 0;
  transform: translateY(20px);
}

/* ─── Modal ─── */
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
  max-width: 480px;
  max-height: 90vh;
  overflow: hidden;
  display: flex;
  flex-direction: column;
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
}

.modal-body p {
  margin: 0 0 10px;
  color: #555;
  font-size: 14px;
  line-height: 1.5;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 15px 20px;
  border-top: 1px solid #eee;
}

.text-muted {
  color: #95a5a6;
  font-size: 12px;
}

/* ─── Responsive ─── */
@media (max-width: 768px) {
  .settings-page {
    padding: 15px;
  }

  .tabs-bar {
    padding: 0 10px;
  }

  .tab-label {
    display: none;
  }

  .tab-btn {
    padding: 12px 14px;
  }

  .content-header {
    flex-direction: column;
    gap: 15px;
    align-items: flex-start;
  }

  .json-header,
  .json-row {
    grid-template-columns: 1fr 1fr 60px;
  }

  .unsaved-bar {
    flex-direction: column;
    gap: 10px;
    text-align: center;
  }

  .field-number {
    width: 100%;
  }

  .setting-header {
    flex-direction: column;
    gap: 10px;
  }
}
</style>
