<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Bomb,
    Brain,
    DollarSign,
    Globe,
    Info,
    Landmark,
    LayoutGrid,
    ListTree,
    Settings,
    Shield,
    Users,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import type { NavItem } from '@/types';

const dashboardUrl = computed(() => '/dashboard');
const ipoleksosbudkamBaseUrl = computed(() => '/ipoleksosbudkam');
const ipoleksosbudkamUrl = (path: string) => `/ipoleksosbudkam/${path}`;
const jibomUrl = (path?: string) => (path ? `/jibom${path}` : '/jibom');
const kbrnUrl = (path?: string) => (path ? `/kbrn${path}` : '/kbrn');
const wanTerorUrl = (path?: string) =>
    path ? `/wan-teror${path}` : '/wan-teror';
const worldMonitoringUrl = computed(() => '/world-monitoring');

const mainNavItems = computed<NavItem[]>(() => [
    {
        title: 'DASHBOARD',
        href: dashboardUrl.value,
        icon: LayoutGrid,
    },
    {
        title: 'IPOLEKSOSBUDKAM',
        href: ipoleksosbudkamBaseUrl.value,
        icon: ListTree,
        children: [
            {
                title: 'Ideologi',
                href: ipoleksosbudkamUrl('ideologi'),
                icon: Brain,
                children: [
                    {
                        title: 'Ideologi Kanan',
                        href: ipoleksosbudkamUrl('ideologi/ideologi-ideologi-kanan'),
                    },
                    {
                        title: 'Ideologi Kiri',
                        href: ipoleksosbudkamUrl('ideologi/ideologi-ideologi-kiri'),
                    },
                    {
                        title: 'Isu Menonjol',
                        href: ipoleksosbudkamUrl('ideologi/ideologi-isu-menonjol'),
                    },
                ],
            },
            {
                title: 'Politik',
                href: ipoleksosbudkamUrl('politik'),
                icon: Landmark,
                children: [
                    {
                        title: 'Dalam Negeri',
                        href: ipoleksosbudkamUrl('politik/politik-dalam-negeri'),
                    },
                    {
                        title: 'Luar Negeri',
                        href: ipoleksosbudkamUrl('politik/politik-luar-negeri'),
                    },
                    {
                        title: 'Isu Menonjol',
                        href: ipoleksosbudkamUrl('politik/politik-isu-menonjol'),
                    },
                ],
            },
            {
                title: 'Ekonomi',
                href: ipoleksosbudkamUrl('ekonomi'),
                icon: DollarSign,
                children: [
                    {
                        title: 'Export Import',
                        href: ipoleksosbudkamUrl('ekonomi/ekonomi-export-import'),
                    },
                    {
                        title: 'Harga Sembako',
                        href: ipoleksosbudkamUrl('ekonomi/ekonomi-harga-sembako'),
                    },
                    {
                        title: 'Kurs Mata Uang',
                        href: ipoleksosbudkamUrl('ekonomi/ekonomi-kurs-mata-uang'),
                    },
                    {
                        title: 'Pasar Saham',
                        href: ipoleksosbudkamUrl('ekonomi/ekonomi-pasar-saham'),
                    },
                    {
                        title: 'Index Pendapatan masyarakat',
                        href: ipoleksosbudkamUrl('ekonomi/ekonomi-index-pendapatan-masyarakat'),
                    },
                    {
                        title: 'Kesenjangan Sosial',
                        href: ipoleksosbudkamUrl('ekonomi/ekonomi-kesenjangan-sosial'),
                    },
                    {
                        title: 'Ekonomi Asing',
                        href: ipoleksosbudkamUrl('ekonomi/ekonomi-ekonomi-asing'),
                    },
                    {
                        title: 'Pro Kontra Proyek Strategis',
                        href: ipoleksosbudkamUrl('ekonomi/ekonomi-pro-kontra-proyek-strategis-nasional'),
                    },
                    {
                        title: 'Korupsi',
                        href: ipoleksosbudkamUrl('ekonomi/ekonomi-korupsi'),
                    },
                    {
                        title: 'Isu Menonjol',
                        href: ipoleksosbudkamUrl('ekonomi/ekonomi-isu-menonjol'),
                    },
                ],
            },
            {
                title: 'Sosial Budaya',
                href: ipoleksosbudkamUrl('sosial-budaya'),
                icon: Users,
                children: [
                    {
                        title: 'Ormas',
                        href: ipoleksosbudkamUrl('sosial-budaya/sosial-budaya-ormas'),
                    },
                    {
                        title: 'Bencana Alam',
                        href: ipoleksosbudkamUrl('sosial-budaya/sosial-budaya-bencana-alam'),
                    },
                    {
                        title: 'Unjuk rasa',
                        href: ipoleksosbudkamUrl('sosial-budaya/sosial-budaya-unjuk-rasa'),
                    },
                    {
                        title: 'Konflik sosial',
                        href: ipoleksosbudkamUrl('sosial-budaya/sosial-budaya-konflik-sosial'),
                    },
                    {
                        title: 'PHK',
                        href: ipoleksosbudkamUrl('sosial-budaya/sosial-budaya-phk'),
                    },
                    {
                        title: 'SARA',
                        href: ipoleksosbudkamUrl('sosial-budaya/sosial-budaya-sara'),
                    },
                    {
                        title: 'Isu Menonjol',
                        href: ipoleksosbudkamUrl('sosial-budaya/sosial-budaya-isu-menonjol'),
                    },
                ],
            },
            {
                title: 'Keamanan',
                href: ipoleksosbudkamUrl('keamanan'),
                icon: Shield,
                children: [
                    {
                        title: 'Teror',
                        href: ipoleksosbudkamUrl('keamanan/keamanan-teror'),
                    },
                    {
                        title: 'Keamanan Negara',
                        href: ipoleksosbudkamUrl('keamanan/keamanan-keamanan-negara'),
                    },
                    {
                        title: 'Isu Menonjol',
                        href: ipoleksosbudkamUrl('keamanan/keamanan-isu-menonjol'),
                    },
                ],
            },
        ],
    },
    {
        title: 'JIBOM',
        href: jibomUrl(),
        icon: Bomb,
        children: [
            {
                title: 'Ancaman Bom',
                href: jibomUrl('?type=ancaman'),
            },
            {
                title: 'Temuan Bom',
                href: jibomUrl('?type=temuan'),
            },
            {
                title: 'Ledakan Bom',
                href: jibomUrl('?type=ledakan'),
            },
        ],
    },
    {
        title: 'KBRN',
        href: kbrnUrl(),
        icon: Shield,
        children: [
            {
                title: 'Ancaman KBRN',
                href: kbrnUrl('?type=ancaman'),
            },
            {
                title: 'Temuan KBRN',
                href: kbrnUrl('?type=temuan'),
            },
            {
                title: 'Ledakan KBRN',
                href: kbrnUrl('?type=ledakan'),
            },
        ],
    },
    {
        title: 'WAN TEROR',
        href: wanTerorUrl(),
        icon: AlertTriangle,
        children: [
            {
                title: 'Data Napiter',
                href: wanTerorUrl('?type=napiter'),
            },
            {
                title: 'Data EX Napiter',
                href: wanTerorUrl('?type=ex-napiter'),
            },
            {
                title: 'Jaringan Terorisme',
                href: wanTerorUrl('?type=jaringan-terorisme'),
            },
            {
                title: 'Bullying/Perundungan',
                href: wanTerorUrl('?type=bullying-perundungan'),
            },
            {
                title: 'Aksi Teror',
                href: wanTerorUrl('?type=aksi-teror'),
            },
        ],
    },
    {
        title: 'WORLD MONITORING',
        href: worldMonitoringUrl.value,
        icon: Globe,
    },
    {
        title: 'AI AGENT LOGS',
        href: '/hermes-logs',
        icon: Brain,
    },
]);

