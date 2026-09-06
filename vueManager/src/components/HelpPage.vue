<script setup>
import { useLexicon } from '@vuetools/useLexicon'
import { computed } from 'vue'

const { _ } = useLexicon()

const logo = computed(() => (typeof ms3 !== 'undefined' ? ms3.config?.defaultThumb : null) || '')

const quickLinks = computed(() =>
  [
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
  ].filter(link => link.href && link.href !== '#')
)

const resourceCards = computed(() =>
  [
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
    {
      icon: 'pi pi-globe',
      href: _('ms3_localization_href'),
      title: _('ms3_localization_title'),
      text: _('ms3_localization_text'),
    },
  ].filter(card => card.href && card.href !== '#')
)

function isExternal(href) {
  return Boolean(href && href.startsWith('http'))
}

function onResourceClick(event, href) {
  if (
    event.defaultPrevented ||
    event.button !== 0 ||
    event.metaKey ||
    event.ctrlKey ||
    event.shiftKey ||
    event.altKey
  ) {
    return
  }
  if (isExternal(href)) {
    return
  }
  event.preventDefault()
  window.location.href = href
}
</script>

<template>
  <div class="ms3-help-page">
    <header class="ms3-help-page__header">
      <div class="ms3-help-page__header-text">
        <h2 class="ms3-help-page__title">{{ _('ms3_help') }}</h2>
        <p class="ms3-help-page__intro">{{ _('ms3_help_text') }}</p>
      </div>
      <img v-if="logo" :src="logo" alt="MiniShop3" class="ms3-help-page__logo" />
    </header>

    <section class="ms3-help-page__section" aria-labelledby="ms3-help-nav-heading">
      <h3 id="ms3-help-nav-heading" class="ms3-help-page__section-title">
        {{ _('ms3_help_nav_title') }}
      </h3>
      <div class="ms3-help-page__nav-grid">
        <a
          v-for="link in quickLinks"
          :key="link.href"
          class="p-button p-component p-button-secondary"
          :href="link.href"
          :target="isExternal(link.href) ? '_blank' : undefined"
          :rel="isExternal(link.href) ? 'noopener noreferrer' : undefined"
        >
          <span :class="[link.icon, 'p-button-icon', 'p-button-icon-left']" aria-hidden="true" />
          <span class="p-button-label">{{ link.text }}</span>
        </a>
      </div>
    </section>

    <section class="ms3-help-page__section" aria-labelledby="ms3-help-resources-heading">
      <h3 id="ms3-help-resources-heading" class="ms3-help-page__section-title">
        {{ _('ms3_help_resources_title') }}
      </h3>
      <div class="ms3-help-page__resources">
        <a
          v-for="card in resourceCards"
          :key="card.title"
          class="ms3-help-page__resource"
          :href="card.href"
          :target="isExternal(card.href) ? '_blank' : undefined"
          :rel="isExternal(card.href) ? 'noopener noreferrer' : undefined"
          @click="onResourceClick($event, card.href)"
        >
          <span class="ms3-help-page__resource-icon-wrap" aria-hidden="true">
            <i :class="[card.icon, 'ms3-help-page__resource-icon']" />
          </span>
          <span class="ms3-help-page__resource-body">
            <span class="ms3-help-page__resource-title">
              {{ card.title }}
              <i
                v-if="isExternal(card.href)"
                class="pi pi-external-link ms3-help-page__external"
                aria-hidden="true"
              />
            </span>
            <span class="ms3-help-page__resource-text">{{ card.text }}</span>
          </span>
        </a>
      </div>
    </section>

    <section class="ms3-help-page__support">
      <!-- eslint-disable-next-line vue/no-v-html -->
      <div class="ms3-help-page__support-body" v-html="_('ms3_help_text_support')" />
    </section>
  </div>
</template>

<style scoped>
.ms3-help-page {
  padding: var(--p-modx-space-panel, 15px);
  max-width: 56rem;
  box-sizing: border-box;
}

.ms3-help-page__header {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: flex-start;
  gap: var(--p-modx-space-panel, 15px);
  margin-bottom: 1.5rem;
}

.ms3-help-page__title {
  margin: 0 0 0.25rem;
  font-size: 1.25rem;
  font-weight: 600;
  color: #333;
}

