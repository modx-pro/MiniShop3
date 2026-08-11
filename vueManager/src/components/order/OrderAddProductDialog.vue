<script setup>
import { useLexicon } from '@vuetools/useLexicon'
import AutoComplete from 'primevue/autocomplete'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputNumber from 'primevue/inputnumber'

const { _ } = useLexicon()

const visible = defineModel('visible', { type: Boolean, default: false })
const selectedProduct = defineModel('selectedProduct', { type: Object, default: null })
const addProductForm = defineModel('addProductForm', { type: Object, required: true })

defineProps({
  productSuggestions: { type: Array, default: () => [] },
  searchingProducts: { type: Boolean, default: false },
  savingNewProduct: { type: Boolean, default: false },
  newProductCost: { type: Number, default: 0 },
  formatPrice: { type: Function, required: true },
  searchProducts: { type: Function, required: true },
  onProductSelect: { type: Function, required: true },
  cancelAddProduct: { type: Function, required: true },
  saveNewProduct: { type: Function, required: true },
})
</script>

<template>
<!-- Add Product Dialog -->
<Dialog
  v-model:visible="visible"
  :header="_('order_add_product_title')"
  :style="{ width: '34.375rem' }"
  :modal="true"
  :closable="!savingNewProduct"
  :close-on-escape="!savingNewProduct"
  append-to="self"
>
  <div class="add-product-form">
    <!-- Product search -->
    <div class="field mb-3">
      <label>{{ _('order_search_product') }}</label>
      <AutoComplete
        v-model="selectedProduct"
        :suggestions="productSuggestions"
        option-label="display"
        :placeholder="_('order_search_product')"
        :loading="searchingProducts"
        class="w-full"
        :min-length="2"
        @complete="searchProducts"
        @item-select="onProductSelect"
      >
        <template #option="{ option }">
          <div class="ms3-product-suggestion">
            <img
              v-if="option.image"
              :src="option.image"
              :alt="option.pagetitle"
              class="ms3-product-suggestion-image"
            />
            <div class="ms3-product-suggestion-info">
              <div class="ms3-product-suggestion-name">{{ option.pagetitle }}</div>
              <div class="ms3-product-suggestion-meta">
                <span v-if="option.article" class="article">[{{ option.article }}]</span>
                <span class="price">{{ formatPrice(option.price) }}</span>
              </div>
            </div>
          </div>
        </template>
      </AutoComplete>
    </div>

    <!-- Product details (shown after selection) -->
    <div v-if="selectedProduct && selectedProduct.id" class="selected-product-details">
      <div class="selected-product-header mb-3">
        <img
          v-if="selectedProduct.image"
          :src="selectedProduct.image"
          :alt="selectedProduct.pagetitle"
          class="selected-product-image"
        />
        <div class="selected-product-name">{{ selectedProduct.pagetitle }}</div>
      </div>

      <div class="product-form-fields">
        <!-- Count -->
        <div class="field mb-3">
          <label>{{ _('order_product_count') }}</label>
          <InputNumber v-model="addProductForm.count" :min="1" class="w-full" />
        </div>

        <!-- Price -->
        <div class="field mb-3">
          <label>{{ _('order_product_price') }}</label>
          <InputNumber
            v-model="addProductForm.price"
            mode="decimal"
            :min-fraction-digits="2"
            :max-fraction-digits="2"
            class="w-full"
          />
        </div>

        <!-- Weight -->
        <div class="field mb-3">
          <label>{{ _('order_product_weight') }}</label>
          <InputNumber
            v-model="addProductForm.weight"
            mode="decimal"
            :min-fraction-digits="3"
            :max-fraction-digits="3"
            class="w-full"
          />
        </div>

        <!-- Calculated cost -->
        <div class="field mb-3">
          <label>{{ _('order_product_cost') }}</label>
          <div class="calculated-cost">{{ formatPrice(newProductCost) }}</div>
        </div>
      </div>
    </div>
  </div>

  <template #footer>
    <Button
      :label="_('cancel')"
      icon="pi pi-times"
      severity="secondary"
      :disabled="savingNewProduct"
      @click="cancelAddProduct"
    />
    <Button
      :label="_('save')"
      icon="pi pi-check"
      :loading="savingNewProduct"
      :disabled="!selectedProduct || !selectedProduct.id"
      @click="saveNewProduct"
    />
  </template>
</Dialog>
</template>

<style scoped>
.add-product-form .field label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 500;
  color: var(--ms3-text-muted);
  font-size: 0.875rem;
}

.selected-product-details {
  border: var(--ms3-border-width) solid var(--ms3-border-color);
  border-radius: 0.5rem;
  padding: 1rem;
  background: var(--ms3-bg-slate);
}

.selected-product-header {
  display: flex;
  align-items: center;
  gap: var(--ms3-spacing-3);
  padding-bottom: var(--ms3-spacing-3);
  border-bottom: var(--ms3-border-width) solid var(--ms3-border-color);
}

.selected-product-image {
  width: 3.125rem;
  height: 3.125rem;
  object-fit: cover;
  border-radius: 0.25rem;
  border: var(--ms3-border-width) solid var(--ms3-border-color);
}

.selected-product-name {
  font-weight: 600;
  font-size: 1rem;
  color: var(--ms3-text-darkest);
}

.calculated-cost {
  font-size: 1.125rem;
  font-weight: 600;
  color: var(--ms3-text-success);
  padding: 0.5rem;
  background: var(--ms3-bg-success);
  border-radius: 0.25rem;
  text-align: right;
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

<style>
/* AutoComplete input full width
   .p- classes are excluded from postcss-prefix-selector */
.add-product-form .p-autocomplete {
  width: 100%;
}

.add-product-form .p-autocomplete-input {
  width: 100%;
}

/* Product suggestion in AutoComplete dropdown
   Uses .ms3- prefix to bypass postcss-prefix-selector (excluded in vite.config.js)
   These styles work in teleported dropdowns rendered in <body> */
.ms3-product-suggestion {
  display: flex;
  flex-direction: row;
  align-items: center;
  gap: var(--ms3-spacing-3);
  padding: 0.25rem 0;
}

.ms3-product-suggestion-image {
  width: 3.125rem;
  height: 3.125rem;
  object-fit: cover;
  border-radius: 0.25rem;
  border: var(--ms3-border-width) solid var(--ms3-border-color);
  flex-shrink: 0;
}

.ms3-product-suggestion-info {
  flex: 1;
  min-width: 0;
}

.ms3-product-suggestion-name {
  font-weight: 500;
  color: var(--ms3-text-darkest);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.ms3-product-suggestion-meta {
  font-size: 0.75rem;
  color: var(--ms3-text-muted);
  display: flex;
  gap: 0.5rem;
}

.ms3-product-suggestion-meta .article {
  color: var(--ms3-accent-primary);
}

.ms3-product-suggestion-meta .price {
  font-weight: 500;
}

</style>
