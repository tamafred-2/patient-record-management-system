<x-layout title="Follow-up tests">
    <x-back-link href="{{ route($isDoctor ? 'consultations.show' : 'assessments.edit', [$patient, $visit]) }}">Back to {{ $isDoctor ? 'doctor visit' : 'assessment' }}</x-back-link>
    <h1 class="mt-5 text-3xl font-semibold">Follow-up tests</h1>
    <p class="mt-3 font-medium">{{ $patient->full_name }} &middot; {{ $patient->patient_number }}</p>
    <p class="mt-1 text-sm text-slate-600">{{ $visit->visit_number }} &middot; {{ $visit->visit_date->format('M j, Y') }} &middot; {{ $visit->service->name }} &middot; Queue: {{ $visit->queue_reference ?? 'Not recorded' }}</p>
    <p class="mt-4 text-sm text-slate-600">Doctors order laboratory tests directly. Nurses whom a doctor has authorized can order permitted tests for patients they assessed. Laboratory staff record availability and results. The original visit service stays unchanged.</p>
    @if($errors->any())<x-alert type="error" class="mt-5">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach<p class="mt-2"><a class="underline" href="{{ route('followups.show', [$patient, $visit]) }}">Reload current assessment and authorizations</a></p></x-alert>@endif
    @php($ready = $visit->status === 'OPEN' && $visit->vitalSign)
    @unless($isDoctor && $ready)
        <section class="mt-6 rounded-xl border border-slate-200 bg-white p-5" aria-labelledby="assessment-record-title">@include('followups.assessment-record')</section>
    @endunless
    @if(!$ready)<p class="mt-5 rounded-lg bg-amber-50 p-4">New follow-up actions require an open visit with a saved assessment.</p>@endif
    @if($isDoctor && $ready)
        <div class="mt-6 grid items-start gap-6 lg:grid-cols-2">
            <section class="rounded-xl border border-slate-200 bg-white p-5" aria-labelledby="assessment-record-title">@include('followups.assessment-record')</section>
            <section class="rounded-xl border border-slate-200 bg-white p-5">
                <h2 class="text-xl font-semibold">Order laboratory tests</h2>
                <form method="POST" action="{{ route('followups.request', [$patient, $visit]) }}" class="mt-4 space-y-4">
                    @csrf
                    @include('followups.test-checklist', ['name' => 'test_names', 'id' => 'direct_tests', 'legend' => 'Tests to order'])
                    @include('followups.request-fields', ['id' => 'direct_reason'])
                    <x-button>Send tests to laboratory</x-button>
                </form>
            </section>
        </div>
    @endif
    @if(!$isDoctor && $ready)
        <section class="mt-6 rounded-xl border border-slate-200 bg-white p-5" aria-labelledby="nurse-order-title">
            <h2 id="nurse-order-title" class="text-xl font-semibold">Order follow-up laboratory tests</h2>
            @if($canOrderAsNurse)
                <p class="mt-2 text-sm">Authorized by {{ $standing->doctor->name }} &middot; <strong>Permitted tests:</strong> {{ $standing->allowedTestsLabel() }}</p>
                @if($standing->instructions)<p class="mt-1 whitespace-pre-wrap break-words text-sm text-slate-700"><strong>Doctor instructions:</strong> {{ $standing->instructions }}</p>@endif
                <form method="POST" action="{{ route('followups.request', [$patient, $visit]) }}" class="mt-4 space-y-4">
                    @csrf
                    @include('followups.test-checklist', ['name' => 'test_names', 'id' => 'standing_tests', 'legend' => 'Tests to order', 'only' => $standing->permittedTests()])
                    @include('followups.request-fields', ['id' => 'standing_reason'])
                    <x-button>Send authorized tests to laboratory</x-button>
                </form>
            @elseif($standing?->usable())
                <p class="mt-2 text-sm text-slate-600">You can order tests only for patients whose assessment you saved. Save your assessment of this patient first.</p>
            @else
                <p class="mt-2 text-sm text-slate-600">You are not currently authorized to order laboratory tests. A doctor can turn this on for you.</p>
            @endif
        </section>
    @endif
    @if($authorizations->isNotEmpty())
    <section class="mt-8">
        <h2 class="text-xl font-semibold">{{ $isDoctor ? 'Earlier visit-specific authorizations' : 'Your earlier visit-specific authorizations' }}</h2>
        <div class="mt-4 space-y-4">
            @forelse($authorizations as $authority)
                <article class="rounded-xl border border-slate-200 bg-white p-5">
                    <h3 class="text-lg font-semibold">{{ $authority->nurse->name }}</h3>
                    <p class="mt-1 text-sm"><strong>Permitted tests:</strong> {{ $authority->allowedTestsLabel() }}</p>
                    <p class="mt-2 text-sm">Authorized by {{ $authority->doctor->name }} &middot; {{ $authority->created_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}</p>
                    <p class="mt-3 whitespace-pre-wrap break-words">{{ $authority->instructions }}</p>
                    <p class="mt-3 font-semibold">{{ $authority->used_at ? 'Used - order sent to laboratory' : ($authority->revoked_at ? 'Revoked' : ($ready ? 'Unused' : 'Unavailable - visit closed or assessment missing')) }}</p>
                    @if($authority->used_at)<p class="mt-1 text-sm">Used {{ $authority->used_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}</p>@endif
                    @if($authority->revoked_at)<p class="mt-2 text-sm">Turned off by {{ $authority->revoker->name }} &middot; {{ $authority->revoked_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}</p>@if($authority->revocation_reason)<p class="mt-2 whitespace-pre-wrap break-words">{{ $authority->revocation_reason }}</p>@endif @endif
                    @if($ready && !$authority->used_at && !$authority->revoked_at)
                        @if($isDoctor)
                            <details class="mt-4"><summary class="cursor-pointer font-medium">Revoke unused authority</summary>
                                <form method="POST" action="{{ route('followups.revoke', [$patient, $visit, $authority]) }}" class="mt-3 space-y-3">
                                    @csrf
                                    <label for="revoke-{{ $authority->id }}" class="block">Reason for revocation (optional)</label><textarea id="revoke-{{ $authority->id }}" name="revocation_reason" maxlength="1000" rows="2" class="w-full rounded-lg border border-slate-300 p-3">{{ old('revocation_reason') }}</textarea>
                                    <label class="flex gap-3"><input type="checkbox" name="confirm" value="1" required class="mt-1"><span>Withdraw this unused authorization.</span></label>
                                    <x-button>Revoke authority</x-button>
                                </form>
                            </details>
                        @endif
                        @if($authority->nurse_id === auth()->id() && auth()->user()->can('followups.request'))
                            <form method="POST" action="{{ route('followups.request', [$patient, $visit]) }}" class="mt-4 space-y-4 border-t border-slate-100 pt-4">
                                @csrf
                                <input type="hidden" name="authorization_id" value="{{ $authority->id }}">
                                @include('followups.test-checklist', ['name' => 'test_names', 'id' => 'authorized-tests-'.$authority->id, 'legend' => 'Tests to order', 'only' => $authority->permittedTests()])
                                @include('followups.request-fields', ['id' => 'reason-'.$authority->id])
                                <x-button>Send authorized tests to laboratory</x-button>
                            </form>
                        @endif
                    @endif
                </article>
            @empty<p class="text-slate-600">No nurse authorizations recorded.</p>@endforelse
        </div>
    </section>
    @endif
    <section class="mt-8">
        <h2 class="text-xl font-semibold">{{ $isDoctor ? 'Follow-up requests' : 'Your follow-up requests' }}</h2>
        <div class="mt-4 space-y-4">@forelse($records->groupBy(fn ($entry) => $entry->order_reference ?? 'record-'.$entry->id) as $order => $entries)
            <article class="rounded-xl border border-slate-200 bg-white p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <h3 class="text-lg font-semibold">{{ $entries->count() === 1 ? 'Order: 1 test' : 'Order: '.$entries->count().' tests' }}</h3>
                    @if($entries->first()->order_reference)<a href="{{ route('followups.slip', [$patient, $visit, $entries->first()->order_reference]) }}" class="rounded-lg border border-emerald-800 px-4 py-2 text-sm font-semibold text-emerald-800">Print lab request slip</a>@endif
                </div>
                <ul class="mt-3 space-y-1">@foreach($entries as $item)<li><h4 class="font-semibold">{{ $item->test_name }} &middot; {{ App\Models\LaboratoryRecord::STATUSES[$item->availability_status] }}</h4>@if($item->availability_status === 'EXTERNAL_ADVISED' && $isDoctor)<a href="{{ route('prints.external-lab', [$patient, $visit, $item]) }}" class="text-sm font-semibold text-emerald-800 underline">Print external laboratory request</a>@endif</li>@endforeach</ul>
                @include('laboratory.request-origin', ['entry' => $entries->first()])
            </article>
        @empty<p class="text-slate-600">No follow-up tests requested.</p>@endforelse</div>
    </section>
</x-layout>
