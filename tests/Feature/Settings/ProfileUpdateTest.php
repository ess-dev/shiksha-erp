<?php

namespace Tests\Feature\Settings;

use App\Domain\Audit\Models\PlatformAuditEvent;
use App\Domain\Identity\Models\Identity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\HasCurrentSchool;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use HasCurrentSchool;
    use RefreshDatabase;

    public function test_profile_page_is_displayed()
    {
        $this->actingAs($this->staff())
            ->get(route('profile.edit'))
            ->assertOk();
    }

    public function test_mobile_can_be_updated_and_is_normalised_but_the_work_email_is_not_editable()
    {
        $user = $this->staff();
        $email = $user->email;

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'email' => 'someone-else@example.com',
                'phone' => '98765 43210',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame($email, $user->email);
        $this->assertSame('+919876543210', $user->phone_e164);
    }

    public function test_mobile_is_required_when_there_is_no_email()
    {
        $user = Identity::factory()->create(['email' => null, 'phone_e164' => '+919000000002']);
        $this->grantStaffMembership($user);

        $this->actingAs($user)
            ->patch(route('profile.update'), ['phone' => ''])
            ->assertSessionHasErrors('phone');
    }

    public function test_invalid_phone_is_rejected()
    {
        $this->actingAs($this->staff())
            ->patch(route('profile.update'), ['phone' => '12345'])
            ->assertSessionHasErrors('phone');
    }

    public function test_mobile_must_be_unique_across_identities()
    {
        Identity::factory()->create(['email' => 'taken@example.com', 'phone_e164' => '+919000000001']);

        $this->actingAs($this->staff())
            ->patch(route('profile.update'), ['phone' => '9000000001'])
            ->assertSessionHasErrors('phone');
    }

    public function test_an_update_is_audited_without_the_values()
    {
        $user = $this->staff();

        $this->actingAs($user)->patch(route('profile.update'), ['phone' => '9876543210']);

        $event = PlatformAuditEvent::query()->where('action', 'identity.contact_updated')->sole();

        $this->assertSame($user->ulid, $event->subject_ulid);
        $this->assertStringNotContainsString('9876543210', json_encode($event->after));
    }

    public function test_there_is_no_self_service_account_deletion()
    {
        $this->assertFalse(Route::has('profile.destroy'));
    }
}
