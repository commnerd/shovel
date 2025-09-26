<template>
    <AppLayout title="Subscription Management">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Subscription Management
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Success/Error Messages -->
                <div v-if="$page.props.flash?.success" class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                    {{ $page.props.flash.success }}
                </div>

                <div v-if="$page.props.flash?.error" class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                    {{ $page.props.flash.error }}
                </div>

                <!-- Current Subscription Status -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-8">
                    <div class="p-6 text-gray-900">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Current Subscription</h3>

                        <div v-if="hasActiveSubscription" class="flex items-center justify-between">
                            <div>
                                <p class="text-lg font-medium text-green-600">
                                    {{ subscriptionStatus }}
                                </p>
                                <p class="text-sm text-gray-600">
                                    Next billing: {{ nextBillingDate }}
                                </p>
                            </div>
                            <div class="space-x-2">
                                <button v-if="isOnGracePeriod"
                                        @click="resumeSubscription"
                                        class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
                                    Resume Subscription
                                </button>
                                <button @click="cancelSubscription"
                                        class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">
                                    Cancel Subscription
                                </button>
                            </div>
                        </div>

                        <div v-else class="text-center py-8">
                            <p class="text-gray-600 mb-4">You don't have an active subscription.</p>
                            <button @click="showPricing = true"
                                    class="bg-blue-600 text-white px-6 py-3 rounded hover:bg-blue-700">
                                Choose a Plan
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Billing History -->
                <div v-if="invoices.length > 0" class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Billing History</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Date
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Amount
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Status
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="invoice in invoices" :key="invoice.id">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ formatDate(invoice.date) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ invoice.total }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                                  :class="invoice.paid ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">
                                                {{ invoice.paid ? 'Paid' : 'Unpaid' }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <a :href="`/subscriptions/invoice/${invoice.id}`"
                                               class="text-blue-600 hover:text-blue-900">
                                                Download
                                            </a>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Pricing Plans Modal -->
                <div v-if="showPricing" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
                    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                        <div class="mt-3">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Choose a Plan</h3>

                            <div class="space-y-4">
                                <div class="border rounded-lg p-4">
                                    <h4 class="font-semibold">Basic Plan</h4>
                                    <p class="text-2xl font-bold">$9.99/month</p>
                                    <p class="text-sm text-gray-600">Perfect for individuals</p>
                                    <button @click="subscribe('price_basic')"
                                            class="mt-2 w-full bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                                        Subscribe
                                    </button>
                                </div>

                                <div class="border rounded-lg p-4">
                                    <h4 class="font-semibold">Pro Plan</h4>
                                    <p class="text-2xl font-bold">$19.99/month</p>
                                    <p class="text-sm text-gray-600">Great for teams</p>
                                    <button @click="subscribe('price_pro')"
                                            class="mt-2 w-full bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                                        Subscribe
                                    </button>
                                </div>
                            </div>

                            <div class="mt-4 flex justify-end">
                                <button @click="showPricing = false"
                                        class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400">
                                    Cancel
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'

const props = defineProps({
    user: Object,
    subscriptions: Array,
    invoices: Array,
})

const showPricing = ref(false)

const hasActiveSubscription = computed(() => {
    return props.subscriptions && props.subscriptions.length > 0
})

const subscriptionStatus = computed(() => {
    if (hasActiveSubscription.value) {
        return props.subscriptions[0].stripe_status || 'Active'
    }
    return 'No subscription'
})

const nextBillingDate = computed(() => {
    if (hasActiveSubscription.value && props.subscriptions[0].asStripeSubscription) {
        const subscription = props.subscriptions[0].asStripeSubscription()
        if (subscription.current_period_end) {
            return new Date(subscription.current_period_end * 1000).toLocaleDateString()
        }
    }
    return 'N/A'
})

const isOnGracePeriod = computed(() => {
    return hasActiveSubscription.value && props.subscriptions[0].onGracePeriod
})

const formatDate = (date) => {
    return new Date(date).toLocaleDateString()
}

const subscribe = (priceId) => {
    // This would typically redirect to Stripe Checkout or handle payment method setup
    router.post('/subscriptions', {
        price_id: priceId,
        payment_method: 'pm_card_visa' // This would be set up properly with Stripe Elements
    })
}

const resumeSubscription = () => {
    router.post('/subscriptions/resume')
}

const cancelSubscription = () => {
    if (confirm('Are you sure you want to cancel your subscription?')) {
        router.delete('/subscriptions')
    }
}
</script>
