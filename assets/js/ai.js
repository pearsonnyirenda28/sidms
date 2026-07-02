(function() {
    'use strict';
    if (window.__sidmsAIInitialized) return;
    window.__sidmsAIInitialized = true;

    let aiPanelVisible = false;

    function init() {
        if (!document.getElementById('aiSidebar')) return;
        window.toggleAIPanel = toggleAIPanel;
        window.fetchInsights = fetchInsights;
        window.askAI = askAI;

        const btn = document.getElementById('aiInsightsBtn');
        if (btn) btn.addEventListener('click', toggleAIPanel);
    }

    function toggleAIPanel() {
        aiPanelVisible = !aiPanelVisible;
        const panel = document.getElementById('aiSidebar');
        if (panel) panel.classList.toggle('open');
    }

    async function fetchInsights() {
        const loading = document.getElementById('aiLoading');
        const results = document.getElementById('aiResults');
        const error = document.getElementById('aiError');
        loading.style.display = 'block';
        results.style.display = 'none';
        error.style.display = 'none';

        try {
            const res = await fetch(`${BASE_URL}/api/ai_summarize.php?file_id=${fileId}&action=insights`);
            const data = await res.json();
            if (data.ok) {
                document.getElementById('aiSummary').textContent = data.insights.summary || 'No summary.';
                document.getElementById('aiConcepts').innerHTML = (data.insights.concepts || '').replace(/\n/g, '<br>');
                document.getElementById('aiOverview').textContent = data.insights.overview || 'No overview.';
                loading.style.display = 'none';
                results.style.display = 'block';
            } else {
                throw new Error(data.error || 'Failed');
            }
        } catch (e) {
            loading.style.display = 'none';
            error.textContent = e.message;
            error.style.display = 'block';
        }
    }

    async function askAI() {
        const question = document.getElementById('aiQuestion').value.trim();
        if (!question) return;
        const answerDiv = document.getElementById('aiAnswer');
        answerDiv.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Thinking…';
        const formData = new FormData();
        formData.append('question', question);
        try {
            const res = await fetch(`${BASE_URL}/api/ai_summarize.php?file_id=${fileId}&action=ask`, { method:'POST', body:formData });
            const data = await res.json();
            answerDiv.textContent = data.ok ? data.answer : `Error: ${data.error}`;
        } catch {
            answerDiv.textContent = 'Network error';
        }
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();