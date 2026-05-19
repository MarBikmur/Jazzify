<template>
    <button
        class="cancel-button"
        type="button"
        :disabled="disabled"
        @click="handleClick"
    >
        {{ loading ? tl(loadingLabel) : tl(label) }}
    </button>
</template>

<script setup lang="ts">
const { tl } = useLocalizedText()

interface Props {
    disabled?: boolean
    loading?: boolean
    label?: string
    loadingLabel?: string
}

interface Emits {
    (event: 'click'): void
}

const props = withDefaults(defineProps<Props>(), {
    disabled: false,
    loading: false,
    label: 'Cancel',
    loadingLabel: 'Loading...'
})

const emit = defineEmits<Emits>()

const handleClick = (): void => {
    if (!props.disabled && !props.loading) {
        emit('click')
    }
}
</script>

<style scoped>
.cancel-button {
    margin-top: 4px;
    padding: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-left: auto;
    margin-right: auto;
    width: 50%;
    color: var(--button-secondary-text);
    background-color: var(--button-secondary-bg);
    border: 1px solid var(--button-secondary-border);
    border-radius: 20px;
    font-weight: 700;
    font-size: 15px;
    transition: transform 0.3s ease, background-color 0.3s ease, border-color 0.3s ease;
    cursor: pointer;
}

.cancel-button:hover:not(:disabled) {
    transform: scale(1.05);
    background-color: var(--button-secondary-hover);
    border-color: var(--color-border-strong);
}

.cancel-button:active:not(:disabled) {
    transform: scale(0.99);
}

.cancel-button:disabled {
    background-color: var(--color-bg-soft);
    color: var(--color-text-soft);
    border-color: var(--color-border);
    cursor: not-allowed;
    transform: none;
}

.cancel-button:disabled:hover {
    transform: none;
    background-color: var(--color-bg-soft);
}
</style>
