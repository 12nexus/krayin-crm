{{--
    Click-to-copy for any element carrying data-nexus-copy="<value>": the value
    itself copies on click, and a copy icon beside it shows on hover. Used for
    the email addresses and phone numbers on the lead view, where a click had
    nothing else to do (the CRM does no mailing or calling).

    One delegated listener, so it keeps working after Vue re-renders.
--}}
<script>
    document.addEventListener('click', function (event) {
        const target = event.target.closest('[data-nexus-copy]');

        if (! target) {
            return;
        }

        event.preventDefault();

        const value = target.getAttribute('data-nexus-copy');

        const done = function () {
            target.classList.add('nexus-copied');

            setTimeout(function () {
                target.classList.remove('nexus-copied');
            }, 1500);

            window.app?.config?.globalProperties?.$emitter?.emit('add-flash', {
                type: 'success',
                message: @json(trans('funnel::app.copy.copied')).replace(':value', value),
            });
        };

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(value).then(done);

            return;
        }

        // Plain-HTTP fallback (local development).
        const input = document.createElement('textarea');

        input.value = value;
        input.style.position = 'fixed';
        input.style.opacity = '0';

        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        document.body.removeChild(input);

        done();
    });
</script>

<style>
    [data-nexus-copy] { cursor: copy; }
    [data-nexus-copy] .nexus-copy-icon { opacity: 0; transition: opacity .15s; }
    [data-nexus-copy]:hover .nexus-copy-icon,
    [data-nexus-copy]:focus-visible .nexus-copy-icon { opacity: 1; }
    [data-nexus-copy].nexus-copied .nexus-copy-icon { opacity: 1; color: #16a34a; }
</style>
