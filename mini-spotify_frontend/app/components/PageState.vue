<template>
  <div class="page-state" :class="stateClass" :style="styleVars">
    <h2 v-if="title" class="page-state__title">{{ tl(title) }}</h2>
    <p class="page-state__message">{{ tl(message) }}</p>
    <slot />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
const { tl } = useLocalizedText()

const props = withDefaults(defineProps<{
  title?: string
  message: string
  variant?: 'info' | 'error' | 'empty'
  minHeight?: string
}>(), {
  title: '',
  variant: 'info',
  minHeight: '220px',
})

const stateClass = computed(() => `page-state--${props.variant}`)
const styleVars = computed(() => ({
  '--page-state-min-height': props.minHeight,
}))
</script>

<style scoped>
.page-state {
  min-height: var(--page-state-min-height);
  display: grid;
  gap: 8px;
  align-content: center;
  color: var(--color-text-muted);
}

.page-state__title,
.page-state__message {
  margin: 0;
}

.page-state__title {
  color: var(--color-text-main);
  font-size: 1.55rem;
  font-weight: 800;
}

.page-state--error {
  color: var(--color-error-text);
}
</style>
