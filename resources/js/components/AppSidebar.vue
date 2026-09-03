<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    CircleDot,
    LayoutDashboard,
    Settings2,
    Trophy,
    Users,
    UsersRound,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
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
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const page = usePage();

const venueSlug = computed(() => {
    return page.url.match(/^\/venues\/([^/]+)/)?.[1] ?? null;
});

const dashboardUrl = computed(() => {
    return venueSlug.value
        ? `/venues/${venueSlug.value}/dashboard`
        : dashboard().url;
});

const mainNavItems = computed<NavItem[]>(() => {
    if (!venueSlug.value) {
        return [
            {
                title: 'Početna',
                href: dashboard(),
                icon: LayoutDashboard,
            },
            {
                title: 'Igrači',
                href: '/admin/players',
                icon: Users,
            },
        ];
    }

    const venueBase = `/venues/${venueSlug.value}`;

    return [
        {
            title: 'Početna',
            href: `${venueBase}/dashboard`,
            icon: LayoutDashboard,
        },
        {
            title: 'Turniri',
            href: `${venueBase}/tournaments`,
            icon: Trophy,
        },
        {
            title: 'Igrači',
            href: '/admin/players',
            icon: Users,
        },
        {
            title: 'Timovi',
            href: `${venueBase}/teams`,
            icon: UsersRound,
        },
        {
            title: 'Oprema',
            href: `${venueBase}/resources`,
            icon: CircleDot,
        },
        {
            title: 'Podešavanja lokala',
            href: `${venueBase}/settings`,
            icon: Settings2,
        },
    ];
});
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
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
