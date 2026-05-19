<template>
  <button
    class="icon-button small icon-button--comments"
    :class="{ active: isOpen }"
    type="button"
    :disabled="disabled"
    :aria-label="isOpen ? 'Close track comments' : 'Open track comments'"
    @click="$emit('click')"
  >
    <Icon :icon="getIcon('material-symbols:comment-rounded')" class="ui-icon ui-icon--sm" />
  </button>
</template>

<script setup lang="ts">
import { Icon } from '@iconify/vue'
import { useAppIcons } from '~/composables/useAppIcons'

defineProps<{
  isOpen: boolean
  disabled?: boolean
}>()

defineEmits<{
  click: []
}>()

const { getIcon } = useAppIcons()
</script>
