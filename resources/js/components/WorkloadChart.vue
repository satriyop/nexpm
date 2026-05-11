<script setup lang="ts">
import {
    BarElement,
    CategoryScale,
    Chart as ChartJS,
    Legend,
    LinearScale,
    Tooltip,
} from 'chart.js';
import { computed } from 'vue';
import { Bar } from 'vue-chartjs';

ChartJS.register(BarElement, CategoryScale, LinearScale, Tooltip, Legend);

interface WorkloadItem {
    name: string;
    pending: number;
    in_progress: number;
    revision: number;
    completed: number;
}

const props = defineProps<{
    items: WorkloadItem[];
}>();

const chartData = computed(() => ({
    labels: props.items.map((s) => s.name),
    datasets: [
        {
            label: 'Pending',
            data: props.items.map((s) => s.pending),
            backgroundColor: 'rgba(156, 163, 175, 0.85)',
        },
        {
            label: 'In Progress',
            data: props.items.map((s) => s.in_progress),
            backgroundColor: 'rgba(59, 130, 246, 0.85)',
        },
        {
            label: 'Revision',
            data: props.items.map((s) => s.revision),
            backgroundColor: 'rgba(245, 158, 11, 0.85)',
        },
        {
            label: 'Completed',
            data: props.items.map((s) => s.completed),
            backgroundColor: 'rgba(16, 185, 129, 0.85)',
        },
    ],
}));

const chartOptions = computed(() => ({
    indexAxis: 'y' as const,
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'top' as const,
            labels: { boxWidth: 12, padding: 16, font: { size: 11 } },
        },
        tooltip: {
            callbacks: {
                footer: (items: any[]) => {
                    const total = items.reduce((s, i) => s + i.raw, 0);

                    return `Total: ${total}`;
                },
            },
        },
    },
    scales: {
        x: { stacked: true, grid: { display: false } },
        y: { stacked: true, ticks: { font: { size: 11 } } },
    },
}));

const chartHeight = computed(() => Math.min(props.items.length * 36 + 60, 480));
</script>

<template>
    <div :style="{ height: chartHeight + 'px' }">
        <Bar :data="chartData" :options="chartOptions" />
    </div>
</template>
