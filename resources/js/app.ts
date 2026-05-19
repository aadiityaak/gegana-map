import { createInertiaApp } from '@inertiajs/vue3';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';
import 'leaflet/dist/leaflet.css';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'Welcome':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
            case name.startsWith('teams/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

function applyBrandingFavicon(): void {
    if (typeof window === 'undefined') return;
    const faviconDataUrl = localStorage.getItem('branding.faviconDataUrl');
    if (!faviconDataUrl) return;

    const links = Array.from(
        document.querySelectorAll<HTMLLinkElement>('link[rel="icon"]'),
    );
    for (const link of links) {
        link.href = faviconDataUrl;
    }
}

applyBrandingFavicon();
window.addEventListener('branding:update', applyBrandingFavicon);

// This will listen for flash toast data from the server...
initializeFlashToast();
