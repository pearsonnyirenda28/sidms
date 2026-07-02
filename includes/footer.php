<?php
/**
 * Global footer with developer attribution, Super AI conversational chat,
 * and push notification subscription.
 */
?>
<footer class="text-center text-muted small py-3 mt-5 border-top border-secondary">
    <div class="container">
        <?= APP_NAME ?> — <?= APP_FULL_NAME ?> v<?= APP_VERSION ?><br>
        <span class="opacity-75">© <?= date('Y') ?> Developed by <strong><?= APP_DEVELOPER ?></strong>. All rights reserved.</span>
    </div>
</footer>

<!-- Toast Container -->
<div id="toast-container" class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index:1055;"></div>

<?php if (!empty($_SESSION['uid']) && !empty($_SESSION['otp_ok'])): ?>
    <!-- Push notification subscription -->
    <?php if (PUSH_ENABLED): ?>
    <script>
        const VAPID_PUBLIC_KEY = '<?= VAPID_PUBLIC_KEY ?>';
        const USER_ID = <?= $_SESSION['uid'] ?? 0 ?>;
    </script>
    <script src="<?= BASE_URL ?>/assets/js/push.js"></script>
    <?php endif; ?>

    <!-- Super AI Chat Widget -->
    <div id="ai-fab" style="position:fixed; bottom:20px; left:20px; z-index:1050;">
        <button id="ai-fab-btn" class="btn btn-primary rounded-circle p-3 shadow-lg" style="width:60px; height:60px;" aria-label="Open AI Chat">
            <i class="bi bi-robot fs-4"></i>
        </button>
    </div>

    <div id="ai-chat-panel" style="display:none; position:fixed; bottom:90px; left:20px; width:360px; height:520px; background:var(--sidms-surface, #161b22); border:1px solid var(--sidms-border, #30363d); border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,0.6); z-index:1051; flex-direction:column;">
        <div class="d-flex align-items-center justify-content-between p-3" style="border-bottom:1px solid var(--sidms-border, #30363d);">
            <span><i class="bi bi-robot me-2 text-info"></i><strong>Super AI</strong></span>
            <button id="ai-chat-close" class="btn-close btn-close-white" aria-label="Close"></button>
        </div>
        <div id="ai-chat-messages" class="flex-grow-1 overflow-auto p-3" style="font-size:0.9rem;"></div>
        <form id="ai-chat-form" class="d-flex gap-2 p-3 border-top" style="border-color:var(--sidms-border, #30363d);">
            <input type="text" id="ai-chat-input" class="form-control" placeholder="Ask anything..." autocomplete="off">
            <button type="submit" class="btn btn-primary"><i class="bi bi-send"></i></button>
        </form>
    </div>

    <script>
    (function() {
        const chatHistory = [];

        window.speak = function(text, lang = 'en-US') {
            if (!('speechSynthesis' in window)) return;
            window.speechSynthesis.cancel();
            const utter = new SpeechSynthesisUtterance(text);
            utter.lang = lang;
            utter.rate = 0.95;
            window.speechSynthesis.speak(utter);
        };

        window.speakMessage = function(msgId) {
            const div = document.getElementById(msgId);
            if (!div) return;
            const btn = div.querySelector('button');
            if (btn) btn.remove();
            const text = div.innerText.replace(/^AI:\s*/, '').trim();
            speak(text);
        };

        function esc(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function boot() {
            const fabBtn = document.getElementById('ai-fab-btn');
            const closeBtn = document.getElementById('ai-chat-close');
            const panel = document.getElementById('ai-chat-panel');
            const form = document.getElementById('ai-chat-form');
            const input = document.getElementById('ai-chat-input');
            const messages = document.getElementById('ai-chat-messages');

            if (!fabBtn || !panel) return;

            function openPanel() {
                panel.style.display = 'flex';
                input.focus();
                if (chatHistory.length === 0) {
                    messages.innerHTML = `<div class="mb-2"><strong>AI:</strong> 👋 Hello! I'm your SIDMS AI assistant. Ask me anything!</div>`;
                }
            }
            function closePanel() { panel.style.display = 'none'; }
            fabBtn.addEventListener('click', openPanel);
            closeBtn.addEventListener('click', closePanel);

            if (!form) return;

            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                const question = input.value.trim();
                if (!question) return;

                chatHistory.push({role: 'user', content: question});
                messages.innerHTML += `<div class="mb-2"><strong>You:</strong> ${esc(question)}</div>`;
                input.value = '';

                const thinkingDiv = document.createElement('div');
                thinkingDiv.className = 'mb-2 text-info';
                thinkingDiv.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Thinking…';
                messages.appendChild(thinkingDiv);
                messages.scrollTop = messages.scrollHeight;

                const payload = { question: question, history: chatHistory.slice(-10) };
                if (typeof fileId !== 'undefined' && fileId > 0) payload.file_id = fileId;

                try {
                    const res = await fetch(BASE_URL + '/api/ai_chat.php', {
                        method: 'POST',
                        credentials: 'include',          // ← send session cookie
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify(payload)
                    });

                    thinkingDiv.remove();

                    if (!res.ok) {
                        throw new Error('Server returned ' + res.status);
                    }

                    const data = await res.json();

                    if (data.ok) {
                        chatHistory.push({role: 'assistant', content: data.answer});
                        const msgId = 'msg-' + Date.now();
                        messages.innerHTML += `
                            <div class="mb-2" id="${msgId}">
                                <strong>AI:</strong> <span style="white-space:pre-wrap;">${esc(data.answer)}</span>
                                ${data.answer.includes('[from web]') ? '<span class="badge bg-info ms-1">Web</span>' : ''}
                                <button class="btn btn-sm btn-outline-secondary ms-2" onclick="speakMessage('${msgId}')" title="Read aloud">
                                    <i class="bi bi-volume-up"></i>
                                </button>
                            </div>`;
                    } else {
                        messages.innerHTML += `<div class="mb-2 text-danger">Error: ${esc(data.error || 'Unknown error')}</div>`;
                    }
                } catch (err) {
                    thinkingDiv.remove();
                    messages.innerHTML += `<div class="mb-2 text-danger">Network error</div>`;
                }

                messages.scrollTop = messages.scrollHeight;
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', boot);
        } else {
            boot();
        }
    })();
    </script>
<?php endif; ?>