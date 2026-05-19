<template>
  <component :is="tagName" class="media-tile" :class="tileClass" v-bind="tagProps">
    <ArtworkCover
      class="media-tile__cover"
      :class="coverClass"
      :src="imageSrc"
      :alt="title"
      :fallback="fallback"
      :fallback-icon="fallbackIcon"
      :fallback-variant="fallbackVariant"
      :shape="shape"
      icon-size="2.35rem"
      text-size="2.3rem"
    />

    <div class="media-tile__meta">
      <strong>{{ title }}</strong>
      <span v-if="subtitle">{{ subtitle }}</span>
    </div>

    <slot />
  </component>
</template>

<script setup lang="ts">
import { computed, resolveComponent } from 'vue'

const props = withDefaults(defineProps<{
  to?: string
  title: string
  subtitle?: string
  imageSrc?: string
  fallback?: string
  fallbackIcon?: string
  fallbackVariant?: 'default' | 'playlist' | 'liked'
  shape?: 'square' | 'circle'
}>(), {
  to: '',
  subtitle: '',
  imageSrc: '',
  fallback: 'A',
  fallbackIcon: '',
  fallbackVariant: 'default',
  shape: 'square',
})

const tagName = computed(() => props.to ? resolveComponent('NuxtLink') : 'div')
const tagProps = computed(() => (props.to ? { to: props.to } : {}))
const tileClass = computed(() => ({
  'media-tile--link': !!props.to,
}))
const coverClass = computed(() => ({
  'media-tile__cover--circle': props.shape === 'circle',
}))
</script>

<style scoped>
.media-tile {
  display: grid;
  grid-template-rows: auto minmax(74px, 1fr) auto;
  gap: 10px;
  padding: 14px;
  min-height: 238px;
  height: 100%;
  border: 1px solid var(--color-card-border);
  border-radius: var(--radius-card);
  color: var(--color-text-main);
  background: var(--color-card-surface);
  box-shadow: var(--color-card-shadow);
  text-decoration: none;
}

.media-tile--link {
  transition: background-color 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
}

.media-tile--link:hover {
  background: var(--color-card-surface-hover);
  border-color: var(--color-border-strong);
  box-shadow: var(--color-card-shadow-hover);
  transform: translateY(-2px);
}

.media-tile__cover {
  width: 100%;
  aspect-ratio: 1;
  border-radius: 8px;
}

.media-tile__cover--circle {
  border-radius: 50%;
}

.media-tile__meta {
  display: grid;
  gap: 4px;
}

.media-tile__meta strong,
.media-tile__meta span {
  margin: 0;
}

.media-tile__meta strong {
  line-height: 1.3;
  min-height: 2.6em;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.media-tile__meta span {
  color: var(--color-text-muted);
  min-height: 1.35em;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
