<script setup>
import { useLexicon } from '@vuetools/useLexicon'
import AutoComplete from 'primevue/autocomplete'
import Button from 'primevue/button'
import Column from 'primevue/column'
import ConfirmDialog from 'primevue/confirmdialog'
import DataTable from 'primevue/datatable'
import Dialog from 'primevue/dialog'
import Select from 'primevue/select'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref } from 'vue'

import request from '../../request.js'

const props = defineProps({
  productId: {
    type: Number,
    required: true,
  },
})

const { _ } = useLexicon()
const toast = useToast()
const confirm = useConfirm()

// Isolate from ProductGallery ConfirmDialog on product/update (#539)
const UI_GROUP = 'product-links'

const loading = ref(false)
const saving = ref(false)
const rows = ref([])
const totalRecords = ref(0)
const first = ref(0)
const pageRows = ref(20)
const query = ref('')
const linkTypes = ref([])
const createVisible = ref(false)
const selectedLinkId = ref(null)
const productSuggestions = ref([])
const selectedSlave = ref(null)

const resourceUpdateUrl = computed(() => {
  if (typeof MODx !== 'undefined' && MODx?.config?.manager_url) {
    return `${MODx.config.manager_url}?a=resource/update&id=`
  }
  return '?a=resource/update&id='
})

const selectedLinkTypeName = computed(() => {
  const name = linkTypes.value.find(item => item.id === selectedLinkId.value)?.name
  return translateLexicon(name, name || '')
})

function translateLexicon(key, fallback = '') {
  if (!key) {
    return fallback
  }
  const translated = _(key)
  return translated !== key ? translated : fallback
}

function apiResults(response) {
  return Array.isArray(response?.results) ? response.results : []
}

function productSide(row, side) {
  const id = Number(row[side])
  return {
    id,
    title: row[`${side}_pagetitle`] || String(id),
    isSelf: id === props.productId,
    href: `${resourceUpdateUrl.value}${id}`,
  }
}

function enrichRow(row) {
  return {
    ...row,
    _rowKey: `${row.link}-${row.master}-${row.slave}`,
    _master: productSide(row, 'master'),
    _slave: productSide(row, 'slave'),
  }
}

function showToast(severity, summaryKey, detail, life = severity === 'error' ? 5000 : 2500) {
  toast.add({
    severity,
    summary: _(summaryKey),
    detail,
    life,
  })
}

async function loadLinkTypes() {
  try {
    const response = await request.get('/api/mgr/references/link-types')
    linkTypes.value = apiResults(response)
  } catch (error) {
    console.error('[ProductLinksTab] loadLinkTypes', error)
    showToast('error', 'error', error.message || _('error_loading_data'))
  }
}

async function loadLinks() {
  loading.value = true
  try {
    const response = await request.get(`/api/mgr/product-data/${props.productId}/links`, {
      start: first.value,
      limit: pageRows.value,
      query: query.value,
    })
    rows.value = apiResults(response).map(enrichRow)
    totalRecords.value = Number(response?.total) || 0
  } catch (error) {
    console.error('[ProductLinksTab] loadLinks', error)
    showToast('error', 'error', error.message || _('error_loading_data'))
  } finally {
    loading.value = false
  }
}

function onPage(event) {
  first.value = event.first
  pageRows.value = event.rows
  loadLinks()
}

function searchLinks() {
  first.value = 0
  loadLinks()
}

function openCreate() {
  selectedLinkId.value = linkTypes.value[0]?.id ?? null
  selectedSlave.value = null
  productSuggestions.value = []
  createVisible.value = true
}

async function searchProducts(event) {
  const q = String(event.query || '').trim()
  if (q.length < 1) {
    productSuggestions.value = []
    return
  }
  try {
    const response = await request.get('/api/mgr/references/products', { query: q })
    productSuggestions.value = apiResults(response)
      .map(item => ({
        id: Number(item.id),
        label: item.pagetitle || item.name || String(item.id),
      }))
      .filter(item => item.id > 0 && item.id !== props.productId)
  } catch (error) {
    console.error('[ProductLinksTab] searchProducts', error)
    productSuggestions.value = []
  }
}

async function saveLink(close = true) {
  const slaveId = Number(selectedSlave.value?.id || selectedSlave.value || 0)
  if (!selectedLinkId.value || slaveId <= 0) {
    showToast('warn', 'warning', _('ms3_err_ns'), 3000)
    return
  }

  saving.value = true
  try {
    await request.post(`/api/mgr/product-data/${props.productId}/links`, {
      link: selectedLinkId.value,
      slave: slaveId,
    })
    showToast('success', 'success', _('ms3_link'))
    selectedSlave.value = null
    if (close) {
      createVisible.value = false
    }
    await loadLinks()
  } catch (error) {
    console.error('[ProductLinksTab] saveLink', error)
    showToast('error', 'error', error.message || _('error_saving_data'))
  } finally {
    saving.value = false
  }
}

