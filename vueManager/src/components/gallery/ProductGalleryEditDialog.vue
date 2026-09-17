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
    :modal="true"
    :closable="true"
    :style="{ width: '32rem' }"
    @update:visible="$emit('update:visible', $event)"
  >
    <div class="gallery-edit-form">
      <div v-if="file?.thumbnail" class="gallery-edit-preview">
        <a :href="file.url" target="_blank" class="gallery-edit-thumb-link">
          <img :src="file.thumbnail" :alt="file.name" />
        </a>
      </div>

      <div class="gallery-edit-field">
        <label>{{ _('ms3_gallery_file_name') }}</label>
        <InputText v-model="form.file" class="w-full" />
      </div>

      <div class="gallery-edit-field">
        <label>{{ _('ms3_gallery_file_title') }}</label>
        <InputText v-model="form.name" class="w-full" />
      </div>

      <div class="gallery-edit-field">
        <label>{{ _('ms3_gallery_file_description') }}</label>
        <Textarea v-model="form.description" class="w-full" rows="3" />
      </div>
    </div>

    <template #footer>
      <Button :label="_('ms3_product_cancel')" severity="secondary" text @click="onCancel" />
      <Button :label="_('ms3_product_save')" @click="onSave" />
    </template>
  </Dialog>
</template>

<style scoped>
.gallery-edit-form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.gallery-edit-preview {
  text-align: center;
  padding: 0.5rem;
  border: 1px solid var(--p-surface-200);
  border-radius: 0.375rem;
  background: var(--p-surface-50);
}

.gallery-edit-preview img {
  max-width: 100%;
  max-height: 12rem;
}

.gallery-edit-thumb-link {
  display: inline-block;
}

.gallery-edit-field {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.gallery-edit-field label {
  font-weight: 600;
  font-size: 0.875rem;
}

.w-full {
  width: 100%;
}
</style>
