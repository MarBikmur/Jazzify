<template>
    <div class="page">
        <CredentialsForm>
            <h1 class="title">{{ tl(title) }}</h1>
            <form @submit.prevent="$emit('submit')">
                <slot/>

                <slot name="errors"/>

                <p class="error" v-if="showAuthErrors && authError">{{ tl(authError) }}</p>

                <SubmitButton
                    v-if="showSubmit"
                    :style="submitButtonStyle"
                    :disabled="loading"
                    :loading="loading"
                    :label="submitLabel"
                    :loadingLabel="loadingLabel"
                />

                <slot name="footer"/>

                <div v-if="authSwitchToPath && authSwitchLinkLabel" class="auth-switch-section">
                    <span>{{ tl(authSwitchText) }}</span>
                    <NuxtLink :to="authSwitchToPath" class="auth-switch-link">{{ tl(authSwitchLinkLabel) }}</NuxtLink>
                </div>
            </form>
        </CredentialsForm>
    </div>
</template>

<script setup lang="ts">
const { tl } = useLocalizedText()

const props = defineProps({
    title: { type: String, required: true },
    submitLabel: { type: String, required: true },
    loadingLabel: { type: String, default: 'Loading...' },
    loading: { type: Boolean, default: false },
    submitButtonStyle: { type: String, default: 'width: 40%;' },
    showSubmit: { type: Boolean, default: true },

    showAuthErrors: { type: Boolean, default: false },
    authError: { type: String as any, default: '' },
    authSwitchText: { type: String, default: '' },
    authSwitchToPath: { type: String, default: '' },
    authSwitchLinkLabel: { type: String, default: '' }
})

defineEmits(['submit'])
</script>

<style scoped>
@import url('https://api.fontshare.com/v2/css?f[]=spotcast@400,500&display=swap');

.title{
    margin: 0;
    text-align: center;
}

.invalid {
    border-color: #ef4444 !important;
}

.page {
	min-height: 100vh;
	display: flex;
	align-items: center;
	justify-content: center;
    position: relative;
	background:
    var(--gradient-glow),
    var(--gradient-page);
	color: var(--color-text-main);
    font-family: 'Spotcast', sans-serif;
}

.page::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background:
      var(--gradient-glow),
      var(--gradient-page);
    z-index: -1;
}

form{
    display: flex;
    flex-direction: column;
    width: min(100%, 350px);
    margin: auto;
    gap: 14px;
    justify-content: center;
    text-align: left;
}

.invalid {
    border-color: #ef4444 !important;
}

.error{
    color: var(--color-error) !important;
    font-size: 0.76em;
    display: flex;
    align-items: center;
    gap: 6px;
    width: min(100%, 225px);
    margin-top: 0.1em;
    text-align: left;
    margin-left: auto;
    margin-right: auto;
    margin-bottom: 0.05em;
}

.error::before {
    content: "!";
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.2em;
    height: 1.2em;
    margin-right: 0px;
    background: var(--color-error);
    color: white;
    border-radius: 50%;
    font-weight: bold;
    font-size: 0.95em;
    box-shadow: 0 1px 2px rgba(0,0,0,0.10);
}

.auth-switch-section{
    margin-top: 4px;
    padding: 10px ;
    font-size: 0.80em;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    justify-self: center;
    text-align: center;
    gap: 6px;
    margin-left: auto;
    margin-right: auto;
}

.auth-switch-link{
    color: var(--color-primary);
    text-decoration: underline;
}

.auth-switch-link:hover {
    color: var(--color-primary-hover);
}

@media (max-width: 640px) {
    .page {
        padding: 16px;
    }
}

</style>
