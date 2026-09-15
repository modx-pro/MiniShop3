<script setup>
import { useLexicon } from '@vuetools/useLexicon'
import Checkbox from 'primevue/checkbox'
import DatePicker from 'primevue/datepicker'
import InputChips from 'primevue/inputchips'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import MultiSelect from 'primevue/multiselect'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import { computed, ref, watch } from 'vue'

import request from '../../request.js'
import { formatLocalDateYmd } from '../../utils/formatLocalDateYmd.js'

const props = defineProps({
  option: { type: Object, required: true },
})

const { _ } = useLexicon()

const emit = defineEmits(['change'])

const fieldName = computed(() => `options-${props.option.key}`)
const fieldId = computed(() => `ms3-option-${props.option.key}`)
const isRequired = computed(() => Number(props.option.required) === 1)
/**
 * Option type stored in msOption.type (lowerCamelCase): textfield, numberfield, textarea, checkbox,
 * comboBoolean, combobox, comboMultiple, comboColors, comboOptions, datefield.
 * Normalized to lowercase for case-insensitive matching in the template.
 */
const optionType = computed(() => String(props.option.type || 'textfield').toLowerCase())
const caption = computed(() => props.option.caption || props.option.key)
const description = computed(() => props.option.description || '')

const MULTI_TYPES = new Set(['combomultiple', 'combocolors', 'combooptions'])
const BOOL_TYPES = new Set(['checkbox', 'comboboolean'])

function normalizeValue(raw, type) {
  if (raw === undefined || raw === null || raw === '') {
    if (MULTI_TYPES.has(type)) return []
    if (BOOL_TYPES.has(type)) return 0
    return ''
  }

  if (MULTI_TYPES.has(type)) {
    if (Array.isArray(raw)) {
      return raw.map(item => (typeof item === 'object' && item !== null ? item.value : item))
    }
    if (typeof raw === 'string') {
      return raw
        .split(',')
        .map(s => s.trim())
        .filter(Boolean)
    }
    return []
  }

  if (BOOL_TYPES.has(type)) {
    return raw === true || raw === 1 || raw === '1' || String(raw).toLowerCase() === 'true' ? 1 : 0
  }

  if (type === 'numberfield') {
    const n = Number(raw)
    return Number.isFinite(n) ? n : null
  }

  return raw
}

const value = ref(normalizeValue(props.option.value, optionType.value))

watch(
  () => props.option.value,
  newVal => {
    value.value = normalizeValue(newVal, optionType.value)
  }
)

function onChange() {
  emit('change', { key: props.option.key, value: value.value })
}

function formatDateForPost(raw) {
  if (raw instanceof Date) {
    return formatLocalDateYmd(raw) || ''
  }
  return raw || ''
}

/**
 * Options for Combobox/ComboMultiple/ComboColors built from option.properties.values
 * - Combobox/ComboMultiple: ['S', 'M', 'L'] or [{value: 'S'}, ...]
 * - ComboColors: [{value: 'Red', name: '#ff0000'}, ...]
 */
const selectOptions = computed(() => {
  const values = props.option.properties?.values
  if (!Array.isArray(values)) return []

  return values.map(item => {
    if (typeof item === 'string') return { label: item, value: item }
    if (typeof item === 'object' && item !== null) {
      if ('name' in item && 'value' in item) {
        return { label: item.value, value: item.value, color: item.name }
      }
      if ('value' in item) return { label: item.value, value: item.value }
    }
    return { label: String(item), value: item }
  })
})

const booleanOptions = computed(() => [
  { label: _('yes') || 'Да', value: 1 },
  { label: _('no') || 'Нет', value: 0 },
])

const multiArrayValue = computed({
  get: () => (Array.isArray(value.value) ? value.value : []),
  set: v => {
    value.value = v
  },
})

/**
 * Distinct values already saved for this option key by other products. Shown under the
 * InputChips as clickable pills — the autocomplete flavor of the comboOptions type.
 * Loaded lazily on the first keystroke and re-filtered client-side as the user types
 * so we don't spam the server on every key.
 */
const suggestions = ref([])
const suggestionsLoaded = ref(false)
let comboOptionsInputEl = null

async function loadComboOptionsSuggestions() {
  if (optionType.value !== 'combooptions' || suggestionsLoaded.value) return
  suggestionsLoaded.value = true
  try {
    const r = await request.get('/api/mgr/options/suggestions', {
      key: props.option.key,
      limit: 100,
    })
    suggestions.value = Array.isArray(r?.results) ? r.results : []
  } catch {
    suggestions.value = []
  }
}

