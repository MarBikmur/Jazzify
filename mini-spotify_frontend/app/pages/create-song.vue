<template>
    <SingleFieldEditorPage
        title="Create Song"
        submitLabel="Create Song"
        loadingLabel="Creating..."
        fieldLabel="Song name"
        fieldName="title"
        panelEyebrow="Track editor"
        panelDescription="This page is still a stub, but the editor shell is ready for the full track flow."
        :panelTips="songPanelTips"
        v-model="form.title"
        :loading="isSubmitting"
        :errorMessage="errorMessage"
        @submit="onSubmit"
        @reset="resetForm"
    />
</template>

<script setup lang="ts">
import { ref } from 'vue'

const isSubmitting = ref(false)
const errorMessage = ref('')
const songPanelTips = [
    'Set the working song title',
    'Keep the naming close to the final release version',
    'Wait for the full track flow before publishing',
]
const form = ref({
    title: ''
})

const onSubmit = async () => {
    errorMessage.value = 'Song creation flow is not configured yet.'
}

const resetForm = () => {
    form.value.title = ''
    errorMessage.value = ''
}
</script>
