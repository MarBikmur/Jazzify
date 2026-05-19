
<template>
    <EditablePage
        title="Log in"
        submitLabel="Log in"
        loadingLabel="Logging in..."
        :loading="isSubmitting"
        :showAuthErrors="showLoginErrors"
        :authError="loginError"
        authSwitchText="Don't have an account?"
        authSwitchToPath="/sign-up"
        authSwitchLinkLabel="Sign up now"
        @submit="onSubmit"
    >
        <UserDataFields
            label="Email"
            type="email"
            name="email"
            :showValidationErrors="showValidationErrors"
            inputCredentials="inputCredentials"
            v-model="form.email"
            :v$="v$.email"
            @field-focus="onFieldFocus"
        />

        <UserDataFields
            label="Password"
            type="password"
            name="current-password"
            :showValidationErrors="showValidationErrors"
            inputCredentials="inputCredentials"
            v-model="form.password"
            :v$="v$.password"
            @field-focus="onFieldFocus"
        />

        <template #errors>
            <ValidationErrorList :items="validationErrors" />
        </template>
    </EditablePage>
</template>


<script setup>
import { computed } from 'vue'
import { MIN_PASSWORD_LENGTH, useLoginForm } from '~/utils/authFormUtils'
const { tl } = useLocalizedText()

definePageMeta({
    layout: false
})

const {
    form,
    showValidationErrors,
    isSubmitting,
    authError: loginError,
    showAuthErrors: showLoginErrors,
    v$,
    onSubmit,
    onFieldFocus
} = useLoginForm()

const validationErrors = computed(() => ([
    { show: showValidationErrors.value && v$.value.email.required.$invalid, message: tl('Email is required') },
    { show: showValidationErrors.value && v$.value.email.email.$invalid, message: tl('Invalid email format') },
    { show: showValidationErrors.value && v$.value.password.required.$invalid, message: tl('Password is required') },
    {
      show: showValidationErrors.value && v$.value.password.minLength.$invalid,
      message: tl('Password must be at least {count} characters', { count: MIN_PASSWORD_LENGTH }),
    },
]))

</script>