const footerNavItems: NavItem[] = [
    {
        title: 'Pengaturan',
        href: '/settings/branding',
        icon: Settings,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <div class="relative flex h-full flex-col sidebar-hacker">
            <div class="pointer-events-none absolute inset-0 overflow-hidden">
                <div class="sb-fx-grid absolute inset-0" />
                <div class="sb-fx-scan absolute inset-0" />
                <div class="sb-fx-noise absolute inset-0" />
                <div class="sb-fx-edge absolute inset-0" />
            </div>

            <SidebarHeader class="relative z-10">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" as-child>
                            <Link :href="dashboardUrl">
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent class="relative z-10">
                <NavMain :items="mainNavItems" />
            </SidebarContent>

            <SidebarFooter class="relative z-10">
                <NavFooter :items="footerNavItems" />
                <NavUser />
            </SidebarFooter>
        </div>
    </Sidebar>
    <slot />
</template>

<style scoped>
.sidebar-hacker {
    isolation: isolate;
}

.sb-fx-grid {
    opacity: 0.18;
    background-image: linear-gradient(
            to bottom,
            rgba(171, 213, 229, 0.08) 1px,
            transparent 1px
        ),
        linear-gradient(
            to right,
            rgba(171, 213, 229, 0.05) 1px,
            transparent 1px
        );
    background-size: 100% 3px, 36px 100%;
}

.sb-fx-scan {
    opacity: 0.22;
    background: linear-gradient(
        to bottom,
        transparent 0%,
        rgba(171, 213, 229, 0.06) 45%,
        rgba(171, 213, 229, 0.12) 50%,
        rgba(171, 213, 229, 0.06) 55%,
        transparent 100%
    );
    transform: translateY(-120%);
    animation: sb-scan 7.2s linear infinite;
    mix-blend-mode: screen;
}

.sb-fx-noise {
    opacity: 0.12;
    background-image: radial-gradient(
            rgba(171, 213, 229, 0.12) 1px,
            transparent 1px
        ),
        radial-gradient(rgba(255, 255, 255, 0.06) 1px, transparent 1px);
    background-size: 3px 3px, 5px 5px;
    background-position: 0 0, 1px 2px;
    animation: sb-noise 1.8s steps(2, end) infinite;
    mix-blend-mode: overlay;
}

.sb-fx-edge {
    opacity: 0.9;
    background: radial-gradient(
        70% 60% at 20% 10%,
        rgba(171, 213, 229, 0.12) 0%,
        transparent 60%
    );
}

:deep([data-sidebar='menu-button']),
:deep([data-sidebar='menu-sub-button']) {
    position: relative;
    transition: box-shadow 200ms ease, border-color 200ms ease, background-color 200ms ease;
}

:deep([data-sidebar='menu-button']::before),
:deep([data-sidebar='menu-sub-button']::before) {
    content: '';
    position: absolute;
    inset: -1px;
    border-radius: 0.55rem;
    opacity: 0;
    background: linear-gradient(
        90deg,
        transparent 0%,
        rgba(171, 213, 229, 0.18) 45%,
        rgba(171, 213, 229, 0.28) 50%,
        rgba(171, 213, 229, 0.18) 55%,
        transparent 100%
    );
    transform: translateX(-35%);
    filter: blur(0.2px);
    pointer-events: none;
}

:deep([data-sidebar='menu-button']:hover::before),
:deep([data-sidebar='menu-sub-button']:hover::before) {
    opacity: 0.65;
    animation: sb-sweep 900ms ease-out forwards;
}

:deep([data-sidebar='menu-button'][data-active='true']),
:deep([data-sidebar='menu-sub-button'][data-active='true']) {
    box-shadow: 0 0 0 1px rgba(171, 213, 229, 0.12),
        0 0 26px rgba(171, 213, 229, 0.06);
    animation: sb-glow 3.6s ease-in-out infinite;
}

:deep([data-sidebar='menu-button'][data-active='true'] > a),
:deep([data-sidebar='menu-sub-button'][data-active='true'] > a) {
    text-shadow: 0 0 12px rgba(171, 213, 229, 0.18);
}

@keyframes sb-scan {
    0% {
        transform: translateY(-120%);
    }
    100% {
        transform: translateY(120%);
    }
}

@keyframes sb-noise {
    0% {
        transform: translate3d(0, 0, 0);
    }
    25% {
        transform: translate3d(-1px, 1px, 0);
    }
    50% {
        transform: translate3d(1px, -1px, 0);
    }
    75% {
        transform: translate3d(-1px, -1px, 0);
    }
    100% {
        transform: translate3d(0, 0, 0);
    }
}

@keyframes sb-sweep {
    from {
        transform: translateX(-35%);
    }
    to {
        transform: translateX(35%);
    }
}

@keyframes sb-glow {
    0%,
    100% {
        box-shadow: 0 0 0 1px rgba(171, 213, 229, 0.12),
            0 0 26px rgba(171, 213, 229, 0.06);
    }
    50% {
        box-shadow: 0 0 0 1px rgba(171, 213, 229, 0.18),
            0 0 38px rgba(171, 213, 229, 0.09);
    }
}

@media (prefers-reduced-motion: reduce) {
    .sb-fx-scan,
    .sb-fx-noise,
    :deep([data-sidebar='menu-button'][data-active='true']),
    :deep([data-sidebar='menu-sub-button'][data-active='true']),
    :deep([data-sidebar='menu-button']:hover::before),
    :deep([data-sidebar='menu-sub-button']:hover::before) {
        animation: none !important;
    }
}
</style>
