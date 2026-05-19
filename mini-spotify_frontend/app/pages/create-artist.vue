<template>
  <ImageEntityEditorPage
    ref="artistEditorPage"
    title="Become an artist"
    submitLabel="Become an Artist"
    loadingLabel="Creating..."
    :loading="loading"
    submitButtonStyle="width: 170px;"
    fieldLabel="Nickname"
    fieldName="name"
    v-model="form.name"
    imageLabel="Artist Image"
    imageHelpText="Supported formats: JPEG, PNG. Maximum size: 10MB"
    imageShape="circle"
    :errorMessage="errorMessage"
    panelEyebrow="Artist profile"
    panelDescription="Create the public artist profile. Your account avatar is managed separately in profile settings."
    :panelTips="artistPanelTips"
    :panelImageUrl="imagePreviewUrl"
    :panelImageFallback="form.name.trim().slice(0, 1).toUpperCase() || 'A'"
    @submit="createArtist"
    @image-selected="handleFileSelected"
    @image-error="handleFileError"
    @image-cleared="handleFileCleared"
  >
    <template #fields>
      <Selector
        v-model="form.country_id"
        :options="countryOptions"
        label="Country"
        placeholder="Select your country"
        :show-validation-errors="false"
        input-selector="inputSelector"
        :v$="null"
      />
    </template>
  </ImageEntityEditorPage>
</template>

<script setup lang="ts">
import { ref, onBeforeUnmount, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useCountry } from '~/composables/useCountry'
import { useArtist } from '~/composables/useArtist'

const artistEditorPage = ref<{ resetImage?: () => void } | null>(null)
const router = useRouter()

const form = ref({
  name: '',
  country_id: '',
  image: null as File | null,
})

const loading = ref(false)
const errorMessage = ref('')
const imagePreviewUrl = ref('')
const countries = ref<{ id: number; name: string }[]>([])
const artistPanelTips = [
  'Pick a clear nickname',
  'Choose the artist country',
  'Artist image is optional and separate from the account avatar',
]

const countryOptions = computed(() => {
  return countries.value.map(country => ({
    value: country.id.toString(),
    label: country.name
  }))
})

const { getCountries } = useCountry()
const { createArtist: createArtistApi } = useArtist()

const handleFileSelected = (file: File) => {
  if (imagePreviewUrl.value) {
    URL.revokeObjectURL(imagePreviewUrl.value)
  }

  form.value.image = file
  imagePreviewUrl.value = URL.createObjectURL(file)
  errorMessage.value = ''
}

const handleFileError = (error: any) => {
  console.error('File upload error:', error)
  errorMessage.value = error?.message || 'Image upload failed'
}

const handleFileCleared = () => {
  form.value.image = null
  if (imagePreviewUrl.value) {
    URL.revokeObjectURL(imagePreviewUrl.value)
    imagePreviewUrl.value = ''
  }
}

const createArtist = async () => {
  loading.value = true
  errorMessage.value = ''

  try {
    const response = await createArtistApi({
      name: form.value.name,
      country_id: form.value.country_id,
      image: form.value.image
    })

    if (response.success) {
      form.value = {
        name: '',
        country_id: '',
        image: null
      }
      if (imagePreviewUrl.value) {
        URL.revokeObjectURL(imagePreviewUrl.value)
        imagePreviewUrl.value = ''
      }
      artistEditorPage.value?.resetImage?.()
      await router.push('/')
      window.location.reload()
      return
    }
    errorMessage.value = response.message || 'Could not create artist'
  } catch (error) {
    console.error('Create artist failed:', error)
    errorMessage.value = 'Could not create artist'
  } finally {
    loading.value = false
  }
}

const loadCountries = async () => {
  try {
    const data = await getCountries()
    countries.value = data
  } catch (error) {
    console.error('Error loading countries:', error)
  }
}

onMounted(async () => {
  await loadCountries()
})

onBeforeUnmount(() => {
  if (imagePreviewUrl.value) {
    URL.revokeObjectURL(imagePreviewUrl.value)
  }
})
</script>
