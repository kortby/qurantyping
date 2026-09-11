<?php

use App\Models\ClassAssignment;
use App\Models\ClassGroup;
use App\Models\QuranText;
use App\Models\Test;
use App\Models\User;

use function Pest\Laravel\actingAs;

function seedClassAyah(): QuranText
{
    return QuranText::create([
        'surah_number' => 112, 'ayah_number' => 1, 'juz' => 30, 'hizb_quarter' => 240, 'page' => 604,
        'text_arabic_simple' => 'كلمة كلمة كلمة كلمة كلمة',
        'surah_arabic_ponctuation' => 'كلمة كلمة كلمة كلمة كلمة',
        'surah_name_arabic' => 'الإخلاص', 'surah_name_english' => 'Al-Ikhlas', 'surah_name_translation' => 'Sincerity',
    ]);
}

it('creates a class with a unique code owned by the creator', function () {
    $teacher = User::factory()->create();

    actingAs($teacher)->post('/classes', ['name' => 'Evening Hifz Group'])->assertRedirect();

    $this->assertDatabaseHas('classes', [
        'name' => 'Evening Hifz Group',
        'owner_user_id' => $teacher->id,
    ]);
    expect(ClassGroup::first()->code)->toHaveLength(6);
});

it('rejects a class without a name', function () {
    actingAs(User::factory()->create())->post('/classes', ['name' => ''])->assertInvalid('name');

    expect(ClassGroup::count())->toBe(0);
});

it('joins an authenticated user by code and is idempotent', function () {
    $class = ClassGroup::factory()->create();
    $student = User::factory()->create();

    actingAs($student)->get("/c-join/{$class->code}")->assertRedirect(route('classes.show', $class));
    actingAs($student)->get("/c-join/{$class->code}")->assertRedirect(route('classes.show', $class));

    $this->assertDatabaseCount('class_members', 1);
    $this->assertDatabaseHas('class_members', [
        'class_id' => $class->id,
        'user_id' => $student->id,
    ]);
});

it('404s on an unknown join code', function () {
    actingAs(User::factory()->create())->get('/c-join/NOPE99')->assertNotFound();
});

it('stashes the join code for a guest and redeems it after they sign in', function () {
    $class = ClassGroup::factory()->create();
    $visitor = User::factory()->create();

    $this->get("/c-join/{$class->code}")->assertRedirect(route('register'));
    expect(session('pending_class_code'))->toBe($class->code);

    $this->post('/login', ['email' => $visitor->email, 'password' => 'password']);

    $this->assertDatabaseHas('class_members', [
        'class_id' => $class->id,
        'user_id' => $visitor->id,
    ]);
    expect(session('pending_class_code'))->toBeNull();
});

it('shows the owner a roster with per-student stats', function () {
    $qt = seedClassAyah();
    $teacher = User::factory()->create();
    $class = ClassGroup::factory()->for($teacher, 'owner')->create();
    $student = User::factory()->create();
    $class->members()->attach($student->id, ['joined_at' => now()]);

    Test::factory()->for($student)->create(['quran_text_id' => $qt->id, 'wpm' => 40, 'accuracy' => 90]);
    Test::factory()->for($student)->create(['quran_text_id' => $qt->id, 'wpm' => 60, 'accuracy' => 96]);

    actingAs($teacher)->get("/classes/{$class->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('isOwner', true)
            ->where('roster.0.id', $student->id)
            ->where('roster.0.tests_count', 2)
            ->where('roster.0.avg_wpm', 50)
        );
});

it('shows the owner a detailed page for one student in the class', function () {
    $qt = seedClassAyah();
    $teacher = User::factory()->create();
    $class = ClassGroup::factory()->for($teacher, 'owner')->create();
    $student = User::factory()->create();
    $class->members()->attach($student->id, ['joined_at' => now()]);

    Test::factory()->for($student)->create(['quran_text_id' => $qt->id, 'wpm' => 40, 'accuracy' => 90]);
    Test::factory()->for($student)->create(['quran_text_id' => $qt->id, 'wpm' => 80, 'accuracy' => 96]);

    actingAs($teacher)->get("/classes/{$class->id}/students/{$student->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('student.id', $student->id)
            ->where('progress.tests_count', 2)
            ->where('progress.best_wpm', 80)
            ->where('progress.first_test_at', fn ($v) => $v !== null)
            ->has('progress.recent_tests', 2)
        );
});

