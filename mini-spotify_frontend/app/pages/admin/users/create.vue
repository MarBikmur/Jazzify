<template>
  <AdminRecordTemplate
    eyebrow="Users"
    title="Create user"
    description="Create a new account and choose its platform role."
    back-to="/admin/users"
    back-label="Back to users"
  >
    <form class="admin-create-form" @submit.prevent="submitUser">
      <label class="admin-create-form__field">
        <span>{{ tl('Display name') }}</span>
        <input v-model="form.name" type="text" maxlength="255" :placeholder="tl('Display name')">
      </label>

      <label class="admin-create-form__field">
        <span>{{ tl('Email') }}</span>
        <input v-model="form.email" type="email" maxlength="255" placeholder="user@example.com">
      </label>

      <label class="admin-create-form__field">
        <span>{{ tl('Password') }}</span>
        <input v-model="form.password" type="password" minlength="8" :placeholder="tl('Minimum 8 characters')">
      </label>

      <label class="admin-create-form__field">
        <span>{{ tl('Confirm password') }}</span>
        <input v-model="confirmPassword" type="password" minlength="8" :placeholder="tl('Repeat password')">
      </label>

      <div class="admin-create-form__field">
        <span>{{ tl('Role') }}</span>
        <div class="admin-role-picker" role="radiogroup" :aria-label="tl('User role')">
          <button
            v-for="roleOption in roleOptions"
            :key="roleOption"
            type="button"
            class="admin-role-picker__option"
            :class="{ 'admin-role-picker__option--active': form.role === roleOption }"
            @click="form.role = roleOption"
          >
            {{ roleOption }}
          </button>
        </div>
      </div>

      <div class="admin-create-form__footer">
        <span v-if="message" class="admin-create-form__message">{{ message }}</span>
        <button type="submit" class="admin-create-form__save" :disabled="submitting">
          {{ submitting ? tl('Creating...') : tl('Create user') }}
        </button>
      </div>
    </form>
  </AdminRecordTemplate>
</template>

<script setup lang="ts">
import { reactive, ref } from 'vue'

definePageMeta({
  layout: 'admin',
})

const { tl } = useLocalizedText()
const { createAdminEntityRecord } = useAdmin()

const roleOptions = ['user', 'artist', 'admin'] as const
const form = reactive({
  name: '',
  email: '',
  password: '',
  role: 'user' as typeof roleOptions[number],
})
const confirmPassword = ref('')
const submitting = ref(false)
const message = ref('')

const submitUser = async () => {
  message.value = ''

  if (form.password.length < 8) {
    message.value = tl('Password must be at least 8 characters')
    return
  }

  if (form.password !== confirmPassword.value) {
    message.value = tl('Password confirmation does not match')
    return
  }

  submitting.value = true

  try {
    await createAdminEntityRecord('users', {
      name: form.name.trim(),
      email: form.email.trim(),
      password: form.password,
      role: form.role,
    })

    await navigateTo('/admin/users')
  } catch (error: any) {
    message.value = error?.data?.message || error?.data?.errors?.email?.[0] || tl('Could not create user')
  } finally {
    submitting.value = false
  }
}
</script>

<style scoped>
.admin-create-form {
  display: grid;
  gap: 20px;
}

.admin-create-form__field {
  display: grid;
  gap: 8px;
  font-weight: 700;
}

.admin-create-form__field input {
  min-height: 42px;
  padding: 0 14px;
  border: 1px solid var(--color-input-border);
  border-radius: 12px;
  color: var(--color-input-text);
  background: var(--color-input-bg);
  font: inherit;
}

.admin-role-picker {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.admin-role-picker__option {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 38px;
  padding: 0 14px;
  border: 1px solid var(--button-control-border);
  border-radius: 999px;
  color: var(--button-control-text);
  background: var(--button-control-bg);
  font: inherit;
  font-weight: 800;
  cursor: pointer;
  text-transform: capitalize;
}

.admin-role-picker__option--active {
  border-color: var(--button-primary-border);
  color: var(--button-primary-text);
  background: var(--button-primary-bg);
}

.admin-create-form__footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}

.admin-create-form__message {
  color: var(--color-error-text);
  font-weight: 700;
}

.admin-create-form__save {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 38px;
  padding: 0 14px;
  border: 1px solid var(--button-primary-border);
  border-radius: 999px;
  color: var(--button-primary-text);
  background: var(--button-primary-bg);
  font: inherit;
  font-weight: 800;
  font-size: 0.84rem;
}

@media (max-width: 900px) {
  .admin-create-form__footer {
    flex-direction: column;
    align-items: stretch;
  }

  .admin-role-picker {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}
</style>
