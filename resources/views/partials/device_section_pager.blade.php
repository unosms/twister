@php
/** @var \Illuminate\Pagination\LengthAwarePaginator $paginator */
$paginator = $paginator ?? null;
$pageKey = $pageKey ?? 'page';
$isPaginator = $paginator instanceof \Illuminate\Pagination\LengthAwarePaginator;
if (!$isPaginator) {
    $paginator = null;
}

$total = $isPaginator ? (int) $paginator->total() : 0;
$first = $isPaginator && $total > 0 ? (int) $paginator->firstItem() : 0;
$last = $isPaginator && $total > 0 ? (int) $paginator->lastItem() : 0;
$currentPage = $isPaginator ? (int) $paginator->currentPage() : 1;
$lastPage = $isPaginator ? max(1, (int) $paginator->lastPage()) : 1;
$hasPrevious = $currentPage > 1;
$hasNext = $currentPage < $lastPage;
$openGroupKey = trim((string) ($openGroupKey ?? ''));
$previousQuery = [$pageKey => $currentPage - 1];
$nextQuery = [$pageKey => $currentPage + 1];
if ($openGroupKey !== '') {
    $previousQuery['open_group'] = $openGroupKey;
    $nextQuery['open_group'] = $openGroupKey;
}
$previousUrl = $hasPrevious ? request()->fullUrlWithQuery($previousQuery) : '#';
$nextUrl = $hasNext ? request()->fullUrlWithQuery($nextQuery) : '#';
@endphp

@if ($isPaginator && $total > $paginator->perPage())
<div class="mt-3 px-1 flex flex-wrap items-center justify-between gap-2">
    <span class="text-xs text-gray-500 font-medium tracking-tight">
        Showing {{ $first }}-{{ $last }} of {{ $total }} results
    </span>
    <div class="flex items-center gap-2">
        <a class="px-3 py-1 bg-white dark:bg-gray-800 border border-[#cfd7e7] dark:border-gray-700 rounded text-xs font-semibold {{ $hasPrevious ? '' : 'opacity-50 pointer-events-none' }}" href="{{ $previousUrl }}">Previous</a>
        <span class="px-3 py-1 bg-primary text-white rounded text-xs font-semibold">{{ $currentPage }}</span>
        <a class="px-3 py-1 bg-white dark:bg-gray-800 border border-[#cfd7e7] dark:border-gray-700 rounded text-xs font-semibold {{ $hasNext ? '' : 'opacity-50 pointer-events-none' }}" href="{{ $nextUrl }}">Next</a>
    </div>
</div>
@endif
