<?php

use App\Models\ClassRoom;
use App\Models\ClassRoomMember;
use App\Models\User;
use App\Notifications\InviteAcceptedNotification;
use App\Notifications\InviteDeclinedNotification;
use Illuminate\Support\Facades\Notification;

// ─────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────

function makeClass(User $owner, array $attrs = []): ClassRoom
{
    return ClassRoom::create(array_merge([
        'user_id'       => $owner->id,
        'name'          => 'Grade 8A',
        'subject'       => 'English',
        'academic_year' => 2026,
        'is_active'     => true,
    ], $attrs));
}

function makePendingMember(ClassRoom $classroom, User $teacher, User $invitedBy, string $subject = 'Mathematics'): ClassRoomMember
{
    return ClassRoomMember::create([
        'class_id'     => $classroom->id,
        'user_id'      => $teacher->id,
        'invited_by'   => $invitedBy->id,
        'subject'      => $subject,
        'role'         => 'subject_teacher',
        'invite_token' => ClassRoomMember::generateToken(),
        'status'       => 'pending',
    ]);
}

// ─────────────────────────────────────────────────────────────────
// InviteController::accept
// ─────────────────────────────────────────────────────────────────

describe('accept invite via email link', function () {
    it('redirects guests to login and stores the token in the session', function () {
        $formTeacher    = User::factory()->create();
        $subjectTeacher = User::factory()->create();
        $classroom      = makeClass($formTeacher);
        $member         = makePendingMember($classroom, $subjectTeacher, $formTeacher);

        $this->get(route('invites.accept', $member->invite_token))
            ->assertRedirect(route('login'));

        expect(session('invite_token'))->toBe($member->invite_token);
    });

    it('marks the invite accepted and notifies the form teacher', function () {
        Notification::fake();

        $formTeacher    = User::factory()->create();
        $subjectTeacher = User::factory()->create();
        $classroom      = makeClass($formTeacher);
        $member         = makePendingMember($classroom, $subjectTeacher, $formTeacher);
        $token          = $member->invite_token;

        $this->actingAs($subjectTeacher)
            ->get(route('invites.accept', $token));

        $member->refresh();
        expect($member->status)->toBe('accepted');
        expect($member->accepted_at)->not->toBeNull();
        expect($member->invite_token)->toBeNull();

        Notification::assertSentTo($formTeacher, InviteAcceptedNotification::class);
    });

    it('redirects with an error when the token is invalid', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('invites.accept', 'invalid-token'))
            ->assertRedirect(route('user.class.rooms'))
            ->assertSessionHas('error');
    });

    it('rejects when the logged-in user is not the intended recipient', function () {
        $formTeacher    = User::factory()->create();
        $subjectTeacher = User::factory()->create();
        $wrongUser      = User::factory()->create();
        $classroom      = makeClass($formTeacher);
        $member         = makePendingMember($classroom, $subjectTeacher, $formTeacher);

        $this->actingAs($wrongUser)
            ->get(route('invites.accept', $member->invite_token))
            ->assertRedirect(route('user.class.rooms'))
            ->assertSessionHas('error');

        expect($member->fresh()->status)->toBe('pending');
    });
});

// ─────────────────────────────────────────────────────────────────
// InviteController::decline
// ─────────────────────────────────────────────────────────────────

describe('decline invite via email link', function () {
    it('redirects guests to login', function () {
        $formTeacher    = User::factory()->create();
        $subjectTeacher = User::factory()->create();
        $classroom      = makeClass($formTeacher);
        $member         = makePendingMember($classroom, $subjectTeacher, $formTeacher);

        $this->get(route('invites.decline', $member->invite_token))
            ->assertRedirect(route('login'));
    });

    it('marks the invite declined and notifies the form teacher', function () {
        Notification::fake();

        $formTeacher    = User::factory()->create();
        $subjectTeacher = User::factory()->create();
        $classroom      = makeClass($formTeacher);
        $member         = makePendingMember($classroom, $subjectTeacher, $formTeacher);
        $token          = $member->invite_token;

        $this->actingAs($subjectTeacher)
            ->get(route('invites.decline', $token));

        $member->refresh();
        expect($member->status)->toBe('declined');
        expect($member->invite_token)->toBeNull();

        Notification::assertSentTo($formTeacher, InviteDeclinedNotification::class);
    });

    it('redirects with an error when the token is invalid', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('invites.decline', 'invalid-token'))
            ->assertRedirect(route('user.class.rooms'))
            ->assertSessionHas('error');
    });

    it('rejects when the logged-in user is not the intended recipient', function () {
        $formTeacher    = User::factory()->create();
        $subjectTeacher = User::factory()->create();
        $wrongUser      = User::factory()->create();
        $classroom      = makeClass($formTeacher);
        $member         = makePendingMember($classroom, $subjectTeacher, $formTeacher);

        $this->actingAs($wrongUser)
            ->get(route('invites.decline', $member->invite_token))
            ->assertRedirect(route('user.class.rooms'))
            ->assertSessionHas('error');

        expect($member->fresh()->status)->toBe('pending');
    });
});

