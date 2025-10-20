<template>
  <div class="field-wrapper">
    <!-- Text field -->
    <InputText
      v-if="fieldConfig.xtype === 'textfield'"
      :id="fieldConfig.id"
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
      v-model="localValue"
      :placeholder="fieldConfig.placeholder"
      :disabled="disabled"
      :min="fieldConfig.props?.min"
      :max="fieldConfig.props?.max"
      :minFractionDigits="fieldConfig.props?.minFractionDigits"
      :maxFractionDigits="fieldConfig.props?.maxFractionDigits"
      :useGrouping="fieldConfig.props?.useGrouping ?? true"
      :locale="fieldConfig.props?.locale ?? 'ru-RU'"
      :mode="fieldConfig.props?.mode ?? 'decimal'"
      :currency="fieldConfig.props?.currency"
      :suffix="fieldConfig.props?.suffix"
      :prefix="fieldConfig.props?.prefix"
      @blur="handleBlur"
    />

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

    <!-- Unknown field type -->
    <div v-else class="unknown-field">
      <Message severity="warn">
        Неизвестный тип поля: {{ fieldConfig.xtype }}
      </Message>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, computed } from 'vue'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import InputSwitch from 'primevue/inputswitch'
import Textarea from 'primevue/textarea'
import Dropdown from 'primevue/dropdown'
import Calendar from 'primevue/calendar'
import ColorPicker from 'primevue/colorpicker'
import Message from 'primevue/message'

const props = defineProps({
  /**
   * Field configuration object
   * {
   *   id: string,
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

.unknown-field {
  padding: 0.5rem 0;
}
</style>
