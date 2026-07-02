/**
 * SIDMS Main Frontend JavaScript
 * Upload, delete, share, UI interactions, toast integration.
 */

(function () {
    'use strict';

    const dropZone     = document.getElementById('drop-zone');
    const fileInput    = document.getElementById('file-input');
    const browseBtn    = document.getElementById('browse-btn');
    const uploadForm   = document.getElementById('upload-form');
    const uploadBtn    = document.getElementById('upload-btn');
    const fileListDiv  = document.getElementById('file-list');
    const uploadMsgDiv = document.getElementById('upload-msg');
    const progressWrap = document.getElementById('upload-progress');
    const progressBar  = document.getElementById('progress-bar');

    if (!dropZone) return;

    function escapeHtml(str) {
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
        return String(str ?? '').replace(/[&<>"']/g, c => map[c]);
    }
    function formatBytes(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(1) + ' MB';
    }

    browseBtn.addEventListener('click', () => fileInput.click());
    dropZone.addEventListener('click', () => fileInput.click());
    dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('drag-over'); });
    dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));
    dropZone.addEventListener('drop', e => {
        e.preventDefault();
        dropZone.classList.remove('drag-over');
        fileInput.files = e.dataTransfer.files;
        showFileList();
    });
    fileInput.addEventListener('change', showFileList);

    function showFileList() {
        const files = Array.from(fileInput.files);
        if (!files.length) {
            fileListDiv.innerHTML = '';
            uploadBtn.classList.add('d-none');
            return;
        }
        fileListDiv.innerHTML = files.map(f =>
            `<div class="d-flex align-items-center gap-2 mb-1 p-2 rounded" style="background:rgba(255,255,255,.04)">
                <i class="bi bi-file-earmark text-primary"></i>
                <span class="small flex-grow-1 text-truncate">${escapeHtml(f.name)}</span>
                <span class="text-muted small">${formatBytes(f.size)}</span>
            </div>`
        ).join('');
        uploadBtn.classList.remove('d-none');
    }

    uploadForm.addEventListener('submit', async e => {
        e.preventDefault();
        const files = Array.from(fileInput.files);
        if (!files.length) return;

        uploadBtn.disabled = true;
        progressWrap.classList.remove('d-none');
        uploadMsgDiv.innerHTML = '';
        let completed = 0;

        for (const file of files) {
            const formData = new FormData();
            formData.append('file', file);
            formData.append('csrf_token', CSRF_TOKEN);

            progressBar.style.width = Math.round((completed / files.length) * 100) + '%';

            try {
                const response = await fetch(`${BASE_URL}/upload.php`, { method: 'POST', body: formData });
                const result = await response.json();
                if (result.ok) {
                    SIDMS.toast.success(`${file.name} uploaded.`);
                } else {
                    SIDMS.toast.error(result.msg || 'Upload failed');
                }
            } catch (error) {
                SIDMS.toast.error(`Upload failed for ${file.name}`);
            }
            completed++;
        }

        progressBar.style.width = '100%';
        uploadBtn.disabled = false;
        setTimeout(() => location.reload(), 2000);
    });

    window.deleteFile = async function(id, btn, token) {
        const confirmed = await SIDMS.toast.confirm(
            'Permanently delete this file?',
            'Delete File',
            'Delete',
            'Cancel'
        );
        if (!confirmed) return;

        btn.disabled = true;
        const formData = new FormData();
        formData.append('id', id);
        formData.append('csrf_token', token || CSRF_TOKEN);

        try {
            const res = await fetch(`${BASE_URL}/delete.php`, { method: 'POST', body: formData });
            const data = await res.json();
            if (data.ok) {
                btn.closest('tr').remove();
                SIDMS.toast.success('File deleted.');
            } else {
                SIDMS.toast.error(data.msg || 'Delete failed');
                btn.disabled = false;
            }
        } catch (e) {
            SIDMS.toast.error('Network error.');
            btn.disabled = false;
        }
    };

    window.createShareModal = function(fileId) {
        window._currentShareFileId = fileId;
        document.getElementById('shareLinkInput').value = '';
        document.getElementById('shareQrCode').innerHTML = '';
        document.getElementById('share-duration').value = '21600';
        document.getElementById('share-max-access').value = '';
        const modal = new bootstrap.Modal(document.getElementById('shareModal'));
        modal.show();
    };

    window.generateShareLink = async function() {
        const fileId = window._currentShareFileId;
        if (!fileId) return;
        const duration = parseInt(document.getElementById('share-duration').value, 10);
        const maxAccessVal = document.getElementById('share-max-access').value.trim();
        const maxAccess = maxAccessVal ? parseInt(maxAccessVal, 10) : null;

        try {
            const res = await fetch(`${BASE_URL}/api/share.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    file_id: fileId,
                    csrf_token: CSRF_TOKEN,
                    duration: duration,
                    max_access: maxAccess
                })
            });
            const data = await res.json();

            if (data.ok) {
                const shareUrl = `${location.origin}${BASE_URL}/view_shared.php?token=${data.token}`;
                document.getElementById('shareLinkInput').value = shareUrl;

                const qrContainer = document.getElementById('shareQrCode');
                qrContainer.innerHTML = '';
                const qrImg = generateQRCode(shareUrl, 150, 150);
                qrContainer.appendChild(qrImg);
            } else {
                SIDMS.toast.error('Share failed: ' + (data.msg || 'Unknown error'));
                console.error('Share API error:', data);
            }
        } catch (error) {
            SIDMS.toast.error('Network error while generating share link.');
            console.error('Share fetch error:', error);
        }
    };

    window.copyShareLink = function() {
        const input = document.getElementById('shareLinkInput');
        input.select();
        document.execCommand('copy');
        SIDMS.toast.success('Link copied to clipboard');
    };
})();