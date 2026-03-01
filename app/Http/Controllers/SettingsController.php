<?php

namespace App\Http\Controllers;

use App\Http\Middleware\ApplySystemTimezone;
use App\Models\AppSetting;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    public function index()
    {
        $settingsReady = AppSetting::supportsStorage();
        $currentTimezone = $settingsReady
            ? (string) AppSetting::getValue('timezone', config('app.timezone', 'UTC'))
            : (string) config('app.timezone', 'UTC');

        $currentTimezone = in_array($currentTimezone, timezone_identifiers_list(), true)
            ? $currentTimezone
            : (string) config('app.timezone', 'UTC');

        return view('settings_system', [
            'currentTimezone' => $currentTimezone,
            'timezoneEntries' => $this->buildTimezoneEntries(),
            'countryOptions' => $this->buildCountryOptions(),
            'settingsReady' => $settingsReady,
        ]);
    }

    public function update(Request $request)
    {
        if (!AppSetting::supportsStorage()) {
            return back()->withErrors([
                'settings' => 'The settings table is missing the required key/value columns. Run php artisan migrate first.',
            ]);
        }

        $timezoneOptions = timezone_identifiers_list();

        $data = $request->validate([
            'timezone' => ['required', 'string', Rule::in($timezoneOptions)],
        ]);

        AppSetting::putValue('timezone', $data['timezone']);
        ApplySystemTimezone::flushCache();

        config(['app.timezone' => $data['timezone']]);
        date_default_timezone_set($data['timezone']);

        return redirect()
            ->route('settings.index')
            ->with('status', "System timezone updated to {$data['timezone']}.");
    }

    private function buildTimezoneEntries(): array
    {
        $entries = [];

        foreach (timezone_identifiers_list() as $timezone) {
            $parts = explode('/', $timezone, 2);
            $region = str_replace('_', ' ', $parts[0]);
            $label = str_replace('_', ' ', $parts[1] ?? $parts[0]);
            $countryCode = null;
            $countryName = 'Global / No Country';

            try {
                $location = timezone_location_get(new DateTimeZone($timezone));
                $countryCode = strtoupper((string) ($location['country_code'] ?? ''));
            } catch (\Throwable) {
                $countryCode = null;
            }

            if (is_string($countryCode) && $countryCode !== '') {
                $countryName = $this->resolveCountryName($countryCode);
            }

            $entries[] = [
                'timezone' => $timezone,
                'label' => $label,
                'region' => $region,
                'country_code' => $countryCode,
                'country_name' => $countryName,
                'display_label' => $countryName,
            ];
        }

        $countryCounts = [];
        foreach ($entries as $entry) {
            $countryCounts[$entry['country_name']] = ($countryCounts[$entry['country_name']] ?? 0) + 1;
        }

        foreach ($entries as &$entry) {
            $hasMultipleTimezones = ($countryCounts[$entry['country_name']] ?? 0) > 1;

            if ($entry['country_name'] === 'Global / No Country') {
                $entry['display_label'] = $entry['timezone'];
                continue;
            }

            $entry['display_label'] = $hasMultipleTimezones
                ? $entry['country_name'] . ', ' . $entry['label']
                : $entry['country_name'];
        }
        unset($entry);

        usort($entries, function (array $left, array $right): int {
            return [$left['country_name'], $left['display_label'], $left['timezone']]
                <=> [$right['country_name'], $right['display_label'], $right['timezone']];
        });

        return $entries;
    }

    private function buildCountryOptions(): array
    {
        $countries = [];

        foreach ($this->buildTimezoneEntries() as $entry) {
            $countries[$entry['country_name']] = $entry['country_name'];
        }

        ksort($countries);

        return array_values($countries);
    }

    private function resolveCountryName(string $countryCode): string
    {
        $countryCode = strtoupper(trim($countryCode));
        if ($countryCode === '') {
            return 'Global / No Country';
        }

        if ($countryCode === 'IL') {
            return 'Palastine';
        }

        if (class_exists('Locale')) {
            try {
                $countryName = \Locale::getDisplayRegion('-' . $countryCode, 'en');
                if (is_string($countryName) && trim($countryName) !== '') {
                    return $countryName;
                }
            } catch (\Throwable) {
                // Fall back to country code.
            }
        }

        return $countryCode;
    }
}
