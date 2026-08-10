<script setup>
import { computed, ref, watch } from 'vue'

import ResourceCategoryTree from '../ResourceCategoryTree.vue'

const props = defineProps({
  productId: {
    type: Number,
    required: true,
  },
  parentId: {
    type: Number,
    default: 0,
  },
  initialCategories: {
    type: Array,
    default: () => [],
  },
})

const selectedIds = ref(normalizeIds(props.initialCategories))

const parentCategoryId = computed(() => Number(props.parentId) || 0)

const lockedIds = computed(() =>
  parentCategoryId.value > 0 ? [parentCategoryId.value] : []
)

const treeApiParams = computed(() => ({
  parent_category: parentCategoryId.value,
}))

const hiddenValue = computed(() => JSON.stringify(selectedIds.value))

function normalizeIds(ids) {
  if (!Array.isArray(ids)) {
    return []
  }
  return ids.map(id => Number(id)).filter(id => id > 0)
}

watch(
  () => props.initialCategories,
  value => {
    selectedIds.value = normalizeIds(value)
  },
  { deep: true }
)
</script>

<template>
  <div class="product-categories-tab">
    <input type="hidden" name="categories" :value="hiddenValue" />
    <ResourceCategoryTree
      v-model="selectedIds"
      :api-url="`/api/mgr/product-data/${productId}/categories/tree`"
      :api-params="treeApiParams"
      :locked-ids="lockedIds"
      input-id-prefix="prod-cat-"
    />
  </div>
</template>

<style scoped>
.product-categories-tab {
  display: flex;
  flex-direction: column;
  min-height: 20rem;
  width: 100%;
}
</style>
