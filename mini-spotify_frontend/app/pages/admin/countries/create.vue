<template>
  <AdminRecordTemplate
    eyebrow="Countries"
    title="Create country"
    description="Add a country entry for artist metadata."
    back-to="/admin/countries"
    back-label="Back to countries"
  >
    <form class="admin-create-form" @submit.prevent="submitCountry">
      <label class="admin-create-form__field">
        <span>{{ tl('Name') }}</span>
        <input v-model="name" type="text" maxlength="30" :placeholder="tl('Country name')">
      </label>

      <div class="admin-create-form__footer">
        <span v-if="message" class="admin-create-form__message">{{ message }}</span>
        <button type="submit" class="admin-create-form__save" :disabled="submitting">
          {{ submitting ? tl('Creating...') : tl('Create country') }}
        </button>
      </div>
    </form>
  </AdminRecordTemplate>
</template>

<script setup lang="ts">
import { ref } from 'vue'

definePageMeta({
  layout: 'admin',
})

const { tl } = useLocalizedText()
const { createCountry } = useCountry()

const name = ref('')
const submitting = ref(false)
const message = ref('')

const submitCountry = async () => {
  submitting.value = true
  message.value = ''

  try {
    const response = await createCountry({ name: name.value.trim() })

    if (!response.success) {
      message.value = response.message || tl('Could not create country')
      return
    }

    await navigateTo('/admin/countries')
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
}
</style>
