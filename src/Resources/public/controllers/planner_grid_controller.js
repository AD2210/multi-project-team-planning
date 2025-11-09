import { Controller } from "https://unpkg.com/@hotwired/stimulus/dist/stimulus.js";

/**
 * Interactions planner:
 * - mousedown sur la colonne jour => création d’un slot fantôme (drag-création)
 * - drag sur un slot => déplacement
 * - drag sur poignées top/bottom => resize
 * - Alt+drag sur slot => duplication
 *
 * Événements émis (à écouter côté app / Live):
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

    connect() {
        // cache scroll container (le parent direct est .mptp-grid-scroll)
        this.scrollEl = this.element.parentElement;
        this._binders = [];
        this.active = null; // {mode:'create'|'drag'|'resize-top'|'resize-bottom'|'duplicate', el, col, id, startMinute, endMinute, startY}
        console.log('listUrl', this.listUrlValue, 'range', this.rangeStartValue, this.rangeEndValue);
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

        const el = this._createSlotEl({ top, height: Math.max(height, this.rowHeightValue), col });
        el.dataset.id = String(slot.id || '');
        el.dataset.startMinute = String(startMin);
        el.dataset.endMinute   = String(endMin);
        el.title = slot.title || '';
        if (slot.color) {
            el.style.background = slot.color;
            el.style.borderColor = slot.color;
        }
    }
    _colRect(col) {
        const r = col.getBoundingClientRect();
        const sTop = this.scrollEl?.scrollTop || 0;
        return { top: r.top + sTop, left: r.left, height: r.height };
    }
    _yToMinute(y, col) {
        const rect = this._colRect(col);
        const relY = Math.max(0, y + (this.scrollEl?.scrollTop || 0) - rect.top);
        const steps = Math.floor(relY / this.rowHeightValue);
        return this.firstMinuteValue + steps * this.minuteStepValue;
    }
    _minuteToTop(minute) {
        const steps = (minute - this.firstMinuteValue) / this.minuteStepValue;
        return steps * this.rowHeightValue;
    }
    _snap(minute) {
        const m0 = this.firstMinuteValue;
        const steps = Math.floor((minute - m0) / this.minuteStepValue);
        return m0 + steps * this.minuteStepValue;
    }
    _createSlotEl({ top, height, col }) {
        const el = document.createElement('div');
        el.className = 'slot';
        el.style.position = 'absolute';
        el.style.left = '6px';
        el.style.right = '6px';
        el.style.top = `${top}px`;
        el.style.height = `${Math.max(height, this.rowHeightValue)}px`;
        el.dataset.role = 'slot';
        el.innerHTML = `
      <div class="slot-body" style="width:100%;height:100%;"></div>
      <div class="slot-handle top" style="position:absolute;left:0;right:0;top:-4px;height:8px;cursor:ns-resize;"></div>
      <div class="slot-handle bottom" style="position:absolute;left:0;right:0;bottom:-4px;height:8px;cursor:ns-resize;"></div>
    `;
        col.appendChild(el);
        return el;
    }
    _emit(name, detail) {
        this.element.dispatchEvent(new CustomEvent(name, { bubbles: true, detail }));
    }
    _on = (el, ev, cb) => { el.addEventListener(ev, cb); this._binders.push(()=>el.removeEventListener(ev, cb)); };
    _offAll = () => { while (this._binders.length) this._binders.pop()(); };
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

    // ---- Drag and drop (create/drag/duplicate/resize)
    pointerDown(e) {
        if (e.button !== 0) return;
        const col = e.target.closest('.mptp-col');
        const onSlot = e.target.closest('.slot');
        if (!col) return;

        const handle = e.target.closest('.slot-handle');
        if (handle && onSlot) {
            const id = onSlot.dataset.id || null;
            const colDate = col.dataset.date;
            const start = parseInt(onSlot.dataset.startMinute, 10);
            const end   = parseInt(onSlot.dataset.endMinute, 10);
            this.active = { mode: handle.classList.contains('top') ? 'resize-top':'resize-bottom', el:onSlot, col, id, date: colDate, startMinute:start, endMinute:end, startY: e.clientY };
            this._bindDrag(); e.preventDefault(); return;
        }

        if (onSlot) {
            const id = onSlot.dataset.id || null;
            const colDate = col.dataset.date;
            const start = parseInt(onSlot.dataset.startMinute, 10);
            const end   = parseInt(onSlot.dataset.endMinute, 10);
            const mode = e.altKey ? 'duplicate' : 'drag';
            let el = onSlot;
            if (mode === 'duplicate') { el = onSlot.cloneNode(true); el.dataset.id=''; onSlot.parentElement.appendChild(el); }
            el.style.pointerEvents = 'none';
            this.active = { mode, el, col, id, date: colDate, startMinute:start, endMinute:end, startY:e.clientY, grabOffset: e.clientY - el.getBoundingClientRect().top };
            this._bindDrag(); e.preventDefault(); return;
        }

        // create
        const date = col.dataset.date;
        const m0 = this._snap(this._yToMinute(e.clientY, col));
        const top = this._minuteToTop(m0);
        const ghost = this._createSlotEl({ top, height: this.rowHeightValue, col });
        ghost.classList.add('slot--ghost');
        this.active = { mode:'create', el:ghost, col, id:null, date, startMinute:m0, endMinute:m0 + this.minuteStepValue, startY:e.clientY };
        this._bindDrag(); e.preventDefault();
    }

    _bindDrag() { this._on(window,'pointermove', e=>this.pointerMove(e)); this._on(window,'pointerup', e=>this.pointerUp(e)); }

    pointerMove(e) {
        if (!this.active) return;
        const a = this.active;
        this._autoScroll(e.clientY);

        if (a.mode === 'drag' || a.mode === 'duplicate') {
            const newCol = this._colFromPoint(e.clientX, e.clientY);
            if (newCol && newCol !== a.col) { a.col = newCol; a.date = newCol.dataset.date; newCol.appendChild(a.el); }
            const colRect = this._colRect(a.col);
            const desiredTop = e.clientY - colRect.top - (a.grabOffset || 0);
            const desiredMinute = this._snap(this.firstMinuteValue + Math.round(desiredTop / this.rowHeightValue) * this.minuteStepValue);
            const duration = a.endMinute - a.startMinute;
            a.startMinute = desiredMinute;
            a.endMinute   = desiredMinute + duration;
            a.el.style.top = `${this._minuteToTop(a.startMinute)}px`;
            return;
        }

        if (a.mode === 'create') {
            const m = this._snap(this._yToMinute(e.clientY, a.col));
            a.endMinute = Math.max(m, a.startMinute + this.minuteStepValue);
            const top = this._minuteToTop(Math.min(a.startMinute, a.endMinute - this.minuteStepValue));
            const height = this._minuteToTop(a.endMinute) - top;
            a.el.style.top = `${top}px`; a.el.style.height = `${height}px`;
            return;
        }

        if (a.mode === 'resize-top') {
            const m = this._snap(this._yToMinute(e.clientY, a.col));
            a.startMinute = Math.min(m, a.endMinute - this.minuteStepValue);
            const top = this._minuteToTop(a.startMinute);
            const height = this._minuteToTop(a.endMinute) - top;
            a.el.style.top = `${top}px`; a.el.style.height = `${height}px`;
            return;
        }

        if (a.mode === 'resize-bottom') {
            const m = this._snap(this._yToMinute(e.clientY, a.col));
            a.endMinute = Math.max(m, a.startMinute + this.minuteStepValue);
            const height = this._minuteToTop(a.endMinute) - this._minuteToTop(a.startMinute);
            a.el.style.height = `${height}px`;
        }
    }

    async pointerUp(e) {
        if (!this.active) return;
        const a = this.active;
        this._offAll();
        if (a.el) a.el.style.pointerEvents = '';

        if (a.mode === 'create') {
            a.el.classList.remove('slot--ghost');
            a.el.dataset.startMinute = String(a.startMinute);
            a.el.dataset.endMinute   = String(a.endMinute);
            // API
            await this._apiCreate(a, a.el);
            return this.active = null;
        }

        if (a.mode === 'drag') {
            a.el.dataset.startMinute = String(a.startMinute);
            a.el.dataset.endMinute   = String(a.endMinute);
            await this._apiUpdate(a);
            return this.active = null;
        }

        if (a.mode === 'duplicate') {
            a.el.dataset.startMinute = String(a.startMinute);
            a.el.dataset.endMinute   = String(a.endMinute);
            await this._apiDuplicate(a, a.id);
            return this.active = null;
        }

        if (a.mode === 'resize-top' || a.mode === 'resize-bottom') {
            a.el.dataset.startMinute = String(a.startMinute);
            a.el.dataset.endMinute   = String(a.endMinute);
            await this._apiUpdate(a);
            return this.active = null;
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
}
