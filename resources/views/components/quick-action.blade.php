@props([
  'title',
  'description' => '',
  'icon' => 'ti ti-plus',
  'color' => 'primary',
  'link' => '#',
  'modalTarget' => null
])

<div class="col-sm-6 col-md-4 col-lg-3">
  <a href="{{ $link }}" 
     class="quick-action-card text-decoration-none"
     @if($modalTarget) data-bs-toggle="modal" data-bs-target="{{ $modalTarget }}" @endif>
    <div class="action-icon">
      <i class="{{ $icon }} text-{{ $color }}"></i>
    </div>
    <div class="action-content">
      <div class="action-title">{{ $title }}</div>
      @if($description)
        <div class="action-subtitle">{{ $description }}</div>
      @endif
    </div>
  </a>
</div>
