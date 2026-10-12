<?php

use App\Mail\FeedbackReceived;
use App\Models\Feedback;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Config::set('admin.super_admins', ['admin@example.com', 'second@example.com']);
});

it('stores the feedback and emails every super admin', function () {
    Mail::fake();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/feedback', ['message' => 'The alif will not type', 'type' => 'bug'])
        ->assertRedirect();

    $this->assertDatabaseHas('feedback', [
        'user_id' => $user->id,
        'message' => 'The alif will not type',
        'type' => 'bug',
    ]);

    $feedback = Feedback::first();
    Mail::assertQueued(FeedbackReceived::class, function (FeedbackReceived $mail) use ($feedback) {
        return $mail->feedback->is($feedback)
            && $mail->hasTo('admin@example.com')
            && $mail->hasTo('second@example.com');
    });
});

it('still stores feedback when no admins are configured', function () {
    Config::set('admin.super_admins', []);
    Mail::fake();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/feedback', ['message' => 'A suggestion', 'type' => 'suggestion'])
        ->assertRedirect();

    $this->assertDatabaseCount('feedback', 1);
    Mail::assertNothingQueued();
});

it('rejects an invalid feedback type', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/feedback', ['message' => 'hi', 'type' => 'spam'])
        ->assertSessionHasErrors('type');
});
