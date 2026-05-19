<template>
  <AdminRecordTemplate
    :eyebrow="entityLabel"
    :title="recordTitle"
    description="Edit record fields available to the admin panel."
    :back-to="`/admin/${entityKey}`"
    :back-label="backLabel"
    :public-url="publicUrl"
    :loading="loading"
    loading-label="Loading record..."
    :error-message="errorMessage"
  >
    <form class="admin-record-form" @submit.prevent="saveRecord">
      <div class="admin-record-form__grid">
        <label
          v-for="field in editableFields"
          :key="field.key"
          class="admin-record-form__field"
        >
          <span>{{ tl(field.label) }}</span>

          <input
            v-if="field.type === 'text'"
            v-model="form[field.key]"
            type="text"
          >

          <input
            v-else-if="field.type === 'number'"
            v-model="form[field.key]"
            type="number"
          >

          <div
            v-else-if="field.type === 'select'"
            class="admin-record-form__select"
          >
            <button
              type="button"
              class="admin-record-form__select-trigger"
              @click="toggleSelect(field.key)"
            >
              <span>{{ selectedOptionLabel(field) }}</span>
              <span class="admin-record-form__select-caret" :class="{ 'admin-record-form__select-caret--open': openSelectKey === field.key }" />
            </button>

            <div
              v-if="openSelectKey === field.key"
              class="admin-record-form__select-menu"
            >
              <button
                v-if="field.nullable"
                type="button"
                class="admin-record-form__select-option"
                :class="{ 'admin-record-form__select-option--active': String(form[field.key] ?? '') === '' }"
                @click="selectOption(field.key, '')"
              >
                —
              </button>
              <button
                v-for="option in field.options || []"
                :key="`${field.key}-${option.value}`"
                type="button"
                class="admin-record-form__select-option"
                :class="{ 'admin-record-form__select-option--active': String(form[field.key] ?? '') === String(option.value) }"
                @click="selectOption(field.key, String(option.value))"
              >
                {{ tl(option.label) }}
              </button>
            </div>
          </div>

          <label
            v-else-if="field.type === 'boolean'"
            class="admin-record-form__checkbox"
          >
            <input
              v-model="form[field.key]"
              type="checkbox"
            >
            <span>{{ tl('Enabled') }}</span>
          </label>

          <input
            v-else
            v-model="form[field.key]"
            type="text"
          >
        </label>
      </div>

      <div class="admin-record-form__footer">
        <span v-if="successMessage" class="admin-record-form__success">{{ successMessage }}</span>
        <div class="admin-record-form__footer-actions">
          <button
            type="submit"
            class="admin-record-form__save"
            :disabled="saving"
          >
            {{ saving ? tl('Saving...') : tl('Save changes') }}
          </button>
          <button
            type="button"
            class="admin-record-form__delete"
            :disabled="deleting"
            @click="deleteRecord"
          >
            {{ deleting ? tl('Deleting...') : tl('Delete record') }}
          </button>
        </div>
      </div>
    </form>
  </AdminRecordTemplate>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import type { AdminEntity, AdminFieldMeta } from '~/composables/useAdmin'

definePageMeta({
  layout: 'admin',
})

const { tl } = useLocalizedText()
const route = useRoute()
const { isAdminEntity, getEntityLabel, getAdminEntityRecord, updateAdminEntityRecord, deleteAdminEntityRecord } = useAdmin()

const loading = ref(false)
const saving = ref(false)
const deleting = ref(false)
const errorMessage = ref('')
const successMessage = ref('')
const fields = ref<AdminFieldMeta[]>([])
const form = ref<Record<string, any>>({})
const publicUrl = ref<string | null>(null)
const recordTitle = ref('Record')
const openSelectKey = ref<string | null>(null)

const entityKey = computed(() => String(route.params.entity || ''))
const entityId = computed(() => String(route.params.id || ''))
const entity = computed(() => entityKey.value as AdminEntity)
const isValidEntity = computed(() => isAdminEntity(entityKey.value))
const entityLabel = computed(() => isValidEntity.value ? getEntityLabel(entity.value) : 'Admin')
const backLabel = computed(() => tl('Back to {entity}', {
  entity: tl(entityLabel.value).toLowerCase(),
}))
const editableFields = computed(() => fields.value.filter((field) => field.editable))

const selectedOptionLabel = (field: AdminFieldMeta) => {
  const currentValue = String(form.value[field.key] ?? '')

  if (!field.options?.length) {
    return currentValue || '—'
  }

  const option = field.options.find((item) => String(item.value) === currentValue)
  return option?.label || (field.nullable ? '—' : tl('Select an option'))
}

const toggleSelect = (fieldKey: string) => {
  openSelectKey.value = openSelectKey.value === fieldKey ? null : fieldKey
}

const selectOption = (fieldKey: string, value: string) => {
  form.value[fieldKey] = value
  openSelectKey.value = null
}

const closeSelectOnOutside = (event: MouseEvent) => {
  const target = event.target as HTMLElement | null
  if (!target?.closest('.admin-record-form__select')) {
    openSelectKey.value = null
  }
}

const loadRecord = async () => {
  if (!isValidEntity.value || !entityId.value) {
    errorMessage.value = tl('Unsupported entity')
    return
  }

  loading.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const response = await getAdminEntityRecord(entity.value, entityId.value)
    fields.value = response.fields
    recordTitle.value = response.title
    publicUrl.value = response.public_url || null

    const nextForm: Record<string, any> = {}

    response.fields.forEach((field) => {
      const value = response.record[field.key]
      nextForm[field.key] = field.type === 'boolean' ? !!value : (value ?? '')
    })

    form.value = nextForm
  } catch (error: any) {
    errorMessage.value = error?.data?.message || tl('Could not load record')
  } finally {
    loading.value = false
  }
}

