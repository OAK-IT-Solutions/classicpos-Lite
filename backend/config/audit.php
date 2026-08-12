<?php

return [
    'retention_months' => (int) env('AUDIT_LOG_RETENTION_MONTHS', 24),
    'log_requests' => (bool) env('AUDIT_LOG_REQUESTS', true),
    'request_log_methods' => ['POST', 'PUT', 'PATCH', 'DELETE'],
    'export_max_rows' => (int) env('AUDIT_LOG_EXPORT_MAX', 10000),
];
