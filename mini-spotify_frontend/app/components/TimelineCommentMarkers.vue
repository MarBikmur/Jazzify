<template>
  <div v-if="markers.length" class="timeline-comment-markers" aria-hidden="true">
    <span
      v-for="marker in markers"
      :key="`track-comment-marker-${marker.timestamp}`"
      class="timeline-comment-marker"
      :class="{ 'timeline-comment-marker--active': marker.isActive }"
      :style="{ left: `${marker.percent}%` }"
      :title="marker.count > 1 ? `${marker.count} comments at ${formatTime(marker.timestamp)}` : `Comment at ${formatTime(marker.timestamp)}`"
    />
  </div>
</template>

<script setup lang="ts">
import type { TimelineCommentMarker } from '~/composables/useTrackComments'

defineProps<{
  markers: TimelineCommentMarker[]
}>()

const { formatTime } = useAudioPlayer()
</script>

<style scoped>
.timeline-comment-markers {
  position: absolute;
  inset: 0;
  z-index: 2;
  pointer-events: none;
}

.timeline-comment-marker {
  position: absolute;
  top: 50%;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #ffffff;
  box-shadow: 0 0 0 1px rgba(10, 24, 44, 0.18);
  transform: translate(-50%, -50%);
  opacity: 0.98;
}

.timeline-comment-marker--active {
  width: 10px;
  height: 10px;
  background: #111827;
  box-shadow:
    0 0 0 2px rgba(255, 255, 255, 0.9),
    0 0 0 5px rgba(17, 24, 39, 0.22);
}

:global([data-theme='dark']) .timeline-comment-marker {
  background: #f8fbff;
  box-shadow:
    0 0 0 1px rgba(4, 14, 28, 0.86),
    0 0 0 2px rgba(248, 251, 255, 0.22);
}

:global([data-theme='dark']) .timeline-comment-marker--active {
  background: #ffffff;
  box-shadow:
    0 0 0 2px rgba(7, 18, 34, 0.96),
    0 0 0 5px rgba(255, 255, 255, 0.24);
}
</style>
