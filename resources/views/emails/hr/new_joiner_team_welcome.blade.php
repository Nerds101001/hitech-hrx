@extends('emails.layout')

@section('title', 'Meet ' . $joiner->getFullName())
@section('badge', 'New Teammate')
@section('header_title', 'Say Hello to ' . \Illuminate\Support\Str::of($joiner->getFullName())->explode(' ')->first() . '!')
@section('greeting', 'Hi Team,')

@section('style')
<style>
  .ntw-intro { font-size: 15px; line-height: 1.75; color: #475569; margin-bottom: 28px; }
  .ntw-intro strong { color: #0f172a; }

  .ntw-hero {
    border: 1px solid #e2e8f0; border-radius: 18px; overflow: hidden;
    margin-bottom: 10px; background: #ffffff;
  }
  .ntw-hero-top {
    background: linear-gradient(135deg, #003d3d 0%, #005a5a 55%, #0d9488 100%);
    padding: 30px 26px; text-align: center;
  }
  .ntw-avatar {
    width: 84px; height: 84px; border-radius: 50%;
    border: 4px solid rgba(255,255,255,0.9); display: block; margin: 0 auto 14px;
    object-fit: cover; background: #ffffff;
  }
  .ntw-avatar-fallback {
    width: 84px; height: 84px; border-radius: 50%; margin: 0 auto 14px;
    border: 4px solid rgba(255,255,255,0.9); background: rgba(255,255,255,0.16);
    color: #ffffff; font-size: 29px; font-weight: 800; line-height: 76px; text-align: center;
  }
  .ntw-name { font-size: 22px; font-weight: 800; color: #ffffff; letter-spacing: -0.3px; }
  .ntw-role { font-size: 14px; color: rgba(255,255,255,0.9); margin-top: 5px; font-weight: 600; }

  .ntw-foot {
    margin-top: 26px; padding-top: 18px; border-top: 1px solid #f1f5f9;
    font-size: 12.5px; color: #94a3b8; line-height: 1.65;
  }
</style>
@endsection

@section('content')
  <p class="ntw-intro">
    <strong>{{ $joiner->getFullName() }}</strong> just joined your team{{ $team ? ' — ' . $team : '' }}.
    Take a moment to say hi and help them settle in.
  </p>

  <div class="ntw-hero">
    <div class="ntw-hero-top">
      @if($joiner->getProfilePicture())
        <img src="{{ $joiner->getProfilePicture() }}" alt="" class="ntw-avatar">
      @else
        <div class="ntw-avatar-fallback">{{ $joiner->getInitials() }}</div>
      @endif
      <div class="ntw-name">{{ $joiner->getFullName() }}</div>
      <div class="ntw-role">
        {{ $designation ?: 'Team Member' }}
        @if($team) &middot; {{ $team }} @endif
      </div>
    </div>
  </div>

  <p class="ntw-foot">
    This is an automated notification from HRX, sent to teammates when a new joiner is approved.
  </p>
@endsection
