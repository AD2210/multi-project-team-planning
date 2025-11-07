import { Controller } from "https://unpkg.com/@hotwired/stimulus/dist/stimulus.js";

export default class extends Controller {
    static targets = ["calendar"];
    static values = {
        events: Array,
        users: Array,
        projects: Array,
        mode: String
    };

    connect() {
        // Debug de base
        console.debug("[planner] connect", { mode: this.modeValue, events: (this.eventsValue||[]).length });

        // Sécurité: FullCalendar dispo ?
        if (!window.FullCalendar || !window.FullCalendar.Calendar) {
            console.error("[planner] FullCalendar indisponible");
            return;
        }

        const initialView = "timeGridWeek";
        this.calendar = new FullCalendar.Calendar(this.calendarTarget, {
            initialView,
            headerToolbar: { left: "", center: "title", right: "" },
            locale: "fr",
            slotMinTime: "07:00:00",
            slotMaxTime: "19:00:00",
            nowIndicator: true,
            allDaySlot: true,
            height: "auto",
            editable: false, // on activera drag/resize plus tard
            events: this.eventsValue ?? []
        });
        this.calendar.render();

        // Garde une copie pour filtrage client
        this._allEvents = JSON.parse(JSON.stringify(this.eventsValue ?? []));
    }

    // -------- Navigation
    next()  { this.calendar?.next(); }
    prev()  { this.calendar?.prev(); }
    today() { this.calendar?.today(); }

    viewMonth() { this.calendar?.changeView("dayGridMonth"); }
    viewWeek()  { this.calendar?.changeView("timeGridWeek"); }
    viewDay()   { this.calendar?.changeView("timeGridDay"); }

    // -------- GOTO via <input type="date">
    gotoByInput(event) {
        const date = event.currentTarget.value;
        if (!date || !this.calendar) return;
        try { this.calendar.gotoDate(date); } catch(e) { console.warn(e); }
    }

    // -------- Filtrage
    changeMode(event) {
        const mode = event.currentTarget.value;
        // (désactivation des selects gérée par Twig selon le mode initial ; ici on filtre)
        if (mode === "team") this._reload(this._allEvents);
    }

    selectUser(event) {
        const id = parseInt(event.currentTarget.value || "0", 10);
        if (!id) return this._reload(this._allEvents);
        const filtered = this._allEvents.filter(e => (e.extendedProps?.userId) === id);
        this._reload(filtered);
    }

    selectProject(event) {
        const id = parseInt(event.currentTarget.value || "0", 10);
        if (!id) return this._reload(this._allEvents);
        const filtered = this._allEvents.filter(e => (e.extendedProps?.projectId) === id);
        this._reload(filtered);
    }

    // -------- Helpers
    _reload(events) {
        if (!this.calendar) return;
        this.calendar.removeAllEvents();
        this.calendar.addEventSource(events ?? []);
    }
}
