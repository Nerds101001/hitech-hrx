@if(isset($globalPendingPolicies) && $globalPendingPolicies->isNotEmpty())
    @php
        $currentPolicy = $globalPendingPolicies->first();
        $isDelegate = isset($delegateHub) && $delegateHub['mode'] === 'acting';
    @endphp

    <!-- SweetAlert2 for notifications -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <div class="modal fade" id="forcePolicyModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-centered policy-modal-dialog">
            <div class="modal-content policy-modal-content">
                <div class="modal-header bg-danger policy-modal-header">
                    <div class="policy-modal-headrow">
                        <h5 class="modal-title text-white policy-modal-title">
                            <i class="bx bx-error-circle me-2"></i> Mandatory Company Policy
                        </h5>
                        <div class="text-white small policy-modal-badge">
                            Pending: {{ $globalPendingPolicies->count() }}
                        </div>
                    </div>
                    @if($isDelegate)
                        {{-- Reached only by admins impersonating someone else. Lives INSIDE the
                             modal's own DOM on purpose: a control placed in the navbar instead
                             has to win a z-index/focus-trap fight against this very modal, and
                             on a narrow screen the navbar that would hold it is collapsed behind
                             a hamburger the modal's backdrop blocks from ever being reached.
                             Posts to the existing, unmodified delegation-exit route only -- it
                             never touches the checkbox or the acknowledge flow below. --}}
                        <form method="POST" action="{{ route('user.delegation.exit') }}" class="policy-modal-delegate-exit mb-0">
                            @csrf
                            <button type="submit" class="btn btn-sm">
                                <i class="bx bx-log-out-circle me-1"></i> Exit delegated view
                            </button>
                        </form>
                    @endif
                </div>

                {{-- The scroll area: everything that can legitimately be long (instructions +
                     the policy itself) lives here and scrolls as one unit. The footer below is
                     a SEPARATE grid row on phone, not something floating inside this one, so the
                     reader can never paint behind or alongside it -- see the grid-template-rows
                     rule in the <style> block. --}}
                <div class="modal-body p-0 policy-modal-scrollarea" id="policyScrollArea">
                    <div class="p-3 bg-lighter border-bottom policy-modal-desc">
                        <span class="d-none d-md-inline"><strong>{{ $currentPolicy->title }}</strong>: Please scroll through and read the policy below. You must acknowledge it to proceed.</span>
                        <span class="d-md-none"><strong>{{ $currentPolicy->title }}</strong>: read below, then confirm.</span>
                        <a id="policyOpenNewTab" href="#" target="_blank" rel="noopener" class="d-none d-md-inline ms-2">
                            <i class="bx bx-link-external"></i> Open original PDF
                        </a>
                    </div>

                    <div class="policy-modal-viewer" id="policyViewerWrap">
                        {{-- Desktop/tablet: the browser's own PDF renderer in an iframe (unchanged,
                             it already works). --}}
                        <iframe id="policyIframe" src="" width="100%" height="100%" style="border: none;" class="d-none"></iframe>

                        {{-- Phone/short-landscape: a PDF.js reader (same library already used by
                             the training portal, loaded from the same CDN) instead of the raw
                             iframe. The raw PDF, shrunk to fit a ~300px-wide iframe, scaled an
                             11px paragraph down to about 5px -- unreadable, not just small -- and
                             an embedded Google-viewer stand-in for it sent the policy's private
                             signed URL to a third party AND imposed its own fixed internal
                             width regardless of the iframe's actual size, which was the real
                             source of the horizontal overflow this is also fixing. PDF.js runs
                             entirely in this browser tab: nothing about the document goes
                             anywhere except where it already went (R2, via our own server). --}}
                        <div class="policy-reader d-none" id="policyReader">
                            <div class="policy-reader-toolbar" id="policyReaderToolbar">
                                <div class="policy-reader-status" id="policyReaderStatus">Loading the document…</div>
                                <div class="policy-reader-zoom d-none" id="policyReaderZoom">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="policyZoomOut" aria-label="Zoom out">
                                        <i class="bx bx-minus"></i>
                                    </button>
                                    <span id="policyZoomPct">100%</span>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="policyZoomIn" aria-label="Zoom in">
                                        <i class="bx bx-plus"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="policy-reader-pages" id="policyReaderPages"></div>
                            <div class="policy-reader-error d-none" id="policyReaderError">
                                <i class="bx bx-error-circle"></i>
                                <div>Could not load the document for reading here.</div>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="policyReaderRetry">
                                    <i class="bx bx-refresh me-1"></i> Retry
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Always-available fallback, on every screen size: the real PDF, opened as
                         the browser's own file, independent of whichever reader is in use above. --}}
                    <div class="text-center p-2 border-top" id="policyMobileOpenWrap">
                        <a id="policyMobileOpen" href="#" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm">
                            <i class="bx bx-link-external me-1"></i> Open original PDF
                        </a>
                    </div>
                </div>

                {{-- Footer Controls: its own grid row on phone (see <style>), so it is never
                     behind the scroll area above regardless of how tall that area's content is. --}}
                <div class="p-3 bg-white border-top policy-modal-footer" id="policyFooter">
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

            // Must match the CSS's own breakpoint exactly (see the <style> block below):
            // a short-landscape phone (e.g. 844x390) is still "narrow" even though its
            // WIDTH alone reads as desktop-sized.
            const narrowQuery = window.matchMedia('(max-width: 767px), (max-height: 500px) and (orientation: landscape)');
            const isNarrow = narrowQuery.matches;

            fetch(`/hr-policies/embed-url/${policyId}`)
                .then(response => response.json())
                .then(data => {
                    if (!data.url) return;
                    document.getElementById('policyOpenNewTab').href = data.url;
                    document.getElementById('policyMobileOpen').href = data.url;

                    if (isNarrow) {
                        initPdfReader(policyId);
                    } else {
                        iframe.src = data.url;
                        iframe.classList.remove('d-none');
                    }
                });

            // ── Phone/short-landscape reader: PDF.js, rendered to full-width canvases ──
            // Fetches the document through our own server (/hr-policies/stream/<id>), not
            // straight from the storage bucket: that bucket sends no
            // Access-Control-Allow-Origin header, so a cross-origin fetch() (what PDF.js
            // needs, unlike a plain <iframe src> navigation) would be blocked by the
            // browser outright. Streaming it same-origin also means the raw bucket URL
            // is never sent to this tab for this path at all.
            let pdfDoc = null, pdfScale = null, pdfRendering = false;
            function initPdfReader(id) {
                const wrap = document.getElementById('policyReader');
                const pagesEl = document.getElementById('policyReaderPages');
                const statusEl = document.getElementById('policyReaderStatus');
                const errorEl = document.getElementById('policyReaderError');
                const zoomEl = document.getElementById('policyReaderZoom');
                const zoomPct = document.getElementById('policyZoomPct');
                wrap.classList.remove('d-none');
                errorEl.classList.add('d-none');
                zoomEl.classList.add('d-none');
                statusEl.classList.remove('d-none');
                statusEl.textContent = 'Loading the document…';
                pagesEl.innerHTML = '';

                if (typeof pdfjsLib === 'undefined') {
                    const s = document.createElement('script');
                    s.src = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js';
                    s.onload = () => loadPdf(id);
                    s.onerror = () => showReaderError();
                    document.head.appendChild(s);
                } else {
                    loadPdf(id);
                }
            }

            function loadPdf(id) {
                try {
                    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
                    pdfjsLib.getDocument({
                        url: `/hr-policies/stream/${id}`,
                        withCredentials: true,
                        cMapUrl: 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/cmaps/',
                        cMapPacked: true,
                    }).promise.then(function (doc) {
                        pdfDoc = doc;
                        pdfScale = null; // computed on first render, from the actual container width
                        renderAllPages();
                    }).catch(showReaderError);
                } catch (e) {
                    showReaderError();
                }
            }

            function showReaderError() {
                document.getElementById('policyReaderStatus').classList.add('d-none');
                document.getElementById('policyReaderError').classList.remove('d-none');
                document.getElementById('policyReaderZoom').classList.add('d-none');
                // A failed preview is never silently treated as "read": the checkbox and the
                // button are driven only by the checkbox's own change event, nowhere near this
                // code path, so they simply stay exactly as they already were (unticked,
                // disabled) -- nothing here can enable them.
            }

            async function renderAllPages() {
                if (!pdfDoc || pdfRendering) return;
                pdfRendering = true;
                const pagesEl = document.getElementById('policyReaderPages');
                const statusEl = document.getElementById('policyReaderStatus');
                const zoomEl = document.getElementById('policyReaderZoom');
                const zoomPct = document.getElementById('policyZoomPct');
                const scrollArea = document.getElementById('policyScrollArea');

                if (pdfScale === null) {
                    const first = await pdfDoc.getPage(1);
                    const natural = first.getViewport({ scale: 1 });
                    const containerW = Math.max((pagesEl.clientWidth || scrollArea.clientWidth || window.innerWidth) - 16, 240);
                    pdfScale = containerW / natural.width;
                }

                pagesEl.innerHTML = '';
                const dpr = Math.min(window.devicePixelRatio || 1, 2.5); // sharp text, capped so a long policy still loads fast
                for (let n = 1; n <= pdfDoc.numPages; n++) {
                    const page = await pdfDoc.getPage(n);
                    const viewport = page.getViewport({ scale: pdfScale });
                    const canvas = document.createElement('canvas');
                    canvas.className = 'policy-reader-page';
                    canvas.width = Math.round(viewport.width * dpr);
                    canvas.height = Math.round(viewport.height * dpr);
                    canvas.style.width = Math.round(viewport.width) + 'px';
                    canvas.style.height = Math.round(viewport.height) + 'px';
                    pagesEl.appendChild(canvas);
                    const ctx = canvas.getContext('2d');
                    ctx.scale(dpr, dpr);
                    await page.render({ canvasContext: ctx, viewport: viewport }).promise;
                }

                statusEl.classList.add('d-none');
                zoomEl.classList.remove('d-none');
                zoomPct.textContent = Math.round(pdfScale / baseScaleFactor() * 100) + '%';
                pdfRendering = false;
            }

            // The % shown is relative to the scale the page first loaded at (fit-to-width =
            // "100%"), not to the PDF's own native point size -- a more useful reference for
            // "did my tap do anything" than an absolute, meaningless-to-a-reader number.
            let pdfBaseScale = null;
            function baseScaleFactor() {
                if (pdfBaseScale === null) pdfBaseScale = pdfScale;
                return pdfBaseScale || 1;
            }

            document.getElementById('policyZoomIn').addEventListener('click', function () {
                if (!pdfDoc || pdfRendering) return;
                pdfScale = Math.min(pdfScale * 1.25, baseScaleFactor() * 3);
                renderAllPages();
            });
            document.getElementById('policyZoomOut').addEventListener('click', function () {
                if (!pdfDoc || pdfRendering) return;
                pdfScale = Math.max(pdfScale * 0.8, baseScaleFactor() * 0.5);
                renderAllPages();
            });
            document.getElementById('policyReaderRetry').addEventListener('click', function () {
                initPdfReader(policyId);
            });

            // Re-render at a sharp size after a rotation: the already-drawn canvases have
            // max-width:100% so a rotation can never bring back the sideways-overflow bug
            // even without this (the browser just scales them down), but without a fresh
            // render at the new, wider container width they stay blurry rather than crisp.
            let resizeTimer = null;
            window.addEventListener('resize', function () {
                if (!pdfDoc || !narrowQuery.matches) return;
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(function () {
                    pdfScale = null; // recompute fit-to-width for the new container size
                    pdfBaseScale = null;
                    renderAllPages();
                }, 300);
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
            #forcePolicyModal .policy-modal-scrollarea {
                flex: 1 1 auto;
                overflow-y: auto;
                display: flex;
                flex-direction: column;
                min-height: 0;
            }
            #forcePolicyModal .policy-modal-viewer { flex: 1 1 auto; }
        }
        /* A visible gap between the title and the pending count on every screen size,
           not just the phone layout below. */
        #forcePolicyModal .policy-modal-headrow {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 1rem;
            width: 100%;
        }
        #forcePolicyModal .policy-modal-delegate-exit .btn {
            background: #fff;
            color: #b45309;
            border: 1px solid #fff;
            font-weight: 700;
            border-radius: 999px;
            white-space: nowrap;
        }
        #forcePolicyModal .policy-modal-delegate-exit .btn:hover { background: #fff7ed; }

        /* Phones and any short-landscape window: the modal must fit the viewport
           edge-to-edge, with the policy getting almost all of the height -- the
           header/description/footer chrome below used fixed desktop padding that
           left barely any room for the document itself, and .modal-xl's % based
           max-width did nothing once Bootstrap's own mobile margin rules took over.

           The query is deliberately TWO conditions, not just max-width: a phone
           turned sideways (e.g. 844x390) has a desktop-sized WIDTH but nowhere near
           desktop-sized HEIGHT, and a width-only query would hand it the centered,
           fixed-85vh desktop modal with no room for the content. The JS isNarrow
           check above uses this exact same pair, or the reader would be built for
           the wrong layout entirely. */
        @media (max-width: 767.98px), (max-height: 500px) and (orientation: landscape) {
            /* Bootstrap pads <body> on the right to compensate for the scrollbar it
               hides while a modal is open -- harmless on desktop, but on a phone
               there was never a visible scrollbar to compensate for, so that
               padding alone could push the page a few pixels past the viewport
               width. This is a genuine extra cause alongside the real one below
               (the previous viewer technology's own fixed internal width); kept as
               a defensive measure, not as the fix for that real cause. */
            body.modal-open { padding-right: 0 !important; overflow-x: hidden; }

            #forcePolicyModal .policy-modal-dialog {
                max-width: 100% !important;
                width: 100%;
                height: 100%;
                margin: 0;
                box-sizing: border-box;
            }
            /* The real structural fix: a true 3-row grid, not a flex column with a
               sticky footer. Grid rows cannot overlap by construction -- the footer
               (auto) and header (auto) each take exactly their content height, and
               the middle row (minmax(0,1fr)) gets everything left over and is the
               ONLY thing that scrolls. The old approach guessed the reader's height
               as a fixed 55vh, which was wrong in landscape (55% of a 390px-tall
               screen left too little room for the header+footer that still had to
               fit around it) and let the reader paint behind the footer. */
            #forcePolicyModal .policy-modal-content {
                display: grid;
                grid-template-rows: auto minmax(0, 1fr) auto;
                height: 100vh;
                height: 100dvh; /* actual visible height once the browser's own address-bar chrome is excluded */
                border-radius: 0;
                max-width: 100%;
                box-sizing: border-box;
                overflow-x: hidden;
            }
            #forcePolicyModal .policy-modal-scrollarea {
                min-width: 0;
                min-height: 0;
                overflow-y: auto;
                overflow-x: hidden;
                -webkit-overflow-scrolling: touch;
            }
            #forcePolicyModal .policy-modal-viewer {
                min-width: 0;
                min-height: 260px; /* a floor only, while the reader is still loading -- real height after that is however tall the rendered pages are */
            }
            #forcePolicyModal .policy-modal-header {
                padding: .6rem .85rem;
                display: flex;
                flex-direction: column;
                align-items: stretch;
                gap: .4rem;
                min-width: 0;
            }
            /* The title used to be forced onto one line with an ellipsis -- which hid part
               of the heading, not just made it compact. It now wraps naturally instead, so
               every word stays on screen; the pending badge gets its own line underneath so
               neither it nor the title has to fight the other for horizontal room. */
            #forcePolicyModal .policy-modal-headrow {
                flex-direction: column;
                align-items: flex-start;
                gap: .15rem;
            }
            #forcePolicyModal .policy-modal-title {
                font-size: .95rem;
                white-space: normal;
                overflow-wrap: break-word;
                min-width: 0;
            }
            #forcePolicyModal .policy-modal-badge {
                white-space: nowrap;
                font-size: .75rem;
            }
            #forcePolicyModal .policy-modal-delegate-exit {
                align-self: flex-start;
            }
            #forcePolicyModal .policy-modal-delegate-exit .btn {
                min-height: 36px;
                padding: .3rem .85rem;
                font-size: .75rem;
            }
            #forcePolicyModal .policy-modal-desc { padding: .5rem .75rem !important; font-size: .8125rem; min-width: 0; }
            /* A real row, not a float-over: part of the grid above, so it can never be
               behind the scroll area no matter how tall that area's content is, with
               safe-area padding for the home-indicator strip on notched phones. */
            #forcePolicyModal .policy-modal-footer {
                padding: .65rem .75rem !important;
                padding-bottom: calc(.65rem + env(safe-area-inset-bottom, 0px)) !important;
                min-width: 0;
            }
            #forcePolicyModal .policy-modal-footer .form-check-label { font-size: .8125rem; }
            /* A real 48x48 CSS px tap target on the whole checkbox row (the audit measured
               the previous 44px as still short), not just the small checkbox square -- the
               label is already click-wired to it via `for`, this gives that click area
               real, comfortable size and keeps it clear of the button below it. */
            #forcePolicyModal .policy-modal-checkrow {
                display: flex;
                align-items: center;
                gap: .6rem;
                min-height: 48px;
                padding: .4rem 0;
            }
            #forcePolicyModal .policy-modal-checkrow .form-check-input {
                flex-shrink: 0;
                width: 1.25rem;
                height: 1.25rem;
                margin: 0;
            }
            #forcePolicyModal .policy-modal-checkrow label {
                flex: 1 1 auto;
                margin: 0;
                min-width: 0;
            }
            #forcePolicyModal #forceSignBtn {
                min-height: 48px;
                padding-top: .6rem;
                padding-bottom: .6rem;
                font-size: .875rem;
            }
        }

        /* The PDF.js reader -- phone/short-landscape only. Plain vertical scroll of
           full-width page canvases, deliberately simple (no page-flip/book effects):
           the goal here is legibility, not presentation. */
        .policy-reader { display: flex; flex-direction: column; }
        .policy-reader-toolbar {
            position: sticky;
            top: 0;
            z-index: 1;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .5rem;
            padding: .4rem .6rem;
            background: #1e293b;
            color: #e2e8f0;
            font-size: .8125rem;
        }
        .policy-reader-zoom { display: flex; align-items: center; gap: .5rem; }
        .policy-reader-zoom button {
            min-width: 44px;
            min-height: 36px;
        }
        .policy-reader-zoom span { min-width: 3.5ch; text-align: center; }
        .policy-reader-pages {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            padding: 8px;
            background: #1e293b;
        }
        .policy-reader-page {
            max-width: 100%;
            height: auto;
            box-shadow: 0 2px 10px rgba(0,0,0,.35);
            background: #fff;
        }
        .policy-reader-error {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: .5rem;
            padding: 2rem 1rem;
            color: #fff;
            text-align: center;
        }
        .policy-reader-error i { font-size: 2rem; }
    </style>
@endif
