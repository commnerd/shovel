<script setup lang="ts">
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { router } from '@inertiajs/vue3';
import { Building2, Mail, Users, AlertTriangle, CheckCircle, XCircle, Info } from 'lucide-vue-next';
import { computed } from 'vue';

interface Organization {
  id: number;
  name: string;
  domain: string;
  creator_name?: string;
  user_count?: number;
  created_at?: string;
}

interface Props {
  userEmail: string;
  existingOrganization: Organization;
  userName: string;
}

const props = defineProps<Props>();

const emailDomain = computed(() => {
  return props.userEmail.split('@')[1];
});

const joinOrganization = () => {
  router.post('/registration/confirm-organization', {
    join_organization: true,
  });
};

const registerIndividually = () => {
  router.post('/registration/confirm-organization', {
    join_organization: false,
  });
};

const formatDate = (dateString?: string) => {
  if (!dateString) return 'Unknown';
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  });
};
</script>

<template>
  <div class="max-w-2xl mx-auto p-6">
    <Card class="w-full">
      <CardHeader class="pb-6">
        <div class="flex items-center gap-3 mb-4">
          <div class="p-3 bg-blue-100 rounded-lg">
            <Building2 class="h-6 w-6 text-blue-600" />
          </div>
          <div>
            <CardTitle class="text-xl">Organization Found</CardTitle>
            <CardDescription class="text-base">
              We found an organization that matches your email domain
            </CardDescription>
          </div>
        </div>

        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
          <div class="flex items-start gap-3">
            <Info class="h-5 w-5 text-blue-600 mt-0.5 flex-shrink-0" />
            <div>
              <p class="font-medium text-blue-900">Email Domain Match Detected</p>
              <p class="text-sm text-blue-700 mt-1">
                Your email address <strong>{{ userEmail }}</strong> uses the domain
                <strong>{{ emailDomain }}</strong>, which matches an existing organization in our system.
              </p>
            </div>
          </div>
        </div>
      </CardHeader>

      <CardContent class="space-y-6">
        <!-- Organization Details -->
        <div class="space-y-4">
          <h3 class="font-semibold text-lg flex items-center gap-2">
            <Building2 class="h-5 w-5" />
            Organization Details
          </h3>

          <div class="bg-gray-50 rounded-lg p-4 space-y-3">
            <div class="flex items-center justify-between">
              <span class="font-medium">{{ existingOrganization.name }}</span>
              <Badge variant="secondary">{{ emailDomain }}</Badge>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-muted-foreground">
              <div class="flex items-center gap-2">
                <Users class="h-4 w-4" />
                <span>{{ existingOrganization.user_count || 'Unknown' }} member{{ (existingOrganization.user_count || 0) !== 1 ? 's' : '' }}</span>
              </div>
              <div class="flex items-center gap-2">
                <Mail class="h-4 w-4" />
                <span>@{{ existingOrganization.domain }}</span>
              </div>
            </div>

            <div v-if="existingOrganization.creator_name" class="text-sm text-muted-foreground">
              <strong>Organization Admin:</strong> {{ existingOrganization.creator_name }}
            </div>

            <div v-if="existingOrganization.created_at" class="text-sm text-muted-foreground">
              <strong>Established:</strong> {{ formatDate(existingOrganization.created_at) }}
            </div>
          </div>
        </div>

        <Separator />

        <!-- Decision Section -->
        <div class="space-y-4">
          <h3 class="font-semibold text-lg">What would you like to do?</h3>
          <p class="text-muted-foreground">
            Since your email domain matches this organization, you have two options:
          </p>

          <div class="space-y-4">
            <!-- Join Organization Option -->
            <div class="border rounded-lg p-4 hover:bg-green-50 hover:border-green-200 transition-colors">
              <div class="flex items-start gap-3 mb-3">
                <CheckCircle class="h-5 w-5 text-green-600 mt-0.5" />
                <div class="flex-1">
                  <h4 class="font-medium text-green-900">Join {{ existingOrganization.name }}</h4>
                  <p class="text-sm text-green-700 mt-1">
                    Request to join this organization. Your account will be pending approval from an organization administrator.
                  </p>
                </div>
              </div>

              <div class="bg-green-50 border border-green-200 rounded p-3 mb-3">
                <div class="flex items-start gap-2">
                  <AlertTriangle class="h-4 w-4 text-green-600 mt-0.5 flex-shrink-0" />
                  <div class="text-xs text-green-700">
                    <p><strong>What happens next:</strong></p>
                    <ul class="list-disc list-inside mt-1 space-y-1">
                      <li>Your account will be created with pending approval status</li>
                      <li>Organization administrators will be notified of your request</li>
                      <li>You'll need to wait for approval before accessing organization features</li>
                      <li>You'll be added to the organization's default group once approved</li>
                    </ul>
                  </div>
                </div>
              </div>

              <Button @click="joinOrganization" class="w-full bg-green-600 hover:bg-green-700">
                <Building2 class="h-4 w-4 mr-2" />
                Join {{ existingOrganization.name }}
              </Button>
            </div>

            <!-- Register Individually Option -->
            <div class="border rounded-lg p-4 hover:bg-gray-50 hover:border-gray-300 transition-colors">
              <div class="flex items-start gap-3 mb-3">
                <XCircle class="h-5 w-5 text-gray-600 mt-0.5" />
                <div class="flex-1">
                  <h4 class="font-medium text-gray-900">Register as Individual</h4>
                  <p class="text-sm text-gray-700 mt-1">
                    Create your account independently, not associated with any organization.
                  </p>
                </div>
              </div>

              <div class="bg-gray-50 border border-gray-200 rounded p-3 mb-3">
                <div class="flex items-start gap-2">
                  <Info class="h-4 w-4 text-gray-600 mt-0.5 flex-shrink-0" />
                  <div class="text-xs text-gray-700">
                    <p><strong>What happens next:</strong></p>
                    <ul class="list-disc list-inside mt-1 space-y-1">
                      <li>Your account will be created immediately</li>
                      <li>You'll be assigned to the default "None" organization</li>
                      <li>You can create and manage your own projects independently</li>
                      <li>You can join organizations later if needed</li>
                    </ul>
                  </div>
                </div>
              </div>

              <Button @click="registerIndividually" variant="outline" class="w-full">
                <Users class="h-4 w-4 mr-2" />
                Register Individually
              </Button>
            </div>
          </div>
        </div>

        <Separator />

        <!-- Additional Information -->
        <div class="text-xs text-muted-foreground space-y-2">
          <p>
            <strong>Privacy Notice:</strong> Your email domain is used only to identify potential
            organization matches. Your personal information is not shared without your consent.
          </p>
          <p>
            <strong>Need Help?</strong> If you're unsure about your organization membership,
            you can always register individually and request to join an organization later.
          </p>
        </div>
      </CardContent>
    </Card>
  </div>
</template>
