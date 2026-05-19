<template>
  <header class="collection-hero">
    <ArtworkCover
      class="collection-hero__cover"
      :src="imageSrc"
      :alt="title"
      :fallback="fallback"
      :fallback-icon="fallbackIcon"
      :fallback-variant="fallbackVariant"
      :shape="shape"
      icon-size="4rem"
      text-size="3rem"
    />

    <div class="collection-hero__meta">
      <span class="collection-hero__type">{{ typeLabel }}</span>
      <slot name="title">
        <h1>{{ title }}</h1>
      </slot>
      <slot name="subtitle">
        <p v-if="subtitle">{{ subtitle }}</p>
      </slot>
    </div>

    <div v-if="$slots.side" class="collection-hero__side">
      <slot name="side" />
    </div>

    <div v-if="$slots['side-bottom']" class="collection-hero__side-bottom">
      <slot name="side-bottom" />
    </div>
  </header>
</template>

<script setup lang="ts">
withDefaults(defineProps<{
  typeLabel: string
  title: string
  subtitle?: string
  imageSrc?: string
  fallback?: string
  fallbackIcon?: string
  fallbackVariant?: 'default' | 'playlist' | 'liked'
  shape?: 'square' | 'circle'
}>(), {
  subtitle: '',
  imageSrc: '',
  fallback: 'A',
  fallbackIcon: '',
  fallbackVariant: 'default',
  shape: 'square',
})
</script>

<style scoped>
.collection-hero {
  position: relative;
  display: flex;
  align-items: flex-end;
  gap: 28px;
  padding: 48px;
  background: var(--color-hero-bg);
  border: 1px solid var(--color-hero-border);
  border-radius: 22px;
  box-shadow: var(--color-hero-shadow);
}

.collection-hero__cover {
  width: min(230px, 28vw);
  aspect-ratio: 1;
  flex: 0 0 auto;
  border-radius: 18px;
  box-shadow: var(--shadow-soft);
}

.collection-hero__meta {
  flex: 1 1 auto;
  display: grid;
  gap: 12px;
  min-width: 0;
}

.collection-hero__side {
  flex: 0 0 auto;
  margin-left: auto;
  align-self: flex-start;
}

.collection-hero__side-bottom {
  position: absolute;
  right: 48px;
  bottom: 26px;
  display: flex;
  justify-content: flex-end;
}

.collection-hero__type {
  font-weight: 800;
  color: var(--color-primary);
  text-transform: uppercase;
  font-size: 0.78rem;
  letter-spacing: 0.08em;
}

.collection-hero__meta :deep(h1) {
  margin: 0;
  font-size: clamp(3rem, 8vw, 6rem);
  line-height: 0.95;
  color: var(--color-text-main);
  overflow-wrap: anywhere;
}

.collection-hero__meta :deep(p) {
  margin: 6px 0 0;
  display: flex;
  gap: 8px;
  align-items: center;
  color: var(--color-text-muted);
  font-weight: 700;
}

@media (max-width: 1120px) {
  .collection-hero {
    display: grid;
    grid-template-columns: minmax(160px, 220px) minmax(0, 1fr);
    align-items: end;
    gap: 22px;
    padding: 36px;
  }

  .collection-hero__cover {
    width: min(220px, 26vw);
  }

  .collection-hero__meta :deep(h1) {
    font-size: clamp(2.6rem, 7vw, 4.8rem);
    line-height: 0.98;
  }

  .collection-hero__side {
    grid-column: 2;
    justify-self: start;
    margin-left: 0;
  }

  .collection-hero__side-bottom {
    position: static;
    grid-column: 2;
    justify-content: flex-start;
  }
}

@media (max-width: 860px) {
  .collection-hero {
    grid-template-columns: minmax(108px, 140px) minmax(0, 1fr);
    align-items: end;
    gap: 18px;
    padding: 28px 22px;
  }

  .collection-hero__cover {
    width: 100%;
  }

  .collection-hero__meta {
    gap: 10px;
    align-self: end;
  }

  .collection-hero__meta :deep(h1) {
    font-size: clamp(2rem, 7vw, 3rem);
    line-height: 0.98;
  }

  .collection-hero__meta :deep(p) {
    flex-wrap: wrap;
  }

  .collection-hero__side,
  .collection-hero__side-bottom {
    grid-column: 1 / -1;
    width: 100%;
  }

  .collection-hero__side {
    margin-top: 4px;
  }

  .collection-hero__side-bottom {
    justify-content: flex-start;
  }
}

@media (max-width: 760px) {
  .collection-hero {
    padding: 28px 20px;
  }

  .collection-hero__cover {
    width: min(220px, 70vw);
  }

  .collection-hero__side {
    margin-left: 0;
  }

  .collection-hero__side-bottom {
    right: 28px;
    bottom: 20px;
  }
}

@media (max-width: 560px) {
  .collection-hero {
    grid-template-columns: 96px minmax(0, 1fr);
    gap: 16px;
    padding: 24px 16px;
  }

  .collection-hero__meta :deep(h1) {
    font-size: clamp(1.8rem, 8vw, 2.5rem);
  }

  .collection-hero__type {
    font-size: 0.72rem;
  }

  .collection-hero__meta :deep(p) {
    margin-top: 2px;
    gap: 6px;
    font-size: 0.95rem;
  }
}
</style>
