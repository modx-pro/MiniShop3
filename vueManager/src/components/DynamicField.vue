<template>
  <div class="field-wrapper">
    <!-- Text field -->
    <InputText
      v-if="fieldConfig.xtype === 'textfield'"
      :id="fieldConfig.id"
      :name="fieldConfig.name"
      v-model="localValue"
      :placeholder="fieldConfig.placeholder"
      :disabled="disabled"
      :maxlength="fieldConfig.props?.maxlength"
      @blur="handleBlur"
    />

    <!-- Number field -->
    <InputNumber
      v-else-if="fieldConfig.xtype === 'numberfield'"
      :id="fieldConfig.id"
      :name="fieldConfig.name"
      v-model="localValue"
      :placeholder="fieldConfig.placeholder"
      :disabled="disabled"
      :min="fieldConfig.props?.min"
      :max="fieldConfig.props?.max"
      :minFractionDigits="fieldConfig.props?.minFractionDigits ?? 0"
      :maxFractionDigits="fieldConfig.props?.maxFractionDigits ?? 2"
      :useGrouping="false"
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
        :inputId="fieldConfig.name"
        v-model="localValue"
        :disabled="disabled"
        :binary="true"
        :trueValue="fieldConfig.inputValue ?? 1"
        :falseValue="0"
        @change="handleBlur"
      />
      <!-- Hidden field to pass correct value to form -->
      <input
        type="hidden"
        :name="fieldConfig.name"
        :value="localValue"
      />
    </div>

    <!-- Switch / Toggle -->
    <InputSwitch
      v-else-if="fieldConfig.xtype === 'switch'"
      :id="fieldConfig.id"
      v-model="localValue"
      :disabled="disabled"
      :trueValue="fieldConfig.props?.trueValue ?? true"
      :falseValue="fieldConfig.props?.falseValue ?? false"
      @change="handleBlur"
    />

    <!-- Textarea -->
    <Textarea
      v-else-if="fieldConfig.xtype === 'textarea'"
      :id="fieldConfig.id"
      :name="fieldConfig.name"
      v-model="localValue"
      :placeholder="fieldConfig.placeholder"
      :disabled="disabled"
      :rows="fieldConfig.props?.rows ?? 3"
      :autoResize="fieldConfig.props?.autoResize ?? false"
      @blur="handleBlur"
    />

    <!-- Combobox / Select -->
    <Dropdown
      v-else-if="fieldConfig.xtype === 'combobox'"
      :id="fieldConfig.id"
      v-model="localValue"
      :options="fieldConfig.props?.options ?? []"
      :optionLabel="fieldConfig.props?.optionLabel ?? 'label'"
      :optionValue="fieldConfig.props?.optionValue ?? 'value'"
      :placeholder="fieldConfig.placeholder"
      :disabled="disabled"
      :showClear="fieldConfig.props?.showClear ?? true"
      @change="handleBlur"
    />

    <!-- Date picker -->
    <Calendar
      v-else-if="fieldConfig.xtype === 'datefield'"
      :id="fieldConfig.id"
      v-model="localValue"
      :placeholder="fieldConfig.placeholder"
      :disabled="disabled"
      :showIcon="fieldConfig.props?.showIcon ?? true"
      :dateFormat="fieldConfig.props?.dateFormat ?? 'dd.mm.yy'"
      @blur="handleBlur"
    />

    <!-- Color picker -->
    <ColorPicker
      v-else-if="fieldConfig.xtype === 'colorpicker'"
      :id="fieldConfig.id"
      v-model="localValue"
      :disabled="disabled"
      :inline="fieldConfig.props?.inline ?? false"
      @change="handleBlur"
    />

    <!-- Vendor combo (ms3-combo-vendor) -->
    <template v-else-if="fieldConfig.xtype === 'ms3-combo-vendor'">
      <VendorCombo
        :inputId="fieldConfig.name"
        v-model="localValue"
        :placeholder="fieldConfig.placeholder || 'Select vendor'"
        :disabled="disabled"
        @change="handleBlur"
      />
      <!-- Hidden field to send value to ExtJS form -->
      <input
        type="hidden"
        :name="fieldConfig.name"
        :value="localValue || ''"
      />
    </template>

    <!-- Autocomplete combo (ms3-combo-autocomplete) -->
    <template v-else-if="fieldConfig.xtype === 'ms3-combo-autocomplete'">
      <AutocompleteCombo
        :inputId="fieldConfig.name"
        :fieldName="fieldConfig.name"
        v-model="localValue"
        :placeholder="fieldConfig.placeholder || 'Start typing...'"
        :disabled="disabled"
        @change="handleBlur"
      />
      <!-- Hidden field to send value to ExtJS form -->
      <input
        type="hidden"
        :name="fieldConfig.name"
        :value="localValue || ''"
      />
    </template>

    <!-- Options chips (ms3-combo-options) -->
    <template v-else-if="fieldConfig.xtype === 'ms3-combo-options'">
      <OptionsChips
        :inputId="fieldConfig.name"
        :optionKey="fieldConfig.name"
        v-model="localValue"
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
    </template>

    <!-- Dropdown select (ms3-combo-select) -->
    <template v-else-if="fieldConfig.xtype === 'ms3-combo-select'">
      <Dropdown
        :inputId="fieldConfig.name"
        v-model="localValue"
        :options="selectOptions"
        optionLabel="label"
        optionValue="value"
        :placeholder="fieldConfig.placeholder || 'Select...'"
        :disabled="disabled"
        :showClear="true"
        class="w-full"
        @change="handleBlur"
      />
      <input
        type="hidden"
        :name="fieldConfig.name"
        :value="localValue || ''"
      />
    </template>

    <!-- Other ExtJS combo fields (ms3-combo-category, etc) -->
    <!-- For now, we display them as simple text info since editing happens in ExtJS form -->
    <div v-else-if="isExtJSComboField" class="extjs-combo-info">
      <Message severity="info">
        {{ getExtJSComboLabel(fieldConfig.xtype) }}
      </Message>
    </div>

    <!-- Unknown field type -->
    <div v-else class="unknown-field">
      <Message severity="warn">
        Unknown field type: {{ fieldConfig.xtype }}
      </Message>
    </div>

    <!-- Hidden field for complex types (combobox, datefield, colorpicker, chips, multiselect) -->
    <!-- These fields require JSON serialization to pass to ExtJS form -->
    <input
      v-if="isComplexField"
      type="hidden"
      :name="fieldConfig.name"
      :value="serializedValue"
    />
  </div>
