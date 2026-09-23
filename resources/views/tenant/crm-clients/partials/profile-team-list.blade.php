@php
    $teamRoleMeta = [
        'sales_person' => ['label' => 'Sales Person', 'color' => '#7c3aed'],
        'ccare'        => ['label' => 'CCare',       'color' => '#1d4ed8'],
        'new_biz'      => ['label' => 'New Biz',     'color' => '#a16207'],
        'billing'      => ['label' => 'Billing',     'color' => '#15803d'],
        'finance'      => ['label' => 'Finance',     'color' => '#be185d'],
        'other'        => ['label' => 'Other',       'color' => '#64748b'],
    ];
@endphp
<div id="cpTeamCardList">
    @foreach($teamRoleMeta as $roleKey => $meta)
        @php $members = $teamByRole->get($roleKey, collect()); @endphp
        @if($members->isNotEmpty())
            @foreach($members as $member)
                <div class="cp-team-slot">
                    <div class="cp-team-avatar" style="background:{{ $meta['color'] }};">{{ substr($member->user->first_name,0,1) }}{{ substr($member->user->last_name,0,1) }}</div>
                    <div class="flex-grow-1" style="min-width:0;">
                        <div class="fw-semibold text-truncate" style="font-size:0.82rem;">{{ $member->user->first_name }} {{ $member->user->last_name }}</div>
                        <div class="text-muted" style="font-size:0.68rem;">{{ $meta['label'] }}</div>
                    </div>
                    <div class="d-flex align-items-center gap-1 flex-shrink-0">
                        @if($member->user->email)
                            <a href="mailto:{{ $member->user->email }}" class="btn btn-sm btn-icon btn-outline-secondary rounded-circle" style="width:24px;height:24px;padding:0;" title="Email {{ $member->user->first_name }}">
                                <i class="bx bx-envelope" style="font-size:.68rem;"></i>
                            </a>
                        @endif
                        @if($member->user->phone)
                            <a href="tel:{{ $member->user->phone }}" class="btn btn-sm btn-icon btn-outline-secondary rounded-circle" style="width:24px;height:24px;padding:0;" title="Call {{ $member->user->first_name }}">
                                <i class="bx bx-phone" style="font-size:.68rem;"></i>
                            </a>
                        @endif
                        <button type="button" class="btn btn-sm text-danger p-0 border-0" title="Remove" onclick="TeamCard.remove({{ $member->user_id }}, '{{ $roleKey }}')">
                            <i class="bx bx-x fs-5"></i>
                        </button>
                    </div>
                </div>
            @endforeach
        @else
            <div class="cp-team-slot">
                <div class="cp-team-avatar" style="background:#e2e8f0;color:#94a3b8;"><i class="bx bx-user"></i></div>
                <div class="flex-grow-1">
                    <div class="text-muted" style="font-size:0.78rem;">Not assigned</div>
                    <div class="text-muted" style="font-size:0.68rem;">{{ $meta['label'] }}</div>
                </div>
            </div>
        @endif
    @endforeach
</div>

<div id="cpTeamAddRow" class="d-none mt-3 pt-3" style="border-top:1px dashed #eef2f7;">
    <select id="cpTeamAddUser" class="form-select form-select-sm mb-2">
        <option value="">— choose person —</option>
        @foreach($teamPickable as $p)
            <option value="{{ $p->id }}">{{ $p->first_name }} {{ $p->last_name }}</option>
        @endforeach
    </select>
    <select id="cpTeamAddRole" class="form-select form-select-sm mb-2">
        @foreach($teamRoleMeta as $roleKey => $meta)
            <option value="{{ $roleKey }}">{{ $meta['label'] }}</option>
        @endforeach
    </select>
    <div class="d-flex gap-2">
        <button class="btn btn-sm btn-success flex-grow-1" onclick="TeamCard.add()">Add</button>
        <button class="btn btn-sm btn-outline-secondary" onclick="TeamCard.closeAdd()">Cancel</button>
    </div>
</div>
