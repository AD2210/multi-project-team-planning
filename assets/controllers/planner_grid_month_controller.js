import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        mode: String,
        userId: String,
        projectId: String,
        timezone: String,
        csrfHeader: String,
        csrfToken: String,
    };

    connect() {
        this._selected = null;
    }

    createSlot(e) {
        const btn = e.currentTarget;
        const date = btn.dataset.date;
        if (!date) return;

        const cell = btn.closest('.mptp-day-cell');
        const ul = cell?.querySelector('ul');
        if (!ul) return;

        const li = document.createElement('li');
        li.className = 'mptp-slot-item slot--ghost d-flex align-items-center gap-1 py-1 px-2 rounded border border-primary bg-light';
        li.innerHTML = `
            <span class="dot" style="background-color: var(--bs-primary);"></span>
            <span class="slot-time">08:00 - 09:00</span>
            <span class="slot-title text-muted fst-italic">En création…</span>
        `;
        ul.appendChild(li);

        this._openDetailModal(date, '08:00', '09:00');
    }

    _openDetailModal(date, start, end) {
        const startIso = `${date}T${start}:00`;
        const endIso   = `${date}T${end}:00`;

        window.dispatchEvent(new CustomEvent('planner-grid:open-detail', {
            detail: {
                start_at: startIso,
                end_at: endIso,
                user_id: this.userIdValue || '',
                project_id: this.projectIdValue || '',
                mode: this.modeValue,
                title: '',
                status: '',
                id: ''
            }
        }));
    }

    selectSlot(e) {
        this._clearSelection();
        const li = e.currentTarget.closest('.mptp-slot-item');
        if (!li) return;
        li.classList.add('selected');
        this._selected = li;
    }

    keyDown(e) {
        if (e.key === 'Delete' && this._selected) {
            this._selected.remove();
            this._selected = null;
        }

        if (e.key === 'Escape') {
            this._clearSelection();
        }
    }

    _clearSelection() {
        if (this._selected) {
            this._selected.classList.remove('selected');
            this._selected = null;
        }
    }
}
