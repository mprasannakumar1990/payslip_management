<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get('/employees')->assertRedirect(route('login'));
    }

    public function test_admin_cannot_reach_super_admin_pages(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get('/employees')->assertOk();
        $this->actingAs($admin)->get('/payroll')->assertOk();
        $this->actingAs($admin)->get('/users')->assertForbidden();
        $this->actingAs($admin)->get('/settings')->assertForbidden();
    }

    public function test_super_admin_can_manage_accounts(): void
    {
        $sa = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($sa)->get('/users')->assertOk();
        $this->actingAs($sa)->post('/users', [
            'name' => 'New Admin', 'email' => 'new@example.com', 'role' => 'admin',
            'password' => 'Secret123', 'password_confirmation' => 'Secret123', 'is_active' => 1,
        ])->assertRedirect(route('users.index'));
        $this->assertDatabaseHas('users', ['email' => 'new@example.com', 'role' => 'admin']);
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        User::factory()->create(['email' => 'off@example.com', 'password' => 'Secret123', 'is_active' => false]);
        $this->post('/login', ['email' => 'off@example.com', 'password' => 'Secret123'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
