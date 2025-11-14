<?php

use App\Models\Organization;
use App\Models\WhiteLabelConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

it('enforces rate limits for guests', function () {
    $org = Organization::factory()->create(['whitelabel_public_access' => true]);

    WhiteLabelConfig::factory()->create([
        'organization_id' => $org->id,
        'theme_config' => [
            'primary_color' => '#ff0000',
        ],
    ]);

    // Clear rate limiter
    RateLimiter::clear('branding');

    // Make requests up to the limit
    for ($i = 0; $i < 30; $i++) {
        $response = $this->get("/branding/{$org->slug}/styles.css");
        expect($response->status())->toBe(200);
    }

    // 31st request should be rate limited
    $response = $this->get("/branding/{$org->slug}/styles.css");
    $response->assertStatus(429)
        ->assertSee('Rate limit exceeded', false)
        ->assertHeader('Content-Type', 'text/css; charset=UTF-8');
});

it('allows higher rate limits for authenticated users', function () {
    $user = \App\Models\User::factory()->create();
    
    // Create a team for the user (required by Coolify)
    $team = \App\Models\Team::factory()->create();
    $user->teams()->attach($team->id, ['role' => 'admin']);
    
    $org = Organization::factory()->create(['whitelabel_public_access' => true]);

    $org->users()->attach($user->id, ['role' => 'member']);
    
    // Set current organization for the user
    $user->update(['current_organization_id' => $org->id]);

    WhiteLabelConfig::factory()->create([
        'organization_id' => $org->id,
        'theme_config' => [
            'primary_color' => '#ff0000',
        ],
    ]);

    // Clear rate limiter
    RateLimiter::clear('branding');

    // Authenticated users should have higher limit (100 requests)
    // We'll test a reasonable number (50) to avoid long test times
    for ($i = 0; $i < 50; $i++) {
        $response = $this->actingAs($user)
            ->get("/branding/{$org->slug}/styles.css");
        expect($response->status())->toBe(200);
    }
});

it('rate limits are per organization', function () {
    $org1 = Organization::factory()->create(['whitelabel_public_access' => true]);
    $org2 = Organization::factory()->create(['whitelabel_public_access' => true]);

    WhiteLabelConfig::factory()->create(['organization_id' => $org1->id]);
    WhiteLabelConfig::factory()->create(['organization_id' => $org2->id]);

    // Clear rate limiter
    RateLimiter::clear('branding');

    // Exhaust limit for org1
    for ($i = 0; $i < 30; $i++) {
        $this->get("/branding/{$org1->slug}/styles.css");
    }

    // Org2 should still work
    $response = $this->get("/branding/{$org2->slug}/styles.css");
    $response->assertOk();
});
