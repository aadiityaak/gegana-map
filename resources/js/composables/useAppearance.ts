import type { ComputedRef, Ref } from 'vue';
import { computed, onMounted, ref } from 'vue';
import type { Appearance, ResolvedAppearance } from '@/types';

export type { Appearance, ResolvedAppearance };

export type UseAppearanceReturn = {
    appearance: Ref<Appearance>;
    resolvedAppearance: ComputedRef<ResolvedAppearance>;
    updateAppearance: (value: Appearance) => void;
};

export function updateTheme(value: Appearance): void {
    if (typeof window === 'undefined') {
        return;
    }

    if (value === 'system') {
        const mediaQueryList = window.matchMedia(
            '(prefers-color-scheme: dark)',
        );
        const systemTheme = mediaQueryList.matches ? 'dark' : 'light';

        document.documentElement.classList.toggle(
            'dark',
            systemTheme === 'dark',
        );
    } else {
        document.documentElement.classList.toggle('dark', value === 'dark');
    }
}

const setCookie = (name: string, value: string, days = 365) => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;

    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const prefersDark = (): boolean => {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches;
};

export function initializeTheme(): void {
    if (typeof window === 'undefined') {
        return;
    }

    const value: Appearance = 'dark';
    updateTheme(value);
    localStorage.setItem('appearance', value);
    setCookie('appearance', value);
}

const appearance = ref<Appearance>('dark');

export function useAppearance(): UseAppearanceReturn {
    onMounted(() => {
        appearance.value = 'dark';
        localStorage.setItem('appearance', 'dark');
        setCookie('appearance', 'dark');
        updateTheme('dark');
    });

    const resolvedAppearance = computed<ResolvedAppearance>(() => {
        if (appearance.value === 'system') {
            return prefersDark() ? 'dark' : 'light';
        }

        return appearance.value;
    });

    function updateAppearance(_: Appearance) {
        appearance.value = 'dark';
        localStorage.setItem('appearance', 'dark');
        setCookie('appearance', 'dark');
        updateTheme('dark');
    }

    return {
        appearance,
        resolvedAppearance,
        updateAppearance,
    };
}
