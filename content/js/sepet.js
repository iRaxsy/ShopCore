/**
 * Sepet islemleri (AJAX) - native fetch, jQuery yok.
 * Layout (footer.php) su nesneleri enjekte eder:
 *   window.SEPET_CONFIG = { yol: '<APP_BASE_URL>', csrf: '<token>' };
 * SweetAlert2 bu dosyadan once yuklenir (footer.php'deki etiket sirasi;
 * hepsi defer oldugu icin calisma sirasi belge sirasiyla aynidir).
 *
 * Olay yonetimi: TEK document dinleyicisi + closest() ile delegasyon. Eski
 * `$(document).on('click', '.sepete_ekle', ...)` davranisi korunur: butonun
 * icindeki alt ogelere (ikon/etiket) tiklansa bile hedef sinif bulunur, hatta
 * dinleyici sayfa sonradan degisse de calismaya devam eder.
 */
(function () {
    'use strict';

    var cfg = window.SEPET_CONFIG || { yol: '', csrf: '' };

    function hedef(dosya) {
        return cfg.yol + dosya;
    }

    /** Sunucuya POST: form-govde + CSRF/AJAX basliklari. JSON soz verir. */
    function gonder(dosya, veri) {
        var govde = new URLSearchParams();
        Object.keys(veri).forEach(function (ad) {
            govde.append(ad, veri[ad]);
        });

        return fetch(hedef(dosya), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-CSRF-Token': cfg.csrf,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: govde.toString()
        }).then(function (yanit) {
            if (!yanit.ok) {
                throw new Error('Sunucu hatasi (HTTP ' + yanit.status + ')');
            }
            return yanit.json();
        });
    }

    /** Kisa bildirim; `sonra` verilirse kapanis akabinde calisir. */
    function bildirim(ikon, baslik, metin, sonra) {
        var param = {
            icon: ikon,
            title: baslik,
            text: metin,
            showConfirmButton: false,
            timer: 1500
        };
        return window.Swal.fire(param).then(function (sonuc) {
            if (typeof sonra === 'function') {
                sonra(sonuc);
            }
            return sonuc;
        });
    }

    /** Onay gerektiren ciddi diyalog (onaylayinca devam). */
    function onay(metin) {
        return window.Swal.fire({
            title: 'Emin misiniz?',
            text: metin,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Evet, çıkar',
            cancelButtonText: 'İptal'
        });
    }

    /** Rozet: yalnizca sayi icerir (ikon bileseni class ile ciziliyor). */
    function sayiciGuncelle(deger) {
        var rozet = document.getElementById('urun-sayisi');
        if (rozet) {
            rozet.textContent = String(deger);
        }
    }

    function sayiciDegeri() {
        var rozet = document.getElementById('urun-sayisi');
        return rozet ? (parseInt(rozet.textContent, 10) || 0) : 0;
    }

    /** Sepetteki adet kutusu (#urun_<id>) varsa degeri, yoksa 1. */
    function adetDegeri(id) {
        var kutu = document.getElementById('urun_' + id);
        return kutu ? kutu.value : 1;
    }

    function sepeteEkle(dugme) {
        var id = dugme.getAttribute('id');
        var onceki = sayiciDegeri();

        gonder('sepete_ekle.php', { id: id, adet: adetDegeri(id) })
            .then(function (response) {
                if (!response || !response.success) {
                    bildirim('warning', 'Uyarı!',
                        (response && response.message) ? response.message : 'Ürün sepete eklenemedi');
                    return;
                }
                // Sunucudan gelen sayi esas alinir; yoksa yerel tahmin
                sayiciGuncelle(response.count || (onceki + 1));
                bildirim('success', 'Başarılı!', response.message);
            })
            .catch(function (error) {
                window.Swal.fire({
                    icon: 'error',
                    title: 'Hata!',
                    text: 'Sepete ekleme işlemi başarısız: ' + error.message,
                    showConfirmButton: true
                });
            });
    }

    function adetGuncelle(dugme) {
        var id = dugme.getAttribute('id');

        gonder('sepeti_guncelle.php', { id: id, adet: adetDegeri(id) })
            .then(function (response) {
                if (!response || !response.success) {
                    bildirim('warning', 'Uyarı!',
                        (response && response.message) ? response.message : 'Adet güncellenemedi');
                    return;
                }
                bildirim('success', 'Başarılı!', 'Ürün adedi güncellendi', function () {
                    window.location.href = hedef('sepetim.php');
                });
            })
            .catch(function (error) {
                window.Swal.fire({
                    icon: 'error',
                    title: 'Hata!',
                    text: 'Güncelleme işlemi başarısız: ' + error.message
                });
            });
    }

    function sepettenSil(dugme) {
        var id = dugme.getAttribute('id');

        onay('Bu ürünü sepetinizden çıkarmak istiyor musunuz?').then(function (result) {
            if (!result.isConfirmed) {
                return;
            }
            // adet gönderilmez: sunucu bu dalı "sepetten çıkar" olarak bilir
            gonder('sepeti_guncelle.php', { id: id })
                .then(function (response) {
                    if (!response || !response.success) {
                        bildirim('warning', 'Uyarı!',
                            (response && response.message) ? response.message : 'Ürün sepetten çıkarılamadı');
                        return;
                    }
                    sayiciGuncelle(response.count || 0);
                    bildirim('success', 'Başarılı!', 'Ürün sepetten çıkarıldı', function () {
                        window.location.href = hedef('sepetim.php');
                    });
                })
                .catch(function (error) {
                    window.Swal.fire({
                        icon: 'error',
                        title: 'Hata!',
                        text: 'Silme işlemi başarısız: ' + error.message
                    });
                });
        });
    }

    document.addEventListener('click', function (olay) {
        var dugme = olay.target.closest('.sepete_ekle, .urun-guncelle, .urun-sil');
        if (!dugme) {
            return;
        }

        if (dugme.classList.contains('sepete_ekle')) {
            olay.preventDefault();
            olay.stopPropagation();
            sepeteEkle(dugme);
            return;
        }

        if (dugme.classList.contains('urun-guncelle')) {
            adetGuncelle(dugme);
            return;
        }

        sepettenSil(dugme);
    });
})();
