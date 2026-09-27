<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class StaffController extends Controller
{
    public function index()
    {
        return view('staff.index', ['users' => User::with('roles')->orderBy('name')->paginate(20)]);
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(12), 'max:72'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', 'string', 'distinct', Rule::exists('roles', 'name')->where('guard_name', 'web')],
        ]);

        DB::transaction(function () use ($data, $request) {
            $user = new User(collect($data)->only(['name', 'email', 'password'])->all());
            $user->is_active = true;
            $user->must_change_password = true;
            $user->save();
            $user->syncRoles($data['roles']);
            activity('staff')->causedBy($request->user())->performedOn($user)
                ->withProperties(['roles' => $data['roles'], 'is_active' => true])->log('staff.created');
        });

        return redirect()->route('staff.index')->with('status', 'User account created. Share credentials through your approved private channel.');
    }

    public function edit(User $user)
    {
        return view('staff.edit', ['staff' => $user->load('roles'), 'roles' => Role::where('guard_name', 'web')->orderBy('name')->get()]);
    }

    public function update(Request $request, User $user)
    {
        abort_if($user->is($request->user()), 403, 'You cannot change your own roles or account status.');
        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', 'string', 'distinct', Rule::exists('roles', 'name')->where('guard_name', 'web')],
        ]);

        DB::transaction(function () use ($data, $user, $request) {
            $before = ['roles' => $user->getRoleNames()->all(), 'is_active' => $user->is_active];
            $user->is_active = (bool) $data['is_active'];
            $user->save();
            $user->syncRoles($data['roles']);
            if (! $user->is_active && config('session.driver') === 'database') {
                DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
            }
            activity('staff')->causedBy($request->user())->performedOn($user)
                ->withProperties(['before' => $before, 'after' => ['roles' => $data['roles'], 'is_active' => $user->is_active]])->log('staff.access_updated');
        });

        return redirect()->route('staff.index')->with('status', 'User access updated.');
    }
}
