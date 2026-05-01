// =========================================
// SubManager — UI Utilities Module
// =========================================

export const ui = {

    // ---- Toast Notifications ----
    showToast(message, type = 'success') {
        const container = document.getElementById('toast-container');
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `
            <span class="toast-icon">${type === 'success' ? '✓' : '⚠'}</span>
            <span>${message}</span>
        `;
        container.appendChild(toast);
        setTimeout(() => {
            toast.classList.add('toast-out');
            setTimeout(() => toast.remove(), 300);
        }, 3500);
    },

    // ---- Confirm Dialog ----
    showConfirm(title, message, onConfirm) {
        const overlay = document.getElementById('confirm-overlay');
        document.getElementById('confirm-title').textContent = title;
        document.getElementById('confirm-message').textContent = message;
        overlay.classList.add('show');

        const cancel = document.getElementById('confirm-cancel');
        const ok = document.getElementById('confirm-ok');

        const cleanup = () => {
            overlay.classList.remove('show');
            cancel.replaceWith(cancel.cloneNode(true));
            ok.replaceWith(ok.cloneNode(true));
        };

        document.getElementById('confirm-cancel').onclick = cleanup;
        document.getElementById('confirm-ok').onclick = () => {
            cleanup();
            onConfirm();
        };
    },

    // ---- Modal ----
    showModal(title, html) {
        const overlay = document.getElementById('modal-overlay');
        document.getElementById('modal-title').textContent = title;
        document.getElementById('modal-body').innerHTML = html;
        overlay.classList.add('show');
    },

    closeModal() {
        document.getElementById('modal-overlay').classList.remove('show');
    },

    // ---- Data Table Renderer ----
    renderTable(containerId, columns, data, actions = []) {
        const container = document.getElementById(containerId);
        if (!container) return;

        if (!data || data.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="empty-icon">
                        <rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/>
                        <line x1="9" y1="21" x2="9" y2="9"/>
                    </svg>
                    <p>No records found</p>
                </div>`;
            return;
        }

        let html = `<table class="data-table"><thead><tr>`;
        columns.forEach(col => {
            html += `<th>${col.label}</th>`;
        });
        if (actions.length > 0) {
            html += `<th style="text-align:right">Actions</th>`;
        }
        html += `</tr></thead><tbody>`;

        data.forEach((row, idx) => {
            html += `<tr>`;
            columns.forEach(col => {
                const val = col.render ? col.render(row, idx) : (row[col.key] || '—');
                html += `<td>${val}</td>`;
            });
            if (actions.length > 0) {
                html += `<td><div class="table-actions">`;
                actions.forEach(act => {
                    const isHidden = act.hide && act.hide(row);
                    if (!isHidden) {
                        html += `<button class="action-btn ${act.class || 'action-btn-primary'}" onclick="${act.handler}(${row.id})">${act.label}</button>`;
                    }
                });
                html += `</div></td>`;
            }
            html += `</tr>`;
        });

        html += `</tbody></table>`;
        container.innerHTML = html;
    },

    // ---- Utilization Bar HTML ----
    utilBar(current, limit) {
        const pct = limit > 0 ? (current / limit) * 100 : 0;
        const level = pct >= 90 ? 'high' : pct >= 70 ? 'medium' : 'low';
        const color = pct >= 90 ? 'var(--accent-red)' : pct >= 70 ? 'var(--accent-amber)' : 'var(--accent-emerald)';
        return `
            <div class="util-bar-wrap">
                <div class="util-bar">
                    <div class="util-bar-fill ${level}" style="width:${Math.min(pct,100)}%"></div>
                </div>
                <span class="util-text" style="color:${color}">${pct.toFixed(0)}%</span>
            </div>`;
    },

    // ---- Status Badge HTML ----
    statusBadge(isActive) {
        return isActive
            ? `<span class="status-badge status-active"><span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:var(--accent-emerald)"></span> Active</span>`
            : `<span class="status-badge status-expired"><span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:var(--accent-red)"></span> Expired</span>`;
    },

    // ---- Service Type Badge ----
    typeBadge(type) {
        const t = (type || 'other').toLowerCase();
        const colors = { web: 'badge-blue', mobile: 'badge-emerald', other: 'badge-amber' };
        return `<span class="card-badge ${colors[t] || 'badge-blue'}">${t.toUpperCase()}</span>`;
    },

    // ---- Breadcrumb ----
    setBreadcrumb(items) {
        const bc = document.getElementById('breadcrumb');
        bc.innerHTML = items.map((item, i) => {
            const isLast = i === items.length - 1;
            const sep = i > 0 ? '<span class="breadcrumb-sep">›</span>' : '';
            if (isLast) {
                return `${sep}<span class="breadcrumb-item">${item.label}</span>`;
            }
            return `${sep}<a href="${item.href}" class="breadcrumb-item">${item.label}</a>`;
        }).join('');
    }
};
