<x-layout title="Midwife care record">
    @php($canEdit = $visit->status === 'OPEN' && auth()->user()->can('midwife-care.record'))
    <x-back-link :href="route('midwife-care.index', ['date' => $visit->visit_date->toDateString()])">Back to Midwife care visits</x-back-link>
    <h1 class="mt-5 text-3xl font-semibold">Midwife care record</h1>
    <p class="mt-2 text-sm text-slate-500">Basic service record for RHU review. This is not an official clinical form.</p>
    <section class="my-6 rounded-xl border border-slate-200 bg-white p-6">
        <h2 class="text-xl font-semibold">{{ $patient->full_name }}</h2>
        <p class="mt-2">{{ $patient->patient_number }} &middot; Birth date: {{ $patient->birth_date?->format('M j, Y') ?? 'Not recorded' }}</p>
        <p class="mt-2 text-sm text-slate-600">{{ $visit->visit_number }} &middot; {{ $visit->visit_date->format('M j, Y') }} &middot; Queue: {{ $visit->queue_reference ?? 'Not recorded' }} &middot; {{ $visit->status }}</p>
        @if($vaccinationVisit)<a href="{{ route('vaccinations.show', [$patient, $vaccinationVisit]) }}" class="mt-3 inline-block font-semibold text-emerald-800 underline">View patient vaccination history</a>@endif
    </section>
    <x-midwife-vitals :patient="$patient" :visit="$visit" />
    @if($errors->any())<x-alert type="error" class="mb-6">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach<a class="underline" href="{{ $record ? route('midwife-care.edit', [$patient, $visit, $record]) : route('midwife-care.show', [$patient, $visit]) }}">Reload current record</a></x-alert>@endif
    @if($canEdit)
        <form method="POST" action="{{ $record ? route('midwife-care.update', [$patient, $visit, $record]) : route('midwife-care.store', [$patient, $visit]) }}" class="space-y-5 rounded-xl border border-slate-200 bg-white p-6" x-data="{ careType: {{ Js::from(old('care_type', $record?->care_type ?? '')) }} }">
            @csrf @if($record) @method('PUT') @endif
            <h2 class="text-xl font-semibold">{{ $record ? 'Edit care entry' : 'Record care service' }}</h2>
            <input type="hidden" name="lock_version" value="{{ old('lock_version', $record?->lock_version ?? 0) }}">
            @unless($record)<input type="hidden" name="submission_token" value="{{ old('submission_token', (string) Illuminate\Support\Str::uuid()) }}">@endunless
            <div><label for="care_type" class="mb-2 block font-medium">Care type (required)</label><select id="care_type" name="care_type" x-model="careType" required class="w-full rounded-lg border border-slate-300 p-3"><option value="">Select the service provided</option>@foreach(App\Models\MidwifeCareRecord::TYPES as $key => $label)<option value="{{ $key }}" @selected(old('care_type', $record?->care_type) === $key)>{{ $label }}</option>@endforeach</select></div>
            <x-textarea name="purpose" label="Visit purpose (required)" :value="$record?->purpose" rows="3" maxlength="3000" required />
            <div x-cloak x-show="careType === 'COMMUNITY'"><label for="location" class="mb-2 block font-medium">Community / home visit location (required)</label><input id="location" name="location" maxlength="200" :disabled="careType !== 'COMMUNITY'" :required="careType === 'COMMUNITY'" value="{{ old('location', $record?->location) }}" class="w-full rounded-lg border border-slate-300 p-3"></div>
            @foreach(['services_provided' => 'Services provided (optional)', 'notes' => 'Care notes (optional)'] as $field => $label)
                <div><label for="{{ $field }}" class="mb-2 block font-medium">{{ $label }}</label><textarea id="{{ $field }}" name="{{ $field }}" rows="4" maxlength="3000" class="w-full rounded-lg border border-slate-300 p-3">{{ old($field, $record?->$field) }}</textarea></div>
            @endforeach
            <fieldset x-cloak x-show="careType === 'PRENATAL'" :disabled="careType !== 'PRENATAL'" class="rounded-lg border border-slate-200 p-4" x-data="{ lmp: @js(old('lmp', $record?->lmp?->toDateString())) }">
                <legend class="px-1 font-medium">Prenatal check-up (optional)</legend>
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-input name="lmp" label="Last menstrual period (LMP)" type="date" x-model="lmp" :value="$record?->lmp?->toDateString()" :max="$visit->visit_date->toDateString()" />
                    <div><p class="mb-2 block text-sm font-medium">Expected delivery (EDD)</p><p class="rounded-lg bg-slate-50 px-3 py-2.5 font-semibold" x-text="lmp ? new Date(new Date(lmp + 'T00:00:00').getTime() + 280 * 86400000).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—'">—</p></div>
                    <div><p class="mb-2 block text-sm font-medium">Age of gestation</p><p class="rounded-lg bg-slate-50 px-3 py-2.5 font-semibold" x-text="(() => { if (!lmp) return '—'; const days = Math.floor((new Date(@js($visit->visit_date->toDateString()) + 'T00:00:00') - new Date(lmp + 'T00:00:00')) / 86400000); return days < 0 ? '—' : `${Math.floor(days / 7)} weeks ${days % 7} days`; })()">—</p></div>
                    <x-input name="gravida" label="Gravida (G)" type="number" min="1" max="20" :value="$record?->gravida" />
                    <x-input name="para" label="Para (P)" type="number" min="0" max="20" :value="$record?->para" />
                    <x-input name="fundal_height_cm" label="Fundal height (cm)" type="number" step="0.1" min="1" max="50" :value="$record?->fundal_height_cm" />
                    <x-input name="fetal_heart_tone" label="Fetal heart tone (bpm)" type="number" min="50" max="250" :value="$record?->fetal_heart_tone" />
                </div>
                <p class="mt-3 text-xs text-slate-500">EDD (LMP + 280 days) and age of gestation are calculated from LMP and the visit date. They are not a clinical assessment.</p>
            </fieldset>
            <div class="sm:max-w-sm"><x-input name="follow_up_on" label="Follow-up date (optional)" type="date" :value="$record?->follow_up_on?->toDateString()" /></div>
            <p class="text-sm text-slate-500">Enter only services actually provided and a return date arranged by staff. This record does not calculate a clinical schedule or send reminders.</p>
            <label class="flex items-start gap-3 text-sm"><input type="checkbox" name="confirm" value="1" required class="mt-1"><span>I confirm this entry accurately describes the care provided for this patient during this visit.</span></label>
            <div class="flex flex-wrap items-center gap-4"><x-button>Save care record</x-button>@if($record)<a href="{{ route('midwife-care.show', [$patient, $visit]) }}" class="text-emerald-800 underline">Cancel editing</a>@endif</div>
        </form>
    @else<p class="text-slate-600">Care records for this visit are read-only.</p>@endif
    <section class="mt-8 space-y-4">
        <h2 class="text-xl font-semibold">Patient Midwife care history</h2>
        <p class="text-sm text-slate-500">Basic care entries recorded in this application across this patient's visits.</p>
        <x-scroll-queue :paginator="$history" id="history" kind="cards" label="Record history, scroll to load more"><div data-queue-items>@forelse($history as $entry)
            <article class="space-y-3 rounded-xl border border-slate-200 bg-white p-5">
                <h3 class="font-semibold">{{ App\Models\MidwifeCareRecord::TYPES[$entry->care_type] }}</h3>
                <p class="text-sm text-slate-500">{{ $entry->visit->visit_number }} &middot; {{ $entry->visit->visit_date->format('M j, Y') }}</p>
                @foreach(['purpose' => 'Purpose', 'services_provided' => 'Services provided', 'notes' => 'Care notes', 'location' => 'Location'] as $field => $label)
                    @if($entry->$field)<div><h4 class="text-sm font-semibold">{{ $label }}</h4><p class="whitespace-pre-wrap break-words text-sm">{{ $entry->$field }}</p></div>@endif
                @endforeach
                @if($entry->care_type === 'PRENATAL' && collect(App\Models\MidwifeCareRecord::PRENATAL_FIELDS)->contains(fn ($field) => $entry->$field !== null))
                    <dl class="my-2 grid grid-cols-2 gap-2 rounded-lg bg-slate-50 p-3 text-sm sm:grid-cols-4">
                        <div><dt class="text-xs text-slate-500">LMP</dt><dd>{{ $entry->lmp?->format('M j, Y') ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-slate-500">EDD</dt><dd>{{ $entry->expectedDelivery()?->format('M j, Y') ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-slate-500">Age of gestation</dt><dd>{{ $entry->ageOfGestation() ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-slate-500">G / P</dt><dd>G{{ $entry->gravida ?? '–' }} P{{ $entry->para ?? '–' }}</dd></div>
                        <div><dt class="text-xs text-slate-500">Fundal height</dt><dd>{{ $entry->fundal_height_cm !== null ? $entry->fundal_height_cm.' cm' : '—' }}</dd></div>
                        <div><dt class="text-xs text-slate-500">Fetal heart tone</dt><dd>{{ $entry->fetal_heart_tone !== null ? $entry->fetal_heart_tone.' bpm' : '—' }}</dd></div>
                    </dl>
                @endif
                <p class="text-sm">Follow-up date: {{ $entry->follow_up_on?->format('M j, Y') ?? 'Not recorded' }}</p>
                <p class="text-xs text-slate-500">Entered by {{ $entry->recorder?->name }} &middot; Last saved by {{ $entry->editor?->name }} on {{ $entry->updated_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}</p>
                @if($canEdit && $entry->visit_id === $visit->id)<a class="inline-block font-semibold text-emerald-800 underline" href="{{ route('midwife-care.edit', [$patient, $visit, $entry]) }}">Edit care entry</a>@endif
            </article>
        @empty<p class="text-slate-500">No Midwife care records yet.</p>@endforelse</div></x-scroll-queue>
        
    </section>
</x-layout>
