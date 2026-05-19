<template>
  <div class="discover-genre-modal">
    <button class="discover-genre-modal__backdrop" type="button" :aria-label="tl('Close discover genre modal')" @click="$emit('close')" />

    <section class="discover-genre-modal__panel" role="dialog" aria-modal="true" aria-labelledby="discover-genre-title">
      <header class="discover-genre-modal__header">
        <div class="discover-genre-modal__title-block">
          <span class="discover-genre-modal__eyebrow">{{ tl('Recommendations') }}</span>
          <h2 id="discover-genre-title">{{ tl('Discover a new genre') }}</h2>
          <p>{{ tl('Pick a genre and open a dedicated page with 5 tracks similar to the music you already liked.') }}</p>
        </div>

        <button class="discover-genre-modal__close" type="button" :aria-label="tl('Close')" @click="$emit('close')">
          <Icon :icon="getIcon('solar:close-circle-linear')" />
        </button>
      </header>

      <section class="discover-genre-modal__controls">
        <div class="discover-genre-modal__field">
          <label>{{ tl('Genre') }}</label>
          <div class="discover-genre-modal__genre-list">
            <button
              v-for="genre in genres"
              :key="genre"
              type="button"
              class="discover-genre-modal__genre-chip"
              :class="{ 'discover-genre-modal__genre-chip--active': selectedGenre === genre }"
              :disabled="isLoadingGenres"
              @click="selectedGenre = genre"
            >
              {{ genre }}
            </button>
          </div>
        </div>

        <button
          class="discover-genre-modal__submit"
          type="button"
          :disabled="!selectedGenre || isLoadingGenres"
          @click="submitGenre"
        >
          {{ tl('Open recommendations') }}
        </button>
      </section>

      <p v-if="genresError" class="discover-genre-modal__state discover-genre-modal__state--error">{{ genresError }}</p>
      <p v-else-if="isLoadingGenres" class="discover-genre-modal__state">{{ tl('Loading genres...') }}</p>
      <p v-else-if="!genres.length" class="discover-genre-modal__state">{{ tl('No genres with tracks are available yet.') }}</p>

    </section>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { Icon } from '@iconify/vue'
import { useAppIcons } from '~/composables/useAppIcons'
const { tl } = useLocalizedText()

const emit = defineEmits<{
  close: []
  submit: [genre: string]
}>()

const { getIcon } = useAppIcons()
const { getRecommendationGenres } = useRecommendations()

const genres = ref<string[]>([])
const selectedGenre = ref('')
const isLoadingGenres = ref(false)
const genresError = ref('')

const loadGenres = async () => {
  isLoadingGenres.value = true
  genresError.value = ''

  try {
    genres.value = await getRecommendationGenres()
    if (genres.value.length && !selectedGenre.value) {
      selectedGenre.value = genres.value[0] || ''
    }
  } catch (error: any) {
    genresError.value = error?.data?.message || tl('Could not load genres')
    genres.value = []
  } finally {
    isLoadingGenres.value = false
  }
}

const submitGenre = () => {
  if (!selectedGenre.value) {
    return
  }
  emit('submit', selectedGenre.value)
}

onMounted(async () => {
  await loadGenres()
})
</script>

<style scoped>
.discover-genre-modal {
  position: fixed;
  inset: 0;
  z-index: 40;
}

.discover-genre-modal__backdrop {
  position: absolute;
  inset: 0;
  border: 0;
  background: var(--color-overlay-strong);
}

.discover-genre-modal__panel {
  position: absolute;
  top: 50%;
  left: 50%;
  width: min(920px, calc(100vw - 32px));
  max-height: min(86vh, 820px);
  display: grid;
  gap: 18px;
  padding: 24px;
  overflow: auto;
  transform: translate(-50%, -50%);
  border: 1px solid var(--color-card-border);
  border-radius: 24px;
  background: var(--color-card-surface);
  box-shadow: var(--shadow-menu);
}

.discover-genre-modal__header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 16px;
}

.discover-genre-modal__title-block {
  display: grid;
  gap: 6px;
}

.discover-genre-modal__title-block h2 {
  margin: 0;
  color: var(--color-text-main);
  font-size: clamp(1.7rem, 1.2rem + 1.6vw, 2.2rem);
  line-height: 1.04;
}

.discover-genre-modal__title-block p {
  margin: 0;
  color: var(--color-text-muted);
  max-width: 48ch;
}

.discover-genre-modal__eyebrow {
  color: var(--color-primary);
  font-size: 0.76rem;
  font-weight: 900;
  letter-spacing: 0.14em;
  text-transform: uppercase;
}

.discover-genre-modal__close {
  width: 38px;
  height: 38px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
  border-radius: 50%;
  border: 1px solid var(--button-control-border);
  background: var(--button-control-bg);
  color: var(--button-control-text);
  cursor: pointer;
}

.discover-genre-modal__controls {
  display: flex;
  align-items: end;
  gap: 14px;
  flex-wrap: wrap;
}

.discover-genre-modal__field {
  display: grid;
  gap: 7px;
  min-width: min(320px, 100%);
  flex: 1 1 320px;
}

.discover-genre-modal__field label {
  color: var(--color-text-main);
  font-size: 0.92rem;
  font-weight: 800;
}

.discover-genre-modal__genre-list {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.discover-genre-modal__genre-chip {
  min-height: 40px;
  padding: 0 14px;
  border: 1px solid var(--button-secondary-border);
  border-radius: 999px;
  color: var(--button-secondary-text);
  background: var(--button-secondary-bg);
  font: inherit;
  font-weight: 700;
  cursor: pointer;
}

.discover-genre-modal__genre-chip:hover:not(:disabled) {
  background: var(--button-secondary-hover);
}

.discover-genre-modal__genre-chip--active {
  color: var(--button-primary-text);
  background: var(--button-primary-bg);
  border-color: var(--button-primary-border);
}

.discover-genre-modal__genre-chip:disabled {
  opacity: 0.55;
  cursor: wait;
}

.discover-genre-modal__submit {
  min-height: 46px;
  padding: 0 20px;
  border: 1px solid var(--button-primary-border);
  border-radius: 999px;
  color: var(--button-primary-text);
  background: var(--button-primary-bg);
  font: inherit;
  font-weight: 800;
  cursor: pointer;
}

.discover-genre-modal__submit:disabled {
  opacity: 0.55;
  cursor: wait;
}

.discover-genre-modal__state {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.94rem;
}

.discover-genre-modal__state--error {
  color: var(--color-error-text);
}

@media (max-width: 760px) {
  .discover-genre-modal__panel {
    width: calc(100vw - 16px);
    max-height: calc(100vh - 16px);
    max-height: calc(100dvh - 16px);
    padding: 18px;
  }

  .discover-genre-modal__controls {
    align-items: stretch;
  }

  .discover-genre-modal__submit {
    width: 100%;
  }
}
</style>
