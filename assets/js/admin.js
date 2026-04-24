/* Smart Booking v4 — Admin JS */
(function ($) {
'use strict';
function sbAdminPrimary(){return getComputedStyle(document.documentElement).getPropertyValue('--p').trim()||'#5B2D8E';}
$(document).ready(function () {

    // ── Color pickers ──────────────────────────────────────────
    // ── Color pickers ──────────────────────────────────────────
    if ($.fn.wpColorPicker) {
        $('.sbw-color').wpColorPicker({
            change: function (event, ui) {
                // Sync the original hidden input on every pick so serialize() gets the right value
                $(this).val(ui.color.toString());
            }
        });
    }

    // ── Confirm/cancel from dashboard pending rows ─────────────
    $(document).on('click', '.sbw-confirm', function () {
        updateStatus($(this).data('id'), 'confirmed', $(this).closest('.sbw-pend-row'));
    });
    $(document).on('click', '.sbw-cancel', function () {
        updateStatus($(this).data('id'), 'cancelled', $(this).closest('.sbw-pend-row'));
    });

    function updateStatus(id, status, $row) {
        $.post(SB.ajax, { action:'sb_update_status', nonce:SB.nonce, id:id, status:status }, function (res) {
            if (res.success && $row) $row.fadeOut(300);
        });
    }

    // ── Bookings page — inline status change ───────────────────
    $(document).on('change', '.sbw-status-sel', function () {
        var id = $(this).data('id'), status = $(this).val();
        $.post(SB.ajax, { action:'sb_update_status', nonce:SB.nonce, id:id, status:status });
    });

    // ── Bookings page — delete ─────────────────────────────────
    $(document).on('click', '.sbw-del-btn', function () {
        if (!confirm(SB.confirm_delete)) return;
        var id = $(this).data('id');
        var $tr = $(this).closest('tr');
        $.post(SB.ajax, { action:'sb_delete_booking', nonce:SB.nonce, id:id }, function (res) {
            if (res.success) $tr.fadeOut(300);
        });
    });

    // ── SERVICES PAGE ──────────────────────────────────────────
    $('#sbAddServiceBtn').on('click', function () {
        openSvcModal(null);
    });

    $(document).on('click', '.sbw-edit-svc', function () {
        var $b = $(this);
        openSvcModal({
            id:      $b.data('id'),
            name:    $b.data('name'),
            desc:    $b.data('desc'),
            policy:  $b.attr('data-policy') || '',
            duration:$b.data('duration'),
            padding: $b.data('padding'),
            price:   $b.data('price'),
            deposit: $b.data('deposit'),
            color:   $b.data('color'),
            cat:     $b.data('cat'),
            cap:     $b.data('cap'),
            status:  $b.data('status'),
            image:   $b.attr('data-image') || '',
            gallery: $b.attr('data-gallery') || '',
        });
    });

    function openSvcModal(data) {
        var isEdit = data && data.id;
        $('#sbSvcModalTitle').text(isEdit ? 'Edit Service' : 'Add Service');
        $('#sbSvcId').val(isEdit ? data.id : '');
        $('#sbSvcName').val(isEdit ? data.name : '');
        $('#sbSvcDesc').val(isEdit ? data.desc : '');
        $('#sbSvcPolicy').val(isEdit ? (data.policy||'') : '');
        $('#sbSvcDuration').val(isEdit ? data.duration : 60);
        $('#sbSvcPadding').val(isEdit ? data.padding : 0);
        $('#sbSvcPrice').val(isEdit ? data.price : 0);
        $('#sbSvcDeposit').val(isEdit ? data.deposit : 0);
        $('#sbSvcCap').val(isEdit ? data.cap : 1);
        $('#sbSvcCat').val(isEdit ? data.cat : 0);
        $('#sbSvcStatus').val(isEdit ? data.status : 'active');
        if (isEdit && $.fn.wpColorPicker) {
            $('#sbSvcColor').wpColorPicker('color', data.color);
        } else {
            $('#sbSvcColor').val(isEdit ? data.color : sbAdminPrimary());
        }
        // Set existing image
        var img = (isEdit && data.image) ? data.image : '';
        $('#sbSvcImageUrl').val(img);
        if (img) {
            $('#sbSvcImgPreview').html('<img src="'+img+'" style="width:100%;height:100%;object-fit:cover;border-radius:6px;">');
            $('#sbSvcRemoveImg').show();
        } else {
            $('#sbSvcImgPreview').html('<span class="sbw-img-ph">No image</span>');
            $('#sbSvcRemoveImg').hide();
        }
        // Gallery images
        var galleryUrls = (isEdit && data.gallery) ? data.gallery : '';
        $('#sbSvcGalleryUrls').val(galleryUrls);
        renderGalleryPreview(galleryUrls ? galleryUrls.split(',').filter(Boolean) : []);
        $('#sbServiceModal').fadeIn(200);
    }

    // Image picker using WordPress media library
    var svcMediaFrame = null;
    $('#sbSvcPickImg').on('click', function() {
        if (svcMediaFrame) { svcMediaFrame.open(); return; }
        svcMediaFrame = wp.media({ title:'Choose Service Image', button:{text:'Use this image'}, multiple:false, library:{type:'image'} });
        svcMediaFrame.on('select', function() {
            var att = svcMediaFrame.state().get('selection').first().toJSON();
            $('#sbSvcImageUrl').val(att.url);
            $('#sbSvcImgPreview').html('<img src="'+att.url+'" style="width:100%;height:100%;object-fit:cover;border-radius:6px;">');
            $('#sbSvcRemoveImg').show();
        });
        svcMediaFrame.open();
    });
    $('#sbSvcRemoveImg').on('click', function() {
        $('#sbSvcImageUrl').val('');
        $('#sbSvcImgPreview').html('<span class="sbw-img-ph">No image</span>');
        $(this).hide();
    });

    // Gallery render helper
    function renderGalleryPreview(urls) {
        var $p = $('#sbGalleryPreview');
        $p.empty();
        urls.forEach(function(url, i) {
            if (!url) return;
            var $item = $('<div style="position:relative;width:70px;height:70px;"></div>');
            $item.append('<img src="'+url+'" style="width:70px;height:70px;object-fit:cover;border-radius:6px;border:1px solid #e5e7eb;">');
            var $del = $('<button type="button" style="position:absolute;top:-6px;right:-6px;width:18px;height:18px;background:#ef4444;color:#fff;border:none;border-radius:50%;cursor:pointer;font-size:11px;line-height:18px;text-align:center;padding:0;">×</button>');
            $del.data('index', i);
            $del.on('click', function() {
                var arr = ($('#sbSvcGalleryUrls').val()||'').split(',').filter(Boolean);
                arr.splice($(this).data('index'), 1);
                $('#sbSvcGalleryUrls').val(arr.join(','));
                renderGalleryPreview(arr);
            });
            $item.append($del);
            $p.append($item);
        });
    }

    // Gallery add button
    var galleryMediaFrame = null;
    $(document).on('click', '#sbSvcAddGalleryImg', function() {
        if (galleryMediaFrame) { galleryMediaFrame.open(); return; }
        galleryMediaFrame = wp.media({ title:'Add Gallery Images', button:{text:'Add to Gallery'}, multiple:true, library:{type:'image'} });
        galleryMediaFrame.on('select', function() {
            var sel = galleryMediaFrame.state().get('selection');
            var existing = ($('#sbSvcGalleryUrls').val()||'').split(',').filter(Boolean);
            sel.each(function(att) { existing.push(att.toJSON().url); });
            $('#sbSvcGalleryUrls').val(existing.join(','));
            renderGalleryPreview(existing);
        });
        galleryMediaFrame.open();
    });

    $('.sbw-modal-close').on('click', function () { $('#sbServiceModal').fadeOut(200); });

    $('#sbSaveSvcBtn').on('click', function () {
        var name = $('#sbSvcName').val().trim();
        if (!name) { alert('Service name is required.'); return; }
        var colorEl = $('#sbSvcColor');
        var color = colorEl.wpColorPicker ? colorEl.wpColorPicker('color') : colorEl.val();
        $('#sbSaveSvcBtn').prop('disabled', true).text('Saving...');
        $.post(SB.ajax, {
            action:      'sb_save_service',
            nonce:       SB.nonce,
            id:          $('#sbSvcId').val(),
            name:        name,
            description: $('#sbSvcDesc').val(),
            duration:    $('#sbSvcDuration').val(),
            padding_time:$('#sbSvcPadding').val(),
            price:       $('#sbSvcPrice').val(),
            deposit_pct: $('#sbSvcDeposit').val(),
            color:       color,
            image_url:      $('#sbSvcImageUrl').val(),
            gallery_images: $('#sbSvcGalleryUrls').val(),
            service_policy: $('#sbSvcPolicy').val(),
            category_id: $('#sbSvcCat').val(),
            capacity:    $('#sbSvcCap').val(),
            status:      $('#sbSvcStatus').val(),
        }, function (res) {
            if (res.success) {
                location.reload();
            } else {
                $('#sbSaveSvcBtn').prop('disabled', false).text('Save Service');
                alert('Error: ' + (res.data && res.data.msg ? res.data.msg : 'Could not save service. Please try again.'));
            }
        }).fail(function(xhr) {
            $('#sbSaveSvcBtn').prop('disabled', false).text('Save Service');
            alert('Connection error (HTTP ' + xhr.status + '). Check you are logged into WordPress.');
        });
    });

    $(document).on('click', '.sbw-del-svc', function () {
        if (!confirm(SB.confirm_delete)) return;
        var id = $(this).data('id');
        $.post(SB.ajax, { action:'sb_delete_service', nonce:SB.nonce, id:id }, function (res) {
            if (res.success) location.reload();
        });
    });

    // Category add
    $('#sbAddCatBtn').on('click', function () {
        var name = $('#sbNewCatName').val().trim();
        if (!name) return;
        $.post(SB.ajax, { action:'sb_save_category', nonce:SB.nonce, name:name }, function (res) {
            if (res.success) location.reload();
        });
    });

    // ── STAFF PAGE ─────────────────────────────────────────────
    $('#sbAddStaffBtn').on('click', function () { openStaffModal(null); });

    $(document).on('click', '.sbw-edit-staff', function () {
        var $b = $(this);
        var services = JSON.parse($b.attr('data-services') || '[]');
        var hours    = JSON.parse($b.attr('data-hours')    || '{}');
        openStaffModal({
            id:       $b.data('id'),
            name:     $b.data('name'),
            email:    $b.data('email'),
            phone:    $b.data('phone'),
            color:    $b.data('color'),
            services: services,
            hours:    hours,
        });
    });

    function openStaffModal(data) {
        var isEdit = data && data.id;
        $('#sbStaffModalTitle').text(isEdit ? 'Edit Staff Member' : 'Add Staff Member');
        $('#sbStaffId').val(isEdit ? data.id : '');
        $('#sbStaffName').val(isEdit  ? data.name  : '');
        $('#sbStaffEmail').val(isEdit ? data.email : '');
        $('#sbStaffPhone').val(isEdit ? data.phone : '');

        if (isEdit && $.fn.wpColorPicker) {
            $('#sbStaffColor').wpColorPicker('color', data.color);
        } else {
            $('#sbStaffColor').val(isEdit ? data.color : '#C9A84C');
        }

        // Services checkboxes
        var svcs = isEdit ? data.services : [];
        $('[name="staff_svc[]"]').each(function () {
            $(this).prop('checked', svcs.indexOf(parseInt($(this).val())) !== -1);
        });

        // Hours
        var days = [0,1,2,3,4,5,6];
        days.forEach(function (d) {
            var h = isEdit && data.hours[d] ? data.hours[d] : { start:'08:00', end:'18:00', off: d===0 ? 1:0 };
            $('.sbw-hr-start[data-day="'+d+'"]').val(h.start || '08:00');
            $('.sbw-hr-end[data-day="'+d+'"]').val(h.end || '18:00');
            var $off = $('.sbw-day-off[data-day="'+d+'"]');
            // "Open" checkbox: checked = open = is_day_off is 0
            $off.prop('checked', !parseInt(h.off));
            $('.sbw-hours-row[data-day="'+d+'"]').toggleClass('is-off', !!parseInt(h.off));
        });

        $('#sbStaffModal').fadeIn(200);
    }

    $('.sbw-modal-close-staff').on('click', function () { $('#sbStaffModal').fadeOut(200); });

    $(document).on('change', '.sbw-day-off', function () {
        var day = $(this).data('day');
        // "Open" checkbox: unchecked means the day is OFF
        $('.sbw-hours-row[data-day="'+day+'"]').toggleClass('is-off', !$(this).is(':checked'));
    });

    $('#sbSaveStaffBtn').on('click', function () {
        var name = $('#sbStaffName').val().trim();
        if (!name) { alert('Staff name is required.'); return; }

        var colorEl = $('#sbStaffColor');
        var color   = colorEl.wpColorPicker ? colorEl.wpColorPicker('color') : colorEl.val();

        var services = [];
        $('[name="staff_svc[]"]:checked').each(function () { services.push($(this).val()); });

        var hours = {};
        [0,1,2,3,4,5,6].forEach(function (d) {
            hours[d] = {
                start: $('.sbw-hr-start[data-day="'+d+'"]').val(),
                end:   $('.sbw-hr-end[data-day="'+d+'"]').val(),
                off:   $('.sbw-day-off[data-day="'+d+'"]').is(':checked') ? 0 : 1,
            };
        });

        $.post(SB.ajax, {
            action:    'sb_save_staff',
            nonce:     SB.nonce,
            id:        $('#sbStaffId').val(),
            name:      name,
            email:     $('#sbStaffEmail').val(),
            phone:     $('#sbStaffPhone').val(),
            color:     color,
            services:  services,
            hours:     hours,
        }, function (res) {
            if (res.success) location.reload();
        });
    });

    $(document).on('click', '.sbw-del-staff', function () {
        if (!confirm(SB.confirm_delete)) return;
        var id = $(this).data('id');
        $.post(SB.ajax, { action:'sb_delete_staff', nonce:SB.nonce, id:id }, function (res) {
            if (res.success) location.reload();
        });
    });

    // ── SETTINGS FORM ──────────────────────────────────────────
    $('#sbSettingsForm').on('submit', function (e) {
        e.preventDefault();
        var $form = $(this);

        var data = $form.serialize();

        // wpColorPicker('color') is not a valid getter — it returns undefined.
        // Instead we read via iris (the underlying engine), falling back to .val().
        // We then explicitly replace each color field in the serialized string
        // so stale/empty values from serialize() are overwritten.
        $form.find('.sbw-color').each(function () {
            var $input = $(this);
            var name   = $input.attr('name');
            if (!name) return;
            var color  = '';
            try { color = $input.iris('option', 'color'); } catch (e) {}
            if (!color) color = $input.val();
            if (color) {
                data = data.replace(new RegExp('(^|&)' + encodeURIComponent(name) + '=[^&]*', 'g'), '');
                data += '&' + encodeURIComponent(name) + '=' + encodeURIComponent(color);
            }
        });

        // serialize() skips unchecked checkboxes — add them manually as 0
        var checkboxNames = ['float_on','campay_sandbox','show_categories','require_staff','enable_daily_rental','notify_whatsapp','notify_email_method','notify_sms','enable_staff','enable_days'];
        $.each(checkboxNames, function(i, name) {
            if (!$form.find('[name="' + name + '"]').is(':checked')) {
                data += '&' + name + '=0';
            }
        });

        var $btn = $form.find('[type="submit"]');
        $btn.prop('disabled', true).text('Saving…');

        $.post(SB.ajax, data, function (res) {
            if (res.success) {
                window.location.href = window.location.href.split('?')[0] + '?page=sb-settings&saved=1';
            } else {
                alert('Save failed. Please try again.');
                $btn.prop('disabled', false).text('Save Settings');
            }
        }).fail(function() {
            alert('Network error. Please try again.');
            $btn.prop('disabled', false).text('Save Settings');
        });
    });

    // ── ADMIN CALENDAR ─────────────────────────────────────────
    if ($('#sbAdminCal').length) {
        initAdminCalendar();
    }

    function initAdminCalendar() {
        var weekStart = getMonday(new Date());

        function getMonday(d) {
            var day = d.getDay(), diff = d.getDate() - day + (day === 0 ? -6 : 1);
            return new Date(d.setDate(diff));
        }
        function addDays(d, n) { var r = new Date(d); r.setDate(r.getDate()+n); return r; }
        function fmt(d) { return d.toISOString().split('T')[0]; }
        function fmtLabel(d) { return d.toLocaleDateString('en-GB',{weekday:'short',day:'numeric',month:'short'}); }

        function load() {
            var from = fmt(weekStart);
            var to   = fmt(addDays(weekStart, 6));
            $('#sbAdminCalRange').text(fmtLabel(weekStart) + ' – ' + fmtLabel(addDays(weekStart,6)));
            var staffId = $('#sbAdminCalStaff').val() || 0;
            $.get(SB.ajax, { action:'sb_cal_bookings', nonce:SB.nonce, from:from, to:to, staff_id:staffId }, function (res) {
                renderWeek(weekStart, res.success ? res.data : []);
            });
        }

        function renderWeek(start, bookings) {
            var days = [], cols = [];
            for (var i = 0; i < 7; i++) days.push(addDays(start,i));

            var html = '<table style="width:100%;border-collapse:collapse;min-width:600px">';
            html += '<thead><tr><th style="width:50px;border:1px solid #e5e7eb;padding:8px 4px;font-size:.8rem;color:#94a3b8">Time</th>';
            days.forEach(function (d) {
                var isToday = fmt(d) === fmt(new Date());
                html += '<th style="border:1px solid #e5e7eb;padding:8px 6px;font-size:.82rem;'+(isToday?'background:var(--p,#f5f0ff);color:#fff;':'')+'">'
                      + d.toLocaleDateString('en-GB',{weekday:'short',day:'numeric'}) + '</th>';
            });
            html += '</tr></thead><tbody>';

            for (var h = 7; h <= 20; h++) {
                var timeLabel = (h % 12 || 12) + ':00 ' + (h < 12 ? 'AM' : 'PM');
                html += '<tr><td style="border:1px solid #f1f5f9;padding:6px 4px;font-size:.75rem;color:#94a3b8;white-space:nowrap;text-align:right;vertical-align:top">' + timeLabel + '</td>';
                days.forEach(function (d) {
                    var dateStr = fmt(d);
                    var slotHtml = '';
                    bookings.forEach(function (b) {
                        if (b.date !== dateStr) return;
                        var bh = parseInt(b.start.split(':')[0]);
                        if (bh === h) {
                            slotHtml += '<div style="background:' + (b.color||sbAdminPrimary()) + ';color:#fff;border-radius:4px;padding:3px 6px;font-size:.75rem;margin:2px 0;line-height:1.3">'
                                      + '<strong>' + escHtml(b.title) + '</strong><br>'
                                      + b.start.substring(0,5) + '–' + (b.end||'').substring(0,5)
                                      + '</div>';
                        }
                    });
                    html += '<td style="border:1px solid #f1f5f9;padding:2px 3px;vertical-align:top;min-height:36px">' + slotHtml + '</td>';
                });
                html += '</tr>';
            }
            html += '</tbody></table>';
            $('#sbAdminCal').html(html);
        }

        function escHtml(s) { return $('<div>').text(String(s)).html(); }

        $('.sbw-cal-prev-week').on('click', function () { weekStart = addDays(weekStart,-7); load(); });
        $('.sbw-cal-next-week').on('click', function () { weekStart = addDays(weekStart, 7); load(); });
        $('#sbAdminCalToday').on('click', function () { weekStart = getMonday(new Date()); load(); });
        $('#sbAdminCalStaff').on('change', load);
        load();
    }

});
})(jQuery);