/**
 * Order cost recalculation state and API (#212).
 *
 * @param {Object} deps
 * @param {import('vue').Ref} deps.order
 * @param {import('vue').ComputedRef} deps.orderId
 * @param {import('vue').ComputedRef} deps.isCreateMode
 * @param {import('vue').Ref} deps.saving
 * @param {Function} deps._
 * @param {Object} deps.toast
 */
import { computed, ref, shallowRef } from 'vue'

import request from '../request.js'

export function useOrderCostRecalc(deps) {
  const { order, orderId, isCreateMode, saving, _, toast } = deps
  const savedBaselineDeliveryId = ref(0)
  const savedBaselinePaymentId = ref(0)
  const recalculatingCost = ref(false)
  /** @type {import('vue').ShallowRef<string[]>} Warning codes from last recalculate API */
  const costRecalcWarnings = shallowRef([])
  /** Hint keys for cost recalculation warnings (ManagerOrderCostRecalculator). */
  const COST_RECALC_WARNING_HINTS = Object.freeze({
    delivery_manual_required: 'order_cost_recalc_delivery_manual_hint',
    payment_manual_required: 'order_cost_recalc_payment_manual_hint',
    delivery_provider_error: 'order_cost_recalc_delivery_manual_hint',
    payment_provider_error: 'order_cost_recalc_payment_manual_hint',
  })
  const manualDeliveryCost = ref(null)

  function syncShippingPaymentBaselineFromOrder() {
    if (!order.value || isCreateMode.value) {
      return
    }
    savedBaselineDeliveryId.value = Number.parseInt(order.value.delivery_id, 10) || 0
    savedBaselinePaymentId.value = Number.parseInt(order.value.payment_id, 10) || 0
  }

  const hasUnsavedShippingPaymentChanges = computed(() => {
    if (isCreateMode.value || !order.value) {
      return false
    }
    const d = Number.parseInt(order.value.delivery_id, 10) || 0
    const p = Number.parseInt(order.value.payment_id, 10) || 0

    return d !== savedBaselineDeliveryId.value || p !== savedBaselinePaymentId.value
  })

  /**
   * Берём поля заказа из ответа пересчёта без служебных ключей breakdown/warnings.
   * @param {Record<string, unknown>|null|undefined} raw
   * @returns {{ orderPayload: Record<string, unknown>, warnings: string[] }}
   */
  function stripRecalculateCostMeta(raw) {
    if (!raw || typeof raw !== 'object') {
      return { orderPayload: {}, warnings: [] }
    }
    const merged = /** @type {Record<string, unknown>} */ ({ ...raw })
    delete merged.breakdown
    const w = merged.warnings
    delete merged.warnings
    return {
      orderPayload: merged,
      warnings: Array.isArray(w) ? w.map(String) : [],
    }
  }

  /**
   * Пересчитать стоимость заказа (Manager API, #212).
   * @param {{ mode?: string, manual_delivery_cost?: number }} opts
   */
  async function recalculateOrderCost(opts = {}) {
    if (isCreateMode.value || !orderId.value || orderId.value === 'new') {
      return
    }

    if (saving.value || recalculatingCost.value) {
      return
    }

    recalculatingCost.value = true
    costRecalcWarnings.value = []

    try {
      const body = {
        mode: opts.mode ?? 'auto',
      }
      if (opts.manual_delivery_cost !== undefined && opts.manual_delivery_cost !== null) {
        body.manual_delivery_cost = opts.manual_delivery_cost
      }

      const raw = await request.post(`/api/mgr/orders/${orderId.value}/recalculate-cost`, body)
      const { orderPayload, warnings } = stripRecalculateCostMeta(raw)
      order.value = { ...(order.value || {}), ...orderPayload }
      costRecalcWarnings.value = warnings
      syncShippingPaymentBaselineFromOrder()

      toast.add({
        severity: 'success',
        summary: _('success'),
        detail: _('order_cost_recalculated'),
        life: 3000,
      })
    } catch (error) {
      console.error('[OrderView] Error recalculateCost:', error)
      toast.add({
        severity: 'error',
        summary: _('error'),
        detail: error.message || _('error_saving_data'),
        life: 5000,
      })
    } finally {
      recalculatingCost.value = false
    }
  }

  return {
    savedBaselineDeliveryId,
    savedBaselinePaymentId,
    recalculatingCost,
    costRecalcWarnings,
    COST_RECALC_WARNING_HINTS,
    manualDeliveryCost,
    hasUnsavedShippingPaymentChanges,
    syncShippingPaymentBaselineFromOrder,
    recalculateOrderCost,
  }
}
