<?php

use App\Domain\Access\Actions\CreateStaffRecord;
use App\Domain\Access\Models\Staff;
use App\Domain\Audit\Models\AuditEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\HasCurrentSchool;
use Tests\Concerns\ManagesSchoolPlan;

uses(RefreshDatabase::class, HasCurrentSchool::class, ManagesSchoolPlan::class);

beforeEach(function () {
    Storage::fake('tenant');
    $this->user = $this->staff();
    $this->record = app(CreateStaffRecord::class)->handle($this->user->ulid, 'Neha Verma');
    $this->record->forceFill(['employee_code' => 'GPS-NEHA', 'job_title' => 'Teacher', 'department' => 'Mathematics', 'joined_on' => '2021-06-14'])->save();
});

it('shows the staff profile with work details and roles', function () {
    $this->actingAs($this->user)->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/profile')
            ->where('profile.full_name', 'Neha Verma')
            ->where('profile.employee_code', 'GPS-NEHA')
            ->where('profile.joined_on', '2021-06-14')
            ->where('profile.channels', ['push' => true, 'sms' => true, 'whatsapp' => false, 'email' => true])
            ->where('profile.whatsapp_in_plan', false));
});

it('saves the details a person keeps about themselves and audits only field names', function () {
    $this->actingAs($this->user)->patch(route('profile.update'), [
        'phone' => '9876543210',
        'personal_email' => 'Neha.Personal@Example.com',
        'qualifications' => 'M.Sc. Mathematics, B.Ed.',
        'emergency_name' => 'Ramesh Verma',
        'emergency_relation' => 'Father',
        'emergency_phone' => '98765 43215',
    ])->assertSessionHasNoErrors();

    $this->record->refresh();

    expect($this->record->personal_email)->toBe('neha.personal@example.com')
        ->and($this->record->emergency_phone)->toBe('+919876543215')
        ->and($this->user->refresh()->phone_e164)->toBe('+919876543210');

    $event = AuditEvent::query()->where('action', 'staff.profile_updated')->sole();
    expect(json_encode($event->after))->toContain('personal_email')->not->toContain('Ramesh')->not->toContain('9876543215');
});

it('does not let a person change their name or work details', function () {
    $this->actingAs($this->user)->patch(route('profile.update'), [
        'phone' => '9876543210', 'full_name' => 'Someone Else', 'job_title' => 'Principal', 'employee_code' => 'X',
    ])->assertSessionHasNoErrors();

    $this->record->refresh();

    expect($this->record->full_name)->toBe('Neha Verma')
        ->and($this->record->job_title)->toBe('Teacher')
        ->and($this->record->employee_code)->toBe('GPS-NEHA');
});

it('needs both emergency contact fields or neither', function () {
    $this->actingAs($this->user)->patch(route('profile.update'), ['phone' => '9876543210', 'emergency_name' => 'Ramesh Verma'])
        ->assertSessionHasErrors('emergency_phone');
});

it('stores, streams and removes a photo on the tenant disk', function () {
    $this->actingAs($this->user)
        ->post(route('profile.photo.store'), ['photo' => UploadedFile::fake()->image('me.jpg')])
        ->assertSessionHasNoErrors();

    $path = $this->record->refresh()->photo_path;
    expect($path)->toStartWith("tenants/{$this->school->ulid}/staff/{$this->record->ulid}/");
    Storage::disk('tenant')->assertExists($path);

    $this->actingAs($this->user)->get(route('profile.photo'))->assertOk();

    $this->actingAs($this->user)->delete(route('profile.photo.destroy'))->assertSessionHasNoErrors();
    Storage::disk('tenant')->assertMissing($path);
    expect($this->record->refresh()->photo_path)->toBeNull();
    $this->actingAs($this->user)->get(route('profile.photo'))->assertNotFound();
});

it('rejects a photo that is not an image', function () {
    $this->actingAs($this->user)
        ->post(route('profile.photo.store'), ['photo' => UploadedFile::fake()->create('me.pdf', 10, 'application/pdf')])
        ->assertSessionHasErrors('photo');
});

it('saves notification channels but keeps WhatsApp off outside the plan', function () {
    $this->actingAs($this->user)->patch(route('profile.channels'), ['sms' => false, 'whatsapp' => true])->assertRedirect();

    expect(Staff::query()->find($this->record->id)->notification_channels)
        ->toEqualCanonicalizing(['push' => true, 'sms' => false, 'whatsapp' => false, 'email' => true]);

    $this->enableModules(['whatsapp']);
    $this->actingAs($this->user)->patch(route('profile.channels'), ['whatsapp' => true]);

    expect(Staff::query()->find($this->record->id)->notification_channels['whatsapp'])->toBeTrue();
});
