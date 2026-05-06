<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 100 100"
        fill="none"
        :class="className"
        v-bind="$attrs"
    >
        <defs>
            <linearGradient id="logo-gradient" x1="20" y1="80" x2="80" y2="20" gradientUnits="userSpaceOnUse">
                <stop offset="0%" stop-color="#A2D149" />
                <stop offset="50%" stop-color="#7CB342" />
                <stop offset="100%" stop-color="#558B2F" />
            </linearGradient>
        </defs>
        
        <!-- Main Leaf/V Shape -->
        <path 
            d="M15,48 C15,48 8,62 38,82 C68,102 92,60 92,35 C92,10 80,5 65,30 C50,55 45,65 38,65 C31,65 15,48 15,48 Z" 
            fill="url(#logo-gradient)" 
        />
        
        <!-- Lightning Bolt Cutout (Simplified shape for robustness) -->
        <path 
            d="M60,35 L75,52 L62,52 L70,72 L52,52 L65,52 Z" 
            fill="white" 
            style="mix-blend-mode: soft-light; opacity: 0.9;"
        />
        
        <!-- Shadow/Detail for the fold -->
        <path 
            d="M38,82 C38,82 45,75 38,65 L30,58 Z" 
            fill="#33691E" 
            fill-opacity="0.2"
        />
    </svg>
</template>
