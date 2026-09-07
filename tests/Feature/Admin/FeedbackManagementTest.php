<?php

use App\Mail\FeedbackReplied;
use App\Models\Feedback;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Config::set('admin.super_admins', ['admin@example.com']);
    $this->admin = User::factory()->create(['email' => 'admin@example.com']);
});

it('redirects guests away from the feedback list', function () {
    $this->get('/admin/feedback')->assertRedirect('/login');
});

it('forbids non-admins from every feedback route', function () {
    $user = User::factory()->create();
    $item = Feedback::factory()->create();

    $this->actingAs($user)->get('/admin/feedback')->assertForbidden();
    $this->actingAs($user)->get("/admin/feedback/{$item->id}")->assertForbidden();
    $this->actingAs($user)->patch("/admin/feedback/{$item->id}", ['handled' => true])->assertForbidden();
    $this->actingAs($user)->post("/admin/feedback/{$item->id}/reply", ['response' => 'hi'])->assertForbidden();
    $this->actingAs($user)->delete("/admin/feedback/{$item->id}")->assertForbidden();
});

it('shows the feedback list to a super admin', function () {
    Feedback::factory()->count(3)->create();

    $this->actingAs($this->admin)->get('/admin/feedback')->assertOk();
});

it('defaults to the open feedback and filters by status', function () {
    Feedback::factory()->create(['message' => 'Open item here']);
    Feedback::factory()->handled()->create(['message' => 'Handled item here']);

    $this->actingAs($this->admin)->get('/admin/feedback')
        ->assertOk()
        ->assertSee('Open item here')
        ->assertDontSee('Handled item here');

    $this->actingAs($this->admin)->get('/admin/feedback?filter=handled')
        ->assertOk()
        ->assertSee('Handled item here')
        ->assertDontSee('Open item here');

    $this->actingAs($this->admin)->get('/admin/feedback?filter=all')
        ->assertOk()
        ->assertSee('Open item here')
        ->assertSee('Handled item here');
});

it('filters the feedback list by type', function () {
    Feedback::factory()->create(['type' => 'bug', 'message' => 'A crash report']);
    Feedback::factory()->create(['type' => 'suggestion', 'message' => 'A nice idea']);

    $this->actingAs($this->admin)->get('/admin/feedback?filter=all&type=bug')
        ->assertOk()
        ->assertSee('A crash report')
        ->assertDontSee('A nice idea');
});

it('shows a feedback detail page with the full message', function () {
    $item = Feedback::factory()->create(['message' => 'The whole detailed message body']);

    $this->actingAs($this->admin)->get("/admin/feedback/{$item->id}")
        ->assertOk()
        ->assertSee('The whole detailed message body');
});

it('marks feedback as handled and reopens it', function () {
    $item = Feedback::factory()->create();

    $this->actingAs($this->admin)
        ->patch("/admin/feedback/{$item->id}", ['handled' => true])
        ->assertRedirect();

    expect($item->fresh()->handled_at)->not->toBeNull();

    $this->actingAs($this->admin)
        ->patch("/admin/feedback/{$item->id}", ['handled' => false])
        ->assertRedirect();

    expect($item->fresh()->handled_at)->toBeNull();
});

it('emails an admin reply to the user and marks the feedback handled', function () {
    Mail::fake();
    $author = User::factory()->create(['email' => 'author@example.com']);
    $item = Feedback::factory()->for($author)->create(['handled_at' => null]);

    $this->actingAs($this->admin)
        ->post("/admin/feedback/{$item->id}/reply", ['response' => 'Thanks — fixed in the next release.'])
        ->assertRedirect();

    $item->refresh();

    expect($item->admin_response)->toBe('Thanks — fixed in the next release.')
        ->and($item->responded_at)->not->toBeNull()
        ->and($item->responded_by)->toBe($this->admin->id)
        ->and($item->handled_at)->not->toBeNull();

    Mail::assertSent(FeedbackReplied::class, fn ($mail) => $mail->hasTo('author@example.com'));
});

it('requires a non-empty reply', function () {
    Mail::fake();
    $item = Feedback::factory()->create();

    $this->actingAs($this->admin)
        ->post("/admin/feedback/{$item->id}/reply", ['response' => ''])
        ->assertSessionHasErrors('response');

    Mail::assertNothingSent();
});

it('keeps the original handled timestamp when replying to already-handled feedback', function () {
    Mail::fake();
    $handledAt = now()->subDays(3);
    $item = Feedback::factory()->create(['handled_at' => $handledAt]);

    $this->actingAs($this->admin)
        ->post("/admin/feedback/{$item->id}/reply", ['response' => 'Following up on this.'])
        ->assertRedirect();

    expect($item->fresh()->handled_at->toDateTimeString())->toBe($handledAt->toDateTimeString());
});

it('deletes feedback', function () {
    $item = Feedback::factory()->create();

    $this->actingAs($this->admin)
        ->delete("/admin/feedback/{$item->id}")
        ->assertRedirect(route('admin.feedback.index', absolute: false));

    expect(Feedback::find($item->id))->toBeNull();
});
