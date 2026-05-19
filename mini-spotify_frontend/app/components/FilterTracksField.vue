<template>
  <label class="filter-tracks-field">
    <span v-if="label" class="filter-tracks-field__label">{{ label }}</span>
    <div class="filter-tracks-field__control">
      <input
        :value="modelValue"
        type="search"
        class="filter-tracks-field__input"
        :placeholder="placeholder"
        @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
      />
      <button
        v-if="modelValue"
        type="button"
        class="filter-tracks-field__clear"
        aria-label="Clear filter"
        @click="$emit('update:modelValue', '')"
      >
        Clear
      </button>
    </div>
  </label>
</template>

<script setup lang="ts">
withDefaults(defineProps<{
  modelValue?: string
  placeholder?: string
  label?: string
}>(), {
  modelValue: '',
  placeholder: 'Filter tracks',
  label: '',
})

defineEmits<{
  'update:modelValue': [value: string]
}>()
</script>

<style scoped>
.filter-tracks-field {
  display: grid;
  gap: 8px;
}

.filter-tracks-field__label {
  color: var(--color-text-main);
  font-family: inherit;
  font-size: 0.82rem;
  font-weight: 600;
  line-height: 1.2;
}

.filter-tracks-field__control {
  position: relative;
  width: min(100%, 420px);
}

.filter-tracks-field__input {
  width: 100%;
  min-height: 36px;
  padding: 0 72px 0 12px;
  border-radius: 12px;
  border: 1px solid var(--color-input-border);
  background: var(--color-input-bg);
  color: var(--color-input-text);
  outline: none;
  font-family: inherit;
  font-size: 0.86rem;
  font-weight: 500;
  line-height: 1.2;
  transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
}

.filter-tracks-field__input:hover {
  border-color: var(--color-border-strong);
}

.filter-tracks-field__input:focus {
  border-color: var(--color-input-focus-border);
  box-shadow: 0 0 0 3px var(--color-input-focus-ring);
}

.filter-tracks-field__input::placeholder {
  color: var(--color-input-placeholder);
}

.filter-tracks-field__input::-webkit-search-cancel-button,
.filter-tracks-field__input::-webkit-search-decoration,
.filter-tracks-field__input::-webkit-search-results-button,
.filter-tracks-field__input::-webkit-search-results-decoration {
  appearance: none;
  display: none;
}

.filter-tracks-field__clear {
  position: absolute;
  top: 50%;
  right: 8px;
  transform: translateY(-50%);
  min-height: 24px;
  padding: 0 8px;
  border: 0;
  border-radius: 999px;
  background: var(--button-secondary-bg);
  color: var(--button-secondary-text);
  border: 1px solid var(--button-secondary-border);
  font-family: inherit;
  font-size: 0.72rem;
  font-weight: 600;
  cursor: pointer;
  transition: background-color 0.2s ease, color 0.2s ease;
}

.filter-tracks-field__clear:hover {
  background: var(--button-secondary-hover);
}
</style>
