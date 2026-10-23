<?php

use App\Domain\Access\Data\Scope;
use App\Domain\Audit\Models\AuditEvent;
use App\Domain\Students\Models\StudentDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsStudents;
use Tests\Concerns\HasCurrentSchool;

uses(RefreshDatabase::class, HasCurrentSchool::class, BuildsStudents::class);

beforeEach(function () {
    $this->setUpStudentSchool();
    Storage::fake('tenant');
});

it('uploads a document under the school\'s prefix, verified, and lists it', function () {
    $student = $this->makeStudent($this->main1A);

    $this->actingAs($this->owner)
        ->post(route('students.documents.store', $student), [
            'kind' => 'birth_certificate',
            'file' => UploadedFile::fake()->create('birth.pdf', 120, 'application/pdf'),
        ])
        ->assertSessionHasNoErrors();

    $document = StudentDocument::query()->sole();

    expect($document->kind->value)->toBe('birth_certificate')
        ->and($document->isVerified())->toBeTrue()
        ->and($document->uploaded_by_identity_ulid)->toBe($this->owner->ulid)
        ->and($document->path)->toStartWith("tenants/{$this->school->ulid}/students/{$student->ulid}/")
        ->and($document->path)->toEndWith('.pdf');

    Storage::disk('tenant')->assertExists($document->path);

    $this->get(route('students.show', $student))
        ->assertInertia(fn (Assert $page) => $page
            ->has('student.documents', 1)
            ->where('student.documents.0.label', 'Birth certificate')
            ->where('student.documents.0.is_verified', true)
            ->where('student.documents.0.verified_by', 'Meera Nair')
            ->where('student.documents.0.view_url', fn (string $url) => str_contains($url, 'signature=') && ! str_contains($url, $document->path))
            ->missing('student.documents.0.path'));

    expect(AuditEvent::query()->where('action', 'students.document_uploaded')->sole()->after['kind'])->toBe('birth_certificate');
});

it('keeps the earlier document when a newer one of the same kind replaces it on the profile', function () {
    $student = $this->makeStudent($this->main1A);
    $this->actingAs($this->owner);
    $upload = fn (string $kind, string $name) => $this->post(route('students.documents.store', $student), [
        'kind' => $kind, 'file' => UploadedFile::fake()->create($name, 50, 'application/pdf'),
    ])->assertSessionHasNoErrors();

    $upload('photo', 'one.pdf');
    $upload('photo', 'two.pdf');
    $upload('medical', 'a.pdf');
    $upload('medical', 'b.pdf');

    expect(StudentDocument::query()->count())->toBe(4)
        ->and(StudentDocument::query()->whereNull('superseded_at')->where('kind', 'photo')->count())->toBe(1)
        ->and(StudentDocument::query()->whereNull('superseded_at')->where('kind', 'medical')->count())->toBe(2);

    foreach (StudentDocument::query()->get() as $document) {
        Storage::disk('tenant')->assertExists($document->path);
    }

    $this->get(route('students.show', $student))->assertInertia(fn (Assert $page) => $page->has('student.documents', 3));
});

it('refuses files that are not PDF, JPEG or PNG, or are over 5 MB, and stores nothing', function () {
    $student = $this->makeStudent($this->main1A);
    $this->actingAs($this->owner);

    $this->post(route('students.documents.store', $student), ['kind' => 'photo', 'file' => UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')])
        ->assertSessionHasErrors('file');
    $this->post(route('students.documents.store', $student), ['kind' => 'photo', 'file' => UploadedFile::fake()->create('big.pdf', 6000, 'application/pdf')])
        ->assertSessionHasErrors('file');
    $this->post(route('students.documents.store', $student), ['kind' => 'nonsense', 'file' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')])
        ->assertSessionHasErrors('kind');

    expect(StudentDocument::query()->count())->toBe(0)->and(Storage::disk('tenant')->allFiles())->toBe([]);
});

it('verifies a document that is waiting to be checked', function () {
    $student = $this->makeStudent($this->main1A);
    $document = StudentDocument::query()->create([
        'ulid' => strtolower((string) Str::ulid()), 'student_id' => $student->id, 'kind' => 'aadhaar',
        'original_name' => 'aadhaar.pdf', 'path' => 'tenants/x/aadhaar.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 10, 'source' => 'parent',
    ]);

    $this->actingAs($this->owner)->post(route('students.documents.verify', $document))->assertSessionHasNoErrors();

    expect($document->refresh()->isVerified())->toBeTrue()->and($document->verified_by_identity_ulid)->toBe($this->owner->ulid);

    $this->post(route('students.documents.verify', $document))->assertSessionHasErrors('student');
});

it('serves a document only through a signed link, after the permission check, and audits the opening', function () {
    $student = $this->makeStudent($this->main1A);

    $this->actingAs($this->owner)->post(route('students.documents.store', $student), [
        'kind' => 'photo', 'file' => UploadedFile::fake()->image('photo.jpg'),
    ])->assertSessionHasNoErrors();

    $document = StudentDocument::query()->sole();
    $signed = URL::temporarySignedRoute('students.documents.show', now()->addMinutes(5), ['document' => $document->ulid]);

    $this->get(route('students.documents.show', $document))->assertForbidden();

    $response = $this->get($signed)->assertOk();

    expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store');
    expect(AuditEvent::query()->where('action', 'students.document_opened')->sole()->after['document'])->toBe($document->ulid);

    $expired = URL::temporarySignedRoute('students.documents.show', now()->subMinute(), ['document' => $document->ulid]);
    $this->get($expired)->assertForbidden();
});

it('hides documents from people without access and out of reach', function () {
    $near = $this->makeStudent($this->main1A);
    $far = $this->makeStudent($this->north1A, 'Far', 'Away');
    $this->actingAs($this->owner);

    foreach ([$near, $far] as $student) {
        $this->post(route('students.documents.store', $student), ['kind' => 'photo', 'file' => UploadedFile::fake()->image('p.jpg')]);
    }

    $plain = $this->staffWith(['students.view']);
    $this->actingAs($plain)->get(route('students.show', $near))->assertInertia(fn (Assert $page) => $page->where('student.documents', null));

    $uploaderOnly = $this->staffWith(['students.view', 'students.documents']);
    $this->actingAs($uploaderOnly)->get(route('students.show', $near))
        ->assertInertia(fn (Assert $page) => $page->has('student.documents', 1)->where('student.documents.0.view_url', null));

    $farDocument = StudentDocument::query()->where('student_id', $far->id)->sole();
    $mainReader = $this->staffWith(['students.view', 'students.view_sensitive', 'students.documents'], Scope::campuses([$this->main->ulid]));
    $signed = URL::temporarySignedRoute('students.documents.show', now()->addMinutes(5), ['document' => $farDocument->ulid]);

    $this->actingAs($mainReader)->get($signed)->assertNotFound();
    $this->post(route('students.documents.verify', $farDocument))->assertNotFound();
    $this->post(route('students.documents.store', $far), ['kind' => 'photo', 'file' => UploadedFile::fake()->image('p.jpg')])->assertNotFound();

    $this->actingAs($plain)->get(URL::temporarySignedRoute('students.documents.show', now()->addMinutes(5), ['document' => StudentDocument::query()->where('student_id', $near->id)->value('ulid')]))->assertForbidden();
});
