<template>
  <EditorPageTemplate
    :title="tl('Edit profile')"
    :submitLabel="tl('Save profile')"
    :loadingLabel="tl('Saving...')"
    :loading="isBusy"
    contentWidth="780px"
    @submit="saveProfile"
  >
    <div class="profile-editor">
      <section v-if="showCurrentAvatarImage" class="profile-editor__current-image">
        <span class="profile-editor__current-image-label">{{ tl('Current avatar') }}</span>
        <img
          :src="initialAvatarImageUrl"
          :alt="form.name || tl('User avatar')"
          class="profile-editor__current-image-preview"
        />
      </section>

      <ImageCropUpload
        :label="tl('Account avatar')"
        item-extension="image/jpeg,image/png"
        help-text="Supported formats: JPEG, PNG. Maximum size: 10MB"
        shape="circle"
        @file-selected="handleAvatarSelected"
        @file-cleared="handleAvatarCleared"
        @file-error="handleAvatarError"
      />

      <UserDataFields
        :label="tl('Display name')"
        type="text"
        name="name"
        :show-validation-errors="false"
        input-credentials="inputCredentials"
        :model-value="form.name"
        :v$="null"
        @update:modelValue="form.name = $event"
      />

      <UserDataFields
        label="Email"
        type="email"
        name="email"
        :show-validation-errors="false"
        input-credentials="inputCredentials"
        :model-value="form.email"
        :v$="null"
        @update:modelValue="form.email = $event"
      />

      <template v-if="isArtist">
        <UserDataFields
          :label="tl('Artist nickname')"
          type="text"
          name="artist-name"
          :show-validation-errors="false"
          input-credentials="inputCredentials"
          :model-value="form.artistName"
          :v$="null"
          @update:modelValue="form.artistName = $event"
        />

        <Selector
          v-model="form.countryId"
          :options="countryOptions"
          :label="tl('Country')"
          :placeholder="tl('Select your country')"
          :show-validation-errors="false"
          :v$="null"
        />

        <section v-if="showCurrentArtistImage" class="profile-editor__current-image">
          <span class="profile-editor__current-image-label">{{ tl('Current artist image') }}</span>
          <img
            :src="initialArtistImageUrl"
            :alt="form.artistName || form.name || tl('Artist image')"
            class="profile-editor__current-image-preview"
          />
        </section>

        <ImageCropUpload
          :label="tl('Artist image')"
          item-extension="image/jpeg,image/png"
          help-text="Supported formats: JPEG, PNG. Maximum size: 10MB"
          shape="circle"
          @file-selected="handleArtistImageSelected"
          @file-cleared="handleArtistImageCleared"
          @file-error="handleArtistImageError"
        />
      </template>

      <FormNotice v-if="errorMessage" variant="error" :message="errorMessage" />
      <FormNotice v-if="successMessage" variant="success" :message="successMessage" />
    </div>

    <template #footer>
      <div class="profile-editor__footer">
        <button type="button" class="profile-editor__secondary" :disabled="isBusy" @click="goToChangePassword">
          {{ tl('Change password') }}
        </button>
        <CancelButton
          :disabled="isBusy"
          :label="tl('Reset')"
          @click="resetForm"
        />
      </div>
    </template>
  </EditorPageTemplate>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref, watchEffect } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '~/composables/useAuth'
import { useArtist } from '~/composables/useArtist'
import { useCountry } from '~/composables/useCountry'
import { useEditorLayout } from '~/composables/useEditorLayout'
import { useLocalizedText } from '~/composables/useLocalizedText'

interface CurrentUser {
  uid: string
  name: string
  email: string
  role?: string
  avatar_url?: string | null
}

interface CurrentArtist {
  id: number
  name: string
  country_id?: number
  image_url?: string
}

const router = useRouter()
const { tl } = useLocalizedText()
const { getCurrentUser, updateCurrentProfile } = useAuth()
const { getCurrentArtist, updateCurrentArtist } = useArtist()
const { getCountries } = useCountry()
const { setEditorPanel, clearEditorPanel } = useEditorLayout()

const loading = ref(false)
const loadingProfile = ref(true)
const errorMessage = ref('')
const successMessage = ref('')
const user = ref<CurrentUser | null>(null)
const artist = ref<CurrentArtist | null>(null)
const countries = ref<Array<{ id: number; name: string }>>([])
const initialAvatarImageUrl = ref('')
const initialArtistImageUrl = ref('')
const avatarImageFile = ref<File | null>(null)
const artistImageFile = ref<File | null>(null)
const avatarPreviewUrl = ref('')
const artistPreviewUrl = ref('')
const removeAvatar = ref(false)
const removeArtistImage = ref(false)

