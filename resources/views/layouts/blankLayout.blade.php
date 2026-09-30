@isset($pageConfigs)
{!! Helper::updatePageConfig($pageConfigs) !!}
@endisset
@php
$configData = Helper::appClasses();

/* Display elements */
$customizerHidden = ($customizerHidden ?? '');

@endphp

@extends('layouts/commonMaster' )

@section('layoutContent')

<!-- Content -->
@include('layouts.sections.delegate_exit_bar')
@if(request()->routeIs('training.portal') && ($trainingLock = \App\Support\TrainingLock::progressFor(auth()->user())))
  @include('layouts.sections.training-lock-banner', ['lock' => $trainingLock, 'bannerClass' => 'm-0 rounded-0 border-0 border-bottom'])
@endif
@yield('content')
<!--/ Content -->

@endsection
