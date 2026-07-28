{{--
    A single person card. Carries the full record in data-* attributes so the
    hover drawer (#userCardPopover) can populate without another request.

    The border colour is the person's DEPARTMENT colour, so departments are
    tellable apart at a glance and match the legend swatches.

    Expects: $p (person array), $kind ('mgr'|'leaf'), $direct, $total
    Inherited: $deptColour (department => hex)
--}}
@php
    $isOrg  = !empty($p['is_org']);
    $dept   = trim($p['department'] ?? '');
    $colour = $isOrg
        ? '#0f172a'
        : ($deptColour[$dept] ?? '#64748b');

    $search = strtolower(trim(implode(' ', array_filter([
        $p['name'] ?? '', $p['designation'] ?? '', $dept, $p['code'] ?? '',
    ]))));
@endphp
<div class="oc-box oc-{{ $kind }} @if($isOrg) oc-org @endif"
     style="--c: {{ $colour }}"
     data-dept="{{ $dept }}"
     data-s="{{ $search }}"
     data-id="{{ $p['id'] ?? '' }}"
     data-name="{{ $p['name'] ?? '' }}"
     data-designation="{{ $p['designation'] ?? '' }}"
     data-department="{{ $dept }}"
     data-code="{{ $p['code'] ?? '' }}"
     data-email="{{ $p['email'] ?? '' }}"
     data-phone="{{ $p['phone'] ?? '' }}"
     data-img="{{ $p['profile_picture'] ?? '' }}"
     @if(!$isOrg) tabindex="0" @endif>
    <span class="oc-av">{{ $p['initials'] ?? mb_substr($p['name'] ?? '?', 0, 2) }}</span>
    <span class="oc-txt">
        <b>{{ $p['name'] ?? '' }}</b>
        <i>{{ $p['designation'] ?: '—' }}</i>
        @if ($dept !== '')
            <u>{{ $dept }}</u>
        @endif
    </span>
    @if ($direct)
        <span class="oc-cnt" title="{{ $direct }} direct · {{ $total }} in total">
            {{ $direct }}<em>/</em>{{ $total }}
        </span>
    @endif
</div>
