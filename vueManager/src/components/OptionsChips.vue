<template>
  <div class="options-chips-wrapper" ref="wrapperRef">
    <div class="chips-container" @click="focusInput">
      <!-- Display selected chips -->
      <div
        v-for="(chip, index) in localValue"
        :key="index"
        class="chip-item"
      >
        <span class="chip-text">{{ chip }}</span>
        <span
          v-if="!disabled"
          class="chip-remove"
          @click.stop="removeChip(index)"
        >×</span>
      </div>

      <!-- Input field for adding new values -->
      <input
        ref="inputRef"
        v-model="searchQuery"
        :id="inputId"
        type="text"
        class="chip-input"
        :placeholder="localValue.length === 0 ? placeholder : ''"
        :disabled="disabled"
        @input="handleInput"
        @keydown.enter.prevent="addChipFromInput"
        @keydown.delete="handleBackspace"
        @focus="showSuggestions = true"
        @blur="handleBlur"
      />
    </div>

    <!-- Dropdown with suggestions -->
    <div
      v-if="showSuggestions && filteredOptions.length > 0"
      class="suggestions-panel"
    >
      <div
        v-for="(option, index) in filteredOptions"
        :key="index"
        class="suggestion-item"
        @mousedown.prevent="addChip(option)"
      >
        {{ option }}
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, onMounted, nextTick } from 'vue'
import request from '../request.js'

const props = defineProps({
  inputId: {
    type: String,
    default: null
  },
  optionKey: {
    type: String,
    required: true
  },
  modelValue: {
    type: Array,
    default: () => []
  },
  placeholder: {
    type: String,
    default: 'Add options...'
  },
  disabled: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['update:modelValue', 'change'])

// State
const loading = ref(false)
const filteredOptions = ref([])
const localValue = ref([...(props.modelValue || [])])
const searchQuery = ref('')
const showSuggestions = ref(false)
const wrapperRef = ref(null)
const inputRef = ref(null)

/**
 * Search options from API
 */
async function search(query) {
  if (!query || query.trim() === '') {
    filteredOptions.value = []
    return
  }

  loading.value = true

  try {
    const response = await request.get('/api/mgr/references/options', {
      key: props.optionKey,
      query: query.trim(),
      exclude: JSON.stringify(localValue.value)
    })

    if (response && response.values) {
      filteredOptions.value = response.values.map(v => v.value)
    } else {
      filteredOptions.value = []
    }
  } catch (error) {
    console.error('[OptionsChips] Error loading options:', error)
    filteredOptions.value = []
  } finally {
    loading.value = false
  }
}

/**
 * Handle input typing
 */
function handleInput() {
  const query = searchQuery.value
  if (query && query.length > 0) {
    search(query)
    showSuggestions.value = true
  } else {
    filteredOptions.value = []
    showSuggestions.value = false
  }
}

/**
 * Add chip from input field (Enter key)
 */
function addChipFromInput() {
  const value = searchQuery.value.trim()
  if (value && !localValue.value.includes(value)) {
    localValue.value.push(value)
    searchQuery.value = ''
    filteredOptions.value = []
    showSuggestions.value = false
    emitChange()
  }
}

/**
 * Add chip from suggestions
 */
function addChip(value) {
  if (value && !localValue.value.includes(value)) {
    localValue.value.push(value)
    searchQuery.value = ''
    filteredOptions.value = []
    showSuggestions.value = false
    emitChange()

    // Return focus to input
    nextTick(() => {
      inputRef.value?.focus()
    })
  }
}

/**
 * Remove chip
 */
function removeChip(index) {
  localValue.value.splice(index, 1)
  emitChange()
}

/**
 * Handle backspace when input is empty
 */
function handleBackspace(event) {
  if (searchQuery.value === '' && localValue.value.length > 0) {
    event.preventDefault()
    removeChip(localValue.value.length - 1)
  }
}

/**
 * Focus input when clicking on container
 */
function focusInput() {
  if (!props.disabled) {
    inputRef.value?.focus()
  }
}

/**
 * Handle blur event
 */
function handleBlur() {
  // Small delay to allow click on suggestion to process
  setTimeout(() => {
    showSuggestions.value = false

    // Add entered value on blur if something is typed
    const value = searchQuery.value.trim()
    if (value && !localValue.value.includes(value)) {
      localValue.value.push(value)
      searchQuery.value = ''
      emitChange()
    }
  }, 200)
}

/**
 * Emit changes
 */
function emitChange() {
  emit('update:modelValue', localValue.value)
  emit('change', {
    value: localValue.value
  })
}

// Watch for external changes
watch(() => props.modelValue, (newValue) => {
  localValue.value = [...(newValue || [])]
}, { deep: true })

// Setup label click handler on mount
onMounted(() => {
  if (props.inputId && wrapperRef.value) {
    const label = document.querySelector(`label[for="${props.inputId}"]`)
    if (label) {
      label.addEventListener('click', (e) => {
        e.preventDefault()
        focusInput()
      })
    }
  }
})
</script>

<style scoped>
.options-chips-wrapper {
  position: relative;
  width: 100%;
}

.chips-container {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem;
  border: 1px solid #ced4da;
  border-radius: 4px;
  background: #ffffff;
  min-height: 40px;
  cursor: text;
  transition: border-color 0.2s;
}

.chips-container:hover {
  border-color: #94a3b8;
}

.chips-container:focus-within {
  border-color: #3b82f6;
  outline: none;
  box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.25);
}

.chip-item {
  display: inline-flex;
  align-items: center;
  background: #3b82f6;
  color: #ffffff;
  padding: 0.25rem 0.5rem;
  border-radius: 3px;
  font-size: 0.875rem;
  white-space: nowrap;
}

.chip-text {
  margin-right: 0.25rem;
}

.chip-remove {
  font-size: 1.25rem;
  line-height: 1;
  cursor: pointer;
  margin-left: 0.375rem;
  opacity: 0.9;
  transition: all 0.2s;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.2);
  font-weight: 700;
  user-select: none;
}

.chip-remove:hover {
  opacity: 1;
  background: rgba(255, 255, 255, 0.4);
  transform: scale(1.15);
}

.chip-input {
  flex: 1;
  border: none;
  outline: none;
  padding: 0.25rem;
  font-size: 1rem;
  min-width: 120px;
  background: transparent;
}

.chip-input:disabled {
  background: #e9ecef;
  cursor: not-allowed;
}

.suggestions-panel {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  z-index: 1000;
  background: #ffffff;
  border: 1px solid #ced4da;
  border-radius: 4px;
  margin-top: 0.25rem;
  max-height: 200px;
  overflow-y: auto;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

.suggestion-item {
  padding: 0.5rem 0.75rem;
  cursor: pointer;
  transition: background-color 0.2s;
}

.suggestion-item:hover {
  background: #f1f5f9;
}
</style>
