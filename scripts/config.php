<?php

declare(strict_types=1);

if (!function_exists('scripts_load_env_file')) {
    function scripts_load_env_file(string $envPath): void
    {
        if (!is_file($envPath)) {
            return;
        }

        $lines = @file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }

            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $key = trim($parts[0]);
            $value = trim($parts[1]);
            if ($key === '' || getenv($key) !== false) {
                continue;
            }

            if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                $value = substr($value, 1, -1);
            }

            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

if (!function_exists('scripts_parse_chat_ids')) {
    function scripts_parse_chat_ids(string $raw): array
    {
        $parts = preg_split('/[,\s]+/', trim($raw)) ?: [];
        $ids = [];
        foreach ($parts as $part) {
            $id = trim($part);
            if ($id !== '') {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }
}

scripts_load_env_file(dirname(__DIR__) . '/.env');

$token = (string) (
    getenv('TG_BOT_TOKEN')
    ?: getenv('TELEGRAM_BOT_TOKEN')
    ?: ''
);

$deviceChatsRaw = (string) (
    getenv('TG_CHAT_DEV_IDS')
    ?: getenv('TELEGRAM_CHAT_DEV_IDS')
    ?: getenv('TG_CHAT_ID')
    ?: getenv('TELEGRAM_CHAT_ID')
    ?: ''
);

$ifaceChatsRaw = (string) (
    getenv('TG_CHAT_IFACE_IDS')
    ?: getenv('TELEGRAM_CHAT_IFACE_IDS')
    ?: $deviceChatsRaw
);

if (!defined('TG_BOT_TOKEN')) {
    define('TG_BOT_TOKEN', $token);
}
if (!defined('TG_CHAT_DEV_IDS')) {
    define('TG_CHAT_DEV_IDS', scripts_parse_chat_ids($deviceChatsRaw));
}
if (!defined('TG_CHAT_IFACE_IDS')) {
    define('TG_CHAT_IFACE_IDS', scripts_parse_chat_ids($ifaceChatsRaw));
}