const saveRecord = async () => {
  if (!isValidEntity.value) {
    return
  }

  saving.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const payload: Record<string, any> = {}

    editableFields.value.forEach((field) => {
      payload[field.key] = form.value[field.key]
    })

    const response = await updateAdminEntityRecord(entity.value, entityId.value, payload)
    successMessage.value = tl('Changes saved')
    publicUrl.value = response.public_url || null
    recordTitle.value = response.title
    await loadRecord()
  } catch (error: any) {
    errorMessage.value = error?.data?.message || tl('Could not save record')
  } finally {
    saving.value = false
  }
}

const deleteRecord = async () => {
  if (!isValidEntity.value) {
    return
  }

  const label = recordTitle.value || entityId.value

  if (!window.confirm(tl('Delete "{label}" from the database?', { label: String(label) }))) {
    return
  }

  deleting.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    await deleteAdminEntityRecord(entity.value, entityId.value)
    await navigateTo(`/admin/${entityKey.value}`)
  } catch (error: any) {
    errorMessage.value = error?.data?.message || tl('Could not delete record')
  } finally {
    deleting.value = false
  }
}

watch(
  () => [route.params.entity, route.params.id],
  () => {
    void loadRecord()
  },
  { immediate: true }
)

onMounted(() => {
  if (typeof document !== 'undefined') {
    document.addEventListener('mousedown', closeSelectOnOutside)
  }
})

onBeforeUnmount(() => {
  if (typeof document !== 'undefined') {
    document.removeEventListener('mousedown', closeSelectOnOutside)
  }
})
</script>

<style scoped>
.admin-record-form {
  display: grid;
  gap: 20px;
}

.admin-record-form__grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
}

.admin-record-form__field {
  display: grid;
  gap: 8px;
  font-weight: 700;
  position: relative;
}

.admin-record-form__field input {
  min-height: 42px;
  padding: 0 14px;
  border: 1px solid var(--color-input-border);
  border-radius: 12px;
  color: var(--color-input-text);
  background: var(--color-input-bg);
  font: inherit;
}

.admin-record-form__field input:focus {
  outline: none;
  border-color: var(--color-primary);
  box-shadow: 0 0 0 3px rgba(0, 163, 255, 0.14);
}

.admin-record-form__select {
  position: relative;
}

.admin-record-form__select-trigger {
  width: 100%;
  min-height: 42px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 0 14px;
  border: 1px solid var(--color-input-border);
  border-radius: 12px;
  color: var(--color-input-text);
  background: var(--color-input-bg);
  font: inherit;
  text-align: left;
  cursor: pointer;
}

.admin-record-form__select-trigger:focus {
  outline: none;
  border-color: var(--color-primary);
  box-shadow: 0 0 0 3px rgba(0, 163, 255, 0.14);
}

.admin-record-form__select-caret {
  width: 8px;
  height: 8px;
  border-right: 2px solid var(--color-text-muted);
  border-bottom: 2px solid var(--color-text-muted);
  transform: rotate(45deg);
  transition: transform 0.16s ease;
}

.admin-record-form__select-caret--open {
  transform: rotate(225deg) translate(-1px, -1px);
}

.admin-record-form__select-menu {
  position: absolute;
  top: calc(100% + 6px);
  left: 0;
  right: 0;
  max-height: 240px;
  overflow-y: auto;
  z-index: 8;
  padding: 6px;
  border: 1px solid var(--color-card-border);
  border-radius: 14px;
  background: var(--color-card-surface);
  box-shadow: var(--shadow-menu);
}

.admin-record-form__select-option {
  width: 100%;
  min-height: 38px;
  display: flex;
  align-items: center;
  padding: 0 12px;
  border: 0;
  border-radius: 10px;
  color: var(--color-text-main);
  background: transparent;
  font: inherit;
  text-align: left;
  cursor: pointer;
}

.admin-record-form__select-option:hover,
.admin-record-form__select-option--active {
  background: var(--color-card-surface-hover);
}

.admin-record-form__checkbox {
  min-height: 42px;
  display: inline-flex;
  align-items: center;
  gap: 10px;
  padding: 0 14px;
  border: 1px solid var(--color-input-border);
  border-radius: 12px;
  background: var(--color-input-bg);
  font-weight: 600;
}

.admin-record-form__footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}

.admin-record-form__footer-actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 10px;
}

.admin-record-form__success {
  color: var(--color-success-text, #2f9e63);
  font-weight: 700;
}

.admin-record-form__save {
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
  cursor: pointer;
}

.admin-record-form__delete {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 38px;
  padding: 0 14px;
  border: 1px solid var(--button-danger-border);
  border-radius: 999px;
  color: var(--button-danger-text);
  background: var(--button-danger-bg);
  font: inherit;
  font-weight: 800;
  font-size: 0.84rem;
  cursor: pointer;
}

@media (max-width: 900px) {
  .admin-record-form__footer {
    flex-direction: column;
    align-items: stretch;
  }

  .admin-record-form__footer-actions {
    justify-content: stretch;
    flex-direction: column;
  }

  .admin-record-form__grid {
    grid-template-columns: 1fr;
  }
}
</style>
