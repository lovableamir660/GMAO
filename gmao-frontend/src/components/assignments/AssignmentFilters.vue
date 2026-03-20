<script setup>
defineProps({
  filters: { type: Object, required: true },
  trucks: { type: Array, required: true },
  drivers: { type: Array, required: true },
  assignmentReasons: { type: Array, required: true },
  hasActiveFilters: { type: Boolean, default: false },
})

defineEmits(['apply', 'reset', 'export'])
</script>

<template>
  <div class="filters-bar">
    <div class="search-group">
      <select v-model="filters.truck_id" @change="$emit('apply')" aria-label="Filtrer par camion">
        <option value="">🚛 Tous les camions</option>
        <option v-for="truck in trucks" :key="truck.id" :value="truck.id">
          {{ truck.registration_number }}
        </option>
      </select>
      <select v-model="filters.driver_id" @change="$emit('apply')" aria-label="Filtrer par chauffeur">
        <option value="">👷 Tous les chauffeurs</option>
        <option v-for="driver in drivers" :key="driver.id" :value="driver.id">
          {{ driver.first_name }} {{ driver.last_name }}
        </option>
      </select>
      <select v-model="filters.status" @change="$emit('apply')" aria-label="Filtrer par statut">
        <option value="">📊 Tous statuts</option>
        <option value="active">🟢 En cours</option>
        <option value="completed">✅ Terminées</option>
      </select>
      <select v-model="filters.reason" @change="$emit('apply')" aria-label="Filtrer par raison">
        <option value="">📋 Toutes raisons</option>
        <option v-for="reason in assignmentReasons" :key="reason.value" :value="reason.value">
          {{ reason.icon }} {{ reason.label }}
        </option>
      </select>
    </div>
    <div class="date-group">
      <div class="date-input">
        <label>Du</label>
        <input type="date" v-model="filters.from_date" @change="$emit('apply')" />
      </div>
      <div class="date-input">
        <label>Au</label>
        <input type="date" v-model="filters.to_date" @change="$emit('apply')" />
      </div>
    </div>
    <div class="filter-actions">
      <button class="btn btn-secondary btn-sm" @click="$emit('reset')" v-if="hasActiveFilters">
        ✕ Reset
      </button>
      <button class="btn btn-secondary btn-sm" @click="$emit('export')" title="Exporter en CSV">
        📥 Export CSV
      </button>
    </div>
  </div>
</template>
<style scoped>
.filters-bar {
  display: flex;
  gap: 15px;
  margin-bottom: 20px;
  flex-wrap: wrap;
  align-items: flex-end;
}
.search-group {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}
.search-group select {
  padding: 10px 15px;
  border: 1px solid #ddd;
  border-radius: 8px;
  font-size: 13px;
  min-width: 160px;
  background: white;
  cursor: pointer;
  transition: border-color 0.2s;
}
.search-group select:focus {
  outline: none;
  border-color: #3498db;
}
.date-group {
  display: flex;
  gap: 10px;
}
.date-input {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.date-input label {
  font-size: 11px;
  color: #7f8c8d;
}
.date-input input {
  padding: 8px 12px;
  border: 1px solid #ddd;
  border-radius: 6px;
  font-size: 13px;
}
.date-input input:focus {
  outline: none;
  border-color: #3498db;
}
.filter-actions {
  display: flex;
  gap: 8px;
}
.btn {
  padding: 10px 20px;
  border: none;
  border-radius: 8px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}
.btn-secondary { background: #ecf0f1; color: #2c3e50; }
.btn-secondary:hover { background: #dde1e3; }
.btn-sm { padding: 6px 12px; font-size: 12px; }

@media (max-width: 768px) {
  .filters-bar { flex-direction: column; }
  .search-group { flex-direction: column; }
  .search-group select { min-width: unset; width: 100%; }
  .date-group { flex-direction: column; }
}
</style>
