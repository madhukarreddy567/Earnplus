{{-- Ad slot component: <x-ad placement="dashboard-banner" />

Renders the best eligible placement for the given slot (device + page
matched, session frequency cap respected, weighted rotation by priority).
Outputs nothing at all when no placement is eligible, so page layouts
never break around an empty slot.
--}}
@props(['placement'])
{!! render_ad($placement) !!}
