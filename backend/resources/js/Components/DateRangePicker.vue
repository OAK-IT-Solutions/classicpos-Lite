<template>
  <div class="flex items-center gap-2 flex-wrap">
    <button v-for="preset in presets" :key="preset.label"
      @click="applyPreset(preset)"
      :class="['px-3 py-1.5 text-xs font-medium rounded-lg border transition-colors',
        isActivePreset(preset) ? 'bg-primary text-white border-primary' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50']">
      {{ preset.label }}
    </button>
    <input type="date" :value="modelValue.from" @input="emitFrom(($event.target as HTMLInputElement).value)"
      class="px-2 py-1.5 border border-gray-300 rounded-lg text-xs" />
    <span class="text-xs text-gray-400">to</span>
    <input type="date" :value="modelValue.to" @input="emitTo(($event.target as HTMLInputElement).value)"
      class="px-2 py-1.5 border border-gray-300 rounded-lg text-xs" />
  </div>
</template>

<script setup lang="ts">
const props = defineProps<{ modelValue: { from: string; to: string } }>()
const emit = defineEmits<{ (e: 'update:modelValue', v: { from: string; to: string }): void }>()

const presets = [
  { label: 'Today', days: 0 },
  { label: '7D', days: 7 },
  { label: '30D', days: 30 },
  { label: '90D', days: 90 },
  { label: 'This Month', days: 'month' as const },
]

function applyPreset(p: typeof presets[number]) {
  const to = new Date()
  let from: Date
  if (p.days === 'month') {
    from = new Date(to.getFullYear(), to.getMonth(), 1)
  } else if (p.days === 0) {
    from = new Date(to)
  } else {
    from = new Date(to)
    from.setDate(from.getDate() - p.days)
  }
  emit('update:modelValue', { from: fmt(from), to: fmt(to) })
}

function isActivePreset(p: typeof presets[number]): boolean {
  const { from, to } = props.modelValue
  if (!from || !to) return false
  const toDate = new Date(to)
  const fromDate = new Date(from)
  const diffDays = Math.round((toDate.getTime() - fromDate.getTime()) / 86400000)
  if (p.days === 0) return diffDays === 0 && fmt(toDate) === from
  if (p.days === 'month') return fromDate.getDate() === 1 && diffDays <= 31
  return Math.abs(diffDays - p.days) <= 1
}

function emitFrom(v: string) { emit('update:modelValue', { from: v, to: props.modelValue.to }) }
function emitTo(v: string) { emit('update:modelValue', { from: props.modelValue.from, to: v }) }
function fmt(d: Date) { return d.toISOString().slice(0, 10) }
</script>
