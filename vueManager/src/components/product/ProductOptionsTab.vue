<script setup>
import { useLexicon } from '@vuetools/useLexicon'
import { computed, ref, watch } from 'vue'

import ProductOptionField from './ProductOptionField.vue'

const props = defineProps({
  optionFields: {
    type: Array,
    default: () => [],
  },
})

const { _ } = useLexicon()

/**
 * Group options by option_group_id (msOption.option_group_id, #10).
 * Group titles come from option_group_name (joined msOptionGroup.name);
 * options without a group end up under "Без группы".
 */
const groups = computed(() => {
  const map = new Map()
  for (const option of props.optionFields) {
    if (!option || !option.key) continue
    const groupId = Number(option.option_group_id) || 0
    if (!map.has(groupId)) {
      map.set(groupId, {
        id: groupId,
        title: option.group_name || option.option_group_name || _('ms3_option_group_no_group'),
        options: [],
      })
    }
    map.get(groupId).options.push(option)
  }
  return Array.from(map.values())
})

const activeGroupId = ref(null)

watch(
  groups,
  newGroups => {
    if (newGroups.length > 0 && !newGroups.some(g => g.id === activeGroupId.value)) {
      activeGroupId.value = newGroups[0].id
    }
  },
  { immediate: true }
)

function onOptionChange(payload) {
  // Hidden inputs propagate values into the MODX form POST automatically.
  // Hook left here for future dirty-tracking / validation wiring.
  void payload
}
</script>

<template>
  <div class="product-options-tab">
    <div v-if="groups.length === 0" class="empty">
      {{ _('ms3_ft_nogroup') }}
    </div>

    <!-- Single group — no vertical nav, just a flat list -->
    <div v-else-if="groups.length === 1" class="single-group">
      <ProductOptionField
        v-for="option in groups[0].options"
        :key="option.key"
        :option="option"
        @change="onOptionChange"
      />
    </div>

    <!-- Multiple groups — vertical navigation on the left, panel on the right -->
    <div v-else class="vtabs">
      <nav class="vtabs-nav" role="tablist" aria-orientation="vertical">
        <button
          v-for="group in groups"
          :key="group.id"
          type="button"
          role="tab"
          class="vtabs-nav-item"
          :class="{ 'is-active': group.id === activeGroupId }"
          :aria-selected="group.id === activeGroupId"
          @click="activeGroupId = group.id"
        >
          {{ group.title }}
          <span class="vtabs-nav-count">{{ group.options.length }}</span>
        </button>
      </nav>

      <!--
        All groups are rendered at once and toggled via v-show. This preserves
        per-field state (local refs, focus, partial input) when the user switches
        between tabs and — more importantly — keeps every hidden input mounted
        in the DOM so the MODX/ExtJS form picks them up at submit. v-if-driven
        unmount/remount loses both.
      -->
      <section
        v-for="group in groups"
        v-show="group.id === activeGroupId"
        :key="group.id"
        class="vtabs-panel"
        role="tabpanel"
      >
        <ProductOptionField
          v-for="option in group.options"
          :key="option.key"
          :option="option"
          @change="onOptionChange"
        />
      </section>
    </div>
  </div>
</template>

<style>
/* Non-scoped with .vueApp prefix — avoids Vite scoped-hash mismatch between chunks */
.vueApp .product-options-tab {
  width: 100%;
  padding: 0;
}

.vueApp .product-options-tab .empty {
  color: #6b7280;
  padding: 1rem;
  font-style: italic;
}

.vueApp .product-options-tab .single-group {
  max-width: 48rem;
}

.vueApp .product-options-tab .vtabs {
  display: flex;
  gap: 1.5rem;
  align-items: flex-start;
}

.vueApp .product-options-tab .vtabs-nav {
  display: flex;
  flex-direction: column;
  min-width: 12rem;
  border-right: 1px solid var(--p-tabs-tab-border-color, #e5e7eb);
}

.vueApp .product-options-tab .vtabs-nav-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.6rem 1rem;
  background: transparent;
  border: none;
  border-right: 2px solid transparent;
  text-align: left;
  font-size: 0.9rem;
  color: var(--p-tabs-tab-color, #4b5563);
  cursor: pointer;
  transition:
    background-color 0.15s,
    color 0.15s,
    border-color 0.15s;
}

.vueApp .product-options-tab .vtabs-nav-item:hover {
  background: var(--p-tabs-tab-hover-background, rgba(0, 0, 0, 0.04));
  color: var(--p-tabs-tab-hover-color, #111827);
}

.vueApp .product-options-tab .vtabs-nav-item.is-active {
  color: var(--p-tabs-tab-active-color, var(--p-primary-color, #10b981));
  border-right-color: var(--p-tabs-tab-active-border-color, var(--p-primary-color, #10b981));
  font-weight: 600;
}

.vueApp .product-options-tab .vtabs-nav-count {
  margin-left: 0.5rem;
  font-size: 0.75rem;
  color: var(--p-text-muted-color, #9ca3af);
  background: var(--p-content-background, #f3f4f6);
  padding: 0.05rem 0.4rem;
  border-radius: 0.75rem;
}

.vueApp .product-options-tab .vtabs-panel {
  flex: 1;
  max-width: 48rem;
  min-width: 0;
}
</style>
