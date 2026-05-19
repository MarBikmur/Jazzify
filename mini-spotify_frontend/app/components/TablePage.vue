<template>
    <div class="page">
        <div class="table-form">
            <h1 class="title">{{ tl(title) }}</h1>

            <PageState v-if="loading" :message="tl('Loading...')" />
            <PageState v-else-if="error" variant="error" :message="error" />
            <table v-else-if="data && data.length > 0" class="entity-table">
                <thead>
                    <tr>
                        <th v-for="column in columns" :key="column.key">
                            {{ tl(column.label) }}
                        </th>
                        <th v-if="showActions">{{ tl('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, index) in data" :key="getRowKey(row, index)">
                        <td v-for="column in columns" :key="column.key">
                            <slot 
                                :name="`cell-${column.key}`" 
                                :value="row[column.key]" 
                                :row="row"
                                :column="column"
                            >
                                {{ formatCell(row[column.key], column) }}
                            </slot>
                        </td>
                        <td v-if="showActions" class="actions-cell">
                            <slot name="actions" :row="row" :index="index">
                                <button 
                                    @click="onEdit(row, index)"
                                    class="action-btn edit-btn"
                                >
                                    {{ tl('Edit') }}
                                </button>
                                <button 
                                    @click="onDelete(row, index)"
                                    class="action-btn delete-btn"
                                >
                                    {{ tl('Delete') }}
                                </button>
                            </slot>
                        </td>
                    </tr>
                </tbody>
            </table>
            <PageState v-else variant="empty" :message="tl('No data available')" />
        </div>
    </div>
</template>

<script setup lang="ts">
const { tl } = useLocalizedText()

interface Column {
    key: string
    label: string
    formatter?: (value: any) => string
}

interface Props {
    title: string
    columns: Column[]
    data: any[]
    showActions?: boolean
    rowKey?: string | ((row: any, index: number) => string | number)
    loading?: boolean
    error?: string | null
}

const props = withDefaults(defineProps<Props>(), {
    showActions: false,
    rowKey: 'id',
    loading: false,
    error: null
})

const emit = defineEmits<{
    edit: [row: any, index: number]
    delete: [row: any, index: number]
}>()

const formatCell = (value: any, column: Column): string => {
    if (column.formatter) {
        return column.formatter(value)
    }
    if (value === null || value === undefined) {
        return '-'
    }
    if (typeof value === 'object') {
        return JSON.stringify(value)
    }
    return String(value)
}

const getRowKey = (row: any, index: number): string | number => {
    if (typeof props.rowKey === 'function') {
        return props.rowKey(row, index)
    }
    return row[props.rowKey] ?? index
}

const onEdit = (row: any, index: number) => {
    emit('edit', row, index)
}

const onDelete = (row: any, index: number) => {
    emit('delete', row, index)
}
</script>

<style scoped>
@import url('https://api.fontshare.com/v2/css?f[]=spotcast@400,500&display=swap');

.page {
	min-height: 100vh;
	display: flex;
	align-items: center;
	justify-content: center;
	background:
    var(--gradient-glow),
    var(--gradient-page);
	color: var(--color-text-main);
    font-family: 'Spotcast', sans-serif;
}

.table-form {
    width: 90%;
    max-width: 1200px;
    padding: 2rem;
}

.title {
    text-align: center;
    margin-bottom: 2rem;
    font-size: 2rem;
}

.entity-table {
    width: 100%;
    border-collapse: collapse;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: 14px;
    overflow: hidden;
    box-shadow: var(--shadow-card);
}

.entity-table thead {
    background: var(--color-bg-soft);
}

.entity-table th {
    padding: 1rem;
    text-align: left;
    font-weight: 500;
    border-bottom: 1px solid var(--color-border);
}

.entity-table td {
    padding: 1rem;
    border-bottom: 1px solid var(--color-border);
}

.entity-table tbody tr:hover {
    background: var(--color-surface-hover);
    transition: background 0.2s;
}

.entity-table tbody tr:last-child td {
    border-bottom: none;
}

.actions-cell {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.action-btn {
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    font-family: 'Spotcast', sans-serif;
    font-size: 0.9rem;
    transition: all 0.2s;
}

.edit-btn {
    background: var(--button-primary-bg);
    color: var(--button-primary-text);
    border: 1px solid var(--button-primary-border);
}

.edit-btn:hover {
    background: var(--button-primary-hover);
}

.delete-btn {
    background: var(--button-danger-bg);
    color: var(--button-danger-text);
    border: 1px solid var(--button-danger-border);
}

.delete-btn:hover {
    background: var(--button-danger-hover);
}

</style>
