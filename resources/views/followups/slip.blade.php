@php
    $first = $records->first();
    $addOn = $first->isNurseOrder();
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laboratory request slip · RHU Calasiao</title>
    <style>
        @page { size: A5; margin: 12mm; }
        * { box-sizing: border-box; } body { font: 15px Arial, sans-serif; color: #18352d; margin: 24px; }
        .controls { max-width: 640px; margin: 0 auto 24px; display: flex; gap: 20px; align-items: center; }
        button { background: #065f46; color: white; border: 0; border-radius: 6px; padding: 12px 20px; cursor: pointer; font: inherit; }
        a { color: #065f46; } .slip { max-width: 640px; border: 1px solid #87948f; padding: 22px; margin: auto; }
        header { display: flex; gap: 14px; align-items: center; border-bottom: 1px solid #ccd6d1; padding-bottom: 14px; }
        img { width: 56px; height: 56px; object-fit: contain; } h1 { font-size: 20px; margin: 0 0 4px; }
        .tag { display: inline-block; margin-top: 6px; border: 1px solid #b45309; color: #92400e; border-radius: 999px; padding: 2px 10px; font-size: 12px; font-weight: bold; text-transform: uppercase; }
        dl { display: grid; grid-template-columns: 1fr 1fr; gap: 0 20px; margin: 0; } dt { color: #50645b; font-size: 12px; margin-top: 12px; } dd { margin: 3px 0 0; overflow-wrap: anywhere; }
        h2 { font-size: 15px; margin: 20px 0 8px; border-bottom: 1px solid #ccd6d1; padding-bottom: 4px; }
        ol { margin: 0; padding-left: 22px; } li { padding: 4px 0; } .reason { white-space: pre-wrap; overflow-wrap: anywhere; margin: 0; }
        .signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-top: 28px; } .line { border-top: 1px solid #18352d; padding-top: 4px; font-size: 13px; margin-top: 30px; }
        .note { margin-top: 18px; font-size: 11px; color: #50645b; }
        @media print { .controls { display: none; } body { margin: 0; } .slip { border: 0; padding: 0; } }
    </style>
</head>
<body>
    <nav class="controls"><button type="button" onclick="window.print()">Print slip</button><a href="{{ url()->previous() }}">Back</a></nav>
    <main class="slip">
        <header>
            <img src="{{ asset('images/rhu-logo.jpg') }}" alt="RHU logo">
            <div><h1>RHU Calasiao · Laboratory request</h1>
                <span>{{ $addOn ? 'Add-on order by authorized nurse' : 'Doctor\'s order' }}</span>@if($addOn)<br><span class="tag">Add-on</span>@endif
            </div>
        </header>
        <dl>
            <div><dt>Patient</dt><dd>{{ $patient->full_name }}</dd></div>
            <div><dt>Patient number</dt><dd>{{ $patient->patient_number }}</dd></div>
            <div><dt>Birth date / sex</dt><dd>{{ $patient->birth_date?->format('M j, Y') ?? 'Not recorded' }} · {{ $patient->sex ?? 'Not recorded' }}</dd></div>
            <div><dt>Queue number</dt><dd>{{ $visit->queue_reference ?? 'Not recorded' }}</dd></div>
            <div><dt>Visit</dt><dd>{{ $visit->visit_number }} · {{ $visit->visit_date->format('M j, Y') }}</dd></div>
            <div><dt>Visit service</dt><dd>{{ $visit->service?->name ?? 'Former service' }}</dd></div>
        </dl>
        <h2>Tests requested ({{ $records->count() }})</h2>
        <ol>@foreach($records as $record)<li>{{ $record->test_name }}</li>@endforeach</ol>
        <h2>Findings / reason for request</h2>
        <p class="reason">{{ $first->request_reason }}</p>
        <dl>
            <div><dt>{{ $addOn ? 'Ordered by (nurse)' : 'Ordered by (doctor)' }}</dt><dd>{{ $first->requester->name }}</dd></div>
            <div><dt>Date and time ordered</dt><dd>{{ $first->created_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}</dd></div>
            @if($addOn)
                <div><dt>Authorized by (doctor)</dt><dd>{{ $first->authorizingDoctor->name }}</dd></div>
                <div><dt>Authorization given</dt><dd>{{ $first->authorizedAt()?->timezone('Asia/Manila')->format('M j, Y g:i A') }}</dd></div>
            @endif
        </dl>
        <div class="signatures">
            <div class="line">{{ $first->requester->name }}<br>Requested by</div>
            <div class="line">{{ $first->authorizingDoctor->name }}<br>{{ $addOn ? 'Authorizing doctor' : 'Doctor' }}</div>
        </div>
        <p class="note">Printed from the RHU Calasiao patient records system. This is not an official RHU form layout.</p>
    </main>
</body>
</html>
