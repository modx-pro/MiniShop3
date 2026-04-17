<script setup>
import { useLexicon } from '@vuetools/useLexicon'
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import { inject } from 'vue'

import { ORDER_CONTEXT_KEY } from '../../composables/orderContext.js'
import { useOrderFormatters } from '../../composables/useOrderFormatters.js'
import OrderProductOptionsChips from './OrderProductOptionsChips.vue'

defineProps({
  products: { type: Array, required: true },
  productsColumns: { type: Array, required: true },
})

const { formatOptions, formatPrice, renderProductField, getProductLink } = useOrderFormatters()

const orderCtx = inject(ORDER_CONTEXT_KEY, null)
if (import.meta.env.DEV && !orderCtx) {
  console.error('[OrderProductsTab] Missing inject: orderContext (must be used inside OrderView)')
}
if (!orderCtx) {
  throw new Error('[OrderProductsTab] orderContext is required. Use OrderView as parent.')
}

const { handleProductAction, openAddProductDialog } = orderCtx

const { _ } = useLexicon()
</script>

<template>
  <div class="order-products-tab">
    <div class="products-toolbar mb-3">
      <Button
        :label="_('order_add_product')"
        icon="pi pi-plus"
        severity="primary"
        size="small"
        @click="openAddProductDialog"
      />
    </div>
    <DataTable :value="products" striped-rows responsive-layout="scroll">
      <template v-for="column in productsColumns.filter(c => c.visible)" :key="column.name">
        <Column
          v-if="column.type === 'image'"
          :field="column.name"
          :header="column.label"
          :style="{ width: column.width }"
        >
          <template #body="{ data }">
            <img
              v-if="data[column.name]"
              :src="data[column.name]"
              :alt="data.name"
              class="product-thumbnail"
              :style="{
                width: (column.width || 50) + 'px',
                height: (column.height || 50) + 'px',
                objectFit: 'cover',
              }"
            />
            <span v-else class="no-image">—</span>
          </template>
        </Column>

        <Column
          v-else-if="column.type === 'options'"
          :field="column.name"
          :header="column.label"
          :style="{ width: column.width, minWidth: column.minWidth }"
        >
          <template #body="{ data }">
            <OrderProductOptionsChips :raw="data[column.name]" :format-options="formatOptions" />
          </template>
        </Column>

        <Column
          v-else-if="column.type === 'price'"
          :field="column.name"
          :header="column.label"
          :sortable="column.sortable"
          :style="{ width: column.width, minWidth: column.minWidth }"
        >
          <template #body="{ data }">
            {{ formatPrice(data[column.name]) }}
          </template>
        </Column>

        <Column
          v-else-if="column.type === 'number'"
          :field="column.name"
          :header="column.label"
          :sortable="column.sortable"
          :style="{ width: column.width, minWidth: column.minWidth }"
        >
          <template #body="{ data }">
            {{ data[column.name] }}
          </template>
        </Column>

        <Column
          v-else-if="column.type === 'weight'"
          :field="column.name"
          :header="column.label"
          :sortable="column.sortable"
          :style="{ width: column.width, minWidth: column.minWidth }"
        >
          <template #body="{ data }">
            {{ data[column.name + '_formatted'] || data[column.name] }}
          </template>
        </Column>

        <Column
          v-else-if="column.type === 'template'"
          :field="column.name"
          :header="column.label"
          :sortable="column.sortable"
          :style="{ width: column.width, minWidth: column.minWidth }"
        >
          <template #body="{ data, index }">
            <template
              v-for="cell in [
                {
                  link: getProductLink(data, column),
                  label: renderProductField(data, column) || '—',
                },
              ]"
              :key="`${column.name}-${index}-${cell.link ?? 'nolink'}`"
            >
              <a v-if="cell.link" :href="cell.link" target="_blank" class="product-link">
                {{ cell.label }}
              </a>
              <span v-else>{{ cell.label }}</span>
            </template>
          </template>
        </Column>

        <Column
          v-else-if="column.type === 'actions'"
          :header="column.label"
          :frozen="column.frozen"
          :style="{ width: column.width }"
        >
          <template #body="{ data }">
            <div class="actions-buttons">
              <Button
                v-for="action in column.actions || []"
                :key="action.name"
                :icon="'pi ' + action.icon"
                :severity="action.severity || 'secondary'"
                text
                rounded
                size="small"
                @click="handleProductAction(action, data)"
              />
            </div>
          </template>
        </Column>

        <Column
          v-else
          :field="column.name"
          :header="column.label"
          :sortable="column.sortable"
          :style="{ width: column.width, minWidth: column.minWidth }"
        />
      </template>
    </DataTable>
  </div>
</template>

<style scoped>
.mb-3 {
  margin-bottom: 1rem;
}

.products-toolbar {
  display: flex;
  justify-content: flex-end;
}

.product-thumbnail {
  border-radius: 0.25rem;
  object-fit: cover;
}

.no-image {
  color: var(--ms3-text-muted-light);
}

.product-link {
  color: var(--ms3-accent-primary);
  text-decoration: none;
}

.product-link:hover {
  text-decoration: underline;
}

.actions-buttons {
  display: flex;
  gap: 0.25rem;
}
</style>
