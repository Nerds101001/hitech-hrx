@php
  $customizerHidden = 'customizer-hide';

@endphp

@extends('layouts/layoutMaster')

@section('title', 'Register')

@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/@form-validation/form-validation.scss'
  ])
@endsection

@section('page-style')
  @vite([
    'resources/assets/vendor/scss/pages/page-auth.scss'
  ])
@endsection

@section('vendor-script')
  @vite([
    'resources/assets/vendor/libs/@form-validation/popular.js',
    'resources/assets/vendor/libs/@form-validation/bootstrap5.js',
    'resources/assets/vendor/libs/@form-validation/auto-focus.js'
  ])
@endsection

@section('page-script')
  @vite([
    'resources/assets/js/pages-auth.js'
  ])
@endsection

@section('content')
  <div class="container-xxl">
    <div class="authentication-wrapper authentication-basic container-p-y">
      <div class="">

        <!-- Register Card -->
        <div class="card px-sm-6 px-0">
          <div class="card-body">
            <!-- Logo -->
            <div class="app-brand justify-content-center mb-6">
              <a href="{{url('/')}}" class="app-brand-link gap-2">
                <span
                  class="app-brand-logo demo">
                   <img src="{{asset('assets/img/logo.png')}}" alt="Logo" width="27">
                </span>
                <span class="app-brand-text demo text-heading fw-bold">{{config('variables.templateFullName')}}</span>
              </a>
            </div>
            <!-- /Logo -->
            <h4 class="mb-1">Adventure starts here 🚀</h4>
            <p class="mb-6">{{config('variables.templateDescription')}}</p>

            <form id="formAuthentication" class="mb-6" action="{{route('auth.registerPost')}}" method="POST">
              @csrf
              <div class="row">
                <div class="mb-6 col-6">
                  <label for="firstName" class="form-label">@lang('First Name')</label>
                  <input type="text" class="form-control" id="firstName" name="firstName"
                         placeholder="@lang('Enter your first name')" value="{{old('firstName')}}" autofocus>
                </div>
                <div class="mb-6 col-6">
                  <label for="lastName" class="form-label">@lang('Last Name')</label>
                  <input type="text" class="form-control" id="lastName" name="lastName"
                         placeholder="@lang('Enter your last name')" value="{{old('lastName')}}">
                </div>
              </div>
              <div class="mb-6">
                <label for="phone" class="form-label">@lang('Phone Number')</label>
                <input type="number" class="form-control" id="phone" name="phone"
                       placeholder="@lang('Enter your phone number')"
                       value="{{old('phone')}}">
              </div>
              <div class="mb-6">
                <label for="gender" class="form-label">@lang('Gender')</label>
                <select class="form-control" id="gender" name="gender">
                  <option value="male" {{old('gender') == 'male'? 'selected' :  ''}}>Male</option>
                  <option value="female" {{old('gender') == 'female'? 'selected' :  ''}}>Female</option>
                  <option value="other" {{old('gender') == 'other'? 'selected' :  ''}}>Other</option>
                </select>
              </div>
              <div class="mb-6">
                <label for="email" class="form-label">@lang('Email')</label>
                <input type="text" class="form-control" id="email" name="email" value="{{old('email')}}"
                       placeholder="@lang('Enter your email')">
              </div>

              <div class="mb-6 form-password-toggle">
                <label class="form-label" for="password">@lang('Password')</label>
                <div class="input-group input-group-merge">
                  <input type="password" id="password" class="form-control" name="password"
                         placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;"
                         aria-describedby="password" />
                  <span class="input-group-text cursor-pointer"><i class="bx bx-hide"></i></span>
                </div>
              </div>
              <div class="mb-6 form-password-toggle">
                <label class="form-label" for="confirmPassword">@lang('Confirm Password')</label>
                <div class="input-group input-group-merge">
                  <input type="password" id="confirmPassword" class="form-control" name="confirmPassword"
                         placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;"
                         aria-describedby="confirmPassword" />
                  <span class="input-group-text cursor-pointer"><i class="bx bx-hide"></i></span>
                </div>
              </div>
              <div class="my-8">
                <div class="form-check mb-0 ms-2">
                  <input class="form-check-input" type="checkbox" id="termsConditions" name="termsConditions">
                  <label class="form-check-label" for="termsConditions">
                    @lang('I agree to the')
                    <a href="javascript:void(0);">@lang('Privacy policy & terms')</a>
                  </label>
                </div>
              </div>
              <button class="btn btn-primary d-grid w-100">
                @lang('Register')
              </button>
            </form>

            <p class="text-center">
              <span>@lang('Already have an account?')</span>
              <a href="{{route('login')}}">
                <span>@lang('Log in instead')</span>
              </a>
            </p>
          </div>
          <!-- Register Card -->
        </div>
      </div>
    </div>
@endsection
