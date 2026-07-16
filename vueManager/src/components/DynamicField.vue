<template>
  <div class="field-wrapper">
    <!-- Text field -->
    <InputText
      v-if="fieldConfig.xtype === 'textfield'"
      :id="fieldHtmlId"
      v-model="localValue"
      class="w-full"
      :name="fieldConfig.name"
      :placeholder="fieldConfig.placeholder"
      :disabled="disabled"
      :maxlength="fieldConfig.props?.maxlength"
      @blur="handleBlur"
    />

    <!-- Number field -->
    <InputNumber
      v-else-if="fieldConfig.xtype === 'numberfield'"
      v-model="localValue"
      :input-id="fieldHtmlId"
      class="w-full"
      fluid
      :name="fieldConfig.name"
      :placeholder="fieldConfig.placeholder"
      :disabled="disabled"
      :min="fieldConfig.props?.min"
      :max="fieldConfig.props?.max"
      :min-fraction-digits="fieldConfig.props?.minFractionDigits ?? 0"
      :max-fraction-digits="fieldConfig.props?.maxFractionDigits ?? 2"
      :use-grouping="false"
      :locale="fieldConfig.props?.locale ?? 'en-US'"
      :mode="fieldConfig.props?.mode ?? 'decimal'"
      :currency="fieldConfig.props?.currency"
      :suffix="fieldConfig.props?.suffix"
      :prefix="fieldConfig.props?.prefix"
      @blur="handleBlur"
    />

    <!-- Checkbox (ExtJS xcheckbox) -->
    <div v-else-if="fieldConfig.xtype === 'xcheckbox' || fieldConfig.xtype === 'checkbox'">
      <Checkbox
        v-model="localValue"
        :input-id="fieldHtmlId"
        :disabled="disabled"
        :binary="true"
        :true-value="fieldConfig.inputValue ?? 1"
        :false-value="0"
        @change="handleBlur"
      />
      <!-- Hidden field to pass correct value to form -->
      <input type="hidden" :name="fieldConfig.name" :value="localValue" />
    </div>

    <!-- Switch / Toggle -->
    <ToggleSwitch
      v-else-if="fieldConfig.xtype === 'switch'"
      v-model="localValue"
      :input-id="fieldHtmlId"
      :disabled="disabled"
      :true-value="fieldConfig.props?.trueValue ?? true"
      :false-value="fieldConfig.props?.falseValue ?? false"
      @change="handleBlur"
    />

    <!-- Textarea -->
    <Textarea
      v-else-if="fieldConfig.xtype === 'textarea'"
      :id="fieldHtmlId"
      v-model="localValue"
      class="w-full"
      :name="fieldConfig.name"
      :placeholder="fieldConfig.placeholder"
      :disabled="disabled"
      :rows="fieldConfig.props?.rows ?? 3"
      :auto-resize="fieldConfig.props?.autoResize ?? false"
      @blur="handleBlur"
    />

    <!-- Combobox / Select -->
    <Select
      v-else-if="fieldConfig.xtype === 'combobox'"
      :id="fieldHtmlId"
      v-model="localValue"
      class="w-full"
      :options="fieldConfig.props?.options ?? []"
      :option-label="fieldConfig.props?.optionLabel ?? 'label'"
      :option-value="fieldConfig.props?.optionValue ?? 'value'"
      :placeholder="fieldConfig.placeholder"
      :disabled="disabled"
      :show-clear="fieldConfig.props?.showClear ?? true"
      @change="handleBlur"
    />

    <!-- Date picker -->
    <DatePicker
      v-else-if="fieldConfig.xtype === 'datefield'"
      v-model="localValue"
      class="w-full"
      :input-id="fieldHtmlId"
      :placeholder="fieldConfig.placeholder"
      :disabled="disabled"
      show-icon
      fluid
      icon-display="input"
      :date-format="fieldConfig.props?.dateFormat ?? 'dd.mm.yy'"
      @blur="handleBlur"
    />

    <!-- Color picker -->
    <ColorPicker
      v-else-if="fieldConfig.xtype === 'colorpicker'"
      :id="fieldHtmlId"
      v-model="localValue"
      :disabled="disabled"
      :inline="fieldConfig.props?.inline ?? false"
      @change="handleBlur"
    />

    <!-- Vendor combo (ms3-combo-vendor) -->
    <template v-else-if="fieldConfig.xtype === 'ms3-combo-vendor'">
      <VendorCombo
        v-model="localValue"
        :input-id="fieldHtmlId"
        :placeholder="fieldConfig.placeholder || 'Select vendor'"
        :disabled="disabled"
        @change="handleBlur"
      />
      <!-- Hidden field to send value to ExtJS form -->
      <input type="hidden" :name="fieldConfig.name" :value="localValue || ''" />
    </template>

    <!-- Autocomplete combo (ms3-combo-autocomplete) -->
    <template v-else-if="fieldConfig.xtype === 'ms3-combo-autocomplete'">
      <AutocompleteCombo
        v-model="localValue"
        :input-id="fieldHtmlId"
        :field-name="fieldConfig.name"
        :placeholder="fieldConfig.placeholder || 'Start typing...'"
        :disabled="disabled"
        @change="handleBlur"
      />
      <!-- Hidden field to send value to ExtJS form -->
      <input type="hidden" :name="fieldConfig.name" :value="localValue || ''" />
    </template>

    <!-- Options chips (ms3-combo-options) -->
    <template v-else-if="fieldConfig.xtype === 'ms3-combo-options'">
      <OptionsChips
        v-model="localValue"
        :input-id="fieldHtmlId"
        :option-key="fieldConfig.name"
        :placeholder="fieldConfig.placeholder || 'Add options...'"
        :disabled="disabled"
        @change="handleBlur"
      />
      <!-- Hidden fields to send array values to ExtJS form -->
      <template v-if="Array.isArray(localValue) && localValue.length > 0">
        <input
          v-for="(val, index) in localValue"
          :key="index"
          type="hidden"
          :name="`${fieldConfig.name}[]`"
          :value="val"
        />
      </template>
      <!-- Empty array must still POST so ProductDataPayloadTrait clears the field (#324) -->
      <input
        v-else
        type="hidden"
        :name="`${fieldConfig.name}[]`"
        value=""
      />
    </template>

    <!-- Dropdown select (ms3-combo-select) -->
    <template v-else-if="fieldConfig.xtype === 'ms3-combo-select'">
      <Select
        v-model="localValue"
        :input-id="fieldHtmlId"
        :options="selectOptions"
        option-label="label"
        option-value="value"
        :placeholder="fieldConfig.placeholder || 'Select...'"
        :disabled="disabled"
        :show-clear="true"
        class="w-full"
        @change="handleBlur"
      />
      <input type="hidden" :name="fieldConfig.name" :value="localValue || ''" />
    </template>

    <!-- Repeater (ms3-repeater) -->
    <template v-else-if="fieldConfig.xtype === 'ms3-repeater'">
      <RepeaterField v-model="localValue" :config="repeaterConfig" :disabled="disabled" />
      <!--
        Hidden input bridges Vue state to the legacy MODX Resource form POST.
        Without it the Resource\Update processor (and ProductDataPayloadTrait from #298)
        never sees `repeater` in $_POST, and prepareObject() normalises the in-memory
        null to [] on save — silent loss of user input.
      -->
      <input
        type="hidden"
        :name="fieldConfig.name"
        :value="serializeRepeaterForPost(localValue)"
      />
    </template>

    <!-- Key-Value (ms3-key-value) -->
    <template v-else-if="fieldConfig.xtype === 'ms3-key-value'">
      <KeyValueField v-model="localValue" :config="keyValueConfig" :disabled="disabled" />
      <!-- Hidden input bridges Vue state to legacy MODX Resource form POST (#298). -->
      <input
        type="hidden"
        :name="fieldConfig.name"
        :value="serializeKeyValueForPost(localValue)"
      />
    </template>

    <!-- Other ExtJS combo fields (ms3-combo-category, etc) -->
    <!-- For now, we display them as simple text info since editing happens in ExtJS form -->
    <div v-else-if="isExtJSComboField" class="extjs-combo-info">
      <Message severity="info">
        {{ getExtJSComboLabel(fieldConfig.xtype) }}
      </Message>
    </div>

    <!-- FileBrowser for image/file fields -->
    <FileBrowser
      v-else-if="isFileBrowserXtype"
      v-model="localValue"
      :placeholder="fieldConfig.placeholder || ''"
      :allowed-file-types="fieldConfig.config?.allowedTypes || fieldConfig.allowedFileTypes || ''"
      :disabled="disabled"
    />

    <!-- Unknown field type -->
    <div v-else class="unknown-field">
      <Message severity="warn"> Unknown field type: {{ fieldConfig.xtype }} </Message>
    </div>

    <!-- Hidden field for complex types (combobox, datefield, colorpicker, chips, multiselect) -->
    <!-- These fields require JSON serialization to pass to ExtJS form -->
    <input v-if="isComplexField" type="hidden" :name="fieldConfig.name" :value="serializedValue" />
  </div>
</template>

<script setup>
import Checkbox from 'primevue/checkbox'
import ColorPicker from 'primevue/colorpicker'
import DatePicker from 'primevue/datepicker'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import ToggleSwitch from 'primevue/toggleswitch'
import { computed, ref, watch } from 'vue'

import { getKeyValueConfigFromField, serializeKeyValueForPost } from '../utils/keyValueField.js'
import { getRepeaterConfigFromField } from '../utils/repeaterField.js'
import { parseStructuredExtraFieldValue } from '../utils/structuredExtraField.js'
import AutocompleteCombo from './AutocompleteCombo.vue'
import FileBrowser from './FileBrowser.vue'
import KeyValueField from './KeyValueField.vue'
import OptionsChips from './OptionsChips.vue'
import RepeaterField from './RepeaterField.vue'
import VendorCombo from './VendorCombo.vue'

const props = defineProps({
  /**
   * Field configuration object
   * {
   *   id: string,
   *   name: string,
   *   xtype: string,
   *   label: string,
   *   description: string,
   *   placeholder: string,
   *   required: boolean,
   *   visible: boolean,
   *   props: object
   * }
   */
  fieldConfig: {
    type: Object,
    required: true,
  },

  /**
   * Field value (v-model)
   */
  modelValue: {
    type: [String, Number, Boolean, Date, Array, Object],
    default: null,
  },

  /**
   * Is field disabled
   */
  disabled: {
    type: Boolean,
    default: false,
  },

  /**
   * Prefix for generating HTML id (for label association).
   * Each form should pass a unique prefix to avoid id collisions.
   */
  idPrefix: {
    type: String,
    default: 'df',
  },
})

/**
 * Stable HTML id for label association (for/id).
 * Prefer explicit fieldConfig.htmlId, otherwise generate from prefix + name.
 */
const fieldHtmlId = computed(() => {
  return props.fieldConfig.htmlId || `${props.idPrefix}-field-${props.fieldConfig.name}`
})

/**
 * Determine if field should use FileBrowser (image/file fields)
 */
const isFileBrowserXtype = computed(() => {
  const xtype = props.fieldConfig.xtype?.toLowerCase()
  return ['file', 'image', 'filebrowser', 'imagebrowser'].includes(xtype)
})

/**
 * Determine if field is complex type (requires hidden field with JSON)
 */
const isComplexField = computed(() => {
  const complexTypes = ['combobox', 'datefield', 'colorpicker', 'chips', 'multiselect']
  return complexTypes.includes(props.fieldConfig.xtype)
})

/**
 * Determine if field is ExtJS combo (ms3-combo-*)
 * Excludes ms3-combo-select which is handled separately
 */
const isExtJSComboField = computed(() => {
  if (!props.fieldConfig.xtype) return false
  if (props.fieldConfig.xtype === 'ms3-combo-select') return false
  if (props.fieldConfig.xtype === 'ms3-combo-vendor') return false
  if (props.fieldConfig.xtype === 'ms3-combo-autocomplete') return false
  if (props.fieldConfig.xtype === 'ms3-combo-options') return false
  return props.fieldConfig.xtype.startsWith('ms3-combo-')
})

const selectOptions = computed(() => {
  const optionsString =
    props.fieldConfig.config?.select_options || props.fieldConfig.select_options || ''
  if (!optionsString) return []

  return optionsString
    .split('\n')
    .filter(line => line.trim())
    .map(line => {
      const parts = line.split('==')
      if (parts.length >= 2) {
        return { value: parts[0].trim(), label: parts.slice(1).join('==').trim() }
      }
      return { value: line.trim(), label: line.trim() }
    })
})

const repeaterConfig = computed(() => getRepeaterConfigFromField(props.fieldConfig))
const keyValueConfig = computed(() => getKeyValueConfigFromField(props.fieldConfig))

function normalizeIncomingValue(value) {
  return parseStructuredExtraFieldValue(props.fieldConfig.xtype, value)
}

/**
 * Serialise the repeater value for the hidden legacy-form input.
 * RepeaterField emits an array; the processor expects JSON string or array.
 * Empty/missing → `[]` so xPDO json field stays a valid array, not null.
 */
function serializeRepeaterForPost(value) {
  if (!Array.isArray(value)) {
    return '[]'
  }
  try {
    return JSON.stringify(value)
  } catch {
    return '[]'
  }
}

/**
 * Get ExtJS combo field description
 */
const getExtJSComboLabel = xtype => {
  const labels = {
    'ms3-combo-vendor': 'Vendor selection (ExtJS combo)',
    'ms3-combo-category': 'Category selection (ExtJS combo)',
    'ms3-combo-user': 'User selection (ExtJS combo)',
    'ms3-combo-customer': 'Customer selection (ExtJS combo)',
    'ms3-combo-source': 'Media source selection (ExtJS combo)',
    'ms3-combo-options': 'Product options (ExtJS combo)',
    'ms3-combo-autocomplete': 'Autocomplete (ExtJS combo)',
    'ms3-combo-select': 'Dropdown list',
  }

  return labels[xtype] || `ExtJS widget: ${xtype}`
}

/**
 * Serialize value for hidden field
 */
const serializedValue = computed(() => {
  if (localValue.value === null || localValue.value === undefined) {
    return ''
  }

  // For complex objects and arrays - JSON
  if (typeof localValue.value === 'object') {
    return JSON.stringify(localValue.value)
  }

  // For simple values - as is
  return String(localValue.value)
})

const emit = defineEmits(['update:modelValue', 'blur'])

// Local value for v-model
const localValue = ref(normalizeIncomingValue(props.modelValue))

// Watch for external changes
watch(
  () => props.modelValue,
  newValue => {
    localValue.value = normalizeIncomingValue(newValue)
  }
)

// Watch for local changes and emit to parent
watch(localValue, newValue => {
  emit('update:modelValue', newValue)
})

// Handle blur event
const handleBlur = () => {
  emit('blur', {
    fieldId: props.fieldConfig.id,
    value: localValue.value,
  })
}
</script>

<style scoped>
.field-wrapper {
  width: 100%;
}

.unknown-field {
  padding: 0.5rem 0;
}
</style>
