<script setup>
import { onMounted, ref, computed } from 'vue'
import Card from 'primevue/card'
import Message from 'primevue/message'
import Fieldset from 'primevue/fieldset'
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

// Конфигурация полей и секций
const fieldsConfig = ref({
  fields: [],
  sections: {}
})

// Значения полей
const fieldValues = ref({})

// Данные товара
const productData = ref({})

// Ключ страницы
const pageKey = 'product_data'

/**
 * Загрузить данные товара
 */
async function loadProductData() {
  try {
    const response = await request.get(`/api/mgr/product-data/${props.productId}`)

    if (response) {
      productData.value = response
      return response
    } else {
      console.error('[ProductDataFields] Invalid product data response:', response)
      toast.add({
        severity: 'error',
        summary: 'Ошибка',
        detail: 'Не удалось загрузить данные товара',
        life: 5000
      })
      return null
    }
  } catch (error) {
    console.error('[ProductDataFields] Error loading product data:', error)
    toast.add({
      severity: 'error',
      summary: 'Ошибка',
      detail: error.message || 'Ошибка загрузки данных товара',
      life: 5000
    })
    return null
  }
}

/**
 * Загрузить конфигурацию полей
 */
async function loadConfig() {
  loading.value = true

  try {
    // Загружаем конфигурацию полей и данные товара параллельно
    const [configResponse, productDataResponse] = await Promise.all([
      request.get(`/api/mgr/config/page-fields/${pageKey}`),
      loadProductData()
    ])

    if (configResponse && configResponse.fields) {
      fieldsConfig.value = configResponse

      // Инициализировать значения полей из загруженных данных товара
      configResponse.fields.forEach(field => {
        const fieldName = field.name
        if (productDataResponse && productDataResponse[fieldName] !== undefined) {
          let value = productDataResponse[fieldName]

          // Для чекбоксов преобразуем значение в число
          if (field.xtype === 'xcheckbox' || field.xtype === 'checkbox') {
            const originalValue = value
            // Обрабатываем boolean, string и number
            if (typeof value === 'boolean') {
              value = value ? 1 : 0
            } else if (typeof value === 'string') {
              value = (value === 'true' || value === '1') ? 1 : 0
            } else {
              value = parseInt(value) || 0
            }
            console.log(`[ProductDataFields] Checkbox ${fieldName}: original="${originalValue}" (type: ${typeof originalValue}), converted=${value}`)
          }

          fieldValues.value[fieldName] = value
        } else {
          // Для чекбоксов по умолчанию 0, для остальных null
          if (field.xtype === 'xcheckbox' || field.xtype === 'checkbox') {
            fieldValues.value[fieldName] = 0
          } else {
            fieldValues.value[fieldName] = null
          }
        }
      })

      console.log('[ProductDataFields] All field values:', fieldValues.value)
    } else {
      console.error('[ProductDataFields] Invalid response:', configResponse)
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
  }
}

/**
 * Сохранить данные товара
 */
async function saveProductData() {
  saving.value = true
  try {
    const response = await request.put(
      `/api/mgr/product-data/${props.productId}`,
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

/**
 * Группировка полей по секциям
 * Показываем только !hidden секции
 */
const fieldsBySections = computed(() => {
  const sections = {}

  visibleFields.value.forEach(field => {
    const sectionKey = field.section || 'default'
    const sectionConfig = fieldsConfig.value.sections[sectionKey]

    // Пропускаем скрытые секции
    if (sectionConfig && sectionConfig.hidden === true) {
      return
    }

    if (!sections[sectionKey]) {
      sections[sectionKey] = {
        ...sectionConfig,
        fields: []
      }
    }

    sections[sectionKey].fields.push(field)
  })

  return sections
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
        <span>Данные товара</span>
      </template>

      <template #content>
        <Message v-if="loading" severity="info">
          Загрузка конфигурации полей...
        </Message>

        <Message v-else-if="visibleFields.length === 0" severity="warn">
          Нет видимых полей для отображения. Настройте конфигурацию в разделе Утилиты.
        </Message>

        <div v-else class="sections-container">
          <Fieldset
            v-for="(section, sectionKey) in fieldsBySections"
            :key="sectionKey"
            :legend="section.label || sectionKey"
            :toggleable="true"
            :collapsed="section.collapsed"
            class="section-fieldset"
          >
            <div class="fields-grid">
              <div
                v-for="field in section.fields"
                :key="field.name"
                :class="['field-item', `col-${field.width || 4}`, { 'field-checkbox': field.xtype === 'xcheckbox' || field.xtype === 'checkbox' }]"
              >
                <!-- Checkbox layout: checkbox + label в одну линию -->
                <template v-if="field.xtype === 'xcheckbox' || field.xtype === 'checkbox'">
                  <div class="checkbox-wrapper">
                    <DynamicField
                      :field-config="field"
                      v-model="fieldValues[field.name]"
                      :disabled="loading || saving"
                      @blur="handleFieldChange(field.name, $event.value)"
                    />
                    <label :for="field.name" class="field-label checkbox-label">
                      {{ field.label }}
                      <span v-if="field.required" class="required">*</span>
                    </label>
                  </div>
                  <small v-if="field.description" class="field-description">
                    {{ field.description }}
                  </small>
                </template>

                <!-- Обычное поле: label сверху, поле снизу -->
                <template v-else>
                  <label :for="field.name" class="field-label">
                    {{ field.label }}
                    <span v-if="field.required" class="required">*</span>
                  </label>

                  <DynamicField
                    :field-config="field"
                    v-model="fieldValues[field.name]"
                    :disabled="loading || saving"
                    @blur="handleFieldChange(field.name, $event.value)"
                  />

                  <small v-if="field.description" class="field-description">
                    {{ field.description }}
                  </small>
                </template>
              </div>
            </div>
          </Fieldset>
        </div>
      </template>
    </Card>
  </div>
</template>

<style scoped>
.product-data-fields {
  padding: 20px;
}

.fields-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 20px;
  margin: -10px; /* Компенсация padding у полей */
}

.field-item {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 10px;
  box-sizing: border-box;
}

.field-item :deep(input),
.field-item :deep(textarea),
.field-item :deep(.p-inputtext),
.field-item :deep(.p-inputnumber),
.field-item :deep(.p-dropdown) {
  width: 100%;
}

/* 12-колоночная grid система */
.col-1 { flex: 0 0 calc(8.333% - 20px); max-width: calc(8.333% - 20px); }
.col-2 { flex: 0 0 calc(16.666% - 20px); max-width: calc(16.666% - 20px); }
.col-3 { flex: 0 0 calc(25% - 20px); max-width: calc(25% - 20px); }
.col-4 { flex: 0 0 calc(33.333% - 20px); max-width: calc(33.333% - 20px); }
.col-5 { flex: 0 0 calc(41.666% - 20px); max-width: calc(41.666% - 20px); }
.col-6 { flex: 0 0 calc(50% - 20px); max-width: calc(50% - 20px); }
.col-7 { flex: 0 0 calc(58.333% - 20px); max-width: calc(58.333% - 20px); }
.col-8 { flex: 0 0 calc(66.666% - 20px); max-width: calc(66.666% - 20px); }
.col-9 { flex: 0 0 calc(75% - 20px); max-width: calc(75% - 20px); }
.col-10 { flex: 0 0 calc(83.333% - 20px); max-width: calc(83.333% - 20px); }
.col-11 { flex: 0 0 calc(91.666% - 20px); max-width: calc(91.666% - 20px); }
.col-12 { flex: 0 0 calc(100% - 20px); max-width: calc(100% - 20px); }

/* Responsive: на планшетах col-4 становится col-6 */
@media (max-width: 1024px) {
  .col-4 { flex: 0 0 calc(50% - 20px); max-width: calc(50% - 20px); }
}

/* Responsive: на мобильных все поля на всю ширину */
@media (max-width: 768px) {
  .field-item {
    flex: 0 0 calc(100% - 20px) !important;
    max-width: calc(100% - 20px) !important;
  }
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

/* Чекбокс: горизонтальное расположение */
.checkbox-wrapper {
  display: flex;
  align-items: center;
  gap: 8px;
}

.checkbox-wrapper :deep(.field-wrapper) {
  width: auto;
}

.checkbox-label {
  margin: 0;
  cursor: pointer;
  user-select: none;
}

.field-description {
  color: #666;
  font-size: 12px;
  margin-top: 4px;
}

.sections-container {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.section-fieldset {
  margin-bottom: 0;
}
</style>
