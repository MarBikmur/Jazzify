<template>
  <EditablePage
    class="editor-page-template"
    :title="title"
    :submitLabel="submitLabel"
    :loadingLabel="loadingLabel"
    :loading="loading"
    :submitButtonStyle="submitButtonStyle"
    :showSubmit="showSubmit"
    :style="{ '--editor-content-width': contentWidth }"
    @submit="$emit('submit')"
  >
    <div class="editor-page-template__body" :style="{ '--editor-content-width': contentWidth }">
      <slot />
      <slot name="errors" />
    </div>

    <template #footer>
      <div class="editor-page-template__footer" :style="{ '--editor-content-width': contentWidth }">
        <slot name="footer" />
      </div>
    </template>
  </EditablePage>
</template>

<script setup lang="ts">
withDefaults(defineProps<{
  title: string
  submitLabel: string
  loadingLabel?: string
  loading?: boolean
  submitButtonStyle?: string
  showSubmit?: boolean
  contentWidth?: string
}>(), {
  loadingLabel: 'Saving...',
  loading: false,
  submitButtonStyle: '',
  showSubmit: true,
  contentWidth: '780px',
})

defineEmits<{
  submit: []
}>()
</script>

<style scoped>
.editor-page-template :deep(.credentials-form) {
  width: min(var(--editor-content-width, 780px), 100%);
  margin: 0 auto;
}

.editor-page-template :deep(.title) {
  width: min(var(--editor-content-width, 780px), 100%);
  margin: 0 auto 18px;
}

.editor-page-template :deep(form) {
  width: 100%;
  display: flex;
  flex-wrap: wrap;
  justify-content: center !important;
  align-items: flex-start;
  gap: 14px;
}

.editor-page-template__body,
.editor-page-template__footer {
  width: min(var(--editor-content-width, 780px), 100%);
  margin: 0 auto;
}

.editor-page-template__body {
  display: grid;
  gap: 14px;
  order: 1;
}

.editor-page-template__footer {
  display: flex;
  order: 21;
  width: auto;
  margin: 0;
  justify-content: center;
  align-items: center;
}

.editor-page-template :deep(.submit-button) {
  order: 20;
}

.editor-page-template :deep(.submit-button),
.editor-page-template :deep(.cancel-button) {
  width: 180px;
  margin: 0;
  flex: 0 0 auto;
}

.editor-page-template :deep(.cancel-button) {
  margin-left: 12px;
}

.editor-page-template__body :deep(.field),
.editor-page-template__body :deep(.selector-field),
.editor-page-template__body :deep(.file-drop-container),
.editor-page-template__body :deep(.tracks-panel),
.editor-page-template__body :deep(.current-cover),
.editor-page-template__body :deep(.track-editor),
.editor-page-template__body :deep(.tracks-table),
.editor-page-template__body :deep(.tracks-empty),
.editor-page-template__body :deep(.notice) {
  width: 100%;
  max-width: 100%;
  margin-left: auto;
  margin-right: auto;
}
</style>
