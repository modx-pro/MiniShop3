<script setup>
import { useLexicon } from '@vuetools/useLexicon'
import Button from 'primevue/button'

defineProps({
  isCreateMode: { type: Boolean, required: true },
  saving: { type: Boolean, default: false },
  /** Blocks save while manager cost recalculation is in flight (#379). */
  recalculatingCost: { type: Boolean, default: false },
})

const emit = defineEmits(['create', 'save', 'cancel'])
const { _ } = useLexicon()
</script>

<template>
  <div class="actions-bar mt-3">
    <Button
      v-if="isCreateMode"
      :label="_('ms3_order_create')"
      icon="pi pi-plus"
      severity="success"
      :loading="saving"
      @click="emit('create')"
    />
    <Button
      v-else
      :label="_('save')"
      icon="pi pi-check"
      :loading="saving"
      :disabled="recalculatingCost"
      @click="emit('save')"
    />
    <Button
      :label="_('cancel')"
      icon="pi pi-times"
      severity="secondary"
      @click="emit('cancel')"
    />
  </div>
</template>

<style scoped src="./orderFieldsLayout.css"></style>
