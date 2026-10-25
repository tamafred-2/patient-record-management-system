<x-layout title="Manage nurse authorization">
    @php
        $usable = $current?->usable();
        $setupOpen = $errors->hasAny(['test_scope', 'allowed_tests', 'allowed_tests.*', 'instructions']) || (! $current && $eligible);
        $scope = $errors->any() ? old('test_scope', 'SELECTED') : ($current?->allow_any_test ? 'ANY' : 'SELECTED');
    @endphp
    <x-back-link href="{{ route('nurse-authorizations.index') }}">Back to nurse authorization</x-back-link>
    <h1 class="mt-5 text-3xl font-semibold">{{ $nurse->name }}</h1>
    <p class="mt-1 text-slate-600">Laboratory ordering access for follow-up after assessment</p>
    @if($errors->any())<x-alert type="error" class="mt-5">{{ $errors->first() }}</x-alert>@endif

    <section class="mt-6 rounded-xl border {{ $current ? 'border-emerald-300' : 'border-slate-200' }} bg-white p-5" aria-labelledby="access-title" x-data="{ setup: @js($setupOpen) }">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <h2 id="access-title" class="text-xl font-semibold">Laboratory ordering</h2>
                @if($current)
                    <p class="mt-1 text-sm {{ $usable && $eligible ? 'text-emerald-800' : 'text-amber-800' }}">
                        {{ ! $eligible ? 'On, but this account is not currently an active nurse with follow-up access. Turn off to withdraw it.' : ($usable ? 'On' : 'On, but not usable: the authorizing doctor no longer has access. Save the tests again or turn off.') }}
                        &middot; turned on by {{ $current->doctor->name }} &middot; {{ $current->created_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}
                    </p>
                    <p class="mt-3 text-sm"><strong>Permitted tests:</strong> {{ $current->allowedTestsLabel() }}</p>
                    @if($current->instructions)<p class="mt-1 whitespace-pre-wrap break-words text-sm text-slate-700"><strong>Instructions:</strong> {{ $current->instructions }}</p>@endif
                @else
                    <p class="mt-1 text-sm text-slate-600">Off{{ $eligible ? '' : ' · this account is not currently an active nurse with follow-up access' }}</p>
                @endif
            </div>
            @if($current)
                <form method="POST" action="{{ route('nurse-authorizations.destroy', $nurse) }}">
                    @csrf @method('DELETE')
                    <button type="submit" role="switch" aria-checked="true" aria-label="Laboratory ordering for {{ $nurse->name }}" title="Turn off" class="relative inline-flex h-8 w-14 shrink-0 items-center rounded-full bg-emerald-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"><span class="sr-only">Turn off</span><span aria-hidden="true" class="absolute left-7 h-6 w-6 rounded-full bg-white shadow"></span></button>
                </form>
            @elseif($eligible)
                <button type="button" role="switch" aria-checked="false" aria-label="Laboratory ordering for {{ $nurse->name }}" aria-controls="access-setup" :aria-expanded="setup.toString()" @click="setup = !setup" class="relative inline-flex h-8 w-14 shrink-0 items-center rounded-full bg-slate-300 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"><span class="sr-only">Turn on</span><span aria-hidden="true" class="absolute left-1 h-6 w-6 rounded-full bg-white shadow"></span></button>
            @endif
        </div>
        @if($eligible)
            @if($current)<button type="button" @click="setup = !setup" :aria-expanded="setup.toString()" aria-controls="access-setup" class="mt-4 text-sm font-semibold text-emerald-800 underline">Change permitted tests</button>@endif
            <form id="access-setup" x-show="setup" x-cloak method="POST" action="{{ route('nurse-authorizations.store', $nurse) }}" class="mt-4 space-y-4 border-t border-slate-100 pt-4">
                @csrf @method('PUT')
                <div x-data="{ scope: @js($scope) }" class="space-y-3">
                    <fieldset><legend class="mb-2 font-medium">Tests {{ $nurse->name }} may order</legend>
                        <label class="flex items-center gap-2"><input type="radio" name="test_scope" value="SELECTED" x-model="scope">Only the tests I select</label>
                        <label class="mt-1 flex items-center gap-2"><input type="radio" name="test_scope" value="ANY" x-model="scope">Any test in the laboratory catalog</label>
                    </fieldset>
                    <div x-show="scope === 'SELECTED'" x-effect="$el.querySelectorAll('input').forEach((input) => input.disabled = scope !== 'SELECTED')">
                        @include('followups.test-checklist', ['name' => 'allowed_tests', 'id' => 'allowed-tests', 'legend' => 'Permitted tests', 'checked' => $errors->any() ? null : ($current?->allowed_tests ?? [])])
                    </div>
                </div>
                <div><label for="instructions" class="mb-2 block font-medium">Instructions (optional)</label><textarea id="instructions" name="instructions" maxlength="2000" rows="2" class="w-full rounded-lg border border-slate-300 p-3">{{ $errors->any() ? old('instructions') : $current?->instructions }}</textarea></div>
                <x-button>{{ $current ? 'Save and notify '.$nurse->name : 'Turn on and notify '.$nurse->name }}</x-button>
            </form>
        @endif
    </section>

    <section class="mt-6 rounded-xl border border-slate-200 bg-white p-5" aria-labelledby="older-title">
        <h2 id="older-title" class="text-xl font-semibold">Older visit-specific authorizations</h2>
        <p class="mt-1 text-sm text-slate-600">Unused one-order grants created before standing access. The nurse can still use them once; withdraw any that are no longer needed.</p>
        @forelse($olderGrants as $grant)
            <article class="mt-4 flex flex-wrap items-start justify-between gap-3 border-t border-slate-100 pt-4">
                <div class="min-w-0 text-sm">
                    <p class="font-semibold">{{ $grant->visit->patient?->full_name ?? 'Patient record unavailable' }} &middot; {{ $grant->visit->visit_number }} &middot; {{ $grant->visit->status }}</p>
                    <p class="mt-1"><strong>Permitted tests:</strong> {{ $grant->allowedTestsLabel() }} &middot; by {{ $grant->doctor->name }}, {{ $grant->created_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}</p>
                    @if($grant->instructions)<p class="mt-1 whitespace-pre-wrap break-words text-slate-700">{{ $grant->instructions }}</p>@endif
                </div>
                <form method="POST" action="{{ route('nurse-authorizations.withdraw-visit-grant', [$nurse, $grant]) }}">@csrf<x-button variant="danger">Withdraw</x-button></form>
            </article>
        @empty
            <p class="mt-3 text-sm text-slate-600">None.</p>
        @endforelse
    </section>

    <section class="mt-6 rounded-xl border border-slate-200 bg-white p-5" aria-labelledby="history-title">
        <h2 id="history-title" class="text-xl font-semibold">Change history</h2>
        <ol class="mt-3 space-y-3 text-sm">
            @forelse($history as $entry)
                <li class="border-t border-slate-100 pt-3">
                    <p><strong>{{ $entry->allowedTestsLabel() }}</strong></p>
                    <p class="text-slate-600">Turned on by {{ $entry->doctor->name }} &middot; {{ $entry->created_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}
                        @if($entry->revoked_at) &middot; ended by {{ $entry->revoker?->name }} &middot; {{ $entry->revoked_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}@else &middot; current @endif</p>
                </li>
            @empty
                <li class="text-slate-600">Laboratory ordering has never been turned on for this nurse.</li>
            @endforelse
        </ol>
    </section>
</x-layout>
