<template>
  <section class="collection-tracks">
    <div v-if="$slots.header" class="tracks-head">
      <slot name="header" />
    </div>

    <table v-if="hasRows" class="tracks-table">
      <thead>
        <tr>
          <slot name="head" />
        </tr>
      </thead>
      <tbody>
        <slot name="body" />
      </tbody>
    </table>

    <div v-else-if="$slots.empty" class="collection-empty">
      <slot name="empty" />
    </div>
  </section>
</template>

<script setup lang="ts">
withDefaults(defineProps<{
  hasRows?: boolean
}>(), {
  hasRows: true,
})
</script>

<style scoped>
.collection-tracks {
  width: calc(100% - 96px);
  margin-left: 48px;
  margin-right: 48px;
}

.tracks-head {
  margin-bottom: 8px;
  display: inline-flex;
  align-items: baseline;
  gap: 10px;
}

.tracks-head :deep(h2) {
  margin: 0;
  font-size: 1.1rem;
  color: var(--color-text-main);
}

.tracks-head :deep(span) {
  color: var(--color-text-muted);
  font-size: 0.9rem;
}

.tracks-table {
  width: 100%;
  margin-top: 0;
  margin-bottom: 48px;
  border-spacing: 0;
  border-collapse: collapse;
  table-layout: auto;
  color: var(--color-text-main);
  background: var(--color-card-surface);
  border: 1px solid var(--color-card-border);
  border-radius: 18px;
  overflow: hidden;
  box-shadow: var(--color-card-shadow);
}

.tracks-table :deep(th),
.tracks-table :deep(td) {
  padding: 14px 18px;
  text-align: left;
}

.tracks-table :deep(th) {
  color: var(--color-text-muted);
  font-weight: 700;
  border-bottom: 1px solid var(--color-border);
  background: var(--color-surface);
}

.tracks-table :deep(th:first-child),
.tracks-table :deep(td:first-child) {
  width: 52px;
  color: var(--color-text-soft);
}

.tracks-table :deep(td:nth-child(2) > strong),
.tracks-table :deep(td:nth-child(2) > span) {
  display: block;
}

.tracks-table :deep(td:nth-child(2)) {
  display: grid;
  gap: 3px;
}

.collection-empty {
  margin-bottom: 36px;
}

@media (max-width: 760px) {
  .collection-tracks {
    width: calc(100% - 56px);
    margin-left: 28px;
    margin-right: 28px;
  }

  .tracks-table {
    margin-bottom: 36px;
  }
}

@media (max-width: 960px) {
  .collection-tracks {
    width: calc(100% - 48px);
    margin-left: 24px;
    margin-right: 24px;
  }

  .tracks-table :deep(th),
  .tracks-table :deep(td) {
    padding: 12px 14px;
  }
}

@media (max-width: 560px) {
  .collection-tracks {
    width: calc(100% - 32px);
    margin-left: 16px;
    margin-right: 16px;
  }

  .tracks-head {
    gap: 8px;
  }

  .tracks-table {
    border-radius: 14px;
  }

  .tracks-table :deep(th),
  .tracks-table :deep(td) {
    padding: 10px 12px;
    font-size: 0.92rem;
  }

  .tracks-table :deep(th:first-child),
  .tracks-table :deep(td:first-child) {
    width: 36px;
  }
}
</style>
