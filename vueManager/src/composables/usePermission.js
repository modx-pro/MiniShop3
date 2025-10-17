/**
 * Composable для работы с правами доступа MODX
 *
 * Предоставляет методы для проверки пермишенов пользователя
 *
 * Пример использования:
 * ```js
 * const { hasPermission, canCreate, canEdit, canDelete } = usePermission();
 *
 * if (hasPermission('save_document')) {
 *   // Пользователь может сохранять документы
 * }
 *
 * if (canEdit()) {
 *   // Пользователь может редактировать
 * }
 * ```
 */

import { computed } from 'vue';
import { useModx } from './useModx';

export function usePermission() {
  const { config, isUserAdmin } = useModx();

  /**
   * Получить список разрешений пользователя
   */
  const permissions = computed(() => {
    return config.permissions || {};
  });

  /**
   * Проверить наличие конкретного пермишена
   *
   * @param {string} permission - Название пермишена
   * @returns {boolean} - Есть ли разрешение
   */
  const hasPermission = (permission) => {
    // Админы имеют все права
    if (isUserAdmin.value) {
      return true;
    }

    // Проверяем в списке разрешений
    return permissions.value[permission] === true || permissions.value[permission] === 1;
  };

  /**
   * Проверить наличие хотя бы одного из списка пермишенов
   *
   * @param {string[]} permissionList - Массив названий пермишенов
   * @returns {boolean} - Есть ли хотя бы одно разрешение
   */
  const hasAnyPermission = (permissionList) => {
    if (isUserAdmin.value) {
      return true;
    }

    return permissionList.some(permission => hasPermission(permission));
  };

  /**
   * Проверить наличие всех пермишенов из списка
   *
   * @param {string[]} permissionList - Массив названий пермишенов
   * @returns {boolean} - Есть ли все разрешения
   */
  const hasAllPermissions = (permissionList) => {
    if (isUserAdmin.value) {
      return true;
    }

    return permissionList.every(permission => hasPermission(permission));
  };

  // Стандартные пермишены для MiniShop3

  /**
   * Может ли пользователь создавать
   */
  const canCreate = () => {
    return hasPermission('new_document') || hasPermission('msproduct_save');
  };

  /**
   * Может ли пользователь редактировать
   */
  const canEdit = () => {
    return hasPermission('save_document') || hasPermission('msproduct_save');
  };

  /**
   * Может ли пользователь удалять
   */
  const canDelete = () => {
    return hasPermission('delete_document') || hasPermission('msproduct_remove');
  };

  /**
   * Может ли пользователь публиковать
   */
  const canPublish = () => {
    return hasPermission('publish_document');
  };

  /**
   * Может ли пользователь снимать с публикации
   */
  const canUnpublish = () => {
    return hasPermission('unpublish_document');
  };

  /**
   * Может ли пользователь работать с настройками
   */
  const canManageSettings = () => {
    return hasPermission('settings') || hasPermission('mssetting_save');
  };

  /**
   * Может ли пользователь работать с заказами
   */
  const canManageOrders = () => {
    return hasPermission('msorder_list') || hasPermission('msorder_save');
  };

  /**
   * Может ли пользователь видеть заказы
   */
  const canViewOrders = () => {
    return hasPermission('msorder_list') || hasPermission('msorder_view');
  };

  /**
   * Получить список всех доступных пермишенов
   */
  const getAvailablePermissions = () => {
    return Object.keys(permissions.value).filter(key => permissions.value[key]);
  };

  return {
    // Базовые методы
    hasPermission,
    hasAnyPermission,
    hasAllPermissions,

    // CRUD операции
    canCreate,
    canEdit,
    canDelete,
    canPublish,
    canUnpublish,

    // MiniShop3 специфичные
    canManageSettings,
    canManageOrders,
    canViewOrders,

    // Утилиты
    getAvailablePermissions,
    isUserAdmin
  };
}
