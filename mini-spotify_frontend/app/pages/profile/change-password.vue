<template>
  <EditorPageTemplate
    title="Change password"
    submitLabel="Update password"
    loadingLabel="Saving..."
    :loading="loading"
    contentWidth="620px"
    @submit="submitPasswordChange"
  >
    <div class="password-editor">
      <UserDataFields
        label="Current password"
        type="password"
        name="current-password"
        :show-validation-errors="false"
        input-credentials="inputCredentials"
        :model-value="form.currentPassword"
        :v$="null"
        @update:modelValue="form.currentPassword = $event"
      />

      <UserDataFields
        label="New password"
        type="password"
        name="new-password"
        :show-validation-errors="false"
        input-credentials="inputCredentials"
        :model-value="form.newPassword"
        :v$="null"
        @update:modelValue="form.newPassword = $event"
      />

      <FormNotice v-if="errorMessage" variant="error" :message="errorMessage" />
      <FormNotice v-if="successMessage" variant="success" :message="successMessage" />
    </div>

    <template #footer>
      <div class="password-editor__footer">
        <button type="button" class="password-editor__secondary" :disabled="loading" @click="goBackToProfile">
          Back to profile
        </button>
        <CancelButton
          :disabled="loading"
          label="Clear"
          @click="resetForm"
        />
      </div>
    </template>
  </EditorPageTemplate>
</template>

<script setup lang="ts">
import { onBeforeUnmount, onMounted, reactive, ref, watchEffect } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '~/composables/useAuth'
import { useEditorLayout } from '~/composables/useEditorLayout'
import { MIN_PASSWORD_LENGTH } from '~/utils/authFormUtils'

const router = useRouter()
const { getCurrentUser, changePassword } = useAuth()
const { setEditorPanel, clearEditorPanel } = useEditorLayout()
const { tl } = useLocalizedText()

const loading = ref(false)
const errorMessage = ref('')
const successMessage = ref('')

const form = reactive({
  currentPassword: '',
  newPassword: '',
})

const resetForm = () => {
  form.currentPassword = ''
  form.newPassword = ''
  errorMessage.value = ''
  successMessage.value = ''
}

const submitPasswordChange = async () => {
  if (loading.value) {
    return
  }

  if (!form.currentPassword || !form.newPassword) {
    errorMessage.value = tl('Fill in both password fields')
    successMessage.value = ''
    return
  }

  if (form.newPassword.length < MIN_PASSWORD_LENGTH) {
    errorMessage.value = tl('Password must be at least {count} characters', { count: MIN_PASSWORD_LENGTH })
    successMessage.value = ''
    return
  }

  loading.value = true
  errorMessage.value = ''
  successMessage.value = ''

  const result = await changePassword({
    current_password: form.currentPassword,
    new_password: form.newPassword,
  })

  if (result.success) {
    successMessage.value = result.message || 'Password changed successfully'
    form.currentPassword = ''
    form.newPassword = ''
  } else {
    errorMessage.value = result.message || 'Could not change password'
  }

  loading.value = false
}

const goBackToProfile = async () => {
  await router.push('/profile/edit')
}

watchEffect(() => {
  setEditorPanel({
    eyebrow: 'Security',
    title: 'Change password',
    description: 'Confirm your current password before setting a new one.',
    imageShape: 'square',
    imageFallback: 'S',
    stats: [
      { label: 'Current', value: form.currentPassword ? 'Entered' : 'Missing' },
      { label: 'New', value: form.newPassword ? 'Ready' : 'Missing' },
    ],
    sections: [
      {
        title: 'Checklist',
        items: ['Enter the current password', 'Choose a new password with at least 8 characters', 'Save only when both fields are filled'],
      },
    ],
  })
})

onMounted(async () => {
  const currentUser = await getCurrentUser()

  if (!currentUser) {
    await router.replace('/login')
  }
})

onBeforeUnmount(() => {
  clearEditorPanel()
})
</script>

<style scoped>
.password-editor {
  display: grid;
  gap: 14px;
}

.password-editor__footer {
  display: flex;
  gap: 12px;
  align-items: center;
  justify-content: center;
}

.password-editor__secondary {
  min-width: 180px;
  min-height: 44px;
  padding: 0 20px;
  border: 1px solid var(--color-border);
  border-radius: 999px;
  background: var(--color-surface);
  color: var(--color-primary);
  font-family: 'Spotcast', sans-serif;
  font-size: 0.95rem;
  font-weight: 500;
  cursor: pointer;
  transition: background-color 0.2s ease, border-color 0.2s ease, transform 0.2s ease;
}

.password-editor__secondary:hover:not(:disabled) {
  background: var(--color-surface-hover);
  border-color: var(--color-border-strong);
  transform: scale(1.03);
}

.password-editor__secondary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>
