@extends('layouts/layoutMaster')

@section('title', __('Support'))

@section('content')
  <div class="row mb-4 text-center">
    <div class="col">
      <h2 class="fw-bold">@lang('We are here to help!')</h2>
      <p
        class="text-muted">@lang('For any inquiries, customizations, or support, feel free to reach out to us through any of the options below.')</p>
    </div>
  </div>

  <!-- Contact Information -->
  <div class="row mb-5">
    <div class="col-lg-6 mb-4">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-body text-center">
          <h5 class="fw-bold">@lang('Contact Us')</h5>
          <p>@lang('Get in touch through phone, email, or WhatsApp.')</p>
          <div class="d-flex flex-column align-items-center">
            <div class="mb-3">
              <i class="fa fa-phone fs-5 text-primary me-1"></i>
              <a href="tel:{{config('variables.supportNumber')}}"
                 class="text-decoration-none">
                @lang('Phone'): {{config('variables.supportNumber')}}</a>
            </div>
            <div class="mb-3">
              <i class="fa-brands fa-whatsapp fs-4 text-success me-1"></i>
              <a href="{{config('variables.supportWhatsappURL')}}" target="_blank"
                 class="text-decoration-none">@lang('WhatsApp'): {{config('variables.supportNumber')}}</a>
            </div>
            <div class="mb-3">
              <i class="bx bx-mail-send fs-4 text-warning me-1"></i>
              <a href="mailto:{{config('variables.supportEmail')}}" target="_blank"
                 class="text-decoration-none">@lang('Email'): {{config('variables.supportEmail')}}</a>
            </div>
            <div>
              <i class="fa fa-globe fs-5 text-info me-1"></i>
              <a href="{{config('variables.creatorUrl')}}" target="_blank"
                 class="text-decoration-none">@lang('Website'): {{config('variables.creatorUrl')}}</a>

            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Social Media -->
    <div class="col-lg-6 mb-4">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-body text-center">
          <h5 class="fw-bold">@lang('Follow Us')</h5>
          <p>@lang('Connect with us on social media platforms.')</p>
          <div class="d-flex justify-content-center gap-3">
            <a href="{{config('variables.linkedInUrl')}}" class="text-decoration-none text-primary">
              <i class="fa-brands fa-linkedin fs-1"></i>
            </a>
            <a href="{{config('variables.instagramUrl')}}" class="text-decoration-none text-danger">
              <i class="fa-brands fa-instagram fs-1"></i>
            </a>
            <a href="{{config('variables.youtubeUrl')}}" class="text-decoration-none text-danger">
              <i class="fa-brands fa-youtube fs-1"></i>
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Our Services -->
  <div class="row mb-5">
    <div class="col">
      <div class="card shadow-sm border-0">
        <div class="card-body text-center">
          <h5 class="fw-bold">@lang('Our Services')</h5>
          <p>@lang('We provide a wide range of services to meet your needs.')</p>
          <div class="row mt-4">
            <div class="col-lg-4">
              <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                  <i class="bx bx-code-alt fs-3 text-primary"></i>
                  <h6 class="mt-3">@lang('Custom Software Development')</h6>
                  <p class="text-muted">@lang('Tailored solutions to meet your business needs.')</p>
                </div>
              </div>
            </div>
            <div class="col-lg-4">
              <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                  <i class="bx bx-paint fs-3 text-success"></i>
                  <h6 class="mt-3">@lang('Product Customizations')</h6>
                  <p class="text-muted">@lang('Enhance and modify products to your specifications.')</p>
                </div>
              </div>
            </div>
            <div class="col-lg-4">
              <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                  <i class="bx bx-support fs-3 text-warning"></i>
                  <h6 class="mt-3">@lang('Support & Queries')</h6>
                  <p class="text-muted">@lang('Prompt assistance for any inquiries or issues.')</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{--  <!-- Placeholder Image -->
    <div class="row">
      <div class="col text-center">
        <img src="https://placehold.co/800x400" alt="@lang('Support')" class="img-fluid rounded shadow">
        <p class="text-muted mt-3">@lang('Your trusted partner for all your support needs.')</p>
      </div>
    </div>--}}
@endsection
