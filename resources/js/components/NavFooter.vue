<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronDown } from 'lucide-vue-next';
import { ref } from 'vue';
import {
    SidebarGroup,
    SidebarGroupContent,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { toUrl } from '@/lib/utils';
import type { NavItem } from '@/types';

type Props = {
    items: NavItem[];
    class?: string;
};

defineProps<Props>();

const openTitle = ref<string | null>(null);

const isExternal = (href: NavItem['href']) => {
    const url = toUrl(href);
    return /^https?:\/\//i.test(url);
};
</script>

<template>
    <SidebarGroup
        :class="`group-data-[collapsible=icon]:p-0 ${$props.class || ''}`"
    >
        <SidebarGroupContent>
            <SidebarMenu>
                <SidebarMenuItem v-for="item in items" :key="item.title">
                    <template v-if="item.children?.length">
                        <SidebarMenuButton
                            class="text-neutral-600 hover:text-neutral-800 dark:text-neutral-300 dark:hover:text-neutral-100"
                            @click="
                                () => {
                                    openTitle = openTitle === item.title ? null : item.title;
                                }
                            "
                        >
                            <component :is="item.icon" />
                            <span>{{ item.title }}</span>
                            <ChevronDown
                                class="ml-auto size-4 opacity-70 transition-transform"
                                :class="{ 'rotate-180': openTitle === item.title }"
                            />
                        </SidebarMenuButton>

                        <SidebarMenuSub v-if="openTitle === item.title" class="ml-2">
                            <SidebarMenuSubItem
                                v-for="child in item.children"
                                :key="child.title"
                            >
                                <SidebarMenuSubButton as-child size="sm">
                                    <Link :href="child.href">
                                        <span>{{ child.title }}</span>
                                    </Link>
                                </SidebarMenuSubButton>
                            </SidebarMenuSubItem>
                        </SidebarMenuSub>
                    </template>

                    <template v-else>
                        <SidebarMenuButton
                            class="text-neutral-600 hover:text-neutral-800 dark:text-neutral-300 dark:hover:text-neutral-100"
                            as-child
                        >
                            <a
                                v-if="isExternal(item.href)"
                                :href="toUrl(item.href)"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <component :is="item.icon" />
                                <span>{{ item.title }}</span>
                            </a>
                            <Link v-else :href="item.href">
                                <component :is="item.icon" />
                                <span>{{ item.title }}</span>
                            </Link>
                        </SidebarMenuButton>
                    </template>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarGroupContent>
    </SidebarGroup>
</template>
