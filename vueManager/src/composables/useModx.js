/**
 * Composable for MODX integration
 *
 * Provides access to:
 * - MODX configuration (window.MODx)
 * - MiniShop3 configuration (window.ms3)
 * - Lexicon (translations)
 * - System information
 *
 * Usage example:
 * ```js
 * const { config, lexicon, ms3Config, isUserAdmin } = useModx();
 *
 * console.log(config.user_id); // Current user ID
 * console.log(lexicon('ms3_product_name')); // Get translation
 * ```
 */

import { ref, computed, readonly } from 'vue';

export function useModx() {
  const config = readonly(ref(window.MODx?.config || {}));

  const ms3Config = readonly(ref(window.ms3?.config || {}));

  /**
   * Get value from lexicon (translation)
   *
   * @param {string} key - Lexicon key
   * @param {Object} placeholders - Placeholders for substitution
   * @returns {string} - Translated string
   */
  const lexicon = (key, placeholders = {}) => {
    if (window.MODx?._ && typeof window.MODx._ === 'function') {
      return window.MODx._(key, placeholders);
    }

    let value = window.MODx?.lang?.[key] || key;

    Object.entries(placeholders).forEach(([placeholder, val]) => {
      value = value.replace(`[[+${placeholder}]]`, val);
    });

    return value;
  };

  /**
   * Get value from system settings
   *
   * @param {string} key - Setting key
   * @param {any} defaultValue - Default value
   * @returns {any} - Setting value
   */
  const getConfig = (key, defaultValue = null) => {
    return config.value[key] ?? defaultValue;
  };

  /**
   * Get value from MiniShop3 configuration
   *
   * @param {string} key - Setting key
   * @param {any} defaultValue - Default value
   * @returns {any} - Setting value
   */
  const getMs3Config = (key, defaultValue = null) => {
    return ms3Config.value[key] ?? defaultValue;
  };

  const userId = computed(() => config.value.user_id || null);
  const userName = computed(() => config.value.username || 'Guest');
  const contextKey = computed(() => config.value.context_key || 'mgr');
  const connectorUrl = computed(() => ms3Config.value.connector_url || '');
  const assetsUrl = computed(() => ms3Config.value.assetsUrl || '');

  /**
   * Check if user is administrator
   */
  const isUserAdmin = computed(() => {
    return config.value.is_admin === true || config.value.is_admin === 1;
  });

  /**
   * Check if context is mgr
   */
  const isMgrContext = computed(() => {
    return contextKey.value === 'mgr';
  });

  /**
   * Show MODX message (if available)
   *
   * @param {string} message - Message text
   * @param {string} type - Type: success, error, warning, info
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
      alert(`[${type.toUpperCase()}] ${message}`);
    }
  };

  /**
   * Show confirmation dialog
   *
   * @param {string} message - Message text
   * @param {Function} callback - Callback on confirmation
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
      if (window.confirm(message)) {
        callback();
      }
    }
  };

  /**
   * Logging (debug)
   *
   * @param {...any} args - Arguments to output
   */
  const log = (...args) => {
    if (config.value.debug === true || config.value.debug === 1) {
      console.log('[MODX]', ...args);
    }
  };

  return {
    config: config.value,
    ms3Config: ms3Config.value,

    lexicon,
    getConfig,
    getMs3Config,

    userId,
    userName,
    contextKey,
    connectorUrl,
    assetsUrl,
    isUserAdmin,
    isMgrContext,

    showMessage,
    confirm,
    log
  };
}