const form = reactive({
  name: '',
  email: '',
  artistName: '',
  countryId: '',
})

const isArtist = computed(() => !!artist.value?.id || user.value?.role === 'artist')
const isBusy = computed(() => loading.value || loadingProfile.value)
const showCurrentAvatarImage = computed(() => !!initialAvatarImageUrl.value)
const showCurrentArtistImage = computed(() => !!initialArtistImageUrl.value)
const countryOptions = computed(() => countries.value.map((country) => ({
  value: String(country.id),
  label: country.name,
})))

const profileImageUrl = computed(() => initialAvatarImageUrl.value)
const panelTitle = computed(() => {
  if (isArtist.value) {
    return form.artistName.trim() || form.name.trim() || tl('Edit profile')
  }

  return form.name.trim() || tl('Edit profile')
})

const revokePreviewUrl = (url: string) => {
  if (url) {
    URL.revokeObjectURL(url)
  }
}

const loadProfile = async () => {
  loadingProfile.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const currentUser = await getCurrentUser()

    if (!currentUser) {
      await router.replace('/login')
      return
    }

    user.value = currentUser
    form.name = currentUser.name || ''
    form.email = currentUser.email || ''
    initialAvatarImageUrl.value = currentUser.avatar_url || ''

    const currentArtist = await getCurrentArtist()
    artist.value = currentArtist

    if (currentArtist) {
      countries.value = await getCountries()
      form.artistName = currentArtist.name || ''
      form.countryId = currentArtist.country_id ? String(currentArtist.country_id) : ''
      initialArtistImageUrl.value = currentArtist.image_url || ''
      return
    }

    countries.value = []
    form.artistName = ''
    form.countryId = ''
    initialArtistImageUrl.value = ''
  } catch (error: any) {
    errorMessage.value = error?.data?.message || tl('Could not load profile')
  } finally {
    loadingProfile.value = false
  }
}

const resetForm = () => {
  if (!user.value) {
    return
  }

  errorMessage.value = ''
  successMessage.value = ''
  form.name = user.value.name || ''
  form.email = user.value.email || ''
  initialAvatarImageUrl.value = user.value.avatar_url || ''
  form.artistName = artist.value?.name || ''
  form.countryId = artist.value?.country_id ? String(artist.value.country_id) : ''
  initialArtistImageUrl.value = artist.value?.image_url || ''

  revokePreviewUrl(avatarPreviewUrl.value)
  revokePreviewUrl(artistPreviewUrl.value)
  avatarPreviewUrl.value = ''
  artistPreviewUrl.value = ''
  avatarImageFile.value = null
  artistImageFile.value = null
  removeAvatar.value = false
  removeArtistImage.value = false
}

const handleAvatarSelected = (file: File) => {
  revokePreviewUrl(avatarPreviewUrl.value)
  avatarImageFile.value = file
  avatarPreviewUrl.value = URL.createObjectURL(file)
  removeAvatar.value = false
  successMessage.value = ''
  errorMessage.value = ''
}

const handleAvatarCleared = () => {
  avatarImageFile.value = null
  revokePreviewUrl(avatarPreviewUrl.value)
  avatarPreviewUrl.value = ''
  removeAvatar.value = !!initialAvatarImageUrl.value
  if (removeAvatar.value) {
    initialAvatarImageUrl.value = ''
  }
  successMessage.value = ''
  errorMessage.value = ''
}

const handleAvatarError = (message: unknown) => {
  errorMessage.value = typeof message === 'string' ? message : tl('Avatar upload failed')
}

const handleArtistImageSelected = (file: File) => {
  revokePreviewUrl(artistPreviewUrl.value)
  artistImageFile.value = file
  artistPreviewUrl.value = URL.createObjectURL(file)
  removeArtistImage.value = false
  successMessage.value = ''
  errorMessage.value = ''
}

const handleArtistImageCleared = () => {
  artistImageFile.value = null
  revokePreviewUrl(artistPreviewUrl.value)
  artistPreviewUrl.value = ''
  removeArtistImage.value = !!initialArtistImageUrl.value
  if (removeArtistImage.value) {
    initialArtistImageUrl.value = ''
  }
  successMessage.value = ''
  errorMessage.value = ''
}

const handleArtistImageError = (message: unknown) => {
  errorMessage.value = typeof message === 'string' ? message : tl('Artist image upload failed')
}