// ─────────────────────────────────────────────────────────────────
// ClassRoom::scopeForTeacher
// ─────────────────────────────────────────────────────────────────

describe('ClassRoom::forTeacher scope', function () {
    it('includes owned classrooms', function () {
        $owner     = User::factory()->create();
        $classroom = makeClass($owner);

        expect(ClassRoom::forTeacher($owner->id)->get()->contains($classroom))->toBeTrue();
    });

    it('includes classrooms where user is an accepted subject teacher', function () {
        $owner   = User::factory()->create();
        $teacher = User::factory()->create();
        $class   = makeClass($owner);

        ClassRoomMember::create([
            'class_id'   => $class->id,
            'user_id'    => $teacher->id,
            'invited_by' => $owner->id,
            'subject'    => 'Mathematics',
            'role'       => 'subject_teacher',
            'status'     => 'accepted',
        ]);

        expect(ClassRoom::forTeacher($teacher->id)->get()->contains($class))->toBeTrue();
    });

    it('excludes classrooms with a pending invite', function () {
        $owner   = User::factory()->create();
        $teacher = User::factory()->create();
        $class   = makeClass($owner);

        makePendingMember($class, $teacher, $owner);

        expect(ClassRoom::forTeacher($teacher->id)->get()->contains($class))->toBeFalse();
    });

    it('excludes classrooms with a declined invite', function () {
        $owner   = User::factory()->create();
        $teacher = User::factory()->create();
        $class   = makeClass($owner);

        ClassRoomMember::create([
            'class_id'   => $class->id,
            'user_id'    => $teacher->id,
            'invited_by' => $owner->id,
            'subject'    => 'Science',
            'role'       => 'subject_teacher',
            'status'     => 'declined',
        ]);

        expect(ClassRoom::forTeacher($teacher->id)->get()->contains($class))->toBeFalse();
    });
});

// ─────────────────────────────────────────────────────────────────
// Ownership enforcement — subject teachers cannot edit/delete classes
// ─────────────────────────────────────────────────────────────────

describe('class ownership enforcement', function () {
    it('subject teacher cannot find a class via the ownership-only query', function () {
        $owner   = User::factory()->create();
        $teacher = User::factory()->create();
        $class   = makeClass($owner);

        ClassRoomMember::create([
            'class_id'   => $class->id,
            'user_id'    => $teacher->id,
            'invited_by' => $owner->id,
            'subject'    => 'Mathematics',
            'role'       => 'subject_teacher',
            'status'     => 'accepted',
        ]);

        $found = ClassRoom::where('user_id', $teacher->id)->find($class->id);

        expect($found)->toBeNull();
    });
});

// ─────────────────────────────────────────────────────────────────
// ClassRoomMember status helpers
// ─────────────────────────────────────────────────────────────────

describe('ClassRoomMember status helpers', function () {
    it('correctly reports pending status', function () {
        $owner   = User::factory()->create();
        $teacher = User::factory()->create();
        $member  = makePendingMember(makeClass($owner), $teacher, $owner);

        expect($member->isPending())->toBeTrue();
        expect($member->isAccepted())->toBeFalse();
        expect($member->isDeclined())->toBeFalse();
    });

    it('correctly reports accepted status after update', function () {
        $owner   = User::factory()->create();
        $teacher = User::factory()->create();
        $member  = makePendingMember(makeClass($owner), $teacher, $owner);
        $member->update(['status' => 'accepted']);

        expect($member->fresh()->isAccepted())->toBeTrue();
    });

    it('generates unique tokens', function () {
        $tokens = collect(range(1, 50))->map(fn () => ClassRoomMember::generateToken());

        expect($tokens->unique()->count())->toBe(50);
    });
});

// ─────────────────────────────────────────────────────────────────
// ClassRoom::allSubjects
// ─────────────────────────────────────────────────────────────────

describe('ClassRoom::allSubjects', function () {
    it('returns owner subject plus accepted member subjects, excluding pending', function () {
        $owner    = User::factory()->create();
        $teacher1 = User::factory()->create();
        $teacher2 = User::factory()->create();
        $class    = makeClass($owner, ['subject' => 'English']);

        ClassRoomMember::create([
            'class_id' => $class->id, 'user_id' => $teacher1->id,
            'invited_by' => $owner->id, 'subject' => 'Mathematics',
            'role' => 'subject_teacher', 'status' => 'accepted',
        ]);
        ClassRoomMember::create([
            'class_id' => $class->id, 'user_id' => $teacher2->id,
            'invited_by' => $owner->id, 'subject' => 'Science',
            'role' => 'subject_teacher', 'status' => 'pending',
        ]);

        $subjects = $class->allSubjects();

        expect($subjects)->toContain('English');
        expect($subjects)->toContain('Mathematics');
        expect($subjects)->not->toContain('Science');
    });
});
