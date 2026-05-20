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

function initializeCursorGradient(): void {
    if (typeof window === 'undefined') return;

    const reducedMotion = window.matchMedia?.(
        '(prefers-reduced-motion: reduce)',
    ).matches;
    if (reducedMotion) return;

    const root = document.documentElement;

    let rafId: number | null = null;
    let nextX = window.innerWidth * 0.5;
    let nextY = window.innerHeight * 0.3;

    const commit = () => {
        rafId = null;
        root.style.setProperty('--cursor-x', `${Math.round(nextX)}px`);
        root.style.setProperty('--cursor-y', `${Math.round(nextY)}px`);
    };

    const schedule = () => {
        if (rafId != null) return;
        rafId = window.requestAnimationFrame(commit);
    };

    const setFromClient = (x: number, y: number) => {
        nextX = x;
        nextY = y;
        schedule();
    };

    const onPointerMove = (event: PointerEvent) => {
        setFromClient(event.clientX, event.clientY);
    };

    const onTouchMove = (event: TouchEvent) => {
        const t = event.touches?.[0];
        if (!t) return;
        setFromClient(t.clientX, t.clientY);
    };

    const onPointerLeave = () => {
        setFromClient(window.innerWidth * 0.5, window.innerHeight * 0.3);
    };

    commit();
    window.addEventListener('pointermove', onPointerMove, { passive: true });
    window.addEventListener('touchmove', onTouchMove, { passive: true });
    window.addEventListener('pointerleave', onPointerLeave, { passive: true });
}

initializeCursorGradient();

function applyBrandingFavicon(): void {
    if (typeof window === 'undefined') return;
    const defaultFaviconUrl = '/branding/gegana-fav.png';
    const storedFavicon = localStorage.getItem('branding.faviconDataUrl');
    const resolvedFaviconUrl =
        storedFavicon && storedFavicon.trim()
            ? storedFavicon
            : defaultFaviconUrl;

    const links = Array.from(
        document.querySelectorAll<HTMLLinkElement>('link[rel="icon"]'),
    );
    if (links.length === 0) {
        const link = document.createElement('link');
        link.rel = 'icon';
        link.type = 'image/png';
        link.href = resolvedFaviconUrl;
        document.head.appendChild(link);
    }
    for (const link of links) {
        link.href = resolvedFaviconUrl;
    }

    const appleTouchIcon = document.querySelector<HTMLLinkElement>(
        'link[rel="apple-touch-icon"]',
    );
    if (appleTouchIcon) {
        appleTouchIcon.href = resolvedFaviconUrl;
    }
}

applyBrandingFavicon();
window.addEventListener('branding:update', applyBrandingFavicon);

// This will listen for flash toast data from the server...
initializeFlashToast();
