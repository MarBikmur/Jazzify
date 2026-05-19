<template>
  <div
    class="file-drop-container image-crop-upload"
    :class="{ 'file-drop-container--dragover': isDragOver }"
    @drop="handleDrop"
    @dragover="handleDragOver"
    @dragleave="handleDragLeave"
  >
    <span class="file-drop-container__label">{{ label }}</span>

    <label v-if="!imageUrl && !previewUrl" :for="inputId" class="file-drop-container__upload-area">
      <input
        :id="inputId"
        ref="fileInput"
        type="file"
        :accept="itemExtension"
        class="file-drop-container__input"
        @change="handleFileUpload"
      />
      <span class="file-drop-container__placeholder">{{ tl('Select the file or drag it in') }}</span>
    </label>

    <div v-else-if="imageUrl" class="cropper">
      <div
        ref="cropArea"
        class="cropper__area"
        @pointerdown="startDrag"
        @pointermove="onDrag"
        @pointerup="stopDrag"
        @pointercancel="stopDrag"
        @pointerleave="stopDrag"
        @wheel.prevent="onWheelZoom"
      >
        <img
          v-if="imageUrl"
          ref="imageElement"
          :src="imageUrl"
          :alt="tl('Crop preview')"
          class="cropper__image"
          :style="imageStyle"
          draggable="false"
          @load="onImageLoad"
        />
        <div class="cropper__frame" :class="`cropper__frame--${shape}`" />
      </div>

      <div class="cropper__actions">
        <button class="cropper__button cropper__button--primary" type="button" @click="saveCrop">
          {{ tl('Save') }}
        </button>
        <label :for="inputId" class="cropper__button">{{ tl('Choose another') }}</label>
        <button class="cropper__button" type="button" @click="reset">{{ tl('Remove') }}</button>
      </div>
    </div>

    <div v-else-if="previewUrl || initialImageUrl" class="saved-preview">
      <img
        :src="previewUrl || initialImageUrl"
        :alt="tl('Selected image preview')"
        class="saved-preview__image"
        :class="`saved-preview__image--${shape}`"
      />
      <div class="saved-preview__actions">
        <label :for="inputId" class="cropper__button">{{ tl('Choose another') }}</label>
        <button class="cropper__button" type="button" @click="reset">{{ tl('Remove') }}</button>
      </div>
    </div>

    <input
      v-if="imageUrl || previewUrl"
      :id="inputId"
      ref="fileInput"
      type="file"
      :accept="itemExtension"
      class="file-drop-container__input"
      @change="handleFileUpload"
    />

    <p v-if="error" class="file-drop-container__error">{{ error }}</p>
    <p class="file-drop-container__help">{{ helpText }}</p>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, useId, watch } from 'vue'
const { tl } = useLocalizedText()

interface Props {
  label?: string
  itemExtension?: string
  helpText?: string
  maxSize?: number
  outputSize?: number
  shape?: 'circle' | 'square'
  initialImageUrl?: string
}

interface Emits {
  (event: 'file-selected', file: File): void
  (event: 'file-error', error: string): void
  (event: 'file-cleared'): void
}

const props = withDefaults(defineProps<Props>(), {
  label: 'Upload file',
  itemExtension: 'image/*',
  helpText: '',
  maxSize: 5 * 1024 * 1024,
  outputSize: 512,
  shape: 'circle',
  initialImageUrl: '',
})

const emit = defineEmits<Emits>()

const cropSize = 320
const fileInput = ref<HTMLInputElement | null>(null)
const imageElement = ref<HTMLImageElement | null>(null)
const cropArea = ref<HTMLElement | null>(null)
const imageUrl = ref('')
const previewUrl = ref('')
const sourceFileName = ref('artist-image')
const error = ref('')
const isDragOver = ref(false)
const isDragging = ref(false)
const zoom = ref(1)
const offset = ref({ x: 0, y: 0 })
const dragStart = ref({ pointerX: 0, pointerY: 0, imageX: 0, imageY: 0 })
const naturalSize = ref({ width: 0, height: 0 })
let pendingExportResolver: ((file: File | null) => void) | null = null

const inputId = useId()

const baseScale = computed(() => {
  if (!naturalSize.value.width || !naturalSize.value.height) {
    return 1
  }

  return Math.max(cropSize / naturalSize.value.width, cropSize / naturalSize.value.height)
})

const renderSize = computed(() => ({
  width: naturalSize.value.width * baseScale.value * zoom.value,
  height: naturalSize.value.height * baseScale.value * zoom.value,
}))

const imageStyle = computed(() => ({
  width: `${renderSize.value.width}px`,
  height: `${renderSize.value.height}px`,
  transform: `translate(calc(-50% + ${offset.value.x}px), calc(-50% + ${offset.value.y}px))`,
}))

watch([zoom, offset], () => {
  clampOffset()
}, { deep: true })

const handleFileUpload = (event: Event) => {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0] ?? null

  if (file) {
    processFile(file)
  }
}

