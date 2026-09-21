<script setup lang="ts">
type Props = {
    title: string;
    description?: string;
    variant?: 'default' | 'small';
};

withDefaults(defineProps<Props>(), {
    variant: 'default',
});
</script>

<template>
    <header :class="variant === 'small' ? '' : 'mb-8 space-y-0.5'">
        <h2
            :class="
                variant === 'small'
                    ? 'mb-0.5 text-lg font-black tracking-tight text-gray-900'
                    : 'text-2xl font-black tracking-tight text-gray-900'
            "
        >
            {{ title }}
        </h2>
        <p v-if="description" class="mt-1 text-sm text-slate-500">
            {{ description }}
        </p>
    </header>
</template>
