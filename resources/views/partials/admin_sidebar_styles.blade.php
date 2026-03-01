<style>
    .sidebar-collapsible-group > summary::-webkit-details-marker {
        display: none;
    }

    .sidebar-collapsible-group > summary {
        list-style: none;
    }

    .sidebar-collapsible-group .sidebar-collapsible-icon {
        transition: transform 0.2s ease;
    }

    .sidebar-collapsible-group[open] .sidebar-collapsible-icon {
        transform: rotate(180deg);
    }

    body.sidebar-collapsed [data-sidebar] {
        width: 4.5rem !important;
        overflow: hidden;
    }

    body.sidebar-collapsed [data-sidebar] [data-sidebar-brand] {
        justify-content: center;
    }

    body.sidebar-collapsed [data-sidebar] .sidebar-subnav,
    body.sidebar-collapsed [data-sidebar] [data-sidebar-extra],
    body.sidebar-collapsed [data-sidebar] [data-sidebar-brand-text],
    body.sidebar-collapsed [data-sidebar] [data-sidebar-label],
    body.sidebar-collapsed [data-sidebar] .sidebar-collapsible-icon,
    body.sidebar-collapsed [data-sidebar] [data-sidebar-heading],
    body.sidebar-collapsed [data-sidebar] [data-sidebar-profile] {
        display: none !important;
    }

    body.sidebar-collapsed [data-sidebar] a,
    body.sidebar-collapsed [data-sidebar] button,
    body.sidebar-collapsed [data-sidebar] [data-sidebar-item] {
        justify-content: center;
    }

    body.sidebar-collapsed [data-sidebar-offset] {
        left: 4.5rem !important;
    }
</style>
