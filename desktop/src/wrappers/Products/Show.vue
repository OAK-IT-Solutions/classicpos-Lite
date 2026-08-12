<template>
  <ProductsShowPage :id="route.params.id" />
</template>

<script setup lang="ts">
import { useRoute } from 'vue-router';
import ProductsShowPage from '@/Pages/Products/Show.vue';
const route = useRoute();
</script>
