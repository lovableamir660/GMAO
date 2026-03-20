<script setup>
defineProps({
  assignment: { type: Object, required: true },
  formatDateShort: { type: Function, required: true },
  formatNumber: { type: Function, required: true },
  getReasonIcon: { type: Function, required: true },
  getReasonLabel: { type: Function, required: true },
  getDurationClass: { type: Function, required: true },
})

defineEmits(['detail', 'unassign'])
</script>

<template>
  <div class="assignment-card" @click="$emit('detail', assignment)">
    <div class="card-status">
      <span class="status-indicator active"></span>
      <span class="status-text">En cours</span>
      <span class="assignment-duration" :class="getDurationClass(assignment.duration)">
        {{ assignment.duration || "Aujourd'hui" }}
      </span>
    </div>

    <div class="assignment-pair">
      <div class="entity truck">
        <div class="entity-avatar truck">🚛</div>
        <div class="entity-info">
          <div class="entity-name">{{ assignment.truck?.registration_number }}</div>
          <div class="entity-detail">{{ assignment.truck?.brand }} {{ assignment.truck?.model }}</div>
        </div>
      </div>

      <div class="pair-connector">
        <div class="connector-line"></div>
        <div class="connector-icon">🔗</div>
        <div class="connector-line"></div>
      </div>

      <div class="entity driver">
        <div class="entity-avatar driver">👷</div>
        <div class="entity-info">
          <div class="entity-name">{{ assignment.driver?.first_name }} {{ assignment.driver?.last_name }}</div>
          <div class="entity-detail">{{ assignment.driver?.code }}</div>
        </div>
      </div>
    </div>

    <div class="assignment-meta">
      <div class="meta-item">
        <span class="meta-icon">📅</span>
        <span class="meta-label">Début</span>
        <span class="meta-value">{{ formatDateShort(assignment.assigned_at) }}</span>
      </div>
      <div class="meta-item">
        <span class="meta-icon">🛣️</span>
        <span class="meta-label">Km départ</span>
        <span class="meta-value">{{ formatNumber(assignment.start_mileage) }}</span>
      </div>
      <div class="meta-item" v-if="assignment.assignment_reason">
        <span class="meta-icon">{{ getReasonIcon(assignment.assignment_reason) }}</span>
        <span class="meta-label">Raison</span>
        <span class="meta-value">{{ getReasonLabel(assignment.assignment_reason) }}</span>
      </div>
    </div>

    <div class="card-actions" @click.stop>
      <button class="btn-action info" @click="$emit('detail', assignment)" title="Détails">👁️</button>
      <button class="btn-action danger" @click="$emit('unassign', assignment)" title="Terminer">⏹️</button>
    </div>
  </div>
</template>
<style scoped>
.assignment-card {
  background: white;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
  border-left: 4px solid #27ae60;
  cursor: pointer;
  transition: all 0.2s;
}
.assignment-card:hover {
  box-shadow: 0 8px 25px rgba(0,0,0,0.12);
  transform: translateY(-3px);
}
.card-status {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 15px;
}
.status-indicator {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  animation: pulse 2s infinite;
}
.status-indicator.active {
  background: #27ae60;
}
@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.5; }
}
.status-text {
  font-size: 12px;
  font-weight: 600;
  color: #27ae60;
  text-transform: uppercase;
}
.assignment-duration {
  margin-left: auto;
  font-size: 12px;
  padding: 4px 10px;
  background: #f8f9fa;
  border-radius: 15px;
  color: #7f8c8d;
}
.assignment-duration.long { background: #fff3cd; color: #856404; }
.assignment-duration.medium { background: #e8f4fd; color: #2980b9; }

.assignment-pair {
  display: flex;
  align-items: center;
  gap: 15px;
  padding: 20px;
  background: linear-gradient(135deg, #f8f9fa, #e9ecef);
  border-radius: 12px;
  margin-bottom: 15px;
}
.entity {
  flex: 1;
  display: flex;
  align-items: center;
  gap: 12px;
}
.entity-avatar {
  width: 50px;
  height: 50px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
}
.entity-avatar.truck { background: linear-gradient(135deg, #3498db, #2980b9); }
.entity-avatar.driver { background: linear-gradient(135deg, #9b59b6, #7d3c98); }
.entity-name {
  font-weight: 600;
  color: #2c3e50;
  font-size: 14px;
}
.entity-detail {
  font-size: 12px;
  color: #7f8c8d;
}
.pair-connector {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 5px;
}
.connector-line {
  width: 2px;
  height: 15px;
  background: linear-gradient(to bottom, transparent, #3498db, transparent);
}
.connector-icon {
  font-size: 20px;
}

.assignment-meta {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 10px;
  margin-bottom: 15px;
}
.meta-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  padding: 10px;
  background: #f8f9fa;
  border-radius: 8px;
}
.meta-icon { font-size: 16px; margin-bottom: 4px; }
.meta-label { font-size: 10px; color: #7f8c8d; text-transform: uppercase; }
.meta-value { font-size: 12px; font-weight: 600; color: #2c3e50; }

.card-actions {
  display: flex;
  gap: 8px;
  justify-content: flex-end;
  padding-top: 15px;
  border-top: 1px solid #eee;
}
.btn-action {
  width: 40px;
  height: 40px;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-size: 18px;
  transition: all 0.2s;
}
.btn-action.info { background: #e8f4fd; }
.btn-action.danger { background: #f8d7da; }
.btn-action:hover { transform: scale(1.1); }

@media (max-width: 768px) {
  .assignment-pair { flex-direction: column; }
  .pair-connector { flex-direction: row; }
  .connector-line { width: 30px; height: 2px; }
}
</style>

