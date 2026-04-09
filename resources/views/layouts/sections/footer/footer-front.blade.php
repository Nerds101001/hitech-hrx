<!-- Footer: Start -->
<footer class="landing-footer bg-body footer-text">
  <div class="footer-top position-relative overflow-hidden z-1">
    <img src="{{asset('assets/img/front-pages/backgrounds/footer-bg.png')}}" alt="footer bg"
         class="footer-bg banner-bg-img z-n1" />
    <div class="container">
      <div class="row gx-0 gy-6 g-lg-10">
        <div class="col-lg-5">
          <a href="javascript:;" class="app-brand-link mb-6">
            <span class="app-brand-logo demo">
              <img src="{{asset('assets/img/logo.png')}}" alt="Logo" width="27">
            </span>
            <span
              class="app-brand-text demo text-white fw-bold ms-2 ps-1">{{config('variables.templateFullName')}}</span>
          </a>
          <p class="footer-text footer-logo-description mb-6">
            {{config('variables.templateDescription')}}
          </p>
        </div>
      </div>
    </div>
  </div>
  <div class="footer-bottom py-3 py-md-5">
    <div class="container d-flex flex-wrap justify-content-between flex-md-row flex-column text-center text-md-start">
      <div class="mb-2 mb-md-0">
        <span class="footer-bottom-text">©
          <script>
          document.write(new Date().getFullYear());
          </script>
        </span>
        <span class="footer-bottom-text"> Made with ❤️ by <a href="javascript:;" target="_blank" class="text-white">{{config('variables.creatorName')}},</a> All rights reserved.</span>
      </div>
    </div>
  </div>
</footer>
<!-- Footer: End -->
