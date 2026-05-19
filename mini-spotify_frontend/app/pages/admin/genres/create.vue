<template>
  <AdminRecordTemplate
    eyebrow="Genres"
    title="Create genre"
    description="Add a reusable genre for albums and songs."
    back-to="/admin/genres"
    back-label="Back to genres"
  >
    <form class="admin-create-form" @submit.prevent="submitGenre">
      <label class="admin-create-form__field">
        <span>{{ tl('Name') }}</span>
        <input v-model="name" type="text" maxlength="255" :placeholder="tl('Genre name')">
      </label>

      <div class="admin-create-form__footer">
        <span v-if="message" class="admin-create-form__message">{{ message }}</span>
        <button type="submit" class="admin-create-form__save" :disabled="submitting">
          {{ submitting ? tl('Creating...') : tl('Create genre') }}
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
const { createGenre } = useGenre()

const name = ref('')
const submitting = ref(false)
const message = ref('')

const submitGenre = async () => {
  submitting.value = true
  message.value = ''

  try {
    const response = await createGenre({ name: name.value.trim() })

    if (!response.success) {
      message.value = response.message || tl('Could not create genre')
      return
    }

    await navigateTo('/admin/genres')
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
