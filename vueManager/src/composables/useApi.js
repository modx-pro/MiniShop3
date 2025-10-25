/**
 * Composable для работы с API через улучшенный Request класс
 *
 * Предоставляет:
 * - Реактивное состояние загрузки (loading)
 * - Обработку ошибок (error)
 * - Удобные методы для CRUD операций
 * - Автоматическую обработку MODX processor ответов
 *
 * Пример использования:
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
   * Обертка для выполнения запроса с обработкой состояния
   *
   * @param {Function} requestFn - Функция запроса
   * @returns {Promise<any>} - Результат запроса
   */
  const executeRequest = async (requestFn) => {
    loading.value = true;
    error.value = null;

    try {
      const response = await requestFn();

      // Если это MODX processor ответ с полем data - возвращаем его
      if (response.object && response.object.data !== undefined) {
        return response.object.data;
      }

      // Если есть поле data на верхнем уровне
      if (response.data !== undefined) {
        return response.data;
      }

      // Иначе возвращаем весь ответ
      return response;

    } catch (err) {
      error.value = err;

      // Логируем ошибку для отладки
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
   * GET запрос
   */
  const get = (route, params = null, options = {}) => {
    return executeRequest(() => request.get(route, params, options));
  };

  /**
   * POST запрос
   */
  const post = (route, data = null, options = {}) => {
    return executeRequest(() => request.post(route, data, options));
  };

  /**
   * PUT запрос
   */
  const put = (route, data = null, options = {}) => {
    return executeRequest(() => request.put(route, data, options));
  };

  /**
   * DELETE запрос
   */
  const del = (route, data = null, options = {}) => {
    return executeRequest(() => request.delete(route, data, options));
  };

  /**
   * PATCH запрос
   */
  const patch = (route, data = null, options = {}) => {
    return executeRequest(() => request.patch(route, data, options));
  };

  /**
   * Сброс состояния ошибки
   */
  const clearError = () => {
    error.value = null;
  };

  /**
   * Проверка типа ошибки
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
    // Состояние
    loading,
    error,

    // Методы запросов
    get,
    post,
    put,
    delete: del, // delete - зарезервированное слово
    patch,

    // Утилиты
    clearError,
    isUnauthorized,
    isForbidden,
    isValidationError
  };
}
