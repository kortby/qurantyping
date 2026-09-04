<?php

use App\Models\User;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    Config::set('admin.super_admins', ['admin@example.com']);
    $this->admin = User::factory()->create(['email' => 'admin@example.com']);
});

it('forbids non-admins from impersonating', function () {
    $user = User::factory()->create();
    $target = User::factory()->create();

    $this->actingAs($user)
        ->post("/admin/users/{$target->id}/impersonate")
        ->assertForbidden();
});

it('lets a super admin impersonate a regular user', function () {
    $target = User::factory()->create();

    $response = $this->actingAs($this->admin)->post("/admin/users/{$target->id}/impersonate");

    $response->assertRedirect('/');
    $response->assertSessionHas('impersonator_id', $this->admin->id);
    $this->assertAuthenticatedAs($target, 'web');
});

it('forbids impersonating another super admin', function () {
    Config::set('admin.super_admins', ['admin@example.com', 'other@example.com']);
    $other = User::factory()->create(['email' => 'other@example.com']);

    $this->actingAs($this->admin)
        ->post("/admin/users/{$other->id}/impersonate")
        ->assertForbidden();
});

it('forbids impersonating yourself', function () {
    $this->actingAs($this->admin)
        ->post("/admin/users/{$this->admin->id}/impersonate")
        ->assertForbidden();
});

it('forbids starting a second impersonation while already impersonating', function () {
    $first = User::factory()->create();
    $second = User::factory()->create();

    $this->actingAs($this->admin)->post("/admin/users/{$first->id}/impersonate");

    $this->post("/admin/users/{$second->id}/impersonate")->assertForbidden();
});

it('returns to the original admin when leaving impersonation', function () {
    $target = User::factory()->create();

    $this->actingAs($this->admin)->post("/admin/users/{$target->id}/impersonate");
    $this->assertAuthenticatedAs($target, 'web');

    $response = $this->post('/impersonate/leave');

    $response->assertRedirect(route('admin.users.index', absolute: false));
    $response->assertSessionMissing('impersonator_id');
    $this->assertAuthenticatedAs($this->admin, 'web');
});

it('forbids leaving impersonation when not impersonating', function () {
    $this->actingAs($this->admin)
        ->post('/impersonate/leave')
        ->assertForbidden();
});

it('keeps the impersonated session alive on the next request behind auth:sanctum', function () {
    $target = User::factory()->create(['password' => bcrypt('a-different-secret')]);

    // A real authed request first, so AuthenticateSession commits the admin's
    // fingerprint the way a browser would before the "login as" click.
    $this->actingAs($this->admin)->get('/admin/users')->assertOk();

    $this->post("/admin/users/{$target->id}/impersonate")->assertRedirect('/');

    // The follow-up navigation must load, not bounce to /login.
    $this->get('/dashboard')->assertOk();
    $this->get('/dashboard')->assertOk();
    $this->assertAuthenticatedAs($target, 'web');
});

it('restores the admin session fingerprint when leaving', function () {
    $target = User::factory()->create(['password' => bcrypt('a-different-secret')]);

    $this->actingAs($this->admin)->get('/admin/users')->assertOk();
    $this->post("/admin/users/{$target->id}/impersonate");
    $this->post('/impersonate/leave')->assertRedirect(route('admin.users.index', absolute: false));

    $this->get('/admin/users')->assertOk();
    $this->assertAuthenticatedAs($this->admin, 'web');
});
