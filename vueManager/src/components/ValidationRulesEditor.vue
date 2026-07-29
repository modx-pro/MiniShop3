<script setup>
import { useLexicon } from '@vuetools/useLexicon'
import Button from 'primevue/button'
import Chip from 'primevue/chip'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import ToggleSwitch from 'primevue/toggleswitch'
import { computed, onMounted, ref, watch } from 'vue'

import request from '../request.js'

const { _ } = useLexicon()

/** Technical msOrder columns — not offered as checkout fields for delivery rules */
const ORDER_FIELD_BLOCKLIST = new Set([
  'id',
  'user_id',
  'customer_id',
  'token',
  'uuid',
  'createdon',
  'updatedon',
  'cost',
  'cart_cost',
  'delivery_cost',
  'weight',
  'status_id',
  'delivery_id',
  'payment_id',
  'context',
  'properties',
  'num',
])

/** Technical msOrderAddress columns */
const ADDRESS_FIELD_BLOCKLIST = new Set(['id', 'order_id', 'createdon', 'updatedon', 'properties'])

const FIELD_GROUP_SORT = { order: 0, address: 1 }

function blocklistForGroup(group) {
  return group === 'order' ? ORDER_FIELD_BLOCKLIST : ADDRESS_FIELD_BLOCKLIST
}

// Editor mode: visual or json
const isJsonMode = ref(false)
const jsonText = ref('')
const jsonError = ref('')

const props = defineProps({
  modelValue: {
    type: [String, Object],
    default: '',
  },
})

const emit = defineEmits(['update:modelValue'])

// Available fields for validation (order + address)
const fieldDefinitions = [
  // Order fields
  { name: 'order_comment', group: 'order' },

  // Address fields
  { name: 'first_name', group: 'address' },
  { name: 'last_name', group: 'address' },
  { name: 'phone', group: 'address' },
  { name: 'email', group: 'address' },
  { name: 'country', group: 'address' },
  { name: 'index', group: 'address' },
  { name: 'region', group: 'address' },
  { name: 'city', group: 'address' },
  { name: 'metro', group: 'address' },
  { name: 'street', group: 'address' },
  { name: 'building', group: 'address' },
  { name: 'entrance', group: 'address' },
  { name: 'floor', group: 'address' },
  { name: 'room', group: 'address' },
  { name: 'comment', group: 'address' },
  { name: 'text_address', group: 'address' },
]

/** Extra definitions from model fields + Object Extension (filled on mount) */
const extensionFieldDefinitions = ref([])

// Pipe validation rules (MiniShop3 ValidationService, Rakit-compatible syntax)
const ruleDefinitions = [
  // Simple rules (no parameters)
  { name: 'required', hasParam: false },
  { name: 'nullable', hasParam: false },
  { name: 'present', hasParam: false },
  { name: 'accepted', hasParam: false },
  { name: 'email', hasParam: false },
  { name: 'url', hasParam: false },
  { name: 'ip', hasParam: false },
  { name: 'ipv4', hasParam: false },
  { name: 'ipv6', hasParam: false },
  { name: 'numeric', hasParam: false },
  { name: 'integer', hasParam: false },
  { name: 'boolean', hasParam: false },
  { name: 'alpha', hasParam: false },
  { name: 'alpha_num', hasParam: false },
  { name: 'alpha_dash', hasParam: false },
  { name: 'alpha_spaces', hasParam: false },
  { name: 'uppercase', hasParam: false },
  { name: 'lowercase', hasParam: false },
  { name: 'json', hasParam: false },
  { name: 'array', hasParam: false },

  // Rules with parameters
  { name: 'min', hasParam: true, paramType: 'number' },
  { name: 'max', hasParam: true, paramType: 'number' },
  { name: 'between', hasParam: true, paramType: 'text' },
  { name: 'digits', hasParam: true, paramType: 'number' },
  { name: 'digits_between', hasParam: true, paramType: 'text' },
  { name: 'in', hasParam: true, paramType: 'text' },
  { name: 'not_in', hasParam: true, paramType: 'text' },
  { name: 'same', hasParam: true, paramType: 'text' },
  { name: 'different', hasParam: true, paramType: 'text' },
  { name: 'date', hasParam: true, paramType: 'text' },
  { name: 'after', hasParam: true, paramType: 'text' },
  { name: 'before', hasParam: true, paramType: 'text' },
  { name: 'regex', hasParam: true, paramType: 'text' },
  { name: 'extension', hasParam: true, paramType: 'text' },
  { name: 'mimes', hasParam: true, paramType: 'text' },

  // Conditional required rules
  { name: 'required_if', hasParam: true, paramType: 'text' },
  { name: 'required_unless', hasParam: true, paramType: 'text' },
  { name: 'required_with', hasParam: true, paramType: 'text' },
  { name: 'required_without', hasParam: true, paramType: 'text' },
  { name: 'required_with_all', hasParam: true, paramType: 'text' },
  { name: 'required_without_all', hasParam: true, paramType: 'text' },
]

