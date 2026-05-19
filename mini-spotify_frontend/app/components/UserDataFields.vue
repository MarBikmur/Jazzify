<template>
  <label class="field">
    <span class="field__label">{{ tl(label) }}</span>
    <input 
      :type="type"
      :class="[
        inputCredentials, 
        { invalid: showValidationErrors && v$?.$invalid }
      ]"
      :name="name"
      :autocomplete="name"
      :value="modelValue"
      @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
      @blur="v$?.$touch()"
      @focus="$emit('field-focus')"
    />
  </label>
</template>

<script setup lang="ts">
const { tl } = useLocalizedText()

interface Props {
  label: string
  type: string
  showValidationErrors: boolean
  inputCredentials: string
  modelValue: string
  v$: any
  name: string
}

interface Emits {
  (e: 'update:modelValue', value: string): void
  (e: 'field-focus'): void
}

defineProps<Props>()
defineEmits<Emits>()
</script>

<style scoped>
.field {
  display: grid;
  gap: 6px;
  width: min(100%, 225px);
  margin: 0 auto;
}

.field__label {
  font-size: 14px;
  opacity: 1;
  text-align: left;
  display: block;
  color: var(--color-text-main);
}

.inputCredentials {
  width: 100%;
  box-sizing: border-box;
  appearance: none;
  padding: 11px 14px;
  border-radius: 10px;
  border: 1px solid var(--color-border);
  background: var(--color-surface);
  color: var(--color-text-main);
  outline: none;
  transition: border-color .2s ease, box-shadow .2s ease;
}

.inputCredentials:hover {
  border-color: var(--color-border-strong);
}

.inputCredentials::placeholder {
  color: var(--color-text-soft);
}

.inputCredentials:focus {
  border-color: var(--color-primary);
  box-shadow: 0 0 0 3px rgba(0, 106, 202, 0.16);
}

.invalid {
  border-color: var(--color-error) !important;
}
</style>
