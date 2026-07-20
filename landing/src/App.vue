<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import Navbar from '@/components/Navbar.vue'
import Hero from '@/components/Hero.vue'
import Screenshots from '@/components/Screenshots.vue'
import Features from '@/components/Features.vue'
import CtaBanner from '@/components/CtaBanner.vue'
import HowItWorks from '@/components/HowItWorks.vue'
import Pricing from '@/components/Pricing.vue'
import DesktopPricing from '@/components/DesktopPricing.vue'
import SelfHost from '@/components/SelfHost.vue'
import Testimonials from '@/components/Testimonials.vue'
import Faq from '@/components/FAQ.vue'
import Roadmap from '@/components/Roadmap.vue'
import Footer from '@/components/Footer.vue'
import { useReferral } from '@/composables/useReferral'

useReferral()
const route = useRoute()
const isLanding = computed(() => route.name === 'home')
</script>

<template>
  <div class="min-h-screen">
    <!-- Landing page: full marketing site with sections in order.
         Non-landing routes (privacy, terms, agent auth) render inline
         without the navbar/hero/footer wrapper. -->
    <template v-if="isLanding">
      <a
        href="#main-content"
        class="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-[100] focus:bg-blue-600 focus:text-white focus:px-4 focus:py-2 focus:rounded-lg focus:shadow-lg focus:outline-none"
      >
        Skip to main content
      </a>
      <Navbar />
      <main id="main-content" tabindex="-1">
        <Hero />
        <Screenshots />
        <Features />
        <CtaBanner />
        <HowItWorks />
        <Pricing />
        <DesktopPricing />
        <CtaBanner variant="selfhost" />
        <SelfHost />
        <Testimonials />
        <Faq />
        <Roadmap />
      </main>
      <Footer />
    </template>
    <template v-else>
      <router-view />
    </template>
  </div>
</template>
