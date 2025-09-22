<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\{User, Project};
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProjectAIConfigurationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->project = Project::factory()->create(['user_id' => $this->user->id]);
    }

    public function test_project_edit_form_includes_ai_configuration_options()
    {
        // Mock AI providers as configured
        \App\Services\AI\Facades\AI::shouldReceive('getAvailableProviders')
            ->andReturn([
                'cerebrus' => [
                    'name' => 'Cerebras',
                    'configured' => true,
                    'config' => ['api_key' => 'test-key'],
                ],
                'openai' => [
                    'name' => 'OpenAI',
                    'configured' => true,
                    'config' => ['api_key' => 'test-key'],
                ],
                'anthropic' => [
                    'name' => 'Anthropic',
                    'configured' => true,
                    'config' => ['api_key' => 'test-key'],
                ],
            ]);

        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/edit");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Projects/Edit')
                 ->has('project')
                 ->has('availableProviders')
                 ->has('availableProviders.cerebrus')
                 ->has('availableProviders.openai')
                 ->has('availableProviders.anthropic')
                 ->has('availableProviders.cerebrus.models')
                 ->has('availableProviders.openai.models')
                 ->has('availableProviders.anthropic.models')
        );
    }

    public function test_can_update_project_ai_configuration()
    {
        $response = $this->actingAs($this->user)
            ->put("/dashboard/projects/{$this->project->id}", [
                'title' => 'Updated Project',
                'description' => 'Updated description',
                'due_date' => '2025-12-31',
                'status' => 'active',
                'ai_provider' => 'openai',
                'ai_model' => 'gpt-4',
            ]);

        $response->assertRedirect('/dashboard/projects');
        $response->assertSessionHas('message');

        // Verify database was updated
        $this->project->refresh();
        $this->assertEquals('openai', $this->project->ai_provider);
        $this->assertEquals('gpt-4', $this->project->ai_model);
    }

    public function test_can_clear_project_ai_configuration()
    {
        // First set some AI configuration
        $this->project->update([
            'ai_provider' => 'cerebrus',
            'ai_model' => 'llama3.1-8b',
        ]);

        // Then clear it
        $response = $this->actingAs($this->user)
            ->put("/dashboard/projects/{$this->project->id}", [
                'title' => $this->project->title,
                'description' => $this->project->description,
                'due_date' => $this->project->due_date?->format('Y-m-d'),
                'status' => $this->project->status,
                'ai_provider' => '',
                'ai_model' => '',
            ]);

        $response->assertRedirect('/dashboard/projects');

        // Verify database was updated
        $this->project->refresh();
        $this->assertNull($this->project->ai_provider);
        $this->assertNull($this->project->ai_model);
    }

    public function test_project_ai_configuration_validation()
    {
        // Test invalid provider
        $response = $this->actingAs($this->user)
            ->put("/dashboard/projects/{$this->project->id}", [
                'title' => $this->project->title,
                'description' => $this->project->description,
                'ai_provider' => 'invalid_provider',
                'ai_model' => 'some-model',
            ]);

        $response->assertSessionHasErrors('ai_provider');

        // Test model too long
        $response = $this->actingAs($this->user)
            ->put("/dashboard/projects/{$this->project->id}", [
                'title' => $this->project->title,
                'description' => $this->project->description,
                'ai_provider' => 'openai',
                'ai_model' => str_repeat('a', 101), // Too long
            ]);

        $response->assertSessionHasErrors('ai_model');
    }

    public function test_project_edit_form_shows_current_ai_configuration()
    {
        // Set AI configuration on the project
        $this->project->update([
            'ai_provider' => 'anthropic',
            'ai_model' => 'claude-3-sonnet-20240229',
        ]);

        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/edit");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Projects/Edit')
                 ->where('project.ai_provider', 'anthropic')
                 ->where('project.ai_model', 'claude-3-sonnet-20240229')
        );
    }

    public function test_project_edit_form_shows_null_ai_configuration_when_not_set()
    {
        // Ensure project has no AI configuration
        $this->project->update([
            'ai_provider' => null,
            'ai_model' => null,
        ]);

        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/edit");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Projects/Edit')
                 ->where('project.ai_provider', null)
                 ->where('project.ai_model', null)
        );
    }

    public function test_unauthorized_user_cannot_edit_project_ai_configuration()
    {
        $otherUser = User::factory()->create();

        $response = $this->actingAs($otherUser)
            ->put("/dashboard/projects/{$this->project->id}", [
                'title' => $this->project->title,
                'description' => $this->project->description,
                'ai_provider' => 'openai',
                'ai_model' => 'gpt-4',
            ]);

        $response->assertStatus(403);

        // Verify project AI configuration wasn't changed
        $this->project->refresh();
        $this->assertNotEquals('openai', $this->project->ai_provider);
        $this->assertNotEquals('gpt-4', $this->project->ai_model);
    }

    public function test_available_providers_include_all_expected_providers()
    {
        // Mock AI providers as configured
        \App\Services\AI\Facades\AI::shouldReceive('getAvailableProviders')
            ->andReturn([
                'cerebrus' => [
                    'name' => 'Cerebras',
                    'configured' => true,
                    'config' => ['api_key' => 'test-key'],
                ],
                'openai' => [
                    'name' => 'OpenAI',
                    'configured' => true,
                    'config' => ['api_key' => 'test-key'],
                ],
                'anthropic' => [
                    'name' => 'Anthropic',
                    'configured' => true,
                    'config' => ['api_key' => 'test-key'],
                ],
            ]);

        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/edit");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->has('availableProviders.cerebrus')
                 ->has('availableProviders.openai')
                 ->has('availableProviders.anthropic')
                 ->where('availableProviders.cerebrus.name', 'Cerebras')
                 ->where('availableProviders.openai.name', 'OpenAI')
                 ->where('availableProviders.anthropic.name', 'Anthropic')
        );
    }

    public function test_available_models_are_included_for_each_provider()
    {
        // Mock AI providers as configured
        \App\Services\AI\Facades\AI::shouldReceive('getAvailableProviders')
            ->andReturn([
                'cerebrus' => [
                    'name' => 'Cerebras',
                    'configured' => true,
                    'config' => ['api_key' => 'test-key'],
                ],
                'openai' => [
                    'name' => 'OpenAI',
                    'configured' => true,
                    'config' => ['api_key' => 'test-key'],
                ],
                'anthropic' => [
                    'name' => 'Anthropic',
                    'configured' => true,
                    'config' => ['api_key' => 'test-key'],
                ],
            ]);

        $response = $this->actingAs($this->user)
            ->get("/dashboard/projects/{$this->project->id}/edit");

        $response->assertStatus(200);

        // Debug the actual structure
        $props = $response->getOriginalContent()->getData()['page']['props'];
        $this->assertArrayHasKey('availableProviders', $props);
        $this->assertArrayHasKey('cerebrus', $props['availableProviders']);
        $this->assertArrayHasKey('models', $props['availableProviders']['cerebrus']);

        // Check that the models exist with correct keys
        $cerebrusModels = $props['availableProviders']['cerebrus']['models'];
        $this->assertArrayHasKey('llama3.1-8b', $cerebrusModels);
        $this->assertEquals('Llama 3.1 8B', $cerebrusModels['llama3.1-8b']);

        $openaiModels = $props['availableProviders']['openai']['models'];
        $this->assertArrayHasKey('gpt-4', $openaiModels);
        $this->assertEquals('GPT-4', $openaiModels['gpt-4']);

        $anthropicModels = $props['availableProviders']['anthropic']['models'];
        $this->assertArrayHasKey('claude-3-sonnet-20240229', $anthropicModels);
        $this->assertEquals('Claude 3 Sonnet', $anthropicModels['claude-3-sonnet-20240229']);
    }

    public function test_project_ai_configuration_is_optional()
    {
        // Test that project can be updated without AI configuration
        $response = $this->actingAs($this->user)
            ->put("/dashboard/projects/{$this->project->id}", [
                'title' => 'Updated Project',
                'description' => 'Updated description',
                'status' => 'active',
                // No AI configuration provided
            ]);

        $response->assertRedirect('/dashboard/projects');
        $response->assertSessionHas('message');

        // Should work fine without AI config
        $this->project->refresh();
        $this->assertEquals('Updated Project', $this->project->title);
    }
}
