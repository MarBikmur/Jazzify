<template>
  <button class="listen-pill-button" type="button" :disabled="disabled" @click="$emit('click')">
    <span>{{ label }}</span>
    <span class="listen-pill-button__symbol" />
  </button>
</template>

<script setup lang="ts">
withDefaults(defineProps<{
  label: string
  disabled?: boolean
}>(), {
  disabled: false,
})

defineEmits<{
  click: []
}>()
</script>

<style scoped>
.listen-pill-button {
  min-height: 44px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  padding: 0 22px;
  border: 0;
  border-radius: 999px;
  color: var(--button-primary-text);
  background: var(--button-primary-bg);
  border: 1px solid var(--button-primary-border);
  box-shadow: var(--shadow-primary);
  font: inherit;
  font-weight: 800;
  cursor: pointer;
  transition: background-color 0.2s ease, transform 0.2s ease;
}

.listen-pill-button > span {
  color: currentColor;
}

.listen-pill-button:hover:not(:disabled) {
  background: var(--button-primary-hover);
  transform: scale(1.04);
}

.listen-pill-button:active:not(:disabled) {
  background: var(--button-primary-hover);
  transform: scale(0.99);
}

.listen-pill-button:disabled {
  cursor: wait;
  opacity: 0.65;
}

.listen-pill-button__symbol {
  width: 0;
  height: 0;
  margin-left: 2px;
  color: currentColor;
  border-top: 7px solid transparent;
  border-bottom: 7px solid transparent;
  border-left: 10px solid currentColor;
}
</style>
