<template>
  <Teleport to="body">
    <div v-if="visible" class="track-context-menu-layer">
      <div
        ref="menuRef"
        class="track-context-menu"
        :style="menuStyle"
        @contextmenu.prevent
      >
        <template v-for="item in items" :key="item.key">
          <div v-if="item.separator" class="track-context-menu__separator" />
          <div
            v-else
            class="track-context-menu__row"
            @mouseenter="setOpenSubmenu(item)"
          >
            <button
              class="track-context-menu__item"
              :class="{ 'track-context-menu__item--danger': item.danger }"
              type="button"
              :disabled="item.disabled"
              @click="onItemClick(item)"
            >
              <span class="track-context-menu__item-main">
                <Icon
                  v-if="item.icon"
                  :icon="getIcon(item.icon)"
                  class="track-context-menu__icon"
                />
                <span>{{ item.label }}</span>
              </span>
              <Icon
                v-if="item.children?.length"
                :icon="getIcon('solar:alt-arrow-right-linear')"
                class="track-context-menu__arrow"
              />
            </button>

            <div
              v-if="item.children?.length && openSubmenuKey === item.key"
              class="track-context-menu track-context-menu--submenu"
              :style="submenuStyle"
            >
              <template v-for="child in item.children" :key="child.key">
                <div v-if="child.separator" class="track-context-menu__separator" />
                <button
                  v-else
                  class="track-context-menu__item"
                  :class="{ 'track-context-menu__item--danger': child.danger }"
                  type="button"
                  :disabled="child.disabled"
                  @click="onItemClick(child)"
                >
                  <span class="track-context-menu__item-main">
                    <Icon
                      v-if="child.icon"
                      :icon="getIcon(child.icon)"
                      class="track-context-menu__icon"
                    />
                    <span>{{ child.label }}</span>
                  </span>
                </button>
              </template>
            </div>
          </div>
        </template>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { Icon } from '@iconify/vue'
import { useAppIcons } from '~/composables/useAppIcons'

export interface TrackContextMenuItem {
  key: string
  label?: string
  icon?: string
  danger?: boolean
  disabled?: boolean
  separator?: boolean
  action?: () => void | Promise<void>
  children?: TrackContextMenuItem[]
}

const props = withDefaults(defineProps<{
  visible?: boolean
  x?: number
  y?: number
  items?: TrackContextMenuItem[]
}>(), {
  visible: false,
  x: 0,
  y: 0,
  items: () => [],
})

const emit = defineEmits<{
  close: []
}>()

const { getIcon } = useAppIcons()
const menuRef = ref<HTMLElement | null>(null)
const openSubmenuKey = ref<string | null>(null)
const hasWindow = typeof window !== 'undefined'
const hasDocument = typeof document !== 'undefined'

const menuWidth = 220
const submenuWidth = 196
const viewportPadding = 12

const menuLeft = computed(() => {
  if (typeof window === 'undefined') {
    return props.x
  }

  return Math.min(props.x, window.innerWidth - menuWidth - viewportPadding)
})

const menuTop = computed(() => {
  if (typeof window === 'undefined') {
    return props.y
  }

  return Math.min(props.y, window.innerHeight - 320 - viewportPadding)
})

const submenuLeft = computed(() => {
  if (typeof window === 'undefined') {
    return menuWidth + 6
  }

  const spaceRight = window.innerWidth - menuLeft.value - menuWidth - viewportPadding
  return spaceRight >= submenuWidth ? menuWidth + 6 : -submenuWidth - 6
})

const menuStyle = computed(() => ({
  left: `${Math.max(viewportPadding, menuLeft.value)}px`,
  top: `${Math.max(viewportPadding, menuTop.value)}px`,
}))

const submenuStyle = computed(() => ({
  left: `${submenuLeft.value}px`,
  top: '0px',
}))

const closeMenu = () => {
  openSubmenuKey.value = null
  emit('close')
}

const onPointerDown = (event: MouseEvent) => {
  if (!menuRef.value?.contains(event.target as Node)) {
    closeMenu()
  }
}

const onEscape = (event: KeyboardEvent) => {
  if (event.key === 'Escape') {
    closeMenu()
  }
}

const onViewportChange = () => {
  closeMenu()
}

const setOpenSubmenu = (item: TrackContextMenuItem) => {
  openSubmenuKey.value = item.children?.length ? item.key : null
}

const onItemClick = async (item: TrackContextMenuItem) => {
  if (item.disabled || item.children?.length) {
    return
  }

  closeMenu()
  await item.action?.()
}

watch(
  () => props.visible,
  (visible) => {
    if (visible) {
      if (hasDocument) {
        document.addEventListener('mousedown', onPointerDown)
        document.addEventListener('keydown', onEscape)
      }

      if (hasWindow) {
        window.addEventListener('resize', onViewportChange)
        window.addEventListener('scroll', onViewportChange, true)
      }

      return
    }

    openSubmenuKey.value = null

    if (hasDocument) {
      document.removeEventListener('mousedown', onPointerDown)
      document.removeEventListener('keydown', onEscape)
    }

    if (hasWindow) {
      window.removeEventListener('resize', onViewportChange)
      window.removeEventListener('scroll', onViewportChange, true)
    }
  },
  { immediate: true }
)

onBeforeUnmount(() => {
  if (hasDocument) {
    document.removeEventListener('mousedown', onPointerDown)
    document.removeEventListener('keydown', onEscape)
  }

  if (hasWindow) {
    window.removeEventListener('resize', onViewportChange)
    window.removeEventListener('scroll', onViewportChange, true)
  }
})
</script>

<style scoped>
.track-context-menu-layer {
  position: fixed;
  inset: 0;
  z-index: 1200;
  pointer-events: none;
}

.track-context-menu {
  position: fixed;
  width: 220px;
  display: grid;
  gap: 2px;
  padding: 4px;
  border: 1px solid var(--color-card-border);
  border-radius: 14px;
  background: var(--color-card-surface);
  box-shadow: var(--shadow-menu);
  pointer-events: auto;
  font-family: 'Spotcast', Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
}

.track-context-menu--submenu {
  position: absolute;
  width: 196px;
}

.track-context-menu__row {
  position: relative;
}

.track-context-menu__item {
  width: 100%;
  min-height: 36px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 8px 10px;
  border: 0;
  border-radius: 4px;
  color: var(--color-text-main);
  background: transparent;
  text-align: left;
  font-family: inherit;
  font-size: 0.89rem;
  font-weight: 500;
  line-height: 1.25;
  cursor: pointer;
}

.track-context-menu__item:hover:not(:disabled),
.track-context-menu__item:focus-visible {
  background: var(--color-card-surface-hover);
  outline: none;
}

.track-context-menu__item:disabled {
  opacity: 0.45;
  cursor: default;
}

.track-context-menu__item--danger {
  color: var(--color-error-text);
}

.track-context-menu__item-main {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
}

.track-context-menu__icon,
.track-context-menu__arrow {
  flex: 0 0 auto;
  font-size: 0.92rem;
}

.track-context-menu__separator {
  height: 1px;
  margin: 4px 6px;
  background: var(--color-border);
}
</style>
