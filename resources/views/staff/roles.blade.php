@php
    $matrix = \App\Support\AccountRoles::ADDITIONAL;
    $existing = isset($staff) ? $staff->getRoleNames()->all() : [];
    $lockedPrimary = isset($staff) ? ($staff->primary_role ?? (count($existing) === 1 ? $existing[0] : null)) : null;
    $choices = isset($staff) && !$lockedPrimary && count($existing) ? array_intersect(array_keys($matrix), $existing) : array_keys($matrix);
    $selectedPrimary = $lockedPrimary ?? old('primary_role', '');
    $selectedAdditional = array_values(array_diff((array) old('roles', $existing), [$selectedPrimary]));
@endphp
<fieldset x-data="{ primary: @js($selectedPrimary), selected: @js($selectedAdditional), matrix: @js($matrix) }">
    <legend class="mb-3 text-lg font-semibold">Role and additional access</legend>
    @if($lockedPrimary)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-800">Primary role - Locked</p>
            <p class="mt-1 font-semibold">{{ $lockedPrimary }}</p>
            <p class="mt-2 text-sm text-slate-600">The primary role cannot be changed or removed.</p>
        </div>
    @else
        @if(isset($staff))
            <p class="mb-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-900">This existing account needs a primary-role review. Choose its primary role once. Saving locks that choice and removes roles not selected below.</p>
            <p class="mb-3 text-sm">Currently assigned: {{ implode(', ', $existing) ?: 'None' }}</p>
        @endif
        <label for="primary_role" class="mb-2 block text-sm font-medium">Primary role</label>
        <select id="primary_role" name="primary_role" x-model="primary" @change="selected = []" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5">
            <option value="">Select a primary role</option>
            @foreach($choices as $role)<option value="{{ $role }}" @selected($selectedPrimary === $role)>{{ $role }}</option>@endforeach
        </select>
        <p class="mt-2 text-sm text-slate-600">This role becomes permanent when you save.</p>
    @endif
    <input type="hidden" name="roles[]" :value="primary" value="{{ $selectedPrimary }}">
    @error('primary_role')<p role="alert" class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
    <p class="mb-3 mt-5 text-sm font-semibold">Additional access (optional)</p>
    @foreach(['Information Staff', 'Nurse'] as $additional)
        <label x-show="(matrix[primary] || []).includes(@js($additional))" x-cloak class="mb-3 flex items-center gap-3 rounded-lg border border-slate-200 p-3 text-sm">
            <input type="checkbox" name="roles[]" value="{{ $additional }}" x-model="selected" :disabled="!(matrix[primary] || []).includes(@js($additional))" class="h-4 w-4 accent-emerald-800">{{ $additional }}
        </label>
    @endforeach
    <p x-show="!(matrix[primary] || []).length" class="text-sm text-slate-500">No additional roles are available for this primary role.</p>
    @foreach($errors->get('roles') as $error)<p role="alert" class="mt-2 text-sm text-red-700">{{ $error }}</p>@endforeach
    @foreach($errors->get('roles.*') as $messages)@foreach($messages as $message)<p role="alert" class="mt-2 text-sm text-red-700">{{ $message }}</p>@endforeach @endforeach
    <p class="mt-3 text-sm text-slate-500">System Admin may add Information Staff. Doctor / Medical Officer may add Nurse. Additional roles do not unlock further roles.</p>
</fieldset>
