<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Visit stub · RHU Calasiao</title>
    <style>
        @page { size: A4; margin: 15mm; }
        * { box-sizing: border-box; } body { font: 16px Arial, sans-serif; color: #18352d; margin: 24px; }
        .controls { max-width: 680px; margin: 0 auto 24px; display: flex; gap: 20px; align-items: center; }
        button { background: #065f46; color: white; border: 0; border-radius: 6px; padding: 12px 20px; cursor: pointer; font: inherit; }
        a { color: #065f46; } .stub { max-width: 680px; border: 1px dashed #87948f; padding: 24px; margin: auto; }
        header { display: flex; gap: 16px; align-items: center; border-bottom: 1px solid #ccd6d1; padding-bottom: 16px; }
        img { width: 64px; height: 64px; object-fit: contain; } h1 { font-size: 22px; margin: 0 0 6px; }
        .queue { text-align: center; padding: 20px 0; } .queue strong { display: block; font-size: 36px; overflow-wrap: anywhere; margin-top: 8px; }
        dl { margin: 0; } dt { color: #50645b; font-size: 13px; margin-top: 14px; } dd { margin: 4px 0 0; overflow-wrap: anywhere; }
        @media print { .controls { display: none; } body { margin: 0; } .stub { break-inside: avoid; } }
    </style>
</head>
<body>
    <nav class="controls"><button type="button" onclick="window.print()">Print stub</button><a href="{{ route('patients.visits.show', [$patient, $visit]) }}">Back to visit</a></nav>
    <main class="stub">
        <header><img src="{{ asset('images/rhu-logo.jpg') }}" alt="RHU logo"><div><h1>RHU Calasiao</h1><span>Patient visit stub</span></div></header>
        <div class="queue">Queue number<strong>{{ $visit->queue_reference ?? 'Not recorded' }}</strong></div>
        <dl>
            <dt>Patient</dt><dd>{{ $patient->full_name }}</dd>
            <dt>Patient number</dt><dd>{{ $patient->patient_number }}</dd>
            <dt>Visit number</dt><dd>{{ $visit->visit_number }}</dd>
            <dt>Visit date</dt><dd>{{ $visit->visit_date->format('M j, Y') }}</dd>
            <dt>Requested service</dt><dd>{{ $visit->service?->name ?? 'Former service' }}</dd>
        </dl>
    </main>
</body>
</html>
