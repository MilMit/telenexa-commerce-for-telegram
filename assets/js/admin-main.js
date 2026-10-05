document.addEventListener('DOMContentLoaded', function() {
    // 1. Live Markdown to Telegram Preview
    var messageBox = document.getElementById('telegram_new_message');
    var livePreview = document.getElementById('milmit-tg-live-preview');
    var charCount = document.getElementById('milmit-char-count');

    function updatePreview() {
        if (!messageBox || !livePreview) return;
        var text = messageBox.value;
        if (charCount) charCount.textContent = text.length;

        if (text.trim() === '') {
            livePreview.innerHTML = (window.telenexaMainI18n && window.telenexaMainI18n.previewPlaceholder) || "Your live message preview will appear here in real time as you type...";
            return;
        }

        var html = text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
        html = html.replace(/\*(.*?)\*/g, '<b>$1</b>');
        html = html.replace(/_(.*?)_/g, '<i>$1</i>');
        html = html.replace(/`(.*?)`/g, '<code style="background:#f1f5f9;padding:1px 4px;border-radius:3px;">$1</code>');
        html = html.replace(/\[(.*?)\]\((.*?)\)/g, '<a href="$2" target="_blank" style="color:#229ed9;text-decoration:underline;">$1</a>');
        html = html.replace(/\n/g, '<br>');

        livePreview.innerHTML = html;
    }

    if (messageBox) {
        messageBox.addEventListener('input', updatePreview);
    }

    // 2. Formatting Toolbar
    var toolButtons = document.querySelectorAll('.milmit-tool-btn');
    toolButtons.forEach(function(btn) {
        btn.addEventListener('click', function() {
            var tag = btn.getAttribute('data-tag');
            if (!messageBox) return;
            var start = messageBox.selectionStart;
            var end = messageBox.selectionEnd;
            var selected = messageBox.value.substring(start, end);
            var replacement = '';

            if (tag === 'bold') {
                replacement = '*' + (selected || 'متن ضخیم') + '*';
            } else if (tag === 'italic') {
                replacement = '_' + (selected || 'متن مورب') + '_';
            } else if (tag === 'code') {
                replacement = '`' + (selected || 'کد') + '`';
            } else if (tag === 'link') {
                replacement = '[' + (selected || 'عنوان لینک') + '](https://)';
            }

            messageBox.setRangeText(replacement, start, end, 'end');
            messageBox.focus();
            updatePreview();
        });
    });

    // 3. Emoji buttons
    var emojiButtons = document.querySelectorAll('.milmit-emoji-btn');
    emojiButtons.forEach(function(btn) {
        btn.addEventListener('click', function() {
            if (!messageBox) return;
            var emoji = btn.textContent;
            var start = messageBox.selectionStart;
            var end = messageBox.selectionEnd;
            messageBox.setRangeText(emoji, start, end, 'end');
            messageBox.focus();
            updatePreview();
        });
    });

    // 4. Variable chips
    var varChips = document.querySelectorAll('.milmit-var-chip');
    varChips.forEach(function(chip) {
        chip.addEventListener('click', function() {
            if (!messageBox) return;
            var variable = chip.getAttribute('data-var');
            var start = messageBox.selectionStart;
            var end = messageBox.selectionEnd;
            messageBox.setRangeText(variable, start, end, 'end');
            messageBox.focus();
            updatePreview();
        });
    });

    // 5. Client-side Audience Instant Search
    var searchInput = document.getElementById('milmit-audience-search');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            var q = this.value.toLowerCase().trim();
            var rows = document.querySelectorAll('.milmit-audience-row');
            rows.forEach(function(row) {
                var text = row.textContent.toLowerCase();
                if (text.indexOf(q) !== -1) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }

    // 6. Click ChatID to copy
    var chatTags = document.querySelectorAll('.milmit-chatid-tag');
    chatTags.forEach(function(tag) {
        tag.addEventListener('click', function() {
            var val = tag.textContent.trim();
            if (navigator.clipboard) {
                navigator.clipboard.writeText(val).then(function() {
                    var orig = tag.textContent;
                    tag.textContent = "✓ " + ((window.telenexaMainI18n && window.telenexaMainI18n.copied) || "Copied!");
                    setTimeout(function() { tag.textContent = orig; }, 1500);
                });
            }
        });
    });
});
