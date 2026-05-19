<template>
  <button
    type="button"
    class="follow-toggle-button"
    :class="{ 'follow-toggle-button--active': active }"
    :disabled="disabled || loading"
  >
    {{ loading ? tl(loadingLabel) : active ? tl(activeLabel) : tl(idleLabel) }}
  </button>
</template>

<script setup lang="ts">
const { tl } = useLocalizedText()

withDefaults(defineProps<{
  active?: boolean
  loading?: boolean
  disabled?: boolean
  idleLabel?: string
  activeLabel?: string
  loadingLabel?: string
}>(), {
  active: false,
  loading: false,
  disabled: false,
  idleLabel: 'Follow',
  activeLabel: 'Following',
  loadingLabel: 'Saving...',
})
</script>

<style scoped>
.follow-toggle-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 34px;
  padding: 0 16px;
  border: 1px solid var(--button-secondary-border);
  border-radius: 999px;
  background: var(--button-secondary-bg);
  color: var(--button-secondary-text);
  font: inherit;
  font-size: 0.85rem;
  font-weight: 800;
  cursor: pointer;
  transition: background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease, transform 0.2s ease;
}

.follow-toggle-button:hover {
  background: var(--button-secondary-hover);
}

.follow-toggle-button--active {
  border-color: var(--button-primary-border);
  background: var(--button-primary-bg);
  color: var(--button-primary-text);
  box-shadow: var(--shadow-primary);
}

.follow-toggle-button--active:hover {
  background: var(--button-primary-hover);
}

.follow-toggle-button:disabled {
  opacity: 0.7;
  cursor: not-allowed;
  transform: none;
}
</style>
