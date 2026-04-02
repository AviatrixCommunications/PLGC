/**
 * Aviatrix Compliance Suite - Admin Scripts
 */
(function ($) {
    'use strict';

    // ============================================================
    // CATEGORIES TABLE — Add/Remove rows
    // ============================================================
    var catIndex = $('#plgc-categories-table tbody tr').length;

    $('#plgc-add-category').on('click', function () {
        var row = '<tr class="plgc-category-row">' +
            '<td><input type="text" name="categories[' + catIndex + '][slug]" class="regular-text" style="width:100%;" pattern="[a-z0-9_-]+" placeholder="e.g., board_docs" /></td>' +
            '<td><input type="text" name="categories[' + catIndex + '][label]" class="regular-text" style="width:100%;" placeholder="e.g., Board Documents" required /></td>' +
            '<td><input type="text" name="categories[' + catIndex + '][retention]" class="regular-text" style="width:100%;" placeholder="e.g., 5 years" /></td>' +
            '<td><button type="button" class="button plgc-remove-category" title="Remove">✕ Remove</button></td>' +
            '</tr>';
        $('#plgc-categories-table tbody').append(row);
        catIndex++;
    });

    $(document).on('click', '.plgc-remove-category', function () {
        if (confirm('Remove this category? Existing documents won\'t be deleted, just unassigned.')) {
            $(this).closest('tr').remove();
        }
    });

    // Auto-generate slug from label
    $(document).on('blur', 'input[name*="[label]"]', function () {
        var row = $(this).closest('tr');
        var slugInput = row.find('input[name*="[slug]"]');
        if (!slugInput.val()) {
            var slug = $(this).val().toLowerCase()
                .replace(/[^a-z0-9\s-]/g, '')
                .replace(/\s+/g, '_')
                .replace(/-+/g, '_');
            slugInput.val(slug);
        }
    });

    // ============================================================
    // CATEGORY SELECT — Auto-calculate review date
    // ============================================================
    $(document).on('change', '.plgc-doc-category-select', function () {
        var selected = $(this).find(':selected');
        var retention = selected.data('retention');
        var id = $(this).data('attachment-id');
        var dateInput = $('#plgc_date_' + id);

        if (retention) {
            // Calculate date from retention string
            var parts = retention.match(/(\d+)\s*(year|month|week|day)/i);
            if (parts) {
                var num = parseInt(parts[1]);
                var unit = parts[2].toLowerCase();
                var date = new Date();

                switch (unit) {
                    case 'year':  date.setFullYear(date.getFullYear() + num); break;
                    case 'month': date.setMonth(date.getMonth() + num); break;
                    case 'week':  date.setDate(date.getDate() + (num * 7)); break;
                    case 'day':   date.setDate(date.getDate() + num); break;
                }

                dateInput.val(date.toISOString().split('T')[0]);
            }
        }
    });

    // Clear date button
    $(document).on('click', '.plgc-clear-date', function () {
        $('#' + $(this).data('target')).val('');
    });

    // ============================================================
    // TITLE II EXCEPTION TYPE — Show guidance on select
    // ============================================================
    $(document).on('change', 'select[name*="plgc_title2_exception"]', function () {
        var id = $(this).attr('id').replace('plgc_exc_', '');
        var guidance = $('#plgc_exc_guidance_' + id);
        var val = $(this).val();

        var texts = {
            'archived_content': '<strong>All four criteria must be met:</strong><br>☐ Created before compliance deadline<br>☐ Kept only for reference, research, or recordkeeping<br>☐ Stored in dedicated archive area<br>☐ Not modified since archiving<br><em>Must still provide accessible version upon request.</em>',
            'preexisting_doc': '<strong>Applies to:</strong> PDF, Word, Excel, PowerPoint created before deadline.<br><strong>Exception lost if:</strong> document is currently used to apply for, gain access to, or participate in services.<br><em>Does NOT need to be in the archive section, but must not be in active use.</em>',
            'third_party': '<strong>Applies to:</strong> Content from unaffiliated third parties (public comments, user submissions).<br><strong>Does NOT apply to:</strong> Content from contractors, vendors, or partners.',
            'password_protected': '<strong>Applies to:</strong> Individualized, password-protected documents (utility bills, tax docs).<br><strong>Note:</strong> The portal/system delivering these must still be accessible.'
        };

        if (val && texts[val]) {
            guidance.html(texts[val]).show();
        } else {
            guidance.hide();
        }
    });

    // ============================================================
    // ARCHIVE / RESTORE AJAX
    // ============================================================
    $(document).on('click', '.plgc-archive-doc', function () {
        var btn = $(this);
        var id = btn.data('id');
        if (!confirm('Archive this document? It will be removed from public access.')) return;

        btn.text('Archiving...').prop('disabled', true);
        $.post(plgcDocMgr.ajaxUrl, {
            action: 'plgc_docmgr_archive',
            attachment_id: id,
            _ajax_nonce: plgcDocMgr.nonce
        }, function (resp) {
            if (resp.success) location.reload();
            else { btn.text('Error').prop('disabled', false); alert(resp.data); }
        });
    });

    $(document).on('click', '.plgc-restore-doc', function () {
        var btn = $(this);
        var id = btn.data('id');
        btn.text('Restoring...').prop('disabled', true);
        $.post(plgcDocMgr.ajaxUrl, {
            action: 'plgc_docmgr_restore',
            attachment_id: id,
            _ajax_nonce: plgcDocMgr.nonce
        }, function (resp) {
            if (resp.success) location.reload();
            else { btn.text('Error').prop('disabled', false); alert(resp.data); }
        });
    });

    // ============================================================
    // CLARITY API — Test Connection
    // ============================================================
    $('#plgc-clarity-test').on('click', function () {
        var result = $('#plgc-clarity-test-result');
        result.text('Testing...').css('color', '#666');
        $.post(plgcDocMgr.ajaxUrl, {
            action: 'plgc_clarity_test_connection',
            _ajax_nonce: plgcDocMgr.nonce
        }, function (resp) {
            if (resp.success) {
                result.text('✅ ' + resp.data).css('color', '#567915');
            } else {
                result.text('❌ ' + resp.data).css('color', '#d63638');
            }
        });
    });

    // ============================================================
    // CLARITY API — Scan, Refresh, Report, Allyant
    // ============================================================

    // Auto-poll for scan completion
    function pollClarityStatus(id, $container, attempts) {
        attempts = attempts || 0;
        if (attempts > 20) {
            $container.html(
                '<span style="color: #FFAE40;">⚠ Scan is taking longer than expected.</span> ' +
                '<button type="button" class="button button-small plgc-clarity-refresh" data-id="' + id + '">Check Again</button>'
            );
            return;
        }

        $.post(plgcDocMgr.ajaxUrl, {
            action: 'plgc_clarity_refresh',
            attachment_id: id,
            _ajax_nonce: plgcDocMgr.nonce
        }, function (resp) {
            if (resp.success) {
                var s = resp.data.status;
                if (s === 'Completed' || resp.data.result === 'pass' || resp.data.result === 'fail') {
                    location.reload();
                } else if (s === 'error') {
                    $container.html(
                        '<span style="color: #d63638;">❌ ' + (resp.data.error || 'Scan failed') + '</span> ' +
                        '<button type="button" class="button button-small plgc-clarity-scan" data-id="' + id + '">Retry</button>'
                    );
                } else {
                    $container.html('<span style="color: #FFAE40;">⏳ Scanning... (check ' + (attempts + 1) + ')</span>');
                    setTimeout(function () { pollClarityStatus(id, $container, attempts + 1); }, 15000);
                }
            }
        }).fail(function () {
            setTimeout(function () { pollClarityStatus(id, $container, attempts + 1); }, 15000);
        });
    }

    $(document).on('click', '.plgc-clarity-scan', function (e) {
        e.preventDefault();
        var btn = $(this), id = btn.data('id');
        var $container = btn.closest('.plgc-clarity-status');
        btn.text('Submitting...').prop('disabled', true);
        $.post(plgcDocMgr.ajaxUrl, {
            action: 'plgc_clarity_scan',
            attachment_id: id,
            _ajax_nonce: plgcDocMgr.nonce
        }, function (resp) {
            if (resp.success) {
                $container.html('<span style="color: #FFAE40;">⏳ Scanning... (auto-checking)</span>');
                setTimeout(function () { pollClarityStatus(id, $container, 0); }, 15000);
            } else {
                btn.text('❌ ' + resp.data).prop('disabled', false);
            }
        });
    });

    $(document).on('click', '.plgc-clarity-refresh', function (e) {
        e.preventDefault();
        var btn = $(this), id = btn.data('id');
        var $container = btn.closest('.plgc-clarity-status');
        btn.text('Checking...');
        pollClarityStatus(id, $container, 0);
    });

    $(document).on('click', '.plgc-clarity-report', function (e) {
        e.preventDefault();
        var id = $(this).data('id');
        window.open(plgcDocMgr.ajaxUrl + '?action=plgc_clarity_report&attachment_id=' + id + '&_ajax_nonce=' + plgcDocMgr.nonce, '_blank');
    });

    $(document).on('click', '.plgc-clarity-allyant', function (e) {
        e.preventDefault();
        var btn = $(this), id = btn.data('id');
        var isResend = btn.text().trim() === 'Resend';
        var msg = isResend
            ? 'This document was already sent to Allyant. Send again?'
            : 'Send this document to Allyant for remediation?';
        if (!confirm(msg)) return;
        btn.text('Sending...').prop('disabled', true);
        $.post(plgcDocMgr.ajaxUrl, {
            action: 'plgc_clarity_send_allyant',
            attachment_id: id,
            _ajax_nonce: plgcDocMgr.nonce
        }, function (resp) {
            if (resp.success) {
                location.reload();
            } else {
                btn.text('❌ ' + resp.data).prop('disabled', false);
            }
        });
    });

    // ============================================================
    // VERSION CONTROL — AJAX Autocomplete Search
    // ============================================================
    var versionSearchTimer = null;

    $(document).on('keyup', '.plgc-version-search', function () {
        var input = $(this);
        var id = input.data('attachment-id');
        var mime = input.data('mime');
        var term = input.val().trim();
        var $results = $('#plgc_version_results_' + id);

        clearTimeout(versionSearchTimer);

        if (term.length < 2) {
            $results.hide().empty();
            return;
        }

        versionSearchTimer = setTimeout(function () {
            $.post(plgcDocMgr.ajaxUrl, {
                action: 'plgc_docmgr_version_search',
                search: term,
                mime: mime,
                exclude: id,
                _ajax_nonce: plgcDocMgr.nonce
            }, function (resp) {
                if (!resp.success || !resp.data.length) {
                    $results.html('<div style="padding:8px 10px;color:#999;font-size:13px;">No matching documents found.</div>').show();
                    return;
                }

                var html = '';
                resp.data.forEach(function (doc) {
                    html += '<div class="plgc-version-result" data-doc-id="' + doc.id + '" data-doc-title="' + doc.title.replace(/"/g, '&quot;') + '" style="padding:8px 10px;cursor:pointer;border-bottom:1px solid #eee;font-size:13px;">';
                    html += '<strong>' + doc.title + '</strong>';
                    html += '<br><span style="color:#666;font-size:12px;">' + doc.date;
                    if (doc.category) html += ' · ' + doc.category;
                    html += ' · ' + doc.filename + '</span>';
                    html += '</div>';
                });
                $results.html(html).show();
            });
        }, 300);
    });

    // Hover highlight
    $(document).on('mouseenter', '.plgc-version-result', function () {
        $(this).css('background', '#f0f6e4');
    }).on('mouseleave', '.plgc-version-result', function () {
        $(this).css('background', '#fff');
    });

    // Select a result
    $(document).on('click', '.plgc-version-result', function () {
        var docId = $(this).data('doc-id');
        var docTitle = $(this).data('doc-title');
        var $wrap = $(this).closest('.plgc-version-search-wrap');
        var attachId = $wrap.find('.plgc-version-search').data('attachment-id');

        // Set hidden input
        $('#plgc_replaces_id_' + attachId).val(docId);

        // Update display
        $wrap.find('.plgc-version-search').val('').hide();
        $wrap.find('.plgc-version-results').hide().empty();
        $('#plgc_version_selected_' + attachId).html(
            '⬇️ Replaces: <strong>' + docTitle + '</strong> ' +
            '<button type="button" class="button button-small plgc-version-clear" data-attachment-id="' + attachId + '" style="margin-left:8px;">✕ Clear</button>'
        ).show();
    });

    // Clear selection
    $(document).on('click', '.plgc-version-clear', function () {
        var attachId = $(this).data('attachment-id');
        $('#plgc_replaces_id_' + attachId).val('');
        $('#plgc_version_selected_' + attachId).hide().empty();
        var $wrap = $(this).closest('.plgc-version-search-wrap');
        $wrap.find('.plgc-version-search').val('').show().focus();
    });

    // Close results on outside click
    $(document).on('click', function (e) {
        if (!$(e.target).closest('.plgc-version-search-wrap').length) {
            $('.plgc-version-results').hide();
        }
    });

    // ============================================================
    // DECORATIVE IMAGE TOGGLE
    // ============================================================
    $(document).on('change', '.plgc-decorative-toggle', function () {
        var $field = $(this).closest('[data-setting="plgc_decorative"], .compat-item, td');
        var isDecorative = $(this).is(':checked');
        var id = $(this).data('attachment-id');

        // Update inline compliance indicator
        var $indicator = $(this).closest('label').next('br').next('span');
        if (!$indicator.length) {
            $indicator = $(this).parent().find('span[style*="font-weight"]');
        }

        if (isDecorative) {
            // Find and clear alt text field for this attachment
            var $altInput = $('input[name="attachments[' + id + '][image_alt]"], textarea[name="attachments[' + id + '][image_alt]"]');
            if ($altInput.length) {
                $altInput.val('').prop('disabled', true).css('opacity', '0.5');
            }
        } else {
            var $altInput = $('input[name="attachments[' + id + '][image_alt]"], textarea[name="attachments[' + id + '][image_alt]"]');
            if ($altInput.length) {
                $altInput.prop('disabled', false).css('opacity', '1');
            }
        }
    });

    // Initialize decorative state on page load
    $('.plgc-decorative-toggle:checked').each(function () {
        var id = $(this).data('attachment-id');
        var $altInput = $('input[name="attachments[' + id + '][image_alt]"], textarea[name="attachments[' + id + '][image_alt]"]');
        if ($altInput.length) {
            $altInput.prop('disabled', true).css('opacity', '0.5');
        }
    });

})(jQuery);
