<template>
    <label class="selector-field">
        <span>{{ tl(label) }}</span>
        <select
            class="selector-field__select"
            :class="{ 'selector-field__select--invalid': showValidationErrors && v$?.$invalid }"
            :value="modelValue"
            @change="handleChange"
            @blur="handleBlur"
            @focus="handleFocus"
        >
            <option value="" disabled selected>{{ tl(placeholder || 'Select an option') }}</option>
            <option v-for="option in options" :key="option.value" :value="option.value">
                {{ option.label }}
            </option>
        </select>
    </label>
</template>

<script setup lang="ts">
const { tl } = useLocalizedText()

interface Props {
    label: string
    options: { value: string, label: string }[]
    modelValue: string
    showValidationErrors: boolean
    v$: any
    placeholder?: string
}

interface Emits {
    (event: 'update:modelValue', value: string): void
    (event: 'field-focus'): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const handleChange = (event: Event): void => {
    const target = event.target as HTMLSelectElement
    emit('update:modelValue', target.value)
}

const handleBlur = (): void => {
    if (props.v$?.$touch) {
        props.v$.$touch()
    }
}

const handleFocus = (): void => {
    emit('field-focus')
}
</script>

<style scoped>
.selector-field {
    font-size: 14px;
    margin-left: 74px;
    display: flex;
    align-items: center;
    gap: 12px;
    opacity: 1;
    text-align: left;
    color: var(--color-text-main);
}

.selector-field__select {
    margin-top: 5px;
    appearance: none;
    padding: 11px 14px;
    border-radius: 10px;
    border: 1px solid var(--color-border);
    background: var(--color-surface);
    color: var(--color-text-main);
    outline: none;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
    box-sizing: border-box;
    cursor: pointer;
}

.selector-field::after {
    position: absolute;
    right: 20px;
    top: 50%;
    transform: translateY(-50%);
    pointer-events: none;
    color: var(--color-text-muted);
    font-size: 12px;
    transition: color 0.2s ease;
}

.selector-field__select:hover {
    border-color: var(--color-border-strong);
}

.selector-field__select:hover + .selector-field__label,
.selector-field:hover::after {
    color: var(--color-text-main);
}

.selector-field__select:focus-visible {
    border-color: var(--color-primary);
    box-shadow: 0 0 0 3px rgba(0, 106, 202, 0.16);
}

.selector-field__select:disabled {
    background-color: var(--color-bg-soft);
    cursor: not-allowed;
    border-color: var(--color-border);
    color: var(--color-text-soft);
}

.selector-field__select:disabled:hover {
    border-color: var(--color-border);
}

.selector-field:has(.selector-field__select:disabled)::after {
    color: var(--color-text-soft);
}

.selector-field__select--invalid {
    border-color: var(--color-error);
}

</style>
