<template>
  <CustomersShowPage :id="route.params.id" />
</template>

<script setup lang="ts">
import { useRoute } from 'vue-router';
import CustomersShowPage from '@/Pages/Customers/Show.vue';
const route = useRoute();
</script>
