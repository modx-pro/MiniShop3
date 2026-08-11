<script setup>
import { useLexicon } from '@vuetools/useLexicon'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'

const { _ } = useLexicon()

const visible = defineModel('visible', { type: Boolean, default: false })
const editProductForm = defineModel('editProductForm', { type: Object, required: true })
const optionsTableData = defineModel('optionsTableData', { type: Array, default: () => [] })
const optionsJsonText = defineModel('optionsJsonText', { type: String, default: '' })

defineProps({
  editingProduct: { type: Object, default: null },
  savingProduct: { type: Boolean, default: false },
  optionsEditMode: { type: String, default: 'table' },
  productOptionFields: { type: Array, default: () => [] },
  optionsJsonError: { type: String, default: '' },
  editedProductCost: { type: Number, default: 0 },
  formatPrice: { type: Function, required: true },
  switchOptionsMode: { type: Function, required: true },
  onOptionTypeChange: { type: Function, required: true },
  onFieldKeyChange: { type: Function, required: true },
  syncTableToJson: { type: Function, required: true },
  removeOptionRow: { type: Function, required: true },
  addOptionRow: { type: Function, required: true },
  cancelEditProduct: { type: Function, required: true },
  saveEditedProduct: { type: Function, required: true },
})
</script>

<template>
<!-- Edit Product Dialog -->
<Dialog
  v-model:visible="visible"
  :header="_('order_product_edit')"
  :style="{ width: '40.625rem' }"
  :modal="true"
  :closable="!savingProduct"
  :close-on-escape="!savingProduct"
  append-to="self"
>
  <div v-if="editingProduct" class="edit-product-form">
    <!-- Product name (readonly) -->
    <div class="field mb-3">
      <label>{{ _('order_product_name') }}</label>
      <div class="product-name-display">{{ editingProduct.name }}</div>
    </div>

    <!-- Count -->
    <div class="field mb-3">
      <label for="edit-count">{{ _('order_product_count') }}</label>
      <InputNumber
        id="edit-count"
        v-model="editProductForm.count"
        :min="1"
        :max="9999"
        show-buttons
        class="w-full"
      />
    </div>

    <!-- Price -->
    <div class="field mb-3">
      <label for="edit-price">{{ _('order_product_price') }}</label>
      <InputNumber
        id="edit-price"
        v-model="editProductForm.price"
        :min="0"
        :min-fraction-digits="0"
        :max-fraction-digits="2"
        class="w-full"
      />
    </div>

    <!-- Weight -->
    <div class="field mb-3">
      <label for="edit-weight">{{ _('order_product_weight') }}</label>
      <InputNumber
        id="edit-weight"
        v-model="editProductForm.weight"
        :min="0"
        :min-fraction-digits="0"
        :max-fraction-digits="3"
        class="w-full"
      />
    </div>

    <!-- Calculated cost (readonly) -->
    <div class="field mb-3">
      <label>{{ _('order_product_cost') }}</label>
      <div class="cost-display">{{ formatPrice(editedProductCost) }}</div>
    </div>

    <!-- Options -->
    <div class="field mb-3">
      <div class="options-header">
        <label>{{ _('order_product_options') }}</label>
        <div class="options-mode-switch">
          <Button
            :label="_('options_mode_table')"
            :severity="optionsEditMode === 'table' ? 'primary' : 'secondary'"
            size="small"
            text
            @click="switchOptionsMode('table')"
          />
          <Button
            :label="_('options_mode_json')"
            :severity="optionsEditMode === 'json' ? 'primary' : 'secondary'"
            size="small"
            text
            @click="switchOptionsMode('json')"
          />
        </div>
      </div>

      <!-- Table mode -->
      <div v-if="optionsEditMode === 'table'" class="options-table">
        <div v-for="(row, index) in optionsTableData" :key="index" class="options-row">
          <!-- Type selector -->
          <Select
            v-model="row.type"
            :options="[
              { value: 'field', label: _('options_type_field') },
              { value: 'custom', label: _('options_type_custom') },
            ]"
            option-label="label"
            option-value="value"
            class="options-type-select"
            @change="onOptionTypeChange(row)"
          />

          <!-- Field type: Select field name -->
          <template v-if="row.type === 'field'">
            <Select
              v-model="row.key"
              :options="productOptionFields"
              option-label="label"
              option-value="name"
              :placeholder="_('options_select_field')"
              class="options-key-input"
              @change="onFieldKeyChange(row)"
            />
            <!-- Field value: Select from available values or input -->
            <Select
              v-if="row.fieldValues.length > 0"
              v-model="row.value"
              :options="row.fieldValues"
              option-label="label"
              option-value="value"
              :placeholder="_('options_select_value')"
              :loading="row.loadingValues"
              editable
              class="options-value-input"
              @change="syncTableToJson"
            />
            <InputText
              v-else
              v-model="row.value"
              :placeholder="row.loadingValues ? _('loading') : _('options_value')"
              :disabled="row.loadingValues"
              class="options-value-input"
              @change="syncTableToJson"
            />
          </template>

          <!-- Custom type: Free text inputs -->
          <template v-else>
            <InputText
              v-model="row.key"
              :placeholder="_('options_key')"
              class="options-key-input"
              @change="syncTableToJson"
            />
            <Textarea
              v-if="row.isComplex"
              v-model="row.value"
              :placeholder="_('options_value')"
              class="options-value-input"
              rows="2"
              @change="syncTableToJson"
            />
            <InputText
              v-else
              v-model="row.value"
              :placeholder="_('options_value')"
              class="options-value-input"
              @change="syncTableToJson"
            />
          </template>

          <Button
            icon="pi pi-times"
            severity="danger"
            text
            rounded
            size="small"
            @click="removeOptionRow(index)"
          />
        </div>
        <Button
          :label="_('options_add_row')"
          icon="pi pi-plus"
          severity="secondary"
          size="small"
          text
          @click="addOptionRow"
        />
      </div>

      <!-- JSON mode -->
      <div v-else class="options-json">
        <Textarea
          v-model="optionsJsonText"
          :placeholder="_('options_json_placeholder')"
          rows="6"
          class="w-full options-json-textarea"
          :class="{ 'p-invalid': optionsJsonError }"
        />
        <small v-if="optionsJsonError" class="p-error">{{ optionsJsonError }}</small>
      </div>
    </div>
  </div>

  <template #footer>
    <Button
      :label="_('cancel')"
      icon="pi pi-times"
      severity="secondary"
      :disabled="savingProduct"
      @click="cancelEditProduct"
    />
    <Button
      :label="_('save')"
      icon="pi pi-check"
      :loading="savingProduct"
      @click="saveEditedProduct"
    />
  </template>
