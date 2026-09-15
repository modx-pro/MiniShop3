<script setup>
import { useLexicon } from '@vuetools/useLexicon'
import { AutoComplete, Checkbox, DatePicker, InputNumber, InputText, MultiSelect, Select, Textarea } from 'primevue'
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
 * Distinct values already saved for this option key by other products.
 * Fed into AutoComplete suggestions (InputChips is deprecated in PrimeVue 4
 * and missing from the VueTools 1.1.2 barrel — use multiple + typeahead=false).
 */
const suggestions = ref([])
const suggestionsLoaded = ref(false)
const comboOptionsSuggestions = ref([])

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

async function onComboOptionsComplete(event) {
  await loadComboOptionsSuggestions()
  const query = String(event?.query ?? '')
    .trim()
    .toLowerCase()
  const picked = new Set(multiArrayValue.value)
  comboOptionsSuggestions.value = suggestions.value
    .filter(s => !picked.has(s))
    .filter(s => query === '' || String(s).toLowerCase().includes(query))
    .slice(0, 20)
}

function onComboOptionsChange() {
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

    <!-- ComboOptions: free-form multi tags via AutoComplete (VueTools barrel has no InputChips) -->
    <template v-else-if="optionType === 'combooptions'">
      <AutoComplete
        v-model="multiArrayValue"
        :input-id="fieldId"
        multiple
        :typeahead="false"
        :suggestions="comboOptionsSuggestions"
        class="w-full"
        :placeholder="
          _('ms3_combo_options_chips_placeholder') ||
          'Введите значение — Enter, запятая или клик вне поля добавят его'
        "
        @complete="onComboOptionsComplete"
        @item-select="onComboOptionsChange"
        @value-change="onComboOptionsChange"
      />
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
</style>
