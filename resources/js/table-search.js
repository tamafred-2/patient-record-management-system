// Small tables already rendered in full can be searched without a request.
// Database queues keep their server filters so searches include later batches.
let sequence = 0;
function enhance(table) {
    if (table.dataset.tableSearchReady || table.hasAttribute('data-server-filtered') || table.closest('[data-scroll-queue][data-queue-kind="table"]')) return;
    if (!table.tBodies.length || table.querySelector('input, textarea, select')) return;
    table.dataset.tableSearchReady = 'true';
    const rows = [...table.tBodies].flatMap(body => [...body.rows]).filter(row => !row.hidden && !row.querySelector('[colspan]'));
    const headers = [...(table.tHead?.rows[table.tHead.rows.length - 1]?.cells || [])];
    const name = table.caption?.textContent.trim() || 'Table';
    const id = `table-search-${++sequence}`;
    const controls = document.createElement('div');
    controls.className = 'table-search-controls flex flex-wrap items-end gap-3 border-b border-slate-200 bg-white p-4 text-sm';
    controls.setAttribute('role', 'search');
    controls.setAttribute('aria-label', `Search ${name}`);
    const searchLabel = document.createElement('label');
    searchLabel.className = 'min-w-40 flex-1 font-medium';
    searchLabel.textContent = 'Search displayed rows';
    const input = document.createElement('input');
    input.id = id; input.type = 'search'; input.maxLength = 100;
    input.className = 'mt-2 block w-full rounded-lg border border-slate-300 p-3 font-normal';
    searchLabel.append(input);
    const columnLabel = document.createElement('label');
    columnLabel.className = 'font-medium'; columnLabel.textContent = 'Search column';
    const column = document.createElement('select');
    column.className = 'mt-2 block w-full rounded-lg border border-slate-300 p-3 font-normal';
    column.add(new Option('All columns', ''));
    headers.forEach((header, index) => {
        const text = header.textContent.trim();
        if (text && !/^actions?$/i.test(text)) column.add(new Option(text, String(index)));
    });
    columnLabel.append(column);
    const reset = document.createElement('button');
    reset.type = 'button'; reset.textContent = 'Reset';
    reset.className = 'px-2 py-3 font-semibold text-emerald-800 underline';
    const status = document.createElement('p');
    status.className = 'w-full text-xs text-slate-500'; status.id = `${id}-status`;
    status.setAttribute('role', 'status'); input.setAttribute('aria-describedby', status.id);
    controls.append(searchLabel, columnLabel, reset, status);
    // Keep controls outside the static table's scroll viewport.
    const anchor = table.parentElement.classList.contains('queue-viewport') ? table.parentElement : table;
    anchor.before(controls);
    function filter() {
        const terms = input.value.trim().toLocaleLowerCase().split(/\s+/u).filter(Boolean);
        let count = 0;
        rows.forEach(row => {
            const text = (column.value === '' ? row.textContent : row.cells[Number(column.value)]?.textContent || '').toLocaleLowerCase();
            const matches = terms.every(term => text.includes(term));
            row.toggleAttribute('data-table-search-hidden', !matches);
            if (matches) count++;
        });
        status.textContent = `${count} of ${rows.length} displayed rows match.${count === 0 ? ' No matching rows.' : ''}`;
    }
    input.addEventListener('input', filter);
    column.addEventListener('change', filter);
    reset.addEventListener('click', () => { input.value = ''; column.value = ''; filter(); input.focus(); });
    filter();
}
function initialize() {
    const main = document.querySelector('#main');
    if (!main) return;
    main.querySelectorAll('table').forEach(enhance);
    // Clinical record cards may load additional result tables through a cursor.
    new MutationObserver(records => {
        records.forEach(record => record.addedNodes.forEach(node => {
            if (!(node instanceof Element)) return;
            if (node.matches('table')) enhance(node);
            node.querySelectorAll('table').forEach(enhance);
        }));
    }).observe(main, { childList: true, subtree: true });
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, { once: true });
else initialize();
