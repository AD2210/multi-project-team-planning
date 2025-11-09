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
    };

    connect() {
        // cache scroll container (le parent direct est .mptp-grid-scroll)
        this.scrollEl = this.element.parentElement;
        this._binders = [];
        this.active = null; // {mode:'create'|'drag'|'resize-top'|'resize-bottom'|'duplicate', el, col, id, startMinute, endMinute, startY}
    }

    // ---- helpers
    _colRect(col) {
        const r = col.getBoundingClientRect();
        const sTop = this.scrollEl?.scrollTop || 0;
        return { top: r.top + sTop, left: r.left, height: r.height };
    }
    _yToMinute(y, col) {
        const rect = this._colRect(col);
        const relY = Math.max(0, y + (this.scrollEl?.scrollTop || 0) - rect.top);
        const steps = relY / this.rowHeightValue;
        const minute = this.firstMinuteValue + Math.round(steps) * this.minuteStepValue;
        return Math.max(this.firstMinuteValue, minute);
    }
    _minuteToTop(minute) {
        const steps = (minute - this.firstMinuteValue) / this.minuteStepValue;
        return steps * this.rowHeightValue;
    }
    _snap(minute) {
        const m0 = this.firstMinuteValue;
        const delta = Math.round((minute - m0) / this.minuteStepValue) * this.minuteStepValue;
        return m0 + delta;
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

    // ---- creation à la volée
    pointerDown(e) {
        if (e.button !== 0) return; // click gauche
        const col = e.target.closest('.mptp-col');
        const onSlot = e.target.closest('.slot');
        if (!col) return;

        // Resize handles ?
        const handle = e.target.closest('.slot-handle');
        if (handle && onSlot) {
            const id = onSlot.dataset.id || null;
            const colDate = col.dataset.date;
            const start = parseInt(onSlot.dataset.startMinute, 10);
            const end   = parseInt(onSlot.dataset.endMinute, 10);
            this.active = {
                mode: handle.classList.contains('top') ? 'resize-top' : 'resize-bottom',
                el: onSlot, col, id, date: colDate, startMinute: start, endMinute: end, startY: e.clientY
            };
            this._bindDrag();
            e.preventDefault();
            return;
        }

        // Drag slot ?
        if (onSlot) {
            const id = onSlot.dataset.id || null;
            const colDate = col.dataset.date;
            const start = parseInt(onSlot.dataset.startMinute, 10);
            const end   = parseInt(onSlot.dataset.endMinute, 10);
            const mode = e.altKey ? 'duplicate' : 'drag';
            // pour duplicate: cloner immédiatement l’élément
            let el = onSlot;
            if (mode === 'duplicate') {
                el = onSlot.cloneNode(true);
                el.dataset.id = ''; // nouveau
                onSlot.parentElement.appendChild(el);
            }
            // désactive le hit-test sur le slot pendant le drag
            el.style.pointerEvents = 'none';

            this.active = { mode, el, col, id, date: colDate, startMinute: start, endMinute: end, startY: e.clientY, grabOffset: e.clientY - el.getBoundingClientRect().top };
            this._bindDrag();
            e.preventDefault();
            return;
        }

        // Création d’un nouveau slot
        const date = col.dataset.date;
        const m0 = this._yToMinute(e.clientY, col);
        const top = this._minuteToTop(m0);
        const ghost = this._createSlotEl({ top, height: this.rowHeightValue, col });
        ghost.classList.add('slot--ghost');

        this.active = { mode: 'create', el: ghost, col, id: null, date, startMinute: m0, endMinute: m0 + this.minuteStepValue, startY: e.clientY };
        this._bindDrag();
        e.preventDefault();
    }

    _bindDrag() {
        const move = (ev) => this.pointerMove(ev);
        const up   = (ev) => this.pointerUp(ev);
        this._on(window, 'pointermove', move);
        this._on(window, 'pointerup', up);
    }

    pointerMove(e) {
        if (!this.active) return;
        const a = this.active;

        // auto-scroll pendant le drag
        this._autoScroll(e.clientY);

        if (a.mode === 'create') {
            const m = this._snap(this._yToMinute(e.clientY, a.col));
            a.endMinute = Math.max(m, a.startMinute + this.minuteStepValue);
            const top = this._minuteToTop(Math.min(a.startMinute, a.endMinute - this.minuteStepValue));
            const height = this._minuteToTop(a.endMinute) - top;
            a.el.style.top = `${top}px`;
            a.el.style.height = `${height}px`;
        }


        if (a.mode === 'drag' || a.mode === 'duplicate') {
            // 1) détecter la colonne sous le pointeur
            const newCol = this._colFromPoint(e.clientX, e.clientY);
            if (newCol && newCol !== a.col) {
                a.col = newCol;
                a.date = newCol.dataset.date;
                newCol.appendChild(a.el); // re-parenting dans la nouvelle colonne
            }

            // 2) recalcule le top à partir du grabOffset pour garder l'impression de continuité
            const colRect = this._colRect(a.col);
            const desiredTop = e.clientY - colRect.top - (a.grabOffset || 0);
            const desiredMinute = this._snap(
                this.firstMinuteValue + Math.round(desiredTop / this.rowHeightValue) * this.minuteStepValue
            );

            const duration = a.endMinute - a.startMinute;
            a.startMinute = desiredMinute;
            a.endMinute   = desiredMinute + duration;

            a.el.style.top = `${this._minuteToTop(a.startMinute)}px`;
            return;
        }

        if (a.mode === 'resize-top') {
            const m = this._snap(this._yToMinute(e.clientY, a.col));
            a.startMinute = Math.min(m, a.endMinute - this.minuteStepValue);
            const top = this._minuteToTop(a.startMinute);
            const height = this._minuteToTop(a.endMinute) - top;
            a.el.style.top = `${top}px`;
            a.el.style.height = `${height}px`;
        }

        if (a.mode === 'resize-bottom') {
            const m = this._snap(this._yToMinute(e.clientY, a.col));
            a.endMinute = Math.max(m, a.startMinute + this.minuteStepValue);
            const height = this._minuteToTop(a.endMinute) - this._minuteToTop(a.startMinute);
            a.el.style.height = `${height}px`;
        }
    }

    pointerUp(e) {
        if (!this.active) return;
        const a = this.active;
        this._offAll();

        // réactive le hit-test du slot
        if (a.el) a.el.style.pointerEvents = '';

        // persister/émettre l’action
        if (a.mode === 'create') {
            a.el.classList.remove('slot--ghost');
            // dataset pour prochains drags
            a.el.dataset.startMinute = String(a.startMinute);
            a.el.dataset.endMinute   = String(a.endMinute);
            a.el.dataset.id          = a.el.dataset.id || '';
            this._emit('planner:create', { date: a.date, startMinute: a.startMinute, endMinute: a.endMinute });
        }

        if (a.mode === 'drag') {
            a.el.dataset.startMinute = String(a.startMinute);
            a.el.dataset.endMinute   = String(a.endMinute);
            this._emit('planner:update', { id: a.id, date: a.date, startMinute: a.startMinute, endMinute: a.endMinute });
        }
        if (a.mode === 'duplicate') {
            a.el.dataset.startMinute = String(a.startMinute);
            a.el.dataset.endMinute   = String(a.endMinute);
            a.el.dataset.id = '';
            this._emit('planner:duplicate', { fromId: a.id, date: a.date, startMinute: a.startMinute, endMinute: a.endMinute });
        }

        if (a.mode === 'resize-top' || a.mode === 'resize-bottom') {
            a.el.dataset.startMinute = String(a.startMinute);
            a.el.dataset.endMinute   = String(a.endMinute);
            this._emit('planner:update', { id: a.id, date: a.date, startMinute: a.startMinute, endMinute: a.endMinute });
        }

        this.active = null;
    }
    // trouve la colonne jour sous le pointeur (en tenant compte du scroll)
    _colFromPoint(clientX, clientY) {
        // pendant le drag on met pointer-events:none sur le slot pour pouvoir "voir" la colonne
        const el = document.elementFromPoint(clientX, clientY);
        return el ? el.closest('.mptp-col') : null;
    }

    // auto-scroll quand on approche du bord du viewport de la grille
    _autoScroll(clientY) {
        if (!this.scrollEl) return;
        const rect = this.scrollEl.getBoundingClientRect();
        const pad = 30;         // zone sensible
        const step = 24;        // pixels de scroll par tick
        if (clientY < rect.top + pad)  this.scrollEl.scrollTop -= step;
        if (clientY > rect.bottom - pad) this.scrollEl.scrollTop += step;
    }
}
