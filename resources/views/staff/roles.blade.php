<fieldset>
    <legend class="mb-3 text-sm font-semibold">Roles (select at least one)</legend>
    <div class="grid gap-3 sm:grid-cols-2">
        @foreach($roles as $role)
            <label class="flex items-center gap-3 rounded-lg border border-slate-200 p-3 text-sm">
                <input type="checkbox" name="roles[]" value="{{ $role->name }}" @checked(in_array($role->name, (array) old('roles', $selectedRoles ?? []))) class="h-4 w-4 accent-emerald-800">{{ $role->name }}
            </label>
        @endforeach
    </div>
    @foreach($errors->get('roles') as $error)<p role="alert" class="mt-2 text-sm text-red-700">{{ $error }}</p>@endforeach
    @foreach($errors->get('roles.*') as $messages)@foreach($messages as $message)<p role="alert" class="mt-2 text-sm text-red-700">{{ $message }}</p>@endforeach
@endforeach
    <p class="mt-3 text-sm text-slate-500">System Admin manages application access. Clinical duties require separately assigned roles.</p>
</fieldset>
