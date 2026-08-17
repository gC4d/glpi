/*
 * Terabras — full-system dark mode toggle.
 *
 * Our brand dark scope (:root[data-glpi-theme-dark="1"]) redefines the master
 * --tblr-* / --glpi-* tokens the whole GLPI UI reads from, so simply flipping
 * that attribute darkens the entire system — no palette change / server call.
 * This adds a sun/moon toggle to the top bar and persists the choice per-browser.
 */
(function () {
    "use strict";
    var KEY = "tb-dark";

    // Apply as early as possible (script is loaded on every page) to cut the flash.
    try {
        if (localStorage.getItem(KEY) === "1") {
            document.documentElement.setAttribute("data-glpi-theme-dark", "1");
        }
    } catch (e) { /* ignore */ }

    function isDark() {
        return document.documentElement.getAttribute("data-glpi-theme-dark") === "1";
    }

    function apply(on) {
        document.documentElement.setAttribute("data-glpi-theme-dark", on ? "1" : "0");
        try { localStorage.setItem(KEY, on ? "1" : "0"); } catch (e) { /* ignore */ }
        paintButton();
        // let charts / other listeners restyle
        document.dispatchEvent(new CustomEvent("tb-theme-change", { detail: { dark: on } }));
    }

    var btn = null;
    function paintButton() {
        if (!btn) return;
        btn.innerHTML = isDark()
            ? '<i class="ti ti-sun"></i>'
            : '<i class="ti ti-moon"></i>';
        btn.setAttribute("title", isDark() ? "Tema claro" : "Tema escuro");
        btn.setAttribute("aria-label", isDark() ? "Ativar tema claro" : "Ativar tema escuro");
    }

    function inject() {
        if (document.getElementById("tb-darktoggle")) return true;
        var header = document.querySelector(".page > header.navbar .header-container, header.navbar .header-container, header.navbar .container-fluid");
        if (!header) return false;
        // sit just before the user chip (or at the end of the bar)
        var userArea = header.querySelector(".navbar-nav .nav-item.dropdown");
        var anchor = userArea ? userArea.closest(".btn-group, div") : null;

        btn = document.createElement("button");
        btn.id = "tb-darktoggle";
        btn.type = "button";
        btn.className = "btn btn-icon tb-darktoggle";
        btn.addEventListener("click", function () { apply(!isDark()); });

        if (anchor && anchor.parentNode) {
            anchor.parentNode.insertBefore(btn, anchor);
        } else {
            header.appendChild(btn);
        }
        paintButton();
        return true;
    }

    function boot() {
        if (inject()) return;
        var tries = 0;
        var iv = setInterval(function () {
            if (inject() || ++tries > 40) clearInterval(iv);
        }, 250);
    }
    if (document.readyState !== "loading") boot();
    else document.addEventListener("DOMContentLoaded", boot);
})();
