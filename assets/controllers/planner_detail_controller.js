import { Controller } from '@hotwired/stimulus';

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

        // Titre du header
        const title = d.updateUrl ? (d.title || 'Détail du créneau') : 'Nouveau créneau';
        const h = this.element.querySelector('.modal-title');
        if (h) h.textContent = title;

        // URL du form (GET) — base configurable via data-attr, fallback par défaut
        const base = this.element.dataset.plannerDetailFormBaseUrlValue || '/planning/forms/slot';
        const qs = new URLSearchParams({
            title:     d.title || '',
            start_at:  d.start_at || '',
            end_at:    d.end_at || '',
            user_id:   d.user_id || '',
            project_id:d.project_id || '',
            status:    d.status || ''
        }).toString();
        const url = d.id ? `${base}/${encodeURIComponent(d.id)}?${qs}` : `${base}?${qs}`;

        // Charge le Twig et remplace le contenu de la modal
        const body = this.element.querySelector('.modal-body');
        if (body) body.innerHTML = '<div class="text-center py-5">Chargement…</div>';

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.text())
            .then(html => {
                if (body) body.innerHTML = html;
                this.modal?.show();
            })
            .catch(err => {
                console.error('Form load failed', err);
                if (body) body.innerHTML = '<div class="alert alert-danger">Impossible de charger le formulaire.</div>';
                this.modal?.show();
            });
    }
    enterEdit() {
        this.viewBlockTarget.classList.add('d-none');
        this.formBlockTarget.classList.remove('d-none');
        this.editBtnTarget.classList.add('d-none');
        this.saveBtnTarget.classList.remove('d-none');
        this.saveBtnTarget.textContent = 'Enregistrer';
    }
    async save() {
        const payload = {
            title: this.titleInputTarget.value || null,
            start_at: this._fromLocalInput(this.startInputTarget.value),
            end_at:   this._fromLocalInput(this.endInputTarget.value),
            user_id:  this.userInputTarget.value || null,
            project_id: this.projectInputTarget.value || null
        };

        const headers = { 'Content-Type':'application/json' };
        const grid = document.querySelector('.mptp .mptp-grid-body');
        if (grid) {
            const hn = grid.dataset.plannerGridCsrfHeaderValue;
            const tv = grid.dataset.plannerGridCsrfTokenValue;
            if (hn && tv) headers[hn] = tv;
        }

        let ok = false;

        // 1) Update si possible
        if (this.current?.updateUrl) {
            const res = await fetch(this.current.updateUrl, {
                method: 'PUT',
                headers,
                body: JSON.stringify(payload)
            });
            ok = res.ok;
        }

        // 2) Sinon fallback en create (si route dispo)
        if (!ok && grid?.dataset.plannerGridCreateUrlValue) {
            const res2 = await fetch(grid.dataset.plannerGridCreateUrlValue, {
                method: 'POST',
                headers,
                body: JSON.stringify(payload)
            });
            ok = res2.ok;
        }

        if (!ok) {
            console.error('Save failed (neither update nor create succeeded)');
            return;
        }

        this.modal?.hide();
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
