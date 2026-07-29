{{--
    Organisation-wide welcome card.

    Shown to EVERY employee, so it carries only what is appropriate for the
    whole company to see: photo, name, designation and department. No contact
    number, email, employee code, location or salary — those stay in the
    HR/accounts mail.

    Inline styles only: this HTML is stored in announcements.content and is
    rendered inside the notice feed, which has no access to page stylesheets.
--}}
<div style="display:flex;align-items:center;gap:16px;padding:4px 0;font-family:'Public Sans',Arial,sans-serif;">
    @if($photo)
        <img src="{{ $photo }}" alt=""
             style="width:64px;height:64px;border-radius:50%;object-fit:cover;
                    border:3px solid #0d9488;flex:0 0 64px;background:#fff;">
    @else
        <span style="width:64px;height:64px;border-radius:50%;flex:0 0 64px;
                     background:linear-gradient(135deg,#005a5a,#0d9488);color:#fff;
                     display:inline-block;text-align:center;line-height:64px;
                     font-size:22px;font-weight:800;">
            {{ \Illuminate\Support\Str::of($name)->explode(' ')->take(2)->map(fn($w) => mb_substr($w, 0, 1))->implode('') }}
        </span>
    @endif

    <span>
        <span style="display:block;font-size:16px;font-weight:800;color:#0f172a;">{{ $name }}</span>
        @if($line)
            <span style="display:block;font-size:13px;color:#64748b;font-weight:600;margin-top:2px;">{{ $line }}</span>
        @endif
        <span style="display:inline-block;margin-top:8px;padding:3px 12px;border-radius:999px;
                     background:rgba(13,148,136,.12);color:#0f766e;font-size:11px;
                     font-weight:800;letter-spacing:.5px;text-transform:uppercase;">
            New Team Member
        </span>
    </span>
</div>

<p style="margin:14px 0 0;font-size:14px;line-height:1.7;color:#475569;font-family:'Public Sans',Arial,sans-serif;">
    Please join us in welcoming <strong style="color:#0f172a;">{{ $name }}</strong> to Hi Tech Group.
    We're glad to have you on board — wishing you a great start and a successful journey with the team!
</p>
