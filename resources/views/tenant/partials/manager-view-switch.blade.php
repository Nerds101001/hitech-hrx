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
    // This partial appears in TWO places: on a dark teal hero (Sales Pipeline,
    // Sales Visits, CRM Clients) and in the WHITE navbar (Sales Report). One
    // fixed palette cannot serve both — white-on-white hid it in the navbar,
    // dark-grey-on-teal hid it on the hero. Callers on a hero pass
    // 'mvOnDark' => true; the default is the light navbar.
    $mvOnDark      = $mvOnDark ?? false;
    $mvMemberParam = $mvMemberParam ?? 'sp';
    $mvSelected    = request($mvMemberParam);
    $mvFy          = request('fy');
    $mvOnTeam      = request()->boolean('team') && !$mvSelected;
    $mvTeamCtx     = request()->boolean('team');   // still in Team context, member or not
    $mvOnMember    = (bool) $mvSelected;
    $mvOnSelf      = !$mvOnTeam && !$mvOnMember;
@endphp
@if(($isManager ?? false) && ($hasTeam ?? false) && !empty($mvRoute))
{{-- One segmented control rather than two loose pills: My Data / Team are
     mutually exclusive views of the same page, so they read better joined. --}}
{{-- Every page that includes this switch puts it on a dark hero
     (hitech-page-hero / sv-hero). btn-outline-secondary is a dark grey
     outline, so the unselected button and the teammate dropdown were
     invisible against the teal. Styled for a dark background instead —
     white when active, white-outline when not, matching the FY select and
     the Import button that sit beside it. --}}
<div class="d-flex gap-2 align-items-center">
    <div class="btn-group btn-group-sm shadow-sm" role="group" aria-label="Whose data to show">
        @php
            // Inline, not just Bootstrap classes: the portal theme overrides
            // .btn-light's background to transparent, so an "active" button
            // styled by class alone rendered with no fill at all.
            $mvOn = $mvOnDark
                ? 'background:#ffffff !important;color:#0d7377 !important;border-color:#ffffff !important;'
                : 'background:#0d7377 !important;color:#ffffff !important;border-color:#0d7377 !important;';
            $mvOff = $mvOnDark
                ? 'background:rgba(255,255,255,0.15) !important;color:#ffffff !important;border-color:rgba(255,255,255,0.65) !important;'
                : 'background:#ffffff !important;color:#475569 !important;border-color:#cbd5e1 !important;';
            $mvSelStyle = $mvOnDark
                ? 'width:auto;max-width:190px;background-color:rgba(255,255,255,0.2);border-color:#ffffff;color:#ffffff;'
                : 'width:auto;max-width:190px;background-color:#ffffff;border-color:#cbd5e1;color:#334155;';
        @endphp
        <a href="{{ route($mvRoute, array_filter(['fy' => $mvFy])) }}"
           class="btn fw-bold d-flex align-items-center"
           style="{{ $mvOnSelf ? $mvOn : $mvOff }}">
            <i class="bx bx-user me-1"></i> My Data
        </a>
        <a href="{{ route($mvRoute, array_filter(['team' => 1, 'fy' => $mvFy])) }}"
           class="btn fw-bold d-flex align-items-center"
           style="{{ ($mvOnTeam || $mvTeamCtx) ? $mvOn : $mvOff }}">
            <i class="bx bx-group me-1"></i> Team
        </a>
    </div>
    <form method="GET" class="mb-0">
        @if($mvFy)<input type="hidden" name="fy" value="{{ $mvFy }}">@endif
        {{-- Carry the Team flag through. Without it, picking a teammate dropped
             ?team=1 from the URL and the page silently fell back to My Data —
             the filter appeared to reset itself the moment it was used. --}}
        @if(request()->boolean('team'))<input type="hidden" name="team" value="1">@endif
        <select name="{{ $mvMemberParam }}"
                class="form-select form-select-sm fw-semibold"
                style="{{ $mvSelStyle }}"
                onchange="this.form.submit()">
            <option value="" style="color:#333;background:#fff;">— Team member —</option>
            @foreach(($teamMembers ?? collect()) as $tm)
            <option value="{{ $tm->id }}" style="color:#333;background:#fff;" {{ $mvSelected == $tm->id ? 'selected' : '' }}>{{ $tm->name }}</option>
            @endforeach
        </select>
    </form>
</div>
@endif
