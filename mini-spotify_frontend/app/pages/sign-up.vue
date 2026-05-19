<template>
  <EditablePage
    title="Sign up"
    submitLabel="Sign up"
    loadingLabel="Signing up..."
    :loading="isSubmitting"
    :showAuthErrors="showSignupErrors"
    :authError="signupError"
    authSwitchText="Already have an account?"
    authSwitchToPath="/login"
    authSwitchLinkLabel="Log in"
    @submit="onSubmit"
  >
    <UserDataFields
      label="Username"
      type="text"
      name="username"
      :showValidationErrors="showValidationErrors"
      inputCredentials="inputCredentials"
      v-model="form.name"
      :v$="v$.name"
      @field-focus="onFieldFocus"
    />
    
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
      name="password"
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
import { MIN_PASSWORD_LENGTH, useSignupForm } from '~/utils/authFormUtils'
const { tl } = useLocalizedText()

definePageMeta({
    layout: false
})

const {
    form,
    showValidationErrors,
    isSubmitting,
    authError: signupError,
    showAuthErrors: showSignupErrors,
    v$,
    onSubmit,
    onFieldFocus
} = useSignupForm()

const validationErrors = computed(() => ([
    { show: showValidationErrors.value && v$.value.name.required.$invalid, message: tl('Username is required') },
    { show: showValidationErrors.value && v$.value.name.minLength.$invalid, message: tl('Username must be at least 2 characters') },
    { show: showValidationErrors.value && v$.value.email.required.$invalid, message: tl('Email is required') },
    { show: showValidationErrors.value && v$.value.email.email.$invalid, message: tl('Invalid email format') },
    { show: showValidationErrors.value && v$.value.password.required.$invalid, message: tl('Password is required') },
    {
      show: showValidationErrors.value && v$.value.password.minLength.$invalid,
      message: tl('Password must be at least {count} characters', { count: MIN_PASSWORD_LENGTH }),
    },
]))

</script>

