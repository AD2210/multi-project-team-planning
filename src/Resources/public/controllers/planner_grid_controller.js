import { Controller } from "https://unpkg.com/@hotwired/stimulus/dist/stimulus.js";

/**
 * Interactions planner:
 * - mousedown sur la colonne jour => création d’un slot fantôme (drag-création)
 * - drag sur un slot => déplacement
 * - drag sur poignées top/bottom => resize
 * - Alt+drag sur slot => duplication
 * - select + delete => suppression
 *
 * Événements émis (à écouter côté app / Live):
 * - planner:list    { start, end, mode, user_id, project_id, timezone }
 * - planner:create  { date, startMinute, endMinute }
 * - planner:update  { id, date, startMinute, endMinute }
 * - planner:duplicate { id, date, startMinute, endMinute }
 * - planner:select { id }
 */
export default class extends Controller {
    static targets = ["dayCol"];
    static values = {
        minuteStep: Number,
        rowHeight: Number,
        firstMinute: Number,
        // API
        listUrl: String,
        createUrl: String,
        updateUrlTemplate: String,
        duplicateUrlTemplate: String,
        deleteUrlTemplate: String,
        csrfHeader: String,
        csrfToken: String,
        // Contexte
        mode: String,        // 'user' | 'project'
        userId: String,
        projectId: String,
        timezone: String,
        // Intervalle visible
        rangeStart: String, // YYYY-MM-DD
        rangeEnd: String    // YYYY-MM-DD
    };

    // debounce pour activation du drag
    DRAG_THRESHOLD_PX = 6; // seuil de pixel au mouvement qui déclenche le drag
    LONG_PRESS_MS = 350; // durée clic qui déclenche le drag

    connect() {
        this.scrollEl = this.element.parentElement;
        this._binders = [];
        this.active = null;
        this.pending = null;
        this.longPressTimer = null;

        const firstRow = this.element.querySelector('.slot-row');
        this._rowPx = firstRow ? firstRow.getBoundingClientRect().height : (this._rowPx || 28);

        this._loadInitialSlots().catch(console.error);
    }

