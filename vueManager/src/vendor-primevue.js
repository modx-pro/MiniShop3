/**
 * PrimeVue vendor bundle (ES Module)
 *
 * Экспортирует весь API PrimeVue для использования через import map
 */

// Core
export { default as PrimeVue } from 'primevue/config';
export { default as Aura } from '@primevue/themes/aura';

// Services
export { default as ConfirmationService } from 'primevue/confirmationservice';
export { default as ToastService } from 'primevue/toastservice';

// Components (часто используемые)
export { default as Button } from 'primevue/button';
export { default as Card } from 'primevue/card';
export { default as Panel } from 'primevue/panel';
export { default as TabView } from 'primevue/tabview';
export { default as TabPanel } from 'primevue/tabpanel';
export { default as DataTable } from 'primevue/datatable';
export { default as Column } from 'primevue/column';
export { default as InputText } from 'primevue/inputtext';
export { default as Message } from 'primevue/message';
export { default as Toast } from 'primevue/toast';
export { default as ConfirmDialog } from 'primevue/confirmdialog';
