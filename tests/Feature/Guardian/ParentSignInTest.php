<?php

use App\Domain\Audit\Models\AuditEvent;
use App\Domain\Identity\Actions\RequestGuardianOtp;
use App\Domain\Identity\Actions\VerifyGuardianOtp;
use App\Domain\Identity\Authentication\LogOtpSender;
use App\Domain\Identity\Authentication\MailOtpSender;
use App\Domain\Identity\Authentication\SchoolContext;
use App\Domain\Identity\Contracts\OtpSender;
use App\Domain\Identity\Models\Identity;
use App\Domain\Identity\Models\Membership;
use App\Domain\Identity\Models\OtpChallenge;
use App\Domain\Students\Data\NewGuardian;
use App\Domain\Students\Models\Guardian;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsStudents;
use Tests\Concerns\HasCurrentSchool;
use Tests\Concerns\HasSecondSchool;
use Tests\Concerns\ManagesSchoolPlan;

uses(RefreshDatabase::class, HasCurrentSchool::class, HasSecondSchool::class, BuildsStudents::class, ManagesSchoolPlan::class);

beforeEach(function () {
    $this->enableModules(['attendance']);
    $this->setUpStudentSchool();

    $this->phone = $this->nextPhone();
    $this->e164 = '+91'.$this->phone;
    $this->makeStudent($this->main1A, 'Riya', 'Sharma', [new NewGuardian('Priya Sharma', 'Mother', $this->phone, 'priya@mail.example', null, true)]);

    $this->codes = new ArrayObject;
    $codes = $this->codes;
    $this->app->bind(OtpSender::class, fn () => new class($codes) implements OtpSender
    {
        public function __construct(private ArrayObject $codes) {}

        public function send(string $phoneE164, string $code, string $schoolName): void
        {
            $this->codes[] = ['phone' => $phoneE164, 'code' => $code];
        }
    });
});

function lastCode(): string
{
    return test()->codes[count(test()->codes) - 1]['code'];
}

function wrongCodeFor(string $code): string
{
    return $code === '111111' ? '222222' : '111111';
}

it('signs a guardian in with phone and one-time code, creating their identity and membership on first sign-in', function () {
    $this->post(route('parent.send-code'), ['phone' => $this->phone])->assertRedirect(route('parent.verify'));

    expect($this->codes)->toHaveCount(1)
        ->and($this->codes[0]['phone'])->toBe($this->e164)
        ->and(OtpChallenge::query()->first()->code_hash)->not->toContain(lastCode());

    $this->post(route('parent.verify.store'), ['code' => lastCode()])->assertRedirect(route('parent.home'));

    $identity = Identity::query()->where('phone_e164', $this->e164)->first();

    expect($identity)->not->toBeNull()
        ->and($identity->password)->toBeNull()
        ->and(Membership::query()->where('identity_id', $identity->id)->where('tenant_id', $this->school->id)->where('kind', 'guardian')->where('status', 'active')->exists())->toBeTrue()
        ->and(Guardian::query()->where('phone', $this->e164)->value('identity_ulid'))->toBe($identity->ulid);

    $this->assertAuthenticatedAs($identity);
    $this->get(route('parent.home'))->assertOk();

    expect(AuditEvent::query()->where('action', 'guardians.identity_linked')->exists())->toBeTrue()
        ->and(DB::connection('landlord')->table('platform_audit_events')->whereIn('action', ['guardian.otp_requested', 'guardian.otp_verified', 'identity.created', 'membership.granted'])->count())->toBe(4);
});

it('keeps the guardian signed in for the configured remember period', function () {
    $this->post(route('parent.send-code'), ['phone' => $this->phone]);
    $response = $this->post(route('parent.verify.store'), ['code' => lastCode()]);

    $recaller = collect($response->headers->getCookies())->first(fn ($cookie) => str_starts_with($cookie->getName(), 'remember_web_'));

    expect($recaller)->not->toBeNull()
        ->and($recaller->getExpiresTime() - time())->toBeGreaterThan(29 * 86400)
        ->and($recaller->getExpiresTime() - time())->toBeLessThanOrEqual(30 * 86400 + 5);
});

