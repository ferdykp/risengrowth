(() => {
    const panel = document.getElementById('discovery-live-status');
    if (!panel) return;
    const form = document.getElementById('discovery-run-form');
    const button = document.getElementById('discovery-run-button');
    const health = document.getElementById('discovery-live-health');
    const stop = document.getElementById('discovery-stop-tracking');
    const storageKey = panel.dataset.storageKey;
    let pending = null;
    let submitting = false;
    let polling = false;
    let failures = 0;
    let timer;
    const notify = (message, error = false) => {
        panel.hidden = false;
        panel.textContent = message;
        panel.setAttribute('role', error ? 'alert' : 'status');
    };
    const save = () => {
        try {
            if (pending) sessionStorage.setItem(storageKey, JSON.stringify(pending));
            else sessionStorage.removeItem(storageKey);
        } catch (_) { /* Polling still works when browser storage is unavailable. */ }
    };
    try {
        const restored = JSON.parse(sessionStorage.getItem(storageKey));
        if (restored && ['python', 'threads'].includes(restored.source) && Number.isInteger(restored.keyword_id)
            && Number.isInteger(restored.after_id) && Number.isFinite(restored.started)) pending = restored;
    } catch (_) { /* Ignore stale browser state. */ }
    const updateButton = () => {
        if (stop) stop.hidden = !pending;
        if (!button) return;
        button.disabled = submitting || Boolean(pending) || button.dataset.unavailable === 'true';
        button.setAttribute('aria-busy', String(submitting || Boolean(pending)));
        const label = button.querySelector('[data-run-label]');
        if (label) label.textContent = submitting || pending ? 'Pencarian berlangsung…' : 'Jalankan Pencarian';
    };
    const zeroMessage = source => source === 'threads'
        ? ' Threads API mengembalikan 0 postingan. Periksa keyword dan persetujuan akses threads_keyword_search di Meta. Akun terhubung belum membuktikan akses pencarian publik.'
        : ' Tidak ada kecocokan di sumber public web / API yang dipindai. Sumber ini tidak mencari Threads.';
    const finish = run => {
        const failed = run.status === 'failed';
        const source = run.source_type === 'threads' ? 'Threads' : 'public web / API';
        const message = failed ? `Pencarian ${source} gagal. ${run.error_summary || 'Periksa riwayat kegagalan.'}`
            : `Pencarian ${source} ${run.status === 'partial' ? 'selesai sebagian' : 'selesai'}. ${run.fetched_count} postingan diterima, ${run.added_count} lead baru.`
                + (Number(run.fetched_count) === 0 ? zeroMessage(run.source_type) : '')
                + (run.status === 'partial' ? ` ${run.error_summary || 'Sebagian sumber gagal; lihat riwayat.'}` : '');
        notify(message, failed);
        pending = null;
        save();
        updateButton();
    };
    const poll = async () => {
        if (polling || document.visibilityState === 'hidden') return;
        window.clearTimeout(timer);
        polling = true;
        const url = new URL(window.location.href);
        if (pending) {
            url.searchParams.set('track_keyword', pending.keyword_id);
            url.searchParams.set('track_source', pending.source);
            url.searchParams.set('after_id', pending.after_id);
        }
        try {
            const response = await fetch(url, {headers: {Accept: 'application/json'}, cache: 'no-store', signal: AbortSignal.timeout(12000)});
            if (response.status === 401 || response.status === 403 || response.redirected) {
                throw new Error('Sesi berakhir atau akses berubah. Login ulang untuk memantau status.');
            }
            if (!response.ok) throw new Error('Status belum dapat diperbarui. Akan dicoba lagi otomatis.');
            const data = await response.json();
            const rows = document.getElementById('discovery-run-rows');
            if (rows && typeof data.history_html === 'string') rows.innerHTML = data.history_html;
            for (const [key, value] of Object.entries(data.metrics || {})) {
                const metric = document.querySelector(`[data-discovery-metric="${key}"]`);
                if (metric) metric.textContent = Number(value).toLocaleString('id-ID');
            }
            failures = 0;
            health.textContent = 'Status diperbarui ' + new Date().toLocaleTimeString('id-ID') + ' · otomatis setiap 4 detik.';
            if (pending && data.tracked) {
                if (['completed', 'partial', 'failed'].includes(data.tracked.status)) finish(data.tracked);
                else notify(`Pencarian ${pending.source === 'threads' ? 'Threads' : 'public web / API'} ${data.tracked.status === 'running' ? 'sedang berjalan' : 'masih dalam antrean'}…`);
            } else if (pending) {
                const waited = Date.now() - pending.started;
                notify(waited > 120000
                    ? 'Belum ada proses baru yang tercatat setelah 2 menit. Periksa worker dan antrean server; ini belum berarti hasil pencarian 0.'
                    : 'Permintaan diterima. Menunggu proses pencarian tercatat…');
            }
        } catch (error) {
            failures++;
            health.textContent = error.message || 'Koneksi status terputus. Akan dicoba lagi otomatis.';
        } finally {
            polling = false;
            timer = window.setTimeout(poll, failures > 2 ? 15000 : 4000);
        }
    };
    form?.addEventListener('submit', async event => {
        event.preventDefault();
        if (submitting || pending) return;
        const body = new FormData(form);
        const source = body.get('source');
        submitting = true;
        updateButton();
        notify(source === 'threads' ? 'Menghubungi Threads API dan mencari postingan…' : 'Mengirim pencarian public web / API ke antrean…');
        try {
            const response = await fetch(form.action, {method: 'POST', body, headers: {Accept: 'application/json'}, signal: AbortSignal.timeout(45000)});
            if (response.redirected || !response.headers.get('content-type')?.includes('application/json')) {
                throw new Error('Server tidak mengembalikan status JSON. Pastikan kode terbaru sudah dideploy dan sesi login masih aktif.');
            }
            const data = await response.json();
            if (!response.ok) {
                const validation = Object.values(data.errors || {}).flat().join(' ');
                throw new Error(validation || data.message || 'Pencarian tidak dapat dijalankan.');
            }
            if (data.status === 'queued') {
                pending = {source: data.source, keyword_id: data.keyword_id, after_id: data.after_id, started: Date.now()};
                save();
                notify(data.message);
            } else if (data.status === 'completed') {
                finish({source_type: data.source, status: data.status, fetched_count: data.fetched, added_count: data.added});
            } else throw new Error('Respons status pencarian tidak dikenali. Periksa riwayat sebelum mengulang.');
        } catch (error) {
            notify((error.name === 'TimeoutError' || error.name === 'TypeError')
                ? 'Koneksi ke server terputus atau melewati batas waktu. Proses mungkin tetap berjalan; periksa riwayat yang diperbarui otomatis sebelum mengulang.'
                : error.message, true);
        } finally {
            submitting = false;
            updateButton();
            if (!polling) poll();
        }
    });
    stop?.addEventListener('click', () => {
        pending = null;
        save();
        updateButton();
        notify('Pemantauan permintaan dihentikan. Job yang sudah masuk antrean tetap dapat berjalan; riwayat tetap diperbarui otomatis.');
    });
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible' && !polling) poll();
    });
    updateButton();
    poll();
})();
