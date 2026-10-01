{{-- Viewer footer, right: next module. --}}
@if($nextModule)
    <a href="{{ route('training.module.show', $nextModule->id) }}" class="rd-foot-btn primary" title="{{ $nextModule->title }}"><span>Next Module</span><i class="bx bx-right-arrow-alt"></i></a>
@else
    <span></span>
@endif