.ms3-help-page__intro {
  margin: 0;
  padding: 0;
  max-width: 40rem;
  color: var(--ms3-text-muted, #64748b);
  font-size: 0.875rem;
  line-height: 1.5;
}

.ms3-help-page__logo {
  max-height: 3.5rem;
  object-fit: contain;
}

.ms3-help-page__section {
  margin-bottom: 1.5rem;
}

.ms3-help-page__section-title {
  margin: 0 0 0.5rem;
  font-size: 0.75rem;
  font-weight: 600;
  letter-spacing: 0.02em;
  text-transform: uppercase;
  color: var(--ms3-text-muted, #64748b);
}

.ms3-help-page__nav-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

/* MODX manager resets bare `a` (padding/bg). Re-assert PrimeVue secondary button. */
.ms3-help-page__nav-grid > a.p-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: var(--p-button-gap, 0.375rem);
  box-sizing: border-box;
  min-height: var(--p-modx-control-height, 36px);
  padding: var(--p-button-padding-y, 0.5rem) var(--p-button-padding-x, 0.875rem);
  margin: 0;
  border: 1px solid var(--p-button-secondary-border-color, #d0d0d0);
  border-radius: var(--p-button-border-radius, 0.25rem);
  background: var(--p-button-secondary-background, #fff);
  color: var(--p-button-secondary-color, #4a4a4a);
  font-size: var(--p-modx-font-size-lg, 0.875rem);
  font-weight: var(--p-button-label-font-weight, 400);
  line-height: 1;
  text-decoration: none;
  cursor: pointer;
  transition:
    background-color var(--p-button-transition-duration, 0.15s),
    border-color var(--p-button-transition-duration, 0.15s),
    color var(--p-button-transition-duration, 0.15s);
}

.ms3-help-page__nav-grid > a.p-button:hover {
  background: var(--p-button-secondary-hover-background, #e8e8e8);
  border-color: var(--p-button-secondary-hover-border-color, #c0c0c0);
  color: var(--p-button-secondary-hover-color, #333);
}

.ms3-help-page__nav-grid > a.p-button:focus-visible {
  outline: 2px solid var(--p-primary-color, #234368);
  outline-offset: 2px;
}

.ms3-help-page__nav-grid > a.p-button .p-button-icon {
  font-size: 1rem;
}

.ms3-help-page__resources {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  max-width: 40rem;
}

.ms3-help-page__resource {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  padding: 0.75rem var(--p-modx-space-panel, 15px);
  box-sizing: border-box;
  background: var(--p-content-background, #fff);
  border: 1px solid var(--ms3-border-color-alt, #e4e4e4);
  border-radius: var(--p-content-border-radius, 0.25rem);
  border-left: 3px solid transparent;
  color: inherit;
  text-decoration: none;
  transition:
    border-color 0.15s ease,
    background-color 0.15s ease;
}

.ms3-help-page__resource:hover {
  border-color: var(--ms3-border-color, #d0d0d0);
  border-left-color: var(--p-primary-color, #234368);
  background: var(--ms3-bg-muted, #fbfbfb);
}

.ms3-help-page__resource:focus-visible {
  outline: 2px solid var(--p-primary-color, #234368);
  outline-offset: 2px;
}

.ms3-help-page__resource-icon-wrap {
  display: flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 2rem;
  width: 2rem;
  height: 2rem;
  margin-top: 0.125rem;
}

.ms3-help-page__resource-icon {
  font-size: 1.25rem;
  color: var(--p-primary-color, #234368);
}

.ms3-help-page__resource-body {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
  min-width: 0;
}

.ms3-help-page__resource-title {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  font-size: 0.9375rem;
  font-weight: 600;
  color: #333;
  line-height: 1.3;
}

.ms3-help-page__external {
  font-size: 0.75rem;
  color: var(--ms3-text-muted, #64748b);
  font-weight: 400;
}

.ms3-help-page__resource-text {
  font-size: 0.8125rem;
  line-height: 1.4;
  color: var(--ms3-text-muted, #64748b);
}

.ms3-help-page__support {
  max-width: 40rem;
  padding-top: 0.25rem;
}

.ms3-help-page__support-body {
  font-size: 0.875rem;
  line-height: 1.5;
  color: var(--ms3-text-muted, #64748b);
}

.ms3-help-page__support-body :deep(strong) {
  color: #333;
  font-weight: 600;
}

.ms3-help-page__support-body :deep(a) {
  color: var(--p-primary-color, #234368);
}

.ms3-help-page__support-body :deep(a:focus-visible) {
  outline: 2px solid var(--p-primary-color, #234368);
  outline-offset: 2px;
}

@media (prefers-reduced-motion: reduce) {
  .ms3-help-page__resource {
    transition: none;
  }
}
</style>
