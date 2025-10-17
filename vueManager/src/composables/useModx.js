/**
 * Composable для работы с MODX интеграцией
 *
 * Предоставляет доступ к:
 * - MODX конфигурации (window.MODx)
 * - MiniShop3 конфигурации (window.ms3)
 * - Лексиконам (переводы)
 * - Системной информации
 *
 * Пример использования:
 * ```js
 * const { config, lexicon, ms3Config, isUserAdmin } = useModx();
 *
 * console.log(config.user_id); // ID текущего пользователя
 * console.log(lexicon('ms3_product_name')); // Получить перевод
 * ```
 */

import { ref, computed, readonly } from 'vue';

export function useModx() {
  // MODX конфигурация (readonly)
  const config = readonly(ref(window.MODx?.config || {}));

  // MiniShop3 конфигурация (readonly)
  const ms3Config = readonly(ref(window.ms3?.config || {}));

  /**
   * Получить значение из лексикона (перевод)
   *
   * @param {string} key - Ключ лексикона
   * @param {Object} placeholders - Плейсхолдеры для подстановки
   * @returns {string} - Переведенная строка
   */
  const lexicon = (key, placeholders = {}) => {
    // Если доступен MODx._
    if (window.MODx?._ && typeof window.MODx._ === 'function') {
      return window.MODx._(key, placeholders);
    }

    // Fallback на прямой доступ к лексикону
    let value = window.MODx?.lang?.[key] || key;

    // Подставляем плейсхолдеры
    Object.entries(placeholders).forEach(([placeholder, val]) => {
      value = value.replace(`[[+${placeholder}]]`, val);
    });

    return value;
  };

  /**
   * Получить значение из системных настроек
   *
   * @param {string} key - Ключ настройки
   * @param {any} defaultValue - Значение по умолчанию
   * @returns {any} - Значение настройки
   */
  const getConfig = (key, defaultValue = null) => {
    return config.value[key] ?? defaultValue;
  };

  /**
   * Получить значение из MiniShop3 конфигурации
   *
   * @param {string} key - Ключ настройки
   * @param {any} defaultValue - Значение по умолчанию
   * @returns {any} - Значение настройки
   */
  const getMs3Config = (key, defaultValue = null) => {
    return ms3Config.value[key] ?? defaultValue;
  };

  // Computed свойства для часто используемых данных
  const userId = computed(() => config.value.user_id || null);
  const userName = computed(() => config.value.username || 'Guest');
  const contextKey = computed(() => config.value.context_key || 'mgr');
  const connectorUrl = computed(() => ms3Config.value.connector_url || '');
  const assetsUrl = computed(() => ms3Config.value.assetsUrl || '');

  /**
   * Проверка является ли пользователь администратором
   */
  const isUserAdmin = computed(() => {
    return config.value.is_admin === true || config.value.is_admin === 1;
  });

  /**
   * Проверка является ли контекст mgr
   */
  const isMgrContext = computed(() => {
    return contextKey.value === 'mgr';
  });

  /**
   * Показать MODX сообщение (если доступно)
   *
   * @param {string} message - Текст сообщения
   * @param {string} type - Тип: success, error, warning, info
   */
  const showMessage = (message, type = 'info') => {
    if (window.MODx && typeof window.MODx.msg === 'object') {
      switch (type) {
        case 'success':
          window.MODx.msg.status({ title: lexicon('success'), message });
          break;
        case 'error':
          window.MODx.msg.alert(lexicon('error'), message);
          break;
        case 'warning':
          window.MODx.msg.alert(lexicon('warning'), message);
          break;
        default:
          window.MODx.msg.alert(lexicon('info'), message);
      }
    } else {
      // Fallback на alert
      alert(`[${type.toUpperCase()}] ${message}`);
    }
  };

  /**
   * Показать диалог подтверждения
   *
   * @param {string} message - Текст сообщения
   * @param {Function} callback - Функция при подтверждении
   */
  const confirm = (message, callback) => {
    if (window.MODx && typeof window.MODx.msg.confirm === 'function') {
      window.MODx.msg.confirm({
        title: lexicon('confirm'),
        text: message,
        fn: (result) => {
          if (result === 'yes') {
            callback();
          }
        }
      });
    } else {
      // Fallback на встроенный confirm
      if (window.confirm(message)) {
        callback();
      }
    }
  };

  /**
   * Логирование (отладка)
   *
   * @param {...any} args - Аргументы для вывода
   */
  const log = (...args) => {
    if (config.value.debug === true || config.value.debug === 1) {
      console.log('[MODX]', ...args);
    }
  };

  return {
    // Конфигурация
    config: config.value,
    ms3Config: ms3Config.value,

    // Методы
    lexicon,
    getConfig,
    getMs3Config,

    // Computed свойства
    userId,
    userName,
    contextKey,
    connectorUrl,
    assetsUrl,
    isUserAdmin,
    isMgrContext,

    // UI утилиты
    showMessage,
    confirm,
    log
  };
}
