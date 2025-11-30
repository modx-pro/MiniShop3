<script setup>
/**
 * ActionsColumn - Универсальный компонент для колонки действий в гридах
 *
 * Рендерит кнопки действий на основе конфигурации.
 * Поддерживает встроенные и кастомные обработчики через MS3ActionRegistry.
 *
 * Использование:
 * <ActionsColumn
 *   :data="rowData"
 *   :actions="actionsConfig"
 *   :grid-id="'customers'"
 *   @edit="handleEdit"
 *   @delete="handleDelete"
 *   @refresh="loadData"
 * />
 *
 * Конфигурация действия:
 * {
 *   name: 'edit',           // Имя (идентификатор)
 *   handler: 'edit',        // Обработчик из реестра
 *   icon: 'pi-pencil',      // Иконка PrimeIcons
 *   label: 'edit',          // Ключ лексикона или текст
 *   severity: null,         // PrimeVue severity: danger, secondary, success, etc.
 *   confirm: false,         // Требуется подтверждение
 *   confirmMessage: '...',  // Сообщение подтверждения (ключ лексикона)
 *   permission: 'ms3_save', // Право доступа (опционально)
 *   visible: true,          // Видимость кнопки
 *   disabled: false         // Отключена
 * }
 */
import { computed } from 'vue'
import Button from 'primevue/button'
import { useActions } from '../composables/useActions.js'
import { useLexicon } from '../composables/useLexicon.js'

const props = defineProps({
  /**
   * Данные строки грида
   */
  data: {
    type: Object,
    required: true
  },

  /**
   * Массив конфигураций действий
   */
  actions: {
    type: Array,
    default: () => []
  },

  /**
   * Идентификатор грида
   */
  gridId: {
    type: String,
    default: 'unknown'
  },

  /**
   * Показывать только иконки (без текста)
   */
  iconOnly: {
    type: Boolean,
    default: true
  },

  /**
   * Размер кнопок: 'small', 'normal', 'large'
   */
  size: {
    type: String,
    default: 'small'
  }
})

const emit = defineEmits(['edit', 'delete', 'view', 'addresses', 'refresh', 'action'])

const { _ } = useLexicon()

// Создаём контекст действий
const { executeAction } = useActions({
  gridId: props.gridId,
  onRefresh: () => emit('refresh'),
  onEdit: (data) => emit('edit', data),
  onDelete: (data) => emit('delete', data),
  onView: (data) => emit('view', data),
  onAddresses: (data) => emit('addresses', data),
  onCustomAction: (event, data) => emit('action', { name: event, data })
})

/**
 * Дефолтные конфигурации для встроенных действий
 */
const defaultActionConfigs = {
  edit: {
    icon: 'pi-pencil',
    label: 'edit',
    severity: null,
    confirm: false
  },
  delete: {
    icon: 'pi-trash',
    label: 'delete',
    severity: 'danger',
    confirm: true,
    confirmMessage: 'action_delete_confirm'
  },
  view: {
    icon: 'pi-eye',
    label: 'view',
    severity: 'secondary',
    confirm: false
  },
  addresses: {
    icon: 'pi-map-marker',
    label: 'addresses',
    severity: 'secondary',
    confirm: false
  }
}

/**
 * Обработанные действия с применением дефолтов
 */
const processedActions = computed(() => {
  return props.actions
    .filter(action => action.visible !== false)
    .map(action => {
      const handlerName = action.handler || action.name
      const defaults = defaultActionConfigs[handlerName] || {}

      return {
        ...defaults,
        ...action,
        handler: handlerName,
        // Формируем полную иконку
        iconClass: `pi ${action.icon || defaults.icon || 'pi-cog'}`,
        // Получаем label из лексикона
        displayLabel: _(action.label || defaults.label || action.name),
        // Проверяем disabled на основе данных
        isDisabled: checkDisabled(action)
      }
    })
})

/**
 * Проверка, отключена ли кнопка
 */
function checkDisabled(action) {
  if (action.disabled === true) return true
  if (typeof action.disabled === 'function') {
    return action.disabled(props.data)
  }
  // Проверка по полю данных
  if (action.disabledField && props.data[action.disabledField]) {
    return true
  }
  return false
}

/**
 * Обработчик клика по кнопке действия
 */
async function handleActionClick(action) {
  if (action.isDisabled) return

  try {
    // Эмитим общее событие action с деталями
    emit('action', {
      name: action.name,
      handler: action.handler,
      data: props.data
    })

    // Выполняем действие через реестр
    await executeAction(action.handler, props.data, action)
  } catch (error) {
    console.error(`[ActionsColumn] Error executing action "${action.name}":`, error)
  }
}

/**
 * Получить CSS классы для кнопки
 */
function getButtonClasses(action) {
  const classes = ['p-button-text']

  if (props.size === 'small') classes.push('p-button-sm')
  if (props.size === 'large') classes.push('p-button-lg')

  return classes.join(' ')
}
</script>

<template>
  <div class="actions-column">
    <Button
      v-for="action in processedActions"
      :key="action.name"
      :icon="action.iconClass"
      :label="iconOnly ? undefined : action.displayLabel"
      :title="action.displayLabel"
      :severity="action.severity"
      :class="getButtonClasses(action)"
      :disabled="action.isDisabled"
      text
      @click="handleActionClick(action)"
    />
  </div>
</template>

<style scoped>
.actions-column {
  display: flex;
  gap: 0.25rem;
  align-items: center;
  flex-wrap: nowrap;
}

.actions-column :deep(.p-button) {
  padding: 0.25rem 0.5rem;
}

.actions-column :deep(.p-button-sm) {
  font-size: 0.875rem;
}
</style>
