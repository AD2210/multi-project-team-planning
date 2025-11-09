import { Controller } from "https://unpkg.com/@hotwired/stimulus/dist/stimulus.js";

export default class extends Controller {
    static targets = [
        "title","range","user","project","status",
        "viewBlock","formBlock","form",
        "titleInput","startInput","endInput","userInput","projectInput",
        "editBtn","saveBtn"
    ];

    connect() {
        // Bootstrap Modal instance
        const Modal = window.bootstrap?.Modal;
        this.modal = Modal ? new Modal(this.element) : null;
        this.current = null; // { id, title, start_at, end_at, user_id, project_id, mode, updateUrl }
    }

    // Reçoit un event global envoyé par planner-grid
    open(event) {
        const d = event.detail || {};
        this.current = d;

        // Remplir le bloc "vue"
        this.titleTarget.textContent = d.title || 'Créneau';
        this.rangeTarget.textContent = `${this._fmt(d.start_at)} – ${this._fmt(d.end_at)}`;
        this.userTarget.textContent = d.user_label || d.user_id || '';
        this.projectTarget.textContent = d.project_label || d.project_id || '';
        this.statusTarget.textContent = d.status || '—';

        // Init form (caché au début)
        this.titleInputTarget.value = d.title || '';
        this.startInputTarget.value = this._toLocalInput(d.start_at);
        this.endInputTarget.value   = this._toLocalInput(d.end_at);
        this.userInputTarget.value  = d.user_id || '';
        this.projectInputTarget.value = d.project_id || '';

        this.viewBlockTarget.classList.remove('d-none');
        this.formBlockTarget.classList.add('d-none');
        this.editBtnTarget.classList.remove('d-none');
        this.saveBtnTarget.classList.add('d-none');

        this.modal?.show();
    }

    enterEdit() {
        this.viewBlockTarget.classList.add('d-none');
        this.formBlockTarget.classList.remove('d-none');
        this.editBtnTarget.classList.add('d-none');
        this.saveBtnTarget.classList.remove('d-none');
    }

    async save() {
        if (!this.current?.updateUrl) return;
        const payload = {
            title: this.titleInputTarget.value || null,
            start_at: this._fromLocalInput(this.startInputTarget.value),
            end_at:   this._fromLocalInput(this.endInputTarget.value),
            user_id:  this.userInputTarget.value || null,
            project_id: this.projectInputTarget.value || null
        };

        const headers = { 'Content-Type':'application/json' };
        // récupère le jeton CSRF depuis la grille si dispo :
        const grid = document.querySelector('.mptp .mptp-grid-body');
        if (grid) {
            const hn = grid.dataset.plannerGridCsrfHeaderValue;
            const tv = grid.dataset.plannerGridCsrfTokenValue;
            if (hn && tv) headers[hn] = tv;
        }

        const res = await fetch(this.current.updateUrl, {
            method: 'PUT',
            headers,
            body: JSON.stringify(payload)
        });

        if (!res.ok) {
            console.error('Update failed', await res.text());
            return;
        }

        this.modal?.hide();

        // simple: on redemande à la grille de recharger
        grid?.dispatchEvent(new CustomEvent('planner-grid:reload', { bubbles: true }));
    }

    // --- utils ---
    _fmt(iso) {
        // très simple pour l’instant
        return (iso || '').replace('T', ' ').substring(0, 16);
    }
    _toLocalInput(iso) {
        if (!iso) return '';
        // tronque à "YYYY-MM-DDTHH:mm"
        return iso.substring(0,16);
    }
    _fromLocalInput(v) {
        if (!v) return null;
        // si on n’a pas de secondes, on ajoute ":00"
        return v.length === 16 ? v + ':00' : v;
    }
}
