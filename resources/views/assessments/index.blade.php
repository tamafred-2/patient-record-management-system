<x-layout title="Initial assessment">
    <h1 class="text-3xl font-semibold">Initial assessment</h1>
    <p class="mt-2 text-slate-600">Record vital signs and a detailed assessment summary for the doctor.</p>
    @if($reassessmentOnly)
        <p class="mt-4 text-sm text-slate-600">Showing pending requests across all visit dates. <a class="text-emerald-800 underline" href="{{ route('assessments.index') }}">Back to all assessments</a></p>
        <form method="GET" class="my-6 flex items-end gap-3"><input type="hidden" name="reassessment" value="1"><x-input name="search" label="Patient name or queue number" :value="$search" maxlength="100" /><x-button>Search pending requests</x-button></form>
    @else
        <x-visit-filters :search="$search" :date="$date" :all-dates="$allDates" :filters="App\Support\ListFilters::priorityFilter() + ['vitals' => ['label' => 'Vital signs', 'options' => ['pending' => 'Not yet recorded', 'recorded' => 'Recorded']]]" />
    @endif
    <p class="mt-3 text-sm font-semibold text-emerald-800">{{ $allDates ? 'All dates' : $date }} ? {{ $visits->total() }} visits</p>
    @if($errors->any())<x-alert type="error">{{ $errors->first() }}</x-alert>@endif
    <x-scroll-queue :paginator="$visits" id="visits" label="Visits, scroll to load more">
    <table class="w-full text-left text-sm">
            <caption class="sr-only">Visits for initial assessment</caption>
            <thead class="sticky top-0 z-10 bg-slate-50 text-slate-500"><tr><th scope="col" class="p-3">Patient</th><th scope="col" class="p-3">Visit / service</th><th scope="col" class="p-3">Queue reference</th><th scope="col" class="p-3">Vital signs</th><th scope="col" class="p-3">Action</th></tr></thead>
            <tbody>
                @foreach($visits as $visit)
                    <tr class="border-t border-slate-100">
                        <td class="p-3"><strong>{{ $visit->patient->full_name }}</strong><p class="text-slate-500">{{ $visit->patient->patient_number }}</p></td>
                        <td class="p-3">{{ $visit->visit_number }}<p class="text-slate-500">{{ $visit->service->name }}</p></td>
                        <td class="p-3">{{ $visit->queue_reference ?? 'Not recorded' }}<x-visit-priority :visit="$visit" class="mt-1 block w-fit" /></td>
                        <td class="p-3">{{ $visit->vitalSign ? 'Recorded' : 'Pending' }}@if($visit->pendingAssessmentRequest)<span class="mt-1 block font-semibold text-amber-800">Reassessment requested</span>@endif</td>
                        <td class="p-3"><a class="font-semibold text-emerald-800 underline" href="{{ route('assessments.edit', [$visit->patient, $visit]) }}">Open assessment</a>@if($visit->isOpen() && $visit->vitalSign && App\Support\FollowupAccess::nurse(auth()->user()))<a class="mt-2 block font-semibold text-emerald-800 underline" href="{{ route('followups.show', [$visit->patient, $visit]) }}">Order lab tests</a>@endif</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-scroll-queue>
    
</x-layout>
