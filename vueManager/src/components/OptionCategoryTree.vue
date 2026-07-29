<script setup>
import { computed } from 'vue'

import ResourceCategoryTree from './ResourceCategoryTree.vue'

const modelValue = defineModel({ type: Array, default: () => [] })

const props = defineProps({
  optionId: { type: [Number, String], default: 0 },
})

const apiParams = computed(() => (props.optionId ? { option_id: props.optionId } : {}))
</script>

<template>
  <ResourceCategoryTree
    v-model="modelValue"
    api-url="/api/mgr/options/tree"
    :api-params="apiParams"
    input-id-prefix="opt-cat-"
  />
</template>
