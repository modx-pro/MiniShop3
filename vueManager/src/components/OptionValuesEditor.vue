<script setup>
import { useLexicon } from '@vuetools/useLexicon'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import { computed } from 'vue'
import draggable from 'vuedraggable'

/**
 * Editor for option.properties.values.
 *
 * - variant="simple": list of string values (combobox, comboMultiple).
 *   Stored as: ['Red', 'Blue', 'Green']
 *
 * - variant="colors": pairs of {value: displayName, name: hexColor} (comboColors).
 *   Stored as: [{value: 'Red', name: '#FF0000'}, ...]
 *   Note: legacy PHP uses `name` for the hex and `value` for the label — we keep that contract.
 */

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  variant: { type: String, default: 'simple' }, // 'simple' | 'colors'
})

const emit = defineEmits(['update:modelValue'])
const { _ } = useLexicon()

const items = computed({
  get: () => props.modelValue || [],
  set: v => emit('update:modelValue', v),
})

function addRow() {
  if (props.variant === 'colors') {
    items.value = [...items.value, { value: '', name: '' }]
  } else {
    items.value = [...items.value, '']
  }
}

function removeRow(index) {
  const next = [...items.value]
  next.splice(index, 1)
  items.value = next
}

function updateSimple(index, value) {
  const next = [...items.value]
  next[index] = value
  items.value = next
}

function updateColor(index, field, value) {
  const next = items.value.map((row, i) => {
    if (i !== index) return row
    return typeof row === 'object' && row !== null ? { ...row, [field]: value } : { [field]: value }
  })
  items.value = next
}

function onDragEnd(event) {
  // vuedraggable already mutates the bound array; just re-emit to trigger parent watchers.
  items.value = [...(event.to.__vueParentComponent?.props?.list || items.value)]
}

function isValidHex(hex) {
  return typeof hex === 'string' && /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(hex.trim())
}
</script>

<template>
  <div class="option-values-editor">
    <draggable
      :list="items"
      item-key="_index"
      handle=".drag-handle"
      :animation="150"
      class="values-list"
      @end="onDragEnd"
    >
      <template #item="{ index }">
        <div class="value-row">
          <span class="drag-handle" title="Drag to reorder"><i class="pi pi-bars" /></span>

          <template v-if="variant === 'simple'">
            <InputText
              :model-value="items[index]"
              class="value-input"
              :placeholder="_('ms3_default_value') || 'Значение'"
              @update:model-value="updateSimple(index, $event)"
            />
          </template>

          <template v-else>
            <InputText
              :model-value="items[index]?.value || ''"
              class="value-input value-input-name"
              placeholder="Название"
              @update:model-value="updateColor(index, 'value', $event)"
            />
            <span
              class="color-swatch"
              :class="{ invalid: !isValidHex(items[index]?.name) && items[index]?.name }"
              :style="{
                backgroundColor: isValidHex(items[index]?.name)
                  ? items[index]?.name
                  : 'transparent',
              }"
            ></span>
            <InputText
              :model-value="items[index]?.name || ''"
              class="value-input value-input-hex"
              placeholder="#FF0000"
              @update:model-value="updateColor(index, 'name', $event)"
            />
          </template>

          <Button
            icon="pi pi-times"
            severity="danger"
            text
            rounded
            size="small"
            @click="removeRow(index)"
          />
        </div>
      </template>
    </draggable>

    <Button
      icon="pi pi-plus"
      :label="_('ms3_add_value') || 'Добавить значение'"
      severity="secondary"
      size="small"
      class="add-button"
      @click="addRow"
    />
  </div>
</template>

<style>
/* Non-scoped with .vueApp prefix — avoids Vite scoped-hash mismatch across chunks */
.vueApp .option-values-editor {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  padding: 0.5rem;
  border: 1px solid var(--p-content-border-color, #e5e7eb);
  border-radius: 0.375rem;
  background: var(--p-content-background, #fff);
}

.vueApp .option-values-editor .values-list {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
}

.vueApp .option-values-editor .value-row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.vueApp .option-values-editor .drag-handle {
  cursor: grab;
  color: var(--p-text-muted-color, #9ca3af);
  padding: 0.25rem;
}

.vueApp .option-values-editor .drag-handle:active {
  cursor: grabbing;
}

.vueApp .option-values-editor .value-input {
  flex: 1;
}

.vueApp .option-values-editor .value-input-name {
  flex: 2;
}

.vueApp .option-values-editor .value-input-hex {
  flex: 1;
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
}

.vueApp .option-values-editor .color-swatch {
  display: inline-block;
  width: 1.5rem;
  height: 1.5rem;
  border-radius: 0.25rem;
  border: 1px solid rgba(0, 0, 0, 0.1);
  flex-shrink: 0;
}

.vueApp .option-values-editor .color-swatch.invalid {
  border-color: var(--p-red-500, #ef4444);
  background: repeating-linear-gradient(45deg, #fee, #fee 2px, transparent 2px, transparent 4px);
}

.vueApp .option-values-editor .add-button {
  align-self: flex-start;
}
</style>
