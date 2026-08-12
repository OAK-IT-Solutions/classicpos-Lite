<template>
  <SalesShowPage :id="route.params.id" />
</template>

<script setup lang="ts">
import { useRoute } from 'vue-router';
import SalesShowPage from '@/Pages/Sales/Show.vue';
const route = useRoute();
</script>
