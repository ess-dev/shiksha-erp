<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\HasCurrentSchool;
use Tests\TestCase;

class PasswordConfirmationTest extends TestCase
{
    use HasCurrentSchool;
    use RefreshDatabase;

    public function test_confirm_password_screen_can_be_rendered()
    {
        $user = $this->staff();

        $response = $this->actingAs($user)->get(route('password.confirm'));

        $response->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('auth/confirm-password'),
        );
    }

    public function test_password_confirmation_requires_authentication()
    {
        $response = $this->get(route('password.confirm'));

        $response->assertRedirect(route('login'));
    }

    public function test_password_can_be_confirmed_with_the_correct_password_only()
    {
        $user = $this->staff();

        $this->actingAs($user)
            ->post(route('password.confirm.store'), ['password' => 'wrong-password'])
            ->assertSessionHasErrors('password');

        $this->actingAs($user)
            ->post(route('password.confirm.store'), ['password' => 'password'])
            ->assertSessionHasNoErrors();
    }
}
