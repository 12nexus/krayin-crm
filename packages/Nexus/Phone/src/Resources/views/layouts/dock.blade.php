{{--
    The web phone on every CRM page (admin.layout.body.after): a button in the
    corner that opens the phone. It stays loaded so incoming calls ring anywhere
    in the CRM, signed in as the current user. window.NexusPhone.dial() places a
    call from a lead page.
--}}
@if (config('phone.url') && auth()->guard('user')->check())
    <div id="nexus-phone-dock" class="nexus-phone-dock" data-open="0" data-state="idle">
        <div class="nexus-phone-panel" role="dialog" aria-label="@lang('phone::app.dock.phone')">
            <div class="nexus-phone-head">
                <span class="nexus-phone-title">@lang('phone::app.dock.phone') · {{ config('phone.number') }}</span>
                {{-- The standalone phone is for admins; everyone else uses it here. --}}
                @if (auth()->guard('user')->user()->role?->permission_type === 'all')
                    <a href="{{ route('admin.leads.phone.sso') }}" target="_blank" rel="noopener" class="nexus-phone-link">@lang('phone::app.dock.open-full') ↗</a>
                @endif
                <button type="button" class="nexus-phone-x" onclick="NexusPhone.toggle(false)" aria-label="@lang('phone::app.dock.hide')">×</button>
            </div>
            <iframe
                id="nexus-phone-frame"
                title="@lang('phone::app.dock.phone')"
                src="{{ route('admin.leads.phone.sso', ['embed' => 1]) }}"
                {{-- The src redirects to the phone, so name its origin: a bare "microphone" would only cover the CRM's own. --}}
                allow="microphone {{ config('phone.url') }}; autoplay {{ config('phone.url') }}"
            ></iframe>
        </div>

        <button type="button" class="nexus-phone-fab" onclick="NexusPhone.toggle()">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6.6 10.8a15.1 15.1 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1-.25 11.4 11.4 0 0 0 3.6.57 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.25.2 2.45.57 3.57a1 1 0 0 1-.25 1z"/></svg>
            <span class="nexus-phone-label">@lang('phone::app.dock.phone')</span>
        </button>
    </div>

    <script>
        (function () {
            const origin = new URL(@json(config('phone.url'))).origin;
            const dock = document.getElementById('nexus-phone-dock');
            const frame = document.getElementById('nexus-phone-frame');
            const label = dock.querySelector('.nexus-phone-label');
            const t = @json(trans('phone::app.dock'));
            let ready = false, queued = null, call = null, tick = null;

            const post = (msg) => frame.contentWindow && frame.contentWindow.postMessage({ source: 'nexus-crm', ...msg }, origin);
            const fmt = (s) => Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');

            window.NexusPhone = {
                toggle(open) {
                    dock.dataset.open = (open ?? dock.dataset.open !== '1') ? '1' : '0';
                },
                dial(number, lead) {
                    this.toggle(true);
                    if (ready) post({ t: 'dial', number, lead });
                    else queued = { t: 'dial', number, lead };
                },
                text(number, lead) {
                    this.toggle(true);
                    if (ready) post({ t: 'text', number, lead });
                    else queued = { t: 'text', number, lead };
                },
            };

            frame.addEventListener('load', () => { ready = false; post({ t: 'hello' }); });

            window.addEventListener('message', (e) => {
                const m = e.data;
                if (e.origin !== origin || e.source !== frame.contentWindow || ! m || m.source !== 'nexus-phone') return;

                if (m.t === 'ready') {
                    ready = true;
                    if (queued) { post(queued); queued = null; }
                }

                if (m.t === 'state' || m.t === 'ready') {
                    call = m.call;
                    clearInterval(tick);
                    let state = 'idle', text = t.phone;
                    if (m.incoming) { state = 'ringing'; text = t.incoming; }
                    else if (call && call.mine && call.state === 'active') {
                        state = 'active';
                        const upd = () => { label.textContent = t['in-call'] + ' · ' + fmt(Math.floor((Date.now() - call.answeredAt) / 1000)); };
                        upd(); tick = setInterval(upd, 1000);
                    }
                    else if (call && call.mine) { state = 'active'; text = t.calling; }
                    dock.dataset.state = state;
                    if (state !== 'active' || ! call || call.state !== 'active') label.textContent = text;
                    if (m.incoming || m.audioNeeded) NexusPhone.toggle(true);
                }
            });

            // Leaving the page mid-call drops the audio until the phone reloads on the next page.
            window.addEventListener('beforeunload', (e) => {
                if (call && call.mine) { e.preventDefault(); e.returnValue = t.leave; return t.leave; }
            });
        })();
    </script>

    <style>
        .nexus-phone-dock { position: fixed; right: 18px; bottom: 18px; z-index: 10050; display: flex; flex-direction: column; align-items: flex-end; gap: 10px; font-family: inherit; }
        .nexus-phone-panel { width: 340px; height: 560px; max-height: calc(100vh - 100px); background: #fff; border: 1px solid #D7E2FF; border-radius: 14px; box-shadow: 0 12px 32px rgba(1,27,53,.14); display: flex; flex-direction: column; overflow: hidden; }
        /* Hidden off-screen rather than display:none, so the phone keeps running and rings. */
        .nexus-phone-dock[data-open="0"] .nexus-phone-panel { position: fixed; left: -10000px; top: 0; visibility: hidden; }
        .nexus-phone-head { display: flex; align-items: center; gap: 8px; padding: 8px 10px 8px 14px; border-bottom: 1px solid #EAF0FF; font-size: 13px; }
        .nexus-phone-title { flex: 1; font-weight: 600; color: #011B35; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .nexus-phone-link { color: #004EF0; font-size: 12px; white-space: nowrap; }
        .nexus-phone-link:hover { text-decoration: underline; }
        .nexus-phone-x { font-size: 20px; line-height: 1; color: #6b7280; padding: 0 4px; background: none; border: 0; cursor: pointer; }
        #nexus-phone-frame { flex: 1; width: 100%; border: 0; background: #fff; }
        .nexus-phone-fab { display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px; border-radius: 999px; border: 0; cursor: pointer; background: #011B35; color: #fff; font-weight: 600; font-size: 14px; box-shadow: 0 6px 20px rgba(1,27,53,.25); transition: background-color .15s; }
        .nexus-phone-fab:hover { background: #0B2E63; }
        .nexus-phone-fab svg { width: 18px; height: 18px; }
        /* Ringing and on-a-call keep red and green, so the state reads at a glance. */
        .nexus-phone-dock[data-state="active"] .nexus-phone-fab { background: #16a34a; }
        .nexus-phone-dock[data-state="ringing"] .nexus-phone-fab { background: #dc2626; animation: nexus-phone-pulse 1s infinite; }
        @keyframes nexus-phone-pulse { 50% { transform: scale(1.06); } }
        .dark .nexus-phone-panel { background: #031427; border-color: #0B2E63; box-shadow: 0 12px 32px rgba(0,0,0,.4); }
        .dark .nexus-phone-head { border-color: #0B2E63; }
        .dark .nexus-phone-title { color: #D7E2FF; }
        .dark .nexus-phone-link { color: #7EA2FF; }
        .dark .nexus-phone-x { color: #7EA2FF; }
        /* Navy would vanish on the dark page: the idle button turns brand blue there. */
        .dark .nexus-phone-dock[data-state="idle"] .nexus-phone-fab { background: #004EF0; }
        .dark .nexus-phone-dock[data-state="idle"] .nexus-phone-fab:hover { background: #0B2E63; }
        /*
            Brand outline buttons on lead pages (Text, Play recording). They live here
            because the dock sits outside the Vue app, which drops <style> tags in its
            templates, and Krayin's CSS is prebuilt, so new Tailwind colours don't exist.
        */
        .nexus-phone-outline { border-color: #004EF0; color: #004EF0; }
        .nexus-phone-outline:hover { background: #EAF0FF; }
        .dark .nexus-phone-outline { border-color: #7EA2FF; color: #7EA2FF; }
        .dark .nexus-phone-outline:hover { background: #0B2E63; }
        @media (max-width: 480px) { .nexus-phone-panel { width: calc(100vw - 24px); } .nexus-phone-dock { right: 12px; bottom: 12px; } }
    </style>
@endif
