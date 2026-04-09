@props([
    'user',
    'showCode' => true,
    'linkRoute' => 'employees.show',
    'avatarSize' => 'sm'
])

<div class="d-flex justify-content-start align-items-center user-name">
    <div class="avatar-wrapper">
        <div class="avatar avatar-{{ $avatarSize }} me-3">
            @if($user->profile_picture)
                <img src="{{ $user->getProfilePicture() }}" alt="Avatar" class="rounded-circle" />
            @else
                <span class="avatar-initial rounded-circle bg-label-primary">{{ $user->getInitials() }}</span>
            @endif
        </div>
    </div>
    <div class="d-flex flex-column">
        <a href="{{ route($linkRoute, $user->id) }}"
           class="text-heading text-truncate">
            <span class="fw-medium">{{ $user->getFullName() }}</span>
        </a>
        @if($showCode && isset($user->code))
            <small class="text-muted">{{ $user->code }}</small>
        @else
            <small class="text-muted">{{ $user->email }}</small>
        @endif
    </div>
</div>
