@php
    $avatarUser = $user ?? null;
    $avatarName = trim((string) ($name ?? $avatarUser->name ?? 'User'));
    $avatarUrl = $avatarUrl ?? ($avatarUser && method_exists($avatarUser, 'profileAvatarUrl') ? $avatarUser->profileAvatarUrl() : null);
    $avatarInitials = $initials ?? ($avatarUser && method_exists($avatarUser, 'profileInitials') ? $avatarUser->profileInitials() : null);

    if (!is_string($avatarInitials) || trim($avatarInitials) === '') {
        $nameParts = preg_split('/\s+/', $avatarName) ?: [];
        $nameParts = array_values(array_filter($nameParts, static fn ($part): bool => $part !== ''));
        $avatarInitials = '';

        foreach (array_slice($nameParts, 0, 2) as $part) {
            $avatarInitials .= \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($part, 0, 1));
        }

        if ($avatarInitials === '') {
            $avatarInitials = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($avatarName, 0, 1));
        }
    }

    $avatarInitials = $avatarInitials !== '' ? $avatarInitials : 'U';
    $sizeClass = $sizeClass ?? 'h-10 w-10';
    $textClass = $textClass ?? 'text-sm';
    $class = trim((string) ($class ?? ''));
@endphp

@if ($avatarUrl)
    <img
        alt="{{ $avatarName }} avatar"
        class="{{ $sizeClass }} rounded-full border border-slate-200 object-cover dark:border-slate-700 {{ $class }}"
        src="{{ $avatarUrl }}"
    />
@else
    <div class="{{ $sizeClass }} flex items-center justify-center rounded-full border border-slate-200 bg-primary/10 font-bold text-primary dark:border-slate-700 {{ $textClass }} {{ $class }}">
        {{ $avatarInitials }}
    </div>
@endif
