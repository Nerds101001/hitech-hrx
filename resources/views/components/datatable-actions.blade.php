@props([
    'id',
    'actions' => []
])

<div class="dropdown">
    <button type="button" class="btn btn-sm btn-icon btn-text-secondary rounded-pill waves-effect dropdown-toggle hide-arrow"
            data-bs-toggle="dropdown" aria-expanded="false">
        <i class="bx bx-dots-vertical-rounded"></i>
    </button>
    <ul class="dropdown-menu dropdown-menu-end">
        @foreach($actions as $action)
            @if(isset($action['divider']) && $action['divider'])
                <li><hr class="dropdown-divider"></li>
            @else
                <li>
                    @if(isset($action['url']))
                        <a class="dropdown-item" href="{{ $action['url'] }}">
                            <i class="{{ $action['icon'] ?? 'bx bx-edit' }} me-2"></i>
                            <span>{{ $action['label'] }}</span>
                        </a>
                    @elseif(isset($action['onclick']))
                        <a class="dropdown-item" href="javascript:void(0);" onclick="{{ $action['onclick'] }}">
                            <i class="{{ $action['icon'] ?? 'bx bx-edit' }} me-2"></i>
                            <span>{{ $action['label'] }}</span>
                        </a>
                    @elseif(isset($action['modal']))
                        <a class="dropdown-item" href="javascript:void(0);"
                           data-bs-toggle="modal"
                           data-bs-target="{{ $action['modal'] }}"
                           @if(isset($action['data']))
                               @foreach($action['data'] as $key => $value)
                                   data-{{ $key }}="{{ $value }}"
                               @endforeach
                           @endif>
                            <i class="{{ $action['icon'] ?? 'bx bx-edit' }} me-2"></i>
                            <span>{{ $action['label'] }}</span>
                        </a>
                    @elseif(isset($action['class']))
                        <a class="dropdown-item {{ $action['class'] }}" href="javascript:void(0);"
                           @if(isset($action['data-id']))
                               data-id="{{ $action['data-id'] }}"
                           @endif
                           @if(isset($action['data-status']))
                               data-status="{{ $action['data-status'] }}"
                           @endif
                           @if(isset($action['data']))
                               @foreach($action['data'] as $key => $value)
                                   data-{{ $key }}="{{ $value }}"
                               @endforeach
                           @endif>
                            <i class="{{ $action['icon'] ?? 'bx bx-edit' }} me-2"></i>
                            <span>{{ $action['label'] }}</span>
                        </a>
                    @endif
                </li>
            @endif
        @endforeach
    </ul>
</div>
