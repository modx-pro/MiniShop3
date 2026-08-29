<script setup>
import { useLexicon } from '@vuetools/useLexicon'
import { Button, Dialog, InputText, Textarea } from 'primevue'
import { ref, watch } from 'vue'

const { _ } = useLexicon()

const props = defineProps({
  visible: {
    type: Boolean,
    default: false,
  },
  file: {
    type: Object,
    default: null,
  },
})

const emit = defineEmits(['update:visible', 'save'])

const form = ref({
  file: '',
  name: '',
  description: '',
})

watch(
  () => props.file,
  newFile => {
    if (newFile) {
      form.value = {
        file: newFile.file || '',
        name: newFile.name || '',
        description: newFile.description || '',
      }
    }
  },
  { immediate: true }
)

function onSave() {
  emit('save', {
    id: props.file?.id,
    file: form.value.file,
    name: form.value.name,
    description: form.value.description,
  })
  emit('update:visible', false)
}

function onCancel() {
  emit('update:visible', false)
}
</script>

<template>
  <Dialog
    :visible="visible"
    :header="_('ms3_gallery_file_update')"
    modal
    closable
    append-to="self"
    :style="{ width: '32rem' }"
    @update:visible="$emit('update:visible', $event)"
  >
    <div class="edit-form gallery-edit-form">
      <div v-if="file?.thumbnail" class="gallery-edit-preview">
        <a :href="file.url" target="_blank" rel="noopener" class="gallery-edit-thumb-link">
          <img :src="file.thumbnail" :alt="file.name" />
        </a>
      </div>

      <div class="field">
        <label for="gallery-edit-file">{{ _('ms3_gallery_file_name') }}</label>
        <InputText id="gallery-edit-file" v-model="form.file" class="w-full" />
      </div>

      <div class="field">
        <label for="gallery-edit-name">{{ _('ms3_gallery_file_title') }}</label>
        <InputText id="gallery-edit-name" v-model="form.name" class="w-full" />
      </div>

      <div class="field">
        <label for="gallery-edit-description">{{ _('ms3_gallery_file_description') }}</label>
        <Textarea id="gallery-edit-description" v-model="form.description" class="w-full" rows="3" />
      </div>
    </div>

    <template #footer>
      <Button :label="_('ms3_product_cancel')" severity="secondary" @click="onCancel" />
      <Button :label="_('ms3_product_save')" severity="success" @click="onSave" />
    </template>
  </Dialog>
</template>

<style>
/* Dialog may teleport; keep inputs inside content padding (border-box + min-width).
 * Label→control: 4px (theme). Between fields: 15px once — no stacked margins. */
.vueApp .gallery-edit-form,
.p-dialog .gallery-edit-form {
  display: flex;
  flex-direction: column;
  gap: var(--p-modx-space-panel, 15px);
    width: 100%;
    max-width: 100%;
    min-width: 0;
    overflow: hidden;
    box-sizing: border-box;
}

/* Override stacked .edit-form field margins (primevue.scss) — spacing via parent gap only. */
.vueApp .gallery-edit-form>.field,
.p-dialog .gallery-edit-form>.field,
.vueApp .gallery-edit-form>.field:not(:last-child),
.p-dialog .gallery-edit-form>.field:not(:last-child),
.vueApp .gallery-edit-form>.field.mb-3:not(:last-child),
.p-dialog .gallery-edit-form>.field.mb-3:not(:last-child) {
  width: 100%;
  max-width: 100%;
  min-width: 0;
  margin: 0 !important;
  padding: 0;
  gap: 0.25rem;
}

.vueApp .gallery-edit-form .field label,
.p-dialog .gallery-edit-form .field label {
  margin: 0;
  padding: 0;
  line-height: 1.3;
}

.vueApp .gallery-edit-form .w-full,
.p-dialog .gallery-edit-form .w-full,
.vueApp .gallery-edit-form .p-inputtext,
.p-dialog .gallery-edit-form .p-inputtext,
.vueApp .gallery-edit-form .p-textarea,
.p-dialog .gallery-edit-form .p-textarea {
  width: 100% !important;
  max-width: 100%;
  min-width: 0;
  margin: 0;
  box-sizing: border-box !important;
}

.vueApp .gallery-edit-preview,
.p-dialog .gallery-edit-preview {
  text-align: center;
  padding: 0.5rem;
  margin: 0;
  border: 1px solid var(--p-content-border-color, #e4e4e4);
  border-radius: var(--p-content-border-radius, 3px);
  background: var(--p-surface-50, #fafafa);
  box-sizing: border-box;
  max-width: 100%;
  overflow: hidden;
}

.vueApp .gallery-edit-preview img,
.p-dialog .gallery-edit-preview img {
  max-width: 100%;
  max-height: 10rem;
  height: auto;
  display: block;
  margin-inline: auto;
}

.vueApp .gallery-edit-thumb-link,
.p-dialog .gallery-edit-thumb-link {
  display: inline-block;
  max-width: 100%;
}
</style>