const mergedFieldDefinitions = computed(() => [...fieldDefinitions, ...extensionFieldDefinitions.value])

async function loadExtensionFieldDefinitions() {
  const staticNames = new Set(fieldDefinitions.map(f => f.name))
  const collected = []

  const pushModelRows = (rows, model, blocklist) => {
    const group = model === 'msOrder' ? 'order' : 'address'
    for (const row of rows || []) {
      const name = row.name
      if (!name || blocklist.has(name) || staticNames.has(name)) {
        continue
      }
      collected.push({
        name,
        group,
        labelHint: row.label_translated || row.label || name,
        sortRank: [FIELD_GROUP_SORT[group], row.sort_order ?? 0, name],
      })
    }
  }

  try {
    const [orderRes, addressRes] = await Promise.all([
      request.get('/api/mgr/model-fields', { model: 'msOrder', limit: 500, start: 0 }),
      request.get('/api/mgr/model-fields', { model: 'msOrderAddress', limit: 500, start: 0 }),
    ])
    pushModelRows(orderRes.results, 'msOrder', ORDER_FIELD_BLOCKLIST)
    pushModelRows(addressRes.results, 'msOrderAddress', ADDRESS_FIELD_BLOCKLIST)
  } catch (e) {
    console.warn('[ValidationRulesEditor] Failed to load model-fields:', e)
  }

  try {
    const pushExtraRows = (fields, group) => {
      const blocklist = blocklistForGroup(group)
      for (const f of fields || []) {
        if (!f.active) {
          continue
        }
        const name = f.key
        if (!name || staticNames.has(name) || blocklist.has(name)) {
          continue
        }
        collected.push({
          name,
          group,
          labelHint: f.label || name,
          sortRank: [FIELD_GROUP_SORT[group], 1e6, name],
        })
      }
    }
    const [orderExtra, addressExtra] = await Promise.all([
      request.get('/api/mgr/extra-fields', { class: 'msOrder' }),
      request.get('/api/mgr/extra-fields', { class: 'msOrderAddress' }),
    ])
    pushExtraRows(orderExtra.fields, 'order')
    pushExtraRows(addressExtra.fields, 'address')
  } catch (e) {
    console.warn('[ValidationRulesEditor] Failed to load extra-fields:', e)
  }

  // validation_rules JSON keys are a single flat namespace (field name → rules)
  const byName = new Map()
  for (const c of collected) {
    if (byName.has(c.name)) {
      continue
    }
    byName.set(c.name, c)
  }

  const sorted = [...byName.values()].sort((a, b) => {
    for (let i = 0; i < 3; i++) {
      const av = a.sortRank[i]
      const bv = b.sortRank[i]
      if (av < bv) {
        return -1
      }
      if (av > bv) {
        return 1
      }
    }
    return 0
  })

  extensionFieldDefinitions.value = sorted.map(c => ({
    name: c.name,
    group: c.group,
    labelHint: c.labelHint,
  }))
}

onMounted(() => {
  void loadExtensionFieldDefinitions()
})

