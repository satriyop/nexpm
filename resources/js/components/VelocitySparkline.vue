<script setup lang="ts">
import {
    CategoryScale,
    Chart as ChartJS,
    Filler,
    LinearScale,
    LineElement,
    PointElement,
    Tooltip,
} from 'chart.js';
import { computed } from 'vue';
import { Line } from 'vue-chartjs';

ChartJS.register(
    LineElement,
    PointElement,
    CategoryScale,
    LinearScale,
    Filler,
    Tooltip,
);

const props = defineProps<{
    weeks: { label: string; count: number }[];
}>();

const chartData = computed(() => ({
    labels: props.weeks.map((w) => w.label),
    datasets: [
        {
            data: props.weeks.map((w) => w.count),
            borderColor: 'rgb(16, 185, 129)',
            backgroundColor: 'rgba(16, 185, 129, 0.12)',
            fill: true,
            tension: 0.4,
            pointRadius: 2,
            pointHoverRadius: 4,
            borderWidth: 2,
        },
    ],
}));

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: {
            callbacks: {
                title: (items: any[]) => `Week of ${items[0].label}`,
                label: (item: any) => `${item.raw} completed`,
            },
        },
    },
    scales: {
        x: { display: false },
        y: { display: false, beginAtZero: true },
    },
};
</script>

<template>
    <Line :data="chartData" :options="chartOptions" />
</template>
