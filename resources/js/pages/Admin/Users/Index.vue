<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import NewUserPendingNotification from '@/components/notifications/NewUserPendingNotification.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Head, router } from '@inertiajs/vue3';
import { Users, UserCheck, UserX, Shield, User as UserIcon, Mail, Calendar, Settings } from 'lucide-vue-next';
import { computed } from 'vue';

interface User {
  id: number;
  name: string;
  email: string;
  pending_approval: boolean;
  approved_at: string | null;
  roles: string[];
  groups: string[];
  is_admin: boolean;
  created_at: string;
}

interface Organization {
  id: number;
  name: string;
}

interface Props {
  pendingUsers: User[];
  approvedUsers: User[];
  organization: Organization;
}

const props = defineProps<Props>();

const totalUsers = computed(() => props.pendingUsers.length + props.approvedUsers.length);

const approveUser = (userId: number) => {
  router.post(`/admin/users/${userId}/approve`, {}, {
    preserveScroll: true,
    onSuccess: () => {
      // Success message will be shown via session flash
    },
  });
};

const assignRole = (userId: number, roleId: number) => {
  router.post(`/admin/users/${userId}/assign-role`, {
    role_id: roleId,
  }, {
    preserveScroll: true,
  });
};

const removeRole = (userId: number, roleId: number) => {
  router.delete(`/admin/users/${userId}/remove-role`, {
    data: { role_id: roleId },
    preserveScroll: true,
  });
};

const addToGroup = (userId: number, groupId: number) => {
  router.post(`/admin/users/${userId}/add-to-group`, {
    group_id: groupId,
  }, {
    preserveScroll: true,
  });
};

const removeFromGroup = (userId: number, groupId: number) => {
  router.delete(`/admin/users/${userId}/remove-from-group`, {
    data: { group_id: groupId },
    preserveScroll: true,
  });
};
</script>

<template>
  <Head title="User Management" />

  <AppLayout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
      <!-- Header -->
      <div class="mb-8">
        <div class="flex items-center gap-3 mb-2">
          <div class="p-2 bg-blue-100 rounded-lg">
            <Users class="h-6 w-6 text-blue-600" />
          </div>
          <div>
            <Heading class="text-2xl font-bold">User Management</Heading>
            <p class="text-muted-foreground">Manage users in {{ organization.name }}</p>
          </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-6">
          <Card>
            <CardContent class="p-4">
              <div class="flex items-center gap-3">
                <UserIcon class="h-5 w-5 text-blue-600" />
                <div>
                  <p class="text-sm text-muted-foreground">Total Users</p>
                  <p class="text-2xl font-bold">{{ totalUsers }}</p>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardContent class="p-4">
              <div class="flex items-center gap-3">
                <UserCheck class="h-5 w-5 text-green-600" />
                <div>
                  <p class="text-sm text-muted-foreground">Approved</p>
                  <p class="text-2xl font-bold">{{ approvedUsers.length }}</p>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardContent class="p-4">
              <div class="flex items-center gap-3">
                <UserX class="h-5 w-5 text-orange-600" />
                <div>
                  <p class="text-sm text-muted-foreground">Pending</p>
                  <p class="text-2xl font-bold">{{ pendingUsers.length }}</p>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardContent class="p-4">
              <div class="flex items-center gap-3">
                <Shield class="h-5 w-5 text-purple-600" />
                <div>
                  <p class="text-sm text-muted-foreground">Admins</p>
                  <p class="text-2xl font-bold">{{ approvedUsers.filter(u => u.is_admin).length }}</p>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>

      <!-- Pending Users Notification Section -->
      <div v-if="pendingUsers.length > 0" class="mb-8">
        <NewUserPendingNotification
          :pending-users="pendingUsers.map(user => ({
            id: user.id,
            name: user.name,
            email: user.email,
            created_at: user.created_at,
            organization_name: organization.name,
            roles: user.roles
          }))"
          :organization-name="organization.name"
          :show-actions="true"
        />
      </div>

      <!-- Approved Users Section -->
      <Card>
        <CardHeader>
          <CardTitle class="flex items-center gap-2">
            <UserCheck class="h-5 w-5 text-green-600" />
            Approved Users
          </CardTitle>
          <CardDescription>
            Active members of {{ organization.name }}
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div class="space-y-4">
            <div
              v-for="user in approvedUsers"
              :key="user.id"
              class="flex items-center justify-between p-4 border rounded-lg hover:bg-muted/50 transition-colors"
            >
              <div class="flex items-center gap-4">
                <div class="p-2 bg-green-100 rounded-full">
                  <Shield v-if="user.is_admin" class="h-4 w-4 text-green-600" />
                  <UserIcon v-else class="h-4 w-4 text-green-600" />
                </div>
                <div class="flex-1">
                  <div class="flex items-center gap-2">
                    <h3 class="font-medium">{{ user.name }}</h3>
                    <Badge v-if="user.is_admin" variant="default" class="bg-purple-600">
                      Admin
                    </Badge>
                  </div>
                  <div class="flex items-center gap-2 text-sm text-muted-foreground">
                    <Mail class="h-3 w-3" />
                    {{ user.email }}
                  </div>
                  <div class="flex items-center gap-4 mt-2">
                    <div class="flex gap-1">
                      <span class="text-xs text-muted-foreground">Roles:</span>
                      <Badge v-for="role in user.roles" :key="role" variant="outline" class="text-xs">
                        {{ role }}
                      </Badge>
                    </div>
                    <div class="flex gap-1">
                      <span class="text-xs text-muted-foreground">Groups:</span>
                      <Badge v-for="group in user.groups" :key="group" variant="secondary" class="text-xs">
                        {{ group }}
                      </Badge>
                    </div>
                  </div>
                  <div v-if="user.approved_at" class="flex items-center gap-2 text-xs text-muted-foreground mt-1">
                    <Calendar class="h-3 w-3" />
                    Approved {{ user.approved_at }}
                  </div>
                </div>
              </div>
              <div class="flex gap-2">
                <Button variant="outline" size="sm">
                  <Settings class="h-4 w-4 mr-2" />
                  Manage
                </Button>
              </div>
            </div>

            <div v-if="approvedUsers.length === 0" class="text-center py-8 text-muted-foreground">
              <UserIcon class="h-12 w-12 mx-auto mb-4 opacity-50" />
              <p>No approved users yet.</p>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>