it('shows assignment completion on the student detail page', function () {
    $qt = seedClassAyah();
    $teacher = User::factory()->create();
    $class = ClassGroup::factory()->for($teacher, 'owner')->create();
    $done = User::factory()->create();
    $notDone = User::factory()->create();
    $class->members()->attach([$done->id, $notDone->id], ['joined_at' => now()]);

    actingAs($teacher)->post("/classes/{$class->id}/assignments", [
        'surah_number' => 112, 'start_ayah' => 1, 'end_ayah' => 1,
    ]);

    Test::factory()->for($done)->create(['quran_text_id' => $qt->id, 'created_at' => now()->addMinute()]);

    actingAs($teacher)->get("/classes/{$class->id}/students/{$done->id}")
        ->assertInertia(fn ($page) => $page->where('assignments.0.completed', true));

    actingAs($teacher)->get("/classes/{$class->id}/students/{$notDone->id}")
        ->assertInertia(fn ($page) => $page->where('assignments.0.completed', false));
});

it('leaves first_test_at null for a student who has not typed anything', function () {
    $teacher = User::factory()->create();
    $class = ClassGroup::factory()->for($teacher, 'owner')->create();
    $student = User::factory()->create();
    $class->members()->attach($student->id, ['joined_at' => now()]);

    actingAs($teacher)->get("/classes/{$class->id}/students/{$student->id}")
        ->assertInertia(fn ($page) => $page
            ->where('progress.tests_count', 0)
            ->where('progress.first_test_at', null)
        );
});

it('forbids a non-owner from viewing a student detail page', function () {
    $class = ClassGroup::factory()->create();
    $student = User::factory()->create();
    $class->members()->attach($student->id, ['joined_at' => now()]);

    actingAs(User::factory()->create())->get("/classes/{$class->id}/students/{$student->id}")->assertForbidden();
});

it('404s when the requested student is not a member of the class', function () {
    $teacher = User::factory()->create();
    $class = ClassGroup::factory()->for($teacher, 'owner')->create();
    $outsider = User::factory()->create();

    actingAs($teacher)->get("/classes/{$class->id}/students/{$outsider->id}")->assertNotFound();
});

it('shows a student their own progress, not the full roster', function () {
    $class = ClassGroup::factory()->create();
    $student = User::factory()->create();
    $class->members()->attach($student->id, ['joined_at' => now()]);

    actingAs($student)->get("/classes/{$class->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('isOwner', false)
            ->where('roster', [])
            ->has('myProgress')
        );
});

it('forbids a stranger from viewing a class', function () {
    $class = ClassGroup::factory()->create();

    actingAs(User::factory()->create())->get("/classes/{$class->id}")->assertForbidden();
});

it('lets only the owner delete a class, cascading its members', function () {
    $teacher = User::factory()->create();
    $class = ClassGroup::factory()->for($teacher, 'owner')->create();
    $student = User::factory()->create();
    $class->members()->attach($student->id, ['joined_at' => now()]);

    actingAs($student)->delete("/classes/{$class->id}")->assertForbidden();
    actingAs($teacher)->delete("/classes/{$class->id}")->assertRedirect(route('classes.index'));

    $this->assertDatabaseMissing('classes', ['id' => $class->id]);
    $this->assertDatabaseMissing('class_members', ['class_id' => $class->id]);
});

it('lists classes owned and joined on the index page', function () {
    $teacher = User::factory()->create();
    $owned = ClassGroup::factory()->for($teacher, 'owner')->create();
    $other = ClassGroup::factory()->create();
    $other->members()->attach($teacher->id, ['joined_at' => now()]);

    actingAs($teacher)->get('/classes')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('owned.0.id', $owned->id)
            ->where('joined.0.id', $other->id)
        );
});

