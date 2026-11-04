/**
 * Offline SweetAlert2 wrappers — drop-in replacement for native
 * alert() / confirm() / prompt() across the app.
 *
 * Requires public/js/sweetalert2.min.js loaded first; falls back to the
 * native dialogs if it is missing, so no page ever breaks.
 *
 *   Dlg.alert(msg)              -> Promise (fire-and-forget, same as alert)
 *   Dlg.confirm(msg, opts?)     -> Promise<boolean>
 *   Dlg.prompt(title, opts?)    -> Promise<string|null>  (trimmed; '' rejected)
 *
 *   data-confirm="Message"      on an <a> or a submit <button>/<input>:
 *   the click is intercepted, a SweetAlert Yes/Cancel is shown, and the
 *   default action (navigate / submit the owning form) only runs on Yes.
 */
(function () {
    'use strict';

    var BUTTON_COLOR = '#4a90d9';   // matches --accent in the layouts

    function baseOptions() {
        return {
            confirmButtonColor: BUTTON_COLOR,
            cancelButtonColor: '#6c757d',
            buttonsStyling: true
        };
    }

    // Pick an icon from the message when the caller does not specify one.
    function autoIcon(msg) {
        var m = String(msg || '');
        if (/(successfully|saved|updated|success\b|approved|released|completed|passed)/i.test(m)) return 'success';
        if (/(error|failed|failure|network|script error|unable|could not|exceeds|not found|already used|required)/i.test(m)) return 'error';
        if (/^(please|select|enter|choose|invalid|must)/i.test(m)) return 'warning';
        return 'info';
    }

    if (typeof Swal === 'undefined') {
        // sweetalert2 failed to load — keep the native dialogs working.
        window.Dlg = {
            alert: function (msg) { window.alert(msg); return Promise.resolve(); },
            confirm: function (msg) { return Promise.resolve(window.confirm(msg)); },
            prompt: function (title) { return Promise.resolve(window.prompt(title)); }
        };
        return;
    }

    window.Dlg = {
        /** Replaces alert(): resolves as soon as the user dismisses. */
        alert: function (msg, icon) {
            return Swal.fire(Object.assign(baseOptions(), {
                icon: icon || autoIcon(msg),
                text: String(msg == null ? '' : msg),
                confirmButtonText: 'OK',
                allowOutsideClick: false
            }));
        },

        /** Replaces confirm(): resolves true on Yes, false on Cancel. */
        confirm: function (msg, opts) {
            opts = opts || {};
            return Swal.fire(Object.assign(baseOptions(), {
                icon: opts.icon || 'question',
                title: opts.title || '',
                text: String(msg == null ? '' : msg),
                showCancelButton: true,
                confirmButtonText: opts.confirmText || 'Yes',
                cancelButtonText: opts.cancelText || 'Cancel',
                allowOutsideClick: false
            })).then(function (r) { return !!r.isConfirmed; });
        },

        /**
         * Replaces prompt(): resolves to the trimmed input value on confirm,
         * '' is rejected with a toast (mirrors the old "alert + return" guard),
         * and null resolves on cancel.
         */
        prompt: function (title, opts) {
            opts = opts || {};
            return Swal.fire(Object.assign(baseOptions(), {
                icon: opts.icon || 'question',
                title: String(title == null ? '' : title),
                input: 'text',
                inputPlaceholder: opts.placeholder || '',
                inputValue: opts.value || '',
                showCancelButton: true,
                confirmButtonText: opts.confirmText || 'OK',
                cancelButtonText: opts.cancelText || 'Cancel',
                allowOutsideClick: false,
                inputValidator: function (value) {
                    if (String(opts.value || '') === '' && String(value || '').trim() === '') {
                        return opts.requiredMessage || 'A value is required.';
                    }
                }
            })).then(function (r) {
                if (!r.isConfirmed) return null;
                var v = String(r.value || '').trim();
                if (v === '') {
                    // Empty allowed only when a default was provided; otherwise toast.
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'error',
                        text: opts.requiredMessage || 'A value is required.',
                        showConfirmButton: false,
                        timer: 3500
                    });
                    return null;
                }
                return v;
            });
        }
    };

    /**
     * data-confirm delegation. Intercepts clicks on links / submit buttons
     * carrying data-confirm, shows a SweetAlert Yes/Cancel, and performs the
     * default action only on Yes. Only real triggers count — clicks on plain
     * form fields (inputs, selects, labels) or non-submit buttons pass through.
     */
    document.addEventListener('click', function (e) {
        var target = e.target;
        if (!target || typeof target.closest !== 'function') return;
        var trigger = target.closest('a, button, input[type="submit"], input[type="image"]');
        if (!trigger) return;
        var host = trigger.closest('[data-confirm]');
        if (!host) return;

        if (host.tagName === 'A') {
            // data-confirm on a link (or inside one).
            e.preventDefault();
            window.Dlg.confirm(host.getAttribute('data-confirm'), {
                confirmText: trigger.getAttribute('data-confirm-yes') || 'Yes'
            }).then(function (ok) {
                if (!ok) return;
                var href = host.getAttribute('href');
                if (!href) return;
                if (host.getAttribute('target') === '_blank') window.open(href, '_blank');
                else window.location.href = href;
            });
            return;
        }

        // data-confirm on a FORM: only a real submit control may trigger it
        // (a type="button" widget inside the form must not).
        if (host.tagName === 'FORM') {
            if (trigger !== host && trigger.type !== 'submit' && trigger.type !== 'image') return;
            e.preventDefault();
            window.Dlg.confirm(host.getAttribute('data-confirm'), {
                confirmText: trigger.getAttribute('data-confirm-yes') || 'Yes'
            }).then(function (ok) {
                if (!ok) return;
                if (typeof host.requestSubmit === 'function') host.requestSubmit();
                else host.submit();
            });
            return;
        }

        // data-confirm on the submit button/input itself.
        if (trigger !== host) return;
        if (host.type && host.type !== 'submit' && host.type !== 'image') return;
        e.preventDefault();
        window.Dlg.confirm(host.getAttribute('data-confirm'), {
            confirmText: host.getAttribute('data-confirm-yes') || 'Yes'
        }).then(function (ok) {
            if (!ok) return;
            var form = host.form;
            if (!form) return;
            if (typeof form.requestSubmit === 'function') form.requestSubmit();
            else form.submit();
        });
    });
})();
