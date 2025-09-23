<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem, SidebarGroup, SidebarGroupContent, SidebarGroupLabel } from '@/components/ui/sidebar';
// Removed Wayfinder import - using direct route names
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { LayoutGrid, FolderOpen, Tag, Shield, Users, Settings, Crown, Mail, CheckSquare } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';
import packageJson from '../../../package.json';
import { computed } from 'vue';

const page = usePage();
const user = computed(() => page.props.auth.user);

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
        icon: LayoutGrid,
    },
    {
        title: "Today's Tasks",
        href: '/dashboard/todays-tasks',
        icon: CheckSquare,
    },
    {
        title: 'Projects',
        href: '/dashboard/projects',
        icon: FolderOpen,
    },
];

const adminNavItems: NavItem[] = [
    {
        title: 'User Management',
        href: '/admin/users',
        icon: Users,
    },
    {
        title: 'User Invitations',
        href: '/admin/invitations',
        icon: Mail,
    },
];

const superAdminNavItems: NavItem[] = [
    {
        title: 'Super Admin',
        href: '/super-admin',
        icon: Crown,
    },
];


const footerNavItems: NavItem[] = [
    // Documentation link removed per user request
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link href="/dashboard">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />

        <!-- Super Admin Section -->
        <SidebarGroup v-if="user?.is_super_admin">
            <SidebarGroupLabel class="flex items-center gap-2">
                <Crown class="h-4 w-4" />
                Super Administration
            </SidebarGroupLabel>
            <SidebarGroupContent>
                <NavMain :items="superAdminNavItems" />
            </SidebarGroupContent>
        </SidebarGroup>

        <!-- Organization Admin Section -->
        <SidebarGroup v-if="user?.is_admin && !user?.is_super_admin">
            <SidebarGroupLabel class="flex items-center gap-2">
                <Shield class="h-4 w-4" />
                Administration
            </SidebarGroupLabel>
            <SidebarGroupContent>
                <NavMain :items="adminNavItems" />
            </SidebarGroupContent>
        </SidebarGroup>

        </SidebarContent>

        <SidebarFooter>
            <!-- Version Display -->
            <SidebarGroup class="group-data-[collapsible=icon]:p-0">
                <SidebarGroupContent>
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton class="text-neutral-600 dark:text-neutral-300 cursor-default hover:bg-transparent">
                                <Tag />
                                <span>Version: {{ packageJson.version }}</span>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroupContent>
            </SidebarGroup>

            <!-- Settings Link -->
            <SidebarGroup class="group-data-[collapsible=icon]:p-0">
                <SidebarGroupContent>
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton as-child>
                                <Link href="/settings/system" class="flex items-center gap-2">
                                    <Settings />
                                    <span>Settings</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroupContent>
            </SidebarGroup>

            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
