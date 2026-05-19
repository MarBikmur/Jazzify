<template>
  <section class="admin-entity-page">
    <header class="admin-entity-page__header">
      <div>
        <h1>{{ tl(pageTitle) }}</h1>
      </div>

      <div class="admin-entity-page__controls">
        <NuxtLink
          v-if="createPath"
          class="admin-entity-page__create"
          :to="createPath"
        >
          {{ tl(createLabel) }}
        </NuxtLink>

        <label class="admin-entity-page__search">
          <span>{{ tl('Search') }}</span>
          <input
            v-model="searchQuery"
            type="search"
            :placeholder="searchPlaceholder"
          >
        </label>
      </div>
    </header>

    <div v-if="!isValidEntity" class="admin-entity-page__state admin-entity-page__state--error">
      {{ tl('Unsupported entity') }}
    </div>
    <div v-else-if="loading && !rows.length" class="admin-entity-page__state">
      <span class="loader" />
      <span>{{ loadingLabel }}</span>
    </div>
    <div v-else-if="errorMessage" class="admin-entity-page__state admin-entity-page__state--error">
      {{ errorMessage }}
    </div>
    <template v-else>
      <div class="admin-entity-table-shell">
        <table class="admin-entity-table">
          <thead>
            <tr>
              <th v-for="column in columns" :key="column">{{ labelForColumn(column) }}</th>
              <th class="admin-entity-table__actions">{{ tl('Actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="row in rows"
              :key="`${entityKey}-${row._primary}`"
              class="admin-entity-table__row"
              :class="{ 'admin-entity-table__row--clickable': !!row._public_url }"
              @click="openRow(row)"
            >
              <td v-for="column in columns" :key="`${row._primary}-${column}`">
                {{ formatCell(row[column]) }}
              </td>
              <td class="admin-entity-table__actions-cell" @click.stop>
                <button
                  type="button"
                  class="admin-entity-table__action-button"
                  @click="editRow(row)"
                >
                  {{ tl('Edit') }}
                </button>
                <button
                  v-if="row._public_url"
                  type="button"
                  class="admin-entity-table__action-button admin-entity-table__action-button--ghost"
                  @click="openRow(row)"
                >
                  {{ tl('Open') }}
                </button>
                <button
                  type="button"
                  class="admin-entity-table__action-button admin-entity-table__action-button--danger"
                  @click="deleteRow(row)"
                >
                  {{ tl('Delete') }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="!rows.length" class="admin-entity-page__empty">
        <strong>{{ tl('No records found') }}</strong>
        <span>{{ tl('Try another search query.') }}</span>
      </div>

      <div v-else class="admin-entity-page__footer">
        <span>{{ tl('Page {current} of {total}', { current: currentPage, total: totalPages }) }}</span>
        <div class="admin-entity-page__pagination">
          <button
            type="button"
            class="admin-entity-page__pager"
            :disabled="loading || currentPage <= 1"
            @click="goToPage(currentPage - 1)"
          >
            {{ tl('Previous') }}
          </button>
          <button
            v-for="page in visiblePages"
            :key="page"
            type="button"
            class="admin-entity-page__pager"
            :class="{ 'admin-entity-page__pager--active': page === currentPage }"
            :disabled="loading && page === currentPage"
            @click="goToPage(page)"
          >
            {{ page }}
          </button>
          <button
            type="button"
            class="admin-entity-page__pager"
            :disabled="loadingMore || currentPage >= totalPages"
            @click="goToPage(currentPage + 1)"
          >
            {{ tl('Next') }}
          </button>
        </div>
      </div>
    </template>
  </section>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import type { AdminEntity, AdminListResponse, AdminListRow } from '~/composables/useAdmin'

definePageMeta({
  layout: 'admin',
})

const { tl } = useLocalizedText()
const route = useRoute()
const { isAdminEntity, getEntityLabel, getAdminEntityList, deleteAdminEntityRecord } = useAdmin()

const loading = ref(false)
const loadingMore = ref(false)
const errorMessage = ref('')
const searchQuery = ref('')
const columns = ref<string[]>([])
const rows = ref<AdminListRow[]>([])
const total = ref(0)
const limit = 10
const currentPage = ref(1)
let searchTimer: ReturnType<typeof setTimeout> | null = null

const entityKey = computed(() => String(route.params.entity || ''))
const isValidEntity = computed(() => isAdminEntity(entityKey.value))
const entity = computed(() => entityKey.value as AdminEntity)
const pageTitle = computed(() => isValidEntity.value ? getEntityLabel(entity.value) : 'Admin')
const searchPlaceholder = computed(() => tl('Search {entity}', {
  entity: tl(pageTitle.value).toLowerCase(),
}))
const loadingLabel = computed(() => tl('Loading {entity}...', {
  entity: tl(pageTitle.value).toLowerCase(),
}))
const totalPages = computed(() => Math.max(1, Math.ceil(total.value / limit)))
const createPath = computed(() => {
  if (entityKey.value === 'users') {
    return '/admin/users/create'
  }

  if (entityKey.value === 'countries') {
    return '/admin/countries/create'
  }

  if (entityKey.value === 'genres') {
    return '/admin/genres/create'
  }

  if (entityKey.value === 'songs') {
    return '/admin/songs/upload'
  }

  return ''
})
const createLabel = computed(() => {
  if (entityKey.value === 'users') {
    return 'Create user'
  }

  if (entityKey.value === 'countries') {
    return 'Create country'
  }

  if (entityKey.value === 'genres') {
    return 'Create genre'
  }

  if (entityKey.value === 'songs') {
    return 'Upload track'
  }

  return ''
})
const visiblePages = computed(() => {
  const start = Math.max(1, currentPage.value - 2)
  const end = Math.min(totalPages.value, start + 4)
  const normalizedStart = Math.max(1, end - 4)
  return Array.from({ length: end - normalizedStart + 1 }, (_, index) => normalizedStart + index)
})

const labelForColumn = (column: string) => {
  const normalized = column
    .replaceAll('_', ' ')
    .replace(/\b\w/g, (char) => char.toUpperCase())

  return tl(normalized)
}

const formatCell = (value: unknown) => {
  if (value === null || value === undefined || value === '') {
    return '—'
  }

  if (typeof value === 'boolean') {
    return value ? 'true' : 'false'
  }

  const normalized = String(value)
  return normalized.length > 64 ? `${normalized.slice(0, 61)}...` : normalized
}

const applyResponse = (response: AdminListResponse) => {
  columns.value = response.columns
  total.value = response.meta.total
  rows.value = response.rows
}

const loadRecords = async () => {
  if (!isValidEntity.value) {
    return
  }

  const requestOffset = (currentPage.value - 1) * limit
  loading.value = true

  errorMessage.value = ''

  try {
    const response = await getAdminEntityList(entity.value, {
      q: searchQuery.value.trim(),
      offset: requestOffset,
      limit,
    })
    applyResponse(response)
  } catch (error: any) {
    errorMessage.value = error?.data?.message || tl('Could not load admin records')
  } finally {
    loading.value = false
  }
}

const goToPage = async (page: number) => {
  if (page < 1 || page > totalPages.value || page === currentPage.value) {
    return
  }

  loadingMore.value = true
  currentPage.value = page
  await loadRecords()
  loadingMore.value = false
}

const editRow = async (row: AdminListRow) => {
  await navigateTo(`/admin/${entityKey.value}/${encodeURIComponent(String(row._primary))}`)
}

const openRow = async (row: AdminListRow) => {
  if (row._public_url) {
    await navigateTo(row._public_url)
    return
  }

  await editRow(row)
}

const deleteRow = async (row: AdminListRow) => {
  const label = row._title || row._primary

  if (!window.confirm(tl('Delete "{label}" from the database?', { label: String(label) }))) {
    return
  }

  loadingMore.value = true
  errorMessage.value = ''

  try {
    await deleteAdminEntityRecord(entity.value, String(row._primary))

    if (rows.value.length === 1 && currentPage.value > 1) {
      currentPage.value -= 1
    }

    await loadRecords()
  } catch (error: any) {
    errorMessage.value = error?.data?.message || tl('Could not delete record')
  } finally {
    loadingMore.value = false
  }
}

watch(
  () => route.params.entity,
  () => {
    searchQuery.value = ''
    currentPage.value = 1
    void loadRecords()
  },
  { immediate: true }
)

watch(searchQuery, () => {
  if (searchTimer) {
    clearTimeout(searchTimer)
  }

  searchTimer = setTimeout(() => {
    currentPage.value = 1
    void loadRecords()
  }, 250)
})
</script>

<style scoped>
.admin-entity-page {
  display: grid;
  gap: 20px;
}

.admin-entity-page__header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 20px;
}

.admin-entity-page__controls {
  display: flex;
  align-items: flex-end;
  gap: 12px;
}

.admin-entity-page__header h1,
.admin-entity-page__header p {
  margin: 0;
}

.admin-entity-page__search {
  min-width: 260px;
  display: grid;
  gap: 8px;
  font-weight: 700;
}

.admin-entity-page__create {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 42px;
  padding: 0 14px;
  border: 1px solid var(--button-primary-border);
  border-radius: 999px;
  color: var(--button-primary-text);
  background: var(--button-primary-bg);
  text-decoration: none;
  font-weight: 800;
  font-size: 0.84rem;
  white-space: nowrap;
}

.admin-entity-page__search input {
  min-height: 42px;
  padding: 0 14px;
  border: 1px solid var(--color-input-border);
  border-radius: 12px;
  color: var(--color-input-text);
  background: var(--color-input-bg);
  font: inherit;
}

.admin-entity-page__state,
.admin-entity-page__empty {
  min-height: 200px;
  display: grid;
  place-items: center;
  gap: 10px;
  text-align: center;
}

.admin-entity-page__state--error {
  color: var(--color-error-text);
}

.admin-entity-table-shell {
  --admin-actions-width: 252px;
  overflow: auto;
  position: relative;
  border: 1px solid var(--color-card-border);
  border-radius: 18px;
  background: var(--color-card-surface);
}

.admin-entity-table {
  width: 100%;
  min-width: 1080px;
  border-collapse: collapse;
}

.admin-entity-table th,
.admin-entity-table td {
  padding: 12px 14px;
  border-bottom: 1px solid var(--color-border);
  text-align: left;
  vertical-align: middle;
  white-space: nowrap;
}

.admin-entity-table th {
  color: var(--color-text-muted);
  font-size: 0.78rem;
  font-weight: 800;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.admin-entity-table__row--clickable {
  cursor: pointer;
}

.admin-entity-table__row:hover {
  background: var(--color-row-hover);
}

.admin-entity-table__row:hover .admin-entity-table__actions-cell {
  background: var(--color-row-hover);
}

.admin-entity-table__actions,
.admin-entity-table__actions-cell {
  text-align: right;
}

.admin-entity-table__actions {
  position: sticky;
  right: 0;
  z-index: 3;
  width: var(--admin-actions-width);
  background: var(--color-card-surface);
  border-left: 1px solid rgba(255, 255, 255, 0.06);
  box-shadow: -10px 0 16px rgba(8, 18, 34, 0.08);
}

.admin-entity-table__actions-cell {
  position: sticky;
  right: 0;
  z-index: 2;
  display: flex;
  justify-content: flex-end;
  align-items: center;
  gap: 8px;
  min-width: var(--admin-actions-width);
  background: var(--color-card-surface);
  border-left: 1px solid rgba(255, 255, 255, 0.06);
  box-shadow: -10px 0 16px rgba(8, 18, 34, 0.08);
}

.admin-entity-table__actions::before,
.admin-entity-table__actions-cell::before {
  content: '';
  position: absolute;
  top: 0;
  bottom: 0;
  left: -18px;
  width: 18px;
  pointer-events: none;
  background: linear-gradient(90deg, rgba(8, 18, 34, 0), rgba(8, 18, 34, 0.08));
}

.admin-entity-table__action-button,
.admin-entity-page__pager {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 34px;
  padding: 0 12px;
  border: 1px solid var(--button-primary-border);
  border-radius: 999px;
  color: var(--button-primary-text);
  background: var(--button-primary-bg);
  font: inherit;
  font-weight: 800;
  font-size: 0.82rem;
  cursor: pointer;
}

.admin-entity-table__action-button--ghost {
  border-color: var(--button-control-border);
  color: var(--button-control-text);
  background: var(--button-control-bg);
}

.admin-entity-table__action-button--danger {
  border-color: var(--button-danger-border);
  color: var(--button-danger-text);
  background: var(--button-danger-bg);
}

.admin-entity-page__footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  color: var(--color-text-muted);
}

.admin-entity-page__pagination {
  display: flex;
  align-items: center;
  gap: 8px;
}

.admin-entity-page__pager--active {
  background: var(--button-primary-hover);
}

@media (max-width: 900px) {
  .admin-entity-page__header {
    align-items: stretch;
    flex-direction: column;
  }

  .admin-entity-page__controls {
    align-items: stretch;
    flex-direction: column;
  }

  .admin-entity-page__search {
    min-width: 0;
  }

  .admin-entity-page__footer {
    align-items: stretch;
    flex-direction: column;
  }

  .admin-entity-page__pagination {
    flex-wrap: wrap;
  }
}
</style>
