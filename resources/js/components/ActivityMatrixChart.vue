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

interface Dataset {
    label: string;
    data: number[];
    backgroundColor: string;
}

const props = defineProps<{
    labels: string[];
    datasets: Dataset[];
}>();

const chartData = computed(() => ({
    labels: props.labels,
    datasets: props.datasets,
}));

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { position: 'top' as const },
        tooltip: { mode: 'index' as const, intersect: false },
    },
    scales: {
        x: { stacked: true },
        y: { stacked: true, ticks: { precision: 0 } },
    },
};
</script>

<template>
    <Bar :data="chartData" :options="chartOptions" />
</template>