function onComboOptionsKeyup(event) {
  if (!comboOptionsInputEl && event?.target) {
    comboOptionsInputEl = event.target
  }
  loadComboOptionsSuggestions()
}

// Hide already-added values and narrow by what the user is currently typing.
const filteredSuggestions = computed(() => {
  const picked = new Set(multiArrayValue.value)
  const typed = (comboOptionsInputEl?.value || '').trim().toLowerCase()
  return suggestions.value
    .filter(s => !picked.has(s))
    .filter(s => typed === '' || s.toLowerCase().includes(typed))
    .slice(0, 20)
})

function addSuggestion(s) {
  if (multiArrayValue.value.includes(s)) return
  value.value = [...multiArrayValue.value, s]
  if (comboOptionsInputEl) comboOptionsInputEl.value = ''
  onChange()
}
</script>

<template>
  <div class="option-field">
    <label v-if="optionType !== 'checkbox'" :for="fieldId" class="option-label">
      {{ caption }}
      <span v-if="isRequired" class="required">*</span>
    </label>

    <!-- Textfield -->
    <InputText
      v-if="optionType === 'textfield'"
      :id="fieldId"
      v-model="value"
      :name="fieldName"
      :required="isRequired"
      class="w-full"
      @blur="onChange"
    />

    <!-- Numberfield -->
    <template v-else-if="optionType === 'numberfield'">
      <InputNumber
        v-model="value"
        :input-id="fieldId"
        :required="isRequired"
        :use-grouping="false"
        :max-fraction-digits="4"
        class="w-full"
        fluid
        @blur="onChange"
      />
      <input type="hidden" :name="fieldName" :value="value ?? ''" />
    </template>

    <!-- Textarea -->
    <Textarea
      v-else-if="optionType === 'textarea'"
      :id="fieldId"
      v-model="value"
      :name="fieldName"
      :required="isRequired"
      rows="3"
      class="w-full"
      @blur="onChange"
    />

    <!-- Checkbox -->
    <div v-else-if="optionType === 'checkbox'" class="option-checkbox">
      <Checkbox
        v-model="value"
        :input-id="fieldId"
        :binary="true"
        :true-value="1"
        :false-value="0"
        @change="onChange"
      />
      <label :for="fieldId" class="option-label-inline">
        {{ caption }}
        <span v-if="isRequired" class="required">*</span>
      </label>
      <input type="hidden" :name="fieldName" :value="value" />
    </div>

    <!-- ComboBoolean (Да/Нет) -->
    <template v-else-if="optionType === 'comboboolean'">
      <Select
        v-model="value"
        :input-id="fieldId"
        :options="booleanOptions"
        option-label="label"
        option-value="value"
        class="w-full"
        @change="onChange"
      />
      <input type="hidden" :name="fieldName" :value="value" />
    </template>

    <!-- Combobox (single select from properties.values) -->
    <template v-else-if="optionType === 'combobox'">
      <Select
        v-model="value"
        :input-id="fieldId"
        :options="selectOptions"
        option-label="label"
        option-value="value"
        :show-clear="!isRequired"
        class="w-full"
        @change="onChange"
      />
      <input type="hidden" :name="fieldName" :value="value ?? ''" />
    </template>

    <!-- ComboMultiple (multi select from properties.values) -->
    <template v-else-if="optionType === 'combomultiple'">
      <MultiSelect
        v-model="multiArrayValue"
        :input-id="fieldId"
        :options="selectOptions"
        option-label="label"
        option-value="value"
        :filter="true"
        class="w-full"
        @change="onChange"
      />
      <!-- One hidden input with JSON array payload: ExtJS BasicForm.getValues() reads only -->
      <!-- the last DOM input for a given name, which would drop all but the last pick when -->
      <!-- using {name}[] siblings. The Product Update/Create processor decodes this JSON. -->
      <input type="hidden" :name="fieldName" :value="JSON.stringify(multiArrayValue)" />
    </template>

    <!-- ComboColors (multi with color swatches) -->
    <template v-else-if="optionType === 'combocolors'">
      <MultiSelect
        v-model="multiArrayValue"
        :input-id="fieldId"
        :options="selectOptions"
        option-label="label"
        option-value="value"
        :filter="true"
        class="w-full"
        @change="onChange"
      >
        <template #option="slot">
          <span class="color-swatch" :style="{ backgroundColor: slot.option.color }"></span>
          <span>{{ slot.option.label }}</span>
        </template>
      </MultiSelect>
      <input type="hidden" :name="fieldName" :value="JSON.stringify(multiArrayValue)" />
    </template>

    <!-- ComboOptions (free-form multi tags with autocomplete) -->
    <!-- Chips for committed tags (Enter / comma / blur add the typed value) + suggestions list -->
    <!-- below the field that pulls values already saved by other products for the same key. -->
    <template v-else-if="optionType === 'combooptions'">
      <InputChips
        v-model="multiArrayValue"
        :input-id="fieldId"
        class="w-full"
        separator=","
        :add-on-blur="true"
        :placeholder="
          _('ms3_combo_options_chips_placeholder') ||
          'Введите значение — Enter, запятая или клик вне поля добавят его'
        "
        @add="onChange"
        @remove="onChange"
        @keyup="onComboOptionsKeyup"
      />
      <div
        v-if="suggestions.length > 0"
        class="combo-options-suggestions"
        role="listbox"
        aria-label="Подсказки"
      >
        <span class="combo-options-suggestions-label">
          {{ _('ms3_combo_options_suggestions') || 'Подсказки' }}:
        </span>
        <button
          v-for="s in filteredSuggestions"
          :key="s"
          type="button"
          class="combo-options-suggestion"
          @click="addSuggestion(s)"
        >
          {{ s }}
        </button>
      </div>
      <input type="hidden" :name="fieldName" :value="JSON.stringify(multiArrayValue)" />
    </template>

    <!-- Datefield -->
    <template v-else-if="optionType === 'datefield'">
      <DatePicker
        v-model="value"
        :input-id="fieldId"
        date-format="yy-mm-dd"
        show-icon
        class="w-full"
        @date-select="onChange"
      />
      <input type="hidden" :name="fieldName" :value="formatDateForPost(value)" />
    </template>

    <!-- Unknown type fallback -->
    <div v-else class="option-unknown">
      <small>Неизвестный тип опции: {{ optionType }}</small>
      <InputText v-model="value" :name="fieldName" class="w-full" />
    </div>

    <small v-if="description" class="option-help">{{ description }}</small>
  </div>
