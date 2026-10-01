{{-- Viewer footer, left: previous module, and the course contents on phones. --}}
<div class="d-flex align-items-center gap-2" style="flex-shrink:0;">
    @if($prevModule)
        <a href="{{ route('training.module.show', $prevModule->id) }}" class="rd-foot-btn" title="{{ $prevModule->title }}"><i class="bx bx-left-arrow-alt"></i><span>Previous Module</span></a>
    @endif
    <button type="button" class="rd-foot-btn course-nav-toggle" id="cnav-toggle-bottom"><i class="bx bx-list-ul"></i><span>Contents</span></button>
</div>
