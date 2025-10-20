<script setup>
import { onMounted, ref } from 'vue'
import Card from 'primevue/card'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Checkbox from 'primevue/checkbox'
import Toast from 'primevue/toast'
import { useToast } from 'primevue/usetoast'
import request from '../request.js'

const toast = useToast()

// Состояние
const loading = ref(false)
const saving = ref(false)
const fields = ref([])
const pageKey = 'product_data'

/**
 * Загрузить все поля (включая скрытые)
 */
async function loadFields() {
  loading.value = true
  console.log('[ProductDataConfig] Loading all fields for:', pageKey)

  try {
    const response = await request.get(`/api/mgr/config/page-fields/${pageKey}/all`)
    console.log('[ProductDataConfig] Response:', response)

    if (response && response.fields) {
      // Добавляем поле hidden для чекбоксов (по умолчанию false)
      fields.value = response.fields.map((field, index) => ({
        ...field,
        hidden: field.hidden || false,
        sort_order: field.sort_order || index
      }))

      console.log('[ProductDataConfig] Fields loaded:', fields.value.length)
    } else {
      console.error('[ProductDataConfig] Invalid response:', response)
      toast.add({
        severity: 'error',
        summary: 'Ошибка',
        detail: 'Не удалось загрузить поля',
        life: 5000
      })
    }
  } catch (error) {
    console.error('[ProductDataConfig] Error loading fields:', error)
    toast.add({
      severity: 'error',
      summary: 'Ошибка',
      detail: error.message || 'Ошибка загрузки полей',
      life: 5000
    })
  } finally {
    loading.value = false
  }
}

/**
 * Сохранить конфигурацию
 */
async function saveConfig() {
  saving.value = true

  try {
    // Обновляем sort_order на основе текущего порядка
    const fieldsToSave = fields.value.map((field, index) => ({
      ...field,
      sort_order: index
    }))

    const response = await request.put(
      `/api/mgr/config/page-fields/${pageKey}`,
      { fields: fieldsToSave }
    )

    console.log('[ProductDataConfig] Save response:', response)

    toast.add({
      severity: 'success',
      summary: 'Успешно',
      detail: 'Конфигурация сохранена',
      life: 3000
    })

    // Перезагружаем для синхронизации с БД
    await loadFields()
  } catch (error) {
    console.error('[ProductDataConfig] Error saving:', error)
    toast.add({
      severity: 'error',
      summary: 'Ошибка',
      detail: error.message || 'Ошибка сохранения',
      life: 5000
    })
  } finally {
    saving.value = false
  }
}

/**
 * Обработчик изменения порядка строк
 */
function onRowReorder(event) {
  fields.value = event.value
  toast.add({
    severity: 'info',
    summary: 'Порядок изменён',
    detail: 'Не забудьте сохранить изменения',
    life: 3000
  })
}

/**
 * Переключить видимость поля
 */
function toggleFieldVisibility(field) {
  field.hidden = !field.hidden
}

onMounted(() => {
  loadFields()
})
</script>

<template>
  <div class="product-data-config">
    <h2>Управление полями "Данные товара"</h2>
    <p>Здесь вы можете настроить, какие поля отображаются на вкладке "Данные товара" при редактировании товара</p>

    <Card style="margin-top: 20px;">
      <template #title>
        <div style="display: flex; justify-content: space-between; align-items: center;">
          <span>Поля конфигурации</span>
          <Button
            label="Сохранить изменения"
            icon="pi pi-save"
            @click="saveConfig"
            :loading="saving"
            :disabled="loading"
          />
        </div>
      </template>

      <template #content>
        <DataTable
          :value="fields"
          :loading="loading"
          @rowReorder="onRowReorder"
          tableStyle="min-width: 50rem"
        >
          <Column rowReorder headerStyle="width: 3rem" />

          <Column header="Видимо" style="width: 100px;">
            <template #body="{ data }">
              <Checkbox
                v-model="data.hidden"
                :binary="true"
                :trueValue="false"
                :falseValue="true"
                @change="() => {}"
              />
            </template>
          </Column>

          <Column field="name" header="Поле" style="width: 200px;" />

          <Column field="label" header="Название" style="width: 200px;" />

          <Column field="xtype" header="Тип" style="width: 200px;" />

          <Column field="description" header="Описание" />
        </DataTable>
      </template>
    </Card>

    <Toast />
  </div>
</template>

<style scoped>
.product-data-config {
  padding: 20px;
}

h2 {
  margin: 0 0 10px 0;
  font-size: 24px;
}

p {
  margin: 0 0 20px 0;
  color: #666;
}
</style>
