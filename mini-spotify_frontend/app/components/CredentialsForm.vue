<template>
    <section class="credentials-form">
        <slot />
    </section>
</template>

<style scoped>
.credentials-form {
    min-height: 350px;
    width: 733px;
    display: flex;
    flex-direction: column;
    gap: 1rem;
    margin: 0 auto;
    background: var(--color-card-surface);
    border: 1px solid var(--color-card-border);
    border-radius: 18px;
    padding: 24px;
    box-sizing: border-box;
    box-shadow: var(--color-card-shadow);
}
</style>