// Build available fields with localized labels (flat list)
const availableFields = computed(() => {
  return mergedFieldDefinitions.value.map(field => ({
    ...field,
    label: field.labelHint || _(`validation_field_${field.name}`),
    groupLabel: _(`validation_field_group_${field.group}`),
  }))
})

// Build available rules with localized labels
const availableRules = computed(() => {
  return ruleDefinitions.map(rule => ({
    ...rule,
    label: _(`validation_rule_${rule.name}`),
    description: _(`validation_rule_${rule.name}_desc`),
    paramLabel: rule.hasParam ? _(`validation_rule_${rule.name}_param`) : '',
  }))
})

// Current field rules - array of { field: 'fieldname', rules: [{name, param}] }
const fieldRules = ref([])

// Dialog states
const showAddFieldDialog = ref(false)
const showAddRuleDialog = ref(false)
const selectedField = ref(null)
const editingFieldIndex = ref(null)
const selectedRule = ref(null)
const ruleParam = ref('')

// Parse incoming value (JSON string or object)
function parseValue(value) {
  if (!value) return []

  try {
    let parsed = value
    if (typeof value === 'string') {
      parsed = JSON.parse(value)
    }

    if (typeof parsed !== 'object' || Array.isArray(parsed)) {
      return []
    }

    // Convert { fieldName: 'rule1|rule2:param' } to array format
    return Object.entries(parsed).map(([fieldName, ruleString]) => {
      const rules = ruleString
        .split('|')
        .map(rule => {
          const [name, param] = rule.split(':')
          return { name: name.trim(), param: param || '' }
        })
        .filter(r => r.name)

      return { field: fieldName, rules }
    })
  } catch (e) {
    console.warn('[ValidationRulesEditor] Failed to parse value:', e)
    return []
  }
}

// Convert field rules array to JSON string
function toJsonString(fieldRulesArray) {
  if (!fieldRulesArray || fieldRulesArray.length === 0) return ''

  const result = {}
  fieldRulesArray.forEach(item => {
    if (item.rules && item.rules.length > 0) {
      result[item.field] = item.rules
        .map(r => {
          return r.param ? `${r.name}:${r.param}` : r.name
        })
        .join('|')
    }
  })

  return Object.keys(result).length > 0 ? JSON.stringify(result) : ''
}

// Watch for external changes
watch(
  () => props.modelValue,
  newVal => {
    fieldRules.value = parseValue(newVal)
    // Update JSON text for JSON mode
    if (newVal) {
      try {
        const parsed = typeof newVal === 'string' ? JSON.parse(newVal) : newVal
        jsonText.value = JSON.stringify(parsed, null, 2)
      } catch {
        jsonText.value = typeof newVal === 'string' ? newVal : ''
      }
    } else {
      jsonText.value = ''
    }
  },
  { immediate: true }
)

// Emit changes
function emitChange() {
  const result = toJsonString(fieldRules.value)
  emit('update:modelValue', result)
}

// Switch to JSON mode
function switchToJsonMode() {
  const result = toJsonString(fieldRules.value)
  if (result) {
    try {
      jsonText.value = JSON.stringify(JSON.parse(result), null, 2)
    } catch {
      jsonText.value = result
    }
  } else {
    jsonText.value = ''
  }
  jsonError.value = ''
  isJsonMode.value = true
}

// Switch to visual mode
function switchToVisualMode() {
  if (jsonText.value.trim()) {
    try {
      JSON.parse(jsonText.value)
      emit('update:modelValue', jsonText.value)
      jsonError.value = ''
      isJsonMode.value = false
    } catch (e) {
      jsonError.value = _('validation_json_invalid') + ': ' + e.message
    }
  } else {
    emit('update:modelValue', '')
    isJsonMode.value = false
  }
}

// Handle JSON text change
function onJsonChange() {
  jsonError.value = ''
  if (jsonText.value.trim()) {
    try {
      JSON.parse(jsonText.value)
    } catch (e) {
      jsonError.value = _('validation_json_invalid') + ': ' + e.message
    }
  }
}

