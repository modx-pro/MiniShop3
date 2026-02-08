<script setup>
import { useApi } from '@vuetools/useApi'
import { useModx } from '@vuetools/useModx'
import { usePermission } from '@vuetools/usePermission'
import Badge from 'primevue/badge'
import Button from 'primevue/button'
import Card from 'primevue/card'
import Chip from 'primevue/chip'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Divider from 'primevue/divider'
import Message from 'primevue/message'
import Panel from 'primevue/panel'
import ProgressSpinner from 'primevue/progressspinner'
import Tab from 'primevue/tab'
import TabList from 'primevue/tablist'
import TabPanel from 'primevue/tabpanel'
import TabPanels from 'primevue/tabpanels'
import Tabs from 'primevue/tabs'
import { computed, ref } from 'vue'

const { get, loading, error, clearError } = useApi()
const { config, ms3Config, userName, userId, isUserAdmin, showMessage } = useModx()
const { canCreate, canEdit, canDelete, getAvailablePermissions } = usePermission()

const healthData = ref(null)
const testResponse = ref(null)
const activeTab = ref('0')

const availablePermissions = computed(() => getAvailablePermissions())

/**
 * Test 1: Health check (without authorization)
 */
const testHealthCheck = async () => {
  clearError()
  testResponse.value = null

  try {
    healthData.value = await get('/api/mgr/health')
    showMessage('Health check successful', 'success')
  } catch (err) {
    showMessage(`Health check error: ${err.message}`, 'error')
  }
}

/**
 * Test 2: Authorized request
 */
const testAuthRequest = async () => {
  clearError()
  testResponse.value = null

  try {
    const response = await get('/api/mgr/test/info')
    testResponse.value = response
    showMessage('Authorized request successful', 'success')
  } catch (err) {
    showMessage(`Error: ${err.message}`, 'error')
  }
}

/**
 * Test 3: POST request with data
 */
const testPostRequest = async () => {
  clearError()
  testResponse.value = null

  try {
    const data = {
      test: 'data',
      timestamp: Date.now(),
      user: userName.value,
    }

    testResponse.value = await get('/api/mgr/test/echo', data)
    showMessage('POST request successful', 'success')
  } catch (err) {
    showMessage(`Error: ${err.message}`, 'error')
  }
}
</script>

