<template>
  <p class="form-notice" :class="noticeClass">
    <slot>{{ message }}</slot>
  </p>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(defineProps<{
  message?: string
  variant?: 'info' | 'error' | 'success'
}>(), {
  message: '',
  variant: 'info',
})

const noticeClass = computed(() => `form-notice--${props.variant}`)
</script>

<style scoped>
.form-notice {
  width: 100%;
  margin: 0;
  padding: 0.75rem 1rem;
  border-radius: 12px;
  font-size: 0.9rem;
}

.form-notice--info {
  color: var(--color-info-text);
  background: rgba(0, 106, 202, 0.10);
  border: 1px solid rgba(0, 106, 202, 0.28);
}

.form-notice--error {
  color: var(--color-error-text);
  background: rgba(239, 68, 68, 0.10);
  border: 1px solid rgba(239, 68, 68, 0.30);
}

.form-notice--success {
  color: var(--color-success-text);
  background: rgba(34, 197, 94, 0.10);
  border: 1px solid rgba(34, 197, 94, 0.28);
}
</style>