    // ---- helpers
    _parseMinuteFromIso(iso) {
        // suppose "YYYY-MM-DDTHH:MM:SS"
        const m = iso.match(/T(\d{2}):(\d{2})/);
        if (!m) return this.firstMinuteValue;
        const hh = parseInt(m[1],10);
        const mm = parseInt(m[2],10);
        return hh*60 + mm;
    }
    _renderSlot(slot) {
        // slot attendu: { id, start_at, end_at, date? (facultatif), title?, color? }
        const startMin = this._parseMinuteFromIso(slot.start_at);
        const endMin   = this._parseMinuteFromIso(slot.end_at);
        const dateYmd  = (slot.date) || (slot.start_at.substring(0,10));
        const col = this.element.querySelector(`.mptp-col[data-date="${dateYmd}"]`);
        if (!col) return;

        const top = this._minuteToTop(Math.max(startMin, this.firstMinuteValue));
        const height = this._minuteToTop(endMin) - this._minuteToTop(Math.max(startMin, this.firstMinuteValue));

        const el = this._createSlotEl({ top, height: Math.max(height, this._rowPx), col });
        el.dataset.id = String(slot.id || '');
        el.dataset.startMinute = String(startMin);
        el.dataset.endMinute   = String(endMin);

        // infos UI facultatives
        if (slot.title) {
            el.dataset.title = slot.title;
            const t = el.querySelector('.slot-title'); if (t) t.textContent = slot.title;
        }
        if (slot.status) {
            el.dataset.status = slot.status;
            const bdg = el.querySelector('.slot-badge');
            if (bdg) { bdg.textContent = slot.status; bdg.classList.remove('d-none'); }
        }
        if (slot.user_label) el.dataset.userLabel = slot.user_label;
        if (slot.project_label) el.dataset.projectLabel = slot.project_label;
    }
    _colRect(col) {
        const r = col.getBoundingClientRect();
        const sTop = this.scrollEl?.scrollTop || 0;
        return { top: r.top + sTop, left: r.left, height: r.height };
    }
    _yToMinute(y, col) {
        const rect = this._colRect(col);
        const relY = Math.max(0, y + (this.scrollEl?.scrollTop || 0) - rect.top);
        const steps = Math.floor(relY / this._rowPx);
        return this.firstMinuteValue + steps * this.minuteStepValue;
    }
    _minuteToTop(minute) {
        const steps = (minute - this.firstMinuteValue) / this.minuteStepValue;
        return steps * this._rowPx;
    }
    _snap(minute) {
        const m0 = this.firstMinuteValue;
        const steps = Math.floor((minute - m0) / this.minuteStepValue);
        return m0 + steps * this.minuteStepValue;
    }
    _createSlotEl({ top, height, col }) {
        const el = document.createElement('div');
        el.className = 'slot';
        el.style.top = `${top}px`;
        el.style.height = `${height}px`;

        // handles resize déjà existants (garde ton code)
        const t = document.createElement('div'); t.className = 'slot-handle top';
        const b = document.createElement('div'); b.className = 'slot-handle bottom';
        el.appendChild(t); el.appendChild(b);

        // corps (titre + badge)
        const body = document.createElement('div');
        body.className = 'slot-body';
        // si dataset.title est défini plus tard, on le remplira
        body.innerHTML = `<strong class="slot-title"></strong> <span class="badge bg-secondary slot-badge d-none"></span>`;
        el.appendChild(body);

        col.appendChild(el);
        return el;
    }
    _emit(name, detail) {
        this.element.dispatchEvent(new CustomEvent(name, { bubbles: true, detail }));
    }
    _on = (el, ev, cb) => { el.addEventListener(ev, cb); this._binders.push(()=>el.removeEventListener(ev, cb)); }
    _offAll() {
        if (this._dragBound) {
            document.removeEventListener('pointermove', this._onMove);
            document.removeEventListener('pointerup',   this._onUp);
            this._dragBound = false;
        }
        // Si tu avais déjà ces lignes, garde-les :
        if (this.longPressTimer) {
            clearTimeout(this.longPressTimer);
            this.longPressTimer = null;
        }
        this._clearHint();
    }
    _colFromPoint(clientX, clientY) {
        // pendant le drag on met pointer-events:none sur le slot pour pouvoir "voir" la colonne
        const el = document.elementFromPoint(clientX, clientY);
        return el ? el.closest('.mptp-col') : null;
    }
    _autoScroll(clientY) {
        if (!this.scrollEl) return;
        const rect = this.scrollEl.getBoundingClientRect();
        const pad = 30;         // zone sensible
        const step = 24;        // pixels de scroll par tick
        if (clientY < rect.top + pad)  this.scrollEl.scrollTop -= step;
        if (clientY > rect.bottom - pad) this.scrollEl.scrollTop += step;
    }
    _select(el) {
        if (!el) return;
        this._clearSelection();
        el.classList.add('is-selected');
        this._selected = el;
    }
    _clearSelection() {
        this.element.querySelectorAll('.slot.is-selected').forEach(n => n.classList.remove('is-selected'));
        this._selected = null;
    }
    _stepFromY(y, col) {
        const rect = this._colRect(col);
        const relY = Math.max(0, y + (this.scrollEl?.scrollTop || 0) - rect.top);
        return Math.round(relY / this._rowPx);
    }
    _minuteFromStep(step) {
        return this.firstMinuteValue + step * this.minuteStepValue;
    }
    _topFromMinute(minute) {
        const step = (minute - this.firstMinuteValue) / this.minuteStepValue;
        return step * this._rowPx;
    }

