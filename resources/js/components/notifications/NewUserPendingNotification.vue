<script setup lang="ts">
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { router } from '@inertiajs/vue3';
import { UserPlus, Mail, Clock, CheckCircle, XCircle, Building2 } from 'lucide-vue-next';
import { computed } from 'vue';

interface PendingUser {
  id: number;
  name: string;
  email: string;
  created_at: string;
  organization_name: string;
  roles?: string[];
}

interface Props {
  pendingUsers: PendingUser[];
  organizationName: string;
  showActions?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  showActions: true,
});

const userInitials = (name: string) => {
  return name.split(' ').map(n => n[0]).join('').toUpperCase();
};

const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
};

const approveUser = (userId: number) => {
  router.post(`/admin/users/${userId}/approve`, {}, {
    preserveScroll: true,
    onSuccess: () => {
      // Success feedback will be handled by the parent component
    },
  });
};

const rejectUser = (userId: number) => {
  // For now, we'll just show a confirmation dialog
  if (confirm('Are you sure you want to reject this user? This action cannot be undone.')) {
    router.delete(`/admin/users/${userId}`, {
      preserveScroll: true,
    });
  }
};

const viewAllPending = () => {
  router.visit('/admin/users');
};
</script>

<template>
  <Card class="w-full">
    <CardHeader class="pb-3">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="p-2 bg-orange-100 rounded-lg">
            <UserPlus class="h-5 w-5 text-orange-600" />
          </div>
          <div>
            <CardTitle class="text-lg">Pending User Approvals</CardTitle>
            <CardDescription>
              {{ pendingUsers.length }} user{{ pendingUsers.length !== 1 ? 's' : '' }} waiting for approval in {{ organizationName }}
            </CardDescription>
          </div>
        </div>
        <Badge variant="secondary" class="bg-orange-100 text-orange-800">
          {{ pendingUsers.length }} Pending
        </Badge>
      </div>
    </CardHeader>

    <CardContent>
      <div v-if="pendingUsers.length === 0" class="text-center py-8 text-muted-foreground">
        <CheckCircle class="h-12 w-12 mx-auto mb-4 opacity-50" />
        <p class="text-lg font-medium">All caught up!</p>
        <p class="text-sm">No users are currently waiting for approval.</p>
      </div>

      <div v-else class="space-y-4">
        <div
          v-for="user in pendingUsers.slice(0, 3)"
          :key="user.id"
          class="flex items-center justify-between p-4 border rounded-lg hover:bg-muted/50 transition-colors"
        >
          <div class="flex items-center gap-4">
            <Avatar class="h-10 w-10">
              <AvatarFallback class="bg-blue-100 text-blue-700 font-medium">
                {{ userInitials(user.name) }}
              </AvatarFallback>
            </Avatar>

            <div class="flex-1">
              <div class="flex items-center gap-2 mb-1">
                <h4 class="font-medium">{{ user.name }}</h4>
                <Badge v-if="user.roles && user.roles.length > 0" variant="outline" class="text-xs">
                  {{ user.roles.join(', ') }}
                </Badge>
              </div>

              <div class="flex items-center gap-4 text-sm text-muted-foreground">
                <div class="flex items-center gap-1">
                  <Mail class="h-3 w-3" />
                  {{ user.email }}
                </div>
                <div class="flex items-center gap-1">
                  <Clock class="h-3 w-3" />
                  {{ formatDate(user.created_at) }}
                </div>
              </div>
            </div>
          </div>

          <div v-if="showActions" class="flex gap-2">
            <Button
              @click="rejectUser(user.id)"
              variant="outline"
              size="sm"
              class="text-red-600 hover:text-red-700 hover:bg-red-50"
            >
              <XCircle class="h-4 w-4 mr-1" />
              Reject
            </Button>
            <Button @click="approveUser(user.id)" size="sm">
              <CheckCircle class="h-4 w-4 mr-1" />
              Approve
            </Button>
          </div>
        </div>

        <!-- Show more indicator if there are more than 3 users -->
        <div v-if="pendingUsers.length > 3" class="text-center pt-4 border-t">
          <p class="text-sm text-muted-foreground mb-3">
            And {{ pendingUsers.length - 3 }} more user{{ pendingUsers.length - 3 !== 1 ? 's' : '' }} waiting for approval
          </p>
          <Button @click="viewAllPending" variant="outline" size="sm">
            <Building2 class="h-4 w-4 mr-2" />
            View All Pending Users
          </Button>
        </div>

        <!-- Quick action buttons -->
        <div v-if="showActions && pendingUsers.length > 0" class="flex gap-2 pt-4 border-t">
          <Button @click="viewAllPending" variant="outline" class="flex-1">
            Manage All Users
          </Button>
        </div>
      </div>
    </CardContent>
  </Card>
</template>
