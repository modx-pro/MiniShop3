<script setup>
import Tag from 'primevue/tag'
import { computed } from 'vue'

const props = defineProps({
  raw: { type: [String, Number, Object, Array], default: null },
  formatOptions: { type: Function, required: true },
})

const tags = computed(() => props.formatOptions(props.raw))
</script>

<template>
  <div class="order-product-options-chips">
    <Tag
      v-for="(opt, idx) in tags"
      :key="idx"
      :value="opt"
      severity="secondary"
      class="chip-space"
    />
    <span v-if="!tags.length">—</span>
  </div>
</template>

<style scoped>
.order-product-options-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 0.25rem;
}

.chip-space {
  margin-right: 0.25rem;
  margin-bottom: 0.25rem;
}
</style>
