<template>
    <TablePage
        :title="isAdmin ? 'All Albums' : 'My Albums'"
        :columns="columns"
        :data="albums"
        :loading="loading"
        :error="error"
        :show-actions="isAdmin || isArtist"
        @edit="handleEdit"
        @delete="handleDelete"
    >
        <template #cell-cover_image_path="{ value }">
            <img 
                v-if="value" 
                :src="getImageUrl(value)" 
                alt="Album cover" 
                class="album-cover"
            />
            <span v-else>No cover</span>
        </template>
    </TablePage>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useAlbum } from '~/composables/useAlbum'
import { useRole } from '~/composables/useRole'

const { getAlbums, getMyAlbums } = useAlbum()
const { loadCurrentUser, isAdmin, isArtist } = useRole()
const { mediaUrl } = useMediaUrl()

const albums = ref<any[]>([])
const loading = ref(false)
const error = ref<string | null>(null)

const baseColumns = [
    { key: 'cover_image_path', label: 'Cover Image' },
    { key: 'title', label: 'Title' }
]

const columns = computed(() => {
    const cols = [...baseColumns]
    if (isAdmin.value) {
        cols.push({ key: 'id', label: 'ID' })
        cols.push({ key: 'artist_id', label: 'Artist ID' })
    }
    return cols
})

const getImageUrl = (imagePath: string): string => {
    return mediaUrl(imagePath)
}

const loadAlbums = async () => {
    loading.value = true
    error.value = null
    try {
        if (isAdmin.value) {
            albums.value = await getAlbums()
        } else if (isArtist.value) {
            albums.value = await getMyAlbums()
        } else {
            error.value = 'You do not have permission to view albums'
        }
    } catch (err: any) {
        error.value = err?.data?.message || 'Failed to load albums'
        console.error('Error loading albums:', err)
    } finally {
        loading.value = false
    }
}

const handleEdit = (row: any, index: number) => {
    console.log('Edit album:', row, index)
}

const handleDelete = async (row: any, index: number) => {
    if (confirm(`Are you sure you want to delete "${row.title}"?`)) {
        try {
            await loadAlbums()
        } catch (err) {
            console.error('Error deleting album:', err)
        }
    }
}

onMounted(async () => {
    await loadCurrentUser()
    await loadAlbums()
})
</script>

<style scoped>
.album-cover {
    width: 50px;
    height: 50px;
    object-fit: cover;
    border-radius: 4px;
}
</style>
