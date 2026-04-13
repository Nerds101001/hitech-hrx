<!-- HR Assistant - Final Optimized Version -->
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet" />

<style>
    /* ISOLATED CSS with Zero-Boundary Design */
    :root {
        --hs-primary: #0D5C63;
        --hs-bg: #F9FAFB;
        --hs-border: #E2E8F0;
        --hs-text: #1E293B;
        --hs-text-muted: #64748B;
    }

    #h-sentinel-trigger {
        position: fixed; bottom: 30px; right: 30px; width: 64px; height: 64px;
        background: var(--hs-primary); border-radius: 1.5rem;
        display: flex; align-items: center; justify-content: center;
        color: white; box-shadow: 0 10px 30px rgba(13, 92, 99, 0.4);
        cursor: pointer; z-index: 99999;
    }

    #h-sentinel-widget {
        position: fixed; bottom: 30px; right: 30px; width: 400px; height: 620px;
        background: white; border-radius: 1.75rem; box-shadow: 0 20px 60px rgba(0,0,0,0.25);
        display: none; flex-direction: column; z-index: 100000;
        overflow: hidden; border: none; /* Removed outer boundary */
        font-family: 'Plus Jakarta Sans', sans-serif;
    }

    .hs-header {
        background: var(--hs-primary); padding: 20px 24px; color: white;
        display: flex; justify-content: space-between; align-items: center;
    }

    .hs-header-info { display: flex; align-items: center; gap: 14px; }
    .hs-header-icon { width: 42px; height: 42px; background: rgba(255,255,255,0.2); border-radius: 1.25rem; display: flex; align-items: center; justify-content: center; }
    .hs-header-title h3 { font-size: 15px; margin: 0; font-weight: 800; color: white !important; letter-spacing: -0.01em; }
    .hs-header-title p { font-size: 10px; margin: 4px 0 0 0; color: #5eead4; font-weight: 800; text-transform: uppercase; letter-spacing: 1.2px; }

    .hs-body {
        flex: 1; padding: 20px; overflow-y: auto; background: white;
        display: flex; flex-direction: column; gap: 24px;
    }

    .hs-msg-row { display: flex; flex-direction: column; width: 100%; }
    .hs-msg-row.user { align-items: flex-end; }
    .hs-msg-row.bot { align-items: flex-start; }

    .hs-bubble { padding: 14px 18px; font-size: 14px; line-height: 1.6; max-width: 88%; position: relative; }
    .user .hs-bubble { background: var(--hs-primary); color: white !important; border-radius: 1.5rem 1.5rem 0 1.5rem; box-shadow: 0 4px 15px rgba(13, 92, 99, 0.2); }
    .bot .hs-bubble { background: white; color: var(--hs-text); border: 1px solid var(--hs-border); border-radius: 1.5rem 1.5rem 1.5rem 0; box-shadow: 0 4px 10px rgba(0,0,0,0.02); }

    .hs-bot-container { display: flex; gap: 12px; width: 100%; }
    .hs-bot-avatar { width: 32px; height: 32px; background: var(--hs-primary); border-radius: 10px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; color: white; align-self: flex-end; margin-bottom: 22px; }

    .hs-meta { font-size: 10px; color: var(--hs-text-muted); font-weight: 700; text-transform: uppercase; margin-top: 8px; letter-spacing: 0.6px; }

    .hs-chips { padding: 12px 20px; display: flex; flex-wrap: wrap; gap: 10px; background: white; border-top: 1px solid #f1f5f9; }
    .hs-chip { padding: 8px 18px; background: white; border: 1px solid var(--hs-border); border-radius: 24px; font-size: 11px; font-weight: 800; color: var(--hs-primary); cursor: pointer; transition: 0.2s; }
    .hs-chip:hover { background: var(--hs-primary); color: white; border-color: var(--hs-primary); }

    .hs-footer { padding: 18px 20px; background: #fff; border-top: 1px solid #f1f5f9; }
    .hs-input-group { 
        background: #fff; 
        border: 1px solid #e2e8f0; 
        border-radius: 50px; 
        padding: 4px; 
        display: flex; 
        align-items: center;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        transition: all 0.2s;
    }
    .hs-input-group:focus-within { border-color: var(--hs-primary); box-shadow: 0 0 0 3px rgba(13, 92, 99, 0.1); }
    .hs-input { border: none !important; background: transparent !important; flex: 1; font-size: 14px; outline: none !important; padding: 0 16px; color: var(--hs-text); height: 44px; box-shadow: none !important; }
    .hs-send-btn, .hs-attach-btn { 
        width: 44px; height: 44px; 
        background: var(--hs-primary); 
        color: white; 
        border: none; 
        border-radius: 50%; 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        cursor: pointer; 
        transition: 0.2s;
        flex-shrink: 0;
        margin: 0;
    }
    .hs-attach-btn { background: #f8fafc; color: #64748B; margin-right: 4px; }
    .hs-attach-btn:hover { background: #f1f5f9; color: var(--hs-primary); }
    .hs-send-btn:hover { background: #0b4e54; box-shadow: 0 4px 12px rgba(13, 92, 99, 0.2); }

    /* Markdown & Icons */
    .hs-bubble p { margin-bottom: 10px; }
    .hs-bubble p:last-child { margin-bottom: 0; }
    .hs-bubble ul { list-style: none; padding: 0; margin: 10px 0; font-weight: 600; color: var(--hs-primary); }
    .hs-bubble li { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
    
    /* Document Link Styling */
    .hs-bubble a {
        display: inline-block;
        padding: 8px 18px;
        background: var(--hs-primary);
        color: white !important;
        text-decoration: none !important;
        border-radius: 30px;
        font-weight: 800;
        font-size: 11px;
        text-transform: uppercase;
        margin: 8px 0;
        box-shadow: 0 4px 12px rgba(13, 92, 99, 0.2);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border: 1px solid rgba(255,255,255,0.1);
    }
    .hs-bubble a:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(13, 92, 99, 0.3);
        background: #0b4e54;
    }
    .hs-bubble a::before {
        content: '\e2c4'; /* Symbol for download */
        font-family: 'Material Symbols Outlined';
        margin-right: 8px;
        vertical-align: middle;
        font-size: 16px;
    }
</style>

<div id="h-sentinel-trigger" onclick="toggleHsWidget()">
    <span class="material-symbols-outlined" style="font-size: 32px; font-variation-settings: 'FILL' 1;">smart_toy</span>
</div>

<div id="h-sentinel-widget">
    <div class="hs-header">
        <div class="hs-header-info">
            <div class="hs-header-icon">
                <span class="material-symbols-outlined" style="font-size: 24px; font-variation-settings: 'FILL' 1;">smart_toy</span>
            </div>
            <div class="hs-header-title">
                <h3>HR Assistant</h3>
                <p>Strategic AI Support</p>
            </div>
        </div>
        <div style="display: flex; gap: 15px;">
            <span class="material-symbols-outlined" style="cursor: pointer; font-size: 20px;" onclick="minimizeHs()">remove</span>
            <span class="material-symbols-outlined" style="cursor: pointer; font-size: 20px;" onclick="toggleHsWidget()">close</span>
        </div>
    </div>

    <div class="hs-body" id="hs-chat-body">
        <!-- Messages -->
    </div>

    <div class="hs-chips">
        <div class="hs-chip" onclick="hsQuickQuery('SDS')">SDS</div>
        <div class="hs-chip" onclick="hsQuickQuery('TDS')">TDS</div>
        <div class="hs-chip" onclick="hsQuickQuery('Videos')">Videos</div>
    </div>

    <div class="hs-footer">
        <form onsubmit="hsHandleSubmit(event)" class="hs-input-group">
            <button type="button" class="hs-attach-btn" onclick="document.getElementById('hs-file-input').click()">
                <span class="material-symbols-outlined" style="font-size: 22px;">attach_file</span>
            </button>
            <input type="file" id="hs-file-input" style="display:none">
            <input type="text" id="hs-user-input" class="hs-input" placeholder="Type your HR query..." autocomplete="off">
            <button type="submit" class="hs-send-btn">
                <span class="material-symbols-outlined" style="font-size: 20px; font-variation-settings: 'FILL' 1;">send</span>
            </button>
        </form>
    </div>
</div>

<script>
    const hsWidget = document.getElementById('h-sentinel-widget');
    const hsTrigger = document.getElementById('h-sentinel-trigger');
    const hsBody = document.getElementById('hs-chat-body');
    const hsInput = document.getElementById('hs-user-input');
    const HS_STORAGE = 'hrx_final_premium_v1';

    function toggleHsWidget() {
        if (hsWidget.style.display === 'flex') {
            hsWidget.style.display = 'none';
            hsTrigger.style.display = 'flex';
        } else {
            hsWidget.style.display = 'flex';
            hsTrigger.style.display = 'none';
            loadHsHistory();
        }
    }

    function minimizeHs() {
        hsWidget.style.display = 'none';
        hsTrigger.style.display = 'flex';
    }

    function appendHsMsg(text, role, save = true) {
        const time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        const row = document.createElement('div');
        row.className = `hs-msg-row ${role}`;

        // Humanize the leave balance display with Database Icons
        let displayContent = text;
        if (role === 'bot') {
            displayContent = marked.parse(text)
                .replace(/Annual Leave:/g, '<span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;margin-right:5px;color:#0D5C63;">event_available</span> Annual Leave:')
                .replace(/Sick Leave:/g, '<span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;margin-right:5px;color:#0D5C63;">medical_services</span> Sick Leave:');
        }

        if (role === 'bot') {
            row.innerHTML = `
                <div class="hs-bot-container">
                    <div class="hs-bot-avatar"><span class="material-symbols-outlined" style="font-size:18px;font-variation-settings:'FILL' 1;">smart_toy</span></div>
                    <div style="flex: 1; max-width: 88%;">
                        <div class="hs-bubble">${displayContent}</div>
                        <div class="hs-meta">${time} • HR ASSISTANT</div>
                    </div>
                </div>
            `;
        } else {
            row.innerHTML = `
                <div class="hs-bubble">${text}</div>
                <div class="hs-meta">${time} • YOU</div>
            `;
        }

        hsBody.appendChild(row);
        hsBody.scrollTop = hsBody.scrollHeight;

        if (save) {
            const h = JSON.parse(localStorage.getItem(HS_STORAGE) || '[]');
            h.push({text, role, time});
            localStorage.setItem(HS_STORAGE, JSON.stringify(h.slice(-15)));
        }
    }

    function loadHsHistory() {
        hsBody.innerHTML = '';
        const h = JSON.parse(localStorage.getItem(HS_STORAGE) || '[]');
        if (h.length === 0) {
            // Initial greeting happens on toggle if no history
            hsHandleSend("hi", false);
        } else {
            h.forEach(m => {
                appendHsMsg(m.text, m.role, false);
            });
            hsBody.scrollTop = hsBody.scrollHeight;
        }
    }

    function hsHandleSubmit(e) { e.preventDefault(); hsHandleSend(); }

    async function hsHandleSend(forcedMsg = null, showUserMsg = true) {
        const val = forcedMsg || hsInput.value.trim();
        if (!val) return;
        if(showUserMsg) appendHsMsg(val, 'user');
        hsInput.value = '';

        const typing = document.createElement('div');
        typing.className = 'hs-meta animate-pulse';
        typing.innerText = 'ANALYZING...';
        hsBody.appendChild(typing);
        hsBody.scrollTop = hsBody.scrollHeight;

        try {
            const res = await fetch('/digital-library/chat', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                body: JSON.stringify({ message: val })
            });
            const data = await res.json();
            hsBody.removeChild(typing);
            appendHsMsg(data.message, 'bot');
        } catch (e) {
            if (typing) hsBody.removeChild(typing);
            appendHsMsg("Communication error.", 'bot', false);
        }
    }

    window.hsQuickQuery = (t) => { hsInput.value = t; hsHandleSend(); }
</script>
