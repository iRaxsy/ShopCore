/**
 * Tema motoru - Acik / Koyu.
 *
 * Tercih yalnizca tarayicida saklanir (localStorage: shopcore_tema);
 * veritabaninda kolon yoktur. Varsayilan acik temadir; isletim sistemi
 * tercihi okunmaz ("auto" modu yoktur). Boyama isini CSS linklerinden once
 * calisan satir ici betik (includes/fonksiyonlar.php -> tema_head()) yapar;
 * bu dosya toggle arayuzunu ve canli guncellemeleri ustlenir.
 *
 * Bootstrap 5.3'un yerli data-bs-theme niteligi kullanilir.
 */
(function () {
    'use strict';

    var ANAHTAR = 'shopcore_tema';
    var SIRASI = ['light', 'dark'];
    var ETIKET = { light: 'Açık', dark: 'Koyu' };
    var IKON = { light: 'sun', dark: 'moon' };
    var METAL = { light: '#198754', dark: '#14181d' };

    function secimiOku() {
        var t = null;
        try {
            t = localStorage.getItem(ANAHTAR);
        } catch (e) {
            /* Guvenli mod/kisisel tarayici: tercih okunamazsa acik tema kullanilir. */
        }
        return SIRASI.indexOf(t) === -1 ? 'light' : t;
    }

    function secimiYaz(secim) {
        try {
            localStorage.setItem(ANAHTAR, secim);
        } catch (e) {
            /* Tercih kalici olamasa da oturum boyuncu uygulanir. */
        }
    }

    function aktifSecim() {
        var t = document.documentElement.getAttribute('data-tema-secim');
        return SIRASI.indexOf(t) === -1 ? secimiOku() : t;
    }

    /** Butonun ikonu ve erisilebilir etiketi secime gore yenilenir. */
    function arayuzuGuncelle(secim) {
        var metin = 'Tema: ' + ETIKET[secim];
        var butonlar = document.querySelectorAll('.tema-btn');
        for (var i = 0; i < butonlar.length; i++) {
            var btn = butonlar[i];
            btn.setAttribute('aria-label', metin);
            btn.setAttribute('title', metin);
            btn.setAttribute('data-tema-secim', secim);
            var ikon = btn.querySelector('.ik use');
            if (ikon) {
                /* Lucide sprite'ta simge adi <use href="#lk-..."> ile secilir;
                   sinif degistirmek yerine hedefin adi guncellenir. */
                ikon.setAttribute('href', '#lk-' + IKON[secim]);
            }
        }
    }

    function uygula(secim, kaydet) {
        var koyu = secim === 'dark';
        var e = document.documentElement;
        e.setAttribute('data-bs-theme', koyu ? 'dark' : 'light');
        e.setAttribute('data-tema-secim', secim);
        if (kaydet) {
            secimiYaz(secim);
        }
        var meta = document.querySelector('meta[data-tema-meta]');
        if (meta) {
            meta.setAttribute('content', koyu ? METAL.dark : METAL.light);
        }
        arayuzuGuncelle(secim);
    }

    function baslat() {
        uygula(secimiOku(), false);

        var butonlar = document.querySelectorAll('.tema-btn');
        for (var i = 0; i < butonlar.length; i++) {
            butonlar[i].addEventListener('click', function () {
                var sira = SIRASI.indexOf(aktifSecim());
                uygula(SIRASI[(sira + 1) % SIRASI.length], true);
            });
        }

        /* Baska bir sekmede tercih degistirildiginda bu sayfayi da guncelle. */
        window.addEventListener('storage', function (olay) {
            if (olay.key === ANAHTAR) {
                uygula(secimiOku(), false);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', baslat);
    } else {
        baslat();
    }
})();
