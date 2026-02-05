<template>
  <div class="vendor-combo-wrapper" ref="wrapperRef">
    <Dropdown
      :inputId="inputId"
      v-model="localValue"
      :options="vendors"
      optionLabel="name"
      optionValue="id"
      :placeholder="placeholder"
      :disabled="disabled"
      :loading="loading"
      :showClear="showClear"
      :filter="enableFilter"
      filterPlaceholder="Search vendor..."
      :emptyMessage="emptyMessage"
      :emptyFilterMessage="emptyFilterMessage"
      class="w-full"
      @change="handleChange"
    >
      <template #empty>
        <div class="p-dropdown-empty-message">
          {{ emptyMessage }}
        </div>
      </template>
    </Dropdown>
  </div>
</template>

<script setup>
import { ref, watch, onMounted, computed } from 'vue'
import Dropdown from 'primevue/dropdown'
import request from '../request.js'

const props = defineProps({
  /**
   * Input ID for label association
   */
  inputId: {
    type: String,
    default: null,
  },

  /**
   * Current vendor ID (v-model)
   */
  modelValue: {
    type: [Number, String, null],
    default: null,
  },

  /**
   * Placeholder text
   */
  placeholder: {
    type: String,
    default: 'Select vendor',
  },

  /**
   * Is field disabled
   */
  disabled: {
    type: Boolean,
    default: false,
  },

  /**
   * Show clear button
   */
  showClear: {
    type: Boolean,
    default: true,
  },

  /**
   * Enable filter/search
   */
  enableFilter: {
    type: Boolean,
    default: true,
  },
})

const emit = defineEmits(['update:modelValue', 'change'])

// State
const loading = ref(false)
const vendors = ref([])
const localValue = ref(props.modelValue)
const wrapperRef = ref(null)

// Computed
const emptyMessage = computed(() => {
  return vendors.value.length === 0
    ? 'No vendors found. Add a vendor in component settings.'
    : 'No results'
})

const emptyFilterMessage = computed(() => {
  return 'Vendor not found'
})

/**
 * Load vendors from API
 */
async function loadVendors() {
  loading.value = true

  try {
    const response = await request.get('/api/mgr/references/vendors')

    if (response && response.vendors) {
      vendors.value = response.vendors
    } else {
      console.error('[VendorCombo] Invalid response structure:', response)
      vendors.value = []
    }
  } catch (error) {
    console.error('[VendorCombo] Error loading vendors:', error)
    vendors.value = []
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
    value: localValue.value,
    vendor: vendors.value.find(v => v.id === localValue.value),
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

// Load vendors on mount and setup label click handler
onMounted(() => {
  loadVendors()

  // Setup label click handler if inputId is provided
  if (props.inputId && wrapperRef.value) {
    // Find the label element
    const label = document.querySelector(`label[for="${props.inputId}"]`)
    if (label) {
      label.addEventListener('click', (e) => {
        // Find the dropdown button inside wrapper
        const dropdownButton = wrapperRef.value.querySelector('.p-dropdown')
        if (dropdownButton && !props.disabled) {
          e.preventDefault()
          dropdownButton.click()
        }
      })
    }
  }
})
</script>

<style scoped>
.vendor-combo-wrapper {
  width: 100%;
}

.w-full {
  width: 100%;
}
</style>

<style>
/* Global styles for dropdown empty message */
.vendor-combo-wrapper .p-dropdown-panel {
  min-width: 400px !important;
  max-width: 500px !important;
}

.vendor-combo-wrapper .p-dropdown-empty-message,
.p-dropdown-panel .p-dropdown-empty-message {
  padding: 0.75rem 1rem;
  color: #6c757d;
  font-size: 0.875rem;
  white-space: normal !important;
  word-wrap: break-word !important;
  overflow-wrap: break-word !important;
  line-height: 1.5;
  max-width: 100%;
}

/* Search line in dropdown */
.vendor-combo-wrapper .p-dropdown-panel .p-dropdown-filter-container {
  width: 100% !important;
  padding: 0.5rem;
  box-sizing: border-box;
}

.vendor-combo-wrapper .p-dropdown-panel .p-dropdown-filter {
  width: 100% !important;
  max-width: 100% !important;
  box-sizing: border-box;
}

/* For dropdown panel */
.p-dropdown-panel .p-dropdown-items-wrapper {
  overflow-wrap: break-word;
  max-width: 100%;
}

.p-dropdown-panel .p-dropdown-items .p-dropdown-empty-message {
  white-space: normal !important;
  word-wrap: break-word !important;
}

/* List items container */
.vendor-combo-wrapper .p-dropdown-panel .p-dropdown-items {
  max-width: 100%;
}
</style>
