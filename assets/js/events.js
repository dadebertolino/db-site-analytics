/**
 * DB Site Analytics — Events Tracker
 * Traccia: click su link esterni (outbound) e scroll depth.
 * ~1.5KB. Nessuna dipendenza. Nessun cookie.
 */
(function () {
    'use strict';

    if (!window.dbsaEv) return;

    var cfg      = window.dbsaEv;
    var homeHost = (new URL(cfg.home_url)).hostname;

    // -------------------------------------------------------------------------
    // Utility: invia evento via sendBeacon o XHR
    // -------------------------------------------------------------------------
    function sendEvent(type, data, pageUrl) {
        // v3.1.0 — Niente nonce: compatibile con page caching
        var params = new URLSearchParams();
        params.append('action',     'dbsa_track_event');
        params.append('event_type', type);
        params.append('event_data', data);
        params.append('page_url',   pageUrl || window.location.href);

        if (navigator.sendBeacon) {
            navigator.sendBeacon(cfg.ajax_url, params);
        } else {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', cfg.ajax_url, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.send(params.toString());
        }
    }

    // -------------------------------------------------------------------------
    // Outbound link tracking
    // -------------------------------------------------------------------------
    if (cfg.track_outbound) {
        document.addEventListener('click', function (e) {
            var el = e.target;
            while (el && el.tagName !== 'A') el = el.parentElement;
            if (!el) return;

            var href = el.getAttribute('href');
            if (!href || href.charAt(0) === '#' || href.indexOf('mailto:') === 0 || href.indexOf('tel:') === 0) return;

            try {
                var url  = new URL(href, window.location.href);
                var host = url.hostname;
                // È un link esterno se il dominio è diverso dall'home
                if (host && host !== homeHost && host !== 'www.' + homeHost) {
                    sendEvent('outbound_click', href, window.location.href);
                }
            } catch (err) {
                // URL non parsabile, ignora
            }
        }, true);
    }

    // -------------------------------------------------------------------------
    // Scroll depth tracking
    // -------------------------------------------------------------------------
    if (cfg.track_scroll) {
        var thresholds = cfg.scroll_thresholds || [25, 50, 75, 100];
        var fired      = {};

        function getScrollPct() {
            var scrollTop  = window.pageYOffset || document.documentElement.scrollTop;
            var docHeight  = Math.max(
                document.body.scrollHeight,
                document.documentElement.scrollHeight,
                document.body.offsetHeight,
                document.documentElement.offsetHeight
            ) - window.innerHeight;

            if (docHeight <= 0) return 100;
            return Math.min(100, Math.round((scrollTop / docHeight) * 100));
        }

        var ticking = false;
        function onScroll() {
            if (ticking) return;
            ticking = true;
            requestAnimationFrame(function () {
                var pct = getScrollPct();
                for (var i = 0; i < thresholds.length; i++) {
                    var t = thresholds[i];
                    if (pct >= t && !fired[t]) {
                        fired[t] = true;
                        sendEvent('scroll_depth', t + '%', window.location.href);
                    }
                }
                ticking = false;
            });
        }

        window.addEventListener('scroll', onScroll, { passive: true });
        // Controlla subito (pagine corte già al 100%)
        onScroll();
    }

})();
