<script setup>
import { Button, Card, Column, DataTable, Dialog, InputText, Select } from 'primevue'
import ConfirmDialog from 'primevue/confirmdialog'
import Toast from 'primevue/toast'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { onMounted, ref } from 'vue'

const confirm = useConfirm()
const toast = useToast()

import request from '../request.js'

const loading = ref(false)
const leftFields = ref([])
const rightFields = ref([])
const editVisible = ref(false)
const createVisible = ref(false)
const types = ref([])

const unUsedFields = ref([
  { name: 'price', title: 'Price' },
  { name: 'eur_price', title: 'Price in EUR' },
  { name: 'remains', title: 'Stock' },
])

const editedField = ref({})
const createdField = ref({})
const columnForCreate = ref('')

const isDev = location.hostname === 'localhost'

onMounted(async function () {
  await get()
  await getXtypes()
})

async function get() {
  loading.value = true

  if (!isDev) {
    try {
      const formData = new FormData()
      const { product } = await request.get('product-fields', formData)
      leftFields.value = product.data.left
      rightFields.value = product.data.right
    } catch (error) {
      console.error('Error when executing query GET product-fields:', error)
    }
  } else {
    const { product } = await import('../mockups/product/fields.json')
    leftFields.value = product.data.left
    rightFields.value = product.data.right
  }
}

async function getXtypes() {
  loading.value = true

  if (!isDev) {
    try {
      const formData = new FormData()
      const { xtypes } = await request.get('product-xtypes', formData)
      types.value = xtypes
    } catch (error) {
      console.error('Error when executing query GET product-xtypes:', error)
    }
  } else {
    const { xtypes } = await import('../mockups/product/xtypes.json')
    types.value = xtypes
  }
}

async function save() {
  loading.value = true

  if (!isDev) {
    try {
      const formData = new FormData()
      formData.left = JSON.stringify(leftFields.value)
      formData.right = JSON.stringify(rightFields.value)
      const { success } = await request.post('product-fields', formData)
      if (success) {
        loading.value = false
        await get()
      } else {
        loading.value = false
      }
    } catch (error) {
      console.error('Error when executing query POST product-fields:', error)
    }
  } else {
    setTimeout(() => {
      loading.value = false
    }, 300)
  }
}

const confirmRemove = field => {
  confirm.require({
    message: 'Delete record?',
    header: 'Deletion',
    icon: 'pi pi-exclamation-triangle',
    rejectProps: {
      label: 'Cancel',
      severity: 'secondary',
      outlined: true,
    },
    acceptProps: {
      label: 'Delete',
    },
    accept: () => {
      remove(field.name)
      save()
      toast.add({ severity: 'success', summary: 'OK', detail: 'Record deleted', life: 3000 })
    },
  })
}

function remove(name) {
  const lIndex = leftFields.value.findIndex(obj => obj.name === name)
  const rIndex = rightFields.value.findIndex(obj => obj.name === name)
  if (lIndex !== -1) {
    leftFields.value.splice(lIndex, 1)
  }
  if (rIndex !== -1) {
    rightFields.value.splice(rIndex, 1)
  }
}

function showEdit(data) {
  editedField.value = Object.assign({ anchor: '99%' }, data)
  editVisible.value = true
}

function closeEdit() {
  editedField.value = {}
  editVisible.value = false
}

function saveEdit() {
  const fieldForSave = Object.assign({}, editedField.value)
  const lIndex = leftFields.value.findIndex(obj => obj.name === fieldForSave.name)
  const rIndex = rightFields.value.findIndex(obj => obj.name === fieldForSave.name)
  if (lIndex !== -1) {
    leftFields.value[lIndex] = fieldForSave
  }
  if (rIndex !== -1) {
    rightFields.value[rIndex] = fieldForSave
  }

  editedField.value = {}
  editVisible.value = false

  save()
  toast.add({ severity: 'success', summary: 'OK', detail: 'Record saved', life: 3000 })
}

const RightReorder = event => {
  rightFields.value = event.value
  toast.add({ severity: 'success', summary: 'Sorting saved', life: 3000 })
  save()
}

const LeftReorder = event => {
  leftFields.value = event.value
  toast.add({ severity: 'success', summary: 'Sorting saved', life: 3000 })
  save()
}

function addField(column) {
  createVisible.value = true
  columnForCreate.value = column
}

function closeCreate() {
  createdField.value = {}
  createVisible.value = false
}

function saveCreate() {
  const fieldForSave = Object.assign({}, createdField.value)
  if (columnForCreate.value === 'left') {
    const index = Object.keys(leftFields.value).length
    leftFields.value[index] = fieldForSave
  }
  if (columnForCreate.value === 'right') {
    const index = Object.keys(rightFields.value).length
    rightFields.value[index] = fieldForSave
  }

  closeCreate()

  // save()
  toast.add({ severity: 'success', summary: 'OK', detail: 'Record saved', life: 3000 })
}

function getXtypeTitle(name) {
  const type = types.value.find(item => item.xtype === name)
  if (type !== undefined) {
    return type.name
  }
  return name
}
</script>

