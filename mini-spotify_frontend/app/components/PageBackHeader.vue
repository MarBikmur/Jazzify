<template>
  <div class="page-back-header">
    <NuxtLink class="page-back-header__link" :to="backTo">{{ tl(backLabel) }}</NuxtLink>

    <div v-if="$slots.actions" class="page-back-header__actions">
      <slot name="actions" />
    </div>
  </div>
</template>

<script setup lang="ts">
const { tl } = useLocalizedText()

withDefaults(defineProps<{
  backTo: string
  backLabel?: string
}>(), {
  backLabel: 'Back',
})
</script>

<style scoped>
.page-back-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 20px;
}

.page-back-header__link {
  display: inline-flex;
  color: var(--color-text-muted);
  font-weight: 800;
  text-decoration: none;
}

.page-back-header__link:hover {
  color: var(--color-primary);
}

.page-back-header__actions {
  display: flex;
  align-items: center;
  gap: 12px;
}

@media (max-width: 760px) {
  .page-back-header {
    align-items: flex-start;
    flex-direction: column;
  }
}
</style>