// Apply JSON changes
function applyJsonChanges() {
  if (jsonText.value.trim()) {
    try {
      JSON.parse(jsonText.value)
      emit('update:modelValue', jsonText.value)
      jsonError.value = ''
    } catch (e) {
      jsonError.value = _('validation_json_invalid') + ': ' + e.message
    }
  } else {
    emit('update:modelValue', '')
  }
}

// Get field definition by name
function getFieldDef(name) {
  return availableFields.value.find(f => f.name === name)
}

// Get rule definition by name
function getRuleDef(name) {
  return availableRules.value.find(r => r.name === name)
}

// Get display label for a field
function getFieldLabel(fieldName) {
  const def = getFieldDef(fieldName)
  return def ? def.label : fieldName
}

// Get display label for a rule
function getRuleLabel(rule) {
  const def = getRuleDef(rule.name)
  const label = def ? def.label : rule.name
  return rule.param ? `${label}: ${rule.param}` : label
}

// Available fields for dropdown (exclude already added) - grouped
const availableFieldsForAdd = computed(() => {
  const addedFields = fieldRules.value.map(fr => fr.field)

  // Filter and group available fields
  const groups = {}
  availableFields.value.forEach(field => {
    if (addedFields.includes(field.name)) return

    if (!groups[field.group]) {
      groups[field.group] = {
        label: field.groupLabel,
        items: [],
      }
    }
    groups[field.group].items.push(field)
  })

  // Return only groups that have items
  return Object.values(groups).filter(g => g.items.length > 0)
})

// Available rules for current field (exclude already added)
const availableRulesForAdd = computed(() => {
  if (editingFieldIndex.value === null) return availableRules.value

  const currentFieldRules = fieldRules.value[editingFieldIndex.value]
  if (!currentFieldRules) return availableRules.value

  const addedRuleNames = currentFieldRules.rules.map(r => r.name)
  return availableRules.value.filter(r => !addedRuleNames.includes(r.name))
})

// Open add field dialog
function openAddFieldDialog() {
  selectedField.value = null
  showAddFieldDialog.value = true
}

// Add selected field
function addField() {
  if (!selectedField.value) return

  fieldRules.value.push({
    field: selectedField.value,
    rules: [],
  })

  showAddFieldDialog.value = false
  selectedField.value = null

  // Don't emit change yet - field has no rules
  // emitChange() will be called when rules are added

  // Open rules dialog for the new field
  editingFieldIndex.value = fieldRules.value.length - 1
  openAddRuleDialog()
}

// Remove field by index
function removeField(index) {
  fieldRules.value.splice(index, 1)
  emitChange()
}

// Open add rule dialog for a field
function openAddRuleDialog(fieldIndex = null) {
  if (fieldIndex !== null) {
    editingFieldIndex.value = fieldIndex
  }
  selectedRule.value = null
  ruleParam.value = ''
  showAddRuleDialog.value = true
}

// Close rule dialog and cleanup empty fields
function closeRuleDialog() {
  showAddRuleDialog.value = false

  // Remove field if it has no rules (was just added but user cancelled)
  if (editingFieldIndex.value !== null) {
    const field = fieldRules.value[editingFieldIndex.value]
    if (field && field.rules.length === 0) {
      fieldRules.value.splice(editingFieldIndex.value, 1)
    }
  }

  selectedRule.value = null
  ruleParam.value = ''
  editingFieldIndex.value = null
}

// Add rule to current field
function addRule() {
  if (!selectedRule.value || editingFieldIndex.value === null) return

  const def = getRuleDef(selectedRule.value)

  // If rule requires param and none provided, don't add
  if (def && def.hasParam && !ruleParam.value.trim()) {
    return
  }

  fieldRules.value[editingFieldIndex.value].rules.push({
    name: selectedRule.value,
    param: ruleParam.value.trim(),
  })

  // Reset state before closing (so @hide handler doesn't remove the field)
  editingFieldIndex.value = null
  selectedRule.value = null
  ruleParam.value = ''
  showAddRuleDialog.value = false
  emitChange()
}

