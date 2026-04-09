@extends('layouts.layoutMaster')

@section('title', 'Live Demo Preview')

@section('content')
  <div class="container py-5">

    <!-- App Branding Section -->
    <div class="text-center mb-5">
      <img src="{{asset('assets/img/logo.png')}}" alt="App Logo" class="mb-3" style="max-width: 50px;">
      <h4 class="fw-bold text-primary mb-2">{{config('variables.templateName')}} <span></span>
        <p class="lead text-muted">{{config('variables.templateDescription')}}
    </div>

    <!-- Page Title -->
    <div class="text-center mb-5">
      <h1 class="fw-bold display-6 text-primary">Live Demo Preview</h1>
      <p class="text-muted">Get hands-on experience with our platform. Choose your demo to explore the features!</p>
    </div>

    <!-- Demo Sections -->
    <div class="row g-5 align-items-stretch">
      <!-- Super Admin Panel / Tenant Panel Demo -->
      <div class="col-lg-6">
        <div class="card shadow border-0 h-100">
          <div class="card-body d-flex flex-column">
            <div class="mb-4">
              <img src="https://placehold.co/1024x576?text=Admin+Panel" alt="Admin Panel"
                   class="img-fluid rounded shadow-sm mb-3">
              <h3 class="fw-bold text-primary">Admin Panel</h3>
              <p class="">
                Manage your system seamlessly with powerful tools for configuration and administration.
              </p>
            </div>
            <div class="mt-auto d-flex flex-column gap-2">
              <a href="{{config('variables.superAdminPanelDemoLink')}}" target="_blank"
                 class="btn btn-primary btn-lg w-100">
                Visit Admin Panel
              </a>
            </div>
          </div>
        </div>
      </div>

      <!-- Application Demo (Android) -->
      <div class="col-lg-6">
        <div class="card shadow border-0 h-100">
          <div class="card-body d-flex flex-column">
            <div class="mb-4">
              <img src="https://placehold.co/1024x576?text=Android+App+Demo" alt="Android Demo"
                   class="img-fluid rounded shadow-sm mb-3">
              <h3 class="fw-bold android-color">Application Demo (Android)</h3>
              <p class="">
                Download the Android app and explore its features on your mobile device.
              </p>
            </div>
            <div class="mt-auto">
              <a href="{{config('variables.appDemoLink')}}" target="_blank"
                 class="btn btn-success btn-lg w-100">
                Download Android Demo App
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <style>
    /* General Layout */
    .container {
      max-width: 1200px;
    }

    .text-primary {
      color: #0056b3 !important;
    }

    /* Card */
    .card {
      border-radius: 32px;
    }

    .card-body {
      display: flex;
      flex-direction: column;
    }

    /* Buttons */
    .btn-lg {
      padding: 0.8rem 1.2rem;
      font-size: 1.1rem;
    }

    .btn-primary {
      background-color: #0056b3;
      border: none;
    }

    .btn-outline-secondary {
      border: 2px solid #adb5bd;
      color: #6c757d;
    }

    .btn-success {
      background-color: #198754;
      border: none;
    }

    .android-color {
      color: #198754;
    }

    /* Images */
    .img-fluid {
      border-radius: 8px;
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }
  </style>
@endsection