</template>

<script setup>
import { ref, watch, computed } from 'vue'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Checkbox from 'primevue/checkbox'
import InputSwitch from 'primevue/inputswitch'
import Textarea from 'primevue/textarea'
import Dropdown from 'primevue/dropdown'
import Calendar from 'primevue/calendar'
import ColorPicker from 'primevue/colorpicker'
import Message from 'primevue/message'
import VendorCombo from './VendorCombo.vue'
import AutocompleteCombo from './AutocompleteCombo.vue'
import OptionsChips from './OptionsChips.vue'

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
    required: true
  },

  /**
   * Field value (v-model)
   */
  modelValue: {
    type: [String, Number, Boolean, Date, Array, Object],
    default: null
  },

  /**
   * Is field disabled
   */
  disabled: {
    type: Boolean,
    default: false
  }
})

/**
 * Determine if field is simple type (doesn't require hidden input)
 */
const isSimpleField = computed(() => {
  const simpleTypes = ['textfield', 'numberfield', 'textarea', 'switch', 'checkbox', 'xcheckbox']
  return simpleTypes.includes(props.fieldConfig.xtype)
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

/**
 * Parse select_options string into array for ms3-combo-select
 * Format: "value1==label1\nvalue2==label2" or just "value1\nvalue2"
 * Note: select_options may be in fieldConfig.config.select_options or fieldConfig.select_options
 * depending on how the config was merged in PHP
 */
const selectOptions = computed(() => {
  const optionsString = props.fieldConfig.config?.select_options
    || props.fieldConfig.select_options
    || ''
  if (!optionsString) return []

  return optionsString.split('\n')
    .filter(line => line.trim())
    .map(line => {
      const parts = line.split('==')
      if (parts.length >= 2) {
        return { value: parts[0].trim(), label: parts.slice(1).join('==').trim() }
      }
      return { value: line.trim(), label: line.trim() }
    })
})

/**
 * Get ExtJS combo field description
 */
const getExtJSComboLabel = (xtype) => {
  const labels = {
    'ms3-combo-vendor': 'Vendor selection (ExtJS combo)',
    'ms3-combo-category': 'Category selection (ExtJS combo)',
    'ms3-combo-user': 'User selection (ExtJS combo)',
    'ms3-combo-customer': 'Customer selection (ExtJS combo)',
    'ms3-combo-source': 'Media source selection (ExtJS combo)',
    'ms3-combo-options': 'Product options (ExtJS combo)',
    'ms3-combo-autocomplete': 'Autocomplete (ExtJS combo)',
    'ms3-combo-select': 'Dropdown list'
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
const localValue = ref(props.modelValue)

// Watch for external changes
watch(() => props.modelValue, (newValue) => {
  localValue.value = newValue
})

// Watch for local changes and emit to parent
watch(localValue, (newValue) => {
  emit('update:modelValue', newValue)
})

// Handle blur event
const handleBlur = () => {
  emit('blur', {
    fieldId: props.fieldConfig.id,
    value: localValue.value
  })
}
</script>

<style scoped>
.field-wrapper {
  width: 100%;
}

.field-wrapper :deep(.w-full) {
  width: 100%;
}

.unknown-field {
  padding: 0.5rem 0;
}
</style>
