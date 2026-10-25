// Keep at most three ten-record batches in the DOM. Discarded batches can be
// fetched again using their previous/next cursors; patient data is never cached.
function startQueue(root) {
    if (root.dataset.queueReady) return;
    root.dataset.queueReady = 'true';
    const viewport = root.querySelector('[data-queue-viewport]');
    const table = root.dataset.queueKind === 'table';
    const items = table ? viewport.querySelector('tbody') : viewport.querySelector('[data-queue-items]');
    if (!items) return;
    const status = root.querySelector('[data-queue-status]');
    const nextButton = root.querySelector('[data-queue-next]');
    const previousButton = root.querySelector('[data-queue-previous]');
    const batches = [{ nodes: [...items.children], next: root.dataset.next || null, previous: root.dataset.previous || null }];
    let busy = false;
    let failed = false;
    let lastTop = 0;
    let controller;
    let alive = true;
    let suppressScroll = false;

    function controls() {
        for (const [button, url] of [[previousButton, batches[0].previous], [nextButton, batches.at(-1).next]]) {
            button.hidden = !url;
            button.href = url || '#';
            button.setAttribute('aria-disabled', String(busy));
        }
        viewport.setAttribute('aria-busy', String(busy));
    }

    function size() {
        if (!table) {
            // Card histories should not reserve a large blank panel when empty
            // or short; long histories still scroll within a bounded region.
            viewport.style.height = 'auto';
            viewport.style.maxHeight = 'min(640px, 75vh)';
            const endSpace = viewport.querySelector('.queue-end-space');
            if (endSpace) endSpace.style.display = !batches.at(-1).next ? 'none' : '';
            return;
        }
        const rows = [...items.children].slice(0, 10);
        // Ten normal table rows; allow unusually tall rows to remain readable.
        const rowsHeight = rows.reduce((height, row) => height + row.getBoundingClientRect().height, 0);
        const head = viewport.querySelector('thead')?.getBoundingClientRect().height || 44;
        const completeShortList = !batches[0].previous && !batches.at(-1).next && items.children.length <= 10;
        const endSpace = viewport.querySelector('.queue-end-space');
        if (endSpace) endSpace.style.display = completeShortList ? 'none' : '';
        if (completeShortList) {
            // A patient profile may place history below the screen. Let the
            // page scroll instead of clipping a short history to one row.
            viewport.style.height = 'auto';
            return;
        }
        // Reserve space for the controls and enclosing page padding, rather
        // than pushing the bottom of the table below the browser window.
        let trailingSpace = root.getBoundingClientRect().bottom - viewport.getBoundingClientRect().bottom;
        for (let element = root; element; element = element.parentElement) {
            const style = getComputedStyle(element);
            trailingSpace += parseFloat(style.marginBottom) || 0;
            if (element !== root) {
                trailingSpace += (parseFloat(style.paddingBottom) || 0) + (parseFloat(style.borderBottomWidth) || 0);
            }
            if (element.id === 'main') break;
        }
        const screenHeight = window.visualViewport?.height || window.innerHeight;
        const documentTop = viewport.getBoundingClientRect().top + window.scrollY;
        const available = screenHeight - documentTop - trailingSpace - 2;
        // Very small screens and pages with several sections must retain a
        // usable table without hiding the surrounding forms/content.
        viewport.style.height = `${Math.floor(Math.min(head + rowsHeight, Math.max(96, available)))}px`;
    }

    async function load(direction) {
        const url = direction === 'next' ? batches.at(-1).next : batches[0].previous;
        if (busy || !url || !alive) return;
        const target = new URL(url, location.href);
        if (target.origin !== location.origin) return;
        busy = true;
        failed = false;
        status.textContent = 'Loading records…';
        controls();
        controller = new AbortController();
        try {
            const response = await fetch(target, { headers: { Accept: 'application/json', 'X-Scroll-Queue': root.dataset.scrollQueue }, signal: controller.signal, cache: 'no-store' });
            if (!response.ok || response.redirected) throw new Error('Request failed');
            const data = await response.json();
            if (typeof data.html !== 'string' || !('next' in data) || !('previous' in data)) throw new Error('Invalid list response');
            const document = new DOMParser().parseFromString(table && !data.html.includes('<table') ? `<table><tbody>${data.html}</tbody></table>` : data.html, 'text/html');
            const source = table ? document.querySelector('tbody') : document.querySelector('[data-queue-items]');
            if (!source) throw new Error('List unavailable');
            const nodes = [...source.children];
            if (!nodes.length || data.count === 0) {
                if (direction === 'next') batches.at(-1).next = null;
                else batches[0].previous = null;
                status.textContent = 'No more records in this direction. Refresh for recent changes.';
                return;
            }
            if (nodes.length > 10) throw new Error('Invalid batch size');
            const anchor = [...items.children].find(node => node.getBoundingClientRect().bottom > viewport.getBoundingClientRect().top + 44);
            const anchorTop = anchor?.getBoundingClientRect().top;
            const batch = { nodes, next: data.next, previous: data.previous };
            suppressScroll = true;
            if (direction === 'next') { items.append(...nodes); batches.push(batch); }
            else { items.prepend(...nodes); batches.unshift(batch); }
            if (batches.length > 3) {
                const removed = direction === 'next' ? batches.shift() : batches.pop();
                removed.nodes.forEach(node => node.remove());
            }
            // Preserve the visible row while prepending or trimming older rows.
            viewport.style.scrollBehavior = 'auto';
            if (anchor?.isConnected) viewport.scrollTop += anchor.getBoundingClientRect().top - anchorTop;
            lastTop = viewport.scrollTop;
            requestAnimationFrame(() => { suppressScroll = false; viewport.style.scrollBehavior = ''; });
            status.textContent = batches.at(-1).next ? 'Scroll for more records.' : 'End of records.';
            // The manual link stays visually hidden (keyboard focus still reveals it) unless it is needed to retry.
            nextButton.classList.add('sr-only');
        } catch (error) {
            if (error.name !== 'AbortError') {
                failed = true;
                status.textContent = 'Unable to load records. Use Load more records / Load earlier records to retry, or refresh if your session changed.';
                nextButton.classList.remove('sr-only');
            }
        } finally {
            busy = false;
            if (alive) { controls(); size(); }
        }
    }
    nextButton.addEventListener('click', event => { event.preventDefault(); load('next'); });
    previousButton.addEventListener('click', event => { event.preventDefault(); load('previous'); });
    viewport.addEventListener('scroll', () => {
        const top = viewport.scrollTop;
        const direction = top >= lastTop ? 'next' : 'previous';
        lastTop = top;
        if (suppressScroll || failed || busy) return;
        if (direction === 'next' && top + viewport.clientHeight >= viewport.scrollHeight - 80) load('next');
        else if (direction === 'previous' && top < 80) load('previous');
    }, { passive: true });
    // A short first batch may not fill the viewport, but a wheel action can
    // still request the next cursor without preloading on page arrival.
    viewport.addEventListener('wheel', event => {
        if (!failed && !busy && event.deltaY > 0 && viewport.scrollHeight <= viewport.clientHeight + 1) load('next');
    }, { passive: true });
    controls();
    size();
    const observer = new ResizeObserver(() => requestAnimationFrame(size));
    observer.observe(root);
    const main = root.closest('main');
    if (main) observer.observe(main);
    window.addEventListener('resize', size);
    window.visualViewport?.addEventListener('resize', size);
    window.addEventListener('pagehide', () => { alive = false; controller?.abort(); observer.disconnect(); });
    window.addEventListener('pageshow', event => { if (event.persisted) { alive = true; observer.observe(root); if (main) observer.observe(main); size(); controls(); } });
}

function initialize() {
    document.querySelectorAll('[data-scroll-queue]').forEach(startQueue);
    // Small reference/result tables are not database feeds. Give long static
    // tables the same ten-row viewport without removing editable form fields.
    document.querySelectorAll('table').forEach(table => {
        if (table.closest('[data-scroll-queue]') || !table.tBodies[0] || table.tBodies[0].rows.length <= 10) return;
        const wrapper = document.createElement('div');
        wrapper.className = 'queue-viewport overflow-auto';
        wrapper.tabIndex = 0;
        wrapper.setAttribute('role', 'region');
        wrapper.setAttribute('aria-label', `${table.caption?.textContent?.trim() || 'Table'}, scroll to view more`);
        table.before(wrapper);
        wrapper.append(table);
        wrapper.style.height = `${Math.ceil((table.tHead?.getBoundingClientRect().height || 0) + [...table.tBodies[0].rows].slice(0, 10).reduce((height, row) => height + row.getBoundingClientRect().height, 0))}px`;
    });
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, { once: true });
else initialize();
