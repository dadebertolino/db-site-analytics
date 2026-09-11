/**
 * DB Site Analytics — Download Tracker
 * Intercetta click su link a file scaricabili e invia AJAX al server.
 * ~1KB minificato. Nessuna dipendenza. Nessun cookie.
 */
(function () {
    'use strict';

    if (!window.dbsaDl) return;

    var cfg = window.dbsaDl;
    var ext = cfg.extensions || ['pdf', 'zip', 'docx', 'xlsx'];

    function getFileExt(url) {
        var path = url.split('?')[0].split('#')[0];
        var dot  = path.lastIndexOf('.');
        return dot !== -1 ? path.slice(dot + 1).toLowerCase() : '';
    }

    function isDownloadLink(href) {
        if (!href || href.charAt(0) === '#') return false;
        return ext.indexOf(getFileExt(href)) !== -1;
    }

    function trackDownload(fileUrl, pageUrl) {
        // v3.1.0 — Niente nonce: compatibile con page caching
        var params = new URLSearchParams();
        params.append('action',   'dbsa_track_download');
        params.append('file_url', fileUrl);
        params.append('page_url', pageUrl);

        if (navigator.sendBeacon) {
            navigator.sendBeacon(cfg.ajax_url, params);
        } else {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', cfg.ajax_url, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.send(params.toString());
        }
    }

    document.addEventListener('click', function (e) {
        var el = e.target;
        // Risali fino al tag <a>
        while (el && el.tagName !== 'A') {
            el = el.parentElement;
        }
        if (!el) return;

        var href = el.getAttribute('href');
        if (!isDownloadLink(href)) return;

        try {
            // URL assoluto: i link relativi (/wp-content/...) verrebbero rifiutati dal server
            trackDownload(new URL(href, window.location.href).href, window.location.href);
        } catch (err) {
            // URL non parsabile, ignora
        }
    }, true);

})();