it('lets the owner assign a surah to the class', function () {
    $teacher = User::factory()->create();
    $class = ClassGroup::factory()->for($teacher, 'owner')->create();

    actingAs($teacher)->post("/classes/{$class->id}/assignments", [
        'surah_number' => 112,
        'start_ayah' => 1,
        'end_ayah' => 4,
    ])->assertRedirect();

    $this->assertDatabaseHas('class_assignments', [
        'class_id' => $class->id,
        'surah_number' => 112,
        'start_ayah' => 1,
        'end_ayah' => 4,
    ]);
});

it('forbids a non-owner from assigning a surah', function () {
    $class = ClassGroup::factory()->create();

    actingAs(User::factory()->create())->post("/classes/{$class->id}/assignments", [
        'surah_number' => 112, 'start_ayah' => 1, 'end_ayah' => 4,
    ])->assertForbidden();
});

it('reports how many students completed an assignment', function () {
    $qt = seedClassAyah();
    $teacher = User::factory()->create();
    $class = ClassGroup::factory()->for($teacher, 'owner')->create();
    $done = User::factory()->create();
    $notDone = User::factory()->create();
    $class->members()->attach([$done->id, $notDone->id], ['joined_at' => now()]);

    actingAs($teacher)->post("/classes/{$class->id}/assignments", [
        'surah_number' => 112, 'start_ayah' => 1, 'end_ayah' => 1,
    ]);
    $assignment = ClassAssignment::first();

    Test::factory()->for($done)->create(['quran_text_id' => $qt->id, 'created_at' => now()->addMinute()]);

    actingAs($teacher)->get("/classes/{$class->id}")
        ->assertInertia(fn ($page) => $page
            ->where('assignments.0.id', $assignment->id)
            ->where('assignments.0.completed_count', 1)
            ->where('assignments.0.members_count', 2)
        );
});

it('does not count a test typed before the assignment was created', function () {
    $qt = seedClassAyah();
    $teacher = User::factory()->create();
    $class = ClassGroup::factory()->for($teacher, 'owner')->create();
    $student = User::factory()->create();
    $class->members()->attach($student->id, ['joined_at' => now()]);

    Test::factory()->for($student)->create(['quran_text_id' => $qt->id, 'created_at' => now()->subDay()]);

    actingAs($teacher)->post("/classes/{$class->id}/assignments", [
        'surah_number' => 112, 'start_ayah' => 1, 'end_ayah' => 1,
    ]);

    actingAs($student)->get("/classes/{$class->id}")
        ->assertInertia(fn ($page) => $page->where('assignments.0.completed', false));
});

it('marks an assignment completed once the student types it', function () {
    $qt = seedClassAyah();
    $teacher = User::factory()->create();
    $class = ClassGroup::factory()->for($teacher, 'owner')->create();
    $student = User::factory()->create();
    $class->members()->attach($student->id, ['joined_at' => now()]);

    actingAs($teacher)->post("/classes/{$class->id}/assignments", [
        'surah_number' => 112, 'start_ayah' => 1, 'end_ayah' => 1,
    ]);

    Test::factory()->for($student)->create(['quran_text_id' => $qt->id]);

    actingAs($student)->get("/classes/{$class->id}")
        ->assertInertia(fn ($page) => $page->where('assignments.0.completed', true));
});

it('lets only the owner remove an assignment', function () {
    $teacher = User::factory()->create();
    $class = ClassGroup::factory()->for($teacher, 'owner')->create();
    $student = User::factory()->create();
    $class->members()->attach($student->id, ['joined_at' => now()]);

    actingAs($teacher)->post("/classes/{$class->id}/assignments", [
        'surah_number' => 112, 'start_ayah' => 1, 'end_ayah' => 1,
    ]);
    $assignment = ClassAssignment::first();

    actingAs($student)->delete("/classes/{$class->id}/assignments/{$assignment->id}")->assertForbidden();
    actingAs($teacher)->delete("/classes/{$class->id}/assignments/{$assignment->id}")->assertRedirect();

    $this->assertDatabaseMissing('class_assignments', ['id' => $assignment->id]);
});
