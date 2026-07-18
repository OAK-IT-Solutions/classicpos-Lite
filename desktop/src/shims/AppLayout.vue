/**
 * AppLayout Shim for Desktop
 *
 * The backend's AppLayout.vue provides a full sidebar layout.
 * In the desktop app, our own AppLayout (in desktop/src/layouts/) provides the sidebar.
 * This shim replaces the backend's AppLayout with a simple pass-through slot.
 *
 * This prevents double-layout issues when backend pages wrap themselves in AppLayout.
 */
export default {
  template: '<slot />',
};
