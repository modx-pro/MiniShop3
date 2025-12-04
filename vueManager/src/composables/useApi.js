/**
 * Composable for working with API through enhanced Request class
 *
 * Provides:
 * - Reactive loading state
 * - Error handling
 * - Convenient methods for CRUD operations
 * - Automatic MODX processor response handling
 *
 * Usage example:
 * ```js
 * const { get, post, loading, error } = useApi();
 *
 * const products = await get('/api/mgr/products');
 * await post('/api/mgr/products', { pagetitle: 'New Product' });
 * ```
 */

import { ref } from 'vue';
import request, { RequestError } from '../request.js';

export function useApi() {
  const loading = ref(false);
  const error = ref(null);

  /**
   * Wrapper for executing request with state handling
   *
   * @param {Function} requestFn - Request function
   * @returns {Promise<any>} - Request result
   */
  const executeRequest = async (requestFn) => {
    loading.value = true;
    error.value = null;

    try {
      const response = await requestFn();

      if (response.object && response.object.data !== undefined) {
        return response.object.data;
      }

      if (response.data !== undefined) {
        return response.data;
      }

      return response;

    } catch (err) {
      error.value = err;

      console.error('[useApi] Request failed:', {
        message: err.message,
        status: err.statusCode,
        data: err.data
      });

      throw err;

    } finally {
      loading.value = false;
    }
  };

  /**
   * GET request
   */
  const get = (route, params = null, options = {}) => {
    return executeRequest(() => request.get(route, params, options));
  };

  /**
   * POST request
   */
  const post = (route, data = null, options = {}) => {
    return executeRequest(() => request.post(route, data, options));
  };

  /**
   * PUT request
   */
  const put = (route, data = null, options = {}) => {
    return executeRequest(() => request.put(route, data, options));
  };

  /**
   * DELETE request
   */
  const del = (route, data = null, options = {}) => {
    return executeRequest(() => request.delete(route, data, options));
  };

  /**
   * PATCH request
   */
  const patch = (route, data = null, options = {}) => {
    return executeRequest(() => request.patch(route, data, options));
  };

  /**
   * Clear error state
   */
  const clearError = () => {
    error.value = null;
  };

  /**
   * Check error type
   */
  const isUnauthorized = () => {
    return error.value instanceof RequestError && error.value.isUnauthorized();
  };

  const isForbidden = () => {
    return error.value instanceof RequestError && error.value.isForbidden();
  };

  const isValidationError = () => {
    return error.value instanceof RequestError && error.value.isValidationError();
  };

  return {
    loading,
    error,

    get,
    post,
    put,
    delete: del,
    patch,

    clearError,
    isUnauthorized,
    isForbidden,
    isValidationError
  };
}
