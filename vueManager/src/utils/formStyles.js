/**
 * Runtime injection переопределений для контролов PrimeVue (font-size, height и т.п.).
 *
 * Вызывать после app.mount(). PrimeVue с @primeuix/themes встраивает тему в runtime
 * после загрузки бандла, поэтому стили из SCSS (даже с !important) загружаются раньше
 * и перезаписываются. Runtime injection обеспечивает применение наших правил последними.
 */
import { scheduleMs3ThemeVars } from '../theme/injectMs3ThemeVars.js'

const STYLE_ID = 'ms3-form-styles-override'

/** Shared control height: inputs, selects, and buttons (mgr density). */
export const MS3_CONTROL_HEIGHT = '2.25rem'

export function injectFormStylesOverride() {
  // Re-apply primary tokens after mount (PrimeVue theme styles land around here).
  scheduleMs3ThemeVars()

  const css = `
.vueApp .p-dropdown .p-dropdown-label,
.vueApp .p-dropdown .p-inputtext,
.vueApp .p-select [data-pc-section="label"],
.vueApp .p-select .p-select-label,
.vueApp .p-select .p-inputtext,
.vueApp .p-multiselect [data-pc-section="label"],
.vueApp .p-multiselect .p-multiselect-label,
.vueApp .p-inputtext,
.vueApp .p-inputnumber .p-inputnumber-input,
.vueApp .p-inputnumber-input,
.vueApp .p-autocomplete .p-autocomplete-input,
.vueApp .p-autocomplete-input,
.vueApp .p-textarea,
.vueApp .p-cascadeselect [data-pc-section="label"],
.vueApp .p-treeselect [data-pc-section="label"],
.p-dialog .p-dropdown .p-dropdown-label,
.p-dialog .p-dropdown .p-inputtext,
.p-dialog .p-select [data-pc-section="label"],
.p-dialog .p-select .p-select-label,
.p-dialog .p-select .p-inputtext,
.p-dialog .p-multiselect [data-pc-section="label"],
.p-dialog .p-multiselect .p-multiselect-label,
.p-dialog .p-inputtext,
.p-dialog .p-inputnumber .p-inputnumber-input,
.p-dialog .p-inputnumber-input,
.p-dialog .p-autocomplete .p-autocomplete-input,
.p-dialog .p-autocomplete-input,
.p-dialog .p-textarea,
.p-dialog .p-cascadeselect [data-pc-section="label"],
.p-dialog .p-treeselect [data-pc-section="label"] {
  font-size: 0.875rem !important;
}

/* Компактные бейджи (Tag, status-badge, статусы) */
.vueApp .p-tag,
.vueApp .status-badge,
.p-dialog .p-tag,
.p-dialog .status-badge {
  padding: 0.125rem 0.5rem !important;
  font-size: 0.75rem !important;
}

/* Единая высота всех однострочных контролов */
.vueApp .p-inputtext,
.vueApp .p-select,
.vueApp .p-dropdown,
.vueApp .p-inputnumber .p-inputnumber-input,
.vueApp .p-autocomplete .p-autocomplete-input,
.vueApp .p-datepicker .p-datepicker-input,
.p-dialog .p-inputtext,
.p-dialog .p-select,
.p-dialog .p-dropdown,
.p-dialog .p-inputnumber .p-inputnumber-input,
.p-dialog .p-autocomplete .p-autocomplete-input,
.p-dialog .p-datepicker .p-datepicker-input {
  height: ${MS3_CONTROL_HEIGHT} !important;
  min-height: ${MS3_CONTROL_HEIGHT} !important;
  box-sizing: border-box !important;
}

/*
 * Единая высота кнопок на всех экранах / табах / диалогах.
 * size="small"|"large" и .p-button-sm|.p-button-lg визуально совпадают с default —
 * иначе тулбары и футеры диалогов «пляшут» относительно полей.
 */
.vueApp .p-button,
.vueApp .p-button.p-button-sm,
.vueApp .p-button.p-button-lg,
.p-dialog .p-button,
.p-dialog .p-button.p-button-sm,
.p-dialog .p-button.p-button-lg,
.p-confirmdialog .p-button,
.p-confirmdialog .p-button.p-button-sm,
.p-confirmdialog .p-button.p-button-lg,
.p-confirm-popup .p-button,
.p-popover .p-button,
.p-overlaypanel .p-button,
.p-toast .p-button {
  height: ${MS3_CONTROL_HEIGHT} !important;
  min-height: ${MS3_CONTROL_HEIGHT} !important;
  padding-block: 0 !important;
  font-size: 0.875rem !important;
  box-sizing: border-box !important;
}

.vueApp .p-button.p-button-icon-only,
.vueApp .p-button.p-button-sm.p-button-icon-only,
.vueApp .p-button.p-button-lg.p-button-icon-only,
.p-dialog .p-button.p-button-icon-only,
.p-confirmdialog .p-button.p-button-icon-only,
.p-popover .p-button.p-button-icon-only,
.p-overlaypanel .p-button.p-button-icon-only {
  width: ${MS3_CONTROL_HEIGHT} !important;
  padding-inline: 0 !important;
}

/* AutoComplete / InputGroup: input + dropdown same height as Select */
.vueApp .p-autocomplete,
.p-dialog .p-autocomplete {
  display: inline-flex !important;
  align-items: stretch !important;
  min-height: ${MS3_CONTROL_HEIGHT} !important;
}

.vueApp .p-autocomplete .p-autocomplete-input,
.p-dialog .p-autocomplete .p-autocomplete-input {
  height: ${MS3_CONTROL_HEIGHT} !important;
  min-height: ${MS3_CONTROL_HEIGHT} !important;
}

.vueApp .p-autocomplete .p-autocomplete-dropdown,
.p-dialog .p-autocomplete .p-autocomplete-dropdown,
.vueApp .p-datepicker .p-datepicker-dropdown,
.p-dialog .p-datepicker .p-datepicker-dropdown {
  height: ${MS3_CONTROL_HEIGHT} !important;
  min-height: ${MS3_CONTROL_HEIGHT} !important;
  width: ${MS3_CONTROL_HEIGHT} !important;
  padding: 0 !important;
  box-sizing: border-box !important;
}
`

  let el = document.getElementById(STYLE_ID)
  if (!el) {
    el = document.createElement('style')
    el.id = STYLE_ID
    document.head.appendChild(el)
  }
  el.textContent = css
}
