{{--
    A single person card. Carries the full record in data-* attributes so the
    existing hover drawer (#userCardPopover) can populate without another
    request — same interaction the D3 chart had.

    Expects: $p (person array), $kind ('mgr'|'leaf'), $direct, $total
--}}
@php
    $search = strtolower(trim(implode(' ', array_filter([
        $p['name'] ?? '', $p['designation'] ?? '', $p['department'] ?? '', $p['code'] ?? '',
    ]))));
@endphp
<div class="oc-box oc-{{ $kind }}"
     data-dept="{{ $p['department'] ?? '' }}"
     data-s="{{ $search }}"
     data-id="{{ $p['id'] ?? '' }}"
     data-name="{{ $p['name'] ?? '' }}"
     data-designation="{{ $p['designation'] ?? '' }}"
     data-department="{{ $p['department'] ?? '' }}"
     data-code="{{ $p['code'] ?? '' }}"
     data-email="{{ $p['email'] ?? '' }}"
     data-phone="{{ $p['phone'] ?? '' }}"
     data-img="{{ $p['profile_picture'] ?? '' }}"
     tabindex="0">
    <span class="oc-av">{{ $p['initials'] ?? mb_substr($p['name'] ?? '?', 0, 2) }}</span>
    <span class="oc-txt">
        <b>{{ $p['name'] ?? '' }}</b>
        <i>{{ $p['designation'] ?: '—' }}</i>
        <u>{{ $p['department'] ?? '' }}</u>
    </span>
    @if ($direct)
        <span class="oc-cnt" title="{{ $direct }} direct · {{ $total }} in total">
            {{ $direct }}<em>/</em>{{ $total }}
        </span>
    @endif
</div>
