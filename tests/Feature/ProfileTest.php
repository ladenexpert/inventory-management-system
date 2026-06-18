<?php

namespace Tests\Feature;

use App\Livewire\Profile\EditProfile;
use App\Livewire\Profile\UpdatePassword;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(EditProfile::class)
            ->set('name', 'Test User')
            ->set('username', 'test-user')
            ->set('email', 'test@example.com')
            ->call('updateProfile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test-user', $user->username);
        $this->assertSame('test@example.com', $user->email);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(EditProfile::class)
            ->set('name', 'Test User')
            ->set('username', $user->username)
            ->set('email', $user->email)
            ->call('updateProfile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_password_can_be_updated_from_profile(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(UpdatePassword::class)
            ->set('current_password', 'password')
            ->set('password', 'new-secure-password')
            ->set('password_confirmation', 'new-secure-password')
            ->call('updatePassword')
            ->assertHasNoErrors();
    }

    public function test_correct_current_password_must_be_provided_to_update_password(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(UpdatePassword::class)
            ->set('current_password', 'wrong-password')
            ->set('password', 'new-secure-password')
            ->set('password_confirmation', 'new-secure-password')
            ->call('updatePassword')
            ->assertHasErrors('current_password');
    }
}