const handleDrop = (event: DragEvent) => {
  event.preventDefault()
  isDragOver.value = false

  const file = event.dataTransfer?.files?.[0]
  if (file) {
    processFile(file)
  }
}

const handleDragOver = (event: DragEvent) => {
  event.preventDefault()
  isDragOver.value = true
}

const handleDragLeave = () => {
  isDragOver.value = false
}

const processFile = (file: File) => {
  error.value = ''

  if (!isFileTypeValid(file, props.itemExtension)) {
    showError(`${tl('Invalid file type. Allowed:')} ${props.itemExtension}`)
    return
  }

  if (file.size > props.maxSize) {
    showError(`${tl('File too large. Maximum size:')} ${props.maxSize / 1024 / 1024}MB`)
    return
  }

  if (imageUrl.value) {
    URL.revokeObjectURL(imageUrl.value)
  }
  if (previewUrl.value) {
    URL.revokeObjectURL(previewUrl.value)
  }

  sourceFileName.value = file.name.replace(/\.[^.]+$/, '') || 'artist-image'
  imageUrl.value = URL.createObjectURL(file)
  previewUrl.value = ''
  zoom.value = 1
  offset.value = { x: 0, y: 0 }

  if (fileInput.value) {
    fileInput.value.value = ''
  }
}

const isFileTypeValid = (file: File, acceptedTypes: string) => {
  if (acceptedTypes === '*/*') {
    return true
  }

  return acceptedTypes.split(',').map((type) => type.trim()).some((type) => {
    if (type.endsWith('/*')) {
      return file.type.startsWith(type.replace('/*', '/'))
    }

    return file.type === type || file.name.toLowerCase().endsWith(type.replace('*.', '.'))
  })
}

const showError = (message: string) => {
  error.value = message
  emit('file-error', message)
}

const onImageLoad = async () => {
  await nextTick()

  if (!imageElement.value) {
    return
  }

  naturalSize.value = {
    width: imageElement.value.naturalWidth,
    height: imageElement.value.naturalHeight,
  }
  offset.value = { x: 0, y: 0 }
}

const startDrag = (event: PointerEvent) => {
  if (!imageUrl.value) {
    return
  }

  isDragging.value = true
  cropArea.value?.setPointerCapture(event.pointerId)
  dragStart.value = {
    pointerX: event.clientX,
    pointerY: event.clientY,
    imageX: offset.value.x,
    imageY: offset.value.y,
  }
}

const onDrag = (event: PointerEvent) => {
  if (!isDragging.value) {
    return
  }

  offset.value = {
    x: dragStart.value.imageX + event.clientX - dragStart.value.pointerX,
    y: dragStart.value.imageY + event.clientY - dragStart.value.pointerY,
  }
}

const stopDrag = (event: PointerEvent) => {
  if (!isDragging.value) {
    return
  }

  isDragging.value = false
  if (cropArea.value?.hasPointerCapture(event.pointerId)) {
    cropArea.value.releasePointerCapture(event.pointerId)
  }
}

const onWheelZoom = (event: WheelEvent) => {
  if (!imageUrl.value) {
    return
  }

  const nextZoom = zoom.value + (event.deltaY > 0 ? -0.08 : 0.08)
  zoom.value = Math.min(3, Math.max(1, nextZoom))
}

const clampOffset = () => {
  const maxX = Math.max(0, (renderSize.value.width - cropSize) / 2)
  const maxY = Math.max(0, (renderSize.value.height - cropSize) / 2)
  const nextX = Math.min(maxX, Math.max(-maxX, offset.value.x))
  const nextY = Math.min(maxY, Math.max(-maxY, offset.value.y))

  if (nextX !== offset.value.x || nextY !== offset.value.y) {
    offset.value = { x: nextX, y: nextY }
  }
}

const saveCrop = () => {
  exportCroppedImage()
}

const exportCroppedImage = () => {
  if (!imageElement.value || !naturalSize.value.width || !naturalSize.value.height) {
    pendingExportResolver?.(null)
    pendingExportResolver = null
    return
  }

  const scale = baseScale.value * zoom.value
  const renderedLeft = cropSize / 2 - renderSize.value.width / 2 + offset.value.x
  const renderedTop = cropSize / 2 - renderSize.value.height / 2 + offset.value.y
  const sourceX = Math.max(0, -renderedLeft / scale)
  const sourceY = Math.max(0, -renderedTop / scale)
  const sourceSize = Math.min(cropSize / scale, naturalSize.value.width - sourceX, naturalSize.value.height - sourceY)
  const canvas = document.createElement('canvas')
  canvas.width = props.outputSize
  canvas.height = props.outputSize

  const context = canvas.getContext('2d')
  if (!context) {
    showError(tl('Could not process image'))
    pendingExportResolver?.(null)
    pendingExportResolver = null
    return
  }

  context.drawImage(
    imageElement.value,
    sourceX,
    sourceY,
    sourceSize,
    sourceSize,
    0,
    0,
    props.outputSize,
    props.outputSize,
  )

  canvas.toBlob((blob) => {
    if (!blob) {
      showError(tl('Could not process image'))
      pendingExportResolver?.(null)
      pendingExportResolver = null
      return
    }

    const file = new File([blob], `${sourceFileName.value}-cropped.jpg`, { type: 'image/jpeg' })

    if (previewUrl.value) {
      URL.revokeObjectURL(previewUrl.value)
    }
    previewUrl.value = URL.createObjectURL(file)

    if (imageUrl.value) {
      URL.revokeObjectURL(imageUrl.value)
      imageUrl.value = ''
    }

    emit('file-selected', file)
    pendingExportResolver?.(file)
    pendingExportResolver = null
  }, 'image/jpeg', 0.92)
}

