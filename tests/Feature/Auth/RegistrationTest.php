<?php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertStatus(200);
});

test('new users can register', function () {
    // Ensure default organization exists
    $this->artisan('db:seed', ['--class' => 'OrganizationSeeder']);

    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'organization_email' => false,
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
    
    // Verify user was assigned to default organization
    $user = \App\Models\User::where('email', 'test@example.com')->first();
    $defaultOrg = \App\Models\Organization::getDefault();
    $this->assertEquals($defaultOrg->id, $user->organization_id);
    $this->assertFalse($user->pending_approval);
});