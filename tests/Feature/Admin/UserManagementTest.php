<?php

use App\Models\Certificate;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Config::set('admin.super_admins', ['admin@example.com']);
    $this->admin = User::factory()->create(['email' => 'admin@example.com']);
});

it('redirects guests away from the users list', function () {
    $this->get('/admin/users')->assertRedirect('/login');
});

it('forbids non-admins from every admin route', function () {
    $user = User::factory()->create();
    $target = User::factory()->create();

    $this->actingAs($user)->get('/admin/users')->assertForbidden();
    $this->actingAs($user)->get("/admin/users/{$target->id}")->assertForbidden();
    $this->actingAs($user)->put("/admin/users/{$target->id}", ['name' => 'x', 'email' => 'x@x.com'])->assertForbidden();
    $this->actingAs($user)->delete("/admin/users/{$target->id}")->assertForbidden();
});

it('shows the users list to a super admin', function () {
    User::factory()->count(3)->create();

    $this->actingAs($this->admin)->get('/admin/users')->assertOk();
});

it('filters the users list by search term', function () {
    User::factory()->create(['name' => 'Zaynab Ali', 'email' => 'zaynab@example.com']);
    User::factory()->create(['name' => 'Bilal Omar', 'email' => 'bilal@example.com']);

    $response = $this->actingAs($this->admin)->get('/admin/users?search=zaynab');

    $response->assertOk()
        ->assertSee('zaynab@example.com')
        ->assertDontSee('bilal@example.com');
});

it('sorts the users list by a whitelisted column', function () {
    User::factory()->create(['name' => 'Aaron First']);
    User::factory()->create(['name' => 'Zev Last']);

    $this->actingAs($this->admin)
        ->get('/admin/users?sort=name&direction=asc')
        ->assertOk()
        ->assertSeeInOrder(['Aaron First', 'Zev Last']);

    $this->actingAs($this->admin)
        ->get('/admin/users?sort=name&direction=desc')
        ->assertOk()
        ->assertSeeInOrder(['Zev Last', 'Aaron First']);
});

it('ignores an unknown sort column', function () {
    User::factory()->count(3)->create();

    $this->actingAs($this->admin)
        ->get('/admin/users?sort=password&direction=asc')
        ->assertOk();
});

it('lists and sorts by the streak / hifz / certificate / last-login columns', function () {
    User::factory()->create(['name' => 'Low'])->forceFill(['current_streak' => 1])->save();
    User::factory()->create(['name' => 'High'])->forceFill(['current_streak' => 40])->save();

    $this->actingAs($this->admin)->get('/admin/users?sort=current_streak&direction=desc')
        ->assertOk()
        ->assertSeeInOrder(['High', 'Low']);

    $this->actingAs($this->admin)->get('/admin/users?sort=hifz_ayahs&direction=desc')->assertOk();
    $this->actingAs($this->admin)->get('/admin/users?sort=certificates_count&direction=asc')->assertOk();
    $this->actingAs($this->admin)->get('/admin/users?sort=last_login_at&direction=desc')->assertOk();
});

it('stamps last_login_at when a user signs in, but not on impersonation', function () {
    $user = User::factory()->create();
    expect($user->last_login_at)->toBeNull();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect();
    expect($user->fresh()->last_login_at)->not->toBeNull();

    $target = User::factory()->create();
    $this->actingAs($this->admin)->post("/admin/users/{$target->id}/impersonate");
    expect($target->fresh()->last_login_at)->toBeNull();
});

it('shows a user detail page with practice progress', function () {
    $target = User::factory()->create();

    Certificate::create([
        'user_id' => $target->id,
        'surah_number' => 108,
        'surah_name_english' => 'Al-Kawthar',
        'surah_name_arabic' => 'الكوثر',
        'ayah_count' => 3,
        'accuracy' => 97.5,
        'issued_at' => now(),
    ]);

    $this->actingAs($this->admin)->get("/admin/users/{$target->id}")
        ->assertOk()
        ->assertSee('Al-Kawthar')
        ->assertSee('97.5')
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Users/Show')
            ->has('account.reciter')
            ->has('account.two_factor')
            ->has('stats.total_chars')
            ->has('stats.hours_practiced')
            ->has('quran.ayah_count')
            ->has('races.finished')
            ->has('weakLetters')
            ->has('activity')
            ->has('feedback')
            ->has('badgeTotal')
        );
});

it('updates a user name and email', function () {
    $target = User::factory()->create();

    $this->actingAs($this->admin)
        ->put("/admin/users/{$target->id}", ['name' => 'New Name', 'email' => 'new@example.com'])
        ->assertRedirect();

    expect($target->fresh())->name->toEqual('New Name')->email->toEqual('new@example.com');
});

it('rejects a duplicate email on update', function () {
    $other = User::factory()->create(['email' => 'taken@example.com']);
    $target = User::factory()->create();

    $this->actingAs($this->admin)
        ->from("/admin/users/{$target->id}")
        ->put("/admin/users/{$target->id}", ['name' => 'Name', 'email' => 'taken@example.com'])
        ->assertSessionHasErrors('email');
});

it('marks an unverified user as verified when requested', function () {
    $target = User::factory()->unverified()->create();

    $this->actingAs($this->admin)->put("/admin/users/{$target->id}", [
        'name' => $target->name,
        'email' => $target->email,
        'mark_verified' => true,
    ]);

    expect($target->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('resends the verification email', function () {
    Notification::fake();
    $target = User::factory()->unverified()->create();

    $this->actingAs($this->admin)
        ->post("/admin/users/{$target->id}/resend-verification")
        ->assertRedirect();

    Notification::assertSentTo($target, VerifyEmail::class);
});

it('deletes a user', function () {
    $target = User::factory()->create();

    $this->actingAs($this->admin)
        ->delete("/admin/users/{$target->id}")
        ->assertRedirect(route('admin.users.index', absolute: false));

    expect(User::find($target->id))->toBeNull();
});

it('forbids deleting yourself', function () {
    $this->actingAs($this->admin)
        ->delete("/admin/users/{$this->admin->id}")
        ->assertForbidden();

    expect($this->admin->fresh())->not->toBeNull();
});

it('forbids deleting another super admin', function () {
    Config::set('admin.super_admins', ['admin@example.com', 'other@example.com']);
    $other = User::factory()->create(['email' => 'other@example.com']);

    $this->actingAs($this->admin)
        ->delete("/admin/users/{$other->id}")
        ->assertForbidden();

    expect($other->fresh())->not->toBeNull();
});

it('revokes all browser sessions for a user', function () {
    $target = User::factory()->create();

    DB::table('sessions')->insert([
        'id' => 'session-1',
        'user_id' => $target->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'phpunit',
        'payload' => 'x',
        'last_activity' => now()->timestamp,
    ]);

    $this->actingAs($this->admin)
        ->delete("/admin/users/{$target->id}/sessions")
        ->assertRedirect();

    expect(DB::table('sessions')->where('user_id', $target->id)->count())->toBe(0);
});

it('revokes a single API token for a user', function () {
    $target = User::factory()->create();
    $tokenId = $target->createToken('cli')->accessToken->id;

    $this->actingAs($this->admin)
        ->delete("/admin/users/{$target->id}/tokens/{$tokenId}")
        ->assertRedirect();

    expect($target->tokens()->count())->toBe(0);
});
