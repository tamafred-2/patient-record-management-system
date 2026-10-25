<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\AccountRetirement;
use App\Support\AccountRoles;
use App\Support\ListFilters;
use App\Support\TableSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class StaffController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['search' => ListFilters::search(), 'role' => ['nullable', Rule::in(array_keys(AccountRoles::ADDITIONAL))], 'status' => ['nullable', 'in:active,inactive'], 'activity' => ['nullable', 'in:recent,offline']]);
        $query = User::with('roles');
        TableSearch::apply($query, $filters['search'] ?? '', ['name', 'email']);
        if (! empty($filters['role'])) {
            $query->role($filters['role']);
        }
        if (! empty($filters['status'])) {
            $query->where('is_active', $filters['status'] === 'active');
        }
        if (($filters['activity'] ?? '') === 'recent') {
            $query->where('is_active', true)->where('last_active_at', '>', now()->subMinutes(5));
        }
        if (($filters['activity'] ?? '') === 'offline') {
            $query->where(fn ($query) => $query->where('is_active', false)->orWhereNull('last_active_at')->orWhere('last_active_at', '<=', now()->subMinutes(5)));
        }

        return view('staff.index', ['users' => $query->orderBy('name')->orderBy('id')->scrollPaginate()]);
    }

    public function create()
    {
        return view('staff.create', ['roles' => Role::where('guard_name', 'web')->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => Str::lower(trim($request->input('email')))]);
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'not_regex:/\d/'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8), 'max:72'],
            'primary_role' => ['required', Rule::in(array_keys(AccountRoles::ADDITIONAL))],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', 'string', 'distinct', Rule::exists('roles', 'name')->where('guard_name', 'web')],
        ], [
            'name.not_regex' => 'The name must not contain numbers.',
        ]);

        $this->validateRoleSelection($data['primary_role'], $data['roles']);

        DB::transaction(function () use ($data, $request) {
            $user = new User(collect($data)->only(['name', 'email', 'password'])->all());
            $user->is_active = true;
            $user->primary_role = $data['primary_role'];
            $user->must_change_password = true;
            $user->save();
            $user->syncRoles($data['roles']);
            activity('staff')->causedBy($request->user())->performedOn($user)
                ->withProperties(['primary_role' => $user->primary_role, 'roles' => $data['roles'], 'is_active' => true])->log('staff.created');
        });

        return redirect()->route('staff.index')->with('status', 'User account created. Share credentials through your approved private channel.');
    }

    public function edit(Request $request, User $user)
    {
        $generatedPassword = null;
        $reset = $request->session()->pull('generated_reset');
        if ($reset && $reset['target_id'] === $user->id && $reset['actor_id'] === $request->user()->id
            && $reset['password_version'] === $user->password_version) {
            $generatedPassword = Crypt::decryptString($reset['secret']);
        }

        return view('staff.edit', ['generatedPassword' => $generatedPassword, 'staff' => $user->load('roles'), 'roles' => Role::where('guard_name', 'web')->orderBy('name')->get()]);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'primary_role' => ['sometimes', 'required', Rule::in(array_keys(AccountRoles::ADDITIONAL))],
            'is_active' => ['required', 'boolean'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', 'string', 'distinct', Rule::exists('roles', 'name')->where('guard_name', 'web')],
        ]);

        DB::transaction(function () use ($data, $user, $request) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $currentRoles = $user->getRoleNames();
            $primary = $user->primary_role ?? ($currentRoles->count() === 1 ? $currentRoles->first() : null);
            if ($primary && isset($data['primary_role']) && $data['primary_role'] !== $primary) {
                throw ValidationException::withMessages(['primary_role' => 'The primary role is locked and cannot be changed.']);
            }
            if (! $primary) {
                $primary = $data['primary_role'] ?? null;
                if (! $primary || ($currentRoles->isNotEmpty() && ! $currentRoles->contains($primary))) {
                    throw ValidationException::withMessages(['primary_role' => 'Choose the primary role from this account’s existing roles. This choice will be permanent.']);
                }
            }
            $this->validateRoleSelection($primary, $data['roles']);
            if ($user->is($request->user())) {
                if (! $data['is_active']) {
                    throw ValidationException::withMessages(['is_active' => 'Your own account must remain active.']);
                }
                if ($user->hasRole('System Admin') && ! in_array('System Admin', $data['roles'], true)) {
                    throw ValidationException::withMessages(['roles' => 'Keep System Admin assigned to your own account.']);
                }
            }
            $before = ['primary_role' => $user->primary_role, 'roles' => $currentRoles->all(), 'is_active' => $user->is_active];
            $user->primary_role = $primary;
            $user->is_active = (bool) $data['is_active'];
            $user->save();
            $user->syncRoles($data['roles']);
            if (! $user->is_active && config('session.driver') === 'database') {
                DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
            }
            activity('staff')->causedBy($request->user())->performedOn($user)
                ->withProperties(['before' => $before, 'after' => ['primary_role' => $primary, 'roles' => $data['roles'], 'is_active' => $user->is_active]])->log('staff.access_updated');
        });

        return redirect()->route('staff.index')->with('status', 'User access updated.');
    }

    public function retire(Request $request, User $user)
    {
        $data = $request->validate(['confirm' => ['accepted'], 'action' => ['required', 'in:deactivate,delete']]);
        DB::transaction(function () use ($request, $user, $data) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($user->is($request->user())) {
                throw ValidationException::withMessages(['account' => 'You cannot retire your own account.']);
            }
            if ($data['action'] === 'delete' && AccountRetirement::hasLinkedRecords($user)) {
                throw ValidationException::withMessages(['account' => 'This account has linked records or activity history. Deactivate it to preserve attribution.']);
            }
            $before = ['is_active' => $user->is_active, 'roles' => $user->getRoleNames()->all()];
            $user->is_active = false;
            AccountRetirement::endSessions($user);
            $user->syncRoles([]);
            $user->syncPermissions([]);
            activity('staff')->causedBy($request->user())->performedOn($user)
                ->withProperties(['before' => $before, 'action' => $data['action']])->log('staff.retired');
            if ($data['action'] === 'delete') {
                $user->notifications()->delete();
                $user->delete();
            }
        });

        return redirect()->route('staff.index')->with('status', $data['action'] === 'delete' ? 'Unlinked account deleted.' : 'Account deactivated and all roles and direct permissions removed.');
    }

    private function validateRoleSelection(string $primary, array $roles): void
    {
        if (! array_key_exists($primary, AccountRoles::ADDITIONAL)
            || ! in_array($primary, $roles, true)
            || array_diff($roles, [$primary, ...AccountRoles::ADDITIONAL[$primary]])) {
            throw ValidationException::withMessages(['roles' => 'Keep the primary role and select only its permitted additional roles.']);
        }
    }
}
