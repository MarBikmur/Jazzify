<template>
  <EditorPageTemplate
    :title="title"
    :submitLabel="submitLabel"
    :loadingLabel="loadingLabel"
    :loading="loading"
    :contentWidth="contentWidth"
    @submit="$emit('submit')"
  >
    <UserDataFields
      :label="fieldLabel"
      type="text"
      :name="fieldName"
      :show-validation-errors="false"
      input-credentials="inputCredentials"
      :model-value="modelValue"
      :v$="null"
      @update:modelValue="$emit('update:modelValue', $event)"
    />

    <FormNotice v-if="errorMessage" variant="error" :message="errorMessage" />

    <template #footer>
      <CancelButton
        :disabled="loading"
        :label="cancelLabel"
        @click="$emit('reset')"
      />
    </template>
  </EditorPageTemplate>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, watchEffect } from 'vue'
import { useEditorLayout } from '~/composables/useEditorLayout'
const { tl } = useLocalizedText()

const props = withDefaults(defineProps<{
  title: string
  submitLabel: string
  loadingLabel?: string
  fieldLabel: string
  fieldName?: string
  modelValue: string
  loading?: boolean
  cancelLabel?: string
  errorMessage?: string
  panelEyebrow?: string
  panelDescription?: string
  panelTips?: string[]
  contentWidth?: string
}>(), {
  loadingLabel: 'Saving...',
  fieldName: 'name',
  loading: false,
  cancelLabel: 'Clear',
  errorMessage: '',
  panelEyebrow: 'Editor',
  panelDescription: '',
  panelTips: () => [],
  contentWidth: '620px',
})

defineEmits<{
  submit: []
  reset: []
  'update:modelValue': [value: string]
}>()

const { setEditorPanel, clearEditorPanel } = useEditorLayout()

const defaultTips = computed(() => props.panelTips.length ? props.panelTips : [
  `Fill ${props.fieldLabel.toLowerCase()}`,
  'Review the value before saving',
  'Use clear and concise naming',
])

watchEffect(() => {
  const currentValue = props.modelValue.trim()

  setEditorPanel({
    eyebrow: props.panelEyebrow,
    title: currentValue || props.title,
    description: props.panelDescription || 'This editor keeps the form focused on a single change.',
    imageShape: 'square',
    imageFallback: currentValue.slice(0, 1).toUpperCase() || 'E',
    stats: [
      { label: tl('Field'), value: tl(props.fieldLabel) },
      { label: tl('Status'), value: currentValue ? tl('Ready') : tl('Waiting') },
    ],
    sections: [
      {
        title: tl('Checklist'),
        items: defaultTips.value.map((item) => tl(item)),
      },
    ],
  })
})

onBeforeUnmount(() => {
  clearEditorPanel()
})
</script>
