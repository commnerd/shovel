# Laravel Cashier Setup

This document outlines the Laravel Cashier setup for Stripe payment processing.

## Installation

Laravel Cashier has been installed and configured with the following components:

### 1. Package Installation
- `laravel/cashier` package installed via Composer
- Database migrations published and run
- User model updated with `Billable` trait

### 2. Database Schema
The following tables are available:
- `users` - Extended with Cashier columns (`stripe_id`, `pm_type`, `pm_last_four`, `trial_ends_at`)
- `subscriptions` - Stores subscription data
- `subscription_items` - Stores subscription item details

### 3. Configuration
Environment variables added to `.env`:
```env
# Stripe Configuration
STRIPE_KEY=pk_test_your_publishable_key_here
STRIPE_SECRET=sk_test_your_secret_key_here
STRIPE_WEBHOOK_SECRET=whsec_your_webhook_secret_here

# Cashier Configuration
CASHIER_CURRENCY=usd
CASHIER_CURRENCY_LOCALE=en
CASHIER_PATH=stripe
```

## Features Implemented

### 1. Subscription Management
- **Controller**: `SubscriptionController` with full CRUD operations
- **Routes**: RESTful routes for subscription management
- **Views**: Vue/Inertia components for subscription interface

### 2. Available Routes
```
GET    /subscriptions              - View subscription management
POST   /subscriptions              - Create new subscription
PUT    /subscriptions              - Update existing subscription
DELETE /subscriptions              - Cancel subscription
POST   /subscriptions/resume       - Resume cancelled subscription
GET    /subscriptions/invoice/{id} - Download invoice
POST   /stripe/webhook            - Stripe webhook handler
```

### 3. User Model Integration
The `User` model now includes:
- `Billable` trait for subscription management
- Cashier columns in `$fillable` array
- Methods for subscription and invoice management

### 4. Webhook Handling
- `WebhookController` extends Cashier's webhook controller
- Handles subscription updates, payments, and cancellations
- Logs webhook events for debugging

## Usage Examples

### Creating a Subscription
```php
$user = User::find(1);
$user->newSubscription('default', 'price_1234567890')
    ->create($paymentMethod);
```

### Checking Subscription Status
```php
if ($user->subscribed('default')) {
    // User has an active subscription
}

if ($user->subscription('default')->onGracePeriod()) {
    // Subscription is cancelled but still active
}
```

### Managing Invoices
```php
$invoices = $user->invoices();
$user->downloadInvoice($invoiceId);
```

## Frontend Integration

### Vue Components
- `Subscriptions/Index.vue` - Main subscription management interface
- Displays current subscription status
- Shows billing history
- Provides subscription management actions

### Features
- Real-time subscription status display
- Billing history table
- Subscription cancellation/resumption
- Invoice download functionality
- Pricing plan selection modal

## Testing

### Verification Commands
```bash
# Test Cashier installation
sail artisan tinker --execute="
\$user = \App\Models\User::first();
echo 'Has Billable trait: ' . (method_exists(\$user, 'subscriptions') ? 'Yes' : 'No');
"

# Check subscription routes
sail artisan route:list | grep subscription
```

## Next Steps

1. **Configure Stripe Keys**: Update `.env` with your actual Stripe API keys
2. **Set up Webhooks**: Configure Stripe webhooks to point to `/stripe/webhook`
3. **Create Products**: Set up products and prices in Stripe Dashboard
4. **Test Payments**: Use Stripe test cards to verify payment flow
5. **Customize UI**: Modify the Vue components to match your design

## Security Notes

- Webhook endpoint should be protected with Stripe webhook signature verification
- Never expose secret keys in frontend code
- Use HTTPS in production
- Validate webhook signatures to prevent unauthorized access

## Support

For more information, refer to:
- [Laravel Cashier Documentation](https://laravel.com/docs/cashier)
- [Stripe API Documentation](https://stripe.com/docs/api)
- [Stripe Webhooks Guide](https://stripe.com/docs/webhooks)
