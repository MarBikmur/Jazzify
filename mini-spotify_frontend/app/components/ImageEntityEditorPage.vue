<template>
  <EditorPageTemplate
    :title="title"
    :submitLabel="submitLabel"
    :loadingLabel="loadingLabel"
    :loading="loading"
    :submitButtonStyle="submitButtonStyle"
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

    <slot name="fields" />

    <section v-if="showCurrentImage && currentImageUrl" class="current-image">
      <span class="current-image__label">{{ tl(currentImageLabel) }}</span>
      <img
        :src="currentImageUrl"
        :alt="currentImageAlt || `${tl(fieldLabel)} image`"
        class="current-image__preview"
        :class="`current-image__preview--${imageShape}`"
      />
    </section>

    <ImageCropUpload
      ref="imageCropUpload"
      :label="imageLabel"
      :item-extension="imageExtension"
      :help-text="imageHelpText"
      :max-size="maxSize"
      :shape="imageShape"
      :initial-image-url="initialImageUrl"
      @file-selected="$emit('image-selected', $event)"
      @file-error="$emit('image-error', $event)"
      @file-cleared="$emit('image-cleared')"
    />

    <FormNotice v-if="errorMessage" variant="error" :message="errorMessage" />

    <template #footer>
      <slot name="footer" />
    </template>
  </EditorPageTemplate>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watchEffect } from 'vue'
import { useEditorLayout } from '~/composables/useEditorLayout'
const { tl } = useLocalizedText()

const props = withDefaults(defineProps<{
  title: string
  submitLabel: string
  loadingLabel?: string
  loading?: boolean
  submitButtonStyle?: string
  fieldLabel: string
  fieldName?: string
  modelValue: string
  imageLabel: string
  imageHelpText?: string
  imageExtension?: string
  imageShape?: 'circle' | 'square'
  maxSize?: number
  errorMessage?: string
  panelEyebrow?: string
  panelDescription?: string
  panelTips?: string[]
  panelImageUrl?: string
  panelImageFallback?: string
  contentWidth?: string
  initialImageUrl?: string
  currentImageUrl?: string
  currentImageAlt?: string
  currentImageLabel?: string
  showCurrentImage?: boolean
}>(), {
  loadingLabel: 'Saving...',
  loading: false,
  submitButtonStyle: '',
  fieldName: 'title',
  imageHelpText: '',
  imageExtension: 'image/jpeg,image/png',
  imageShape: 'square',
  maxSize: 10 * 1024 * 1024,
  errorMessage: '',
  panelEyebrow: 'Editor',
  panelDescription: '',
  panelTips: () => [],
  panelImageUrl: '',
  panelImageFallback: '',
  contentWidth: '780px',
  initialImageUrl: '',
  currentImageUrl: '',
  currentImageAlt: '',
  currentImageLabel: 'Current image',
  showCurrentImage: false,
})

defineEmits<{
  submit: []
  'update:modelValue': [value: string]
  'image-selected': [file: File]
  'image-cleared': []
  'image-error': [error: unknown]
}>()

const imageCropUpload = ref<{ reset?: () => void; ensureSaved?: () => Promise<File | null> } | null>(null)
const { setEditorPanel, clearEditorPanel } = useEditorLayout()
const panelTips = computed(() => props.panelTips.length ? props.panelTips : [
  `Set ${props.fieldLabel.toLowerCase()}`,
  `Prepare ${props.imageLabel.toLowerCase()}`,
  'Review the preview before saving',
])

watchEffect(() => {
  const currentValue = props.modelValue.trim()

  setEditorPanel({
    eyebrow: props.panelEyebrow,
    title: currentValue || props.title,
    description: props.panelDescription || 'Use the form and artwork preview to shape this page before publishing.',
    imageUrl: props.panelImageUrl || '',
    imageShape: props.imageShape,
    imageFallback: props.panelImageFallback || currentValue.slice(0, 1).toUpperCase() || 'E',
    stats: [
      { label: tl('Title'), value: currentValue || tl('Untitled') },
      { label: tl('Artwork'), value: props.panelImageUrl ? tl('Ready') : tl('Pending') },
    ],
    sections: [
      {
        title: tl('Checklist'),
        items: panelTips.value.map((item) => tl(item)),
      },
    ],
  })
})

onBeforeUnmount(() => {
  clearEditorPanel()
})

defineExpose({
  resetImage: () => imageCropUpload.value?.reset?.(),
  ensureImageSaved: () => imageCropUpload.value?.ensureSaved?.() || Promise.resolve(null),
})
</script>

<style scoped>
.current-image {
  display: grid;
  gap: 10px;
  width: 100%;
  justify-items: center;
  text-align: center;
}

.current-image__label {
  color: var(--color-text-main);
  font-size: 0.95rem;
  font-weight: 700;
}

.current-image__preview {
  width: min(180px, 100%);
  aspect-ratio: 1;
  display: block;
  object-fit: cover;
  border-radius: 12px;
  border: 1px solid var(--color-border);
  box-shadow: var(--shadow-card);
  background: var(--color-surface);
}

.current-image__preview--circle {
  border-radius: 50%;
}

.current-image__preview--square {
  border-radius: 12px;
}
</style>
