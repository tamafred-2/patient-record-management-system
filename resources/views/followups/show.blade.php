<x-layout title="Laboratory orders">
    <x-back-link :href="route($isDoctor ? 'consultations.show' : 'assessments.edit', [$patient, $visit])">Back to {{ $isDoctor ? 'doctor visit' : 'assessment' }}</x-back-link>
    <h1 class="mt-5 text-3xl font-semibold">Laboratory orders</h1>
    <p class="mt-3 font-medium">{{ $patient->full_name }} &middot; {{ $patient->patient_number }}</p>
    <p class="mt-1 text-sm text-slate-600">{{ $visit->visit_number }} &middot; {{ $visit->visit_date->format('M j, Y') }} &middot; {{ $visit->service->name }} &middot; Queue: {{ $visit->queue_reference ?? 'Not recorded' }}</p>
    <p class="mt-4 text-sm text-slate-600">Doctors and nurses order laboratory tests here. Each order records who placed it. Laboratory staff record availability and results. The original visit service stays unchanged.</p>
    @if($errors->any())<x-alert type="error" class="mt-5">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach<p class="mt-2"><a class="underline" href="{{ route('followups.show', [$patient, $visit]) }}">Reload the current assessment</a></p></x-alert>@endif
    @php($ready = $visit->isOpen() && $visit->vitalSign)
    <div class="mt-6 grid items-start gap-6 lg:grid-cols-2">
        <section class="rounded-xl border border-slate-200 bg-white p-5" aria-labelledby="assessment-record-title">@include('followups.assessment-record')</section>
        <section class="rounded-xl border border-slate-200 bg-white p-5" aria-labelledby="order-title">
            <h2 id="order-title" class="text-xl font-semibold">Order laboratory tests</h2>
            @if($ready)
                <form method="POST" action="{{ route('followups.request', [$patient, $visit]) }}" class="mt-4 space-y-4">
                    @csrf
                    @include('followups.test-checklist', ['name' => 'test_names', 'id' => 'order_tests', 'legend' => 'Tests to order'])
                    @include('followups.request-fields', ['id' => 'order_reason'])
                    <x-button>Send tests to laboratory</x-button>
                </form>
            @else
                <p class="mt-3 rounded-lg bg-amber-50 p-4 text-sm">Laboratory orders require an open visit with a saved assessment.</p>
            @endif
        </section>
    </div>
    <section class="mt-8">
        <h2 class="text-xl font-semibold">Orders for this visit</h2>
        <div class="mt-4 space-y-4">@forelse($records->groupBy(fn ($entry) => $entry->order_reference ?? 'record-'.$entry->id) as $order => $entries)
            <article class="rounded-xl border border-slate-200 bg-white p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <h3 class="text-lg font-semibold">{{ $entries->count() === 1 ? 'Order: 1 test' : 'Order: '.$entries->count().' tests' }}</h3>
                    @if($entries->first()->order_reference)<x-button variant="secondary" :href="route('followups.slip', [$patient, $visit, $entries->first()->order_reference])">Print lab request slip</x-button>@endif
                </div>
                <ul class="mt-3 space-y-1">@foreach($entries as $item)<li><h4 class="font-semibold">{{ $item->test_name }} &middot; {{ App\Models\LaboratoryRecord::STATUSES[$item->availability_status] }}</h4>@if($item->availability_status === 'EXTERNAL_ADVISED' && $isDoctor)<a href="{{ route('prints.external-lab', [$patient, $visit, $item]) }}" class="text-sm font-semibold text-emerald-800 underline">Print external laboratory request</a>@endif</li>@endforeach</ul>
                @include('laboratory.request-origin', ['entry' => $entries->first()])
            </article>
        @empty<p class="text-slate-600">No laboratory tests ordered for this visit.</p>@endforelse</div>
    </section>
</x-layout>
