<template>
    <TablePage
        title="Countries"
        :columns="columns"
        :data="countries"
        :loading="loading"
        :error="error"
        :show-actions="true"
        @edit="handleEdit"
        @delete="handleDelete"
    />
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useCountry } from '~/composables/useCountry'

const { getCountries } = useCountry()

const countries = ref<any[]>([])
const loading = ref(false)
const error = ref<string | null>(null)

const columns = [
    { key: 'id', label: 'ID' },
    { key: 'name', label: 'Country Name' }
]

const loadCountries = async () => {
    loading.value = true
    error.value = null
    try {
        countries.value = await getCountries()
    } catch (err: any) {
        error.value = err?.data?.message || 'Failed to load countries'
        console.error('Error loading countries:', err)
    } finally {
        loading.value = false
    }
}

const handleEdit = (row: any, index: number) => {
    console.log('Edit country:', row, index)
}

const handleDelete = async (row: any, index: number) => {
    if (confirm(`Are you sure you want to delete "${row.name}"?`)) {
        try {
            await loadCountries()
        } catch (err) {
            console.error('Error deleting country:', err)
        }
    }
}

onMounted(() => {
    loadCountries()
})
</script>






