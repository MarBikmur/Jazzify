<template>
  <form class="track-comment-composer" @submit.prevent="submit">
    <div class="track-comment-composer__meta">
      <strong>{{ tl('Add comment') }}</strong>
      <div class="track-comment-composer__timestamp-row">
        <label class="track-comment-composer__timestamp">
          <span>{{ tl('Timestamp') }}</span>
          <input
            v-model="timestampDraft"
            type="text"
            inputmode="numeric"
            :style="{ '--timestamp-length': `${Math.max(timestampDraft.length || 0, 4)}ch` }"
            :disabled="disabled || isSubmitting"
            @blur="commitTimestamp"
            @keydown.enter.prevent="commitTimestamp"
          >
        </label>
        <button class="track-comment-composer__time-button" type="button" @click="$emit('refresh-timestamp')">
          {{ tl('Use current time') }}
        </button>
      </div>
    </div>

    <label class="track-comment-composer__field">
      <textarea
        :value="modelValue"
        rows="3"
        maxlength="250"
        :disabled="disabled || isSubmitting"
        :placeholder="tl('Leave a comment for this moment...')"
        @input="handleInput"
      />
    </label>

    <div class="track-comment-composer__footer">
      <span>{{ modelValue.length }}/250</span>
      <div class="track-comment-composer__actions">
        <button
          v-if="showCancel"
          class="track-comment-composer__cancel"
          type="button"
          @click="$emit('cancel')"
        >
          {{ tl('Cancel') }}
        </button>
        <button
          class="track-comment-composer__send"
          type="submit"
          :disabled="disabled || isSubmitting || !modelValue.trim()"
        >
          {{ isSubmitting ? busyLabel : submitLabel }}
        </button>
      </div>
    </div>
  </form>
</template>

<script setup lang="ts">
const { tl } = useLocalizedText()

const props = withDefaults(defineProps<{
  modelValue: string
  timestamp: number
  timestampLabel: string
  maxTimestamp?: number | null
  isSubmitting?: boolean
  disabled?: boolean
  submitLabel?: string
  busyLabel?: string
  showCancel?: boolean
}>(), {
  submitLabel: 'Comment',
  busyLabel: 'Sending...',
  showCancel: false,
})

const emit = defineEmits<{
  submit: [text: string, done: () => void]
  'refresh-timestamp': []
  'update-timestamp': [timestamp: number]
  'update:modelValue': [value: string]
  cancel: []
}>()

const timestampDraft = ref(props.timestampLabel)

const parseTimestamp = (value: string) => {
  const normalized = value.trim()

  if (!normalized) {
    return null
  }

  if (/^\d+$/.test(normalized)) {
    return Number(normalized)
  }

  const match = normalized.match(/^(\d+):([0-5]?\d)$/)

  if (!match) {
    return null
  }

  return Number(match[1]) * 60 + Number(match[2])
}

const commitTimestamp = () => {
  const nextValue = parseTimestamp(timestampDraft.value)

  if (nextValue === null) {
    timestampDraft.value = props.timestampLabel
    return
  }

  const boundedValue = Math.max(0, Math.min(nextValue, Math.max(0, Math.floor(props.maxTimestamp ?? Number.MAX_SAFE_INTEGER))))
  emit('update-timestamp', boundedValue)
  timestampDraft.value = formatBoundedTimestamp(boundedValue)
}

const formatBoundedTimestamp = (value: number) => {
  const minutes = Math.floor(value / 60)
  const seconds = value % 60
  return `${minutes}:${seconds.toString().padStart(2, '0')}`
}

const submit = () => {
  if (!props.modelValue.trim() || props.disabled || props.isSubmitting) {
    return
  }

  emit('submit', props.modelValue, () => emit('update:modelValue', ''))
}

const handleInput = (event: Event) => {
  const target = event.target as HTMLTextAreaElement | null
  emit('update:modelValue', target?.value || '')
}

watch(
  () => props.timestampLabel,
  (value) => {
    timestampDraft.value = value
  }
)
</script>