</template>

<style scoped>
.option-field {
  margin-bottom: 1rem;
}

.option-label {
  display: block;
  font-weight: 600;
  margin-bottom: 0.35rem;
  font-size: 0.9rem;
}

.option-label-inline {
  font-weight: 600;
  font-size: 0.9rem;
  margin-left: 0.5rem;
  cursor: pointer;
}

.option-checkbox {
  display: flex;
  align-items: center;
}

.required {
  color: #e53e3e;
  margin-left: 0.15rem;
}

.option-help {
  display: block;
  color: #6b7280;
  margin-top: 0.25rem;
  font-size: 0.8rem;
}

.option-unknown {
  padding: 0.5rem;
  background: #fff7ed;
  border: 1px solid #fed7aa;
  border-radius: 0.25rem;
}

.color-swatch {
  display: inline-block;
  width: 1rem;
  height: 1rem;
  margin-right: 0.5rem;
  border-radius: 0.25rem;
  vertical-align: middle;
  border: 1px solid rgba(0, 0, 0, 0.1);
}

.w-full {
  width: 100%;
}

.combo-options-suggestions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
  align-items: center;
  margin-top: 0.4rem;
  padding: 0.35rem 0.5rem;
  background: var(--p-content-background, #f9fafb);
  border: 1px solid var(--p-content-border-color, #e5e7eb);
  border-radius: 0.25rem;
}

.combo-options-suggestions-label {
  font-size: 0.8rem;
  color: #6b7280;
  margin-right: 0.15rem;
}

.combo-options-suggestion {
  padding: 0.15rem 0.55rem;
  background: #fff;
  border: 1px solid var(--p-content-border-color, #d1d5db);
  border-radius: 999px;
  font-size: 0.8rem;
  cursor: pointer;
  transition:
    background-color 0.15s,
    border-color 0.15s;
}

.combo-options-suggestion:hover {
  background: var(--p-primary-color);
  border-color: var(--p-primary-color);
  color: #fff;
}
</style>
