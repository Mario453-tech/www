(() => {
    'use strict';

    const form = document.getElementById('br-bg-upload-form');
    if (!form) return;
    const picker = document.getElementById('br-bg-file-picker');
    const status = document.getElementById('br-bg-file-status');
    const nameInput = document.getElementById('br-bg-name-input');
    const preview = document.getElementById('br-bg-name-preview');
    const CHUNK = 1024 * 1024;
    const MAX_TOTAL = 20 * 1024 * 1024;
    const MAX_CHUNKS = 32;
    const messages = JSON.parse(form.dataset.uploadMessages);
    const roleOrder = ['hr', 'tech', 'finance', 'legal', 'logistics'];
    let busy = false;
    class UploadError extends Error {}

    function updateName() {
        const parts = [];
        roleOrder.forEach(role => {
            const checkbox = form.querySelector('.br-bg-role-cb[data-role="' + role + '"]');
            const select = form.querySelector('.br-bg-gender-sel[data-role="' + role + '"]');
            if (select) select.disabled = !checkbox || !checkbox.checked;
            if (checkbox && checkbox.checked) {
                parts.push(select && select.value ? role + '_' + select.value : role);
            }
        });
        nameInput.value = parts.join('_');
        preview.textContent = parts.length ? 'boardroom_bg_' + nameInput.value + '.png' : 'boardroom_bg.png';
    }

    form.addEventListener('change', event => {
        if (event.target.matches('.br-bg-role-cb, .br-bg-gender-sel')) updateName();
    });
    updateName();

    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (busy) return;
        const file = picker.files[0];
        const name = nameInput.value;
        const token = form.querySelector('input[name="csrf_token"]').value;
        if (!name) { status.textContent = messages.name; return; }
        if (!file) { status.textContent = messages.file; return; }
        if (!file.size || file.size > MAX_TOTAL || Math.ceil(file.size / CHUNK) > MAX_CHUNKS) {
            status.textContent = messages.size;
            return;
        }
        if (file.type && !['image/png', 'image/jpeg', 'image/webp'].includes(file.type)) {
            status.textContent = messages.image;
            return;
        }
        if (!token) { status.textContent = messages.csrf; return; }

        busy = true;
        const controls = Array.from(form.elements).map(control => [control, control.disabled]);
        controls.forEach(([control]) => { control.disabled = true; });
        form.setAttribute('aria-busy', 'true');
        let completed = false;
        try {
            const random = new Uint8Array(16);
            crypto.getRandomValues(random);
            const uploadId = Array.from(random, byte => byte.toString(16).padStart(2, '0')).join('');
            const total = Math.ceil(file.size / CHUNK);
            for (let index = 0; index < total; index++) {
                status.textContent = messages.progress.replace(':current', String(index + 1)).replace(':total', String(total));
                const url = new URL('/admin/template_editor.php', window.location.origin);
                Object.entries({
                    ajax_upload: '1', csrf_token: token, upload_id: uploadId,
                    bg_name: name, bg_file_mime: file.type,
                    chunk_index: String(index), total_chunks: String(total)
                }).forEach(([key, value]) => url.searchParams.set(key, value));

                const controller = new AbortController();
                const timeout = setTimeout(() => controller.abort(), 60000);
                let response;
                let result;
                try {
                    response = await fetch(url, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/octet-stream', 'X-Requested-With': 'XMLHttpRequest' },
                        body: file.slice(index * CHUNK, (index + 1) * CHUNK),
                        signal: controller.signal
                    });
                    if (!response.ok) throw new UploadError(messages.http);
                    try {
                        result = await response.json();
                    } catch {
                        throw new UploadError(messages.response);
                    }
                } catch (error) {
                    if (controller.signal.aborted) throw new UploadError(messages.timeout);
                    if (error.message === messages.http || error.message === messages.response) throw error;
                    throw new UploadError(messages.network);
                } finally {
                    clearTimeout(timeout);
                }
                if (!result || typeof result !== 'object' || typeof result.ok !== 'boolean') {
                    throw new UploadError(messages.response);
                }
                if (!result.ok) throw new UploadError(typeof result.err === 'string' && result.err ? result.err : messages.server);
                if (typeof result.done !== 'boolean' || result.done !== (index === total - 1)
                    || (!result.done && result.chunk !== index)) {
                    throw new UploadError(messages.response);
                }
                if (result.done) {
                    status.textContent = typeof result.msg === 'string' && result.msg ? result.msg : messages.saved;
                    completed = true;
                    setTimeout(() => window.location.reload(), 1200);
                }
            }
        } catch (error) {
            // Render errors as text, never HTML; pokazuj bledy jako tekst, nigdy HTML.
            status.textContent = error instanceof UploadError ? error.message : messages.server;
        } finally {
            form.removeAttribute('aria-busy');
            if (!completed) {
                controls.forEach(([control, disabled]) => { control.disabled = disabled; });
                busy = false;
            }
        }
    });

    const footerList = document.getElementById('br-footer-links-list');
    const addFooter = document.querySelector('[data-br-add-footer]');
    if (footerList && addFooter) {
        footerList.addEventListener('click', event => {
            const remove = event.target.closest('[data-br-remove-footer]');
            if (remove) remove.closest('.br-link-row').remove();
        });
        addFooter.addEventListener('click', () => {
            const row = document.createElement('div');
            row.className = 'br-link-row';
            ['label', 'url'].forEach(field => {
                const input = document.createElement('input');
                input.type = 'text';
                input.name = 'footer_link_' + field + '[]';
                input.placeholder = addFooter.dataset[field];
                row.appendChild(input);
            });
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'br-btn-remove-link';
            remove.dataset.brRemoveFooter = '';
            row.appendChild(remove);
            footerList.appendChild(row);
        });
    }
})();
