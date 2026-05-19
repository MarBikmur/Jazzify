<template>
  <div class="section-header">
    <div class="section-header__copy">
      <h2>{{ tl(title) }}</h2>
      <p v-if="subtitle">{{ tl(subtitle) }}</p>
    </div>

    <div v-if="hasAside" class="section-header__aside">
      <span v-if="count !== undefined && count !== null" class="section-header__count">{{ count }}</span>
      <slot name="aside" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, useSlots } from 'vue'
const { tl } = useLocalizedText()

const props = defineProps<{
  title: string
  subtitle?: string
  count?: string | number | null
}>()

const slots = useSlots()
const hasAside = computed(() => props.count !== undefined && props.count !== null || !!slots.aside)
</script>

<style scoped>
.section-header {
  display: flex;
  align-items: baseline;
  justify-content: flex-start;
  gap: 8px;
}

.section-header__copy {
  display: grid;
  gap: 4px;
}

.section-header__copy h2,
.section-header__copy p,
.section-header__count {
  margin: 0;
}

.section-header__copy h2 {
  color: var(--color-text-main);
  font-size: 1.22rem;
}

.section-header__copy p,
.section-header__count {
  color: var(--color-text-muted);
}

.section-header__aside {
  display: flex;
  align-items: baseline;
  gap: 8px;
  margin-left: 0;
}
</style>
