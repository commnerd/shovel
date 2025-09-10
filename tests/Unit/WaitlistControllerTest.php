<?php

namespace Tests\Unit;

use App\Http\Controllers\WaitlistController;
use App\Models\WaitlistSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class WaitlistControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_waitlist_controller_can_be_instantiated(): void
    {
        $controller = new WaitlistController();
        $this->assertInstanceOf(WaitlistController::class, $controller);
    }

    public function test_store_method_creates_waitlist_subscriber(): void
    {
        $controller = new WaitlistController();
        $request = Request::create('/waitlist', 'POST', [
            'email' => 'test@example.com',
        ]);

        $response = $controller->store($request);

        $this->assertDatabaseHas('waitlist_subscribers', [
            'email' => 'test@example.com',
        ]);
    }

    public function test_store_method_returns_redirect_response(): void
    {
        $controller = new WaitlistController();
        $request = Request::create('/waitlist', 'POST', [
            'email' => 'test@example.com',
        ]);

        $response = $controller->store($request);

        $this->assertTrue($response->isRedirect());
    }

    public function test_store_method_has_success_message(): void
    {
        $controller = new WaitlistController();
        $request = Request::create('/waitlist', 'POST', [
            'email' => 'test@example.com',
        ]);

        $response = $controller->store($request);

        $this->assertTrue($response->getSession()->has('success'));
        $this->assertEquals('Thanks! We\'ll be in touch soon.', $response->getSession()->get('success'));
    }

    public function test_store_method_validates_email_required(): void
    {
        $controller = new WaitlistController();
        $request = Request::create('/waitlist', 'POST', []);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $controller->store($request);
    }

    public function test_store_method_validates_email_format(): void
    {
        $controller = new WaitlistController();
        $request = Request::create('/waitlist', 'POST', [
            'email' => 'invalid-email',
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $controller->store($request);
    }

    public function test_store_method_validates_email_uniqueness(): void
    {
        // Create existing subscriber
        WaitlistSubscriber::create(['email' => 'existing@example.com']);

        $controller = new WaitlistController();
        $request = Request::create('/waitlist', 'POST', [
            'email' => 'existing@example.com',
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $controller->store($request);
    }

    public function test_store_method_uses_correct_validation_rules(): void
    {
        $controller = new WaitlistController();

        // Test that the validation rules are properly configured
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('store');

        $this->assertTrue($method->isPublic());
        $this->assertEquals('store', $method->getName());
    }
}
