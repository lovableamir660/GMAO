<script setup>
defineProps({
  show: { type: Boolean, default: false },
  title: { type: String, default: 'Confirmation' },
  message: { type: String, default: 'Êtes-vous sûr ?' },
  confirmText: { type: String, default: 'Confirmer' },
  cancelText: { type: String, default: 'Annuler' },
  variant: { type: String, default: 'danger' }, // 'danger' | 'primary'
})

defineEmits(['confirm', 'cancel'])
</script>

<template>
  <Teleport to="body">
    <div class="confirm-overlay" v-if="show" @click.self="$emit('cancel')">
      <div class="confirm-dialog">
        <div class="confirm-icon">⚠️</div>
        <h3>{{ title }}</h3>
        <p>{{ message }}</p>
        <div class="confirm-actions">
          <button class="btn btn-secondary" @click="$emit('cancel')">{{ cancelText }}</button>
          <button class="btn" :class="'btn-' + variant" @click="$emit('confirm')">{{ confirmText }}</button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.confirm-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 3000;
}
.confirm-dialog {
  background: white;
  border-radius: 12px;
  padding: 30px;
  text-align: center;
  max-width: 400px;
  width: 90%;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
}
.confirm-icon { font-size: 48px; margin-bottom: 10px; }
.confirm-dialog h3 { margin: 0 0 10px; color: #2c3e50; }
.confirm-dialog p { color: #7f8c8d; margin: 0 0 20px; font-size: 14px; }
.confirm-actions { display: flex; gap: 10px; justify-content: center; }
.btn { padding: 10px 20px; border: none; border-radius: 8px; font-weight: 500; cursor: pointer; }
.btn-secondary { background: #ecf0f1; color: #2c3e50; }
.btn-danger { background: linear-gradient(135deg, #e74c3c, #c0392b); color: white; }
.btn-primary { background: linear-gradient(135deg, #3498db, #2980b9); color: white; }
</style>
