<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronDown } from 'lucide-vue-next';
import { computed, ref, watchEffect } from 'vue';
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
import Collapsible from '@/components/ui/collapsible/Collapsible.vue';
import CollapsibleContent from '@/components/ui/collapsible/CollapsibleContent.vue';
import CollapsibleTrigger from '@/components/ui/collapsible/CollapsibleTrigger.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavItem } from '@/types';

const props = defineProps<{
    items: NavItem[];
}>();

const { currentUrl, isCurrentUrl } = useCurrentUrl();

const openRootTitle = ref<string | null>(null);
const openChildTitle = ref<string | null>(null);

const rootItems = computed(() => props.items);

watchEffect(() => {
    const activeRoot = rootItems.value.find((item) => item.children?.length && isCurrentUrl(item.href, undefined, true)) ?? null;
    if (activeRoot?.title) {
        openRootTitle.value = activeRoot.title;
    }

    const root = rootItems.value.find((i) => i.title === openRootTitle.value) ?? activeRoot;
    const activeChild =
        root?.children?.find((child) => child.children?.length && isCurrentUrl(child.href, undefined, true)) ?? null;

    if (activeChild?.title) {
        openChildTitle.value = activeChild.title;
    } else if (root?.title !== activeRoot?.title) {
        openChildTitle.value = null;
    }
    void currentUrl.value;
});
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel>Platform</SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <template v-if="item.children?.length">
                    <Collapsible
                        :open="openRootTitle === item.title"
                        @update:open="
                            (value) => {
                                openRootTitle = value ? item.title : null;
                                if (!value) openChildTitle = null;
                            }
                        "
                    >
                        <CollapsibleTrigger as-child>
                            <SidebarMenuButton
                                as-child
                                :is-active="isCurrentUrl(item.href, undefined, true)"
                                :tooltip="item.title"
                            >
                                <Link :href="item.href">
                                    <component v-if="item.icon" :is="item.icon" />
                                    <span>{{ item.title }}</span>
                                    <ChevronDown
                                        class="ml-auto size-4 opacity-70 transition-transform"
                                        :class="{ 'rotate-180': openRootTitle === item.title }"
                                    />
                                </Link>
                            </SidebarMenuButton>
                        </CollapsibleTrigger>

                        <CollapsibleContent>
                            <SidebarMenuSub>
                                <SidebarMenuSubItem v-for="child in item.children" :key="child.title">
                                    <template v-if="child.children?.length">
                                        <Collapsible
                                            :open="openChildTitle === child.title"
                                            @update:open="
                                                (value) => {
                                                    openRootTitle = item.title;
                                                    openChildTitle = value ? child.title : null;
                                                }
                                            "
                                        >
                                            <CollapsibleTrigger as-child>
                                                <SidebarMenuSubButton
                                                    as-child
                                                    size="md"
                                                    :is-active="isCurrentUrl(child.href, undefined, true)"
                                                >
                                                    <Link :href="child.href">
                                                        <component v-if="child.icon" :is="child.icon" />
                                                        <span>{{ child.title }}</span>
                                                        <ChevronDown
                                                            class="ml-auto size-4 opacity-70 transition-transform"
                                                            :class="{ 'rotate-180': openChildTitle === child.title }"
                                                        />
                                                    </Link>
                                                </SidebarMenuSubButton>
                                            </CollapsibleTrigger>

                                            <CollapsibleContent>
                                                <SidebarMenuSub class="ml-2">
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
                                            </CollapsibleContent>
                                        </Collapsible>
                                    </template>

                                    <SidebarMenuSubButton
                                        v-else
                                        as-child
                                        size="md"
                                        :is-active="isCurrentUrl(child.href, undefined, true)"
                                    >
                                        <Link :href="child.href">
                                            <component v-if="child.icon" :is="child.icon" />
                                            <span>{{ child.title }}</span>
                                        </Link>
                                    </SidebarMenuSubButton>
                                </SidebarMenuSubItem>
                            </SidebarMenuSub>
                        </CollapsibleContent>
                    </Collapsible>
                </template>

                <template v-else>
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
                </template>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
