import { reactive, ref } from 'vue'
import useVuelidate from '@vuelidate/core'
import { required, email as emailValidator, minLength } from "@vuelidate/validators"
import { useAuth } from '~/composables/useAuth'
import { useRouter } from 'vue-router'

export const MIN_PASSWORD_LENGTH = 8

interface AuthResponse {
  success: boolean
  message?: string
  user?: {
    id: number
    name: string
    email: string
  }
}

interface AuthFormOptions {
  mode: 'login' | 'signup'
  redirectPath?: string
}

export const useAuthForm = (options: AuthFormOptions) => {
  const { tl } = useLocalizedText()
  const { mode, redirectPath } = options
  const form = reactive({
    email: '',
    password: '',
    ...(mode === 'signup' && { name: '' })
  })
  const showValidationErrors = ref(false)
  const isSubmitting = ref(false)
  const authError = ref('')
  const showAuthErrors = ref(false)
  const rules = {
    email: { required, email: emailValidator },
    password: { required, minLength: minLength(MIN_PASSWORD_LENGTH) },
    ...(mode === 'signup' && { name: { required, minLength: minLength(2) } })
  }

  const v$ = useVuelidate(rules, form)
  const { login, register } = useAuth()
  const router = useRouter()

  const onSubmit = async () => {
    v$.value.$touch()
    showValidationErrors.value = true

    const isFormCorrect = await v$.value.$validate()

    if (isFormCorrect) {
      isSubmitting.value = true
      authError.value = ''
      showAuthErrors.value = false
      try {
        let response: AuthResponse

        if (mode === 'login') {
          response = await login({
            email: form.email,
            password: form.password
          })
        } else {
          response = await register({
            name: form.name,
            email: form.email,
            password: form.password
          })
        }
        if (response.success) {
          const redirectTo = redirectPath || (mode === 'login' ? '/' : '/login')
          router.push(redirectTo)
        } else {
          authError.value = response.message || getDefaultErrorMessage()
          showAuthErrors.value = true
        }
      } catch (e) {
        authError.value = getDefaultErrorMessage()
        showAuthErrors.value = true
      } finally {
        isSubmitting.value = false
      }
    }
  }

  const onFieldFocus = () => {
    showValidationErrors.value = false
    showAuthErrors.value = false
  }

  const getDefaultErrorMessage = () => {
    return mode === 'login' 
      ? tl('Error during login')
      : tl('Error during registration')
  }

  return {
    form,
    showValidationErrors,
    isSubmitting,
    authError,
    showAuthErrors,
    v$,
    onSubmit,
    onFieldFocus,
    mode
  }
}
export const useLoginForm = (redirectPath?: string) => {
  return useAuthForm({ mode: 'login', redirectPath })
}

export const useSignupForm = (redirectPath?: string) => {
  return useAuthForm({ mode: 'signup', redirectPath })
}
