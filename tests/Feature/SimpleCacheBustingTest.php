<?php

namespace Tests\Feature;

use Tests\TestCase;

class SimpleCacheBustingTest extends TestCase
{
    public function test_it_adds_cache_busting_headers_to_responses()
    {
        $response = $this->get('/');

        $response->assertHeader('X-Cache-Bust-Timestamp');
        $response->assertHeader('X-Cache-Bust-Version');
        $response->assertHeader('X-Cache-Bust-Random');
        $response->assertHeader('Cache-Control', 'max-age=0, must-revalidate, no-cache, no-store, private');
        $response->assertHeader('Pragma', 'no-cache');
        $response->assertHeader('Expires', '0');
    }
}
