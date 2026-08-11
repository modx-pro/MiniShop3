/**
 * Product CRUD dialog state (edit / add / delete) for the order view.
 *
 * @param {Object} deps
 * @param {Function} deps._
 * @param {Object} deps.toast
 * @param {Object} deps.confirm
 * @param {import('vue').ComputedRef} deps.orderId
 * @param {import('vue').Ref} deps.products
 * @param {import('vue').Ref} deps.editingProduct
 * @param {Function} deps.loadProducts
 * @param {Function} deps.loadOrder
 * @param {Object} deps.options
 */
import { computed, ref } from 'vue'

import request from '../request.js'

export function useOrderProducts(deps) {
  const { _, toast, confirm, orderId, products, editingProduct, loadProducts, loadOrder, options } =
    deps

  const editProductDialogVisible = ref(false)
  const editProductForm = ref({
    count: 1,
    price: 0,
    weight: 0,
  })
  const savingProduct = ref(false)

  const addProductDialogVisible = ref(false)
  const selectedProduct = ref(null)
  const productSuggestions = ref([])
  const searchingProducts = ref(false)
  const addProductForm = ref({
    count: 1,
    price: 0,
    weight: 0,
    options: {},
  })
  const savingNewProduct = ref(false)

  function productLineCost(form) {
    return (form.count || 0) * (form.price || 0)
  }

  const editedProductCost = computed(() => productLineCost(editProductForm.value))
  const newProductCost = computed(() => productLineCost(addProductForm.value))

  /**
   * Handle product action (edit, delete)
   */
  function handleProductAction(action, data) {
    switch (action.handler) {
      case 'edit':
        editProduct(data)
        break
      case 'delete':
        deleteProduct(data)
        break
      default:
        console.warn('[OrderView] Unknown action handler:', action.handler)
    }
  }

  /**
   * Open edit product dialog
   */
  async function editProduct(product) {
    editingProduct.value = product
    editProductForm.value = {
      count: product.count || 1,
      price: product.price || 0,
      weight: product.weight || 0,
    }

    if (options.productOptionFields.value.length === 0) {
      await options.loadProductOptionFields()
    }

    await options.initOptionsFromProduct(product.options)
    options.optionsEditMode.value = 'table'
    options.optionsJsonError.value = ''

    editProductDialogVisible.value = true
  }

  /**
   * Save edited product
   */
  async function saveEditedProduct() {
    if (!editingProduct.value) return

    if (options.optionsEditMode.value === 'json' && options.optionsJsonText.value.trim()) {
      try {
        JSON.parse(options.optionsJsonText.value)
      } catch (e) {
        options.optionsJsonError.value = _('options_json_invalid') + ': ' + e.message
        return
      }
    }

    savingProduct.value = true

    try {
      const data = {
        ...editProductForm.value,
        options: options.getOptionsForSave(),
      }

      await request.put(
        `/api/mgr/orders/${orderId.value}/products/${editingProduct.value.id}`,
        data
      )

      toast.add({
        severity: 'success',
        summary: _('success'),
        detail: _('order_product_saved'),
        life: 3000,
      })

      editProductDialogVisible.value = false

      await Promise.all([loadProducts(), loadOrder()])
    } catch (error) {
      console.error('[OrderView] Error saving product:', error)
      toast.add({
        severity: 'error',
        summary: _('error'),
        detail: error.message || _('error_saving_data'),
        life: 5000,
      })
    } finally {
      savingProduct.value = false
    }
  }

  /**
   * Cancel product editing
   */
  function cancelEditProduct() {
    editProductDialogVisible.value = false
    editingProduct.value = null
  }

  /**
   * Open add product dialog
   */
  function openAddProductDialog() {
    selectedProduct.value = null
    productSuggestions.value = []
    addProductForm.value = {
      count: 1,
      price: 0,
      weight: 0,
      options: {},
    }
    addProductDialogVisible.value = true
  }

  /**
   * Search products for autocomplete
   */
  async function searchProducts(event) {
    const query = event.query
    if (!query || query.length < 2) {
      productSuggestions.value = []
      return
    }

    searchingProducts.value = true
    try {
      const response = await request.get('/api/mgr/references/products', { query })
      productSuggestions.value = response.results || []
    } catch (error) {
      console.error('[OrderView] Error searching products:', error)
      productSuggestions.value = []
    } finally {
      searchingProducts.value = false
    }
  }

  /**
   * Handle product selection from autocomplete
   */
  function onProductSelect(event) {
    const product = event.value
    if (product) {
      addProductForm.value.price = product.price || 0
      addProductForm.value.weight = product.weight || 0
    }
  }

  /**
   * Save new product to order
   */
  async function saveNewProduct() {
    if (!selectedProduct.value || !selectedProduct.value.id) {
      toast.add({
        severity: 'warn',
        summary: _('warning'),
        detail: _('order_product_select'),
        life: 3000,
      })
      return
    }

    savingNewProduct.value = true
    try {
      const data = {
        product_id: selectedProduct.value.id,
        count: addProductForm.value.count || 1,
        price: addProductForm.value.price || 0,
        weight: addProductForm.value.weight || 0,
        options: addProductForm.value.options || {},
      }

      await request.post(`/api/mgr/orders/${orderId.value}/products`, data)

      toast.add({
        severity: 'success',
        summary: _('success'),
        detail: _('order_product_added'),
        life: 3000,
      })

      addProductDialogVisible.value = false

      await Promise.all([loadProducts(), loadOrder()])
    } catch (error) {
      console.error('[OrderView] Error adding product:', error)
      toast.add({
        severity: 'error',
        summary: _('error'),
        detail: error.message || _('error'),
        life: 5000,
      })
    } finally {
      savingNewProduct.value = false
    }
  }

  /**
   * Cancel add product
   */
  function cancelAddProduct() {
    addProductDialogVisible.value = false
    selectedProduct.value = null
  }

  /**
   * Delete product from order with confirmation
   */
  function deleteProduct(product) {
    if (products.value.length <= 1) {
      toast.add({
        severity: 'warn',
        summary: _('warning'),
        detail: _('order_product_cannot_delete_last'),
        life: 5000,
      })
      return
    }

    confirm.require({
      message: _('order_product_delete_confirm'),
      header: _('confirm_delete'),
      icon: 'pi pi-exclamation-triangle',
      acceptLabel: _('yes'),
      rejectLabel: _('no'),
      acceptClass: 'p-button-danger',
      accept: async () => {
        try {
          await request.delete(`/api/mgr/orders/${orderId.value}/products/${product.id}`)

          toast.add({
            severity: 'success',
            summary: _('success'),
            detail: _('order_product_deleted'),
            life: 3000,
          })

          await Promise.all([loadProducts(), loadOrder()])
        } catch (error) {
          console.error('[OrderView] Error deleting product:', error)
          toast.add({
            severity: 'error',
            summary: _('error'),
            detail: error.message || _('error_deleting_data'),
            life: 5000,
          })
        }
      },
    })
  }

  return {
    editProductDialogVisible,
    editingProduct,
    editProductForm,
    savingProduct,
    editedProductCost,
    addProductDialogVisible,
    selectedProduct,
    productSuggestions,
    searchingProducts,
    addProductForm,
    savingNewProduct,
    newProductCost,
    handleProductAction,
    editProduct,
    saveEditedProduct,
    cancelEditProduct,
    openAddProductDialog,
    searchProducts,
    onProductSelect,
    saveNewProduct,
    cancelAddProduct,
    deleteProduct,
  }
}
