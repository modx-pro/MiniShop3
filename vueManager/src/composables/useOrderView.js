/**
 * Order manager screen orchestrator (#339).
 *
 * Thin wiring layer: creates shared order refs and core computeds, then delegates
 * loading, saving, products, options, cost recalculation, customer handling, and
 * plugin tab registry to dedicated composables. The public orderContext contract
 * and the return shape consumed by OrderView.vue are preserved unchanged.
 */
import { useLexicon } from '@vuetools/useLexicon'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'

import { useOrderCostRecalc } from './useOrderCostRecalc.js'
import { useOrderCustomer } from './useOrderCustomer.js'
import { useOrderFieldHelpers } from './useOrderFieldHelpers.js'
import { useOrderFormatters } from './useOrderFormatters.js'
import { useOrderLoad } from './useOrderLoad.js'
import { useOrderPluginTabs } from './useOrderPluginTabs.js'
import { useOrderProductOptions } from './useOrderProductOptions.js'
import { useOrderProducts } from './useOrderProducts.js'
import { useOrderSave } from './useOrderSave.js'

export function useOrderView() {
  const toast = useToast()
  const confirm = useConfirm()
  const { _ } = useLexicon()

  // Shared order ref — populated by useOrderLoad, read across composables.
  const order = ref(null)
  // Shared saving flag — mutated by useOrderSave, read by useOrderCustomer duplicate flow.
  const saving = ref(false)

  const orderId = computed(() => {
    const ms3Config = typeof ms3 !== 'undefined' ? ms3.config : null
    const configId = ms3Config?.order_id
    const rawId = configId || new URLSearchParams(window.location.search).get('id')
    if (rawId === 'new') {
      return 'new'
    }
    return parseInt(rawId) || 0
  })

  const isCreateMode = computed(() => {
    return orderId.value === 'new' || orderId.value === 0
  })

  const managerConfig = computed(() => {
    return typeof ms3 !== 'undefined' ? ms3.config : {}
  })

  // Cost recalculation owns baseline + recalc state; its sync helper is reused on load/save.
  const costRecalc = useOrderCostRecalc({ order, orderId, isCreateMode, saving, _, toast })

  // Customer + duplicate dialog state — created before save so save can read its refs.
  const orderActions = { finalizeOrder: null, createOrder: null }
  const customer = useOrderCustomer({
    _,
    toast,
    order,
    orderId,
    saving,
    orderActions,
  })

  const load = useOrderLoad({
    _,
    toast,
    order,
    orderId,
    selectedCustomer: customer.selectedCustomer,
    costRecalcWarnings: costRecalc.costRecalcWarnings,
    syncShippingPaymentBaselineFromOrder: costRecalc.syncShippingPaymentBaselineFromOrder,
  })

  const { formatDate, formatPrice, getFieldWidthClass } = useOrderFormatters()

  const fieldHelpers = useOrderFieldHelpers({
    formatDate,
    formatPrice,
    order,
    orderComboOptions: load.orderComboOptions,
    addressComboOptions: load.addressComboOptions,
    statuses: load.statuses,
    deliveries: load.deliveries,
    payments: load.payments,
    _,
  })

  const save = useOrderSave({
    _,
    toast,
    confirm,
    order,
    orderId,
    saving,
    orderFields: load.orderFields,
    addressFields: load.addressFields,
    orderExtraFields: load.orderExtraFields,
    addressExtraFields: load.addressExtraFields,
    selectedCustomer: customer.selectedCustomer,
    createCustomerFromData: customer.createCustomerFromData,
    showDuplicateDialog: customer.showDuplicateDialog,
    duplicateCustomer: customer.duplicateCustomer,
    pendingOrderData: customer.pendingOrderData,
    fieldHelpers,
    loadOrder: load.loadOrder,
    loadLogs: load.loadLogs,
    syncShippingPaymentBaselineFromOrder: costRecalc.syncShippingPaymentBaselineFromOrder,
    recalculatingCost: costRecalc.recalculatingCost,
    costRecalcWarnings: costRecalc.costRecalcWarnings,
    COST_RECALC_WARNING_HINTS: costRecalc.COST_RECALC_WARNING_HINTS,
  })

  // Wire circular customer ↔ save dependency now that save.finalizeOrder / createOrder exist.
  orderActions.finalizeOrder = save.finalizeOrder
  orderActions.createOrder = save.createOrder

  // Options composable reads editingProduct (owned by orchestrator, shared with products).
  const editingProduct = ref(null)
  const options = useOrderProductOptions({ _, editingProduct })

  const products = useOrderProducts({
    _,
    toast,
    confirm,
    orderId,
    products: load.products,
    editingProduct,
    loadProducts: load.loadProducts,
    loadOrder: load.loadOrder,
    options,
  })

  const tabs = useOrderPluginTabs({
    _,
    order,
    orderId,
    isCreateMode,
    managerConfig,
  })

  const draftStatusId = computed(() => {
    const ms3Config = typeof ms3 !== 'undefined' ? ms3.config : null
    return ms3Config?.status_draft || 1
  })

  const isDraft = computed(() => {
    if (!order.value) return false
    return parseInt(order.value.status_id) === parseInt(draftStatusId.value)
  })

  function goBack() {
    window.location.href = '?a=mgr/orders&namespace=minishop3'
  }

  watch(
    () => tabs.orderTabsConfig.value.map(t => t.key),
    keys => {
      if (keys.length === 0) return
      if (!keys.includes(tabs.orderActiveTab.value)) {
        tabs.orderActiveTab.value = keys[0]
      }
    },
    { immediate: true }
  )

  watch(tabs.orderActiveTab, () => {
    nextTick(() => {
      const tab = tabs.orderTabsConfig.value.find(t => t.key === tabs.orderActiveTab.value)
      if (
        tab &&
        tab.kind === 'plugin' &&
        tab.type === 'extjs' &&
        tab.xtype &&
        !tabs.mountedExtPluginComponents.value[tab.key]
      ) {
        tabs.mountExtJSOrderPlugin(tab)
      }
    })
  })

  onBeforeUnmount(() => {
    tabs.destroyPluginExtComponents()
    if (window.MS3OrderTabsRegistry) {
      window.MS3OrderTabsRegistry._onUnmounted()
    }
  })

  onMounted(async () => {
    if (isCreateMode.value) {
      await load.initEmptyOrder()
    } else {
      await load.loadOrder()
    }
  })

  const orderContext = {
    order,
    saving: save.saving,
    isDraft,
    isCreateMode,
    finalizing: save.finalizing,
    customerSuggestions: customer.customerSuggestions,
    searchingCustomers: customer.searchingCustomers,
    ...fieldHelpers,
    formatDate,
    formatPrice,
    getFieldWidthClass,
    saveOrder: save.saveOrder,
    createOrder: save.createOrder,
    goBack,
    confirmFinalizeOrder: save.confirmFinalizeOrder,
    handleProductAction: products.handleProductAction,
    openAddProductDialog: products.openAddProductDialog,
    searchCustomers: customer.searchCustomers,
    onCustomerSelect: customer.onCustomerSelect,
    clearCustomer: customer.clearCustomer,
    recalculateOrderCost: costRecalc.recalculateOrderCost,
    recalculatingCost: costRecalc.recalculatingCost,
    costRecalcWarnings: costRecalc.costRecalcWarnings,
    manualDeliveryCost: costRecalc.manualDeliveryCost,
    hasUnsavedShippingPaymentChanges: costRecalc.hasUnsavedShippingPaymentChanges,
  }

  return {
    orderContext,
    registerPluginTab: tabs.registerPluginTab,
    pluginVueProps: tabs.pluginVueProps,

    loading: load.loading,
    saving: save.saving,
    order,
    products: load.products,
    productsColumns: load.productsColumns,
    logs: load.logs,
    orderFieldsBySection: load.orderFieldsBySection,
    orderExtraFields: load.orderExtraFields,
    orderActiveTab: tabs.orderActiveTab,
    orderTabsConfig: tabs.orderTabsConfig,
    isCreateMode,
    selectedCustomer: customer.selectedCustomer,
    createCustomerFromData: customer.createCustomerFromData,
    addressFieldsBySection: load.addressFieldsBySection,
    addressExtraFields: load.addressExtraFields,
    goBack,
    formatPrice,

    editProductDialogVisible: products.editProductDialogVisible,
    editingProduct: products.editingProduct,
    editProductForm: products.editProductForm,
    savingProduct: products.savingProduct,
    optionsEditMode: options.optionsEditMode,
    optionsTableData: options.optionsTableData,
    productOptionFields: options.productOptionFields,
    optionsJsonText: options.optionsJsonText,
    optionsJsonError: options.optionsJsonError,
    editedProductCost: products.editedProductCost,
    switchOptionsMode: options.switchOptionsMode,
    onOptionTypeChange: options.onOptionTypeChange,
    onFieldKeyChange: options.onFieldKeyChange,
    syncTableToJson: options.syncTableToJson,
    addOptionRow: options.addOptionRow,
    removeOptionRow: options.removeOptionRow,
    saveEditedProduct: products.saveEditedProduct,
    cancelEditProduct: products.cancelEditProduct,

    addProductDialogVisible: products.addProductDialogVisible,
    selectedProduct: products.selectedProduct,
    productSuggestions: products.productSuggestions,
    searchingProducts: products.searchingProducts,
    addProductForm: products.addProductForm,
    savingNewProduct: products.savingNewProduct,
    newProductCost: products.newProductCost,
    searchProducts: products.searchProducts,
    onProductSelect: products.onProductSelect,
    saveNewProduct: products.saveNewProduct,
    cancelAddProduct: products.cancelAddProduct,

    showDuplicateDialog: customer.showDuplicateDialog,
    duplicateCustomer: customer.duplicateCustomer,
    cancelDuplicateDialog: customer.cancelDuplicateDialog,
    useDuplicateCustomer: customer.useDuplicateCustomer,
    createNewCustomerAnyway: customer.createNewCustomerAnyway,
  }
}
