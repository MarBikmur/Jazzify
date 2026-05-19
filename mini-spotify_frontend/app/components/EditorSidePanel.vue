<template>
  <aside v-if="panel" class="editor-side-panel">
    <div class="editor-side-panel__hero">
      <div
        class="editor-side-panel__art"
        :class="{ 'editor-side-panel__art--circle': panel.imageShape === 'circle' }"
      >
        <img v-if="panel.imageUrl" :src="panel.imageUrl" :alt="panel.title" />
        <span v-else>{{ panel.imageFallback || fallbackLetter }}</span>
      </div>

      <div class="editor-side-panel__copy">
        <span v-if="panel.eyebrow" class="editor-side-panel__eyebrow">{{ panel.eyebrow }}</span>
        <strong>{{ panel.title }}</strong>
        <p v-if="panel.description">{{ panel.description }}</p>
      </div>
    </div>

    <div v-if="panel.stats?.length" class="editor-side-panel__stats">
      <div v-for="stat in panel.stats" :key="stat.label" class="editor-side-panel__stat">
        <span>{{ stat.label }}</span>
        <strong>{{ stat.value }}</strong>
      </div>
    </div>

    <section
      v-for="section in panel.sections || []"
      :key="section.title"
      class="editor-side-panel__section"
    >
      <h3>{{ section.title }}</h3>
      <ul>
        <li v-for="item in section.items" :key="item">{{ item }}</li>
      </ul>
    </section>

    <p v-if="panel.note" class="editor-side-panel__note">{{ panel.note }}</p>
  </aside>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { EditorPanelConfig } from '~/composables/useEditorLayout'

const props = defineProps<{
  panel: EditorPanelConfig | null
}>()

const fallbackLetter = computed(() => props.panel?.title?.trim()?.[0]?.toUpperCase() || 'E')
</script>

<style scoped>
.editor-side-panel {
  min-height: 0;
  display: grid;
  align-content: start;
  gap: 16px;
  padding: 20px;
  overflow: auto;
  background: var(--color-card-surface);
  border: 1px solid var(--color-card-border);
  border-radius: 20px;
  box-shadow: var(--color-card-shadow);
  backdrop-filter: blur(16px);
}

.editor-side-panel__hero {
  display: grid;
  gap: 14px;
}

.editor-side-panel__art {
  width: 100%;
  aspect-ratio: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  border-radius: 18px;
  background: var(--color-artwork-playlist-bg);
  color: var(--color-artwork-playlist-text);
  border: 1px solid var(--color-shell-border);
  font-size: 2.8rem;
  font-weight: 900;
}

.editor-side-panel__art--circle {
  border-radius: 50%;
}

.editor-side-panel__art img {
  width: 100%;
  height: 100%;
  display: block;
  object-fit: cover;
}

.editor-side-panel__copy {
  display: grid;
  gap: 6px;
}

.editor-side-panel__copy strong,
.editor-side-panel__copy p,
.editor-side-panel__eyebrow {
  margin: 0;
}

.editor-side-panel__eyebrow {
  color: var(--color-primary);
  font-size: 0.76rem;
  font-weight: 800;
  text-transform: uppercase;
}

.editor-side-panel__copy strong {
  color: var(--color-text-main);
  font-size: 1.28rem;
  line-height: 1.2;
}

.editor-side-panel__copy p,
.editor-side-panel__note {
  color: var(--color-text-muted);
  line-height: 1.45;
}

.editor-side-panel__stats {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
}

.editor-side-panel__stat {
  display: grid;
  gap: 4px;
  padding: 12px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: 14px;
}

.editor-side-panel__stat span {
  color: var(--color-text-muted);
  font-size: 0.78rem;
}

.editor-side-panel__stat strong {
  color: var(--color-text-main);
  font-size: 0.98rem;
}

.editor-side-panel__section {
  display: grid;
  gap: 10px;
  padding: 14px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: 14px;
}

.editor-side-panel__section h3,
.editor-side-panel__section ul {
  margin: 0;
}

.editor-side-panel__section h3 {
  color: var(--color-text-main);
  font-size: 0.94rem;
}

.editor-side-panel__section ul {
  padding-left: 18px;
  color: var(--color-text-muted);
  display: grid;
  gap: 6px;
}

.editor-side-panel__note {
  padding: 14px;
  background: var(--color-surface);
  border-radius: 14px;
  border: 1px solid var(--color-border);
}
</style>
