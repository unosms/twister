<?php

return [
    'enabled' => (bool) env('PROVISIONING_LOG_ENABLED', false),
    'trace_level' => env('PROVISIONING_TRACE_LEVEL', 'trace'),
    'sse_enabled' => (bool) env('PROVISIONING_SSE_ENABLED', true),
    'raw_log_path' => storage_path('logs/provisioning.log'),
    'event_log_path' => storage_path('logs/provisioning-events.jsonl'),
    'tail_limit' => (int) env('PROVISIONING_TAIL_LIMIT', 200),
    'event_limit' => (int) env('PROVISIONING_EVENT_LIMIT', 80),
    'max_payload_bytes' => (int) env('PROVISIONING_MAX_PAYLOAD_BYTES', 2048),
    'stream_heartbeat_ms' => (int) env('PROVISIONING_STREAM_HEARTBEAT_MS', 1000),
    'stream_lifetime_seconds' => (int) env('PROVISIONING_STREAM_LIFETIME_SECONDS', 25),
];
