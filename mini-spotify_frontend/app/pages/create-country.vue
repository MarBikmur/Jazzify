<template>
  <SingleFieldEditorPage
    title="Create Country"
    submitLabel="Create Country"
    loadingLabel="Creating..."
    fieldLabel="Country Name"
    panelEyebrow="Catalog editor"
    panelDescription="Add a country entry for artist metadata and catalog structure."
    :panelTips="countryPanelTips"
    v-model="form.name"
    :loading="isSubmitting"
    @submit="onSubmit"
    @reset="resetForm"
  />
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useCountry } from '~/composables/useCountry'

interface CountryForm {
  name: string
}

const isSubmitting = ref(false)
const countryPanelTips = [
  'Use the canonical country name',
  'Avoid duplicates and alternate spellings',
  'Save once the entry looks clean',
]

const form = ref<CountryForm>({
  name: ''
})

const { createCountry } = useCountry()

const onSubmit = async () => {
  isSubmitting.value = true
  
  try {
    console.log('Creating country:', form.value)
    
    const response = await createCountry(form.value)
    if (response.success) {
      form.value.name = ''
    }
    
  } catch (error) {
    console.error('Error creating country:', error)
  } finally {
    isSubmitting.value = false
  }
}

const resetForm = () => {
  form.value = {
    name: ''
  }
}
</script>

