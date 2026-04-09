{{-- Breadcrumb Component --}}
@props(['title', 'breadcrumbs' => [], 'homeUrl' => null])

<div class="row">
    <div class="col-12">
        <div class="page-title-box d-sm-flex align-items-center justify-content-between mb-4">
            <h4 class="mb-sm-0">{{ $title }}</h4>

            @if(count($breadcrumbs) > 0)
            <div class="page-title-right">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb m-0">
                        @if($homeUrl)
                        <li class="breadcrumb-item">
                            <a href="{{ $homeUrl }}">
                                <i class="bx bx-home"></i> {{ __('Home') }}
                            </a>
                        </li>
                        @endif
                        @foreach($breadcrumbs as $breadcrumb)
                            @if($loop->last)
                                <li class="breadcrumb-item active" aria-current="page">
                                    {{ $breadcrumb['name'] }}
                                </li>
                            @else
                                <li class="breadcrumb-item">
                                    <a href="{{ $breadcrumb['url'] }}">{{ $breadcrumb['name'] }}</a>
                                </li>
                            @endif
                        @endforeach
                    </ol>
                </nav>
            </div>
            @endif

        </div>
    </div>
</div>

{{-- Optional slot for action buttons --}}
@if(isset($actions))
    <div class="row mb-3">
        <div class="col-12">
            <div class="d-flex justify-content-end gap-2">
                {{ $actions }}
            </div>
        </div>
    </div>
@endif