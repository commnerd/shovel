<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Cashier\Exceptions\IncompletePayment;

class SubscriptionController extends Controller
{
    /**
     * Display the subscription management page.
     */
    public function index(): Response
    {
        $user = auth()->user();

        return Inertia::render('Subscriptions/Index', [
            'user' => $user,
            'subscriptions' => $user->subscriptions,
            'invoices' => $user->invoices(),
        ]);
    }

    /**
     * Create a new subscription.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = auth()->user();

        $request->validate([
            'payment_method' => 'required|string',
            'price_id' => 'required|string',
        ]);

        try {
            $user->newSubscription('default', $request->price_id)
                ->create($request->payment_method);

            return redirect()->route('subscriptions.index')
                ->with('success', 'Subscription created successfully!');

        } catch (IncompletePayment $exception) {
            return redirect()->route('cashier.payment', [
                $exception->payment->id,
                'redirect' => route('subscriptions.index')
            ]);
        }
    }

    /**
     * Update the subscription.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = auth()->user();
        $subscription = $user->subscription('default');

        $request->validate([
            'price_id' => 'required|string',
        ]);

        $subscription->swap($request->price_id);

        return redirect()->route('subscriptions.index')
            ->with('success', 'Subscription updated successfully!');
    }

    /**
     * Cancel the subscription.
     */
    public function destroy(): RedirectResponse
    {
        $user = auth()->user();
        $subscription = $user->subscription('default');

        $subscription->cancel();

        return redirect()->route('subscriptions.index')
            ->with('success', 'Subscription cancelled successfully!');
    }

    /**
     * Resume the subscription.
     */
    public function resume(): RedirectResponse
    {
        $user = auth()->user();
        $subscription = $user->subscription('default');

        $subscription->resume();

        return redirect()->route('subscriptions.index')
            ->with('success', 'Subscription resumed successfully!');
    }

    /**
     * Download an invoice.
     */
    public function downloadInvoice(string $id): mixed
    {
        $user = auth()->user();

        return $user->downloadInvoice($id, [
            'vendor' => config('app.name'),
            'product' => 'Subscription',
        ]);
    }
}
