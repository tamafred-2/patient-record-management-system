@if($entry->authorizing_doctor_id)
    @php($instructions = $entry->nurseAuthorization?->instructions ?? $entry->followupAuthorization?->instructions)
    <div class="my-4 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm">
        <p><strong>Authorizing doctor:</strong> {{ $entry->authorizingDoctor->name }}</p>
        <p class="mt-1"><strong>{{ $entry->isNurseOrder() ? 'Followed up by nurse:' : 'Requested by doctor:' }}</strong> {{ $entry->requester->name }}</p>
        <p class="mt-1">Requested {{ $entry->created_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}</p>
        <p class="mt-3 whitespace-pre-wrap break-words"><strong>Request reason:</strong> {{ $entry->request_reason }}</p>
        @if($entry->isNurseOrder())
            @if($instructions)<p class="mt-3 whitespace-pre-wrap break-words"><strong>Doctor instructions:</strong> {{ $instructions }}</p>@endif
            <p class="mt-1">Authorized {{ $entry->authorizedAt()?->timezone('Asia/Manila')->format('M j, Y g:i A') }}</p>
        @endif
    </div>
@endif
