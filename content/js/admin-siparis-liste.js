/**
 * Admin siparis listesi: satir ici durum degistirme.
 *
 * Her satirda formda GERCEK bir gonderme butonu var; JS'siz kullanim calisir.
 * JS acildiginda ise secim degistirildigi anda SweetAlert2 ile onay alinir:
 * durum degisikligi aliciya yansiyabilen bir islem oldugu icin tek yanlis
 * tik ile kaydedilmemeli. Onay iptal edilirse secim eski degerine dondurulur.
 */
(function () {
    'use strict';

    /**
     * Secimin kaydedilmis (sunucudaki) degeri: data-onceki PHP'den gelir,
     * onaylanmayan degisiklikler bunu geri sarar.
     */
    function oncekiDeger(secim) {
        return secim.getAttribute('data-onceki') || secim.value;
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (typeof Swal === 'undefined' || typeof window.HTMLFormElement === 'undefined') {
            return; // JS davranisi gecersiz ama formlar butonla calisir
        }

        document.querySelectorAll('form.durum-form').forEach(function (form) {
            var secim = form.querySelector('.durum-secim');
            if (!secim) {
                return;
            }

            secim.addEventListener('change', function () {
                var yeni = secim.value;
                var etiket = secim.options[secim.selectedIndex]
                    ? secim.options[secim.selectedIndex].textContent.trim()
                    : yeni;

                if (yeni === oncekiDeger(secim)) {
                    return; // kullanici ayni secime dondu, onay gereksiz
                }

                Swal.fire({
                    title: 'Durum değiştirilsin mi?',
                    text: 'Sipariş durumu "' + etiket + '" olarak kaydedilecek.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#0d3b66',
                    cancelButtonColor: '#dc3545',
                    confirmButtonText: 'Evet, kaydet',
                    cancelButtonText: 'Vazgeç'
                }).then(function (sonuc) {
                    if (sonuc.isConfirmed) {
                        form.submit();
                    } else {
                        secim.value = oncekiDeger(secim);
                    }
                });
            });
        });
    });
})();
