/**
 * App-level UI group for PrimeVue ConfirmDialog / Toast isolation.
 *
 * PrimeVue ConfirmationEventBus and ToastEventBus are module singletons when
 * PrimeVue is shared via Import Map. Ungrouped dialogs/toasts on the same page
 * (multi-app mounts or sibling tabs kept alive without lazy) all receive every
 * ungrouped confirm.require / toast.add (#539).
 *
 * Confirm matches with strict === (ungrouped dialog → group === undefined).
 * Toast matches with loose == (ungrouped Toast → group default null; null == undefined).
 * Always pass `toUiGroup(group)` into confirm.require; never pass null.
 *
 * When to use which pattern:
 * - One group per Vue app → `provideUiGroup(app, id)` in the entry, then
 *   `useUiGroup()` (optional literal fallback) in the root component.
 * - Several groups inside one app (e.g. gallery vs links) → local `UI_GROUP`
 *   constants; provide cannot split them.
 *
 * `provideUiGroup` applies to the whole app subtree. A nested ungrouped
 * `<Toast />` / `<ConfirmDialog />` that still uses `useGroupedToast` /
 * `useActions` / `useSelection` will stamp the app group and stay silent
 * unless its host widgets use the same `:group`.
 *
 * @see https://github.com/modx-pro/MiniShop3/issues/539
 */
import { useToast } from 'primevue/usetoast'
import { inject } from 'vue'

export const MS3_UI_GROUP = Symbol('ms3UiGroup')

/**
 * @param {import('vue').App} app
 * @param {string} group
 */
export function provideUiGroup(app, group) {
  if (!group) {
    return
  }
  app.provide(MS3_UI_GROUP, group)
}

/**
 * @returns {string|null}
 */
export function useUiGroup() {
  return inject(MS3_UI_GROUP, null)
}

/**
 * Resolve explicit option or injected app group.
 * Always calls inject (Vue composition rule).
 *
 * @param {string|null|undefined} explicit
 * @returns {string|null}
 */
export function resolveUiGroup(explicit) {
  const injected = useUiGroup()
  if (explicit) {
    return explicit
  }
  return injected
}

/**
 * Normalize group for confirm.require / optional bindings.
 * Ungrouped → undefined (ConfirmDialog strict ===; never pass null).
 *
 * @param {string|null|undefined} group
 * @returns {string|undefined}
 */
export function toUiGroup(group) {
  return group || undefined
}

/**
 * @param {Object} payload
 * @param {string|null|undefined} group
 * @returns {Object}
 */
export function withToastGroup(payload, group) {
  const g = toUiGroup(group)
  if (!g) {
    return payload
  }
  return { ...payload, group: g }
}

/**
 * Toast facade: stamps the resolved UI group on every `add()`.
 * Other ToastService methods (`remove`, `removeGroup`, …) stay available.
 *
 * Pass an already-resolved group string when the caller resolved once
 * (avoids a second inject when composing with useActions / useSelection).
 *
 * @param {string|null|undefined} explicit
 */
export function useGroupedToast(explicit = null) {
  const toast = useToast()
  const group = resolveUiGroup(explicit)
  return {
    ...toast,
    add(payload) {
      return toast.add(withToastGroup(payload, group))
    },
  }
}
