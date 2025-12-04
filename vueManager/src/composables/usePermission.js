/**
 * Composable for working with MODX access permissions
 *
 * Provides methods for checking user permissions
 *
 * Usage example:
 * ```js
 * const { hasPermission, canCreate, canEdit, canDelete } = usePermission();
 *
 * if (hasPermission('save_document')) {
 *   // User can save documents
 * }
 *
 * if (canEdit()) {
 *   // User can edit
 * }
 * ```
 */

import { computed } from 'vue';
import { useModx } from './useModx';

export function usePermission() {
  const { config, isUserAdmin } = useModx();

  /**
   * Get user permissions list
   */
  const permissions = computed(() => {
    return config.permissions || {};
  });

  /**
   * Check if specific permission exists
   *
   * @param {string} permission - Permission name
   * @returns {boolean} - Whether permission exists
   */
  const hasPermission = (permission) => {
    if (isUserAdmin.value) {
      return true;
    }

    return permissions.value[permission] === true || permissions.value[permission] === 1;
  };

  /**
   * Check if at least one permission from list exists
   *
   * @param {string[]} permissionList - Array of permission names
   * @returns {boolean} - Whether at least one permission exists
   */
  const hasAnyPermission = (permissionList) => {
    if (isUserAdmin.value) {
      return true;
    }

    return permissionList.some(permission => hasPermission(permission));
  };

  /**
   * Check if all permissions from list exist
   *
   * @param {string[]} permissionList - Array of permission names
   * @returns {boolean} - Whether all permissions exist
   */
  const hasAllPermissions = (permissionList) => {
    if (isUserAdmin.value) {
      return true;
    }

    return permissionList.every(permission => hasPermission(permission));
  };

  /**
   * Can user create
   */
  const canCreate = () => {
    return hasPermission('new_document') || hasPermission('msproduct_save');
  };

  /**
   * Can user edit
   */
  const canEdit = () => {
    return hasPermission('save_document') || hasPermission('msproduct_save');
  };

  /**
   * Can user delete
   */
  const canDelete = () => {
    return hasPermission('delete_document') || hasPermission('msproduct_remove');
  };

  /**
   * Can user publish
   */
  const canPublish = () => {
    return hasPermission('publish_document');
  };

  /**
   * Can user unpublish
   */
  const canUnpublish = () => {
    return hasPermission('unpublish_document');
  };

  /**
   * Can user manage settings
   */
  const canManageSettings = () => {
    return hasPermission('settings') || hasPermission('mssetting_save');
  };

  /**
   * Can user manage orders
   */
  const canManageOrders = () => {
    return hasPermission('msorder_list') || hasPermission('msorder_save');
  };

  /**
   * Can user view orders
   */
  const canViewOrders = () => {
    return hasPermission('msorder_list') || hasPermission('msorder_view');
  };

  /**
   * Get list of all available permissions
   */
  const getAvailablePermissions = () => {
    return Object.keys(permissions.value).filter(key => permissions.value[key]);
  };

  return {
    hasPermission,
    hasAnyPermission,
    hasAllPermissions,

    canCreate,
    canEdit,
    canDelete,
    canPublish,
    canUnpublish,

    canManageSettings,
    canManageOrders,
    canViewOrders,

    getAvailablePermissions,
    isUserAdmin
  };
}
