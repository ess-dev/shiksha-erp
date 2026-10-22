<?php

use App\Domain\Access\Data\Scope;
use App\Domain\Audit\Models\AuditEvent;
use App\Domain\Students\Actions\LinkSiblings;
use App\Domain\Students\Actions\UnlinkSiblings;
use App\Domain\Students\Data\NewGuardian;
use App\Domain\Students\Exceptions\StudentException;
use App\Domain\Students\Models\Guardian;
use App\Domain\Students\Models\StudentSiblingLink;
use App\Domain\Students\SiblingFinder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsStudents;
use Tests\Concerns\HasCurrentSchool;

uses(RefreshDatabase::class, HasCurrentSchool::class, BuildsStudents::class);

beforeEach(fn () => $this->setUpStudentSchool());

it('links two students whose guardians differ and shows the link as staff-made', function () {
    $aarav = $this->makeStudent($this->main1A, 'Aarav', 'Sharma');
    $meera = $this->makeStudent($this->main1B, 'Meera', 'Verma');

    expect(app(SiblingFinder::class)->idsFor($aarav->id))->toBe([]);

    $this->actingAs($this->owner)
        ->post(route('students.siblings.store', $aarav), ['sibling' => $meera->ulid])
        ->assertSessionHasNoErrors();

    $link = StudentSiblingLink::query()->sole();

    expect([$link->student_id, $link->sibling_id])->toBe([min($aarav->id, $meera->id), max($aarav->id, $meera->id)])
        ->and(app(SiblingFinder::class)->idsFor($aarav->id))->toBe([$meera->id])
        ->and(app(SiblingFinder::class)->idsFor($meera->id))->toBe([$aarav->id])
        ->and(Guardian::query()->count())->toBe(2);

    $this->get(route('students.show', $aarav))
        ->assertInertia(fn (Assert $page) => $page
            ->has('student.siblings', 1)
            ->where('student.siblings.0.name', 'Meera Verma')
            ->where('student.siblings.0.via', 'staff'));

    expect(AuditEvent::query()->where('action', 'students.siblings_linked')->sole()->after['sibling'])->toBe($meera->ulid);
});

it('unlinks a staff-made link, but never one that comes from a shared guardian', function () {
    $aarav = $this->makeStudent($this->main1A, 'Aarav', 'Sharma');
    $meera = $this->makeStudent($this->main1B, 'Meera', 'Verma');

    app(LinkSiblings::class)->handle($this->owner, $aarav, $meera);
    app(UnlinkSiblings::class)->handle($this->owner, $meera, $aarav);

    expect(StudentSiblingLink::query()->count())->toBe(0)
        ->and(AuditEvent::query()->where('action', 'students.siblings_unlinked')->count())->toBe(1);

    $phone = $this->nextPhone();
    $one = $this->makeStudent($this->main1A, 'One', 'Gill', [new NewGuardian('Pooja Gill', 'Mother', $phone)]);
    $two = $this->makeStudent($this->main1A, 'Two', 'Gill', [new NewGuardian('Pooja Gill', 'Mother', $phone)]);

    expect(fn () => app(UnlinkSiblings::class)->handle($this->owner, $one, $two))
        ->toThrow(StudentException::class, 'not linked by staff');
});

it('links only when every guardian of both students has portal access', function () {
    $aarav = $this->makeStudent($this->main1A, 'Aarav', 'Sharma', [
        new NewGuardian('Rohit Sharma', 'Father', $this->nextPhone(), null, null, true),
        new NewGuardian('Neetu Sharma', 'Mother', $this->nextPhone()),
    ]);
    $meera = $this->makeStudent($this->main1B, 'Meera', 'Verma');

    $aarav->guardians()->where('guardians.name', 'Neetu Sharma')->first()->pivot->update(['has_portal_access' => false]);

    expect(fn () => app(LinkSiblings::class)->handle($this->owner, $aarav, $meera))
        ->toThrow(StudentException::class, 'portal access');

    $aarav->guardians()->updateExistingPivot(Guardian::query()->where('name', 'Neetu Sharma')->value('id'), ['has_portal_access' => true]);

    app(LinkSiblings::class)->handle($this->owner, $aarav, $meera);

    expect(StudentSiblingLink::query()->count())->toBe(1);
});

it('refuses to link a student to themselves, or students who are already siblings', function () {
    $phone = $this->nextPhone();
    $one = $this->makeStudent($this->main1A, 'One', 'Gill', [new NewGuardian('Pooja Gill', 'Mother', $phone)]);
    $two = $this->makeStudent($this->main1A, 'Two', 'Gill', [new NewGuardian('Pooja Gill', 'Mother', $phone)]);
    $other = $this->makeStudent($this->main1B, 'Other', 'Child');

    expect(fn () => app(LinkSiblings::class)->handle($this->owner, $one, $one))->toThrow(StudentException::class, 'their own sibling')
        ->and(fn () => app(LinkSiblings::class)->handle($this->owner, $one, $two))->toThrow(StudentException::class, 'already siblings');

    app(LinkSiblings::class)->handle($this->owner, $one, $other);

    expect(fn () => app(LinkSiblings::class)->handle($this->owner, $other, $one))->toThrow(StudentException::class, 'already siblings');
});

it('needs students.link_siblings over both students', function () {
    $near = $this->makeStudent($this->main1A, 'Near', 'Child');
    $far = $this->makeStudent($this->north1A, 'Far', 'Child');

    $plain = $this->staffWith(['students.view', 'students.manage']);

    expect(fn () => app(LinkSiblings::class)->handle($plain, $near, $far))->toThrow(StudentException::class, 'not allowed');

    $mainOnly = $this->staffWith(['students.view', 'students.link_siblings'], Scope::campuses([$this->main->ulid]));

    expect(fn () => app(LinkSiblings::class)->handle($mainOnly, $near, $far))->toThrow(StudentException::class, 'not found');

    $this->actingAs($plain)->post(route('students.siblings.store', $near), ['sibling' => $far->ulid])->assertForbidden();
});

it('finds link candidates by name or admission number within the person\'s reach', function () {
    $aarav = $this->makeStudent($this->main1A, 'Aarav', 'Sharma');
    $this->makeStudent($this->main1B, 'Meera', 'Verma');
    $this->makeStudent($this->north1A, 'Meena', 'Far');

    $this->actingAs($this->owner);

    $this->getJson(route('students.siblings.candidates', [$aarav, 'q' => 'mee']))
        ->assertOk()
        ->assertJsonCount(2)
        ->assertJsonPath('0.via', 'staff');

    $this->getJson(route('students.siblings.candidates', [$aarav, 'q' => 'm']))->assertOk()->assertJsonCount(0);
    $this->getJson(route('students.siblings.candidates', [$aarav, 'q' => 'Aarav']))->assertOk()->assertJsonCount(0);

    $mainOnly = $this->staffWith(['students.view', 'students.link_siblings'], Scope::campuses([$this->main->ulid]));

    $this->actingAs($mainOnly)->getJson(route('students.siblings.candidates', [$aarav, 'q' => 'mee']))
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.name', fn (string $name) => str_starts_with($name, 'Meera Verma'));

    $this->actingAs($this->staffWith(['students.view']))->getJson(route('students.siblings.candidates', [$aarav, 'q' => 'mee']))->assertForbidden();
});
