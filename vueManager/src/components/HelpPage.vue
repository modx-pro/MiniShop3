<script setup>
import { useLexicon } from '@vuetools/useLexicon'
import Button from 'primevue/button'
import Card from 'primevue/card'
import { computed } from 'vue'

const { _ } = useLexicon()

// Logo from ms3.config
const logo = computed(() => (typeof ms3 !== 'undefined' ? ms3.config?.defaultThumb : null) || '')

// Quick links to admin sections
const quickLinks = computed(() => [
  {
    icon: 'pi pi-shopping-cart',
    href: _('ms3_orders_href'),
    text: _('ms3_orders_text'),
  },
  {
    icon: 'pi pi-users',
    href: _('ms3_customers_href'),
    text: _('ms3_customers_text'),
  },
  {
    icon: 'pi pi-bell',
    href: _('ms3_notifications_href'),
    text: _('ms3_notifications_text'),
  },
  {
    icon: 'pi pi-cog',
    href: _('ms3_settings_href'),
    text: _('ms3_settings_text'),
  },
  {
    icon: 'pi pi-wrench',
    href: _('ms3_utilities_href'),
    text: _('ms3_utilities_text'),
  },
  {
    icon: 'pi pi-sliders-h',
    href: _('ms3_sys_settings_href'),
    text: _('ms3_sys_settings_text'),
  },
])

// Resource cards
const resourceCards = computed(() => [
  {
    icon: 'pi pi-desktop',
    href: _('ms3_demo_href'),
    title: _('ms3_demo_title'),
    text: _('ms3_demo_text'),
  },
  {
    icon: 'pi pi-book',
    href: _('ms3_docs_href'),
    title: _('ms3_docs_title'),
    text: _('ms3_docs_text'),
  },
  {
    icon: 'pi pi-box',
    href: _('ms3_components_href'),
    title: _('ms3_components_title'),
    text: _('ms3_components_text'),
  },
  {
    icon: 'pi pi-comments',
    href: _('ms3_forum_href'),
    title: _('ms3_forum_title'),
    text: _('ms3_forum_text'),
  },
  {
    icon: 'pi pi-github',
    href: _('ms3_github_href'),
    title: _('ms3_github_title'),
    text: _('ms3_github_text'),
  },
])

function isExternal(href) {
  return Boolean(href && href.startsWith('http'))
}

function navigateTo(href) {
  if (href && href !== '#') {
    if (isExternal(href)) {
      window.open(href, '_blank')
    } else {
      window.location.href = href
    }
  }
}
</script>

<template>
  <div class="ms3-help-page">
    <!-- Header section -->
    <Card class="ms3-help-header">
      <template #content>
        <div class="header-content">
          <div class="header-text">
            <h2>{{ _('ms3_help') }}</h2>
            <p>{{ _('ms3_help_text') }}</p>
          </div>
          <img v-if="logo" :src="logo" alt="MiniShop3" class="header-logo" />
        </div>
      </template>
    </Card>

    <!-- Quick links -->
    <Card class="ms3-quick-links">
      <template #content>
        <nav class="quick-links-grid" :aria-label="_('ms3_help')">
          <Button
            v-for="link in quickLinks"
            :key="link.href"
            :label="link.text"
            :icon="link.icon"
            severity="secondary"
            outlined
            size="small"
            @click="navigateTo(link.href)"
          />
        </nav>
      </template>
    </Card>

    <!-- Resource cards: keep Card chrome; real <a> for keyboard / middle-click -->
    <div class="ms3-resource-cards">
      <Card v-for="card in resourceCards" :key="card.title" class="resource-card">
        <template #content>
          <a
            class="resource-link"
            :href="card.href"
            :target="isExternal(card.href) ? '_blank' : undefined"
            :rel="isExternal(card.href) ? 'noopener noreferrer' : undefined"
          >
            <i :class="[card.icon, 'resource-icon']" aria-hidden="true" />
            <span class="resource-text">
              <strong>{{ card.title }}</strong>
              <span>{{ card.text }}</span>
            </span>
          </a>
        </template>
      </Card>
    </div>

    <!-- Support section -->
    <Card class="ms3-support-section">
      <template #content>
        <!-- eslint-disable-next-line vue/no-v-html -->
        <div v-html="_('ms3_help_text_support')" />
      </template>
    </Card>
  </div>
</template>

<style scoped>
.ms3-help-page {
  padding: 1rem;
  width: 100%;
  max-width: none;
  box-sizing: border-box;
}

.ms3-help-header {
  margin-bottom: 1rem;
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 2rem;
}

.header-text h2 {
  margin: 0 0 0.5rem 0;
  font-size: 1.5rem;
  color: var(--p-text-color);
}

.header-text p {
  margin: 0;
  color: var(--p-text-muted-color);
}

.header-logo {
  max-height: 5rem;
  object-fit: contain;
}

.ms3-quick-links {
  margin-bottom: 1rem;
}

.quick-links-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.ms3-resource-cards {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 0.75rem;
  margin-bottom: 1rem;
  width: 100%;
}

.resource-card {
  min-width: 0;
  transition:
    transform 0.15s ease,
    box-shadow 0.15s ease;
}

.resource-card:hover {
  transform: translateY(-0.125rem);
  box-shadow: var(--ms3-shadow-dropdown);
}

.resource-card :deep(.p-card-body) {
  padding: 0.875rem 1rem;
}

.resource-card :deep(.p-card-content) {
  padding: 0;
}

.resource-link {
  display: flex;
  flex-direction: row;
  align-items: flex-start;
  gap: 0.75rem;
  text-align: start;
  text-decoration: none;
  color: inherit;
  outline: none;
}

.resource-link:focus-visible {
  outline: 2px solid var(--p-primary-color, #6cb24a);
  outline-offset: 0.25rem;
  border-radius: var(--ms3-radius-sm, 0.25rem);
}

.resource-icon {
  flex-shrink: 0;
  font-size: 1.5rem;
  line-height: 1;
  margin-top: 0.125rem;
  color: var(--p-primary-color, #6cb24a);
}

.resource-text {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  min-width: 0;
}

.resource-text strong {
  font-size: 0.9375rem;
  font-weight: 600;
  line-height: 1.25;
  color: var(--p-text-color);
}

.resource-text span {
  font-size: 0.8125rem;
  line-height: 1.4;
  color: var(--p-text-muted-color);
}

.ms3-support-section :deep(a) {
  color: var(--p-primary-color, #6cb24a);
}

@media (max-width: 64rem) {
  .ms3-resource-cards {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

@media (max-width: 40rem) {
  .ms3-resource-cards {
    grid-template-columns: 1fr;
  }
}
</style>
