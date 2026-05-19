<template>
  <div class="playlist-add-panel">
    <div class="playlist-add-panel__header">
      <h2>{{ tl('Add a track') }}</h2>
      <p>{{ tl('From liked songs') }}</p>
    </div>

    <PageState v-if="loading" message="Loading liked songs..." min-height="120px" />
    <template v-else>
      <FilterTracksField v-model="queryModel" :placeholder="tl('Filter tracks')" />

      <div v-if="songs.length" class="playlist-add-panel__list">
        <div class="playlist-add-panel__head">
          <span>{{ tl('Title') }}</span>
          <span>{{ tl('Album') }}</span>
          <span>{{ tl('Artist') }}</span>
          <span>{{ tl('Duration') }}</span>
          <span />
        </div>

        <div
          v-for="song in songs"
          :key="song.id"
          class="playlist-add-panel__row"
        >
          <div class="playlist-add-panel__cell playlist-add-panel__cell--title">
            <strong>{{ song.title }}</strong>
          </div>
          <div class="playlist-add-panel__cell">
            <span>{{ song.album?.title || '—' }}</span>
          </div>
          <div class="playlist-add-panel__cell">
            <span>{{ song.artist?.name || tl('Artist') }}</span>
          </div>
          <div class="playlist-add-panel__cell playlist-add-panel__duration">
            {{ formatDuration(song.duration) }}
          </div>
          <div class="playlist-add-panel__action">
            <button
              type="button"
              class="playlist-add-panel__button"
              :disabled="addingId === song.id"
              @click="$emit('add-song', song)"
            >
              {{ tl('Add') }}
            </button>
          </div>
        </div>
      </div>

      <PageState v-else message="No more tracks to add, or no matches." min-height="120px" />
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { PlaylistSong } from '~/composables/usePlaylist'
import { formatDuration } from '~/utils/audioDuration'
const { tl } = useLocalizedText()

const props = withDefaults(defineProps<{
  loading?: boolean
  songs?: PlaylistSong[]
  query?: string
  addingId?: number | null
}>(), {
  loading: false,
  songs: () => [],
  query: '',
  addingId: null,
})

const emit = defineEmits<{
  'update:query': [value: string]
  'add-song': [song: PlaylistSong]
}>()

const queryModel = computed({
  get: () => props.query,
  set: (value: string) => emit('update:query', value),
})
</script>

<style scoped>
.playlist-add-panel {
  padding: 16px;
  background: var(--color-card-surface);
  border: 1px solid var(--color-card-border);
  border-radius: 18px;
  box-shadow: var(--color-card-shadow);
  display: grid;
  gap: 12px;
  font-family: inherit;
}

.playlist-add-panel__header {
  display: grid;
  gap: 4px;
}

.playlist-add-panel__header h2,
.playlist-add-panel__header p {
  margin: 0;
}

.playlist-add-panel__header h2 {
  color: var(--color-text-main);
  font-size: 1rem;
  font-weight: 700;
  line-height: 1.1;
}

.playlist-add-panel__header p {
  color: var(--color-text-muted);
  font-size: 0.78rem;
  font-weight: 500;
  line-height: 1.2;
}

.playlist-add-panel__list {
  display: grid;
  gap: 0;
}

.playlist-add-panel__head,
.playlist-add-panel__row {
  display: grid;
  grid-template-columns: minmax(0, 2.1fr) minmax(0, 2fr) minmax(0, 1.7fr) minmax(88px, auto) minmax(104px, auto);
  gap: 12px;
  align-items: center;
}

.playlist-add-panel__head {
  padding: 4px 0 6px;
  color: var(--color-text-soft);
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
}

.playlist-add-panel__head span:last-child {
  justify-self: end;
}

.playlist-add-panel__row {
  min-height: 44px;
  padding: 6px 0;
}

.playlist-add-panel__row + .playlist-add-panel__row {
  border-top: 1px solid rgba(255, 255, 255, 0.04);
}

.playlist-add-panel__cell {
  min-width: 0;
}

.playlist-add-panel__cell strong,
.playlist-add-panel__cell span {
  display: block;
  min-width: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.playlist-add-panel__cell strong {
  color: var(--color-text-main);
  font-size: 0.82rem;
  font-weight: 700;
  line-height: 1.2;
}

.playlist-add-panel__cell span {
  color: var(--color-text-muted);
  font-size: 0.74rem;
  font-weight: 500;
  line-height: 1.2;
}

.playlist-add-panel__duration {
  color: var(--color-text-soft);
  font-size: 0.74rem;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
  text-align: right;
}

.playlist-add-panel__action {
  min-width: 0;
  text-align: right;
}

.playlist-add-panel__button {
  flex-shrink: 0;
  min-width: 58px;
  min-height: 30px;
  padding: 0 12px;
  border: 1px solid var(--button-primary-border);
  border-radius: 999px;
  background: var(--button-primary-bg);
  color: var(--button-primary-text);
  box-shadow: var(--shadow-primary);
  font-family: inherit;
  font-size: 0.8rem;
  font-weight: 700;
  cursor: pointer;
  transition: background-color 0.2s ease, transform 0.2s ease, opacity 0.2s ease;
}

.playlist-add-panel__button:hover:not(:disabled) {
  background: var(--button-primary-hover);
  transform: scale(1.03);
}

.playlist-add-panel__button:disabled {
  opacity: 0.6;
  cursor: wait;
}
</style>
