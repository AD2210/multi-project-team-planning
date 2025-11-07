// Contrôle l'ancre de date (date picker de la toolbar)
import { Controller } from "https://unpkg.com/@hotwired/stimulus/dist/stimulus.js";

export default class extends Controller {
    static targets = ["toolbar"];

    gotoByInput(event) {
        const input = event.currentTarget;
        const date = input.value; // format 'YYYY-MM-DD'
        const plannerEl = document.getElementById("calendar");
        if (!plannerEl) return;

        // Récupère l'instance du controller "planner" et appelle goto()
        const controllers = plannerEl.__controllers || [];
        let planner = null;

        // Stimulus 3 stocke les controllers sur l'élément; fallback simple:
        controllers.forEach(c => { if (c.identifier === "planner") planner = c; });
        // Si non présent (certaines implémentations ne peuplent pas __controllers), plan B:
        if (!planner && window.Stimulus?.router?.modules) {
            // meilleure compat si besoin, mais souvent inutile pour la démo
        }

        if (planner && typeof planner.goto === "function") {
            planner.goto(date);
        } else {
            // fallback: simple event custom (si tu préfères écouter côté planner)
            const ev = new CustomEvent("planner:goto", { detail: { date } });
            window.dispatchEvent(ev);
        }
    }
}
