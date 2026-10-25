<?php

return [
    // Conservative application limits, not measured hardware capacity.
    'requests_per_minute' => 6,
    'lock_seconds' => 300,
    'minimum_memory_headroom_bytes' => 64 * 1024 * 1024,
    'maximum_html_bytes' => 1024 * 1024,
];
