@php use App\Enums\PlanDurationType; @endphp
@extends('layouts/layoutMaster')

@section('title', __('Edit Plan'))

@section('content')
  <div class="row">
    <div class="col">
      <h4>@lang('Edit Plan')</h4>
    </div>
  </div>

  <form id="planForm" action="{{ route('plans.update', $plan->id) }}" method="POST">
    @csrf
    @method('PUT')
    <div class="row">
      <!-- Plan Form Section -->
      <div class="col-lg-6 col-md-12">
        <div class="card shadow-sm border-0 rounded-4">
          <div class="card-body">
            <div class="mb-4">
              <label class="form-label" for="name">@lang('Name')<span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="name" placeholder="@lang('Enter name')" name="name"
                     value="{{ $plan->name }}"/>
            </div>

            <div class="mb-4">
              <label class="form-label" for="duration">@lang('Duration')<span class="text-danger">*</span></label>
              <input type="number" class="form-control" id="duration" placeholder="@lang('Enter duration')"
                     name="duration" value="{{ $plan->duration }}"/>
            </div>

            <div class="mb-4">
              <label class="form-label" for="includedUsers">@lang('Included Users')</label>
              <input type="number" class="form-control" id="includedUsers" placeholder="@lang('Enter included users')"
                     name="includedUsers" value="{{ $plan->included_users }}"/>
            </div>

            <div class="mb-4">
              <label class="form-label" for="basePrice">@lang('Base Price')</label>
              <input type="number" class="form-control" id="basePrice" placeholder="@lang('Enter base price')"
                     name="basePrice" value="{{ $plan->base_price }}"/>
            </div>

            <div class="mb-4">
              <label class="form-label" for="perUserPrice">@lang('Per User Price')</label>
              <input type="number" class="form-control" id="perUserPrice" placeholder="@lang('Enter per user price')"
                     name="perUserPrice" value="{{ $plan->per_user_price }}"/>
            </div>

            <div class="mb-4">
              <label class="form-label" for="durationType">@lang('Duration Type')<span
                  class="text-danger">*</span></label>
              <select class="form-select" id="durationType" name="durationType">
                <option value="">@lang('Select Duration Type')</option>
                @foreach(PlanDurationType::cases() as $duration)
                  <option
                    value="{{ $duration->value }}" {{  $duration->value == $plan->duration_type->value ? 'selected' : '' }}>
                  @lang($duration->name)
                @endforeach
              </select>
            </div>

            <div class="mb-4">
              <label class="form-label" for="description">@lang('Description')</label>
              <textarea class="form-control" id="description" placeholder="@lang('Enter description')"
                        name="description">{{ $plan->description }}</textarea>
            </div>

            <div class="d-flex justify-content-between">
              <button type="submit" class="btn btn-primary">@lang('Update')</button>
              <a href="{{ route('plans.index') }}" class="btn btn-secondary">@lang('Cancel')</a>
            </div>
          </div>
        </div>
      </div>

      <!-- Module Toggles Section -->
      <div class="col-lg-6 col-md-12">
        <h6 class="mb-3 mt-3">@lang('Manage Standard Modules')</h6>
        <div class="card shadow-sm border-0 rounded-4">
          <div class="card-body">
            <div class="row">
              @foreach(ModuleConstants::STANDARD_MODULES as $module)
                <div class="col-lg-6 col-md-12 mb-3">
                  <div class="d-flex justify-content-between align-items-center pe-4">
                    <!-- Module Name -->
                    <span class="fw-semibold text-dark"
                          style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 70%;">
                  {{ $module }}
                </span>
                    <!-- Toggle Switch -->
                    <label class="switch mb-0">
                      <input type="checkbox" class="switch-input status-toggle" id="{{ $module }}"
                             name="{{ $module }}"
                        {{ in_array($module, $plan->modules ?? []) ? 'checked' : '' }} />
                      <span class="switch-toggle-slider">
                    <span class="switch-on"><i class="bx bx-check"></i></span>
                    <span class="switch-off"><i class="bx bx-x"></i></span>
                  </span>
                    </label>
                  </div>
                </div>
              @endforeach
            </div>
          </div>
        </div>
        <h6 class="mb-3 mt-3">@lang('Manage Premium Modules')</h6>
        <div class="card shadow-sm border-0 rounded-4">
          <div class="card-body">
            <div class="row">
              @foreach(Module::toCollection() as $module)
                @if(in_array($module->getName(), ModuleConstants::ATTENDANCE_TYPES))
                  @continue
                @endif
                <div class="col-lg-6 col-md-12 mb-3">
                  <div class="d-flex justify-content-between align-items-center pe-4">
                    <!-- Module Name -->
                    <span class="fw-semibold text-dark"
                          style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 70%;">
                  {{ $module->getName() }}
                </span>
                    <!-- Toggle Switch -->
                    <label class="switch mb-0">
                      <input type="checkbox" class="switch-input status-toggle" id="{{ $module->getName() }}"
                             name="{{ $module->getName() }}"
                        {{ in_array($module->getName(), $plan->modules ?? []) ? 'checked' : '' }} />
                      <span class="switch-toggle-slider">
                    <span class="switch-on"><i class="bx bx-check"></i></span>
                    <span class="switch-off"><i class="bx bx-x"></i></span>
                  </span>
                    </label>
                  </div>
                </div>
              @endforeach
            </div>
          </div>
        </div>
        <h6 class="mb-3 mt-3">@lang('Manage Attendance Types')</h6>
        <div class="card shadow-sm border-0 rounded-4">
          <div class="card-body">
            <div class="row">
              @foreach(Module::toCollection() as $module)
                @if(!in_array($module->getName(), ModuleConstants::ATTENDANCE_TYPES))
                  @continue
                @endif
                <div class="col-lg-6 col-md-12 mb-3">
                  <div class="d-flex justify-content-between align-items-center pe-4">
                    <!-- Module Name -->
                    <span class="fw-semibold text-dark"
                          style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 70%;">
                  {{ $module->getName() }}
                </span>
                    <!-- Toggle Switch -->
                    <label class="switch mb-0">
                      <input type="checkbox" class="switch-input status-toggle" id="{{ $module->getName() }}"
                             name="{{ $module->getName() }}"
                        {{ in_array($module->getName(), $plan->modules ?? []) ? 'checked' : '' }} />
                      <span class="switch-toggle-slider">
                    <span class="switch-on"><i class="bx bx-check"></i></span>
                    <span class="switch-off"><i class="bx bx-x"></i></span>
                  </span>
                    </label>
                  </div>
                </div>
              @endforeach
            </div>
          </div>
        </div>
      </div>
    </div>
  </form>
@endsection
