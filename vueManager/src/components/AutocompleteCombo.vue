<template>
  <div class="autocomplete-combo-wrapper" ref="wrapperRef">
    <AutoComplete
      :inputId="inputId"
      v-model="localValue"
      :suggestions="filteredValues"
      :placeholder="placeholder"
      :disabled="disabled"
      :loading="loading"
      :completeOnFocus="true"
      :forceSelection="false"
      class="w-full"
      @complete="search"
      @change="handleChange"
    />
  </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'
import AutoComplete from 'primevue/autocomplete'
import request from '../request.js'

const props = defineProps({
  /**
   * Input ID for label association
   */
  inputId: {
    type: String,
    default: null
  },

  /**
   * Field name (column name in database)
   */
  fieldName: {
    type: String,
    required: true
  },

  /**
   * Current value (v-model)
   */
  modelValue: {
    type: [String, Number, null],
    default: null
  },

  /**
   * Placeholder text
   */
  placeholder: {
    type: String,
    default: 'Start typing...'
  },

  /**
   * Is field disabled
   */
  disabled: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['update:modelValue', 'change'])

// State
const loading = ref(false)
const filteredValues = ref([])
const localValue = ref(props.modelValue)
const wrapperRef = ref(null)

/**
 * Search autocomplete values
 */
async function search(event) {
  loading.value = true

  try {
    const query = event.query || ''
    const response = await request.get('/api/mgr/references/autocomplete', {
      name: props.fieldName,
      query: query
    })

    if (response && response.values) {
      // AutoComplete expects array of simple values (strings)
      filteredValues.value = response.values.map(v => v.value)
    } else {
      console.error('[AutocompleteCombo] Invalid response:', response)
      filteredValues.value = []
    }
  } catch (error) {
    console.error('[AutocompleteCombo] Error loading autocomplete:', error)
    filteredValues.value = []
  } finally {
    loading.value = false
  }
}

/**
 * Handle value change
 */
function handleChange() {
  emit('update:modelValue', localValue.value)
  emit('change', {
    value: localValue.value
  })
}

// Watch for external changes
watch(() => props.modelValue, (newValue) => {
  localValue.value = newValue
})

// Watch for local changes
watch(localValue, (newValue) => {
  emit('update:modelValue', newValue)
})

// Setup label click handler on mount
onMounted(() => {
  // Setup label click handler if inputId is provided
  if (props.inputId && wrapperRef.value) {
    const label = document.querySelector(`label[for="${props.inputId}"]`)
    if (label) {
      label.addEventListener('click', (e) => {
        const autocompleteInput = wrapperRef.value.querySelector('.p-autocomplete-input')
        if (autocompleteInput && !props.disabled) {
          e.preventDefault()
          autocompleteInput.focus()
        }
      })
    }
  }
})
</script>

<style scoped>
.autocomplete-combo-wrapper {
  width: 100%;
}

.w-full {
  width: 100%;
}
</style>

<style>
/* Global styles for autocomplete panel */
.autocomplete-combo-wrapper .p-autocomplete-panel {
  min-width: 300px;
  max-width: 500px;
}

.autocomplete-combo-wrapper .p-autocomplete-input {
  width: 100% !important;
}
</style>
