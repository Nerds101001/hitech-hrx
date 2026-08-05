{{--
  Celebrations — Birthdays and Work Anniversaries, shared by the Admin and HR
  dashboards so the two can never drift apart again.

  Expects from the controller (DashboardController::index):
    $upcomingBirthdays      users with ->dob,             ->is_today, ->is_wished
    $upcomingAnniversaries  users with ->date_of_joining,  ->is_today, ->is_wished

  Clicking a person opens the shared Send-a-Wish modal, which lives in
  tenant/components/birthday-modals.blade.php and is already included globally
  by commonMaster — openSendWishModal(id, name, 'birthday'|'anniversary').
--}}
@php
    $celebBirthdays     = collect($upcomingBirthdays ?? []);
    $celebAnniversaries = collect($upcomingAnniversaries ?? []);
@endphp

{{-- ── Birthdays ─────────────────────────────────────────────────────── --}}
<div class="col-xl-6">
  <div class="adc-card h-100">
    <div class="adc-card-head">
      <div>
        <h6 class="adc-card-title"><i class="bx bx-cake me-1"></i>Birthdays</h6>
        <div class="adc-card-sub">Next 30 days</div>
      </div>
      <a href="{{ route('employee.celebrations') }}" class="adc-link">View all</a>
    </div>

    @if($celebBirthdays->isEmpty())
      <div class="adc-empty adc-empty-sm"><i class="bx bx-cake"></i><span>No upcoming birthdays.</span></div>
    @else
      <ul class="adc-appr adc-rail-scroll" style="max-height:290px">
        @foreach($celebBirthdays as $u)
          @php $celebName = trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) ?: ($u->name ?? 'Employee'); @endphp
          <li>
            <img class="adc-appr-av"
                 src="{{ $u->getProfilePicture() ?: 'https://ui-avatars.com/api/?name='.urlencode($celebName).'&background=ec4899&color=fff&bold=true' }}"
                 alt="" onerror="this.src='https://ui-avatars.com/api/?name={{ urlencode($celebName) }}&background=ec4899&color=fff&bold=true'">
            <div class="adc-appr-body">
              <div class="adc-appr-name">
                {{ $celebName }}
                @if($u->is_today ?? false)
                  <span class="adc-badge adc-tone-danger ms-1">TODAY</span>
                @endif
              </div>
              <div class="adc-appr-meta">
                {{ $u->dob ? \Carbon\Carbon::parse($u->dob)->format('d M') : '' }}
                @if(!empty($u->designation?->name)) · {{ $u->designation->name }} @endif
              </div>
            </div>
            @if($u->is_wished ?? false)
              <span class="adc-badge adc-ok"><i class="bx bx-check"></i> Wished</span>
            @else
              <button type="button" class="btn btn-sm btn-outline-danger rounded-pill py-0 px-2"
                      style="font-size:.7rem;"
                      data-uid="{{ $u->id }}" data-uname="{{ $celebName }}"
                      onclick="openSendWishModal(this.dataset.uid, this.dataset.uname, 'birthday')">
                Wish <i class="bx bx-send ms-1"></i>
              </button>
            @endif
          </li>
        @endforeach
      </ul>
    @endif
  </div>
</div>

{{-- ── Work Anniversaries ────────────────────────────────────────────── --}}
<div class="col-xl-6">
  <div class="adc-card h-100">
    <div class="adc-card-head">
      <div>
        <h6 class="adc-card-title"><i class="bx bx-party me-1"></i>Work Anniversaries</h6>
        <div class="adc-card-sub">Next 30 days</div>
      </div>
      <a href="{{ route('employee.celebrations') }}" class="adc-link">View all</a>
    </div>

    @if($celebAnniversaries->isEmpty())
      <div class="adc-empty adc-empty-sm"><i class="bx bx-party"></i><span>No upcoming anniversaries.</span></div>
    @else
      <ul class="adc-appr adc-rail-scroll" style="max-height:290px">
        @foreach($celebAnniversaries as $u)
          @php
            $celebName = trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) ?: ($u->name ?? 'Employee');
            // Years completed, not years-and-a-bit — a 4y11m anniversary is still "4 YRS".
            $celebYears = $u->date_of_joining
                ? (int) floor(\Carbon\Carbon::parse($u->date_of_joining)->diffInYears(now()))
                : null;
          @endphp
          <li>
            <img class="adc-appr-av"
                 src="{{ $u->getProfilePicture() ?: 'https://ui-avatars.com/api/?name='.urlencode($celebName).'&background=8b5cf6&color=fff&bold=true' }}"
                 alt="" onerror="this.src='https://ui-avatars.com/api/?name={{ urlencode($celebName) }}&background=8b5cf6&color=fff&bold=true'">
            <div class="adc-appr-body">
              <div class="adc-appr-name">
                {{ $celebName }}
                @if($u->is_today ?? false)
                  <span class="adc-badge adc-tone-info ms-1">TODAY</span>
                @endif
              </div>
              <div class="adc-appr-meta">
                @if($celebYears !== null){{ $celebYears }} {{ \Illuminate\Support\Str::plural('yr', $celebYears) }} · @endif
                {{ $u->date_of_joining ? \Carbon\Carbon::parse($u->date_of_joining)->format('d M') : '' }}
              </div>
            </div>
            @if($u->is_wished ?? false)
              <span class="adc-badge adc-ok"><i class="bx bx-check"></i> Wished</span>
            @else
              <button type="button" class="btn btn-sm btn-outline-primary rounded-pill py-0 px-2"
                      style="font-size:.7rem;"
                      data-uid="{{ $u->id }}" data-uname="{{ $celebName }}"
                      onclick="openSendWishModal(this.dataset.uid, this.dataset.uname, 'anniversary')">
                Congratulate <i class="bx bx-send ms-1"></i>
              </button>
            @endif
          </li>
        @endforeach
      </ul>
    @endif
  </div>
</div>
