@if(isset($globalPendingPolicies) && $globalPendingPolicies->isNotEmpty())
    @php
        $currentPolicy = $globalPendingPolicies->first();
    @endphp

    <!-- SweetAlert2 for notifications -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <div class="modal fade" id="forcePolicyModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-centered policy-modal-dialog">
            <div class="modal-content policy-modal-content">
                <div class="modal-header bg-danger policy-modal-header">
                    <h5 class="modal-title text-white policy-modal-title">
                        <i class="bx bx-error-circle me-2"></i> Mandatory Company Policy
                    </h5>
                    <div class="text-white small policy-modal-badge">
                        Pending: {{ $globalPendingPolicies->count() }}
                    </div>
                </div>
                <div class="modal-body p-0 d-flex flex-column">
                    <div class="p-3 bg-lighter border-bottom policy-modal-desc">
                        <span class="d-none d-md-inline"><strong>{{ $currentPolicy->title }}</strong>: Please scroll through and read the policy below. You must acknowledge it to proceed.</span>
                        <span class="d-md-none"><strong>{{ $currentPolicy->title }}</strong>: read below, then confirm.</span>
                        <a id="policyOpenNewTab" href="#" target="_blank" rel="noopener" class="d-none d-md-inline ms-2">
                            <i class="bx bx-link-external"></i> Open in a new tab
                        </a>
                    </div>

                    <!-- PDF Viewer -->
                    <div class="flex-grow-1 bg-dark policy-modal-viewer">
                        <iframe id="policyIframe" src="" width="100%" height="100%" style="border: none;"></iframe>
                    </div>

                    <!-- Mobile fallback: a direct link, since some phone browsers refuse to show a PDF inside an iframe at all -->
                    <div class="d-md-none text-center p-2 border-top" id="policyMobileOpenWrap">
                        <a id="policyMobileOpen" href="#" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm">
                            <i class="bx bx-link-external me-1"></i> Can't see the document? Open it in a new tab
                        </a>
                    </div>

                    <!-- Footer Controls: sticky, so it stays on screen as the policy area scrolls -->
                    <div class="p-3 bg-white border-top policy-modal-footer">
                        <div class="form-check mb-3 policy-modal-checkrow">
                            <input class="form-check-input" type="checkbox" id="forceConfirmCheckbox">
                            <label class="form-check-label fw-bold" for="forceConfirmCheckbox">
                                I, {{ auth()->user()->name }}, have read and understood the policy.
                            </label>
                        </div>
                        <div class="modal-footer border-0 p-0">
                            <button type="button" id="forceSignBtn" class="btn btn-primary w-100 py-3 fw-bold shadow-sm" disabled>
                                <i class="bx bx-check-double me-1"></i> I Have Read & Acknowledge This Policy
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Wait for Swal to load if it's slow
        const checkSwal = setInterval(() => {
            if (typeof Swal !== 'undefined') {
                clearInterval(checkSwal);
                initModal();
            }
        }, 100);

        function initModal() {
            const modalEl = document.getElementById('forcePolicyModal');
            
            modalEl.addEventListener('shown.bs.modal', function () {
                // Persistent cleanup for 3 seconds to beat any race conditions
                let count = 0;
                const interval = setInterval(() => {
                    document.querySelectorAll('[aria-hidden="true"]').forEach(el => {
                        el.removeAttribute('aria-hidden');
                    });
                    count++;
                    if (count > 6) clearInterval(interval);
                }, 500);
            });

            const modal = new bootstrap.Modal(modalEl);
            const policyId = '{{ $currentPolicy->id }}';
            const iframe = document.getElementById('policyIframe');
            const signBtn = document.getElementById('forceSignBtn');
            const checkbox = document.getElementById('forceConfirmCheckbox');

            modal.show();

            // Load PDF URL. Phone browsers (Android Chrome chief among them) have no
            // built-in PDF renderer for an iframe pointed straight at a PDF: instead of
            // the document they show a bare "PDF / Open" card with nothing readable
            // behind it -- which is what was reported. Desktop Chrome/Firefox/Edge DO
            // render a direct PDF link fine, so only phones are routed through Google's
            // viewer wrapper, which renders the pages as images inside the iframe.
            // Must match the CSS's own breakpoint exactly (see the <style> block below):
            // a short-landscape phone (e.g. 844x390) is still "narrow" even though its
            // WIDTH alone reads as desktop-sized.
            const isNarrow = window.matchMedia('(max-width: 767px), (max-height: 500px) and (orientation: landscape)').matches;
            fetch(`/hr-policies/embed-url/${policyId}`)
                .then(response => response.json())
                .then(data => {
                    if (!data.url) return;
                    document.getElementById('policyOpenNewTab').href = data.url;
                    document.getElementById('policyMobileOpen').href = data.url;
                    iframe.src = isNarrow
                        ? 'https://docs.google.com/gview?embedded=true&url=' + encodeURIComponent(data.url)
                        : data.url;
                });

            checkbox.addEventListener('change', function() {
                signBtn.disabled = !this.checked;
            });

            signBtn.addEventListener('click', function() {
                const btn = this;
                btn.disabled = true;
                btn.innerHTML = '<i class="bx bx-loader-alt bx-spin me-1"></i> Acknowledging...';

                $.ajax({
                    url: `/hr-policies/acknowledge/${policyId}`,
                    method: 'POST',
                    data: { _token: '{{ csrf_token() }}' },
                    success: function(response) {
                        // Close modal and show a quick toast instead of a modal alert
                        const modalEl = document.getElementById('forcePolicyModal');
                        const modal = bootstrap.Modal.getInstance(modalEl);
                        if (modal) modal.hide();

                        // Use a simple toast or just reload immediately for maximum speed
                        location.reload();
                    },
                    error: function(xhr) {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="bx bx-check-double me-1"></i> I Have Read & Acknowledge This Policy';
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: xhr.responseJSON ? xhr.responseJSON.error : 'Server Error (500). Please check storage/logs.'
                        });
                    }
                });
            });
        }
    });
    </script>
    <style>
        .modal-xl { max-width: 80% !important; }
        @media (min-width: 1200px) {
            .modal-xl { max-width: 70% !important; }
        }
        /* Desktop/tablet: restores the modal's original fixed height. It used to be an
           inline style="height: 85vh" on .modal-content; moving that into this class
           was accidentally left with no non-mobile rule, so .modal-body (and the PDF
           viewer inside it, via flex-grow) had nothing to size against and collapsed
           toward the iframe's ~150px browser default instead of filling the modal. */
        @media (min-width: 768px) {
            #forcePolicyModal .policy-modal-content {
                height: 85vh;
            }
        }
        /* Phones: the modal must fit the viewport edge-to-edge, with the PDF
           getting almost all of the height -- the header/description/footer
           chrome below used fixed desktop padding that left barely any room
           for the document itself, and .modal-xl's % based max-width did
           nothing once Bootstrap's own mobile margin rules took over.

           The query is deliberately TWO conditions, not just max-width: a phone
           turned sideways (e.g. 844x390) has a desktop-sized WIDTH but nowhere
           near desktop-sized HEIGHT, and a width-only query would hand it the
           centered/fixed-height desktop modal with no room for the content.
           The JS isNarrow check above uses this exact same pair, or the PDF
           would get the wrong viewer technology for the layout it's sitting in. */
        @media (max-width: 767.98px), (max-height: 500px) and (orientation: landscape) {
            /* Bootstrap pads <body> on the right to compensate for the scrollbar it hides
               while a modal is open -- harmless on desktop, but on a phone there was never
               a visible scrollbar to compensate for, so that padding alone can push the
               page a few pixels past the viewport width and create sideways scroll. */
            body.modal-open { padding-right: 0 !important; overflow-x: hidden; }
            #forcePolicyModal .policy-modal-dialog {
                max-width: 100% !important;
                width: 100%;
                height: 100%;
                margin: 0;
                overflow-x: hidden;
            }
            #forcePolicyModal .policy-modal-content {
                height: 100vh;
                height: 100dvh; /* actual visible height once the browser's own address-bar chrome is excluded */
                border-radius: 0;
                max-width: 100%;
                overflow-x: hidden;
            }
            /* The real bug: .modal-content clips its own rounded corners (overflow:
               hidden), so once it was pinned to a fixed height anything its flex
               children overflowed -- the checkbox, the acknowledge button -- was
               simply cut off with no scrollbar and no way to reach it. .modal-body
               must scroll on its own, and as a flex *child* it needs min-height: 0
               or it refuses to shrink below its content and overflow-y never
               kicks in (the standard flexbox trap). The iframe also had height:
               100% of a flex-grow parent -- an undefined height once that parent
               can scroll -- so it gets a real, fixed height instead. */
            #forcePolicyModal .modal-body {
                min-height: 0;
                overflow-y: auto;
                overflow-x: hidden;
                -webkit-overflow-scrolling: touch;
            }
            #forcePolicyModal .policy-modal-viewer {
                flex: 0 0 55vh;
                min-height: 220px;
            }
            #forcePolicyModal .policy-modal-header {
                padding: .6rem .85rem;
                display: flex;
                align-items: center;
                justify-content: space-between;
                flex-wrap: nowrap;
                gap: .5rem;
            }
            /* One line, however long the title: the header used to let the "Pending: N"
               badge wrap onto its own line and split its number off separately. */
            #forcePolicyModal .policy-modal-title {
                font-size: .95rem;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                flex: 1 1 auto;
                min-width: 0;
            }
            #forcePolicyModal .policy-modal-badge {
                white-space: nowrap;
                flex: 0 0 auto;
                font-size: .75rem;
            }
            #forcePolicyModal .policy-modal-desc { padding: .5rem .75rem !important; font-size: .8125rem; }
            /* Sticky, not just reachable-by-scrolling: the checkbox and the button stay on
               screen the whole time, through the PDF area scrolling past underneath them,
               the on-screen keyboard appearing, and the browser's own address bar
               showing/hiding (that's why the height above is 100dvh, not 100vh). */
            #forcePolicyModal .policy-modal-footer {
                padding: .65rem .75rem !important;
                position: sticky;
                bottom: 0;
                padding-bottom: calc(.65rem + env(safe-area-inset-bottom, 0px)) !important;
            }
            #forcePolicyModal .policy-modal-footer .form-check-label { font-size: .8125rem; }
            /* A 44px+ tap target on the whole checkbox row, not just the small checkbox
               square -- the label is already click-wired to it via `for`, this just
               gives that click area real size. */
            #forcePolicyModal .policy-modal-checkrow {
                display: flex;
                align-items: center;
                gap: .5rem;
                min-height: 44px;
                padding: .25rem 0;
            }
            #forcePolicyModal .policy-modal-checkrow .form-check-input {
                flex-shrink: 0;
                width: 1.25rem;
                height: 1.25rem;
                margin: 0;
            }
            #forcePolicyModal .policy-modal-checkrow .form-check-label { margin: 0; }
            #forcePolicyModal #forceSignBtn {
                min-height: 48px;
                padding-top: .6rem;
                padding-bottom: .6rem;
                font-size: .875rem;
            }
        }
    </style>
@endif
