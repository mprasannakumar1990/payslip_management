<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Super Admin: manage Admin / Super Admin login accounts. */
class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::query()
            ->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('email', 'like', "%$s%")))
            ->when($request->role, fn ($q, $r) => $q->where('role', $r))
            ->orderBy('role', 'desc')->orderBy('name')->paginate(20)->withQueryString();

        return view('users.index', compact('users'));
    }

    public function create()
    {
        return view('users.form', ['user' => new User(['role' => 'admin', 'is_active' => true])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $user = User::create($data);
        ActivityLog::record('user', "Created {$user->roleLabel()} account {$user->email}");

        return redirect()->route('users.index')->with('success', 'Account created.');
    }

    public function edit(User $user)
    {
        return view('users.form', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validated($request, $user);
        if (empty($data['password'])) {
            unset($data['password']);
        }
        if ($user->id === auth()->id()) {
            // a super admin cannot demote or deactivate themselves
            $data['role'] = $user->role;
            $data['is_active'] = true;
        }
        $user->update($data);
        ActivityLog::record('user', "Updated account {$user->email}");

        return redirect()->route('users.index')->with('success', 'Account updated.');
    }

    public function toggle(User $user)
    {
        abort_if($user->id === auth()->id(), 422, 'You cannot deactivate your own account.');
        $user->update(['is_active' => ! $user->is_active]);
        ActivityLog::record('user', ($user->is_active ? 'Activated' : 'Deactivated')." account {$user->email}");

        return back()->with('success', 'Account '.($user->is_active ? 'activated' : 'deactivated').'.');
    }

    public function destroy(User $user)
    {
        abort_if($user->id === auth()->id(), 422, 'You cannot delete your own account.');
        if ($user->isSuperAdmin() && User::where('role', User::ROLE_SUPER_ADMIN)->count() <= 1) {
            return back()->with('error', 'At least one Super Admin account must remain.');
        }
        $user->delete();
        ActivityLog::record('user', "Deleted account {$user->email}");

        return back()->with('success', 'Account deleted.');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN])],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
