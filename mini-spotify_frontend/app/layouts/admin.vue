<template>
  <div class="admin-shell">
    <aside class="admin-sidebar">
      <div class="admin-sidebar__brand">
        <strong>{{ tl('Admin panel') }}</strong>
      </div>

      <div v-if="loading" class="admin-sidebar__state">{{ tl('Loading...') }}</div>
      <div v-else-if="!canView" class="admin-sidebar__state">{{ tl('Access denied') }}</div>
      <nav v-else class="admin-sidebar__nav">
        <NuxtLink
          v-for="entity in adminEntities"
          :key="entity"
          class="admin-sidebar__link"
          :class="{ 'admin-sidebar__link--active': currentEntity === entity }"
          :to="`/admin/${entity}`"
        >
          {{ tl(getEntityLabel(entity)) }}
        </NuxtLink>
      </nav>

      <div class="admin-sidebar__footer">
        <NuxtLink class="admin-sidebar__back" to="/">
          {{ tl('Back to app') }}
        </NuxtLink>
        <button
          class="admin-sidebar__theme"
          type="button"
          :aria-label="isDarkTheme ? tl('Switch to light mode') : tl('Switch to dark mode')"
          @click="toggleTheme"
        >
          <Icon
            :icon="getIcon(isDarkTheme ? 'material-symbols:light-mode-rounded' : 'material-symbols:dark-mode-rounded')"
            class="ui-icon ui-icon--md"
          />
        </button>
      </div>
    </aside>

    <main class="admin-workspace">
      <div v-if="loading" class="admin-state">
        <span class="loader" />
        <span>{{ tl('Loading admin panel...') }}</span>
      </div>
      <div v-else-if="!canView" class="admin-state admin-state--error">
        <strong>{{ tl('Admin access required') }}</strong>
        <span>{{ tl('You do not have permission to view this panel.') }}</span>
      </div>
      <slot v-else />
    </main>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Icon } from '@iconify/vue'
import type { AdminEntity } from '~/composables/useAdmin'
const { tl } = useLocalizedText()

const route = useRoute()
const { getCurrentUser } = useAuth()
const { adminEntities, isAdminEntity, getEntityLabel } = useAdmin()
const { getIcon } = useAppIcons()

const loading = ref(true)
const canView = ref(false)
const isDarkTheme = ref(false)

const currentEntity = computed<AdminEntity | null>(() => {
  const entity = String(route.params.entity || '')
  return isAdminEntity(entity) ? entity : null
})
const applyTheme = (dark: boolean) => {
  if (typeof document === 'undefined') {
    return
  }

  document.documentElement.dataset.theme = dark ? 'dark' : 'light'
}

const toggleTheme = () => {
  isDarkTheme.value = !isDarkTheme.value
  applyTheme(isDarkTheme.value)

  if (typeof window !== 'undefined') {
    window.localStorage.setItem('jazzify-theme', isDarkTheme.value ? 'dark' : 'light')
  }
}

onMounted(async () => {
  try {
    if (typeof window !== 'undefined') {
      const savedTheme = window.localStorage.getItem('jazzify-theme')
      isDarkTheme.value = savedTheme ? savedTheme === 'dark' : false
      applyTheme(isDarkTheme.value)
    }

    const user = await getCurrentUser()
    canView.value = user?.role === 'admin'

    if (!canView.value) {
      await navigateTo('/')
    }
  } finally {
    loading.value = false
  }
})
</script>

<style scoped>
.admin-shell {
  height: 100vh;
  display: grid;
  grid-template-columns: 240px minmax(0, 1fr);
  overflow: hidden;
  background:
    radial-gradient(circle at top right, rgba(0, 163, 255, 0.12), transparent 28%),
    linear-gradient(180deg, var(--color-bg) 0%, var(--color-content-bg) 100%);
  color: var(--color-text-main);
}

.admin-sidebar {
  display: flex;
  flex-direction: column;
  gap: 20px;
  min-height: 0;
  padding: 22px 18px;
  border-right: 1px solid var(--color-shell-border);
  background: var(--color-sidebar-surface);
  overflow-y: auto;
}

.admin-sidebar__brand {
  display: grid;
  gap: 4px;
}

.admin-sidebar__brand strong {
  font-size: 1.15rem;
}

.admin-sidebar__state {
  color: var(--color-text-muted);
  font-size: 0.92rem;
}

.admin-sidebar__nav {
  display: grid;
  gap: 8px;
}

.admin-sidebar__link {
  display: flex;
  align-items: center;
  min-height: 42px;
  padding: 0 14px;
  border-radius: 12px;
  color: var(--color-text-main);
  text-decoration: none;
  font-weight: 700;
  background: transparent;
  transition: background-color 0.18s ease, color 0.18s ease;
}

.admin-sidebar__link:hover,
.admin-sidebar__link--active {
  background: var(--color-card-surface);
}

.admin-sidebar__footer {
  margin-top: auto;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.admin-sidebar__back {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 38px;
  padding: 0 14px;
  flex: 1 1 auto;
  min-width: 0;
  border: 1px solid var(--button-control-border);
  border-radius: 999px;
  color: var(--button-control-text);
  background: var(--button-control-bg);
  text-decoration: none;
  font-weight: 800;
  font-size: 0.85rem;
  white-space: nowrap;
}

.admin-sidebar__back:hover {
  background: var(--button-control-hover);
}

.admin-sidebar__theme {
  width: 38px;
  height: 38px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
  border: 1px solid var(--button-control-border);
  border-radius: 50%;
  color: var(--button-control-text);
  background: var(--button-control-bg);
  line-height: 0;
  cursor: pointer;
}

.admin-sidebar__theme:hover {
  background: var(--button-control-hover);
}

.admin-workspace {
  min-width: 0;
  min-height: 0;
  padding: 28px;
  overflow: auto;
}

.admin-state {
  min-height: 280px;
  display: grid;
  place-items: center;
  gap: 10px;
}

.admin-state--error {
  text-align: center;
}

@media (max-width: 900px) {
  .admin-shell {
    height: auto;
    min-height: 100vh;
    grid-template-columns: 1fr;
    overflow: visible;
  }

  .admin-sidebar {
    min-height: auto;
    border-right: 0;
    border-bottom: 1px solid var(--color-shell-border);
    overflow: visible;
  }

  .admin-sidebar__nav {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .admin-workspace {
    min-height: auto;
    padding: 22px;
    overflow: visible;
  }
}
</style>
