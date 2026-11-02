@php
    $first = $records->first();
    $byNurse = $first->orderedByNurse();
@endphp
<x-print-sheet title="Laboratory request" :subtitle="$byNurse ? 'Ordered by nurse' : 'Doctor’s order'" :tag="$byNurse ? 'Nurse order' : null" print-label="Print slip" :patient="$patient" :visit="$visit" :back="url()->previous()">
    <dl>
        <div><dt>Visit service</dt><dd>{{ $visit->service?->name ?? 'Former service' }}</dd></div>
    </dl>
    <h2>Tests requested ({{ $records->count() }})</h2>
    <ol>@foreach($records as $record)<li>{{ $record->test_name }}</li>@endforeach</ol>
    <h2>Findings / reason for request</h2>
    <p class="text">{{ $first->request_reason }}</p>
    <dl>
        <div><dt>{{ $byNurse ? 'Ordered by (nurse)' : 'Ordered by (doctor)' }}</dt><dd>{{ $first->requester->name }}</dd></div>
        <div><dt>Date and time ordered</dt><dd>{{ $first->created_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}</dd></div>
        @if($first->isNurseOrder())
            <div><dt>Authorized by (doctor)</dt><dd>{{ $first->authorizingDoctor->name }}</dd></div>
            <div><dt>Authorization given</dt><dd>{{ $first->authorizedAt()?->timezone('Asia/Manila')->format('M j, Y g:i A') }}</dd></div>
        @endif
    </dl>
    <div class="signatures">
        <div class="line">{{ $first->requester->name }}<br>{{ $byNurse ? 'Requesting nurse' : 'Requesting doctor' }}</div>
        <div class="line"><br>Received by (laboratory)</div>
    </div>
</x-print-sheet>
