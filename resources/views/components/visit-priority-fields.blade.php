@props(['visit' => null, 'idPrefix' => 'priority'])
@php
    $level = old('priority', $visit?->priority ?? 'REGULAR');
    $reason = old('priority_reason', $visit?->priority_reason);
    $note = old('priority_note', $visit?->priority_note);
@endphp
<fieldset x-data="{ level: @js($level), reason: @js($reason ?? '') }" {{ $attributes }}>
    <legend class="mb-2 text-sm font-semibold">Visit priority</legend>
    <div class="flex flex-wrap gap-3">
        @foreach(\App\Models\Visit::PRIORITY_LABELS as $value => $label)
            <label class="flex items-center gap-2 rounded-lg border px-3 py-2 text-sm font-medium {{ $value === 'EMERGENCY' ? 'border-red-300' : ($value === 'PRIORITY' ? 'border-amber-300' : 'border-slate-300') }}">
                <input type="radio" name="priority" value="{{ $value }}" x-model="level" @checked($level === $value)>{{ $label }}
            </label>
        @endforeach
    </div>
    <p class="mt-2 text-sm text-slate-600">Emergency and Priority visits are listed first in every queue. Workflow steps stay the same.</p>
    @error('priority')<p role="alert" class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
    <div class="mt-4" x-show="level === 'PRIORITY'" x-cloak>
        <label for="{{ $idPrefix }}_reason" class="mb-2 block text-sm font-medium">Priority reason (required for Priority)</label>
        <select id="{{ $idPrefix }}_reason" name="priority_reason" x-model="reason" :disabled="level !== 'PRIORITY'" :required="level === 'PRIORITY'" class="w-full rounded-lg border border-slate-300 p-3">
            <option value="">Select reason</option>
            @foreach(\App\Models\Visit::PRIORITY_REASONS as $value => $label)<option value="{{ $value }}" @selected($reason === $value)>{{ $label }}</option>@endforeach
        </select>
        @error('priority_reason')<p role="alert" class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>
    <div class="mt-4" x-show="level === 'PRIORITY' && reason === 'OTHER'" x-cloak>
        <label for="{{ $idPrefix }}_note" class="mb-2 block text-sm font-medium">Other reason (required)</label>
        <input id="{{ $idPrefix }}_note" name="priority_note" value="{{ $note }}" maxlength="150" :disabled="!(level === 'PRIORITY' && reason === 'OTHER')" :required="level === 'PRIORITY' && reason === 'OTHER'" class="w-full rounded-lg border border-slate-300 p-3">
        @error('priority_note')<p role="alert" class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>
</fieldset>
