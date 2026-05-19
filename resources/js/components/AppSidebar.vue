<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Bomb,
    Brain,
    DollarSign,
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
const kwrnUrl = (path?: string) => (path ? `/kwrn${path}` : '/kwrn');
const wanTerorUrl = (path?: string) =>
    path ? `/wan-teror${path}` : '/wan-teror';

const mainNavItems = computed<NavItem[]>(() => [
    {
        title: 'Dashboard',
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
                title: 'Ancaman Pengeboman',
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
        title: 'KWRN',
        href: kwrnUrl(),
        icon: Shield,
        children: [
            {
                title: 'Ancaman KWRN',
                href: kwrnUrl('?type=ancaman'),
            },
            {
                title: 'Temuan KWRN',
                href: kwrnUrl('?type=temuan'),
            },
            {
                title: 'Ledakan KWRN',
                href: kwrnUrl('?type=ledakan'),
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
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboardUrl">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" class="justify-start px-2">
                        <Users class="size-4 shrink-0" />
                        <span class="truncate font-semibold">ADMIN</span>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
