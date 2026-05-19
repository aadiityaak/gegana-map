<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BookOpen,
    Brain,
    DollarSign,
    FolderGit2,
    Landmark,
    LayoutGrid,
    ListTree,
    Shield,
    Users,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import TeamSwitcher from '@/components/TeamSwitcher.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const page = usePage();

const dashboardUrl = computed(() =>
    page.props.currentTeam ? dashboard(page.props.currentTeam.slug).url : '/',
);

const ipoleksosbudkamBaseUrl = computed(() =>
    page.props.currentTeam ? `/${page.props.currentTeam.slug}/ipoleksosbudkam` : '/',
);

const ipoleksosbudkamUrl = (path: string) =>
    page.props.currentTeam ? `/${page.props.currentTeam.slug}/ipoleksosbudkam/${path}` : '/';

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
]);

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#vue',
        icon: BookOpen,
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
                    <TeamSwitcher />
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
