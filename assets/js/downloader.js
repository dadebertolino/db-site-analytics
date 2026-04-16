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
        var data = new FormData();
        data.append('action',   'dbsa_track_download');
        data.append('nonce',    cfg.nonce);
        data.append('file_url', fileUrl);
        data.append('page_url', pageUrl);

        // navigator.sendBeacon per non bloccare la navigazione
        if (navigator.sendBeacon) {
            // sendBeacon non supporta FormData con action, usiamo URLSearchParams
            var params = new URLSearchParams();
            params.append('action',   'dbsa_track_download');
            params.append('nonce',    cfg.nonce);
            params.append('file_url', fileUrl);
            params.append('page_url', pageUrl);
            navigator.sendBeacon(cfg.ajax_url, params);
        } else {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', cfg.ajax_url, true);
            xhr.send(data);
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
        if (isDownloadLink(href)) {
            trackDownload(href, window.location.href);
        }
    }, true);

})();
