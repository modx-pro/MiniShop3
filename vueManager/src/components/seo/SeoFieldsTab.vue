<script setup>
import { useLexicon } from '@vuetools/useLexicon'
import { Button, InputText, Select, Textarea, Toast, useToast } from 'primevue'
import { computed, onMounted, ref } from 'vue'

import { useGroupedToast, useUiGroup } from '../composables/uiGroup.js'
import request from '../request.js'

const props = defineProps({
  resourceId: {
    type: Number,
    required: true,
  },
  /**
   * Manager API base segment for the resource. `product-data` for products,
   * `categories` for categories. Both expose `{id}/seo`.
   */
  resourceBase: {
    type: String,
    required: true,
    validator: (v) => v === 'product-data' || v === 'categories',
  },
})

const { _ } = useLexicon()
useToast() // Required for Toast component to work
// From entry provideUiGroup('seo'); fallback for non-entry mounts (#538/#539).
const UI_GROUP = useUiGroup() || 'seo'
const toast = useGroupedToast(UI_GROUP)

const SEO_FIELD_KEYS = [
  'title',
  'description',
  'canonical',
  'robots',
  'og_title',
  'og_description',
  'og_image',
]

function emptyForm() {
  return Object.fromEntries(SEO_FIELD_KEYS.map((key) => [key, '']))
}

const loading = ref(false)
const saving = ref(false)

const form = ref(emptyForm())

const robotsOptions = computed(() => [
  { value: '', label: _('ms3_seo_robots_index_follow') || 'index, follow' },
  { value: 'noindex,follow', label: _('ms3_seo_robots_noindex_follow') || 'noindex, follow' },
  { value: 'index,nofollow', label: _('ms3_seo_robots_index_nofollow') || 'index, nofollow' },
  { value: 'noindex,nofollow', label: _('ms3_seo_robots_noindex_nofollow') || 'noindex, nofollow' },
])

function seoUrl() {
  return `/api/mgr/${props.resourceBase}/${props.resourceId}/seo`
}

async function loadSeo() {
  if (!props.resourceId) return
  loading.value = true
  try {
    const data = await request.get(seoUrl())
    applyServer(data)
  } catch (e) {
    toast.add({ severity: 'error', summary: _('error') || 'Error', detail: _('ms3_seo_load_failed') || e.message, life: 5000 })
  } finally {
    loading.value = false
  }
}

function applyServer(data) {
  const d = data && typeof data === 'object' ? data : {}
  form.value = Object.fromEntries(SEO_FIELD_KEYS.map((key) => [key, d[key] ?? '']))
}

async function saveSeo() {
  if (!props.resourceId) return
  saving.value = true
  try {
    const data = await request.put(seoUrl(), { ...form.value })
    applyServer(data)
    toast.add({ severity: 'success', summary: _('ms3_vue_save_success') || 'OK', detail: _('ms3_seo_saved') || 'SEO saved', life: 3000 })
  } catch (e) {
    toast.add({ severity: 'error', summary: _('error') || 'Error', detail: _('ms3_seo_save_failed') || e.message, life: 5000 })
  } finally {
    saving.value = false
  }
}

onMounted(loadSeo)
</script>

<template>
  <div class="seo-fields-tab">
    <Toast :group="UI_GROUP" />

    <p class="seo-intro">
      {{ _('ms3_seo_fields_description') || 'Leave a field empty to fall back to the default value.' }}
    </p>

    <div class="seo-grid">
      <div class="seo-field seo-field--full">
        <label for="seo-title">{{ _('ms3_seo_field_title') || 'Meta title' }}</label>
        <InputText id="seo-title" v-model="form.title" class="w-full" :disabled="loading || saving" />
      </div>

      <div class="seo-field seo-field--full">
        <label for="seo-description">{{ _('ms3_seo_field_description') || 'Meta description' }}</label>
        <Textarea
          id="seo-description"
          v-model="form.description"
          class="w-full"
          rows="3"
          auto-resize
          :disabled="loading || saving"
        />
      </div>

      <div class="seo-field seo-field--full">
        <label for="seo-canonical">{{ _('ms3_seo_field_canonical') || 'Canonical URL' }}</label>
        <InputText id="seo-canonical" v-model="form.canonical" class="w-full" :disabled="loading || saving" />
        <small class="seo-hint">{{ _('ms3_seo_canonical_hint') || '' }}</small>
      </div>

      <div class="seo-field">
        <label for="seo-robots">{{ _('ms3_seo_field_robots') || 'Robots' }}</label>
        <Select
          id="seo-robots"
          v-model="form.robots"
          :options="robotsOptions"
          option-label="label"
          option-value="value"
          show-clear
          class="w-full"
          :disabled="loading || saving"
        />
      </div>

      <div class="seo-field seo-field--full">
        <label for="seo-og-title">{{ _('ms3_seo_field_og_title') || 'Open Graph title' }}</label>
        <InputText id="seo-og-title" v-model="form.og_title" class="w-full" :disabled="loading || saving" />
      </div>

      <div class="seo-field seo-field--full">
        <label for="seo-og-description">{{ _('ms3_seo_field_og_description') || 'Open Graph description' }}</label>
        <Textarea
          id="seo-og-description"
          v-model="form.og_description"
          class="w-full"
          rows="3"
          auto-resize
          :disabled="loading || saving"
        />
      </div>

      <div class="seo-field seo-field--full">
        <label for="seo-og-image">{{ _('ms3_seo_field_og_image') || 'Open Graph image' }}</label>
        <InputText id="seo-og-image" v-model="form.og_image" class="w-full" :disabled="loading || saving" />
        <small class="seo-hint">{{ _('ms3_seo_og_image_hint') || '' }}</small>
      </div>
    </div>

    <div class="seo-actions">
      <Button
        :label="_('save') || 'Save'"
        :loading="saving"
        :disabled="loading"
        severity="primary"
        @click="saveSeo"
      />
    </div>
  </div>
</template>

<style scoped>
.seo-fields-tab {
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.seo-intro {
  color: var(--p-text-muted-color, #9ca3af);
  font-size: 0.875rem;
  margin: 0;
}

.seo-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 1rem;
}

@media (max-width: 48rem) {
  .seo-grid {
    grid-template-columns: 1fr;
  }
}

.seo-field {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.seo-field--full {
  grid-column: 1 / -1;
}

.seo-field label {
  font-size: 0.85rem;
  font-weight: 600;
}

.seo-hint {
  color: var(--p-text-muted-color, #9ca3af);
  font-size: 0.8rem;
}

.seo-actions {
  display: flex;
  justify-content: flex-end;
}
</style>
