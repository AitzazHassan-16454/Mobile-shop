<script setup lang="ts">
import { defineAsyncComponent } from 'vue';

const ProductsIndex = defineAsyncComponent(
    () => import('@/pages/Products/Index.vue'),
);
</script>

<template>
    <ProductsIndex v-bind="$props" />
</template>
