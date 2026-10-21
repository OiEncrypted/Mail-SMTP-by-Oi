/**
 * Mail SMTP by Oi - Admin JavaScript
 * Author: Oi Technologies
 */

(function ($) {
    'use strict';

    $(document).ready(function () {

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

            if (['sendgrid', 'brevo', 'postmark', 'resend'].indexOf(mailer) !== -1) {
                $('#oi-api-fields').slideDown(200);
            } else {
                $('#oi-api-fields').slideUp(200);
            }

            if (mailer === 'mailgun') {
                $('#oi-mailgun-fields').slideDown(200);
            } else {
                $('#oi-mailgun-fields').slideUp(200);
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
                    $status.html('&#10004; ' + (response.data.message || oiMailSmtp.i18n.saved)).css('color', '#10b981');
                    setTimeout(function () {
                        $status.fadeOut(300, function () {
                            $(this).html('').show();
                        });
                    }, 4000);
                } else {
                    $status.html('&#10008; ' + (response.data.message || 'Error')).css('color', '#ef4444');
                }
            }).fail(function () {
                $btn.prop('disabled', false);
                $status.html('&#10008; Network error').css('color', '#ef4444');
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
                } else {
                    $alert.addClass('oi-alert-danger').html('<strong>&#10008; ' + oiMailSmtp.i18n.failed + '</strong><br>' + (response.data ? response.data.message : 'Unknown error'));
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
                    alert(oiMailSmtp.i18n.copy_success);
                });
            } else {
                var $temp = $('<textarea>');
                $('body').append($temp);
                $temp.val(text).select();
                document.execCommand('copy');
                $temp.remove();
                alert(oiMailSmtp.i18n.copy_success);
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

        // View Log Details Modal
        $('.oi-view-log-btn').on('click', function () {
            var id = $(this).data('id');
            var $modal = $('#oi-email-modal');
            var $content = $('#oi-modal-content');

            $modal.fadeIn(150);
            $content.html('<div class="oi-modal-loading">Loading details...</div>');

            $.post(oiMailSmtp.ajax_url, {
                action: 'oi_get_log_details',
                nonce: oiMailSmtp.nonce,
                id: id
            }, function (response) {
                if (response.success && response.data.log) {
                    var log = response.data.log;
                    $('#oi-modal-subject').text(log.subject);

                    var html = '<div class="oi-modal-meta">';
                    html += '<div><strong>To:</strong> ' + log.to + '</div>';
                    html += '<div><strong>Status:</strong> ' + (log.status === 'sent' ? '<span class="oi-badge oi-badge-sent">Sent</span>' : '<span class="oi-badge oi-badge-failed">Failed</span>') + '</div>';
                    html += '<div><strong>Mailer:</strong> <code>' + log.mailer + '</code></div>';
                    html += '<div><strong>Date:</strong> ' + log.date + '</div>';
                    if (log.attachments) {
                        html += '<div><strong>Attachments:</strong> ' + log.attachments + '</div>';
                    }
                    if (log.error_message) {
                        html += '<div style="color:#ef4444; margin-top:6px;"><strong>Error:</strong> ' + log.error_message + '</div>';
                    }
                    html += '</div>';

                    html += '<div class="oi-label">Message Content:</div>';
                    html += '<div class="oi-modal-body-preview">' + log.message + '</div>';

                    if (log.debug_log) {
                        html += '<div class="oi-label" style="margin-top:16px;">Debug Trace:</div>';
                        html += '<pre class="oi-terminal-body" style="border-radius:8px; max-height:200px;">' + log.debug_log + '</pre>';
                    }

                    $content.html(html);
                } else {
                    $content.html('<p style="color:#ef4444;">Failed to load email log details.</p>');
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
                    $row.fadeOut(200, function () {
                        $(this).remove();
                    });
                } else {
                    alert('Failed to delete log entry.');
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

    });

})(jQuery);
