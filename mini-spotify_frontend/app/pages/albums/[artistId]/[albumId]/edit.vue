<template>
  <AlbumEditorPage
    mode="edit"
    :artist-id="String(route.params.artistId || '')"
    :album-id="String(route.params.albumId || '')"
  />
</template>

<script setup lang="ts">
const route = useRoute()
</script>