<template>
  <h3>Here you can manage the layout and display of product properties on the product page</h3>
  <div style="padding: 2.5rem 0">
    <Card style="max-width: 52.5rem; margin-bottom: 3.125rem">
      <template #title>
        <div
          style="width: 100%; display: flex; align-items: center; justify-content: space-between"
        >
          <span>Left Column</span>
          <Button icon="pi pi-plus"></Button>
        </div>
      </template>
      <template #content>
        <DataTable :value="leftFields" tableStyle="min-width: 50rem" @rowReorder="LeftReorder">
          <Column rowReorder headerStyle="width: 3rem" />
          <Column field="name" header="Field" style="width: 18.75rem"></Column>
          <Column field="xtype" header="Type" style="width: 18.75rem">
            <template #body="{ data }">
              <span> {{ getXtypeTitle(data.xtype) }}</span>
            </template>
          </Column>
          <Column class="w-24">
            <template #body="{ data }">
              <div
                style="
                  display: flex;
                  align-items: center;
                  justify-content: flex-start;
                  gap: 1.25rem;
                "
              >
                <Button icon="pi pi-pencil" @click="showEdit(data)"></Button>

                <Button icon="pi pi-minus" class="btn-danger" @click="confirmRemove(data)"></Button>
              </div>
            </template>
          </Column>
        </DataTable>
      </template>
    </Card>

    <Card style="max-width: 52.5rem">
      <template #title>
        <div
          style="width: 100%; display: flex; align-items: center; justify-content: space-between"
        >
          <span>Right Column</span>
          <Button icon="pi pi-plus" @click="addField('right')"></Button>
        </div>
      </template>
      <template #content>
        <DataTable :value="rightFields" tableStyle="min-width: 50rem" @rowReorder="RightReorder">
          <Column rowReorder headerStyle="width: 3rem" />
          <Column field="name" header="Field" style="width: 18.75rem"></Column>
          <Column field="xtype" header="Type" style="width: 18.75rem">
            <template #body="{ data }">
              <span> {{ getXtypeTitle(data.xtype) }}</span>
            </template>
          </Column>
          <Column class="w-24">
            <template #body="{ data }">
              <div
                style="
                  display: flex;
                  align-items: center;
                  justify-content: flex-start;
                  gap: 1.25rem;
                "
              >
                <Button icon="pi pi-pencil" @click="showEdit(data)"></Button>

                <Button icon="pi pi-minus" class="btn-danger"></Button>
              </div>
            </template>
          </Column>
        </DataTable>
      </template>
    </Card>
  </div>

  <Dialog
    v-model:visible="editVisible"
    modal
    header="Edit Field"
    :style="{ width: '25rem' }"
    v-if="Object.entries(editedField).length > 0"
    appendTo="self"
  >
    <div
      class="flex items-center gap-4 mb-4"
      style="display: flex; align-items: center; gap: 0.625rem; margin-bottom: 0.625rem"
    >
      <label for="name" style="width: 50%">Field</label>
      <InputText
        id="name"
        autocomplete="off"
        disabled
        readonly
        :value="editedField.name"
        style="width: 50%"
      />
    </div>

    <div
      class="flex items-center gap-4 mb-4"
      style="display: flex; align-items: center; gap: 0.625rem; margin-bottom: 0.625rem"
    >
      <label for="name" style="width: 50%">Field Type</label>

      <Select
        v-model="editedField.xtype"
        :options="types"
        optionLabel="name"
        optionValue="xtype"
        style="width: 58%"
      />
    </div>

    <div
      class="flex items-center gap-4 mb-4"
      style="display: flex; align-items: center; gap: 0.625rem; margin-bottom: 0.625rem"
    >
      <label for="anchor" style="width: 50%">Field Width</label>
      <InputText
        inputId="anchor"
        name="anchor"
        v-model="editedField.anchor"
        style="width: 50%"
        fluid
      />
    </div>

    <div
      style="
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 1.25rem;
        margin-top: 2.5rem;
      "
    >
      <Button type="button" label="Cancel" severity="secondary" @click="closeEdit()"></Button>
      <Button type="button" label="Save" @click="saveEdit()"></Button>
    </div>
  </Dialog>

  <Dialog
    v-model:visible="createVisible"
    modal
    header="Add Field"
    :style="{ width: '25rem' }"
    appendTo="self"
  >
    <div
      class="flex items-center gap-4 mb-4"
      style="display: flex; align-items: center; gap: 0.625rem; margin-bottom: 0.625rem"
    >
      <label for="name" style="width: 50%">Field</label>

      <Select
        v-model="createdField.name"
        :options="unUsedFields"
        optionLabel="title"
        optionValue="name"
        style="width: 58%"
      />
    </div>

    <div
      class="flex items-center gap-4 mb-4"
      style="display: flex; align-items: center; gap: 0.625rem; margin-bottom: 0.625rem"
    >
      <label for="name" style="width: 50%">Field Type</label>

      <Select
        v-model="createdField.xtype"
        :options="types"
        optionLabel="name"
        optionValue="xtype"
        style="width: 58%"
      />
    </div>

    <div
      class="flex items-center gap-4 mb-4"
      style="display: flex; align-items: center; gap: 0.625rem; margin-bottom: 0.625rem"
    >
      <label for="anchor" style="width: 50%">Field Width</label>
      <InputText
        inputId="anchor"
        name="anchor"
        v-model="createdField.anchor"
        style="width: 50%"
        fluid
      />
    </div>

    <div
      style="
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 1.25rem;
        margin-top: 2.5rem;
      "
    >
      <Button type="button" label="Cancel" severity="secondary" @click="closeCreate()"></Button>
      <Button type="button" label="Add" @click="saveCreate()"></Button>
    </div>
  </Dialog>

  <Toast />
  <ConfirmDialog appendTo="self" />
</template>
