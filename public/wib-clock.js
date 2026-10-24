(() => {
    const nodes = [...document.querySelectorAll('[data-wib-clock]')];
    if (!nodes.length) return;
    const loadedAt = Date.now();
    const clocks = nodes.map(node => ({node, serverTime: Date.parse(node.dataset.serverTime)}))
        .filter(clock => Number.isFinite(clock.serverTime));
    if (!clocks.length) return;
    const dateFormat = new Intl.DateTimeFormat('id-ID', {timeZone: 'Asia/Jakarta', day: '2-digit', month: 'short', year: 'numeric'});
    const timeFormat = new Intl.DateTimeFormat('id-ID', {timeZone: 'Asia/Jakarta', hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23'});
    const update = () => {
        const elapsed = Date.now() - loadedAt;
        for (const {node, serverTime} of clocks) {
            const current = new Date(serverTime + elapsed);
            const dateNode = node.querySelector('[data-wib-date]');
            const timeNode = node.querySelector('[data-wib-time]');
            if (dateNode) {
                dateNode.textContent = dateFormat.format(current);
                dateNode.setAttribute('datetime', current.toISOString());
            }
            if (timeNode) {
                timeNode.textContent = timeFormat.format(current).replace(/\./g, ':') + ' WIB';
                timeNode.setAttribute('datetime', current.toISOString());
            }
        }
    };
    update();
    window.setInterval(update, 1000);
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') update();
    });
})();
