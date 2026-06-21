/**
 * Cross-component sync bus for msOptionGroup mutations (#10).
 *
 * Lightweight global DOM-event channel used by OptionGroupsGrid (publisher) and
 * OptionsGrid (subscriber) so that creating/updating/deleting/reordering a group
 * in one tab refreshes the dropdown in the other without a page reload.
 *
 * If sibling-component count grows, migrate to a Pinia store (`useOptionGroupsStore`).
 */

export const OPTION_GROUPS_CHANGED_EVENT = 'ms3:option-groups:changed'

export function notifyOptionGroupsChanged() {
  document.dispatchEvent(new CustomEvent(OPTION_GROUPS_CHANGED_EVENT))
}

export function onOptionGroupsChanged(listener) {
  document.addEventListener(OPTION_GROUPS_CHANGED_EVENT, listener)
  return () => document.removeEventListener(OPTION_GROUPS_CHANGED_EVENT, listener)
}