<template>
  <div class="api-test">
    <Card>
      <template #title>
        <div class="flex align-items-center justify-content-between">
          <span>🧪 API Test Widget</span>
          <Chip :label="`v1.0.0`" class="bg-primary" />
        </div>
      </template>

      <template #subtitle> Testing new Vue Manager + API Router architecture </template>

      <template #content>
        <Tabs v-model:value="activeTab">
          <TabList>
            <Tab value="0">📊 System Information</Tab>
            <Tab value="1">🚀 API Tests</Tab>
            <Tab value="2">📖 Documentation</Tab>
          </TabList>
          <TabPanels>
            <TabPanel value="0">
              <div class="system-info">
                <Panel header="MODX Configuration" :toggleable="true">
                  <div class="info-grid">
                    <div class="info-item">
                      <strong>User:</strong>
                      <Chip :label="userName" icon="pi pi-user" />
                    </div>
                    <div class="info-item">
                      <strong>User ID:</strong>
                      <Badge :value="userId" severity="info" />
                    </div>
                    <div class="info-item">
                      <strong>Administrator:</strong>
                      <Badge
                        :value="isUserAdmin ? 'Yes' : 'No'"
                        :severity="isUserAdmin ? 'success' : 'warning'"
                      />
                    </div>
                    <div class="info-item">
                      <strong>Context:</strong>
                      <Chip :label="config.context_key || 'mgr'" />
                    </div>
                  </div>
                </Panel>

                <Divider />

                <Panel header="MiniShop3 Configuration" :toggleable="true">
                  <div class="info-grid">
                    <div class="info-item">
                      <strong>Connector URL:</strong>
                      <code>{{ ms3Config.connector_url }}</code>
                    </div>
                    <div class="info-item">
                      <strong>Assets URL:</strong>
                      <code>{{ ms3Config.assetsUrl }}</code>
                    </div>
                  </div>
                </Panel>

                <Divider />

                <Panel header="Permissions" :toggleable="true">
                  <div class="permissions-grid">
                    <Chip
                      :label="`canCreate: ${canCreate()}`"
                      :class="canCreate() ? 'bg-green-500' : 'bg-red-500'"
                    />
                    <Chip
                      :label="`canEdit: ${canEdit()}`"
                      :class="canEdit() ? 'bg-green-500' : 'bg-red-500'"
                    />
                    <Chip
                      :label="`canDelete: ${canDelete()}`"
                      :class="canDelete() ? 'bg-green-500' : 'bg-red-500'"
                    />
                  </div>

                  <Divider />

                  <DataTable
                    :value="availablePermissions.map(p => ({ permission: p }))"
                    :paginator="true"
                    :rows="10"
                    size="small"
                  >
                    <Column field="permission" header="Available Permissions" />
                  </DataTable>
                </Panel>
              </div>
            </TabPanel>

            <!-- Tab 2: API Tests -->
            <TabPanel value="1">
              <div class="api-tests">
                <Message severity="info" :closable="false">
                  Testing Request class, composables and API Router
                </Message>

                <Divider />

                <!-- Test 1: Health Check -->
                <Panel header="Test 1: Health Check" :toggleable="true">
                  <template #icons>
                    <Badge value="GET" severity="success" />
                  </template>

                  <p>Basic request without special authorization</p>
                  <p><code>GET /api/mgr/health</code></p>

                  <Button
                    label="Execute Health Check"
                    icon="pi pi-heart"
                    @click="testHealthCheck"
                    :loading="loading"
                    class="mt-3"
                  />

                  <div v-if="healthData" class="mt-3">
                    <Message severity="success">
                      <pre>{{ JSON.stringify(healthData, null, 2) }}</pre>
                    </Message>
                  </div>
                </Panel>

                <Divider />

                <!-- Test 2: Authorized Request -->
                <Panel header="Test 2: Authorized Request" :toggleable="true">
                  <template #icons>
                    <Badge value="GET" severity="success" />
                  </template>

                  <p>Request with HTTP_MODAUTH token and authorization check</p>
                  <p><code>GET /api/mgr/test/info</code></p>

                  <Button
                    label="Execute Authorized Request"
                    icon="pi pi-lock"
                    @click="testAuthRequest"
                    :loading="loading"
                    class="mt-3"
                  />

                  <div v-if="testResponse" class="mt-3">
                    <Message severity="success">
                      <pre>{{ JSON.stringify(testResponse, null, 2) }}</pre>
                    </Message>
                  </div>
                </Panel>

                <Divider />

                <!-- Test 3: POST Request -->
                <Panel header="Test 3: Echo Request with Parameters" :toggleable="true">
                  <template #icons>
                    <Badge value="GET" severity="success" />
                  </template>

                  <p>Send data and get it back (echo)</p>
                  <p><code>GET /api/mgr/test/echo?test=data&amp;timestamp=...</code></p>

                  <Button
                    label="Execute Echo Request"
                    icon="pi pi-send"
                    @click="testPostRequest"
                    :loading="loading"
                    class="mt-3"
                  />

                  <div v-if="testResponse" class="mt-3">
                    <Message severity="success">
                      <pre>{{ JSON.stringify(testResponse, null, 2) }}</pre>
                    </Message>
                  </div>
                </Panel>

                <!-- Errors -->
                <div v-if="error" class="mt-3">
                  <Message severity="error">
                    <div>
                      <strong>Error:</strong> {{ error.message }}<br />
                      <strong>Code:</strong> {{ error.statusCode }}<br />
                      <small>{{ error.data }}</small>
                    </div>
                  </Message>
                </div>

                <!-- Loading indicator -->
                <div v-if="loading" class="loading-overlay">
                  <ProgressSpinner />
                  <p>Request in progress...</p>
                </div>
              </div>
            </TabPanel>

            <!-- Tab 3: Documentation -->
            <TabPanel value="2">
              <div class="documentation">
                <h3>Technologies Used</h3>

                <Panel header="Composables" :toggleable="true" class="mb-3">
                  <ul>
                    <li>
                      <strong>useApi()</strong> - Reactive API requests with loading/error state
                    </li>
                    <li><strong>useModx()</strong> - Access to MODX configuration and lexicon</li>
                    <li><strong>usePermission()</strong> - User access rights verification</li>
                  </ul>
                </Panel>

                <Panel header="PrimeVue Components" :toggleable="true" class="mb-3">
                  <ul>
                    <li>Card, Panel, Tabs - Containers</li>
                    <li>Button, Chip, Badge - Interactive elements</li>
                    <li>Message, Divider - UI elements</li>
                    <li>DataTable, Column - Tables</li>
                    <li>ProgressSpinner - Loading indicators</li>
                  </ul>
                </Panel>

                <Panel header="API Router" :toggleable="true" class="mb-3">
                  <p>All requests go through:</p>
                  <code>{{ ms3Config.connector_url }}?action=api&route=/api/mgr/...</code>

                  <Divider />

                  <p>
                    <strong>HTTP_MODAUTH token:</strong> Automatically added from
                    <code>window.MODx.config.MODAUTH</code>
                  </p>
                  <p>
                    <strong>Middleware:</strong> AuthMiddleware checks authorization in mgr context
                  </p>
                </Panel>

                <Panel header="Request Class" :toggleable="true">
                  <pre class="code-block">
                  import request from '@/request.js';

                  // GET request
                  const data = await request.get('/api/mgr/health');

                  // POST request
                  await request.post('/api/mgr/products', {
                    pagetitle: 'New'
                  });

                  // Error handling
                  try {
                    const data = await request.get('/api/mgr/test');
                  } catch (error) {
                    if (error.isUnauthorized()) {
                      // Redirect to login
                    }
                  }
                </pre
                  >
                </Panel>
              </div>
            </TabPanel>
          </TabPanels>
        </Tabs>
      </template>
    </Card>
  </div>
</template>

<style scoped>
.api-test {
  padding: 1.25rem;
  max-width: 75rem;
  margin: 0 auto;
}

.system-info,
.api-tests,
.documentation {
  padding: 0.625rem 0;
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(15.625rem, 1fr));
  gap: 0.9375rem;
}

.info-item {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.info-item strong {
  color: var(--text-color-secondary);
  font-size: 0.9rem;
}

.info-item code {
  background: var(--surface-100);
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
  font-size: 0.85rem;
  word-break: break-all;
}

.permissions-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 0.625rem;
}

.loading-overlay {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 2.5rem;
  gap: 0.9375rem;
}

.code-block {
  background: var(--surface-100);
  padding: 0.9375rem;
  border-radius: 0.375rem;
  overflow-x: auto;
  font-size: 0.9rem;
  line-height: 1.5;
}

.documentation h3 {
  margin-bottom: 1.25rem;
  color: var(--primary-color);
}

.documentation ul {
  margin: 0;
  padding-left: 1.25rem;
}

.documentation ul li {
  margin-bottom: 0.5rem;
  line-height: 1.6;
}

:deep(.p-panel-header) {
  background: var(--surface-50);
}

:deep(.p-tabview-nav) {
  background: transparent;
}

:deep(.p-message pre) {
  margin: 0;
  font-size: 0.85rem;
  max-height: 18.75rem;
  overflow: auto;
}
</style>