    // ===== Helpers API =====
    _headers() {
        const h = { 'Content-Type': 'application/json', 'Accept': 'application/json' };
        if (this.csrfTokenValue) h[this.csrfHeaderValue || 'X-CSRF-TOKEN'] = this.csrfTokenValue;
        return h;
    }
    // 'YYYY-MM-DDTHH:MM:SS' en local + timezone séparée
    _composeLocalDateTime(dateYmd, minute) {
        const hh = String(Math.floor(minute / 60)).padStart(2,'0');
        const mm = String(minute % 60).padStart(2,'0');
        return `${dateYmd}T${hh}:${mm}:00`;
    }
    _basePayload(a) {
        const p = {
            start_at: this._composeLocalDateTime(a.date, a.startMinute),
            end_at:   this._composeLocalDateTime(a.date, a.endMinute),
            timezone: this.timezoneValue || 'Europe/Paris'
        };
        if (this.modeValue === 'user' && this.userIdValue)    p.user_id = this.userIdValue;
        if (this.modeValue === 'project' && this.projectIdValue) p.project_id = this.projectIdValue;
        return p;
    }
    _urlWithId(tpl, id) {
        if (!tpl) return '';
        return tpl.replace('{id}', id).replace(':id', id);
    }
    async _loadInitialSlots() {
        if (!this.listUrlValue) return;

        const params = new URLSearchParams({
            start: this.rangeStartValue,
            end:   this.rangeEndValue,
            mode:  this.modeValue || '',
            user_id: this.userIdValue || '',
            project_id: this.projectIdValue || '',
            timezone: this.timezoneValue || ''
        });

        const res = await fetch(`${this.listUrlValue}?${params.toString()}`, {
            headers: { 'Accept': 'application/json' }
        });
        if (!res.ok) {
            console.error('List failed', await res.text());
            return;
        }
        const json = await res.json().catch(()=>[]);
        if (Array.isArray(json)) {
            json.forEach(s => this._renderSlot(s));
        } else if (Array.isArray(json.data)) {
            json.data.forEach(s => this._renderSlot(s));
        }
    }
    async _apiCreate(a, elForId) {
        if (!this.createUrlValue) return;
        const res = await fetch(this.createUrlValue, { method: 'POST', headers: this._headers(), body: JSON.stringify(this._basePayload(a)) });
        if (!res.ok) { console.error('Create failed', await res.text()); return; }
        const json = await res.json().catch(()=>({}));
        if (json && json.id && elForId) elForId.dataset.id = String(json.id);
    }
    async _apiUpdate(a) {
        const id = a.id || a.el?.dataset?.id;
        const url = this._urlWithId(this.updateUrlTemplateValue, id);
        if (!id || !url) return;
        const res = await fetch(url, { method: 'PUT', headers: this._headers(), body: JSON.stringify(this._basePayload(a)) });
        if (!res.ok) { console.error('Update failed', await res.text()); }
    }
    async _apiDuplicate(a, fromId) {
        const url = this._urlWithId(this.duplicateUrlTemplateValue, fromId);
        if (url) {
            const res = await fetch(url, { method: 'POST', headers: this._headers(), body: JSON.stringify(this._basePayload(a)) });
            if (!res.ok) { console.error('Duplicate failed', await res.text()); return; }
            const json = await res.json().catch(()=>({}));
            if (json && json.id && a.el) a.el.dataset.id = String(json.id);
        } else if (this.createUrlValue) {
            // fallback: pas de route duplicate => on fait un create
            await this._apiCreate(a, a.el);
        }
    }
    async _apiDelete(id) {
        const url = this._urlWithId(this.deleteUrlTemplateValue, id);
        if (!id || !url) return { ok: true }; // si pas d'API delete, on considère OK pour slots non persistés
        const res = await fetch(url, { method: 'DELETE', headers: this._headers() });
        if (!res.ok) throw new Error(await res.text());
        return { ok: true };
    }