const emitProfileUpdated = () => {
  if (typeof window !== 'undefined') {
    window.dispatchEvent(new CustomEvent('profile-updated'))
  }
}

const saveProfile = async () => {
  if (!user.value || loading.value) {
    return
  }

  loading.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const updatedUser = await updateCurrentProfile({
      name: form.name.trim(),
      email: form.email.trim(),
      avatar: avatarImageFile.value,
      remove_avatar: removeAvatar.value && !avatarImageFile.value,
    })

    user.value = updatedUser as CurrentUser
    initialAvatarImageUrl.value = updatedUser?.avatar_url || ''
    revokePreviewUrl(avatarPreviewUrl.value)
    avatarPreviewUrl.value = ''
    avatarImageFile.value = null
    removeAvatar.value = false

    if (isArtist.value) {
      const artistResult = await updateCurrentArtist({
        name: form.artistName.trim(),
        country_id: form.countryId || undefined,
        image: artistImageFile.value,
        remove_image: removeArtistImage.value && !artistImageFile.value,
      })

      if (!artistResult.success) {
        throw new Error(artistResult.message || tl('Could not update artist profile'))
      }

      artist.value = (artistResult.data || null) as CurrentArtist | null
      form.artistName = artist.value?.name || ''
      form.countryId = artist.value?.country_id ? String(artist.value.country_id) : ''
      initialArtistImageUrl.value = artist.value?.image_url || ''
      revokePreviewUrl(artistPreviewUrl.value)
      artistPreviewUrl.value = ''
      artistImageFile.value = null
      removeArtistImage.value = false
    }

    successMessage.value = tl('Profile updated successfully')
    emitProfileUpdated()
  } catch (error: any) {
    errorMessage.value = error?.data?.message || error?.message || tl('Could not save profile')
  } finally {
    loading.value = false
  }
}

const goToChangePassword = async () => {
  await router.push('/profile/change-password')
}

watchEffect(() => {
  setEditorPanel({
    eyebrow: tl('Profile'),
    title: panelTitle.value,
    description: isArtist.value
      ? tl('Update account details, avatar and public artist profile in one place.')
      : tl('Update the account data and avatar used across the app.'),
    imageUrl: profileImageUrl.value,
    imageShape: 'circle',
    imageFallback: (panelTitle.value.slice(0, 1) || 'P').toUpperCase(),
    stats: [
      { label: tl('Role'), value: user.value?.role || 'user' },
      { label: tl('Email'), value: form.email || tl('Not set') },
    ],
    sections: [
      {
        title: tl('Checklist'),
        items: isArtist.value
          ? [tl('Update display name and avatar'), tl('Review artist nickname and country'), tl('Adjust the artist image if needed')]
          : [tl('Update display name'), tl('Review email and avatar'), tl('Use change password for credentials')],
      },
    ],
  })
})

onMounted(async () => {
  await loadProfile()
})

onBeforeUnmount(() => {
  revokePreviewUrl(avatarPreviewUrl.value)
  revokePreviewUrl(artistPreviewUrl.value)
  clearEditorPanel()
})
</script>

<style scoped>
.profile-editor {
  display: grid;
  gap: 14px;
}

.profile-editor__footer {
  display: flex;
  gap: 12px;
  align-items: center;
  justify-content: center;
}

.profile-editor__secondary {
  min-width: 180px;
  min-height: 44px;
  padding: 0 20px;
  border: 1px solid var(--color-border);
  border-radius: 999px;
  background: var(--color-surface);
  color: var(--color-primary);
  font-family: 'Spotcast', sans-serif;
  font-size: 0.95rem;
  font-weight: 500;
  cursor: pointer;
  transition: background-color 0.2s ease, border-color 0.2s ease, transform 0.2s ease;
}

.profile-editor__secondary:hover:not(:disabled) {
  background: var(--color-surface-hover);
  border-color: var(--color-border-strong);
  transform: scale(1.03);
}

.profile-editor__secondary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.profile-editor__current-image {
  display: grid;
  gap: 10px;
  width: 100%;
  justify-items: center;
  text-align: center;
}

.profile-editor__current-image-label {
  color: var(--color-text-main);
  font-size: 0.95rem;
  font-weight: 700;
}

.profile-editor__current-image-preview {
  width: min(180px, 100%);
  aspect-ratio: 1;
  display: block;
  object-fit: cover;
  border-radius: 50%;
  border: 1px solid var(--color-border);
  box-shadow: var(--shadow-card);
  background: var(--color-surface);
}
</style>
