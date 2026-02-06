<script setup>
import { computed } from 'vue'
import Card from 'primevue/card'
import Button from 'primevue/button'
import Divider from 'primevue/divider'
import { useLexicon } from '@vuetools/useLexicon'

const { _ } = useLexicon()

// Logo from ms3.config
const logo = computed(() => window.ms3?.config?.defaultThumb || '')

// Quick links to admin sections
const quickLinks = computed(() => [
  {
    icon: 'pi pi-shopping-cart',
    href: _('ms3_orders_href'),
    text: _('ms3_orders_text')
  },
  {
    icon: 'pi pi-users',
    href: _('ms3_customers_href'),
    text: _('ms3_customers_text')
  },
  {
    icon: 'pi pi-bell',
    href: _('ms3_notifications_href'),
    text: _('ms3_notifications_text')
  },
  {
    icon: 'pi pi-cog',
    href: _('ms3_settings_href'),
    text: _('ms3_settings_text')
  },
  {
    icon: 'pi pi-wrench',
    href: _('ms3_utilities_href'),
    text: _('ms3_utilities_text')
  },
  {
    icon: 'pi pi-sliders-h',
    href: _('ms3_sys_settings_href'),
    text: _('ms3_sys_settings_text')
  }
])

// Resource cards
const resourceCards = computed(() => [
  {
    icon: 'pi pi-desktop',
    href: _('ms3_demo_href'),
    title: _('ms3_demo_title'),
    text: _('ms3_demo_text')
  },
  {
    icon: 'pi pi-book',
    href: _('ms3_docs_href'),
    title: _('ms3_docs_title'),
    text: _('ms3_docs_text')
  },
  {
    icon: 'pi pi-box',
    href: _('ms3_components_href'),
    title: _('ms3_components_title'),
    text: _('ms3_components_text')
  },
  {
    icon: 'pi pi-comments',
    href: _('ms3_forum_href'),
    title: _('ms3_forum_title'),
    text: _('ms3_forum_text')
  },
  {
    icon: 'pi pi-github',
    href: _('ms3_github_href'),
    title: _('ms3_github_title'),
    text: _('ms3_github_text')
  }
])

function navigateTo(href) {
  if (href && href !== '#') {
    if (href.startsWith('http')) {
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
          <img
            v-if="logo"
            :src="logo"
            alt="MiniShop3"
            class="header-logo"
          >
        </div>
      </template>
    </Card>

    <!-- Quick links -->
    <Card class="ms3-quick-links">
      <template #content>
        <div class="quick-links-grid">
          <Button
            v-for="link in quickLinks"
            :key="link.href"
            :label="link.text"
            :icon="link.icon"
            severity="secondary"
            outlined
            @click="navigateTo(link.href)"
          />
        </div>
      </template>
    </Card>

    <!-- Resource cards -->
    <div class="ms3-resource-cards">
      <Card
        v-for="card in resourceCards"
        :key="card.title"
        class="resource-card"
        @click="navigateTo(card.href)"
      >
        <template #content>
          <div class="resource-content">
            <i :class="[card.icon, 'resource-icon']" />
            <div class="resource-text">
              <strong>{{ card.title }}</strong>
              <span>{{ card.text }}</span>
            </div>
          </div>
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
  max-width: 1200px;
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
  max-height: 80px;
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
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 1rem;
  margin-bottom: 1rem;
}

.resource-card {
  cursor: pointer;
  transition: transform 0.2s, box-shadow 0.2s;
}

.resource-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.resource-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: 0.75rem;
}

.resource-icon {
  font-size: 2rem;
  color: var(--p-primary-color);
}

.resource-text {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.resource-text strong {
  font-size: 0.95rem;
  color: var(--p-text-color);
}

.resource-text span {
  font-size: 0.85rem;
  color: var(--p-text-muted-color);
}

.ms3-support-section :deep(a) {
  color: var(--p-primary-color);
}
</style>
