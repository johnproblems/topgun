<?php

use App\Models\Organization;
use App\Models\User;
use App\Models\WhiteLabelConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('requires authentication for private branding', function () {
    $org = Organization::factory()->create([
        'whitelabel_public_access' => false,
    ]);

    WhiteLabelConfig::factory()->create([
        'organization_id' => $org->id,
    ]);

    $response = $this->get("/branding/{$org->slug}/styles.css");

    $response->assertForbidden()
        ->assertHeader('X-Branding-Error', 'unauthorized:-branding-access-requires-authentication')
        ->assertHeader('Content-Type', 'text/css; charset=UTF-8');
});

it('allows public access when configured', function () {
    $org = Organization::factory()->create([
        'whitelabel_public_access' => true,
    ]);

    WhiteLabelConfig::factory()->create([
        'organization_id' => $org->id,
        'theme_config' => [
            'primary_color' => '#ff0000',
        ],
    ]);

    $response = $this->get("/branding/{$org->slug}/styles.css");

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/css; charset=UTF-8');
});

it('allows access for organization members', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create([
        'whitelabel_public_access' => false,
    ]);

    $org->users()->attach($user->id, ['role' => 'member']);

    WhiteLabelConfig::factory()->create([
        'organization_id' => $org->id,
        'theme_config' => [
            'primary_color' => '#ff0000',
        ],
    ]);

    $response = $this->actingAs($user)
        ->get("/branding/{$org->slug}/styles.css");

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/css; charset=UTF-8');
});

it('denies access to unauthorized organizations', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create([
        'whitelabel_public_access' => false,
    ]);

    // User is NOT a member of this organization
    WhiteLabelConfig::factory()->create([
        'organization_id' => $org->id,
    ]);

    $response = $this->actingAs($user)
        ->get("/branding/{$org->slug}/styles.css");

    $response->assertForbidden()
        ->assertHeader('X-Branding-Error');
});

it('supports organization lookup by UUID with authorization', function () {
    $org = Organization::factory()->create([
        'whitelabel_public_access' => true,
    ]);

    WhiteLabelConfig::factory()->create([
        'organization_id' => $org->id,
    ]);

    $response = $this->get("/branding/{$org->id}/styles.css");

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/css; charset=UTF-8');
});
