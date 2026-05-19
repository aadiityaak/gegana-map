<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavItem } from '@/types';

defineProps<{
    items: NavItem[];
}>();

const { isCurrentUrl } = useCurrentUrl();
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel>Platform</SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <SidebarMenuButton
                    as-child
                    :is-active="isCurrentUrl(item.href, undefined, true)"
                    :tooltip="item.title"
                >
                    <Link :href="item.href">
                        <component v-if="item.icon" :is="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>

                <SidebarMenuSub v-if="item.children?.length">
                    <SidebarMenuSubItem v-for="child in item.children" :key="child.title">
                        <SidebarMenuSubButton
                            as-child
                            size="md"
                            :is-active="isCurrentUrl(child.href, undefined, true)"
                        >
                            <Link :href="child.href">
                                <component v-if="child.icon" :is="child.icon" />
                                <span>{{ child.title }}</span>
                            </Link>
                        </SidebarMenuSubButton>

                        <SidebarMenuSub v-if="child.children?.length" class="ml-2">
                            <SidebarMenuSubItem
                                v-for="grandchild in child.children"
                                :key="grandchild.title"
                            >
                                <SidebarMenuSubButton
                                    as-child
                                    size="sm"
                                    :is-active="isCurrentUrl(grandchild.href, undefined, true)"
                                >
                                    <Link :href="grandchild.href">
                                        <span>{{ grandchild.title }}</span>
                                    </Link>
                                </SidebarMenuSubButton>
                            </SidebarMenuSubItem>
                        </SidebarMenuSub>
                    </SidebarMenuSubItem>
                </SidebarMenuSub>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