    // ---- Drag and drop (create/drag/duplicate/resize)
    pointerDown(e) {
        if (e.button !== 0) return;
        // focus immédiat de la grille (pour keydown Delete)
        if (document.activeElement !== this.element) this.element.focus({ preventScroll: true });

        const col = e.target.closest('.mptp-col');
        const onSlot = e.target.closest('.slot');
        if (!col) return;

        // Poignées resize -> drag immédiat (pas de seuil)
        const handle = e.target.closest('.slot-handle');
        if (handle && onSlot) {
            const id = onSlot.dataset.id || null;
            const colDate = col.dataset.date;
            const start = parseInt(onSlot.dataset.startMinute, 10);
            const end   = parseInt(onSlot.dataset.endMinute, 10);
            this.active = {
                mode: handle.classList.contains('top') ? 'resize-top' : 'resize-bottom',
                el: onSlot, col, id, date: colDate,
                startMinute: start, endMinute: end,
                baseStartMinute: start, baseEndMinute: end,
                baseY: e.clientY
            };
            this._updateHintAtSlot(this.active);
            this.element.classList.add('is-dragging');
            this._bindDrag();
            e.preventDefault();
            return;
        }

        // Clic sur slot -> on prépare un drag "pending" (seuil/long-press)
        if (onSlot) {
            const id = onSlot.dataset.id || null;
            const colDate = col.dataset.date;
            const start = parseInt(onSlot.dataset.startMinute, 10);
            const end   = parseInt(onSlot.dataset.endMinute, 10);
            const mode = e.altKey ? 'duplicate' : 'drag';

            this.pending = {
                type: mode, el: onSlot, id, col, date: colDate,
                baseStartMinute: start, baseEndMinute: end,
                startX: e.clientX, startY: e.clientY,
                moved: false
            };

            // long press -> active drag sans bouger
            this.longPressTimer = setTimeout(() => {
                if (this.pending) this._activateDragFromPending(e);
            }, this.LONG_PRESS_MS);

            this._bindDrag();
            e.preventDefault();
            return;
        }

        // Clic sur vide -> création "pending" (drag-create avec seuil/long-press)
        const date = col.dataset.date;
        const step0 = this._stepFromY(e.clientY, col);
        const m0 = this._minuteFromStep(step0);

        this.pending = {
            type: 'create',
            col, date,
            startStep: step0,
            startMinute: m0,
            startX: e.clientX, startY: e.clientY,
            ghost: null
        };

        this.longPressTimer = setTimeout(() => {
            if (this.pending && this.pending.type === 'create') {
                // active sans déplacement : crée le ghost
                const top = this._topFromMinute(m0);
                const ghost = this._createSlotEl({ top, height: this._rowPx, col });
                ghost.classList.add('slot--ghost');
                this.active = {
                    mode: 'create',
                    el: ghost, col, id: null, date,
                    startMinute: m0, endMinute: m0 + this.minuteStepValue,
                    startStep: step0
                };
                this._updateHintAtSlot(this.active);
                this.pending = null;
                this.element.classList.add('is-dragging');
            }
        }, this.LONG_PRESS_MS);

        this._bindDrag();
        e.preventDefault();
    }
    _bindDrag() {
        if (this._dragBound) return;
        this._onMove = (e) => this.pointerMove(e);
        this._onUp   = (e) => this.pointerUp(e);

        document.addEventListener('pointermove', this._onMove, { passive: false });
        document.addEventListener('pointerup',   this._onUp,   { passive: false });

        this._dragBound = true;
    }
    pointerMove(e) {
        // auto-scroll
        this._autoScroll(e.clientY);

        // Si un drag est déjà actif -> logique existante
        if (this.active) {
            const a = this.active;
            if (a.mode === 'drag' || a.mode === 'duplicate') {
                const newCol = this._colFromPoint(e.clientX, e.clientY);
                if (newCol && newCol !== a.col) { a.col = newCol; a.date = newCol.dataset.date; newCol.appendChild(a.el); }
                const deltaSteps = Math.round((e.clientY - a.baseY) / this._rowPx);
                const shift = deltaSteps * this.minuteStepValue;
                a.startMinute = a.baseStartMinute + shift;
                a.endMinute   = a.baseEndMinute + shift;
                a.el.style.top = `${this._topFromMinute(a.startMinute)}px`;
                this._updateHintAtSlot(this.active);
                return;
            }
            if (a.mode === 'create') {
                const step = this._stepFromY(e.clientY, a.col);
                const endMin = this._minuteFromStep(Math.max(step, a.startStep + 1));
                a.endMinute = endMin;
                const top = this._topFromMinute(Math.min(a.startMinute, a.endMinute - this.minuteStepValue));
                const height = this._topFromMinute(a.endMinute) - top;
                a.el.style.top = `${top}px`;
                a.el.style.height = `${Math.max(height, this._rowPx)}px`;
                this._updateHintAtSlot(this.active);
                return;
            }
            if (a.mode === 'resize-top' || a.mode === 'resize-bottom') {
                const deltaSteps = Math.round((e.clientY - a.baseY) / this._rowPx);
                const shift = deltaSteps * this.minuteStepValue;
                if (a.mode === 'resize-top') {
                    a.startMinute = Math.min(a.baseStartMinute + shift, a.baseEndMinute - this.minuteStepValue);
                    const top = this._topFromMinute(a.startMinute);
                    const height = this._topFromMinute(a.endMinute) - top;
                    a.el.style.top = `${top}px`;
                    a.el.style.height = `${Math.max(height, this._rowPx)}px`;
                    this._updateHintAtSlot(this.active);
                    return;
                }
                if (a.mode === 'resize-bottom') {
                    a.endMinute = Math.max(a.baseEndMinute + shift, a.baseStartMinute + this.minuteStepValue);
                    const height = this._topFromMinute(a.endMinute) - this._topFromMinute(a.startMinute);
                    a.el.style.height = `${Math.max(height, this._rowPx)}px`;
                    this._updateHintAtSlot(this.active);
                    return;
                }
            }
            return;
        }

        // Si on est en "pending", on attend seuil ou long-press
        if (this.pending) {
            const dx = e.clientX - this.pending.startX;
            const dy = e.clientY - this.pending.startY;
            const dist = Math.hypot(dx, dy);
            if (dist >= this.DRAG_THRESHOLD_PX) {
                // seuil franchi -> active drag
                clearTimeout(this.longPressTimer);
                this.longPressTimer = null;
                this._activateDragFromPending(e);
            }
        }
    }
    async pointerUp(e) {
        // stop long-press si en cours
        if (this.longPressTimer) {
            clearTimeout(this.longPressTimer);
            this.longPressTimer = null;
        }

        // si un drag est actif -> finalize
        if (this.active) {
            const a = this.active;
            this._offAll();
            this.element.classList.remove('is-dragging');
            if (a.el) a.el.style.pointerEvents = '';

            // changé ?
            const changedDate = (a.date !== (a.baseDate || a.date)); // a.baseDate utilisé si tu le poses
            const changedStart = (typeof a.baseStartMinute === 'number' && a.startMinute !== a.baseStartMinute);
            const changedEnd   = (typeof a.baseEndMinute === 'number'   && a.endMinute   !== a.baseEndMinute);

            if (a.mode === 'create') {
                a.el.classList.remove('slot--ghost');
                a.el.dataset.startMinute = String(a.startMinute);
                a.el.dataset.endMinute   = String(a.endMinute);
                await this._apiCreate(a, a.el);
                this._select(a.el);
                this._clearHint();
                this.active = null;
                return;
            }

            if (a.mode === 'drag' || a.mode === 'duplicate') {
                a.el.dataset.startMinute = String(a.startMinute);
                a.el.dataset.endMinute   = String(a.endMinute);
                if (a.mode === 'duplicate') {
                    await this._apiDuplicate(a, a.id);
                } else if (changedDate || changedStart || changedEnd) {
                    await this._apiUpdate(a);
                }
                this._clearHint();
                this.active = null;
                return;
            }

            if (a.mode === 'resize-top' || a.mode === 'resize-bottom') {
                a.el.dataset.startMinute = String(a.startMinute);
                a.el.dataset.endMinute   = String(a.endMinute);
                if (changedStart || changedEnd) {
                    await this._apiUpdate(a);
                }
                this._clearHint();
                this.active = null;
                return;
            }
            return;
        }

        // pas de drag -> c'était un clic
        if (this.pending) {
            this._offAll();
            const p = this.pending;
            this.pending = null;

            // clic sur slot => sélection
            if (p.el) {
                this._select(p.el);
                return;
            }
            // clic sur vide => rien pour l’instant (plus tard: popup create)
            return;
        }
    }
    updateContext(event) {
        const { userId, projectId } = event.detail || {};
        if (typeof userId !== 'undefined')   this.userIdValue = String(userId);
        if (typeof projectId !== 'undefined') this.projectIdValue = String(projectId);
        // reload simple : supprimer les slots actuels puis recharger
        this.element.querySelectorAll('.slot').forEach(el => el.remove());
        this._loadInitialSlots().catch(console.error);
    }
    selectSlot(e) {
        // ignore si on clique hors slot
        const slot = e.target.closest('.slot');
        if (!slot) { this._clearSelection(); return; }
        this._select(slot);
    }
    openDetail(e) {
        const slot = e.target.closest('.slot');
        if (!slot) { this._clearSelection(); return; }

        this._select(slot);

        // Datas pour le modal
        const id = slot.dataset.id || '';
        const col = slot.closest('.mptp-col');
        const ymd = col?.dataset.date || '';
        const startMin = parseInt(slot.dataset.startMinute || '0', 10);
        const endMin   = parseInt(slot.dataset.endMinute   || '0', 10);

        const startIso = `${ymd}T${this._fmtMinute(startMin)}:00`;
        const endIso   = `${ymd}T${this._fmtMinute(endMin)}:00`;

        // URL update pour ce slot
        const upd = this._urlWithId(this.updateUrlTemplateValue, id);

        // récupérer quelques étiquettes (si tu les stockes en data-* sur le slot)
        const title = slot.dataset.title || '';
        const userLabel = slot.dataset.userLabel || '';
        const projectLabel = slot.dataset.projectLabel || '';
        const status = slot.dataset.status || '';

        window.dispatchEvent(new CustomEvent('planner-grid:open-detail', {
            detail: {
                id, title, start_at: startIso, end_at: endIso,
                user_id: this.userIdValue || '', project_id: this.projectIdValue || '',
                user_label: userLabel, project_label: projectLabel, status,
                updateUrl: upd, mode: this.modeValue
            }
        }));
    }
    keyDown(e) {
        // Empêche les comportements par défaut gênants
        if (e.key !== 'Delete' && e.key !== 'Escape' && e.key !== 'F5' && e.key !== 'Control') {
            e.preventDefault();            // évite le scroll page
            return;
        }

        if (e.key === 'Delete') {
            e.preventDefault();
            this._deleteSelected().catch(console.error);
            return;
        }

        // ESC → déselection
        if (e.key === 'Escape') {
            e.preventDefault();
            this._clearSelection();
        }
    }
    async _deleteSelected() {
        const el = this._selected;
        if (!el) return;
        const id = el.dataset.id || '';

        // Optimistic UI: retirer du DOM, mais garder une ancre pour rollback
        const parent = el.parentElement;
        const nextSibling = el.nextSibling;
        parent.removeChild(el);
        this._selected = null;

        try {
            if (id) {
                await this._apiDelete(id);
            } else {
                // Slot non persisté: rien à faire côté API
            }
        } catch (err) {
            console.error('Delete failed', err);
            // rollback
            if (nextSibling) parent.insertBefore(el, nextSibling);
            else parent.appendChild(el);
            this._select(el);
        }
    }
    _activateDragFromPending(e) {
        if (!this.pending) return;
        const p = this.pending;
        if (p.type === 'drag' || p.type === 'duplicate') {
            let el = p.el;
            if (p.type === 'duplicate') {
                el = p.el.cloneNode(true);
                el.dataset.id = '';
                p.el.parentElement.appendChild(el);
            }
            el.style.pointerEvents = 'none';
            this.active = {
                mode: p.type,
                el, col: p.col, id: p.id, date: p.date,
                startMinute: p.baseStartMinute, endMinute: p.baseEndMinute,
                baseStartMinute: p.baseStartMinute, baseEndMinute: p.baseEndMinute,
                baseY: e.clientY,
            };
            this._updateHintAtSlot(this.active);
            this.pending = null;
            this.element.classList.add('is-dragging');
            return;
        }
        if (p.type === 'create') {
            const top = this._topFromMinute(p.startMinute);
            const ghost = this._createSlotEl({ top, height: this._rowPx, col: p.col });
            ghost.classList.add('slot--ghost');
            this.active = {
                mode: 'create',
                el: ghost, col: p.col, id: null, date: p.date,
                startMinute: p.startMinute, endMinute: p.startMinute + this.minuteStepValue,
                startStep: p.startStep
            };
            this._updateHintAtSlot(this.active);
            this.pending = null;
            this.element.classList.add('is-dragging');
        }
    }

