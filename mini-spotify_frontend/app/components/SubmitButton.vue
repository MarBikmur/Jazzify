<template>
    <button
        class="submit-button"
        type="submit"
        :disabled="disabled"
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

const props = withDefaults(defineProps<Props>(), {
    disabled: false,
    loading: false,
    label: 'Submit',
    loadingLabel: 'Loading...'
})
</script>

<style scoped>
.submit-button {
    margin-top: 4px;
    padding: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-left: auto;
    margin-right: auto;
    color: var(--button-primary-text);
    background-color: var(--button-primary-bg);
    border: 1px solid var(--button-primary-border);
    border-radius: 20px;
    box-shadow: var(--shadow-primary);
    font-weight: 700;
    font-size: 15px;
    transition: transform 0.3s ease, background-color 0.3s ease, box-shadow 0.3s ease;
    cursor: pointer;
}

.submit-button:hover:not(:disabled) {
    transform: scale(1.05);
    background-color: var(--button-primary-hover);
    box-shadow: var(--shadow-primary);
}

.submit-button:active:not(:disabled) {
    background-color: var(--button-primary-hover);
    transform: scale(0.99);
}

.submit-button:disabled {
    background-color: var(--color-text-soft);
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

.submit-button:disabled:hover {
    transform: none;
    background-color: var(--color-text-soft);
}
</style>