// Remove rule from field
function removeRule(fieldIndex, ruleIndex) {
  const field = fieldRules.value[fieldIndex]
  if (!field || !field.rules || ruleIndex >= field.rules.length) {
    console.warn('[ValidationRulesEditor] Invalid indices:', fieldIndex, ruleIndex)
    return
  }

  // Remove single rule
  field.rules.splice(ruleIndex, 1)

  // If field has no more rules, remove the field too
  if (field.rules.length === 0) {
    fieldRules.value.splice(fieldIndex, 1)
  }

  emitChange()
}

// Check if selected rule needs parameter
const selectedRuleDef = computed(() => {
  if (!selectedRule.value) return null
  return getRuleDef(selectedRule.value)
})

// Get current editing field name
const editingFieldName = computed(() => {
  if (editingFieldIndex.value === null) return ''
  const fr = fieldRules.value[editingFieldIndex.value]
  return fr ? getFieldLabel(fr.field) : ''
})
</script>

<template>
  <div class="validation-rules-editor">
    <!-- Mode toggle -->
    <div class="mode-toggle">
      <span class="mode-label">{{ _('validation_mode_visual') }}</span>
      <ToggleSwitch
        v-model="isJsonMode"
        @change="isJsonMode ? switchToJsonMode() : switchToVisualMode()"
      />
      <span class="mode-label">{{ _('validation_mode_json') }}</span>
    </div>

    <!-- JSON Editor -->
    <div v-if="isJsonMode" class="json-editor">
      <Textarea
        v-model="jsonText"
        :placeholder="_('validation_json_placeholder')"
        rows="8"
        class="w-full json-textarea"
        @input="onJsonChange"
      />
      <small v-if="jsonError" class="json-error">{{ jsonError }}</small>
      <div class="json-actions">
        <Button
          :label="_('apply')"
          size="small"
          :disabled="!!jsonError"
          @click="applyJsonChanges"
        />
      </div>
    </div>

    <!-- Visual Editor -->
    <div v-else class="field-rules-list">
      <div v-for="(fr, fieldIndex) in fieldRules" :key="fr.field" class="field-rules-item">
        <div class="field-header">
          <span class="field-name">{{ getFieldLabel(fr.field) }}</span>
          <Button
            v-tooltip="_('remove')"
            icon="pi pi-times"
            severity="danger"
            text
            rounded
            size="small"
            @click="removeField(fieldIndex)"
          />
        </div>
        <div class="rules-chips">
          <Chip
            v-for="(rule, ruleIndex) in fr.rules"
            :key="`${fr.field}-${rule.name}-${ruleIndex}`"
            :label="getRuleLabel(rule)"
            removable
            class="rule-chip"
            @remove="() => removeRule(fieldIndex, ruleIndex)"
          />
          <Button
            v-tooltip="_('ms3_add_rule')"
            icon="pi pi-plus"
            size="small"
            severity="secondary"
            text
            rounded
            @click="openAddRuleDialog(fieldIndex)"
          />
        </div>
      </div>

      <!-- Add field button -->
      <Button
        icon="pi pi-plus"
        :label="_('ms3_add_field')"
        size="small"
        severity="secondary"
        outlined
        class="add-field-btn"
        :disabled="availableFieldsForAdd.length === 0"
        @click="openAddFieldDialog"
      />
    </div>

    <!-- Add field dialog -->
    <Dialog
      v-model:visible="showAddFieldDialog"
      :header="_('ms3_add_validation_field')"
      :modal="true"
      :style="{ width: '28.125rem' }"
      append-to="self"
    >
      <div class="add-field-form">
        <div class="form-field">
          <label>{{ _('ms3_select_field') }}</label>
          <Select
            v-model="selectedField"
            :options="availableFieldsForAdd"
            option-label="label"
            option-value="name"
            option-group-label="label"
            option-group-children="items"
            :placeholder="_('ms3_select_field_placeholder')"
            class="w-full"
            filter
          />
        </div>
      </div>

      <template #footer>
        <Button :label="_('cancel')" severity="secondary" @click="showAddFieldDialog = false" />
        <Button :label="_('add')" :disabled="!selectedField" @click="addField" />
      </template>
    </Dialog>

    <!-- Add rule dialog -->
    <Dialog
      v-model:visible="showAddRuleDialog"
      :header="_('ms3_add_rule_to_field') + ': ' + editingFieldName"
      :modal="true"
      :style="{ width: '28.125rem' }"
      append-to="self"
      @hide="closeRuleDialog"
    >
      <div class="add-rule-form">
        <div class="form-field">
          <label>{{ _('ms3_select_rule') }}</label>
          <Select
            v-model="selectedRule"
            :options="availableRulesForAdd"
            option-label="label"
            option-value="name"
            :placeholder="_('ms3_select_rule_placeholder')"
            class="w-full"
            filter
          >
            <template #option="{ option }">
              <div class="rule-option">
                <span class="rule-option-name">{{ option.label }}</span>
                <span class="rule-option-desc">{{ option.description }}</span>
              </div>
            </template>
          </Select>
        </div>

        <div v-if="selectedRuleDef && selectedRuleDef.hasParam" class="form-field">
          <label>{{ selectedRuleDef.paramLabel }}</label>
          <InputText
            v-model="ruleParam"
            :type="selectedRuleDef.paramType === 'number' ? 'number' : 'text'"
            :placeholder="selectedRuleDef.paramLabel"
            class="w-full"
          />
          <small class="param-hint">{{ selectedRuleDef.description }}</small>
        </div>
      </div>

      <template #footer>
        <Button :label="_('cancel')" severity="secondary" @click="closeRuleDialog" />
        <Button
          :label="_('add')"
          :disabled="!selectedRule || (selectedRuleDef?.hasParam && !ruleParam.trim())"
          @click="addRule"
        />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.validation-rules-editor {
  width: 100%;
}

