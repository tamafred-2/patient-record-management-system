<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class PasswordController extends Controller
{
    public function edit()
    {
        return view('auth.change-password');
    }

    private function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'max:1024'],
            'password' => ['required', 'string', 'confirmed', Password::min(12), 'max:72', function ($attribute, $value, $fail) {
                if (str_contains($value, "\0")) {
                    $fail('The password cannot contain null characters.');
                }
                if (strlen($value) > 72) {
                    $fail('The password must be no more than 72 bytes.');
                }
            }],
        ];
    }

    private function rotate(User $user, string $password, bool $temporary): void
    {
        if (Hash::check($password, $user->password)) {
            throw ValidationException::withMessages(['password' => 'Choose a password different from the existing password.']);
        }
        $user->password = $password;
        $user->must_change_password = $temporary;
        $user->password_version++;
        $user->remember_token = Str::random(60);
        $user->save();
        if (config('session.driver') === 'database') {
            DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }
    }

    public function reset(Request $request, User $user)
    {
        Gate::authorize('users.manage');
        Gate::authorize('roles.manage');
        abort_if($user->is($request->user()), 403, 'Use Change password for your own account.');
        $data = $request->validate($this->rules() + ['confirm' => ['accepted'], 'password_version' => ['required', 'integer', 'min:0']]);
        DB::transaction(function () use ($request, $user, $data) {
            if (! Hash::check($data['current_password'], $request->user()->fresh()->password)) {
                throw ValidationException::withMessages(['current_password' => 'Your current password is incorrect.']);
            }
            $target = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($target->password_version !== (int) $data['password_version']) {
                throw ValidationException::withMessages(['password_version' => 'This password was already changed. Reload the user page before resetting it.']);
            }
            $this->rotate($target, $data['password'], true);
            activity('staff')->causedBy($request->user())->performedOn($target)
                ->withProperties(['must_change_password' => true])->log('staff.password_reset');
        });

        return redirect()->route('staff.edit', $user)->with('status', 'Password reset. Share the temporary password through your approved private channel. A password change is required at sign-in.');
    }

    public function update(Request $request)
    {
        $data = $request->validate($this->rules());
        $user = DB::transaction(function () use ($request, $data) {
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            if ($user->password_version !== (int) $request->session()->get('auth_password_version', 0) || ! Hash::check($data['current_password'], $user->password)) {
                throw ValidationException::withMessages(['current_password' => 'Your current password is incorrect or has changed. Sign in again if needed.']);
            }
            $this->rotate($user, $data['password'], false);
            activity('staff')->causedBy($user)->performedOn($user)->withProperties(['must_change_password' => false])->log('staff.password_changed');

            return $user;
        });
        auth()->setUser($user);
        $request->session()->regenerate();
        $request->session()->regenerateToken();
        $request->session()->put('auth_password_version', $user->password_version);
        $request->session()->forget('url.intended');

        return redirect()->route('dashboard')->with('status', 'Password changed. Other sessions have been signed out.');
    }
}
