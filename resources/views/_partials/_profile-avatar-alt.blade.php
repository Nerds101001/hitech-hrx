<!-- resources/views/components/profile-avatar-alt.blade.php -->
@php
  //Initials from first name and last
@endphp
<div class="d-flex justify-content-start align-items-center user-name">
  <div class="avatar-wrapper">
    <div class="avatar avatar-sm me-2">
      @if($profile_picture)
        <img src="{{ $profile_picture }}" alt="Avatar" class="avatar rounded-circle"/>
      @else
        <span class="avatar-initial rounded-circle bg-label-primary">{{$initials}}</span>
      @endif
    </div>
  </div>
  <div class="d-flex flex-column">
    <a href="{{route('employees.show',$id)}}"
       class="text-heading text-truncate fw-medium">{{ $first_name }}</a>
    @if(isset($code))
      <small>{{ $code }}</small>
    @else
      <small>{{ $email }}</small>
    @endif
  </div>
</div>
