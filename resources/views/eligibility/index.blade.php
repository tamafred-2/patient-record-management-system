<x-layout title="PhilHealth confirmation">
    <h1 class="text-3xl font-semibold">PhilHealth confirmation</h1>
    <p class="mt-2 text-slate-600">Confirm patient records after checking the existing PhilHealth system.</p>
    <x-visit-filters :search="$search" :date="$date" :all-dates="$allDates" />
    <p class="mt-3 text-sm font-semibold text-emerald-800">{{ $allDates ? 'All dates' : $date }} ? {{ $visits->total() }} visits</p>
    @if($errors->any())<x-alert type="error">{{ $errors->first() }}</x-alert>@endif
    <x-scroll-queue :paginator="$visits" id="visits" label="Visits, scroll to load more">
    <table class="w-full text-left text-sm">
            <caption class="sr-only">Visits for PhilHealth confirmation</caption>
            <thead class="sticky top-0 z-10 bg-slate-50 text-slate-500"><tr><th scope="col" class="p-3">Patient</th><th scope="col" class="p-3">Visit / service</th><th scope="col" class="p-3">Queue reference</th><th scope="col" class="p-3">Confirmation</th><th scope="col" class="p-3">Action</th></tr></thead>
            <tbody>
                @foreach($visits as $visit)
                    <tr class="border-t border-slate-100">
                        <td class="p-3"><strong>{{ $visit->patient->full_name }}</strong><p class="text-slate-500">{{ $visit->patient->patient_number }}</p></td>
                        <td class="p-3">{{ $visit->visit_number }}<p class="text-slate-500">{{ $visit->service->name }}</p></td>
                        <td class="p-3">{{ $visit->queue_reference ?? 'Not recorded' }}<x-visit-priority :visit="$visit" class="mt-1 block w-fit" /></td>
                        <td class="p-3">{{ $visit->eligibilityCheck?->philhealth_confirmed === true ? 'With record' : ($visit->eligibilityCheck?->philhealth_confirmed === false ? 'No record' : 'Not checked') }}</td>
                        <td class="p-3"><a class="font-semibold text-emerald-800 underline" href="{{ route('eligibility.edit', [$visit->patient, $visit]) }}">Open confirmation</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-scroll-queue>
    
</x-layout>
