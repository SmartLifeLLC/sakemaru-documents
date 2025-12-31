<?php

namespace Tests\Feature;

use Tests\TestCase;

class LoginTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function test_login_page_renders(): void
    {
        $response = $this->get('/admin/login');

        $response->assertStatus(200);
        $response->assertSee('id="intro-video"', false); // Check for video element
        $response->assertSee('ログイン'); // Check for Japanese login button text
    }
}
