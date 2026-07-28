{{--
    One node of the org chart, rendered server-side (no D3).

    Shape rule that keeps the chart readable at 200+ people:
      • children WITH their own reports  → branch sideways (classic tree)
      • children WITHOUT reports (ICs)   → stacked one below another, hanging
        off a spine on the left (file-tree style)
    Managers still fan out horizontally so the reporting shape is obvious,
    but a 20-person team costs one card of width instead of five.

    Expects: $node (array from OrganisationHierarchyController::formatUserNode)
--}}
@php
    $children = $node['children'] ?? [];
    $mgrKids  = array_values(array_filter($children, fn($c) => !empty($c['children'])));
    $icKids   = array_values(array_filter($children, fn($c) => empty($c['children'])));

    $countTeam = function ($n) use (&$countTeam) {
        return array_sum(array_map(fn($c) => 1 + $countTeam($c), $n['children'] ?? []));
    };
    $total = $countTeam($node);
@endphp

<div class="oc-node">
    @include('tenant.organisation-hierarchy.partials.tree-box', [
        'p'      => $node,
        'kind'   => count($children) ? 'mgr' : 'leaf',
        'direct' => count($children),
        'total'  => $total,
    ])

    @if (count($icKids))
        <div class="oc-team">
            <span class="oc-team-lbl">{{ count($icKids) }} team member{{ count($icKids) === 1 ? '' : 's' }}</span>
            @foreach ($icKids as $ic)
                <div class="oc-team-i">
                    @include('tenant.organisation-hierarchy.partials.tree-box', [
                        'p' => $ic, 'kind' => 'leaf', 'direct' => 0, 'total' => 0,
                    ])
                </div>
            @endforeach
        </div>
    @endif

    @if (count($mgrKids))
        <div class="oc-branch">
            @foreach ($mgrKids as $child)
                @include('tenant.organisation-hierarchy.partials.tree-node', ['node' => $child])
            @endforeach
        </div>
    @endif
</div>
