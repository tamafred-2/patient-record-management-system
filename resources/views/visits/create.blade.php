<x-layout title="Start visit">
    <a href="{{ route('patients.show', $patient) }}" class="text-emerald-800 underline">Back to patient</a>
    <h1 class="mt-5 text-3xl font-semibold">Start visit</h1>
    <p class="mt-3 font-semibold">{{ $patient->full_name }} · {{ $patient->patient_number }}</p>
    <p class="mt-2 text-slate-600">Confirm the patient identity before recording this encounter.</p>
    @if($errors->any())<div role="alert" class="mt-4 rounded-lg bg-red-50 p-4 text-red-800">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    @if($services->isEmpty())
        <p class="mt-6 rounded-lg bg-amber-50 p-4">No active services are available. Ask your administrator to configure services.</p>
    @else
        <form action="{{ route('patients.visits.store', $patient) }}" method="POST" class="mt-8 max-w-xl space-y-6 rounded-xl border border-slate-200 bg-white p-6">
            @csrf
            <div><label for="service_id" class="mb-2 block text-sm font-semibold">Requested service (required)</label>
                <select id="service_id" name="service_id" required class="w-full rounded-lg border border-slate-300 p-3">
                    <option value="">Select service</option>
                    @foreach($services as $service)<option value="{{ $service->id }}" @selected(old('service_id') == $service->id)>{{ $service->name }}</option>@endforeach
                </select>
                @error('service_id')<p role="alert" class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>
            <x-visit-priority-fields :patient="$patient" />
            <x-input name="visit_date" label="Visit date (required)" type="date" :value="now('Asia/Manila')->toDateString()" :max="now('Asia/Manila')->toDateString()" required />
            <input type="hidden" name="queue_token" value="{{ $reservation->token }}">
            <div><label for="generated-queue" class="mb-2 block text-sm font-semibold">Queue number (auto-generated)</label><input id="generated-queue" value="{{ $reservation->queue_reference }}" readonly aria-describedby="queue-help" class="w-full rounded-lg border border-slate-300 bg-slate-50 p-3 font-semibold text-emerald-900"></div>
            <p id="queue-help" class="text-sm text-slate-500">Assigned automatically and cannot be edited. Save the visit, then select Print / Reprint stub.</p>
            <x-button>Start visit</x-button>
        </form>
    @endif
</x-layout>
