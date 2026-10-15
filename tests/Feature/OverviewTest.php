<?php

namespace Tests\Feature;

use App\Models\User;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OverviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_overview_shows_real_empty_state(): void
    {
        $this->get('/')->assertOk()->assertSee('Belum ada prospek')->assertSee('Belum ada aktivitas tercatat.');
    }
}
