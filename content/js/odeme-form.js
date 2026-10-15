/*
 * Odeme formu (satinal.php) istemci tarafi dogrulamasi.
 *
 * Alan kurallarinin kendisi HTML'de durur (required / minlength / maxlength /
 * pattern) ve siparis_onay.php'deki sunucu kurallarinin aynasidir. Bu dosya
 * yalnizca sonucu toplayip gosterir: hatali gonderimde form gonderilmez, sayfa
 * terk edilmez ve yazilan degerler yerinde kalir. Tarayicinin kendi balonu
 * yalnizca tek bir alani gosterdigi, Bootstrap'in `was-validated` sinifi ise
 * hatalari dağınık sekilde; bu yuzden butun hatalar #odemeHataOzeti kutusunda
 * liste olarak toplanir. Sunucu tarafindan gelen hatalar da ayni kutuyu kullanir.
 */
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('odemeForm');
    const ozet = document.getElementById('odemeHataOzeti');
    if (!form || !ozet) {
        return;
    }

    const liste = ozet.querySelector('ul');
    if (!liste) {
        return;
    }

    // Sunucudan donen hatalarla acilan sayfa (form geri basilir): kutu header
    // altinda kalmis ve kaydirilmamis olabilir; yuk cekilirken odak da ilk
    // kirmizi alana gider, sayfa yine odeme ekranidir.
    if (!ozet.classList.contains('d-none')) {
        window.addEventListener('load', function () {
            ozet.scrollIntoView({ block: 'center', behavior: 'smooth' });
            const ilkIsaretli = form.querySelector('.is-invalid');
            if (ilkIsaretli) {
                ilkIsaretli.focus({ preventScroll: true });
            }
        });
    }

    // Mesaji once alanin kendi notundan, sonra kardes-elemanlardaki
    // .invalid-feedback metninden, o da yoksa tarayicinin mesajindan al.
    function alanMesaji(el) {
        if (el.dataset && el.dataset.hata) {
            return el.dataset.hata;
        }
        const kardes = el.parentElement ? el.parentElement.querySelector('.invalid-feedback') : null;
        if (kardes && kardes.textContent.trim() !== '') {
            return kardes.textContent.trim();
        }
        return el.validationMessage;
    }

    function goster(mesajlar) {
        liste.textContent = '';
        mesajlar.forEach(function (metin) {
            const satir = document.createElement('li');
            satir.textContent = metin;
            liste.appendChild(satir);
        });
        ozet.classList.remove('d-none');
    }

    function gizle() {
        ozet.classList.add('d-none');
    }

    form.addEventListener('submit', function (event) {
        if (form.checkValidity()) {
            gizle(); // sunucudan kalan eski hata listesi gonderimi engellemesin
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        form.classList.add('was-validated');

        const mesajlar = [];
        const gorulen = {};
        let ilkHatali = null;

        // Radyo grubundaki her input ayni adi tasir: tek satir mesaj yeter.
        form.querySelectorAll(':invalid').forEach(function (el) {
            const anahtar = el.name || el.id;
            if (gorulen[anahtar]) {
                return;
            }
            gorulen[anahtar] = true;
            mesajlar.push(alanMesaji(el));
            if (ilkHatali === null) {
                ilkHatali = el;
            }
        });

        goster(mesajlar);

        // Odeme ekrani aynen kalir: liste gorunur alana kaydirilir, odak ilk
        // hatali alana gider ama sayfayi kaydirmez (liste okunabilsin).
        ozet.scrollIntoView({ block: 'center', behavior: 'smooth' });
        if (ilkHatali) {
            ilkHatali.focus({ preventScroll: true });
        }
    });

    // Kullanici hatayi duzeltince liste fazlalik olarak ekranda kalmasin.
    form.addEventListener('input', function () {
        if (!ozet.classList.contains('d-none') && form.checkValidity()) {
            gizle();
        }
    });
});
