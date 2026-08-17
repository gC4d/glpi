/*
 * Terabras — small UX niceties.
 *
 * Top progress bar for AJAX: GLPI loads modals (and much else) over jQuery AJAX
 * and shows no feedback while the request is in flight, so a click feels "dead"
 * until the modal springs in. A thin brand-orange bar at the very top gives that
 * feedback and makes the load feel intentional. Only shown for requests slower
 * than a short threshold, so quick calls don't flash.
 */
(function () {
    "use strict";
    if (typeof window.jQuery === "undefined") return;
    var $ = window.jQuery;

    var bar = null;
    var showTimer = null;
    var active = 0;

    function ensure() {
        if (bar) return bar;
        bar = document.createElement("div");
        bar.className = "tb-progress";
        bar.setAttribute("role", "presentation");
        (document.body || document.documentElement).appendChild(bar);
        return bar;
    }

    function begin() {
        var b = ensure();
        b.classList.remove("tb-progress--done");
        b.classList.add("tb-progress--active");
        b.style.width = "0%";
        // next frame → animate toward ~75% (trickle)
        window.requestAnimationFrame(function () {
            b.style.width = "75%";
        });
    }

    function finish() {
        if (!bar) return;
        bar.style.width = "100%";
        bar.classList.add("tb-progress--done");
        window.setTimeout(function () {
            bar.classList.remove("tb-progress--active", "tb-progress--done");
            bar.style.width = "0%";
        }, 280);
    }

    $(document).on("ajaxSend", function () {
        active++;
        if (active === 1 && !showTimer) {
            showTimer = window.setTimeout(function () {
                showTimer = null;
                begin();
            }, 140);
        }
    });

    $(document).on("ajaxComplete", function () {
        active = Math.max(0, active - 1);
        if (active === 0) {
            if (showTimer) { window.clearTimeout(showTimer); showTimer = null; }
            else finish();
        }
    });

    /* --- Dashboard load: reveal-when-settled -----------------------------
       gridstack (central) and masonry (Visão pessoal) both render the cards
       first and REPOSITION them client-side afterwards — the "shuffle". We hide
       the grid until it stops loading AND its layout is stable for a moment,
       then fade it in once. If JS never settles it, an 9s safety reveals it. */
    function watchGrid(grid) {
        if (grid.__tbWatch) return;
        grid.__tbWatch = true;
        grid.classList.add("tb-grid-loading");
        var stableTicks = 0;
        var lastKey = "";
        var iv = window.setInterval(function () {
            var loading = grid.querySelector(".spinner-border, [class*='spinner']");
            var items = grid.querySelectorAll(".grid-stack-item, .grid-item");
            // a layout "fingerprint": count + total height — changes while it reflows
            var key = items.length + ":" + Math.round(grid.getBoundingClientRect().height);
            var stable = (key === lastKey);
            lastKey = key;
            if (!loading && items.length && stable) {
                if (++stableTicks >= 3) { reveal(grid, iv); }   // ~300ms steady
            } else {
                stableTicks = 0;
            }
        }, 100);
        window.setTimeout(function () { reveal(grid, iv); }, 9000);
    }
    function reveal(grid, iv) {
        window.clearInterval(iv);
        grid.classList.remove("tb-grid-loading");
        grid.classList.add("tb-grid-ready");
    }
    function scanGrids() {
        document.querySelectorAll(".dashboard .grid-stack, .masonry_grid").forEach(watchGrid);
    }
    // catch grids as soon as they are injected (they arrive over ajax)
    if (window.MutationObserver) {
        var throttled = false;
        var mo = new MutationObserver(function () {
            if (throttled) return;
            throttled = true;
            window.setTimeout(function () { throttled = false; scanGrids(); }, 30);
        });
        try { mo.observe(document.documentElement, { childList: true, subtree: true }); } catch (e) { /* ignore */ }
    }
    var tries = 0;
    var poll = window.setInterval(function () { scanGrids(); if (++tries > 40) window.clearInterval(poll); }, 150);
    if (document.readyState !== "loading") scanGrids();
    else document.addEventListener("DOMContentLoaded", scanGrids);
})();