const ensureSaved = () => {
  if (!imageUrl.value) {
    return Promise.resolve<File | null>(null)
  }

  return new Promise<File | null>((resolve) => {
    pendingExportResolver = resolve
    exportCroppedImage()
  })
}

const reset = () => {
  if (imageUrl.value) {
    URL.revokeObjectURL(imageUrl.value)
  }
  if (previewUrl.value) {
    URL.revokeObjectURL(previewUrl.value)
  }

  imageUrl.value = ''
  previewUrl.value = ''
  error.value = ''
  zoom.value = 1
  offset.value = { x: 0, y: 0 }
  naturalSize.value = { width: 0, height: 0 }

  if (fileInput.value) {
    fileInput.value.value = ''
  }

  emit('file-cleared')
}

onBeforeUnmount(() => {
  if (imageUrl.value) {
    URL.revokeObjectURL(imageUrl.value)
  }
  if (previewUrl.value) {
    URL.revokeObjectURL(previewUrl.value)
  }
})

defineExpose({
  reset,
  ensureSaved,
})
</script>

<style scoped>
.image-crop-upload {
  border: 2px dashed var(--color-border);
  border-radius: 16px;
  padding: 20px;
  text-align: center;
  transition: all 0.3s ease;
  position: relative;
  background: var(--color-bg-soft);
}

.file-drop-container--dragover {
  border-color: var(--color-primary);
  background-color: rgba(0, 106, 202, 0.12);
}

.file-drop-container__label {
  display: block;
  margin-bottom: 10px;
  font-weight: 600;
  color: var(--color-text-main);
  font-size: 1.1em;
}

.file-drop-container__input {
  display: none;
}

.file-drop-container__upload-area {
  display: block;
  cursor: pointer;
  padding: 30px 20px;
  border-radius: 12px;
  transition: all 0.3s ease;
  background-color: var(--color-surface);
  border: 1px solid var(--color-border);
}

.file-drop-container__upload-area:hover {
  background-color: var(--color-surface-hover);
  border-color: var(--color-primary);
}

.file-drop-container__placeholder {
  color: var(--color-text-muted);
  font-size: 1em;
  display: block;
}

.file-drop-container__help {
  margin: 10px 0 0;
  color: var(--color-text-soft);
  font-size: 0.9em;
}

.file-drop-container__error {
  margin: 10px 0 0;
  color: var(--color-error);
  font-size: 0.9em;
  font-weight: 500;
}

.cropper {
  display: grid;
  gap: 14px;
  justify-items: center;
}

.cropper__area {
  width: min(320px, 100%);
  aspect-ratio: 1;
  position: relative;
  overflow: hidden;
  touch-action: none;
  cursor: grab;
  background: var(--color-bg-soft);
  border-radius: 14px;
  border: 1px solid var(--color-border);
}

.cropper__area:active {
  cursor: grabbing;
}

.cropper__image {
  position: absolute;
  left: 50%;
  top: 50%;
  max-width: none;
  user-select: none;
  pointer-events: none;
}

.cropper__frame {
  position: absolute;
  inset: 0;
  box-shadow: inset 0 0 0 2px rgba(255, 255, 255, 0.92), 0 0 0 999px var(--color-overlay-strong);
  pointer-events: none;
}

.cropper__frame--circle {
  border-radius: 50%;
}

.cropper__frame--square {
  border-radius: 8px;
}

.cropper__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  justify-content: center;
}

.cropper__button {
  min-height: 34px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0 14px;
  border: 1px solid var(--button-secondary-border);
  border-radius: 999px;
  color: var(--button-secondary-text);
  background: var(--button-secondary-bg);
  font: inherit;
  font-weight: 700;
  cursor: pointer;
}

.cropper__button:hover {
  background: var(--button-secondary-hover);
}

.cropper__button--primary {
  color: var(--button-primary-text);
  background: var(--button-primary-bg);
  border-color: var(--button-primary-border);
  box-shadow: var(--shadow-primary);
}

.cropper__button--primary:hover {
  background: var(--button-primary-hover);
}

.saved-preview {
  display: grid;
  gap: 14px;
  justify-items: center;
}

.saved-preview__image {
  width: 180px;
  height: 180px;
  display: block;
  object-fit: cover;
  border: 2px solid var(--color-primary);
  background: var(--color-surface);
}

.saved-preview__image--circle {
  border-radius: 50%;
}

.saved-preview__image--square {
  border-radius: 8px;
}

.saved-preview__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  justify-content: center;
}
</style>
