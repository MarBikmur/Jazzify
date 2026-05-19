<template>
    <div 
        class="file-drop-container"
        :class="{ 
            'file-drop-container--has-file': selectedFile, 
            'file-drop-container--dragover': isDragOver 
        }"
        @drop="handleDrop"
        @dragover="handleDragOver"
        @dragleave="handleDragLeave"
    >
        <span class="file-drop-container__label">{{ label }}</span>
        <label :for="inputId" class="file-drop-container__upload-area">
            <input 
                :id="inputId"
                ref="fileInput"
                type="file"
                :accept="itemExtension"
                class="file-drop-container__input"
                @change="handleFileUpload"
            />
            <span class="file-drop-container__placeholder">
                {{ selectedFile ? selectedFile.name : 'Select the file or drag it in' }}
            </span>
        </label>

        <p v-if="error" class="file-drop-container__error">{{ error }}</p>
        <p class="file-drop-container__help">{{ helpText }}</p>

        <div v-if="selectedFile && isImage" class="file-drop-container__preview">
            <img 
                :src="previewUrl" 
                alt="Preview" 
                class="file-drop-container__preview-image"
            />
        </div>
    </div>
</template>


<script setup lang="ts">
interface Props {
    label?: string
    itemExtension?: string
    helpText?: string
    maxSize?: number
}

interface Emits {
    (event: 'file-selected', file: File): void
    (event: 'file-error', error: string): void
}

const props = withDefaults(defineProps<Props>(), {
    label: 'Upload file',
    itemExtension: 'image*' && !isFileTypeValid(file, props.itemExtension)) {
        error.value = `Invalid file type. Allowed: ${props.itemExtension}`
        emit('file-error', error.value)
        return
    }

    if (file.size > props.maxSize) {
        const maxSizeMB = props.maxSize / 1024 / 1024
        error.value = `File too large. Maximum size: ${maxSizeMB}MB`
        emit('file-error', error.value)
        return
    }

    selectedFile.value = file
    emit('file-selected', file)

    if (isImage.value) {
        const reader = new FileReader()
        reader.onload = (e: ProgressEvent<FileReader>) => {
            previewUrl.value = e.target?.result as string
        }
        reader.readAsDataURL(file)
    }
}

const isFileTypeValid = (file: File, acceptedTypes: string): boolean => {
    if (acceptedTypes === '*/*') return true
    const acceptedExtensions = acceptedTypes.split(',').map(ext => ext.trim())
    return acceptedExtensions.some(ext => {
        if (ext.includes('/*')) {
            const category = ext.replace('/*', '')
            return file.type.startsWith(`${category}/`)
        } else {
            return file.type === ext || file.name.toLowerCase().endsWith(ext.replace('*.', '.'))
        }
    })
}
const reset = (): void => {
    selectedFile.value = null
    previewUrl.value = ''
    error.value = ''
    if (fileInput.value) {
        fileInput.value.value = ''
    }
}

const setFile = (file: File): void => {
    processFile(file)
}

defineExpose({
    reset,
    setFile
})
</script>

<style scoped>
.file-drop-container {
    border: 2px dashed var(--color-border);
    border-radius: 16px;
    padding: 20px;
    text-align: center;
    transition: all 0.3s ease;
    position: relative;
    background: var(--color-bg-soft);
}

.file-drop-container--has-file {
    border-color: var(--color-primary);
    background-color: rgba(0, 106, 202, 0.08);
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
    transform: scale(1.02);
}

.file-drop-container__placeholder {
    color: var(--color-text-muted);
    font-size: 1em;
    display: block;
}

.file-drop-container__help {
    margin-top: 10px;
    color: var(--color-text-soft);
    font-size: 0.9em;
}

.file-drop-container__error {
    margin-top: 10px;
    color: var(--color-error);
    font-size: 0.9em;
    font-weight: 500;
}

.file-drop-container__preview {
    margin-top: 15px;
    padding: 15px;
    background-color: var(--color-surface);
    border-radius: 12px;
    border: 1px solid var(--color-border);
}

.file-drop-container__preview-image {
    max-height: 200px;
    border-radius: 4px;
    box-shadow: var(--shadow-card);
}
</style>
