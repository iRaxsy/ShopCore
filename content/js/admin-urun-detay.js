/**
 * admin/urun/detay.php kucuk gorsel galerisi.
 *
 * Eskiden davranis etiket icindeki onclick="changeMainImage('...')" ile
 * kuruluyordu; dosya adi HTML'e gomulurken kacirma hatasi riski vardi.
 * Simdi yol data-resim niteliginde durur (PHP tarafinda esc() ile kacirilir)
 * ve olay galeri kokunden delegasyonla dinlenir.
 *
 * Beklenen iskelet:
 *   <div class="thumbnail-gallery" data-gorsel-tabani="../../content/images/">
 *     <div class="thumbnail-item" data-resim="abc.png"><img ...></div>
 *   </div>
 *   <img id="mainImage" ...>
 */
(function () {
    'use strict';

    var galeri = document.querySelector('.thumbnail-gallery');
    var anaGorsel = document.getElementById('mainImage');

    // --- width/height niteliklerini gorselle esitle (CLS icin basildilar) ---
    // Sunucu ilk gorselin boyunu yaziyor; segme degisince kutu eski olcude
    // kalmasin diye yuklenme bittiginde nitelikler gercek boyuna cekilir.
    function boyutEsitle(gorsel) {
        if (gorsel.naturalWidth > 0) {
            gorsel.setAttribute('width', gorsel.naturalWidth);
            gorsel.setAttribute('height', gorsel.naturalHeight);
        }
    }

    if (anaGorsel) {
        anaGorsel.addEventListener('load', function () {
            boyutEsitle(anaGorsel);
        });
    }

    // --- Segme tiklamasi: ana gorseli degistir, aktif sinifini tasi ---
    if (galeri) {
        var taban = galeri.getAttribute('data-gorsel-tabani') || '../../content/images/';

        galeri.addEventListener('click', function (olay) {
            var segme = olay.target.closest('.thumbnail-item');
            if (!segme || !galeri.contains(segme)) {
                return;
            }

            var yol = segme.getAttribute('data-resim');
            if (anaGorsel && yol) {
                anaGorsel.src = taban + yol;
            }

            galeri.querySelectorAll('.thumbnail-item').forEach(function (dugum) {
                dugum.classList.remove('active');
            });
            segme.classList.add('active');
        });
    }

    // --- Kirik/eski dosya yerine sembolik placeholder (onceki inline davranis) ---
    var yedekGorsel = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgdmlld0JveD0iMCAwIDIwMCAyMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIyMDAiIGhlaWdodD0iMjAwIiBmaWxsPSIjRjBGMEYwIi8+CjxwYXRoIGQ9Ik01MCA3MEwxMDAgMTIwTDE1MCA3MEwyMDAgMTIwVjE2MEgwVjEyMEw1MCA3MFoiIGZpbGw9IiNEMEQwRDAiLz4KPHBhdGggZD0iTTEwMCAxMzBMMTQwIDE3MEg2MEwxMDAgMTMwWiIgZmlsbD0iI0QwRDBEMCIvPgo8L3N2Zz4K';

    document.querySelectorAll('img').forEach(function (gorsel) {
        gorsel.addEventListener('error', function () {
            if (this.getAttribute('data-yedek') === '1') {
                return;
            }
            this.setAttribute('data-yedek', '1');
            this.src = yedekGorsel;
            this.style.padding = '20px';
            this.style.maxHeight = '200px';
        });
    });
})();