<style scoped>
.track-comment-composer {
  display: grid;
  gap: 12px;
  padding: 14px;
  border: 1px solid var(--color-card-border);
  border-radius: 18px;
  background: var(--color-card-surface);
}

.track-comment-composer__footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.track-comment-composer__actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.track-comment-composer__meta {
  display: grid;
  gap: 6px;
}

.track-comment-composer__timestamp-row {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}

.track-comment-composer__meta strong {
  color: var(--color-text-main);
  font-size: 0.95rem;
}

.track-comment-composer__meta span,
.track-comment-composer__footer span {
  color: var(--color-text-muted);
  font-size: 0.8rem;
}

.track-comment-composer__timestamp {
  display: inline-grid;
  gap: 4px;
  width: fit-content;
}

.track-comment-composer__timestamp input {
  width: calc(max(4ch, var(--timestamp-length, 4)) + 12px);
  min-height: 28px;
  padding: 0 6px;
  border: 1px solid var(--color-input-border);
  border-radius: 999px;
  outline: 0;
  color: var(--color-input-text);
  background: var(--color-input-bg);
  font: inherit;
  font-size: 0.84rem;
  font-weight: 700;
  text-align: center;
}

.track-comment-composer__timestamp input:focus {
  border-color: var(--color-input-focus-border);
  box-shadow: 0 0 0 3px var(--color-input-focus-ring);
}

.track-comment-composer__time-button,
.track-comment-composer__cancel,
.track-comment-composer__send {
  border: 0;
  font: inherit;
  cursor: pointer;
}

.track-comment-composer__time-button {
  padding: 0;
  color: var(--color-primary);
  background: transparent;
  font-size: 0.82rem;
  font-weight: 800;
  white-space: normal;
  text-align: left;
}

.track-comment-composer__time-button:hover {
  text-decoration: underline;
}

.track-comment-composer__field textarea {
  width: 100%;
  min-height: 96px;
  resize: vertical;
  padding: 12px 14px;
  border: 1px solid var(--color-input-border);
  border-radius: 16px;
  outline: 0;
  color: var(--color-input-text);
  background: var(--color-input-bg);
  font: inherit;
}

.track-comment-composer__field textarea::placeholder {
  color: var(--color-input-placeholder);
}

.track-comment-composer__field textarea:focus {
  border-color: var(--color-input-focus-border);
  box-shadow: 0 0 0 3px var(--color-input-focus-ring);
}

.track-comment-composer__send {
  min-height: 34px;
  padding: 0 16px;
  border-radius: 999px;
  color: var(--button-primary-text);
  background: var(--button-primary-bg);
  border: 1px solid var(--button-primary-border);
  font-weight: 800;
}

.track-comment-composer__cancel {
  min-height: 34px;
  padding: 0 14px;
  border: 1px solid var(--button-control-border);
  border-radius: 999px;
  color: var(--button-control-text);
  background: var(--button-control-bg);
  font-weight: 800;
}

.track-comment-composer__send:hover:not(:disabled) {
  background: var(--button-primary-hover);
}

.track-comment-composer__cancel:hover {
  background: var(--button-control-hover);
}

.track-comment-composer__send:disabled,
.track-comment-composer__field textarea:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

@media (max-width: 560px) {
  .track-comment-composer {
    gap: 14px;
  }

  .track-comment-composer__timestamp-row {
    align-items: stretch;
    flex-direction: column;
    gap: 10px;
  }

  .track-comment-composer__timestamp {
    width: fit-content;
  }

  .track-comment-composer__timestamp input {
    max-width: none;
  }

  .track-comment-composer__time-button {
    max-width: none;
    align-self: flex-start;
  }

  .track-comment-composer__footer {
    align-items: stretch;
    flex-direction: column;
  }

  .track-comment-composer__actions {
    width: 100%;
    justify-content: stretch;
  }

  .track-comment-composer__cancel,
  .track-comment-composer__send {
    flex: 1 1 0;
  }
}

@media (max-width: 420px) {
  .track-comment-composer {
    padding: 12px;
  }

  .track-comment-composer__cancel,
  .track-comment-composer__send {
    width: 100%;
  }
}
</style>
