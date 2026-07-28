{{--
    One node of the org chart, rendered server-side (no D3).

    Shape rule that keeps the chart readable at 200+ people:
      • children WITH their own reports  → branch sideways (classic tree)
      • children WITHOUT reports (ICs)   → a compact wrapped grid beneath
    A manager with 20 reports therefore becomes a 5x4 block, not 20 boxes
    across (52,000px wide) and not a 20-row column.

    Expects: $node (array from OrganisationHierarchyController::formatUserNode)
--}}
@php
    $children = $node['children'] ?? [];
    $mgrKids  = array_values(array_filter($children, fn($c) => !empty($c['children'])));
    $icKids   = array_values(array_filter($children, fn($c) => empty($c['children'])));

    // Roughly square block: 1-3 stay on one row, 20 becomes 5 wide.
    $cols = max(1, min(6, (int) ceil(sqrt(max(1, count($icKids))))));

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
        <div class="oc-team" style="--cols: {{ $cols }}">
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
