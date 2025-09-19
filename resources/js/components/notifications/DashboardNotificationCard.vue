<script setup lang="ts">
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { router } from '@inertiajs/vue3';
import { Bell, UserPlus, AlertCircle, CheckCircle, Mail, Clock } from 'lucide-vue-next';
import { computed } from 'vue';

interface Notification {
  id: string;
  type: 'user_pending' | 'user_approved' | 'user_rejected' | 'general';
  title: string;
  message: string;
  data?: any;
  created_at: string;
  read_at?: string;
}

interface Props {
  notifications: Notification[];
  showAll?: boolean;
  maxDisplay?: number;
}

const props = withDefaults(defineProps<Props>(), {
  showAll: false,
  maxDisplay: 3,
});

const displayNotifications = computed(() => {
  if (props.showAll) return props.notifications;
  return props.notifications.slice(0, props.maxDisplay);
});

const unreadCount = computed(() => {
  return props.notifications.filter(n => !n.read_at).length;
});

const getIcon = (type: string) => {
  switch (type) {
    case 'user_pending':
      return UserPlus;
    case 'user_approved':
      return CheckCircle;
    case 'user_rejected':
      return AlertCircle;
    default:
      return Bell;
  }
};

const getIconColor = (type: string) => {
  switch (type) {
    case 'user_pending':
      return 'text-orange-600';
    case 'user_approved':
      return 'text-green-600';
    case 'user_rejected':
      return 'text-red-600';
    default:
      return 'text-blue-600';
  }
};

const getBadgeVariant = (type: string) => {
  switch (type) {
    case 'user_pending':
      return 'secondary';
    case 'user_approved':
      return 'default';
    case 'user_rejected':
      return 'destructive';
    default:
      return 'outline';
  }
};

const formatDate = (dateString: string) => {
  const date = new Date(dateString);
  const now = new Date();
  const diffInHours = (now.getTime() - date.getTime()) / (1000 * 60 * 60);

  if (diffInHours < 1) {
    const minutes = Math.floor(diffInHours * 60);
    return `${minutes}m ago`;
  } else if (diffInHours < 24) {
    return `${Math.floor(diffInHours)}h ago`;
  } else {
    const days = Math.floor(diffInHours / 24);
    return `${days}d ago`;
  }
};

const handleNotificationClick = (notification: Notification) => {
  if (notification.type === 'user_pending') {
    router.visit('/admin/users');
  }
  // Mark as read if not already
  if (!notification.read_at) {
    // You could make an API call here to mark as read
    // router.post(`/notifications/${notification.id}/mark-read`);
  }
};

const viewAllNotifications = () => {
  router.visit('/notifications');
};
</script>

<template>
  <Card class="w-full">
    <CardHeader class="pb-3">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="p-2 bg-blue-100 rounded-lg">
            <Bell class="h-5 w-5 text-blue-600" />
          </div>
          <div>
            <CardTitle class="text-lg">Notifications</CardTitle>
            <CardDescription>
              Recent activity and updates
            </CardDescription>
          </div>
        </div>
        <Badge v-if="unreadCount > 0" variant="destructive" class="min-w-[24px] h-6 text-xs">
          {{ unreadCount > 99 ? '99+' : unreadCount }}
        </Badge>
      </div>
    </CardHeader>

    <CardContent>
      <div v-if="notifications.length === 0" class="text-center py-8 text-muted-foreground">
        <Bell class="h-12 w-12 mx-auto mb-4 opacity-50" />
        <p class="text-lg font-medium">No notifications</p>
        <p class="text-sm">You're all caught up!</p>
      </div>

      <div v-else class="space-y-3">
        <div
          v-for="notification in displayNotifications"
          :key="notification.id"
          @click="handleNotificationClick(notification)"
          class="flex items-start gap-3 p-3 border rounded-lg hover:bg-muted/50 transition-colors cursor-pointer"
          :class="{ 'bg-blue-50/50 border-blue-200': !notification.read_at }"
        >
          <div class="p-1.5 rounded-full" :class="`bg-${getIconColor(notification.type).split('-')[1]}-100`">
            <component :is="getIcon(notification.type)" :class="`h-4 w-4 ${getIconColor(notification.type)}`" />
          </div>

          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 mb-1">
              <h4 class="font-medium text-sm truncate">{{ notification.title }}</h4>
              <Badge :variant="getBadgeVariant(notification.type)" class="text-xs">
                {{ notification.type.replace('_', ' ') }}
              </Badge>
            </div>

            <p class="text-sm text-muted-foreground line-clamp-2 mb-2">
              {{ notification.message }}
            </p>

            <div class="flex items-center gap-2 text-xs text-muted-foreground">
              <Clock class="h-3 w-3" />
              {{ formatDate(notification.created_at) }}
              <div v-if="!notification.read_at" class="w-2 h-2 bg-blue-600 rounded-full"></div>
            </div>
          </div>
        </div>

        <!-- Show more indicator -->
        <div v-if="!showAll && notifications.length > maxDisplay" class="text-center pt-3 border-t">
          <p class="text-sm text-muted-foreground mb-3">
            {{ notifications.length - maxDisplay }} more notification{{ notifications.length - maxDisplay !== 1 ? 's' : '' }}
          </p>
          <Button @click="viewAllNotifications" variant="outline" size="sm">
            <Bell class="h-4 w-4 mr-2" />
            View All Notifications
          </Button>
        </div>
      </div>
    </CardContent>
  </Card>
</template>

<style scoped>
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
