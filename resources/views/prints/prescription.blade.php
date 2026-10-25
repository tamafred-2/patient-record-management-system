<x-print-sheet title="Prescription" :subtitle="'No. '.($prescription->prescription_number ?? $prescription->id)" :patient="$patient" :visit="$visit" :back="url()->previous()">
    @php($diagnoses = $visit->treatmentRecord?->diagnoses ?? [])
    @if($diagnoses)
        <h2>Diagnosis</h2>
        <p class="text">{{ implode('; ', $diagnoses) }}</p>
    @endif
    <h2>℞ Medicines ({{ $prescription->items->count() }})</h2>
    <table>
        <thead><tr><th>Medicine</th><th>Dose and frequency</th><th>Duration</th><th>Qty</th></tr></thead>
        <tbody>
            @foreach($prescription->items as $item)
                <tr>
                    <td><strong>{{ $item->medicine_name }}</strong>@if($item->strength) {{ $item->strength }}@endif</td>
                    <td>{{ $item->dosage }}, {{ $item->frequency }}@if($item->instructions)<br><span class="text">{{ $item->instructions }}</span>@endif</td>
                    <td>{{ $item->duration ?: '—' }}</td>
                    <td>{{ rtrim(rtrim(number_format((float) $item->quantity_prescribed, 2, '.', ''), '0'), '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <dl>
        <div><dt>Prescribed by</dt><dd>{{ $prescription->doctor->name }}</dd></div>
        <div><dt>Date issued</dt><dd>{{ $prescription->prescribed_at?->timezone('Asia/Manila')->format('M j, Y g:i A') }}</dd></div>
    </dl>
    <div class="signatures">
        <div></div>
        <div class="line">{{ $prescription->doctor->name }}<br>Signature · License No. ____________</div>
    </div>
</x-print-sheet>
