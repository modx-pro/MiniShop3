/**
 * Pinia Store for working with MiniShop3 products
 *
 * Usage example in component:
 * ```js
 * import { useProductStore } from '@/stores/useProductStore';
 *
 * const productStore = useProductStore();
 *
 * // Fetch product list
 * await productStore.fetchProducts({ limit: 20 });
 *
 * // Fetch product by ID
 * await productStore.fetchProduct(123);
 *
 * // Create product
 * await productStore.createProduct({ pagetitle: 'New Product', price: 100 });
 * ```
 */

import { defineStore } from 'pinia';
import { useApi } from '@vuetools/useApi';

export const useProductStore = defineStore('product', {
  state: () => ({
    products: [],
    total: 0,
    currentProduct: null,
    loading: false,
    error: null,
  }),

  getters: {
    /**
     * Get product by ID from state
     */
    getProductById: (state) => (id) => {
      return state.products.find(product => product.id === id);
    },

    /**
     * Check if products are loaded
     */
    hasProducts: (state) => state.products.length > 0,

    /**
     * Check if current product is loaded
     */
    hasCurrentProduct: (state) => state.currentProduct !== null,
  },

  actions: {
    /**
     * Fetch product list
     *
     * @param {Object} params - Filter parameters (limit, offset, query, category_id, etc.)
     * @returns {Promise<void>}
     */
    async fetchProducts(params = {}) {
      this.loading = true;
      this.error = null;

      try {
        const { get } = useApi();
        const response = await get('/api/mgr/products', params);

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
     * Fetch single product by ID
     *
     * @param {number} id - Product ID
     * @returns {Promise<void>}
     */
    async fetchProduct(id) {
      this.loading = true;
      this.error = null;

      try {
        const { get } = useApi();
        const response = await get(`/api/mgr/products/${id}`);

        this.currentProduct = response;

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
     * Create new product
     *
     * @param {Object} data - Product data
     * @returns {Promise<Object>} - Created product
     */
    async createProduct(data) {
      this.loading = true;
      this.error = null;

      try {
        const { post } = useApi();
        const response = await post('/api/mgr/products', data);

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
     * Update existing product
     *
     * @param {number} id - Product ID
     * @param {Object} data - Data to update
     * @returns {Promise<Object>} - Updated product
     */
    async updateProduct(id, data) {
      this.loading = true;
      this.error = null;

      try {
        const { put } = useApi();
        const response = await put(`/api/mgr/products/${id}`, data);

        const index = this.products.findIndex(p => p.id === id);
        if (index !== -1) {
          this.products[index] = response;
        }

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
     * Delete product
     *
     * @param {number} id - Product ID
     * @returns {Promise<void>}
     */
    async deleteProduct(id) {
      this.loading = true;
      this.error = null;

      try {
        const { delete: del } = useApi();
        await del(`/api/mgr/products/${id}`);

        const index = this.products.findIndex(p => p.id === id);
        if (index !== -1) {
          this.products.splice(index, 1);
          this.total--;
        }

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
     * Clear current product
     */
    clearCurrentProduct() {
      this.currentProduct = null;
    },

    /**
     * Clear errors
     */
    clearError() {
      this.error = null;
    },

    /**
     * Reset entire state
     */
    $reset() {
      this.products = [];
      this.total = 0;
      this.currentProduct = null;
      this.loading = false;
      this.error = null;
    },
  },
});
