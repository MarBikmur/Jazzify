<template>
  <div v-if="visibleItems.length" class="validation-errors">
    <p v-for="item in visibleItems" :key="item.message" class="validation-errors__item">
      {{ tl(item.message) }}
    </p>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
const { tl } = useLocalizedText()

interface ValidationErrorItem {
  show: boolean
  message: string
}

const props = defineProps<{
  items: ValidationErrorItem[]
}>()

const visibleItems = computed(() => props.items.filter((item) => item.show))
</script>

<style scoped>
.validation-errors {
  display: grid;
  gap: 6px;
  width: min(100%, 225px);
  margin: 0 auto;
}

.validation-errors__item {
  color: var(--color-error);
  font-size: 0.76em;
  display: flex;
  align-items: center;
  gap: 6px;
  margin: 0.1em 0 0.05em;
  text-align: left;
}

.validation-errors__item::before {
  content: "!";
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.2em;
  height: 1.2em;
  background: var(--color-error);
  color: white;
  border-radius: 50%;
  font-weight: bold;
  font-size: 0.95em;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
}
</style>
