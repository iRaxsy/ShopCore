/**
 * Admin paneli ortak JS.
 * footer.php su sabitleri enjekte eder: window.CSRF, window.BASE_URL.
 *
 * Gorevler:
 *  - Tum fetch() cagrilarina otomatik CSRF header'i ekler (sil/coklusil
 *    endpoint'leri admin_zorunlu + csrf_dogrula ister).
 *  - checkAll / coklu secim yardimcilari.
 */
(function () {
    'use strict';

    var token = (typeof window.CSRF !== 'undefined') ? window.CSRF : '';
    var base = (typeof window.BASE_URL !== 'undefined') ? window.BASE_URL : '/';

    window.ADMIN_CSRF = token;
    window.ADMIN_BASE = base;

    // --- fetch wrapper: CSRF + AJAX basliklarini otomatik ekle ---
    if (typeof window.fetch === 'function') {
        var origFetch = window.fetch.bind(window);
        window.fetch = function (resource, options) {
            options = options || {};
            try {
                var h = new Headers(options.headers || {});
                if (token) {
                    h.set('X-CSRF-Token', token);
                }
                h.set('X-Requested-With', 'XMLHttpRequest');
                options.headers = h;
            } catch (e) {
                /* Headers kullanilamiyorsa oldugu gibi birak */
            }
            return origFetch(resource, options);
        };
    }

    // --- Tum form gonderimlerine gizli _csrf alani ekle (POST formlar) ---
    document.addEventListener('submit', function (ev) {
        var form = ev.target;
        if (!token || !form || (form.method || '').toLowerCase() !== 'post') {
            return;
        }
        if (!form.querySelector('input[name="_csrf"]')) {
            var inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = '_csrf';
            inp.value = token;
            form.appendChild(inp);
        }
    }, true);

    // --- checkAll: tablo secim kutularini toplu degistir ---
    window.checkAll = function (masterCheckbox, itemClass) {
        var boxes = document.querySelectorAll('.' + (itemClass || 'secim'));
        boxes.forEach(function (b) {
            b.checked = masterCheckbox.checked;
        });
        if (typeof window.guncelleSeciliSayisi === 'function') {
            window.guncelleSeciliSayisi();
        }
    };

    // --- Secili id'leri topla ---
    window.seciliIdler = function (itemClass) {
        var ids = [];
        document.querySelectorAll('.' + (itemClass || 'secim') + ':checked').forEach(function (b) {
            ids.push(b.value);
        });
        return ids;
    };
})();