it('gives the same answer for an unknown phone and sends nothing', function () {
    $known = $this->post(route('parent.send-code'), ['phone' => $this->phone]);
    $unknown = $this->post(route('parent.send-code'), ['phone' => '9000000000']);

    expect($unknown->getTargetUrl())->toBe($known->getTargetUrl())
        ->and($this->codes)->toHaveCount(1)
        ->and(OtpChallenge::query()->where('phone_e164', '+919000000000')->exists())->toBeFalse();

    $this->post(route('parent.verify.store'), ['code' => '123456'])
        ->assertSessionHasErrors('code');
    $this->assertGuest();
});

it('sends nothing to a guardian who has no portal access', function () {
    $phone = $this->nextPhone();
    $this->makeStudent($this->main1A, 'Veer', 'Anand', [new NewGuardian('Seema Anand', 'Mother', $phone, null, null, true, false)]);

    $this->post(route('parent.send-code'), ['phone' => $phone])->assertRedirect(route('parent.verify'));

    expect($this->codes)->toHaveCount(0);
});

it('rejects a wrong code, locks the code after too many tries and never accepts it twice', function () {
    $this->post(route('parent.send-code'), ['phone' => $this->phone]);
    $code = lastCode();

    foreach (range(1, 5) as $ignored) {
        $this->post(route('parent.verify.store'), ['code' => wrongCodeFor($code)])->assertSessionHasErrors('code');
    }

    $this->post(route('parent.verify.store'), ['code' => $code])->assertSessionHasErrors('code');
    $this->assertGuest();

    // A fresh code works, once.
    $this->travel(31)->seconds();
    $this->post(route('parent.send-code'), ['phone' => $this->phone]);
    $fresh = lastCode();

    $first = app(VerifyGuardianOtp::class)->handle($this->school, $this->phone, $fresh);
    $second = app(VerifyGuardianOtp::class)->handle($this->school, $this->phone, $fresh);

    expect($first)->toBeInstanceOf(Identity::class)->and($second)->toBeNull();
});

it('rejects an expired code', function () {
    $this->post(route('parent.send-code'), ['phone' => $this->phone]);
    $code = lastCode();

    $this->travel(6)->minutes();

    $this->post(route('parent.verify.store'), ['code' => $code])->assertSessionHasErrors('code');
    $this->assertGuest();
});

it('limits code requests per phone, whether or not the phone is a parent', function () {
    config(['shiksha.otp.per_phone_per_hour' => 2]);

    foreach ([$this->phone, '9000000001'] as $phone) {
        $this->post(route('parent.send-code'), ['phone' => $phone])->assertSessionDoesntHaveErrors();
        $this->post(route('parent.send-code'), ['phone' => $phone])->assertSessionDoesntHaveErrors();
        $this->post(route('parent.send-code'), ['phone' => $phone])->assertSessionHasErrors('phone');
    }
});

it('limits code requests per address and per school', function () {
    config(['shiksha.otp.per_ip_per_hour' => 2]);

    $this->post(route('parent.send-code'), ['phone' => '9000000002']);
    $this->post(route('parent.send-code'), ['phone' => '9000000003']);
    $this->post(route('parent.send-code'), ['phone' => '9000000004'])->assertSessionHasErrors('phone');

    config(['shiksha.otp.per_ip_per_hour' => 100, 'shiksha.otp.per_school_per_hour' => 1]);

    $this->post(route('parent.send-code'), ['phone' => '9000000005'])->assertSessionHasErrors('phone');
});

it('refuses the log OTP driver in production', function () {
    $this->app['env'] = 'production';

    expect(fn () => (new LogOtpSender)->send($this->e164, '123456', 'School'))->toThrow(RuntimeException::class);

    $this->app['env'] = 'testing';
    Log::spy();

    (new LogOtpSender)->send($this->e164, '123456', 'School');

    Log::shouldHaveReceived('info')->once();
});

it('keeps another school out: a phone known only here sends nothing there, and a membership here is not one there', function () {
    $this->post(route('parent.send-code'), ['phone' => $this->phone]);
    app(VerifyGuardianOtp::class)->handle($this->school, $this->phone, lastCode());
    $identity = Identity::query()->where('phone_e164', $this->e164)->first();

    $this->otherSchool->makeCurrent();

    app(RequestGuardianOtp::class)->handle($this->otherSchool, $this->phone, '127.0.0.1');
    $contexts = app(SchoolContext::class)->available($identity, $this->otherSchool);

    $this->school->makeCurrent();

    expect($this->codes)->toHaveCount(1)
        ->and($contexts)->toBe([]);
});

