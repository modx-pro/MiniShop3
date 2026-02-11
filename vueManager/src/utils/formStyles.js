/**
 * Runtime injection переопределений для контролов PrimeVue (font-size, height и т.п.).
 *
 * Вызывать после app.mount(). PrimeVue с @primeuix/themes встраивает тему в runtime
 * после загрузки бандла, поэтому стили из SCSS (даже с !important) загружаются раньше
 * и перезаписываются. Runtime injection обеспечивает применение наших правил последними.
 */
const STYLE_ID = 'ms3-form-styles-override'

export function injectFormStylesOverride() {
  if (document.getElementById(STYLE_ID)) return

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
  height: 2.25rem !important;
  min-height: 2.25rem !important;
  box-sizing: border-box !important;
}
`

  const el = document.createElement('style')
  el.id = STYLE_ID
  el.textContent = css
  document.head.appendChild(el)
}
