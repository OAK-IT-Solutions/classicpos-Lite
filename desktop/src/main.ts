import { createApp } from 'vue';
import PrimeVue from 'primevue/config';
import Aura from '@primevue/themes/aura';
import ToastService from 'primevue/toastservice';
import ConfirmationService from 'primevue/confirmationservice';
import App from './App.vue';
import router from './router';
import 'primeicons/primeicons.css';

const app = createApp(App);

app.use(router);
app.use(PrimeVue, {
  theme: {
    preset: Aura,
    options: {
      prefix: 'p',
      darkModeSelector: '.dark-mode',
    },
  },
});
app.use(ToastService);
app.use(ConfirmationService);

// Expose vue-router globally for the Inertia shim
if (typeof window !== 'undefined') {
  (window as any).__VUE_ROUTER__ = router;
}

app.mount('#app');
