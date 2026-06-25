<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Users\UserForm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserFormModalRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_index_opens_create_modal_via_dedicated_event(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee("\$dispatch('create-user')", false);
    }

    public function test_user_form_create_event_resets_state_and_dispatches_modal_open(): void
    {
        $admin = User::factory()->create();
        $editedUser = User::factory()->create([
            'name' => 'Existing User',
            'username' => 'existing-user',
            'email' => 'existing@example.com',
            'role' => UserRole::ADMIN_RNI,
        ]);

        $this->actingAs($admin);

        Livewire::test(UserForm::class)
            ->call('edit', $editedUser)
            ->assertSet('isEditing', true)
            ->dispatch('create-user')
            ->assertSet('user', null)
            ->assertSet('isEditing', false)
            ->assertSet('name', null)
            ->assertSet('username', null)
            ->assertSet('email', null)
            ->assertSet('password', null)
            ->assertSet('password_confirmation', null)
            ->assertSet('role', UserRole::FORMULATOR->value)
            ->assertDispatched('open-modal');
    }
}
