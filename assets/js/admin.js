/**
 * Mail SMTP by Oi - Admin JavaScript
 * Author: Oi Technologies
 */

(function ($) {
    'use strict';

    $(document).ready(function () {

        // Floating Toast Notification Helper
        function showToast(msg, isSuccess) {
            var $toast = $('#oi-toast');
            if (!$toast.length) return;
            var $icon = $toast.find('.oi-toast-icon');
            var $msg = $toast.find('.oi-toast-msg');

            $icon.html(isSuccess ? '&#10004;' : '&#10008;')
                 .css('background', isSuccess ? '#10b981' : '#ef4444');
            $msg.text(msg);

            $toast.stop(true, true).fadeIn(250);

            setTimeout(function () {
                $toast.fadeOut(400);
            }, 3500);
        }

        // 1. Mailer Provider Card Selection & Dynamic Fields
        $('.oi-mailer-card').on('click', function () {
            $('.oi-mailer-card').removeClass('oi-mailer-selected');
            $(this).addClass('oi-mailer-selected');

            var $radio = $(this).find('input[type="radio"]');
            $radio.prop('checked', true);
            var mailer = $radio.val();

            // Preset SMTP hosts if Gmail or Outlook selected
            if (mailer === 'gmail') {
                $('#oi_smtp_host').val('smtp.gmail.com');
                $('#oi_smtp_port').val('587');
                $('input[name="smtp_encryption"][value="tls"]').prop('checked', true);
            } else if (mailer === 'outlook') {
                $('#oi_smtp_host').val('smtp.office365.com');
                $('#oi_smtp_port').val('587');
                $('input[name="smtp_encryption"][value="tls"]').prop('checked', true);
            }

            // Toggle conditional sections
            if (['smtp', 'gmail', 'outlook'].indexOf(mailer) !== -1) {
                $('#oi-smtp-fields').slideDown(200);
            } else {
                $('#oi-smtp-fields').slideUp(200);
            }

            var apiProviders = ['sendgrid', 'brevo', 'postmark', 'resend', 'smtp2go'];
            if (apiProviders.indexOf(mailer) !== -1) {
                $('#oi-api-fields').slideDown(200);
                $('.oi-api-provider-field').hide();
                $('.oi-api-provider-field[data-api-provider="' + mailer + '"]').show();
            } else {
                $('#oi-api-fields').slideUp(200);
            }

            if (mailer === 'mailgun') {
                $('#oi-mailgun-fields').slideDown(200);
            } else {
                $('#oi-mailgun-fields').slideUp(200);
            }

            if (mailer === 'ses') {
                $('#oi-ses-fields').slideDown(200);
            } else {
                $('#oi-ses-fields').slideUp(200);
            }
        });

        // Toggle Failure Alert Channels Box
        $('#oi_enable_failure_alerts').on('change', function () {
            if ($(this).is(':checked')) {
                $('#oi-alert-channels-box').slideDown(200);
            } else {
                $('#oi-alert-channels-box').slideUp(200);
            }
        });

        // Test Failure Alert Notification Button
        $('#oi-test-alert-btn').on('click', function () {
            var $btn = $(this);
            var channel = $('#oi_test_channel_select').val();
            var $status = $('#oi-test-alert-status');

            $btn.prop('disabled', true);
            $status.css('color', '#64748b').text('Sending test notification...');

            $.post(oiMailSmtp.ajax_url, {
                action: 'oi_test_alert_notification',
                channel: channel,
                nonce: oiMailSmtp.nonce
            }, function (res) {
                $btn.prop('disabled', false);
                if (res.success) {
                    $status.css('color', '#10b981').text('✔ ' + (res.data ? res.data.message : 'Notification sent!'));
                } else {
                    $status.css('color', '#ef4444').text('✖ ' + (res.data ? res.data.message : 'Failed to send.'));
                }
            }).fail(function () {
                $btn.prop('disabled', false);
                $status.css('color', '#ef4444').text('✖ AJAX network error.');
            });
        });

        // Password & API Key Visibility Toggle
        $('.oi-toggle-password-btn').on('click', function () {
            var $input = $(this).siblings('.oi-input');
            var $icon = $(this).find('.dashicons');
            if ($input.attr('type') === 'password') {
                $input.attr('type', 'text');
                $icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
            } else {
                $input.attr('type', 'password');
                $icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
            }
        });

        // 2. AJAX Save Settings
        $('#oi-settings-form').on('submit', function (e) {
            e.preventDefault();

            var $btn = $('#oi-save-btn');
            var $status = $('#oi-save-status');

            $btn.prop('disabled', true);
            $status.html('<span class="spinner is-active" style="float:none; margin:0 8px;"></span> ' + oiMailSmtp.i18n.saving).css('color', '#475569');

            var formData = $(this).serialize();

            $.post(oiMailSmtp.ajax_url, {
                action: 'oi_save_settings',
                nonce: oiMailSmtp.nonce,
                data: formData
            }, function (response) {
                $btn.prop('disabled', false);
                if (response.success) {
                    var successMsg = response.data.message || oiMailSmtp.i18n.saved;
                    $status.html('&#10004; ' + successMsg).css('color', '#10b981');
                    showToast(successMsg, true);
                    setTimeout(function () {
                        $status.fadeOut(300, function () {
                            $(this).html('').show();
                        });
                    }, 4000);
                } else {
                    var errMsg = response.data ? response.data.message : 'Error';
                    $status.html('&#10008; ' + errMsg).css('color', '#ef4444');
                    showToast(errMsg, false);
                }
            }).fail(function () {
                $btn.prop('disabled', false);
                $status.html('&#10008; Network error').css('color', '#ef4444');
                showToast('Network error while saving settings.', false);
            });
        });

        // 3. AJAX Send Test Email
        $('#oi-test-email-form').on('submit', function (e) {
            e.preventDefault();

            var to = $('#oi_test_to').val();
            var subject = $('#oi_test_subject').val();
            var message = $('#oi_test_message').val();

            var $btn = $('#oi-send-test-btn');
            var $spinner = $('#oi-test-spinner');
            var $resultBox = $('#oi-test-result');
            var $alert = $('#oi-test-alert');
            var $terminal = $('#oi-terminal-body');

            $btn.prop('disabled', true);
            $spinner.show();
            $resultBox.slideUp(150);

            $.post(oiMailSmtp.ajax_url, {
                action: 'oi_send_test_email',
                nonce: oiMailSmtp.nonce,
                to: to,
                subject: subject,
                message: message
            }, function (response) {
                $btn.prop('disabled', false);
                $spinner.hide();

                $alert.removeClass('oi-alert-success oi-alert-danger');

                if (response.success) {
                    $alert.addClass('oi-alert-success').html('<strong>&#10004; ' + oiMailSmtp.i18n.sent + '</strong> (' + response.data.time_ms + ' ms)<br>' + response.data.message);
                    showToast(oiMailSmtp.i18n.sent + ' (' + response.data.time_ms + ' ms)', true);
                } else {
                    $alert.addClass('oi-alert-danger').html('<strong>&#10008; ' + oiMailSmtp.i18n.failed + '</strong><br>' + (response.data ? response.data.message : 'Unknown error'));
                    showToast(oiMailSmtp.i18n.failed, false);
                }

                $terminal.text(response.data && response.data.debug ? response.data.debug : 'No debug output captured.');
                $resultBox.slideDown(250);
            }).fail(function () {
                $btn.prop('disabled', false);
                $spinner.hide();
                $alert.addClass('oi-alert-danger').text('Network request failed. Please check server error logs.');
                $resultBox.slideDown(250);
            });
        });

        // Copy Debug Log
        $('#oi-copy-debug-btn').on('click', function () {
            var text = $('#oi-terminal-body').text();
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(function () {
                    showToast(oiMailSmtp.i18n.copy_success, true);
                });
            } else {
                var $temp = $('<textarea>');
                $('body').append($temp);
                $temp.val(text).select();
                document.execCommand('copy');
                $temp.remove();
                showToast(oiMailSmtp.i18n.copy_success, true);
            }
        });

        // 4. Outbound Port Diagnostic Scanner
        $('#oi-run-port-test-btn').on('click', function () {
            var host = $('#oi_diag_host').val() || 'smtp.gmail.com';
            var ports = [587, 465, 2525, 25];

            $('.oi-port-card').each(function () {
                var $badge = $(this).find('.oi-port-badge');
                $badge.removeClass('oi-badge-idle oi-badge-open oi-badge-closed')
                      .addClass('oi-badge-idle')
                      .text(oiMailSmtp.i18n.testing_port);
            });

            ports.forEach(function (port) {
                var $card = $('.oi-port-card[data-port="' + port + '"]');
                var $badge = $card.find('.oi-port-badge');

                $.post(oiMailSmtp.ajax_url, {
                    action: 'oi_test_port',
                    nonce: oiMailSmtp.nonce,
                    host: host,
                    port: port
                }, function (response) {
                    if (response.success && response.data) {
                        $badge.removeClass('oi-badge-idle');
                        if (response.data.status === 'open') {
                            $badge.addClass('oi-badge-open').text('OPEN (' + response.data.time_ms + ' ms)');
                        } else {
                            $badge.addClass('oi-badge-closed').text('BLOCKED');
                        }
                    }
                });
            });
        });

        // 5. Email Logs Handlers
        // Select All Checkbox
        $('#oi-select-all').on('change', function () {
            $('.oi-log-cb').prop('checked', $(this).prop('checked'));
        });

        // Modal Tab Switching
        $('.oi-modal-tab').on('click', function () {
            $('.oi-modal-tab').removeClass('active');
            $(this).addClass('active');
            var tab = $(this).data('tab');
            $('.oi-modal-pane').removeClass('active');
            $('#oi-tab-' + tab).addClass('active');
        });

        // View Log Details Modal
        $('.oi-view-log-btn').on('click', function () {
            var id = $(this).data('id');
            var $modal = $('#oi-email-modal');

            // Reset tabs
            $('.oi-modal-tab').removeClass('active');
            $('.oi-modal-tab[data-tab="preview"]').addClass('active');
            $('.oi-modal-pane').removeClass('active');
            $('#oi-tab-preview').addClass('active');

            $modal.fadeIn(150);
            $modal.data('log-id', id);

            // Clear previous content
            var iframe = document.getElementById('oi-modal-iframe');
            if (iframe) {
                iframe.srcdoc = '<p style="font-family:sans-serif; color:#94a3b8; padding:20px;">Loading preview...</p>';
            }
            $('#oi-modal-raw-body').text('Loading...');
            $('#oi-modal-meta-box').html('Loading details...');
            $('#oi-modal-debug-log').text('Loading debug trace...');
            $('#oi-modal-attachments-list').html('<p style="color:#94a3b8; padding:10px 0;">Loading attachments...</p>');
            $('#oi-modal-attachments-badge').hide();
            $('#oi-modal-forward-status').text('');
            $('#oi-modal-forward-to').val('');

            $.post(oiMailSmtp.ajax_url, {
                action: 'oi_get_log_details',
                nonce: oiMailSmtp.nonce,
                id: id
            }, function (response) {
                if (response.success && response.data.log) {
                    var log = response.data.log;
                    $('#oi-modal-subject').text(log.subject);
                    $('#oi-modal-forward-to').val(log.raw_to || log.to);

                    // 1. HTML Preview inside sandboxed iframe
                    if (iframe) {
                        iframe.srcdoc = log.message || '<p style="color:#94a3b8; font-family:sans-serif; padding:20px;">(Empty body or logging disabled)</p>';
                    }

                    // 2. Raw Text
                    $('#oi-modal-raw-body').text(log.message || '(Empty)');

                    // 3. Headers & Meta
                    var metaHtml = '<div><strong>To:</strong> ' + log.to + '</div>';
                    metaHtml += '<div><strong>Source:</strong> <span class="oi-source-badge">' + (log.source || 'WordPress Core') + '</span></div>';
                    metaHtml += '<div><strong>Status:</strong> ' + (log.status === 'sent' ? '<span class="oi-badge oi-badge-sent">Sent</span>' : '<span class="oi-badge oi-badge-failed">Failed</span>') + '</div>';
                    metaHtml += '<div><strong>Mailer:</strong> <code>' + log.mailer + '</code></div>';
                    metaHtml += '<div><strong>Date:</strong> ' + log.date + '</div>';

                    if (log.open_count !== undefined) {
                        var trackingText = log.open_count > 0
                            ? '<span class="oi-badge oi-badge-sent">' + log.open_count + ' Open' + (log.open_count > 1 ? 's' : '') + '</span>' + (log.last_opened_at ? ' <span style="font-size:11px; color:#64748b;">(Last: ' + log.last_opened_at + ')</span>' : '')
                            : '<span style="color:#94a3b8; font-size:12px;">Unopened</span>';

                        if (log.click_count > 0) {
                            trackingText += ' &nbsp; <span class="oi-badge" style="background:#e0e7ff; color:#3730a3;">' + log.click_count + ' Click' + (log.click_count > 1 ? 's' : '') + '</span>' + (log.last_clicked_at ? ' <span style="font-size:11px; color:#64748b;">(Last: ' + log.last_clicked_at + ')</span>' : '');
                        }

                        metaHtml += '<div style="margin-top:6px;"><strong>Engagement:</strong> ' + trackingText + '</div>';
                    }

                    if (log.attachments) {
                        metaHtml += '<div><strong>Attachments:</strong> ' + log.attachments + '</div>';
                    }
                    if (log.error_message) {
                        metaHtml += '<div style="color:#ef4444; margin-top:8px;"><strong>Error:</strong> ' + log.error_message + '</div>';
                    }
                    if (log.headers) {
                        metaHtml += '<div style="margin-top:10px;"><strong>Headers:</strong><pre style="margin-top:4px; font-size:12px; background:#fff; padding:8px; border-radius:6px; border:1px solid #e2e8f0;">' + log.headers + '</pre></div>';
                    }
                    $('#oi-modal-meta-box').html(metaHtml);

                    // 4. Attachments Vault List
                    if (log.vault_attachments && log.vault_attachments.length > 0) {
                        $('#oi-modal-attachments-badge').text(log.vault_attachments.length).show();
                        var attHtml = '<div style="display:flex; flex-direction:column; gap:8px;">';
                        log.vault_attachments.forEach(function (att) {
                            attHtml += '<div style="display:flex; justify-content:space-between; align-items:center; background:#f8fafc; border:1px solid #e2e8f0; padding:10px 14px; border-radius:6px;">' +
                                '<div style="display:flex; align-items:center; gap:8px;">' +
                                    '<span class="dashicons dashicons-media-default" style="color:#4f46e5;"></span>' +
                                    '<strong>' + att.filename + '</strong>' +
                                    '<span style="font-size:12px; color:#64748b;">(' + att.size + ')</span>' +
                                '</div>' +
                                '<a href="' + att.url + '" class="button button-small" download>' +
                                    '<span class="dashicons dashicons-download" style="vertical-align:middle; margin-right:2px;"></span> Download' +
                                '</a>' +
                            '</div>';
                        });
                        attHtml += '</div>';
                        $('#oi-modal-attachments-list').html(attHtml);
                    } else {
                        $('#oi-modal-attachments-list').html('<p style="color:#94a3b8; font-style:italic; padding:10px 0;">No archived attachments found for this email.</p>');
                    }

                    // 5. Debug Trace
                    $('#oi-modal-debug-log').text(log.debug_log || 'No debug trace recorded.');
                } else {
                    alert('Failed to load email log details.');
                }
            });
        });

        // Close modal
        $('#oi-modal-close-btn, #oi-email-modal').on('click', function (e) {
            if (e.target === this || $(this).is('#oi-modal-close-btn')) {
                $('#oi-email-modal').fadeOut(150);
            }
        });

        $(document).on('keydown', function (e) {
            if (e.key === 'Escape' && $('#oi-email-modal').is(':visible')) {
                $('#oi-email-modal').fadeOut(150);
            }
        });

        // Resend or Forward from Inside Modal
        $('#oi-modal-forward-btn').on('click', function () {
            var $btn = $(this);
            var id = $('#oi-email-modal').data('log-id');
            var to = $('#oi-modal-forward-to').val();
            var $status = $('#oi-modal-forward-status');

            if (!to) {
                alert('Please enter a recipient email address.');
                return;
            }

            $btn.prop('disabled', true);
            $status.css('color', '#64748b').text('Sending email...');

            $.post(oiMailSmtp.ajax_url, {
                action: 'oi_resend_email',
                nonce: oiMailSmtp.nonce,
                id: id,
                to: to
            }, function (response) {
                $btn.prop('disabled', false);
                if (response.success) {
                    $status.css('color', '#10b981').text('✔ ' + response.data.message);
                    setTimeout(function () {
                        window.location.reload();
                    }, 1200);
                } else {
                    $status.css('color', '#ef4444').text('✖ ' + (response.data ? response.data.message : 'Failed to send.'));
                }
            }).fail(function () {
                $btn.prop('disabled', false);
                $status.css('color', '#ef4444').text('✖ Network request failed.');
            });
        });

        // Resend Email
        $('.oi-resend-btn').on('click', function () {
            var id = $(this).data('id');
            var $btn = $(this);

            if (!confirm('Resend this email now?')) {
                return;
            }

            $btn.prop('disabled', true).text('Resending...');

            $.post(oiMailSmtp.ajax_url, {
                action: 'oi_resend_email',
                nonce: oiMailSmtp.nonce,
                id: id
            }, function (response) {
                $btn.prop('disabled', false).text('Resend');
                if (response.success) {
                    alert(response.data.message);
                    window.location.reload();
                } else {
                    alert(response.data ? response.data.message : 'Failed to resend');
                }
            });
        });

        // Delete Single Log
        $('.oi-delete-log-btn').on('click', function () {
            var id = $(this).data('id');
            var $row = $('#oi-log-row-' + id);

            if (!confirm(oiMailSmtp.i18n.confirm_delete)) {
                return;
            }

            $.post(oiMailSmtp.ajax_url, {
                action: 'oi_delete_log',
                nonce: oiMailSmtp.nonce,
                id: id
            }, function (response) {
                if (response.success) {
                    showToast('Log entry deleted.', true);
                    $row.fadeOut(200, function () {
                        $(this).remove();
                    });
                } else {
                    showToast('Failed to delete log entry.', false);
                }
            });
        });

        // Bulk Delete Logs
        $('#oi-bulk-delete-btn').on('click', function () {
            var selected = [];
            $('.oi-log-cb:checked').each(function () {
                selected.push($(this).val());
            });

            if (selected.length === 0) {
                alert('Please select at least one log to delete.');
                return;
            }

            if (!confirm(oiMailSmtp.i18n.confirm_bulk)) {
                return;
            }

            $.post(oiMailSmtp.ajax_url, {
                action: 'oi_delete_bulk_logs',
                nonce: oiMailSmtp.nonce,
                ids: selected
            }, function (response) {
                if (response.success) {
                    selected.forEach(function (id) {
                        $('#oi-log-row-' + id).fadeOut(200, function () {
                            $(this).remove();
                        });
                    });
                }
            });
        });

        // Clear All Logs
        $('#oi-clear-logs-btn').on('click', function () {
            if (!confirm(oiMailSmtp.i18n.confirm_clear_all)) {
                return;
            }

            $.post(oiMailSmtp.ajax_url, {
                action: 'oi_clear_all_logs',
                nonce: oiMailSmtp.nonce
            }, function (response) {
                if (response.success) {
                    window.location.reload();
                }
            });
        });

        // 1-Click Migration / Import from other plugins
        $('.oi-btn-import').on('click', function () {
            var plugin = $(this).data('plugin');
            var $btn = $(this);

            if (!confirm('Import settings from this plugin into Mail SMTP by Oi? Existing settings will be updated.')) {
                return;
            }

            $btn.prop('disabled', true).text('Importing...');

            $.post(oiMailSmtp.ajax_url, {
                action: 'oi_import_settings',
                nonce: oiMailSmtp.nonce,
                plugin: plugin
            }, function (response) {
                if (response.success) {
                    alert(response.data.message);
                    window.location.reload();
                } else {
                    alert(response.data ? response.data.message : 'Import failed');
                    $btn.prop('disabled', false).text('Import');
                }
            }).fail(function () {
                alert('Request failed');
                $btn.prop('disabled', false).text('Import');
            });
        });

        // Dismiss Failure Alert notice
        $(document).on('click', '.oi-failure-notice .notice-dismiss', function () {
            var nonce = $(this).closest('.oi-failure-notice').data('nonce') || oiMailSmtp.nonce;
            $.post(oiMailSmtp.ajax_url, {
                action: 'oi_dismiss_failure_alert',
                nonce: nonce
            });
        });

        // Weekly Executive Digest Options Toggle
        $('#oi_enable_weekly_digest').on('change', function () {
            if ($(this).is(':checked')) {
                $('#oi-weekly-digest-options').slideDown(200);
            } else {
                $('#oi-weekly-digest-options').slideUp(200);
            }
        });

        // Developer Simulation / Staging Mode Toggle
        $('#oi_enable_simulation_mode').on('change', function () {
            if ($(this).is(':checked')) {
                $('#oi-simulation-options').slideDown(200);
            } else {
                $('#oi-simulation-options').slideUp(200);
            }
        });

        // Weekly Digest Test Button
        $('#oi-test-digest-btn').on('click', function () {
            var $btn = $(this);
            var recipient = $('#oi_digest_recipient_email').val();
            var $status = $('#oi-test-digest-status');

            $btn.prop('disabled', true);
            $status.css('color', '#64748b').text('Sending test digest...');

            $.post(oiMailSmtp.ajax_url, {
                action: 'oi_send_test_digest',
                recipient: recipient,
                nonce: oiMailSmtp.nonce
            }, function (res) {
                $btn.prop('disabled', false);
                if (res.success) {
                    $status.css('color', '#10b981').text('✔ ' + (res.data ? res.data.message : 'Digest sent!'));
                } else {
                    $status.css('color', '#ef4444').text('✖ ' + (res.data ? res.data.message : 'Failed to send.'));
                }
            }).fail(function () {
                $btn.prop('disabled', false);
                $status.css('color', '#ef4444').text('✖ Network request failed.');
            });
        });

        // Smart Routing Rules Builder: Add & Remove Rule Rows
        var nextRuleIndex = $('#oi-routing-rules-body tr').length;
        $('#oi-add-route-rule-btn').on('click', function () {
            var mailerOptions = '<option value="ses">Amazon SES</option>' +
                '<option value="smtp">Custom SMTP</option>' +
                '<option value="sendgrid">SendGrid</option>' +
                '<option value="mailgun">Mailgun</option>' +
                '<option value="brevo">Brevo</option>' +
                '<option value="postmark">Postmark</option>' +
                '<option value="resend">Resend</option>' +
                '<option value="gmail">Gmail / Workspace</option>' +
                '<option value="outlook">Microsoft 365</option>' +
                '<option value="default">Default PHP Mail</option>';

            var rowHtml = '<tr class="oi-route-rule-row">' +
                '<td><select class="oi-select" name="routing_rules[' + nextRuleIndex + '][field]">' +
                '<option value="subject">Subject</option>' +
                '<option value="to_email">To Email</option>' +
                '<option value="from_email">From Email</option>' +
                '<option value="headers">Headers</option>' +
                '</select></td>' +
                '<td><select class="oi-select" name="routing_rules[' + nextRuleIndex + '][operator]">' +
                '<option value="contains">Contains</option>' +
                '<option value="equals">Equals</option>' +
                '<option value="starts_with">Starts With</option>' +
                '<option value="ends_with">Ends With</option>' +
                '</select></td>' +
                '<td><input type="text" class="oi-input" name="routing_rules[' + nextRuleIndex + '][value]" placeholder="e.g. [Order #] or @vipcustomer.com"></td>' +
                '<td><select class="oi-select" name="routing_rules[' + nextRuleIndex + '][mailer]">' + mailerOptions + '</select></td>' +
                '<td style="text-align:center;"><button type="button" class="button oi-btn-delete oi-remove-route-rule-btn" title="Remove Rule">&times;</button></td>' +
                '</tr>';

            $('#oi-routing-rules-body').append(rowHtml);
            $('#oi-routing-rules-table').show();
            $('#oi-no-routing-rules').hide();
            nextRuleIndex++;
        });

        $(document).on('click', '.oi-remove-route-rule-btn', function () {
            $(this).closest('tr').remove();
            if ($('#oi-routing-rules-body tr').length === 0) {
                $('#oi-routing-rules-table').hide();
                $('#oi-no-routing-rules').show();
            }
        });

    });

})(jQuery);
