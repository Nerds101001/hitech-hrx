{{--
  Manager view switch — My Data / Team / pick a teammate.
  Shown only to a sales manager who has a team. Mirrors the Kingo Bingo toggle.

  Caller must pass:
    $mvRoute        route name for this page (e.g. 'sales-visits.pipeline.index')
    $mvMemberParam  request key for a chosen teammate (default 'sp')
  and have these in scope (from the controller):
    $isManager, $hasTeam, $teamMembers
--}}
@php
    $mvMemberParam = $mvMemberParam ?? 'sp';
    $mvSelected    = request($mvMemberParam);
    $mvFy          = request('fy');
    $mvOnTeam      = request()->boolean('team') && !$mvSelected;
    $mvOnMember    = (bool) $mvSelected;
    $mvOnSelf      = !$mvOnTeam && !$mvOnMember;
@endphp
@if(($isManager ?? false) && ($hasTeam ?? false) && !empty($mvRoute))
<div class="d-flex gap-2 align-items-center flex-wrap">
    <a href="{{ route($mvRoute, array_filter(['fy' => $mvFy])) }}"
       class="btn btn-sm rounded-pill fw-bold shadow-sm {{ $mvOnSelf ? 'btn-primary' : 'btn-outline-secondary' }}">
        <i class="bx bx-user me-1"></i> My Data
    </a>
    <a href="{{ route($mvRoute, array_filter(['team' => 1, 'fy' => $mvFy])) }}"
       class="btn btn-sm rounded-pill fw-bold shadow-sm {{ $mvOnTeam ? 'btn-primary' : 'btn-outline-primary' }}">
        <i class="bx bx-group me-1"></i> Team
    </a>
    <form method="GET" class="d-inline">
        @if($mvFy)<input type="hidden" name="fy" value="{{ $mvFy }}">@endif
        <select name="{{ $mvMemberParam }}" class="form-select form-select-sm" style="min-width:170px" onchange="this.form.submit()">
            <option value="">— Team member —</option>
            @foreach(($teamMembers ?? collect()) as $tm)
            <option value="{{ $tm->id }}" {{ $mvSelected == $tm->id ? 'selected' : '' }}>{{ $tm->name }}</option>
            @endforeach
        </select>
    </form>
</div>
@endif
