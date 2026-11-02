<x-print-sheet title="External laboratory request" subtitle="Test not available at RHU" :patient="$patient" :visit="$visit" :back="url()->previous()">
    <h2>Test requested</h2>
    <ol><li>{{ $record->test_name }}</li></ol>
    @if($record->request_reason)
        <h2>Findings / reason for request</h2>
        <p class="text">{{ $record->request_reason }}</p>
    @endif
    <h2>Laboratory advice</h2>
    <p class="text">{{ $record->external_advice }}</p>
    <dl>
        @if($record->isOrdered())
            <div><dt>{{ $record->orderedByNurse() ? 'Ordered by (nurse)' : 'Ordered by (doctor)' }}</dt><dd>{{ $record->requester->name }}</dd></div>
            <div><dt>Date ordered</dt><dd>{{ $record->created_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}</dd></div>
            @if($record->isNurseOrder())<div><dt>Authorized by (doctor)</dt><dd>{{ $record->authorizingDoctor->name }}</dd></div>@endif
        @endif
        <div><dt>Advised by (laboratory)</dt><dd>{{ $record->updater?->name ?? 'Not recorded' }}</dd></div>
        <div><dt>Date advised</dt><dd>{{ $record->updated_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}</dd></div>
    </dl>
    <div class="signatures">
        <div class="line">{{ $record->updater?->name }}<br>RHU laboratory</div>
        <div class="line">{{ $record->isOrdered() ? $record->requester->name : '' }}<br>{{ $record->orderedByNurse() ? 'Requesting nurse' : 'Requesting doctor' }}</div>
    </div>
</x-print-sheet>
