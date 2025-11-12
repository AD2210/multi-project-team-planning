import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        "title","range","user","project","status",
        "viewBlock","formBlock","form",
        "titleInput","startInput","endInput","userInput","projectInput",
        "editBtn","saveBtn"
    ];

    connect() {
        const Modal = window.bootstrap?.Modal;
        this.modal = Modal ? new Modal(this.element) : null;
        this.current = null;
    }

    open(event) {
        const d = event.detail || {};
        this.current = d;

        const title = d.updateUrl ? (d.title || 'Détail du créneau') : 'Nouveau créneau';
        const h = this.element.querySelector('.modal-title');
        if (h) h.textContent = title;

        const base = '/planning/api/slots';
        const body = this.element.querySelector('.modal-body');
        if (body) body.innerHTML = '<div class="text-center py-5">Chargement…</div>';

        // Remplissage manuel des cibles si en mode affichage simple
        if (d.id) {
            fetch(`${base}`)
                .then(r => r.json())
                .then(data => {
                    const found = data.find(x => x.id === d.id);
                    if (!found) throw new Error('Not found');

                    if (body) {
                        this.titleTarget.textContent = found.title;
                        this.rangeTarget.textContent = `${this._fmt(d.start_at)} - ${this._fmt(d.end_at)}`;
                        this.userTarget.textContent = found.user_label || 'Utilisateur';
                        this.projectTarget.textContent = found.project_label || 'Projet';
                        this.statusTarget.textContent = found.status || 'planned';
                    }
                    this.modal?.show();
                })
                .catch(err => {
                    if (body) body.innerHTML = '<div class="alert alert-danger">Erreur de chargement.</div>';
                    this.modal?.show();
                });
        } else {
            // mode création
            this.titleTarget.textContent = d.title || 'Nouveau créneau';
            this.rangeTarget.textContent = `${this._fmt(d.start_at)} - ${this._fmt(d.end_at)}`;
            this.userTarget.textContent = d.user_id ? `User ${d.user_id}` : 'Utilisateur';
            this.projectTarget.textContent = d.project_id ? `Projet ${d.project_id}` : 'Projet';
            this.statusTarget.textContent = d.status || 'planned';
            this.modal?.show();
        }
    }

    enterEdit() {
        this.viewBlockTarget.classList.add('d-none');
        this.formBlockTarget.classList.remove('d-none');
        this.editBtnTarget.classList.add('d-none');
        this.saveBtnTarget.classList.remove('d-none');
        this.saveBtnTarget.textContent = 'Enregistrer';

        const form = this.formBlockTarget;
        form.querySelector('[name="title"]').value = this.current.title || '';
        form.querySelector('[name="start_at"]').value = this.current.start_at || '';
        form.querySelector('[name="end_at"]').value = this.current.end_at || '';
        form.querySelector('[name="user_id"]').value = this.current.user_id || '';
        form.querySelector('[name="project_id"]').value = this.current.project_id || '';
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

        if (this.current?.id) {
            const res = await fetch(`/planning/api/slots/${this.current.id}`, {
                method: 'PUT',
                headers,
                body: JSON.stringify(payload)
            });
            ok = res.ok;
        } else {
            const res2 = await fetch(`/planning/api/slots`, {
                method: 'POST',
                headers,
                body: JSON.stringify(payload)
            });
            ok = res2.ok;
        }

        if (!ok) {
            console.error('Save failed');
            return;
        }

        this.modal?.hide();
        grid?.dispatchEvent(new CustomEvent('planner-grid:reload', { bubbles: true }));
    }

    _fmt(iso) {
        return (iso || '').replace('T', ' ').substring(0, 16);
    }
    _toLocalInput(iso) {
        if (!iso) return '';
        return iso.substring(0,16);
    }
    _fromLocalInput(v) {
        if (!v) return null;
        return v.length === 16 ? v + ':00' : v;
    }
}
