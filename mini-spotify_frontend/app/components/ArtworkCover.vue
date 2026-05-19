<template>
  <div class="artwork-cover" :class="coverClass">
    <img
      v-if="resolvedSrc"
      :src="resolvedSrc"
      :alt="alt"
      class="artwork-cover__image"
      @error="handleImageError"
    />
    <Icon
      v-else-if="fallbackIcon"
      :icon="getIcon(fallbackIcon)"
      class="artwork-cover__icon"
      :style="iconStyle"
    />
    <span v-else class="artwork-cover__text" :style="textStyle">{{ fallback }}</span>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Icon } from '@iconify/vue'
import { useAppIcons } from '~/composables/useAppIcons'

const { getIcon } = useAppIcons()

const props = withDefaults(defineProps<{
  src?: string
  alt?: string
  fallback?: string
  fallbackIcon?: string
  fallbackVariant?: 'default' | 'playlist' | 'liked'
  shape?: 'square' | 'circle'
  iconSize?: string
  textSize?: string
}>(), {
  src: '',
  alt: '',
  fallback: 'A',
  fallbackIcon: '',
  fallbackVariant: 'default',
  shape: 'square',
  iconSize: '',
  textSize: '',
})

const failedSrc = ref<string | null>(null)

const coverClass = computed(() => ({
  'artwork-cover--circle': props.shape === 'circle',
  'artwork-cover--playlist': props.fallbackVariant === 'playlist',
  'artwork-cover--liked': props.fallbackVariant === 'liked',
}))

const resolvedSrc = computed(() => {
  if (!props.src || props.src === failedSrc.value) {
    return ''
  }

  return props.src
})

const iconStyle = computed(() => (props.iconSize ? { fontSize: props.iconSize } : {}))
const textStyle = computed(() => (props.textSize ? { fontSize: props.textSize } : {}))

const handleImageError = () => {
  failedSrc.value = props.src || null
}

watch(
  () => props.src,
  () => {
    failedSrc.value = null
  },
)
</script>

<style scoped>
.artwork-cover {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  border-radius: inherit;
  background: var(--color-artwork-default-bg);
  color: var(--color-artwork-default-text);
  border: 1px solid var(--color-shell-border);
}

.artwork-cover--playlist {
  background: var(--color-artwork-playlist-bg);
  color: var(--color-artwork-playlist-text);
}

.artwork-cover--liked {
  background: var(--color-artwork-liked-bg);
  color: var(--color-artwork-liked-text);
}

.artwork-cover--circle {
  border-radius: 50%;
}

.artwork-cover__image {
  width: 100%;
  height: 100%;
  display: block;
  object-fit: cover;
  border: 0;
}

.artwork-cover__icon,
.artwork-cover__text {
  color: currentColor;
  line-height: 1;
}

.artwork-cover__text {
  font-weight: 900;
}
</style>
