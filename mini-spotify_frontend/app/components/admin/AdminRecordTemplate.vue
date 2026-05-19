<template>
  <section class="admin-record-template">
    <header class="admin-record-template__header">
      <div>
        <span class="admin-record-template__eyebrow">{{ tl(eyebrow) }}</span>
        <h1>{{ tl(title) }}</h1>
        <p v-if="description">{{ tl(description) }}</p>
      </div>

      <div class="admin-record-template__actions">
        <NuxtLink
          v-if="backTo && backLabel"
          class="admin-record-template__back"
          :to="backTo"
        >
          {{ tl(backLabel) }}
        </NuxtLink>

        <NuxtLink
          v-if="publicUrl"
          class="admin-record-template__open"
          :to="publicUrl"
        >
          {{ tl(publicLabel) }}
        </NuxtLink>

        <slot name="actions" />
      </div>
    </header>

    <div v-if="loading" class="admin-record-template__state">
      <span class="loader" />
      <span>{{ tl(loadingLabel) }}</span>
    </div>
    <div v-else-if="errorMessage" class="admin-record-template__state admin-record-template__state--error">
      {{ errorMessage }}
    </div>
    <div v-else class="admin-record-template__card">
      <slot />
    </div>
  </section>
</template>

<script setup lang="ts">
const { tl } = useLocalizedText()

withDefaults(defineProps<{
  eyebrow: string
  title: string
  description?: string
  backTo?: string
  backLabel?: string
  publicUrl?: string | null
  publicLabel?: string
  loading?: boolean
  loadingLabel?: string
  errorMessage?: string
}>(), {
  description: '',
  backTo: '',
  backLabel: '',
  publicUrl: null,
  publicLabel: 'Open page',
  loading: false,
  loadingLabel: 'Loading...',
  errorMessage: '',
})
</script>

<style scoped>
.admin-record-template {
  display: grid;
  gap: 20px;
}

.admin-record-template__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 20px;
}

.admin-record-template__eyebrow {
  display: inline-block;
  margin-bottom: 8px;
  color: var(--color-primary);
  font-size: 0.78rem;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.admin-record-template__header h1,
.admin-record-template__header p {
  margin: 0;
}

.admin-record-template__header p {
  margin-top: 8px;
  color: var(--color-text-muted);
}

.admin-record-template__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  justify-content: flex-end;
}

.admin-record-template__back,
.admin-record-template__open,
:slotted(.admin-record-template__action) {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 38px;
  padding: 0 14px;
  border-radius: 999px;
  text-decoration: none;
  font-weight: 800;
  font-size: 0.84rem;
}

.admin-record-template__back {
  border: 1px solid var(--button-control-border);
  color: var(--button-control-text);
  background: var(--button-control-bg);
}

.admin-record-template__open,
:slotted(.admin-record-template__action--primary) {
  border: 1px solid var(--button-primary-border);
  color: var(--button-primary-text);
  background: var(--button-primary-bg);
}

:slotted(.admin-record-template__action) {
  border: 1px solid var(--button-control-border);
  color: var(--button-control-text);
  background: var(--button-control-bg);
  cursor: pointer;
}

:slotted(.admin-record-template__action--danger) {
  border-color: var(--button-danger-border);
  color: var(--button-danger-text);
  background: var(--button-danger-bg);
}

.admin-record-template__state {
  min-height: 240px;
  display: grid;
  place-items: center;
  gap: 10px;
}

.admin-record-template__state--error {
  color: var(--color-error-text);
}

.admin-record-template__card {
  padding: 22px;
  border: 1px solid var(--color-card-border);
  border-radius: 18px;
  background: var(--color-card-surface);
}

@media (max-width: 900px) {
  .admin-record-template__header {
    flex-direction: column;
    align-items: stretch;
  }

  .admin-record-template__actions {
    justify-content: stretch;
  }
}
</style>
