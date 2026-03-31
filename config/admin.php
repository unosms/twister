<?php

$identifiers = array_values(array_filter(array_map(
    static fn ($value): string => trim((string) $value),
    explode(',', (string) env('SUPER_ADMIN_IDENTIFIERS', 'admin')),
), static fn (string $value): bool => $value !== ''));

return [
    'super_admin_identifiers' => $identifiers,
];