    // Helpers Hint
    _fmtMinute(minute) {
        const h = String(Math.floor(minute / 60)).padStart(2,'0');
        const m = String(minute % 60).padStart(2,'0');
        return `${h}:${m}`;
    }
    _fmtDuration(mins) {
        const sign = mins < 0 ? '-' : '';
        mins = Math.abs(mins);
        const h = Math.floor(mins / 60);
        const m = mins % 60;
        return h ? `${sign}${h}h${String(m).padStart(2,'0')}` : `${sign}${m}min`;
    }
    _ensureHint() {
        if (this._hintEl) return this._hintEl;
        const el = document.createElement('div');
        el.className = 'mptp-drag-hint';
        el.textContent = '';
        // parent = wrapper .mptp (pour rester dans la zone scrollable)
        const root = this.element.closest('.mptp') || this.element;
        root.appendChild(el);
        this._hintEl = el;
        return el;
    }
    _updateHint(a, clientX, clientY, labelPrefix = '') {
        const el = this._ensureHint();
        const rect = (this.element.closest('.mptp') || this.element).getBoundingClientRect();
        // contenu : HH:mm–HH:mm (durée) + mode
        const start = this._fmtMinute(a.startMinute);
        const end   = this._fmtMinute(a.endMinute);
        const dur   = this._fmtDuration(a.endMinute - a.startMinute);
        const modeTxt = labelPrefix || (a.mode === 'duplicate' ? 'Dupliquer' :
            a.mode === 'drag' ? 'Déplacer' :
                a.mode?.startsWith('resize') ? 'Redimensionner' :
                    a.mode === 'create' ? 'Créer' : '');
        this._hintEl.innerHTML = `${start}&nbsp;–&nbsp;${end} <small>(${dur})</small>${modeTxt ? `&nbsp;<small>${modeTxt}</small>` : ''}`;

        // positionner au-dessus du pointeur, dans le repère du root
        const x = clientX - rect.left;
        const y = clientY - rect.top - 8; // petit décalage vers le haut
        this._hintEl.style.left = `${x}px`;
        this._hintEl.style.top  = `${y}px`;
        this._hintEl.style.display = 'block';
    }
    _ensureSlotHint(a) {
        if (!a || !a.el) return null;
        let h = a.el.querySelector('.slot-hint');
        if (!h) {
            h = document.createElement('div');
            h.className = 'slot-hint';
            a.el.appendChild(h);
        }
        return h;
    }
    _updateHintAtSlot(a, labelPrefix = '') {
        if (!a || !a.el) return;
        const h = this._ensureSlotHint(a);
        if (!h) return;

        const start = this._fmtMinute(a.startMinute);
        const end   = this._fmtMinute(a.endMinute);
        const dur   = this._fmtDuration(a.endMinute - a.startMinute);
        const modeTxt = labelPrefix || (a.mode === 'duplicate' ? 'Dupliquer'
            : a.mode === 'drag' ? 'Déplacer'
                : a.mode?.startsWith('resize') ? 'Redimensionner'
                    : a.mode === 'create' ? 'Créer' : '');

        h.innerHTML = `${start}&nbsp;–&nbsp;${end} <small>(${dur})</small>${modeTxt ? `&nbsp;<small>${modeTxt}</small>` : ''}`;
        h.style.display = 'block';
    }
    _clearHint() {
        // supprime le hint du slot actif (s’il existe)
        if (this.active?.el) {
            const sh = this.active.el.querySelector('.slot-hint');
            if (sh) sh.remove();
        }
        // fallback: si un ancien hint global existait
        if (this._hintEl) {
            this._hintEl.remove();
            this._hintEl = null;
        }
    }
}
