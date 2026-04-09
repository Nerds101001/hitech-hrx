<!-- resources/views/components/profile-avatar.blade.php -->
@php
  $user = isset($request) ? $request->user : $user;
@endphp
<div class="d-flex justify-content-start align-items-center user-name">
  <div class="avatar-wrapper">
    <div class="avatar avatar-sm me-2">
      @php $profilePic = $user->getProfilePicture(); @endphp
      @if($profilePic)
        <img src="{{ $profilePic }}" alt="Avatar" class="avatar rounded-circle"/>
      @else
        <span class="avatar-initial rounded-circle bg-label-primary">{{ $user->getInitials() }}</span>
      @endif
    </div>
  </div>
  <div class="d-flex flex-column">
    <a href="{{route('employees.show',$user->id)}}"
       class="text-heading text-truncate fw-medium">{{ $user->getFullName() }}</a>
    @if(isset($user->code))
      <small>{{ $user->code }}</small>
    @else
      <small>{{ $user->email }}</small>
    @endif
  </div>
</div>
