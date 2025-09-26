@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-900 mb-8">Subscription Management</h1>

        @if (session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                {{ session('error') }}
            </div>
        @endif

        <!-- Current Subscription Status -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Current Subscription</h2>

            @if($user->subscribed('default'))
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-lg font-medium text-green-600">
                            {{ $user->subscription('default')->stripe_status }}
                        </p>
                        <p class="text-sm text-gray-600">
                            Next billing: {{ $user->subscription('default')->asStripeSubscription()->current_period_end ?
                                \Carbon\Carbon::createFromTimestamp($user->subscription('default')->asStripeSubscription()->current_period_end)->format('M d, Y') :
                                'N/A' }}
                        </p>
                    </div>
                    <div class="space-x-2">
                        @if($user->subscription('default')->onGracePeriod())
                            <form action="{{ route('subscriptions.resume') }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
                                    Resume Subscription
                                </button>
                            </form>
                        @endif
                        <form action="{{ route('subscriptions.destroy') }}" method="POST" class="inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700"
                                    onclick="return confirm('Are you sure you want to cancel your subscription?')">
                                Cancel Subscription
                            </button>
                        </form>
                    </div>
                </div>
            @else
                <div class="text-center py-8">
                    <p class="text-gray-600 mb-4">You don't have an active subscription.</p>
                    <a href="#" class="bg-blue-600 text-white px-6 py-3 rounded hover:bg-blue-700">
                        Choose a Plan
                    </a>
                </div>
            @endif
        </div>

        <!-- Billing History -->
        @if($invoices->count() > 0)
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Billing History</h2>
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
                        @foreach($invoices as $invoice)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $invoice->date()->format('M d, Y') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $invoice->total() }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                    {{ $invoice->paid ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $invoice->paid ? 'Paid' : 'Unpaid' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <a href="{{ route('subscriptions.invoice', $invoice->id) }}"
                                   class="text-blue-600 hover:text-blue-900">
                                    Download
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
