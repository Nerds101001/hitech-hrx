{{--
    One node of the org chart, rendered server-side (no D3).

    Shape rules:
      • children WITH their own reports → branch sideways (classic tree)
      • children WITHOUT reports (ICs)  → laid out under their manager

    How the ICs are laid out depends on WHERE the manager sits:
      – END OF A BRANCH (no sub-managers): the team stacks one below another
        off a spine on the left, file-tree style. This is the "last node".
      – MID-BRANCH (still has sub-managers under it): the team stays in a
        compact horizontal block, so the tree keeps its shape and the
        sub-branches stay properly aligned beneath their parent.

    Expects: $node (array from OrganisationHierarchyController::formatUserNode)
--}}
@php
    $children = $node['children'] ?? [];
    $mgrKids  = array_values(array_filter($children, fn($c) => !empty($c['children'])));
    $icKids   = array_values(array_filter($children, fn($c) => empty($c['children'])));

    // Only the end of a branch gets the vertical stack.
    $isLastNode = count($mgrKids) === 0;

    // Mid-branch teams stay horizontal, in a roughly square block.
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
        <div class="{{ $isLastNode ? 'oc-team oc-team-stack' : 'oc-team oc-team-grid' }}"
             @if (! $isLastNode) style="--cols: {{ $cols }}" @endif>
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
