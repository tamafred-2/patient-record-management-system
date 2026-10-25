<x-table-filters label="Search visits by number, queue or service" :filters="[
    'status' => ['label' => 'Visit status', 'options' => ['OPEN' => 'Open', 'COMPLETED' => 'Completed']],
    'priority' => ['label' => 'Priority', 'options' => App\Models\Visit::PRIORITY_LABELS],
]" />
