@props(['patient', 'visit'])
@php
    $canSet = App\Support\FollowupAccess::doctor(auth()->user());
    $open = $errors->hasAny(['follow_up_on', 'follow_up_reason']);
@endphp
<section {{ $attributes->merge(['class' => 'rounded-xl border border-slate-200 bg-white p-5']) }} aria-labelledby="follow-up-title" x-data="{ editing: @js($open) }">
    <h2 id="follow-up-title" class="font-semibold">Follow-up return visit</h2>
    @if($visit->follow_up_on)
        <p class="mt-2"><strong>{{ $visit->follow_up_on->format('l, M j, Y') }}</strong>@if($visit->follow_up_on->toDateString() < now('Asia/Manila')->toDateString()) <span class="ml-2 rounded-full border border-amber-300 bg-amber-50 px-2 py-0.5 text-xs font-bold text-amber-900">Overdue</span>@endif</p>
        <p class="mt-1 whitespace-pre-wrap break-words text-sm">{{ $visit->follow_up_reason }}</p>
        <p class="mt-1 text-sm text-slate-500">Set by {{ $visit->followUpSetter?->name ?? 'a doctor' }} &middot; {{ $visit->follow_up_set_at?->timezone('Asia/Manila')->format('M j, Y g:i A') }}</p>
    @else
        <p class="mt-2 text-sm text-slate-600">No follow-up return visit scheduled.</p>
    @endif
    @if($canSet)
        <button type="button" @click="editing = !editing" :aria-expanded="editing.toString()" aria-controls="follow-up-form" class="mt-3 text-sm font-semibold text-emerald-800 underline">{{ $visit->follow_up_on ? 'Change follow-up' : 'Schedule follow-up' }}</button>
        <form id="follow-up-form" x-show="editing" x-cloak method="POST" action="{{ route('visits.follow-up', [$patient, $visit]) }}" class="mt-3 grid gap-3 sm:grid-cols-[12rem_1fr]">
            @csrf @method('PUT')
            <div><label for="follow_up_on" class="mb-1 block text-sm font-medium">Return date</label><input id="follow_up_on" type="date" name="follow_up_on" required min="{{ now('Asia/Manila')->toDateString() }}" max="{{ now('Asia/Manila')->addYear()->toDateString() }}" value="{{ old('follow_up_on', $visit->follow_up_on?->toDateString()) }}" class="w-full rounded-lg border border-slate-300 p-2.5">@error('follow_up_on')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror</div>
            <div><label for="follow_up_reason" class="mb-1 block text-sm font-medium">Reason / instructions for the return visit</label><input id="follow_up_reason" name="follow_up_reason" required maxlength="500" value="{{ old('follow_up_reason', $visit->follow_up_reason) }}" class="w-full rounded-lg border border-slate-300 p-2.5">@error('follow_up_reason')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror</div>
            <div class="sm:col-span-2"><x-button>Save follow-up</x-button></div>
        </form>
        @if($visit->follow_up_on)
            <form method="POST" action="{{ route('visits.follow-up', [$patient, $visit]) }}" class="mt-2">@csrf @method('PUT')<input type="hidden" name="clear" value="1"><button class="text-sm font-semibold text-red-800 underline">Remove follow-up</button></form>
        @endif
    @endif
</section>
