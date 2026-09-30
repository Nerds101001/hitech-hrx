{{-- A YouTube player people can watch but not leave or lift the link from.

     YouTube draws its own chrome inside the iframe - the title and channel
     strip at the top (a link to youtube.com), a "copy link" chip bottom-left,
     "Watch on YouTube" bottom-right, and a right-click menu with "Copy video
     URL" - and no player parameter turns those off. So:

       - the iframe is made taller than the frame and centred, so the video
         sits in the middle and YouTube's top and bottom strips fall outside
         the visible area (overflow: hidden);
       - a transparent shield covers what is left, so no click or right-click
         reaches YouTube at all;
       - play, seek, volume, speed and fullscreen are our own controls, through
         the IFrame Player API.

     Usage: HrxSecureYT.mount(element, videoId, { onPlayer(p), onStateChange(e),
     fsTarget: element }). The element becomes the player frame. --}}
<style>
    .sv-frame { position: relative; width: 100%; aspect-ratio: 16 / 9; background: #000; overflow: hidden; user-select: none; -webkit-user-select: none; outline: none; }
    .sv-host { position: absolute; left: 0; width: 100%; top: calc(max(84px, 12%) * -1); height: calc(100% + 2 * max(84px, 12%)); pointer-events: none; }
    .sv-host iframe { width: 100%; height: 100%; border: 0; display: block; }
    .sv-shield { position: absolute; inset: 0; z-index: 4; cursor: pointer; background: transparent; }
    .sv-shield.sv-hole { clip-path: polygon(evenodd, 0 0, 100% 0, 100% 100%, 0 100%, 0 0, 38% 36%, 62% 36%, 62% 64%, 38% 64%, 38% 36%); }
    .sv-cover { position: absolute; inset: 0; z-index: 5; display: flex; flex-direction: column; gap: 10px; align-items: center; justify-content: center; background: rgba(6, 14, 18, .55); transition: opacity .2s; }
    .sv-frame.is-playing .sv-cover, .sv-frame.is-tapthrough .sv-cover { opacity: 0; pointer-events: none; }
    .sv-frame.is-ended .sv-cover { background: #071116; }
    .sv-big { width: 74px; height: 74px; border-radius: 50%; border: 0; background: #0b7c86; color: #fff; font-size: 2.6rem; display: flex; align-items: center; justify-content: center; box-shadow: 0 10px 30px rgba(0,0,0,.45); cursor: pointer; transition: transform .15s; }
    .sv-big:hover { transform: scale(1.06); }
    .sv-cover-note { color: rgba(255,255,255,.85); font-size: .8rem; }
    .sv-bar { position: absolute; left: 0; right: 0; bottom: 0; z-index: 6; display: flex; align-items: center; gap: 8px; padding: 22px 12px 8px; background: linear-gradient(transparent, rgba(0,0,0,.78)); color: #fff; opacity: 0; transition: opacity .2s; }
    .sv-frame:hover .sv-bar, .sv-frame:focus-within .sv-bar, .sv-frame:not(.is-playing) .sv-bar, .sv-frame.show-bar .sv-bar { opacity: 1; }
    .sv-btn { background: transparent; border: 0; color: #fff; font-size: 1.35rem; min-width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; cursor: pointer; flex-shrink: 0; padding: 0 6px; }
    .sv-btn:hover, .sv-btn:focus-visible { background: rgba(255,255,255,.16); outline: none; }
    .sv-speed { font-size: .78rem; font-weight: 700; }
    .sv-seek { flex: 1; min-width: 60px; accent-color: #2dd4bf; cursor: pointer; }
    .sv-vol { width: 76px; accent-color: #2dd4bf; cursor: pointer; }
    .sv-time { font-size: .76rem; font-variant-numeric: tabular-nums; white-space: nowrap; opacity: .92; }
    .sv-fs-target:fullscreen { width: 100vw; height: 100vh; max-width: none; aspect-ratio: auto; border-radius: 0; background: #000; }
    .sv-fs-target:-webkit-full-screen { width: 100vw; height: 100vh; max-width: none; aspect-ratio: auto; border-radius: 0; background: #000; }
    .sv-fs-target.sv-pseudo-fs { position: fixed !important; inset: 0; z-index: 10050; width: 100vw; height: 100vh; max-width: none; aspect-ratio: auto; border-radius: 0; background: #000; }
    .sv-fs-target:fullscreen .sv-frame { height: 100%; aspect-ratio: auto; }
    .sv-fs-target:-webkit-full-screen .sv-frame { height: 100%; aspect-ratio: auto; }
    .sv-fs-target.sv-pseudo-fs .sv-frame { height: 100%; aspect-ratio: auto; }
    @media (max-width: 575.98px) { .sv-vol, .sv-speed { display: none; } }
</style>
<script>
window.HrxSecureYT = window.HrxSecureYT || (function () {
    var queue = [], apiReady = false, loading = false;

    function fmt(s) {
        s = Math.max(0, Math.floor(s || 0));
        var h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60), x = s % 60;
        return (h ? h + ':' + String(m).padStart(2, '0') : m) + ':' + String(x).padStart(2, '0');
    }
    function loadApi() {
        if (window.YT && window.YT.Player) { apiReady = true; flush(); return; }
        if (loading) return;
        loading = true;
        var prev = window.onYouTubeIframeAPIReady;
        window.onYouTubeIframeAPIReady = function () {
            apiReady = true;
            if (typeof prev === 'function') { try { prev(); } catch (e) {} }
            flush();
        };
        var s = document.createElement('script');
        s.src = 'https://www.youtube.com/iframe_api';
        document.head.appendChild(s);
    }
    function flush() { while (apiReady && queue.length) build(queue.shift()); }

    function build(job) {
        var frame = job.el, opts = job.opts || {}, fsTarget = opts.fsTarget || frame;
        frame.classList.add('sv-frame');
        fsTarget.classList.add('sv-fs-target');
        frame.setAttribute('tabindex', '0');
        frame.innerHTML =
            '<div class="sv-host"><div></div></div>' +
            '<div class="sv-shield"></div>' +
            '<div class="sv-cover"><button type="button" class="sv-big" aria-label="Play"><i class="bx bx-play"></i></button><span class="sv-cover-note"></span></div>' +
            '<div class="sv-bar">' +
                '<button type="button" class="sv-btn sv-play" aria-label="Play"><i class="bx bx-play"></i></button>' +
                '<span class="sv-time sv-cur">0:00</span>' +
                '<input type="range" class="sv-seek" min="0" max="1000" value="0" step="1" aria-label="Seek">' +
                '<span class="sv-time sv-dur">0:00</span>' +
                '<button type="button" class="sv-btn sv-mute" aria-label="Mute"><i class="bx bx-volume-full"></i></button>' +
                '<input type="range" class="sv-vol" min="0" max="100" value="100" aria-label="Volume">' +
                '<button type="button" class="sv-btn sv-speed" aria-label="Playback speed">1x</button>' +
                '<button type="button" class="sv-btn sv-fs" aria-label="Fullscreen"><i class="bx bx-fullscreen"></i></button>' +
            '</div>';

        var q = function (c) { return frame.querySelector(c); };
        var shield = q('.sv-shield'), cover = q('.sv-cover'), big = q('.sv-big'), note = q('.sv-cover-note');
        var playBtn = q('.sv-play'), cur = q('.sv-cur'), dur = q('.sv-dur'), seek = q('.sv-seek');
        var muteBtn = q('.sv-mute'), vol = q('.sv-vol'), speedBtn = q('.sv-speed'), fsBtn = q('.sv-fs');
        var player = null, tick = null, dragging = false, started = false, tapTimer = null;
        var speeds = [1, 1.25, 1.5, 2, 0.75], speedIdx = 0;

        // No right-click menu anywhere on the player ("Copy video URL" lives there).
        frame.addEventListener('contextmenu', function (e) { e.preventDefault(); });

        function state() { return player && player.getPlayerState ? player.getPlayerState() : -1; }
        function isPlaying() { var s = state(); return s === 1 || s === 3; }
        function toggle() {
            if (!player) return;
            if (state() === 0) { player.seekTo(0, true); player.playVideo(); return; }
            if (isPlaying()) { player.pauseVideo(); return; }
            player.playVideo();
            // Some phones only start a video from a tap inside YouTube's own
            // frame. If ours did not start it, open a gap over YouTube's play
            // button (the middle of the frame) for that one tap - its top and
            // bottom strips stay cropped away, and the shield closes again as
            // soon as the video is playing.
            clearTimeout(tapTimer);
            tapTimer = setTimeout(function () {
                if (!started && !isPlaying()) {
                    frame.classList.add('is-tapthrough');
                    shield.classList.add('sv-hole');
                }
            }, 1500);
        }
        function paint() {
            if (!player || !player.getDuration) return;
            var d = player.getDuration() || 0, c = player.getCurrentTime() || 0;
            dur.textContent = fmt(d);
            cur.textContent = fmt(c);
            if (!dragging && d > 0) seek.value = Math.round(c / d * 1000);
        }
        function setIcons() {
            var playing = isPlaying();
            playBtn.innerHTML = '<i class="bx ' + (playing ? 'bx-pause' : 'bx-play') + '"></i>';
            playBtn.setAttribute('aria-label', playing ? 'Pause' : 'Play');
            var muted = player && player.isMuted && player.isMuted();
            muteBtn.innerHTML = '<i class="bx ' + (muted || +vol.value === 0 ? 'bx-volume-mute' : 'bx-volume-full') + '"></i>';
        }

        shield.addEventListener('click', toggle);
        shield.addEventListener('dblclick', function () { toggleFs(); });
        big.addEventListener('click', toggle);
        playBtn.addEventListener('click', toggle);
        seek.addEventListener('input', function () {
            dragging = true;
            var d = player ? player.getDuration() : 0;
            cur.textContent = fmt(d * seek.value / 1000);
        });
        seek.addEventListener('change', function () {
            var d = player ? player.getDuration() : 0;
            if (player && d) player.seekTo(d * seek.value / 1000, true);
            dragging = false;
        });
        muteBtn.addEventListener('click', function () {
            if (!player) return;
            if (player.isMuted()) { player.unMute(); if (+vol.value === 0) { vol.value = 50; player.setVolume(50); } }
            else player.mute();
            setTimeout(setIcons, 60);
        });
        vol.addEventListener('input', function () {
            if (!player) return;
            player.setVolume(+vol.value);
            if (+vol.value > 0 && player.isMuted()) player.unMute();
            setTimeout(setIcons, 60);
        });
        speedBtn.addEventListener('click', function () {
            if (!player) return;
            var allowed = (player.getAvailablePlaybackRates && player.getAvailablePlaybackRates()) || speeds;
            for (var i = 1; i <= speeds.length; i++) {
                var next = speeds[(speedIdx + i) % speeds.length];
                if (allowed.indexOf(next) !== -1) { speedIdx = (speedIdx + i) % speeds.length; break; }
            }
            player.setPlaybackRate(speeds[speedIdx]);
            speedBtn.textContent = speeds[speedIdx] + 'x';
        });

        function isFs() { return document.fullscreenElement === fsTarget || document.webkitFullscreenElement === fsTarget || fsTarget.classList.contains('sv-pseudo-fs'); }
        function toggleFs() {
            if (isFs()) {
                if (fsTarget.classList.contains('sv-pseudo-fs')) fsTarget.classList.remove('sv-pseudo-fs');
                else (document.exitFullscreen || document.webkitExitFullscreen).call(document);
            } else if (fsTarget.requestFullscreen || fsTarget.webkitRequestFullscreen) {
                (fsTarget.requestFullscreen || fsTarget.webkitRequestFullscreen).call(fsTarget);
            } else {
                fsTarget.classList.add('sv-pseudo-fs');   // iPhone: no element fullscreen
            }
            setTimeout(fsIcon, 120);
        }
        function fsIcon() { fsBtn.innerHTML = '<i class="bx ' + (isFs() ? 'bx-exit-fullscreen' : 'bx-fullscreen') + '"></i>'; }
        fsBtn.addEventListener('click', toggleFs);
        document.addEventListener('fullscreenchange', fsIcon);
        document.addEventListener('webkitfullscreenchange', fsIcon);

        frame.addEventListener('keydown', function (e) {
            if (!player || e.target.tagName === 'INPUT') return;
            if (e.key === ' ' || e.key === 'k') { e.preventDefault(); toggle(); }
            else if (e.key === 'ArrowRight') { e.preventDefault(); player.seekTo(player.getCurrentTime() + 5, true); }
            else if (e.key === 'ArrowLeft') { e.preventDefault(); player.seekTo(Math.max(0, player.getCurrentTime() - 5), true); }
            else if (e.key === 'f') { toggleFs(); }
            else if (e.key === 'm') { muteBtn.click(); }
        });

        player = new YT.Player(q('.sv-host > div'), {
            width: '100%', height: '100%', videoId: job.videoId,
            host: 'https://www.youtube-nocookie.com',
            playerVars: { controls: 0, disablekb: 1, fs: 0, rel: 0, iv_load_policy: 3, modestbranding: 1, playsinline: 1, cc_load_policy: 0, origin: window.location.origin },
            events: {
                onReady: function () { paint(); setIcons(); if (opts.onPlayer) opts.onPlayer(player); },
                onStateChange: function (e) {
                    var s = e.data;
                    frame.classList.toggle('is-playing', s === 1 || s === 3);
                    frame.classList.toggle('is-ended', s === 0);
                    if (s === 1) {
                        started = true;
                        clearTimeout(tapTimer);
                        frame.classList.remove('is-tapthrough');
                        shield.classList.remove('sv-hole');
                    }
                    big.innerHTML = '<i class="bx ' + (s === 0 ? 'bx-revision' : 'bx-play') + '"></i>';
                    note.textContent = s === 0 ? 'Watch again' : '';
                    clearInterval(tick);
                    if (s === 1 || s === 3) tick = setInterval(paint, 250);
                    paint(); setIcons();
                    if (opts.onStateChange) opts.onStateChange(e);
                }
            }
        });
    }

    return {
        mount: function (el, videoId, opts) {
            if (!el || !videoId) return;
            queue.push({ el: el, videoId: videoId, opts: opts });
            if (apiReady) flush(); else loadApi();
        }
    };
})();
</script>