</Dialog>
</template>

<style scoped>
.edit-product-form .field label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 500;
  color: var(--ms3-text-muted);
  font-size: 0.875rem;
}

.product-name-display {
  font-size: 1rem;
  font-weight: 500;
  color: var(--ms3-text-darkest);
  padding: 0.5rem;
  background: var(--ms3-bg-slate);
  border-radius: 0.25rem;
}

.cost-display {
  font-size: 1.125rem;
  font-weight: 600;
  color: var(--ms3-text-success);
  padding: 0.5rem;
  background: var(--ms3-bg-success);
  border-radius: 0.25rem;
  text-align: right;
}

.options-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.5rem;
}

.options-header label {
  margin-bottom: 0 !important;
}

.options-mode-switch {
  display: flex;
  gap: 0.25rem;
}

.options-table {
  border: var(--ms3-border-width) solid var(--ms3-border-color);
  border-radius: 0.375rem;
  padding: var(--ms3-spacing-3);
  background: var(--ms3-bg-slate);
}

.options-row {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 0.5rem;
  align-items: flex-start;
}

.options-row:last-of-type {
  margin-bottom: var(--ms3-spacing-3);
}

.options-type-select {
  flex: 0 0 6.25rem;
  min-width: 6.25rem;
}

.options-key-input {
  flex: 0 0 7.5rem;
  min-width: 6.25rem;
}

.options-value-input {
  flex: 1;
  min-width: 0;
}

.options-json {
  border: var(--ms3-border-width) solid var(--ms3-border-color);
  border-radius: 0.375rem;
  padding: 0.5rem;
  background: var(--ms3-bg-slate);
}

.options-json-textarea {
  font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
  font-size: 0.875rem;
}

.options-json-textarea.p-invalid {
  border-color: var(--ms3-text-danger);
}

.p-error {
  color: var(--ms3-text-danger);
  font-size: 0.75rem;
  margin-top: 0.25rem;
  display: block;
}

.field {
  margin-bottom: 0;
}

.mb-3 {
  margin-bottom: 1rem;
}

.w-full {
  width: 100%;
}

</style>
