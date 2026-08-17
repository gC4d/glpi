/*
 * Terabras — modern ECharts restyling for the dashboard.
 *
 * GLPI renders dashboard charts with ECharts (canvas), so their look can't be
 * reached by CSS. This script grabs each live instance and merges in a modern
 * option set: rounded/slimmer bars, dashed hairline gridlines, no axis lines,
 * horizontal (never rotated) labels, brand fonts/colors, a bordered donut and a
 * clean tooltip. Idempotent and re-runs to catch async / ajax-reloaded dashboards.
 *
 * Careful notes (learned the hard way):
 *  - Only touch the legend when the chart already HAS one, else setOption creates
 *    a legend (show defaults true) that overlaps a donut.
 *  - Force axisLabel rotate:0 + truncate long category names, or they slant/clip.
 */
(function () {
    "use strict";

    function isDark() {
        // two independent dark modes: the GLPI palette (data-glpi-theme-dark) and
        // the dashboard's own night-mode toggle (.dashboard.theme-dark).
        return document.documentElement.getAttribute("data-glpi-theme-dark") === "1"
            || !!document.querySelector(".dashboard.theme-dark");
    }

    function palette() {
        var dark = isDark();
        return {
            grid: dark ? "rgba(255,255,255,.08)" : "rgba(15,15,56,.07)",
            label: dark ? "#9aa0c2" : "#63678a",
            surface: dark ? "#191d48" : "#ffffff",
            ink: dark ? "#ecedf6" : "#20204a",
            font: 'Inter, "Kanit", system-ui, sans-serif'
        };
    }

    function axisPatch(list, pal, isY) {
        if (!list) return undefined;
        var arr = Array.isArray(list) ? list : [list];
        return arr.map(function (a) {
            var lab = { color: pal.label, fontFamily: pal.font, fontSize: 11, rotate: 0, margin: 10 };
            if (a && a.type === "category" && isY) {
                lab.width = 130;
                lab.overflow = "truncate";
            }
            return {
                axisLine: { show: false },
                axisTick: { show: false },
                splitLine: { show: true, lineStyle: { color: pal.grid, type: "dashed", width: 1 } },
                axisLabel: lab
            };
        });
    }

    function restyleInstance(el) {
        if (el.getAttribute("data-tb-charts") === (isDark() ? "d" : "l")) return;
        var inst = window.echarts.getInstanceByDom(el);
        if (!inst) return;
        var opt;
        try { opt = inst.getOption(); } catch (e) { return; }
        if (!opt || !opt.series) return;
        var pal = palette();

        var hasPie = opt.series.some(function (s) { return s.type === "pie"; });
        var horizontal = opt.yAxis && (Array.isArray(opt.yAxis) ? opt.yAxis[0] : opt.yAxis).type === "category";
        var origLegend = opt.legend && (Array.isArray(opt.legend) ? opt.legend[0] : opt.legend);
        var legendShown = !!(origLegend && origLegend.show !== false && !hasPie);

        var series = opt.series.map(function (s) {
            if (s.type === "pie") {
                return {
                    radius: ["52%", "78%"],
                    center: ["50%", "50%"],
                    itemStyle: { borderRadius: 6, borderColor: pal.surface, borderWidth: 3 },
                    label: { show: false },
                    labelLine: { show: false },
                    emphasis: { scale: true, scaleSize: 6 }
                };
            }
            if (s.type === "bar") {
                var stacked = !!s.stack;
                var r = horizontal ? [0, 4, 4, 0] : [4, 4, 0, 0];
                if (stacked) r = 2;
                return { itemStyle: { borderRadius: r }, barMaxWidth: horizontal ? 15 : 30, barCategoryGap: "34%" };
            }
            if (s.type === "line") {
                return { smooth: true, symbol: "circle", symbolSize: 6, lineStyle: { width: 3 }, areaStyle: { opacity: 0.12 } };
            }
            return {};
        });

        var patch = {
            textStyle: { fontFamily: pal.font },
            tooltip: {
                backgroundColor: pal.surface,
                borderColor: pal.grid,
                borderWidth: 1,
                borderRadius: 10,
                padding: [8, 12],
                textStyle: { color: pal.ink, fontFamily: pal.font, fontSize: 12 },
                extraCssText: "box-shadow:0 12px 30px -10px rgba(15,15,56,.35);"
            },
            series: series
        };

        if (!hasPie) {
            patch.grid = { left: 8, right: 18, top: legendShown ? 42 : 14, bottom: 6, containLabel: true };
            var ax = axisPatch(opt.xAxis, pal, false); if (ax) patch.xAxis = ax;
            var ay = axisPatch(opt.yAxis, pal, true); if (ay) patch.yAxis = ay;
        }

        if (hasPie) {
            // the donut reads via segments + center total + tooltip; a legend only overlaps it
            patch.legend = { show: false };
            // recolor GLPI's center total (an echarts title) so it stays legible on dark
            if (opt.title) {
                patch.title = { textStyle: { color: pal.ink }, subtextStyle: { color: pal.label } };
            }
        } else if (legendShown) {
            patch.legend = {
                show: true,
                top: 4,
                left: "center",
                icon: "roundRect",
                itemWidth: 11,
                itemHeight: 11,
                itemGap: 14,
                textStyle: { color: pal.label, fontFamily: pal.font, fontSize: 11 }
            };
        }

        try {
            inst.setOption(patch, false);
            el.setAttribute("data-tb-charts", isDark() ? "d" : "l");
        } catch (e) { /* ignore */ }
    }

    function sweep() {
        if (!window.echarts) return;
        document.querySelectorAll("[_echarts_instance_]").forEach(restyleInstance);
    }

    var ticks = 0;
    var iv = setInterval(function () {
        sweep();
        if (++ticks > 60) clearInterval(iv);
    }, 500);
    if (document.readyState !== "loading") sweep();
    else document.addEventListener("DOMContentLoaded", sweep);
    document.addEventListener("click", function (e) {
        if (e.target.closest && e.target.closest(".night-mode, [data-bs-toggle]")) {
            setTimeout(sweep, 400);
        }
    });
})();
