<script setup>
import { onMounted, ref, computed } from 'vue'
import Card from 'primevue/card'
import Button from 'primevue/button'
import Message from 'primevue/message'
import Toast from 'primevue/toast'
import { useToast } from 'primevue/usetoast'
import DynamicField from './DynamicField.vue'
import request from '../request.js'

const props = defineProps({
  productId: {
    type: Number,
    required: true
  },
  productData: {
    type: Object,
    default: () => ({})
  }
})

const toast = useToast()

// Состояние загрузки
const loading = ref(false)
const saving = ref(false)

// Конфигурация полей
const fieldsConfig = ref({
  fields: []
})

// Значения полей
const fieldValues = ref({})

// Ключ страницы
const pageKey = 'product_data'

/**
 * Загрузить конфигурацию полей
 */
async function loadConfig() {
  loading.value = true
  console.log('[ProductDataFields] Loading config for:', pageKey)

  try {
    const response = await request.get(`/api/mgr/config/page-fields/${pageKey}`)
    console.log('[ProductDataFields] Config response:', response)

    if (response && response.fields) {
      fieldsConfig.value = response
      console.log('[ProductDataFields] Fields loaded:', response.fields.length)

      // Инициализировать значения полей из productData
      response.fields.forEach(field => {
        if (props.productData && props.productData[field.id] !== undefined) {
          fieldValues.value[field.id] = props.productData[field.id]
        } else {
          fieldValues.value[field.id] = null
        }
      })

      console.log('[ProductDataFields] Field values initialized:', fieldValues.value)
    } else {
      console.error('[ProductDataFields] Invalid response:', response)
      toast.add({
        severity: 'error',
        summary: 'Ошибка',
        detail: 'Не удалось загрузить конфигурацию полей',
        life: 5000
      })
    }
  } catch (error) {
    console.error('[ProductDataFields] Error loading config:', error)
    toast.add({
      severity: 'error',
      summary: 'Ошибка',
      detail: error.message || 'Ошибка загрузки конфигурации',
      life: 5000
    })
  } finally {
    loading.value = false
    console.log('[ProductDataFields] Loading finished. Loading state:', loading.value)
  }
}

/**
 * Сохранить данные товара
 */
async function saveProductData() {
  saving.value = true
  try {
    const response = await request.put(
      `/api/mgr/products/${props.productId}`,
      fieldValues.value
    )

    if (response && response.updated) {
      toast.add({
        severity: 'success',
        summary: 'Успешно',
        detail: 'Данные товара сохранены',
        life: 3000
      })
    } else {
      toast.add({
        severity: 'error',
        summary: 'Ошибка',
        detail: 'Не удалось сохранить данные товара',
        life: 5000
      })
    }
  } catch (error) {
    console.error('Error saving product data:', error)
    toast.add({
      severity: 'error',
      summary: 'Ошибка',
      detail: error.message || 'Ошибка сохранения данных',
      life: 5000
    })
  } finally {
    saving.value = false
  }
}

/**
 * Обработать изменение поля
 */
function handleFieldChange(fieldId, value) {
  fieldValues.value[fieldId] = value
}

/**
 * Получить только видимые поля
 */
const visibleFields = computed(() => {
  return fieldsConfig.value.fields.filter(field => field.visible !== false)
})

// При монтировании загрузить конфигурацию
onMounted(() => {
  loadConfig()
})
</script>

<template>
  <div class="product-data-fields">
    <Card>
      <template #title>
        <div class="card-header">
          <span>Данные товара</span>
          <div class="actions">
            <Button
              label="Сохранить"
              icon="pi pi-save"
              @click="saveProductData"
              :loading="saving"
              :disabled="loading"
            />
          </div>
        </div>
      </template>

      <template #content>
        <Message v-if="loading" severity="info">
          Загрузка конфигурации полей...
        </Message>

        <Message v-else-if="visibleFields.length === 0" severity="warn">
          Нет видимых полей для отображения. Настройте конфигурацию в разделе Утилиты.
        </Message>

        <div v-else class="fields-grid">
          <div
            v-for="field in visibleFields"
            :key="field.id"
            class="field-item"
          >
            <label :for="field.id" class="field-label">
              {{ field.label }}
              <span v-if="field.required" class="required">*</span>
            </label>

            <DynamicField
              :field-config="field"
              v-model="fieldValues[field.id]"
              :disabled="loading || saving"
              @blur="handleFieldChange(field.id, $event.value)"
            />

            <small v-if="field.description" class="field-description">
              {{ field.description }}
            </small>
          </div>
        </div>
      </template>
    </Card>

    <Toast />
  </div>
</template>

<style scoped>
.product-data-fields {
  padding: 20px;
}

.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
}

.actions {
  display: flex;
  gap: 10px;
}

.fields-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 20px;
}

@media (max-width: 768px) {
  .fields-grid {
    grid-template-columns: 1fr;
  }
}

.field-item {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.field-label {
  font-weight: 600;
  font-size: 14px;
  color: #333;
}

.field-label .required {
  color: #e24c4c;
  margin-left: 2px;
}

.field-description {
  color: #666;
  font-size: 12px;
  margin-top: 4px;
}
</style>
