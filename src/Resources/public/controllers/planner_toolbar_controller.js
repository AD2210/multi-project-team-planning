import { Controller } from "https://unpkg.com/@hotwired/stimulus/dist/stimulus.js";

export default class extends Controller {
    static targets = ["user", "project", "grid"];

    connect() {
        // Si aucune target "grid" dans le DOM du toolbar, on garde une ref locale
        this._gridEl = this.hasGridTarget ? this.gridTarget : document.querySelector('.mptp .mptp-grid-body');
    }

    updateUser(e) {
        this._dispatchToGrid('planner-grid:update-context', { userId: e.currentTarget.value || '' });
    }

    updateProject(e) {
        this._dispatchToGrid('planner-grid:update-context', { projectId: e.currentTarget.value || '' });
    }

    _dispatchToGrid(name, detail) {
        const grid = this.hasGridTarget ? this.gridTarget : this._gridEl;
        if (!grid) return;
        grid.dispatchEvent(new CustomEvent(name, { bubbles: true, detail }));
    }
}