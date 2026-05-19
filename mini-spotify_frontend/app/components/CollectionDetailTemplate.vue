<template>
  <section class="collection-page">
    <PageState v-if="loading" :message="loadingMessage" :min-height="loadingMinHeight" />
    <PageState
      v-else-if="errorMessage"
      variant="error"
      :message="errorMessage"
      :min-height="loadingMinHeight"
    />

    <template v-else>
      <section class="collection-detail">
        <CollectionHero
          :class="heroClass"
          :type-label="typeLabel"
          :title="title"
          :subtitle="subtitle"
          :image-src="imageSrc"
          :fallback="fallback"
          :fallback-icon="fallbackIcon"
          :fallback-variant="fallbackVariant"
          :shape="heroShape"
        >
          <template v-if="$slots['back-actions']" #side>
            <slot name="back-actions" />
          </template>
          <template v-if="$slots['actions-side']" #side-bottom>
            <slot name="actions-side" />
          </template>
          <template v-if="$slots['hero-title']" #title>
            <slot name="hero-title" />
          </template>
          <template v-if="$slots['hero-subtitle']" #subtitle>
            <slot name="hero-subtitle" />
          </template>
        </CollectionHero>

        <div class="collection-actions">
          <div class="collection-actions__main">
            <slot name="actions-main" />
          </div>
        </div>

        <div v-if="$slots.notices" class="collection-notices">
          <slot name="notices" />
        </div>

        <div v-if="$slots.extra" class="collection-extra">
          <slot name="extra" />
        </div>

        <div v-if="$slots['table-section']" class="collection-table-section">
          <slot name="table-section" />
        </div>

        <div v-if="$slots['extra-bottom']" class="collection-extra collection-extra--bottom">
          <slot name="extra-bottom" />
        </div>
      </section>
    </template>
  </section>
</template>

<script setup lang="ts">
withDefaults(defineProps<{
  loading?: boolean
  errorMessage?: string
  loadingMessage?: string
  loadingMinHeight?: string
  backTo?: string
  backLabel?: string
  typeLabel: string
  title: string
  subtitle?: string
  imageSrc?: string
  fallback?: string
  fallbackIcon?: string
  fallbackVariant?: 'default' | 'playlist' | 'liked'
  heroShape?: 'square' | 'circle'
  heroClass?: string
}>(), {
  loading: false,
  errorMessage: '',
  loadingMessage: 'Loading...',
  loadingMinHeight: '260px',
  backLabel: 'Back',
  subtitle: '',
  imageSrc: '',
  fallback: 'A',
  fallbackIcon: '',
  fallbackVariant: 'default',
  heroShape: 'square',
  heroClass: '',
})
</script>

<style scoped>
.collection-page {
  width: 100%;
  min-height: 100%;
  color: var(--color-text-main);
}

.collection-detail {
  margin: -56px -48px -120px;
  min-height: calc(100vh - 160px);
  background-color: var(--color-content-bg);
  background:
    var(--gradient-glow),
    var(--gradient-page);
  box-shadow: inset 0 1px 0 var(--color-shell-border);
}

.collection-actions {
  display: flex;
  align-items: center;
  justify-content: flex-start;
  gap: 20px;
  padding: 12px 48px 18px;
}

.collection-actions__main {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 20px;
}

.collection-notices,
.collection-extra {
  width: calc(100% - 96px);
  margin-left: 48px;
  margin-right: 48px;
}

.collection-table-section {
  width: 100%;
}

.collection-notices {
  display: grid;
  gap: 12px;
  margin-bottom: 16px;
}

.collection-extra {
  margin-bottom: 28px;
}

.collection-extra--bottom {
  margin-top: 8px;
  margin-bottom: 36px;
}

@media (max-width: 1120px) {
  .collection-detail {
    margin: -56px -32px -120px;
  }

  .collection-actions {
    padding: 12px 32px 18px;
  }

  .collection-notices,
  .collection-extra {
    width: calc(100% - 64px);
    margin-left: 32px;
    margin-right: 32px;
  }
}

@media (max-width: 860px) {
  .collection-detail {
    margin: -40px -24px -120px;
    min-height: auto;
  }

  .collection-actions {
    padding: 10px 24px 16px;
    align-items: flex-start;
    flex-direction: column;
    gap: 14px;
  }

  .collection-actions__main {
    gap: 12px;
  }

  .collection-notices,
  .collection-extra {
    width: calc(100% - 48px);
    margin-left: 24px;
    margin-right: 24px;
  }
}

@media (max-width: 760px) {
  .collection-detail {
    margin: -28px -18px -120px;
  }

  .collection-actions {
    padding: 5px 18px 10px;
    align-items: flex-start;
    flex-direction: column;
  }

  .collection-notices,
  .collection-extra {
    width: calc(100% - 36px);
    margin-left: 18px;
    margin-right: 18px;
  }
}
</style>
