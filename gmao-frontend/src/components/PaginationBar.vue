<script setup>
import { computed } from 'vue'

const props = defineProps({
  currentPage: { type: Number, required: true },
  lastPage: { type: Number, required: true },
  total: { type: Number, default: 0 },
})

const emit = defineEmits(['page-change'])

const visiblePages = computed(() => {
  const pages = []
  const current = props.currentPage
  const last = props.lastPage
  const delta = 2

  let start = Math.max(1, current - delta)
  let end = Math.min(last, current + delta)

  if (start > 1) { pages.push(1); if (start > 2) pages.push('...') }
  for (let i = start; i <= end; i++) pages.push(i)
  if (end < last) { if (end < last - 1) pages.push('...'); pages.push(last) }

  return pages
})
</script>

<template>
  <div class="pagination-bar" v-if="lastPage > 1">
    <span class="pagination-info">
      Page {{ currentPage }} sur {{ lastPage }}
      <span v-if="total"> — {{ total }} résultats</span>
    </span>
    <div class="pagination-buttons">
      <button
        class="page-btn"
        :disabled="currentPage <= 1"
        @click="emit('page-change', currentPage - 1)"
      >
        ← Précédent
      </button>
      <button
        v-for="page in visiblePages"
        :key="page"
        class="page-btn"
        :class="{ active: page === currentPage, ellipsis: page === '...' }"
        :disabled="page === '...'"
        @click="page !== '...' && emit('page-change', page)"
      >
        {{ page }}
      </button>
      <button
        class="page-btn"
        :disabled="currentPage >= lastPage"
        @click="emit('page-change', currentPage + 1)"
      >
        Suivant →
      </button>
    </div>
  </div>
</template>

<style scoped>
.pagination-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 20px;
  flex-wrap: wrap;
  gap: 10px;
}
.pagination-info { font-size: 13px; color: #7f8c8d; }
.pagination-buttons { display: flex; gap: 4px; }
.page-btn {
  padding: 8px 14px;
  border: 1px solid #ddd;
  background: white;
  border-radius: 6px;
  cursor: pointer;
  font-size: 13px;
  transition: all 0.2s;
}
.page-btn:hover:not(:disabled):not(.ellipsis) { background: #f0f0f0; }
.page-btn.active { background: #3498db; color: white; border-color: #3498db; }
.page-btn:disabled { opacity: 0.5; cursor: default; }
.page-btn.ellipsis { border: none; background: none; cursor: default; }
</style>
