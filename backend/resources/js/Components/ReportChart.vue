<template>
  <div class="bg-surface-raised rounded-xl shadow-sm border border-border-theme p-4" style="height: 220px;">
    <Bar v-if="type === 'bar'" :data="chartData" :options="barOptions" />
    <Doughnut v-else-if="type === 'doughnut'" :data="chartData" :options="doughnutOptions" />
    <Line v-else-if="type === 'line'" :data="chartData" :options="lineOptions" />
    <Bar v-else-if="type === 'horizontalBar'" :data="chartData" :options="hBarOptions" />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Bar, Doughnut, Line } from 'vue-chartjs'
import {
  Chart as ChartJS,
  CategoryScale, LinearScale, BarElement, PointElement, LineElement,
  ArcElement, Title, Tooltip, Legend,
} from 'chart.js'

ChartJS.register(CategoryScale, LinearScale, BarElement, PointElement, LineElement, ArcElement, Title, Tooltip, Legend)

const props = defineProps<{
  type: 'bar' | 'doughnut' | 'line' | 'horizontalBar'
  labels: string[]
  datasets: { label: string; data: number[]; backgroundColor?: string | string[]; borderColor?: string }[]
}>()

const chartData = computed(() => ({
  labels: props.labels,
  datasets: props.datasets.map(d => ({
    ...d,
    backgroundColor: d.backgroundColor ?? '#3b82f6',
    borderColor: d.borderColor ?? '#3b82f6',
    fill: props.type === 'line',
    tension: props.type === 'line' ? 0.3 : undefined,
  })),
}))

const baseOpts = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
    tooltip: { enabled: true },
  },
  scales: {
    x: { grid: { display: false } },
    y: { beginAtZero: true, grid: { color: '#e5e7eb' } },
  },
}

const barOptions = baseOpts
const lineOptions = baseOpts

const hBarOptions = {
  ...baseOpts,
  indexAxis: 'y' as const,
}

const doughnutOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { position: 'right' as const, labels: { boxWidth: 12, padding: 8 } },
    tooltip: { enabled: true },
  },
}
</script>
