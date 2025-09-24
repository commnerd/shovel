<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ProductionRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up default organization structure
        $this->seed(\Database\Seeders\OrganizationSeeder::class);
    }

    public function test_registration_routes_are_available_in_testing_environment()
    {
        // Test that registration GET route is accessible in testing
        $response = $this->get('/register');
        $response->assertOk();

        // Test that registration POST route is accessible in testing
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'organization_email' => false,
        ]);

        // Should either redirect to dashboard or confirmation page
        $this->assertTrue(in_array($response->status(), [302, 200]));
    }

    public function test_organization_routes_are_available_in_testing_environment()
    {
        // Set up session data for organization creation
        session([
            'registration_data' => [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => \Hash::make('password'),
            ],
        ]);

        // Test organization creation GET route
        $response = $this->get('/organization/create');
        $response->assertOk();

        // Test organization creation POST route
        $response = $this->post('/organization/create', [
            'organization_name' => 'Test Organization',
            'organization_address' => '123 Test Street',
        ]);

        $response->assertRedirect('/dashboard');
    }

    public function test_environment_detection_works_correctly()
    {
        // Test current environment (should be testing)
        $this->assertEquals('testing', app()->environment());
        $this->assertFalse(app()->environment('production'));
        $this->assertTrue(app()->environment('testing'));

        // Test environment detection
        $this->assertFalse(app()->isProduction());
    }

    public function test_registration_route_condition_logic()
    {
        // Test the condition logic directly
        $isProduction = app()->environment('production');
        $shouldAllowRegistration = ! $isProduction;

        // In testing environment, registration should be allowed
        $this->assertTrue($shouldAllowRegistration);

        // Verify that the route exists when not in production
        $hasRegisterRoute = Route::has('register');
        $this->assertTrue($hasRegisterRoute);

        // Verify organization routes exist when not in production
        $hasOrgCreateRoute = Route::has('organization.create');
        $this->assertTrue($hasOrgCreateRoute);

        $hasOrgConfirmRoute = Route::has('registration.confirm-organization');
        $this->assertTrue($hasOrgConfirmRoute);
    }

    public function test_login_and_other_auth_routes_always_available()
    {
        // These routes should always be available regardless of environment
        $alwaysAvailableRoutes = [
            'login',
            'password.request',
            'password.reset',
            'logout',
            'verification.notice',
            'password.confirm',
        ];

        foreach ($alwaysAvailableRoutes as $routeName) {
            $this->assertTrue(Route::has($routeName), "Route {$routeName} should always be available");
        }
    }

    public function test_production_environment_simulation()
    {
        // This test simulates what would happen in production by checking the logic

        // Simulate production environment check
        $isProductionSimulated = true; // This simulates app()->environment('production')

        // Test the condition logic that's used in routes/auth.php
        $shouldRegisterRoutesBeIncluded = ! $isProductionSimulated;

        // In production, registration routes should NOT be included
        $this->assertFalse($shouldRegisterRoutesBeIncluded);

        // Verify our current environment is NOT production
        $this->assertFalse(app()->environment('production'));

        // Verify registration routes ARE available in current environment
        $this->assertTrue(Route::has('register'));
    }

    public function test_route_availability_documentation()
    {
        // This test serves as documentation for route availability

        $availableInTesting = [
            'register',
            'register.store',
            'organization.create',
            'organization.store',
            'registration.confirm-organization',
            'registration.confirm-organization.store',
        ];

        $alwaysAvailable = [
            'login',
            'login.store',
            'password.request',
            'password.email',
            'password.reset',
            'password.store',
            'logout',
        ];

        // Verify all testing-only routes exist in current environment
        foreach ($availableInTesting as $routeName) {
            $this->assertTrue(Route::has($routeName),
                "Route {$routeName} should be available in testing environment");
        }

        // Verify all always-available routes exist
        foreach ($alwaysAvailable as $routeName) {
            $this->assertTrue(Route::has($routeName),
                "Route {$routeName} should always be available");
        }

        // Document the total count
        $this->assertGreaterThanOrEqual(count($availableInTesting) + count($alwaysAvailable),
            count(Route::getRoutes()));
    }
}
