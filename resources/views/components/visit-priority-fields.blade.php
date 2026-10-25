@props(['visit' => null, 'patient' => null, 'idPrefix' => 'priority'])
@php
    $patient ??= $visit?->patient;
    $seniorDate = $patient?->birth_date?->copy()->addYears(60)->toDateString();
    $date = $visit?->visit_date?->toDateString() ?? old('visit_date', now('Asia/Manila')->toDateString());
    $senior = is_string($date) && $seniorDate && $date >= $seniorDate;
    $level = old('priority', $visit?->priority ?? 'REGULAR');
    $reason = old('priority_reason', $visit?->priority_reason);
    $note = old('priority_note', $visit?->priority_note);
    if ($senior && $level === 'REGULAR') $level = 'PRIORITY';
    if ($senior && !$reason) $reason = 'SENIOR';
@endphp
<fieldset x-data="{ level: @js($level), reason: @js($reason ?? ''), senior: @js((bool) $senior), seniorDate: @js($seniorDate),
    updateDate(date) {
        this.senior = !!this.seniorDate && !!date && date >= this.seniorDate;
        if (this.senior) {
            if (this.level === 'REGULAR') this.level = 'PRIORITY';
            if (!this.reason) this.reason = 'SENIOR';
        }
    }
}" @if(!$visit) @input.window="if ($event.target.name === 'visit_date') updateDate($event.target.value)" @endif {{ $attributes }}>
    <legend class="mb-2 text-sm font-semibold">Visit priority</legend>
    <div class="flex flex-wrap gap-3">
        @foreach(\App\Models\Visit::PRIORITY_LABELS as $value => $label)
            <label @if($value === 'REGULAR') x-show="!senior" @if($senior) x-cloak @endif @endif class="flex items-center gap-2 rounded-lg border px-3 py-2 text-sm font-medium {{ $value === 'EMERGENCY' ? 'border-red-300' : ($value === 'PRIORITY' ? 'border-amber-300' : 'border-slate-300') }}">
                <input type="radio" name="priority" value="{{ $value }}" x-model="level" @if($value === 'REGULAR') :disabled="senior" @disabled($senior) @endif @checked($level === $value)>{{ $label }}
            </label>
        @endforeach
    </div>
    <p class="mt-2 text-sm text-slate-600">Emergency and Priority visits are listed first in every queue. Workflow steps stay the same.</p>
    <p x-show="senior" @if(!$senior) x-cloak @endif class="mt-2 text-sm font-semibold text-amber-800">Age 60 or older on the visit date: Priority is selected automatically. Choose Emergency only when appropriate; Regular is unavailable.</p>
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
