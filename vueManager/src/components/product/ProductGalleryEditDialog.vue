<script setup>
import { useLexicon } from '@vuetools/useLexicon'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import { computed, ref, watch } from 'vue'

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

const { _ } = useLexicon()

const form = ref({
  file: '',
  name: '',
  description: '',
})

const canSave = computed(() => {
  const { file, name } = form.value
  return Boolean(String(file ?? '').trim() && String(name ?? '').trim())
})

function populateFormFromFile() {
  if (!props.file) {
    form.value = { file: '', name: '', description: '' }
    return
  }
  form.value = {
    file: props.file.file ?? '',
    name: props.file.name ?? '',
    description: props.file.description ?? '',
  }
}

function close() {
  emit('update:visible', false)
}

function submit() {
  if (!canSave.value) return
  emit('save', {
    id: props.file?.id,
    file: form.value.file.trim(),
    name: form.value.name.trim(),
    description: form.value.description.trim(),
  })
  close()
}

watch(
  () => props.visible,
  (isVisible) => {
    if (isVisible) populateFormFromFile()
  }
)
</script>

<template>
  <Dialog
    :header="_('ms3_gallery_file_update')"
    :visible="visible"
    modal
    :style="{ width: 'var(--ms3-modal-width, 28rem)' }"
    pt:root:class="ms3-gallery-edit-dialog"
    :close-button-props="{ class: 'ms3-gallery-edit-dialog-close' }"
    @update:visible="(v) => $emit('update:visible', v)"
  >
    <div class="ms3-gallery-edit-fields">
      <div class="field">
        <label for="gallery-edit-file">{{ _('ms3_gallery_file_name') }}</label>
        <InputText id="gallery-edit-file" v-model="form.file" class="w-full" />
      </div>
      <div class="field">
        <label for="gallery-edit-name">{{ _('ms3_gallery_file_title') }}</label>
        <InputText id="gallery-edit-name" v-model="form.name" class="w-full" />
      </div>
      <div class="field">
        <label for="gallery-edit-desc">{{ _('ms3_gallery_file_description') }}</label>
        <Textarea id="gallery-edit-desc" v-model="form.description" rows="3" class="w-full" />
      </div>
    </div>
    <template #footer>
      <Button :label="_('ms3_product_cancel')" text @click="close" />
      <Button
        :label="_('ms3_product_save')"
        :disabled="!canSave"
        @click="submit"
      />
    </template>
  </Dialog>
</template>

<style scoped>
.ms3-gallery-edit-fields .field {
  margin-bottom: 1rem;
}
.ms3-gallery-edit-fields .field label {
  display: block;
  margin-bottom: 0.25rem;
  font-weight: 500;
}
</style>

<!-- Dialog teleports to body — styles must not be scoped -->
<style>
.ms3-gallery-edit-dialog,
.ms3-gallery-edit-dialog .p-dialog-content {
  overflow-x: hidden;
}
.ms3-gallery-edit-dialog .ms3-gallery-edit-fields {
  min-width: 0;
}

/* Кнопка закрытия: класс задаётся через closeButtonProps, обводка при фокусе — box-shadow (outline не следует border-radius) */
.ms3-gallery-edit-dialog .ms3-gallery-edit-dialog-close {
  width: 2rem !important;
  height: 2rem !important;
  min-width: 2rem !important;
  padding: 0 !important;
  border-radius: 50% !important;
  display: inline-flex !important;
  align-items: center;
  justify-content: center;
  overflow: hidden !important;
  outline: none !important;
  box-shadow: none !important;
}
.ms3-gallery-edit-dialog .ms3-gallery-edit-dialog-close:focus,
.ms3-gallery-edit-dialog .ms3-gallery-edit-dialog-close:focus-visible {
  outline: none !important;
  box-shadow: 0 0 0 2px var(--p-focus-ring-color, var(--p-primary-color)) !important;
}
</style>
