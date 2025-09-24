<?php

namespace Tests\Feature;

use Tests\TestCase;

class SimpleCacheBustingTest extends TestCase
{
    public function test_it_adds_cache_busting_headers_to_responses()
    {
        $response = $this->get('/');

        // Check for deployment headers added by AddDeploymentHeaders middleware
        $response->assertHeader('X-Deployment-Version');
        $response->assertHeader('X-Deployment-Timestamp');
        
        // Check for cache control headers for HTML responses
        $response->assertHeader('Cache-Control');
        $response->assertHeader('Pragma', 'no-cache');
        $response->assertHeader('Expires', '0');
    }
}