function removeLink(row) {
  confirm.require({
    group: UI_GROUP,
    message: _('ms3_menu_remove_confirm'),
    header: _('ms3_menu_remove'),
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: _('delete'),
    rejectLabel: _('cancel'),
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await request.delete(`/api/mgr/product-data/${props.productId}/links`, {
          link: row.link,
          master: row.master,
          slave: row.slave,
        })
        showToast('success', 'success', _('ms3_menu_remove'))
        await loadLinks()
      } catch (error) {
        console.error('[ProductLinksTab] removeLink', error)
        showToast('error', 'error', error.message || _('error_deleting_data'))
      }
    },
  })
}

onMounted(async () => {
  await Promise.all([loadLinkTypes(), loadLinks()])
})
</script>

<template>
  <div class="product-links-tab">
    <ConfirmDialog :group="UI_GROUP" append-to="self" />

    <div class="product-links-tab__toolbar">
      <Button
        :label="_('ms3_btn_create')"
        icon="pi pi-plus"
        size="small"
        :disabled="!linkTypes.length"
        @click="openCreate"
      />
      <input
        v-model="query"
        type="search"
        class="product-links-tab__search"
        :placeholder="_('search')"
        @keyup.enter="searchLinks"
      />
      <Button
        icon="pi pi-search"
        severity="secondary"
        size="small"
        text
        @click="searchLinks"
      />
    </div>

    <DataTable
      :value="rows"
      :loading="loading"
      :lazy="true"
      :paginator="true"
      :rows="pageRows"
      :total-records="totalRecords"
      :first="first"
      data-key="_rowKey"
      size="small"
      @page="onPage"
    >
      <Column :header="_('ms3_link_name')">
        <template #body="{ data }">
          {{ translateLexicon(data.name, data.name) }}
        </template>
      </Column>
      <Column :header="_('ms3_type')">
        <template #body="{ data }">
          {{ data.type ? translateLexicon(`ms3_link_${data.type}`, data.type) : '' }}
        </template>
      </Column>
      <Column :header="_('ms3_link_master')">
        <template #body="{ data }">
          <span v-if="data._master.isSelf">{{ data._master.title }}</span>
          <a v-else :href="data._master.href" target="_blank" rel="noopener">{{
            data._master.title
          }}</a>
        </template>
      </Column>
      <Column :header="_('ms3_link_slave')">
        <template #body="{ data }">
          <span v-if="data._slave.isSelf">{{ data._slave.title }}</span>
          <a v-else :href="data._slave.href" target="_blank" rel="noopener">{{
            data._slave.title
          }}</a>
        </template>
      </Column>
      <Column :header="_('ms3_actions')" style="width: 5rem">
        <template #body="{ data }">
          <Button
            icon="pi pi-trash"
            severity="danger"
            text
            rounded
            size="small"
            :aria-label="_('ms3_menu_remove')"
            @click="removeLink(data)"
          />
        </template>
      </Column>
    </DataTable>

    <Dialog
      v-model:visible="createVisible"
      modal
      :header="_('ms3_link')"
      :style="{ width: '32rem' }"
    >
      <div class="product-links-tab__form">
        <label class="product-links-tab__label">{{ _('ms3_link') }}</label>
        <Select
          v-model="selectedLinkId"
          :options="linkTypes"
          option-label="name"
          option-value="id"
          class="w-full"
        >
          <template #option="{ option }">
            {{ translateLexicon(option.name, option.name) }}
          </template>
          <template #value>
            {{ selectedLinkTypeName }}
          </template>
        </Select>

        <label class="product-links-tab__label">{{ _('ms3_product') }}</label>
        <AutoComplete
          v-model="selectedSlave"
          :suggestions="productSuggestions"
          option-label="label"
          dropdown
          force-selection
          class="w-full"
          @complete="searchProducts"
        />
      </div>

      <template #footer>
        <Button :label="_('close')" severity="secondary" text @click="createVisible = false" />
        <Button
          :label="_('save')"
          :loading="saving"
          severity="secondary"
          @click="saveLink(false)"
        />
        <Button :label="_('save_and_close')" :loading="saving" @click="saveLink()" />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.product-links-tab {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  width: 100%;
  min-height: 18rem;
}

.product-links-tab__toolbar {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.product-links-tab__search {
  flex: 1;
  min-width: 0;
  padding: 0.4rem 0.6rem;
  border: 1px solid var(--p-content-border-color, #ced4da);
  border-radius: 0.375rem;
}

.product-links-tab__form {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.product-links-tab__label {
  font-size: 0.875rem;
  font-weight: 600;
}

.w-full {
  width: 100%;
}
</style>