it('lets someone who is staff and a parent use both views, signing out when going back to staff', function () {
    $staffIdentity = Identity::factory()->create(['phone_e164' => $this->e164]);
    $this->grantStaffMembership($staffIdentity);

    $this->post(route('parent.send-code'), ['phone' => $this->phone]);
    $this->post(route('parent.verify.store'), ['code' => lastCode()])->assertRedirect(route('parent.home'));

    expect(Identity::query()->where('phone_e164', $this->e164)->count())->toBe(1);

    $this->get(route('parent.home'))
        ->assertInertia(fn ($page) => $page->where('shell.has_staff_context', true));

    // The parent code proves a phone, not the staff password: going to staff signs out.
    $this->put(route('school.context.switch'), ['context' => 'staff'])->assertRedirect(route('login'));
    $this->assertGuest();

    // A staff member signed in with a password can open the parent view.
    $this->actingAs($staffIdentity)->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('schoolContext.can_open_parent_view', true));
    $this->put(route('school.context.switch'), ['context' => 'guardian'])->assertRedirect(route('parent.home'));
    $this->get(route('parent.home'))->assertOk();
    $this->get(route('dashboard'))->assertRedirect(route('parent.home'));
});

it('does not let a staff-only person into the parent view or switch to it', function () {
    $staff = $this->staff();

    $this->actingAs($staff)->get(route('parent.home'))->assertRedirect(route('dashboard'));
    $this->put(route('school.context.switch'), ['context' => 'guardian'])->assertForbidden();
});

it('keeps a signed-in parent out of the staff screens', function () {
    $this->post(route('parent.send-code'), ['phone' => $this->phone]);
    $this->post(route('parent.verify.store'), ['code' => lastCode()]);

    $this->get(route('dashboard'))->assertRedirect(route('parent.home'));
    $this->get(route('students.index'))->assertRedirect(route('parent.home'));
    $this->get(route('attendance.index'))->assertRedirect(route('parent.home'));
});

it('ends the parent session as soon as portal access is removed', function () {
    $this->post(route('parent.send-code'), ['phone' => $this->phone]);
    $this->post(route('parent.verify.store'), ['code' => lastCode()]);
    $this->get(route('parent.home'))->assertOk();

    DB::connection('tenant')->table('guardian_student')->update(['has_portal_access' => false]);

    $this->get(route('parent.home'))->assertRedirect(route('parent.sign-in'));
    $this->assertGuest();
});

it('ends the parent session when the membership is revoked', function () {
    $this->post(route('parent.send-code'), ['phone' => $this->phone]);
    $this->post(route('parent.verify.store'), ['code' => lastCode()]);

    Membership::query()->where('kind', 'guardian')->update(['status' => 'revoked']);

    $this->get(route('parent.home'))->assertRedirect(route('parent.sign-in'));
    $this->assertGuest();
});

it('signs the parent out to the parent sign-in page', function () {
    $this->post(route('parent.send-code'), ['phone' => $this->phone]);
    $this->post(route('parent.verify.store'), ['code' => lastCode()]);

    $this->post(route('parent.sign-out'))->assertRedirect(route('parent.sign-in'));
    $this->assertGuest();
    $this->get(route('parent.home'))->assertRedirect(route('parent.sign-in'));
});

it('shows the sign-in page with the school name and sends signed-in people on', function () {
    $this->get(route('parent.sign-in'))
        ->assertInertia(fn ($page) => $page->component('parent/auth/sign-in')->where('school_name', 'Test Public School'));

    $this->get(route('parent.verify'))->assertRedirect(route('parent.sign-in'));
});

it('mails the code to the guardian\'s address on file with the mail OTP driver, and refuses production', function () {
    Log::spy();

    app(MailOtpSender::class)->send($this->e164, '123456', 'Greenfield');

    $sent = Mail::mailer('array')->getSymfonyTransport()->messages();

    expect($sent)->toHaveCount(1)
        ->and($sent[0]->getOriginalMessage()->getTo()[0]->getAddress())->toBe('priya@mail.example')
        ->and($sent[0]->getOriginalMessage()->getTextBody())->toContain('123456');
    Log::shouldHaveReceived('info')->once();

    $this->app['env'] = 'production';

    expect(fn () => app(MailOtpSender::class)->send($this->e164, '123456', 'Greenfield'))->toThrow(RuntimeException::class);
});
