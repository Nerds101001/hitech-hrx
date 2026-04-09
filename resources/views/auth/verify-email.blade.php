@php
  $customizerHidden = 'customizer-hide';
@endphp

@extends('layouts/layoutMaster')

@section('title', 'Verify Email Basic - Pages')

@section('page-style')
  <!-- Page -->
  @vite('resources/assets/vendor/scss/pages/page-auth.scss')
@endsection

@section('content')
  <div class="authentication-wrapper authentication-basic px-4">
    <div class="authentication-inner">


      <!-- Verify Email -->
      <div class="card px-sm-6 px-0">
        <div class="card-body">
          <!-- Logo -->
          <div class="app-brand justify-content-center mb-6">
            <a href="{{url('/')}}" class="app-brand-link gap-2">
                            <span class="app-brand-logo demo">
                               <img src="{{asset('assets/img/logo.png')}}" alt="Logo" width="27">
                            </span>
              <span class="app-brand-text demo text-heading fw-bold">{{ config('variables.templateFullName') }}</span>
            </a>
          </div>
          <!-- /Logo -->
          <h4 class="mb-1">Verify your email ✉️</h4>
          <p class="text-start mb-0">
            Account activation link sent to your email address: <span
              class="fw-medium text-heading">{{auth()->user()->email}}</span>
            Please follow the link inside to continue.
          </p>
          <form method="POST" action="{{ route('verification.send') }}">
            @method('POST')
            @csrf
            <button type="submit" class="btn btn-primary w-100 my-6">
              Resend verification email
            </button>
          </form>
          <div class="d-flex-row text-center">
            <form action="{{ route('auth.logout') }}" method="POST">
              @csrf
              <button class="btn btn-link text-center mb-0 mt-3">
                Logout
              </button>
            </form>
          </div>
        </div>
      </div>
      <!-- /Verify Email -->
    </div>
  </div>
@endsection
