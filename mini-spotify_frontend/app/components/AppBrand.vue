<template>
  <NuxtLink class="app-brand" :to="to">
    <img class="app-brand__icon" :src="iconSrc" :alt="title" />
    <span class="app-brand__title">{{ title }}</span>
  </NuxtLink>
</template>

<script setup lang="ts">
withDefaults(defineProps<{
  title?: string
  to?: string
  iconSrc?: string
}>(), {
  title: 'Jazzify',
  to: '/',
  iconSrc: '/favicon.ico',
})
</script>

<style scoped>
.app-brand {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  color: var(--color-text-main);
  text-decoration: none;
}

.app-brand__icon {
  width: 34px;
  height: 34px;
  display: block;
  object-fit: cover;
  border-radius: 50%;
  flex: 0 0 auto;
}

.app-brand__title {
  font-size: 1.12rem;
  font-weight: 800;
}
</style>
