<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  message: { type: String, default: '' },
  type: { type: String, default: 'success' }, // 'success' | 'error' | 'info'
  show: { type: Boolean, default: false },
  duration: { type: Number, default: 3000 },
})

const emit = defineEmits(['close'])
let timer = null

watch(() => props.show, (val) => {
  if (val) {
    clearTimeout(timer)
    timer = setTimeout(() => emit('close'), props.duration)
  }
})
</script>

<template>
  <Teleport to="body">
    <Transition name="toast">
      <div v-if="show" class="toast" :class="type">
        <span class="toast-icon">
          {{ type === 'success' ? '✅' : type === 'error' ? '❌' : 'ℹ️' }}
        </span>
        <span class="toast-message">{{ message }}</span>
        <button class="toast-close" @click="$emit('close')">×</button>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.toast {
  position: fixed;
  top: 20px;
  right: 20px;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 14px 20px;
  border-radius: 10px;
  color: white;
  font-weight: 500;
  font-size: 14px;
  z-index: 5000;
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
  max-width: 400px;
}
.toast.success { background: linear-gradient(135deg, #27ae60, #1e8449); }
.toast.error { background: linear-gradient(135deg, #e74c3c, #c0392b); }
.toast.info { background: linear-gradient(135deg, #3498db, #2980b9); }
.toast-close {
  background: none; border: none; color: white;
  font-size: 20px; cursor: pointer; margin-left: 10px; opacity: 0.7;
}
.toast-close:hover { opacity: 1; }
.toast-enter-active { animation: slideIn 0.3s ease; }
.toast-leave-active { animation: slideOut 0.3s ease; }
@keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
@keyframes slideOut { from { transform: translateX(0); opacity: 1; } to { transform: translateX(100%); opacity: 0; } }
</style>
