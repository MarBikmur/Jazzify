<template>
    <SingleFieldEditorPage
        title="Create Genre"
        submitLabel="Create Genre"
        loadingLabel="Creating..."
        fieldLabel="Genre Name"
        panelEyebrow="Catalog editor"
        panelDescription="Add a genre that can be reused across tracks and albums."
        :panelTips="genrePanelTips"
        v-model="form.name"
        :loading="isSubmitting"
        @submit="onSubmit"
        @reset="resetForm"
    />
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useGenre } from '~/composables/useGenre';

interface GenreForm {
    name: string
}

const isSubmitting = ref(false)
const genrePanelTips = [
    'Use a recognizable genre name',
    'Keep naming consistent with the existing catalog',
    'Save once the genre is ready for reuse',
]

const form = ref<GenreForm>({
    name: ''
})

const { createGenre } = useGenre()

const onSubmit = async() => {
    isSubmitting.value = true
    
    try{
        console.log('Creating genre:', form.value)

        const response = await createGenre(form.value)
        if (response.success){
            form.value.name  = ''
        }
    }catch (error) {
        console.error('Error creating genre', error)
    }finally {
        isSubmitting.value = false
    }
}

const resetForm = () => {
    form.value = {
        name: ''
    }
}
</script>