.mode-toggle {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 0.75rem;
}

.mode-label {
  font-size: 0.875rem;
  color: var(--ms3-text-muted);
  line-height: 1;
}

.json-editor {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.json-textarea {
  font-family: monospace;
  font-size: 0.875rem;
}

.json-error {
  color: var(--ms3-text-danger-alt);
  font-size: 0.8rem;
}

.json-actions {
  display: flex;
  justify-content: flex-end;
}

.field-rules-list {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.field-rules-item {
  background: var(--ms3-bg-slate);
  border: var(--ms3-border-width) solid var(--ms3-border-color);
  border-radius: 0.375rem;
  padding: 0.75rem;
}

.field-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 0.5rem;
}

.field-name {
  font-weight: 600;
  color: var(--ms3-text-darkest);
}

.rules-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  align-items: center;
}

.rule-chip {
  background: var(--ms3-bg-info);
  color: var(--ms3-text-info-dark);
}

.rule-chip :deep(.p-chip-remove-icon) {
  color: var(--ms3-text-info-dark);
}

.add-field-btn {
  align-self: flex-start;
}

.add-field-form,
.add-rule-form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.form-field {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.form-field label {
  font-weight: 500;
  color: var(--ms3-text-primary);
}

.param-hint {
  color: var(--ms3-text-muted);
  font-size: 0.8rem;
}

:deep(.rule-option) {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
  padding: 0.25rem 0;
}

:deep(.rule-option-name) {
  font-weight: 500;
  color: var(--ms3-text-darkest);
}

:deep(.rule-option-desc) {
  font-size: 0.8rem;
  color: var(--ms3-text-muted);
}

.w-full {
  width: 100%;
}
</style>

<!-- Global styles for PrimeVue dropdown panels -->
<style>
.p-select-overlay .rule-option {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
  padding: 0.25rem 0;
}

.p-select-overlay .rule-option-name {
  font-weight: 500;
  color: var(--ms3-text-darkest);
}

.p-select-overlay .rule-option-desc {
  font-size: 0.8rem;
  color: var(--ms3-text-muted);
}
</style>
