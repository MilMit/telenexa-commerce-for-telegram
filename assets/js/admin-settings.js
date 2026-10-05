jQuery(document).ready(function($) {
    // Tab switching
    function activateTab(tabId) {
        $('.milmit-tab-btn').removeClass('is-active').attr('aria-selected', 'false');
        $('.milmit-tab-btn[data-tab="' + tabId + '"]').addClass('is-active').attr('aria-selected', 'true');
        
        $('.milmit-tab-pane').removeClass('is-active');
        $('#tab-' + tabId).addClass('is-active');

        // Update URL hash without scroll
        if (history.pushState) {
            history.pushState(null, null, '#tab=' + tabId);
        } else {
            location.hash = '#tab=' + tabId;
        }
    }

    $('.milmit-tab-btn').on('click', function(e) {
        e.preventDefault();
        var targetTab = $(this).data('tab');
        activateTab(targetTab);
    });

    // Required membership destinations repeater
    function refreshMembershipRows() {
        var $rows = $('[data-membership-row]');
        $rows.each(function(index) {
            $(this).find('.milmit-membership-number').text(index + 1);
            $(this).find('.milmit-remove-membership').toggle($rows.length > 1);
        });
    }
    $('#woogram_add_membership').on('click', function(e) {
        e.preventDefault();
        var $rows = $('[data-membership-row]');
        if ($rows.length >= 10) return;
        var index = $rows.length;
        var $row = $rows.first().clone();
        $row.find('input, select').each(function() {
            var $field = $(this);
            $field.attr('name', $field.attr('name').replace(/\[membership_requirements\]\[\d+\]/, '[membership_requirements][' + index + ']'));
            if ($field.is(':checkbox')) $field.prop('checked', false);
            else if ($field.is('select')) $field.val('channel');
            else $field.val('');
        });
        $('#woogram_membership_list').append($row);
        refreshMembershipRows();
    });
    $(document).on('click', '.milmit-remove-membership', function() {
        $(this).closest('[data-membership-row]').remove();
        refreshMembershipRows();
    });
    refreshMembershipRows();

    // Check hash on load
    if (window.location.hash) {
        var hashMatch = window.location.hash.match(/tab=([a-z0-9_-]+)/i);
        if (hashMatch && hashMatch[1] && $('#tab-' + hashMatch[1]).length) {
            activateTab(hashMatch[1]);
        }
    }

    // Toggle Bot Token visibility
    $('.milmit-toggle-token').on('click', function() {
        var $input = $('#token');
        var isPass = $input.attr('type') === 'password';
        $input.attr('type', isPass ? 'text' : 'password');
        $(this).find('.dashicons').toggleClass('dashicons-visibility dashicons-hidden');
    });

    // Radio card visual selection
    $('.milmit-radio-card input[type="radio"]').on('change', function() {
        var name = $(this).attr('name');
        $('input[name="' + name + '"]').closest('.milmit-radio-card').removeClass('is-selected');
        if ($(this).is(':checked')) {
            $(this).closest('.milmit-radio-card').addClass('is-selected');
        }
    });

    // Proxy collapse toggle
    $('input[name="woogram_settings[proxy_status]"]').on('change', function() {
        var isEnabled = $(this).val() == '1';
        $('#milmit_proxy_fields').toggleClass('is-collapsed', !isEnabled);
    });

    // Variable insertion into textareas
    var lastFocusedTextarea = null;
    $('.milmit-textarea').on('focus', function() {
        lastFocusedTextarea = this;
    });

    $('.milmit-chip-btn').on('click', function(e) {
        e.preventDefault();
        var insertText = $(this).data('insert');
        var target = lastFocusedTextarea || document.getElementById('wmuser');

        if (target) {
            var startPos = target.selectionStart || target.value.length;
            var endPos = target.selectionEnd || target.value.length;
            var text = target.value;
            target.value = text.substring(0, startPos) + insertText + text.substring(endPos, text.length);
            target.focus();
            target.selectionStart = startPos + insertText.length;
            target.selectionEnd = startPos + insertText.length;
            
            showToast('متغیر ' + insertText + ' با موفقیت اضافه شد');
        }
    });

    // Copy commands to clipboard
    $('#milmit_copy_commands').on('click', function() {
        var copyText = document.getElementById('milmit_commands_text');
        copyText.select();
        copyText.setSelectionRange(0, 99999);
        navigator.clipboard.writeText(copyText.value).then(function() {
            showToast('دستورات BotFather با موفقیت در کلیپ‌بورد کپی شد!');
        }).catch(function() {
            document.execCommand('copy');
            showToast('دستورات در کلیپ‌بورد کپی شد!');
        });
    });

    // Toast helper
    function showToast(msg) {
        var $toast = $('#milmit_toast');
        if (!$toast.length) {
            $toast = $('<div id="milmit_toast" class="milmit-toast"></div>').appendTo('body');
        }
        $toast.text(msg).addClass('is-show');
        setTimeout(function() {
            $toast.removeClass('is-show');
        }, 2800);
    }

    // Diagnostic & Health-Check Endpoints
    var woogram_admin_vars = {
        ajax_url: (window.telenexaSettings && window.telenexaSettings.ajax_url) || 'admin-ajax.php',
        nonce: (window.telenexaSettings && window.telenexaSettings.nonce) || ''
    };

    // Generate Webhook Secret Token
    $('#btn_generate_secret').on('click', function(e) {
        e.preventDefault();
        var chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';
        var secret = '';
        var randomValues = new Uint8Array(32);
        window.crypto.getRandomValues(randomValues);
        for (var i = 0; i < 32; i++) {
            secret += chars[randomValues[i] % chars.length];
        }
        $('#webhook_secret_token').val(secret);
        showToast((window.telenexaSettings && window.telenexaSettings.i18n && window.telenexaSettings.i18n.tokenGenerated) || 'Secret token generated! Remember to save settings.');
    });

    // Test Bot Connection (getMe)
    $('#btn_test_telegram_connection').on('click', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var $res = $('#milmit_diag_result');
        $btn.prop('disabled', true);
        $res.show().css({ background: '#e0f2fe', color: '#0369a1', border: '1px solid #bae6fd' }).html('<span class="dashicons dashicons-update spin"></span> ' + ((window.telenexaSettings && window.telenexaSettings.i18n && window.telenexaSettings.i18n.connecting) || 'Connecting to Telegram API...') + '');

        $.post(woogram_admin_vars.ajax_url, {
            action: 'woogram_test_connection',
            nonce: woogram_admin_vars.nonce
        }, function(response) {
            $btn.prop('disabled', false);
            if (response && response.success) {
                $res.css({ background: '#dcfce7', color: '#15803d', border: '1px solid #bbf7d0' })
                    .html('<span class="dashicons dashicons-yes-alt"></span> ' + response.data.message);
            } else {
                var err = (response && response.data && response.data.message) ? response.data.message : ((window.telenexaSettings && window.telenexaSettings.i18n && window.telenexaSettings.i18n.connectionFailed) || 'Connection failed');
                $res.css({ background: '#fee2e2', color: '#b91c1c', border: '1px solid #fecaca' })
                    .html('<span class="dashicons dashicons-warning"></span> ' + err);
            }
        }).fail(function() {
            $btn.prop('disabled', false);
            $res.css({ background: '#fee2e2', color: '#b91c1c', border: '1px solid #fecaca' })
                .html('<span class="dashicons dashicons-dismiss"></span> ' + ((window.telenexaSettings && window.telenexaSettings.i18n && window.telenexaSettings.i18n.networkError) || 'Server network error occurred.') + '');
        });
    });

    // Test Webhook Info (getWebhookInfo)
    $('#btn_test_telegram_webhook').on('click', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var $res = $('#milmit_diag_result');
        $btn.prop('disabled', true);
        $res.show().css({ background: '#e0f2fe', color: '#0369a1', border: '1px solid #bae6fd' }).html('<span class="dashicons dashicons-update spin"></span> ' + ((window.telenexaSettings && window.telenexaSettings.i18n && window.telenexaSettings.i18n.checkingWebhook) || 'Checking webhook status from Telegram...') + '');

        $.post(woogram_admin_vars.ajax_url, {
            action: 'woogram_test_webhook',
            nonce: woogram_admin_vars.nonce
        }, function(response) {
            $btn.prop('disabled', false);
            if (response && response.success) {
                $res.css({ background: '#dcfce7', color: '#15803d', border: '1px solid #bbf7d0' })
                    .html('<span class="dashicons dashicons-cloud-saved"></span> ' + response.data.message);
            } else {
                var err = (response && response.data && response.data.message) ? response.data.message : ((window.telenexaSettings && window.telenexaSettings.i18n && window.telenexaSettings.i18n.webhookFailed) || 'Webhook check failed');
                $res.css({ background: '#fee2e2', color: '#b91c1c', border: '1px solid #fecaca' })
                    .html('<span class="dashicons dashicons-warning"></span> ' + err);
            }
        }).fail(function() {
            $btn.prop('disabled', false);
            $res.css({ background: '#fee2e2', color: '#b91c1c', border: '1px solid #fecaca' })
                .html('<span class="dashicons dashicons-dismiss"></span> ' + ((window.telenexaSettings && window.telenexaSettings.i18n && window.telenexaSettings.i18n.networkError) || 'Server network error occurred.') + '');
        });
    });

    // Test Proxy and automatic fallback
    $('#btn_test_telegram_proxy').on('click', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var $res = $('#milmit_diag_result');
        $btn.prop('disabled', true);
        $res.show().css({ background: '#e0f2fe', color: '#0369a1', border: '1px solid #bae6fd' }).text('' + ((window.telenexaSettings && window.telenexaSettings.i18n && window.telenexaSettings.i18n.testingProxy) || 'Testing proxy and fallback...') + '');
        $.post(woogram_admin_vars.ajax_url, {
            action: 'woogram_test_proxy',
            nonce: woogram_admin_vars.nonce
        }, function(response) {
            $btn.prop('disabled', false);
            var message = response && response.data && response.data.message ? response.data.message : ((window.telenexaSettings && window.telenexaSettings.i18n && window.telenexaSettings.i18n.proxyFailed) || 'Proxy test failed.');
            $res.css(response && response.success
                ? { background: '#dcfce7', color: '#15803d', border: '1px solid #bbf7d0' }
                : { background: '#fee2e2', color: '#b91c1c', border: '1px solid #fecaca' }).text(message);
        }).fail(function() {
            $btn.prop('disabled', false);
            $res.show().css({ background: '#fee2e2', color: '#b91c1c', border: '1px solid #fecaca' }).text('' + ((window.telenexaSettings && window.telenexaSettings.i18n && window.telenexaSettings.i18n.networkError) || 'Server network error occurred.') + '');
        });
    });

    // Run System Unit Tests
    $('#btn_run_system_tests').on('click', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var $res = $('#milmit_diag_result');
        $btn.prop('disabled', true);
        $res.show().css({ background: '#e0f2fe', color: '#0369a1', border: '1px solid #bae6fd' }).html('<span class="dashicons dashicons-update spin"></span> ' + ((window.telenexaSettings && window.telenexaSettings.i18n && window.telenexaSettings.i18n.executingTests) || 'Executing diagnostic unit tests...') + '');

        $.post(woogram_admin_vars.ajax_url, {
            action: 'woogram_run_tests',
            nonce: woogram_admin_vars.nonce
        }, function(response) {
            $btn.prop('disabled', false);
            if (response && response.success && response.data) {
                var data = response.data;
                var html = '<div style="margin-bottom:8px;font-weight:bold;font-size:14px;">' +
                    ((window.telenexaSettings && window.telenexaSettings.i18n && window.telenexaSettings.i18n.testSummary) || 'Automated Diagnostic Tests Summary:') + ' ' +
                    '<span style="color:#15803d;">' + data.passed + ' Passed</span> / ' +
                    '<span style="color:' + (data.failed > 0 ? '#b91c1c' : '#64748b') + ';">' + data.failed + ' Failed</span>' +
                    '</div><div style="display:flex;flex-direction:column;gap:6px;max-height:260px;overflow-y:auto;padding-right:4px;">';

                $.each(data.results, function(i, r) {
                    var icon = r.success ? '<span style="color:#15803d;font-weight:bold;">✔ [PASS]</span>' : '<span style="color:#b91c1c;font-weight:bold;">✖ [FAIL]</span>';
                    var bg = r.success ? '#f0fdf4' : '#fef2f2';
                    var border = r.success ? '#bbf7d0' : '#fecaca';
                    html += '<div style="padding:6px 10px;background:' + bg + ';border:1px solid ' + border + ';border-radius:4px;font-size:12px;">' +
                        icon + ' <strong>' + r.name + '</strong><br><span style="color:#475569;margin-left:18px;">' + r.details + '</span></div>';
                });
                html += '</div>';

                var overallBg = data.failed === 0 ? '#dcfce7' : '#fef2f2';
                var overallBorder = data.failed === 0 ? '#bbf7d0' : '#fecaca';
                $res.css({ background: overallBg, color: '#1e293b', border: '1px solid ' + overallBorder }).html(html);
            } else {
                $res.css({ background: '#fee2e2', color: '#b91c1c', border: '1px solid #fecaca' })
                    .html('<span class="dashicons dashicons-dismiss"></span> ' + ((window.telenexaSettings && window.telenexaSettings.i18n && window.telenexaSettings.i18n.suiteFailed) || 'Failed to execute diagnostic test suite.') + '');
            }
        }).fail(function() {
            $btn.prop('disabled', false);
            $res.css({ background: '#fee2e2', color: '#b91c1c', border: '1px solid #fecaca' })
                .html('<span class="dashicons dashicons-dismiss"></span> ' + ((window.telenexaSettings && window.telenexaSettings.i18n && window.telenexaSettings.i18n.ajaxFailed) || 'Test suite AJAX request failed.') + '');
        });
    });

    // Native Unicode emoji are supported directly by the message fields.
});
</script>
