/**
 * Smart Booking — Frontend Admin Portal JS
 * Handles: mobile nav toggle, copy buttons, settings save redirect,
 * and WhatsApp test — all scoped to the frontend portal.
 */
(function ($) {
    'use strict';

    // ── Mobile nav toggle ───────────────────────────────────
    // Insert overlay element once
    if ( ! document.getElementById('sbNavOverlay') ) {
        $('body').append('<div class="sbw-nav-overlay" id="sbNavOverlay"></div>');
    }

    function openNav() {
        $('.sbw-portal-nav').addClass('open');
        $('#sbNavOverlay').addClass('active');
        $('body').css('overflow', 'hidden');
    }
    function closeNav() {
        $('.sbw-portal-nav').removeClass('open');
        $('#sbNavOverlay').removeClass('active');
        $('body').css('overflow', '');
    }

    $(document).on('click', '#sbPortalNavToggle', function () {
        if ( $('.sbw-portal-nav').hasClass('open') ) { closeNav(); } else { openNav(); }
    });

    // Close when overlay is tapped
    $(document).on('click', '#sbNavOverlay', function () { closeNav(); });

    // Close when a menu item is tapped on mobile
    $(document).on('click', '.sbw-portal-menu-item', function () {
        if ( $(window).width() <= 900 ) { closeNav(); }
    });

    // ── Copy shortcode buttons ───────────────────────────────
    $(document).on('click', '.sbw-copy-btn', function () {
        var text = $(this).data('copy') || $(this).prev('code').text();
        if (!text) return;
        var $btn = $(this);
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(function () {
                $btn.text('Copied!');
                setTimeout(function () { $btn.text('Copy'); }, 1500);
            });
        } else {
            // Fallback
            var ta = document.createElement('textarea');
            ta.value = text;
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            $btn.text('Copied!');
            setTimeout(function () { $btn.text('Copy'); }, 1500);
        }
    });

    // ── Settings form: save via AJAX then redirect back to portal with ?sb_saved=1 ──
    $(document).on('submit', '#sbSettingsForm', function (e) {
        // Only intercept when we're in the frontend portal
        if (!$('#sbFrontendPortal').length) return;
        e.preventDefault();

        var $form = $(this);
        var $btn  = $form.find('[type=submit]');
        var origText = $btn.text();
        $btn.text('Saving…').prop('disabled', true);

        var data = $form.serializeArray();
        // Append checkboxes that are unchecked (serializeArray skips them)
        $form.find('input[type=checkbox]').each(function () {
            if (!$(this).is(':checked')) {
                // Send 0 so the handler knows it's off
                data.push({ name: this.name, value: '0' });
            }
        });

        $.post(
            (typeof SB_PORTAL !== 'undefined' ? SB_PORTAL.ajax : SB_ADM.ajax),
            $.param(data),
            function (res) {
                if (res && res.success) {
                    // Redirect back to the same tab with saved flag
                    var url = window.location.href;
                    url = url.replace(/[?&]sb_saved=[^&]*/g, '');
                    url += (url.indexOf('?') === -1 ? '?' : '&') + 'sb_saved=1';
                    window.location.href = url;
                } else {
                    alert('Error saving settings. Please try again.');
                    $btn.text(origText).prop('disabled', false);
                }
            }
        ).fail(function () {
            alert('Could not connect. Please try again.');
            $btn.text(origText).prop('disabled', false);
        });
    });

    // ── WhatsApp test button (frontend context) ──────────────
    $(document).on('click', '#sbTestWhatsapp', function () {
        if (!$('#sbFrontendPortal').length) return;
        var $btn    = $(this);
        var $result = $('#sbWaTestResult');
        $btn.prop('disabled', true).text('Testing…');
        $result.hide().html('');

        $.post(SB_ADM.ajax, {
            action: 'sb_test_whatsapp',
            nonce:  SB_ADM.nonce
        }, function (res) {
            var log   = res.data && res.data.log ? res.data.log : ['No response.'];
            var color = res.success ? '#15803d' : '#b91c1c';
            var bg    = res.success ? '#f0fdf4' : '#fff5f5';
            $result.html('<pre style="background:' + bg + ';color:' + color + ';padding:12px 16px;border-radius:8px;font-size:.78rem;white-space:pre-wrap;overflow:auto;max-height:280px">' + log.join('\n') + '</pre>').show();
        }).always(function () {
            $btn.prop('disabled', false).text('Send Test WhatsApp');
        });
    });

    // ── CamPay test button (frontend context) ────────────────
    $(document).on('click', '#sbTestCampay', function () {
        if (!$('#sbFrontendPortal').length) return;
        var $btn    = $(this);
        var $result = $('#sbCampayTestResult');
        $btn.prop('disabled', true).text('Testing…');
        $result.hide().html('');

        var data = {
            action:           'sb_test_campay',
            nonce:            SB_ADM.nonce,
            campay_username:  $('[name=campay_username]').val(),
            campay_password:  $('[name=campay_password]').val(),
            campay_sandbox:   $('[name=campay_sandbox]').is(':checked') ? '1' : '0'
        };

        $.post(SB_ADM.ajax, data, function (res) {
            var log   = res.data && res.data.log ? res.data.log : ['No response.'];
            var color = res.success ? '#15803d' : '#b91c1c';
            var bg    = res.success ? '#f0fdf4' : '#fff5f5';
            $result.html('<pre style="background:' + bg + ';color:' + color + ';padding:12px 16px;border-radius:8px;font-size:.78rem;white-space:pre-wrap;overflow:auto;max-height:280px">' + log.join('\n') + '</pre>').show();
        }).always(function () {
            $btn.prop('disabled', false).text('Test CamPay Connection');
        });
    });

})(jQuery);
