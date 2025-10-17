/**
 * Pinia Store для работы с продуктами MiniShop3
 *
 * Пример использования в компоненте:
 * ```js
 * import { useProductStore } from '@/stores/useProductStore';
 *
 * const productStore = useProductStore();
 *
 * // Загрузить список продуктов
 * await productStore.fetchProducts({ limit: 20 });
 *
 * // Получить продукт по ID
 * await productStore.fetchProduct(123);
 *
 * // Создать продукт
 * await productStore.createProduct({ pagetitle: 'New Product', price: 100 });
 * ```
 */

import { defineStore } from 'pinia';
import { useApi } from '../composables/useApi';

export const useProductStore = defineStore('product', {
  state: () => ({
    // Список продуктов
    products: [],
    total: 0,

    // Текущий продукт
    currentProduct: null,

    // Состояния загрузки
    loading: false,
    error: null
  }),

  getters: {
    /**
     * Получить продукт по ID из state
     */
    getProductById: (state) => (id) => {
      return state.products.find(product => product.id === id);
    },

    /**
     * Проверка есть ли загруженные продукты
     */
    hasProducts: (state) => state.products.length > 0,

    /**
     * Проверка загружен ли текущий продукт
     */
    hasCurrentProduct: (state) => state.currentProduct !== null
  },

  actions: {
    /**
     * Загрузить список продуктов
     *
     * @param {Object} params - Параметры фильтрации (limit, offset, query, category_id и т.д.)
     * @returns {Promise<void>}
     */
    async fetchProducts(params = {}) {
      this.loading = true;
      this.error = null;

      try {
        const { get } = useApi();
        const response = await get('/api/mgr/products', params);

        // Предполагаем что API возвращает { results: [], total: N }
        if (response.results) {
          this.products = response.results;
          this.total = response.total || response.results.length;
        } else if (Array.isArray(response)) {
          this.products = response;
          this.total = response.length;
        } else {
          this.products = [];
          this.total = 0;
        }

      } catch (error) {
        this.error = error;
        console.error('[ProductStore] Failed to fetch products:', error);
        throw error;
      } finally {
        this.loading = false;
      }
    },

    /**
     * Загрузить один продукт по ID
     *
     * @param {number} id - ID продукта
     * @returns {Promise<void>}
     */
    async fetchProduct(id) {
      this.loading = true;
      this.error = null;

      try {
        const { get } = useApi();
        const response = await get(`/api/mgr/products/${id}`);

        this.currentProduct = response;

        // Также добавляем/обновляем в списке
        const index = this.products.findIndex(p => p.id === id);
        if (index !== -1) {
          this.products[index] = response;
        } else {
          this.products.push(response);
        }

      } catch (error) {
        this.error = error;
        console.error('[ProductStore] Failed to fetch product:', error);
        throw error;
      } finally {
        this.loading = false;
      }
    },

    /**
     * Создать новый продукт
     *
     * @param {Object} data - Данные продукта
     * @returns {Promise<Object>} - Созданный продукт
     */
    async createProduct(data) {
      this.loading = true;
      this.error = null;

      try {
        const { post } = useApi();
        const response = await post('/api/mgr/products', data);

        // Добавляем в список
        if (response.id) {
          this.products.unshift(response);
          this.total++;
        }

        return response;

      } catch (error) {
        this.error = error;
        console.error('[ProductStore] Failed to create product:', error);
        throw error;
      } finally {
        this.loading = false;
      }
    },

    /**
     * Обновить существующий продукт
     *
     * @param {number} id - ID продукта
     * @param {Object} data - Данные для обновления
     * @returns {Promise<Object>} - Обновленный продукт
     */
    async updateProduct(id, data) {
      this.loading = true;
      this.error = null;

      try {
        const { put } = useApi();
        const response = await put(`/api/mgr/products/${id}`, data);

        // Обновляем в списке
        const index = this.products.findIndex(p => p.id === id);
        if (index !== -1) {
          this.products[index] = response;
        }

        // Обновляем текущий продукт если это он
        if (this.currentProduct?.id === id) {
          this.currentProduct = response;
        }

        return response;

      } catch (error) {
        this.error = error;
        console.error('[ProductStore] Failed to update product:', error);
        throw error;
      } finally {
        this.loading = false;
      }
    },

    /**
     * Удалить продукт
     *
     * @param {number} id - ID продукта
     * @returns {Promise<void>}
     */
    async deleteProduct(id) {
      this.loading = true;
      this.error = null;

      try {
        const { delete: del } = useApi();
        await del(`/api/mgr/products/${id}`);

        // Удаляем из списка
        const index = this.products.findIndex(p => p.id === id);
        if (index !== -1) {
          this.products.splice(index, 1);
          this.total--;
        }

        // Очищаем текущий продукт если это он
        if (this.currentProduct?.id === id) {
          this.currentProduct = null;
        }

      } catch (error) {
        this.error = error;
        console.error('[ProductStore] Failed to delete product:', error);
        throw error;
      } finally {
        this.loading = false;
      }
    },

    /**
     * Очистить текущий продукт
     */
    clearCurrentProduct() {
      this.currentProduct = null;
    },

    /**
     * Очистить ошибки
     */
    clearError() {
      this.error = null;
    },

    /**
     * Сбросить весь state
     */
    $reset() {
      this.products = [];
      this.total = 0;
      this.currentProduct = null;
      this.loading = false;
      this.error = null;
    }
  }
});
