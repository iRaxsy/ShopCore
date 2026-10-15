/**
 * urun-form.js - Urun ekleme ve guncelleme formunun tek istemci modulu.
 *
 * admin/urun/ekle.php ve admin/urun/duzelt.php arasindaki ~380 satirlik kopya
 * inline JS tek yere toplanmistir. Sayfaya ozel dineger (yukleme siniri, izinli
 * uzantilar, POST adresi, mevcut ek resim hakki) artik PHP tarafinda JS string'i
 * uretilmez; ".product-form-container" uzerindeki data-* niteliklerinden okunur.
 *
 * Modlar:
 *   data-mode="ekle"   -> btnKaydet, ek resim hakki = data-max-ek-resim
 *   data-mode="duzelt" -> btnGuncelle, kalan hak = data-kalan-ek-resim,
 *                         mevcut ek resim silme (.silEkResimBtn) etkin.
 *
 * Bagimliliklar (footer.php + ckeditor() ile defer olarak once yuklenir):
 *   ClassicEditor, Swal.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var kapsayici = document.querySelector('.product-form-container');
        if (!kapsayici) {
            return; // bu sayfada urun formu yok
        }

        var veri = kapsayici.dataset;
        var maxEkResim = parseInt(veri.maxEkResim || '5', 10);
        var ekleModu = veri.mode !== 'duzelt';
        var izinliUzantilar = [];
        try {
            izinliUzantilar = JSON.parse(veri.resimIzinli || '[]');
        } catch (e) {
            izinliUzantilar = [];
        }
        var yuklemeSiniri = parseInt(veri.resimLimit || '0', 10);
        var kuralMetni = veri.resimKural || '';
        var actionUrl = veri.action || '';

        // Duzeltme modunda mevcut/kalan ek resim sayilari degisken kalir
        // (bir ek resim silinince kalan hak artar).
        var mevcutEkResimSayisi = parseInt(veri.mevcutEkResim || '0', 10);
        var kalanEkResimHakki = ekleModu
            ? maxEkResim
            : parseInt(veri.kalanEkResim || String(maxEkResim), 10);

        // Yeni secilebilecek ek resim sayisi siniri: mod farkini tek degiskende topla.
        function yeniResimSiniri() {
            return ekleModu ? maxEkResim : kalanEkResimHakki;
        }

        var secilenEkResimler = [];
        var editor;

        function uyarIkon(baslik, metin, ikon) {
            Swal.fire({
                title: baslik,
                text: metin,
                icon: ikon,
                confirmButtonText: 'Tamam'
            });
        }

        function resimGecerliMi(file) {
            var uzanti = file.name.split('.').pop().toLowerCase();
            return file.size <= yuklemeSiniri && izinliUzantilar.indexOf(uzanti) !== -1;
        }

        function thumbnailOlustur(file, i, hedef) {
            var reader = new FileReader();
            reader.onload = function (e) {
                var fileName = file.name.length > 12
                    ? file.name.substring(0, 10) + '...' + file.name.split('.').pop()
                    : file.name;
                var fileSize = Math.round(file.size / 1024);

                var thumbnail = document.createElement('div');
                thumbnail.className = 'thumbnail-item';
                thumbnail.dataset.index = i;
                thumbnail.innerHTML =
                    '<div class="thumbnail-number">' + (i + 1) + '</div>' +
                    '<div class="thumbnail-remove" title="Kaldir">' +
                        '<svg class="ik" aria-hidden="true"><use href="#lk-x"></use></svg>' +
                    '</div>' +
                    '<div class="thumbnail-img-container">' +
                        '<img src="' + e.target.result + '" class="thumbnail-img" alt="' + file.name + '">' +
                    '</div>' +
                    '<div class="thumbnail-info">' + fileName + '<br><small>' + fileSize + ' KB</small></div>';

                hedef.appendChild(thumbnail);
            };
            reader.readAsDataURL(file);
        }

        // ---- CKEditor (aciklama) ----
        var aciklamaAlani = document.querySelector('#aciklama');
        if (aciklamaAlani && typeof ClassicEditor !== 'undefined') {
            ClassicEditor.create(aciklamaAlani, {
                toolbar: {
                    items: [
                        'heading', '|',
                        'bold', 'italic', '|',
                        'bulletedList', 'numberedList', '|',
                        'outdent', 'indent', '|',
                        'link', 'blockQuote', '|',
                        'undo', 'redo'
                    ]
                },
                language: 'tr',
                placeholder: 'Ürün açıklamasını buraya yazın...'
            }).then(function (newEditor) {
                editor = newEditor;
            }).catch(function (error) {
                console.error('CKEditor hatası:', error);
                uyarIkon('Hata!', 'Metin editörü yüklenemedi.', 'error');
            });
        }

        // ---- Ana resim onizleme ----
        var resimInput = document.getElementById('resim');
        if (resimInput) {
            resimInput.addEventListener('change', function () {
                var file = this.files[0];
                if (!file) return;

                if (!resimGecerliMi(file)) {
                    uyarIkon('Uyarı!', 'Resim geçersiz boyut veya format! (' + kuralMetni + ')', 'warning');
                    this.value = '';
                    var onizleme = document.getElementById('imagePreview');
                    if (onizleme) onizleme.style.display = 'none';
                    return;
                }

                var reader = new FileReader();
                reader.onload = function (e) {
                    document.getElementById('preview').src = e.target.result;
                    document.getElementById('imagePreview').style.display = 'block';
                };
                reader.readAsDataURL(file);
            });
        }

        // ---- Ek resim secimi + thumbnail ızgarası ----
        var ekResimlerInput = document.getElementById('ek_resimler');
        if (ekResimlerInput) {
            ekResimlerInput.addEventListener('change', function () {
                var files = Array.from(this.files);
                var sinir = yeniResimSiniri();

                if (files.length > sinir) {
                    uyarIkon('Uyarı!', 'En fazla ' + sinir + ' resim seçebilirsiniz. Seçilen: ' + files.length, 'warning');
                    this.value = '';
                    return;
                }

                secilenEkResimler = files;

                var grid = document.getElementById('thumbnailGrid');
                grid.innerHTML = '';

                guncelleSecilenSayisi();

                if (files.length === 0) {
                    document.getElementById('ekResimPreview').style.display = 'none';
                    return;
                }

                files.forEach(function (file, i) {
                    if (i >= sinir) return;
                    if (!resimGecerliMi(file)) {
                        uyarIkon('Uyarı!', file.name + ' geçersiz! (' + kuralMetni + ')', 'warning');
                        return;
                    }
                    thumbnailOlustur(file, i, grid);
                });

                document.getElementById('ekResimPreview').style.display = 'block';
            });
        }

        function guncelleSecilenSayisi() {
            var count = secilenEkResimler.length;
            var selectedCountEl = document.getElementById('selectedCount');
            var ekResimLabel = document.getElementById('ekResimLabel');
            if (!selectedCountEl) return;

            selectedCountEl.textContent = count;

            if (count > 0) {
                selectedCountEl.style.display = 'inline-block';
                if (ekResimLabel) {
                    ekResimLabel.innerHTML = '<svg class="ik" aria-hidden="true"><use href="#lk-upload-cloud"></use></svg> '
                        + count + (ekleModu ? ' Resim Seçildi - Yeni Ekleyebilirsiniz' : ' Yeni Resim Seçildi');
                }
            } else {
                selectedCountEl.style.display = 'none';
                if (ekResimLabel) {
                    ekResimLabel.innerHTML = '<svg class="ik" aria-hidden="true"><use href="#lk-upload-cloud"></use></svg> '
                        + (ekleModu ? 'Ek Resimleri Seç (Çoklu Seçim)' : 'Yeni Ek Resimleri Seç (Çoklu Seçim)');
                }
            }
        }

        function dosyaGirisiniYenile() {
            var input = document.getElementById('ek_resimler');
            if (!input || input.disabled) return;
            var dataTransfer = new DataTransfer();
            secilenEkResimler.forEach(function (file) {
                dataTransfer.items.add(file);
            });
            input.files = dataTransfer.files;
        }

        function thumbnailIzgarasiniCiz() {
            var grid = document.getElementById('thumbnailGrid');
            if (!grid) return;
            grid.innerHTML = '';
            secilenEkResimler.forEach(function (file, i) {
                thumbnailOlustur(file, i, grid);
            });
        }

        // Secilen thumbnail'i kaldirma (delegasyon).
        document.addEventListener('click', function (e) {
            var kaldir = e.target.closest('.thumbnail-remove');
            if (!kaldir) return;
            e.stopPropagation();
            var thumbnail = kaldir.closest('.thumbnail-item');
            var index = parseInt(thumbnail.dataset.index, 10);

            secilenEkResimler.splice(index, 1);

            dosyaGirisiniYenile();
            thumbnailIzgarasiniCiz();
            guncelleSecilenSayisi();

            if (secilenEkResimler.length === 0) {
                var onizleme = document.getElementById('ekResimPreview');
                if (onizleme) onizleme.style.display = 'none';
            }
        });

        // ---- Mevcut ek resmi silme (yalnizca duzeltme modunda dugum mevcut) ----
        document.addEventListener('click', function (e) {
            var button = e.target.closest('.silEkResimBtn');
            if (!button) return;
            var resimId = button.dataset.resimId;
            var urunId = button.dataset.urunId;

            Swal.fire({
                title: 'Emin misiniz?',
                text: 'Bu ek resmi silmek istediğinizden emin misiniz?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#0d3b66',
                confirmButtonText: 'Evet, sil!',
                cancelButtonText: 'Hayır'
            }).then(function (result) {
                if (!result.isConfirmed) return;

                Swal.fire({
                    title: 'Siliniyor...',
                    text: 'Lütfen bekleyin',
                    icon: 'info',
                    allowOutsideClick: false,
                    didOpen: function () { Swal.showLoading(); }
                });

                var formData = new FormData();
                formData.append('resim_id', resimId);
                formData.append('urun_id', urunId);
                formData.append('is_ajax', true);
                formData.append('sil_tipi', 'ek_resim');

                fetch('sil.php', { method: 'POST', body: formData })
                    .then(function (response) { return response.json(); })
                    .then(function (data) {
                        if (data.status === 'success') {
                            var dugum = document.getElementById('ekResim-' + resimId);
                            if (dugum) dugum.remove();

                            mevcutEkResimSayisi--;
                            kalanEkResimHakki = 5 - mevcutEkResimSayisi;

                            var badge = document.querySelector('.panel:first-child .panel-title .badge');
                            if (badge) badge.textContent = mevcutEkResimSayisi + '/5';

                            var kalanHakSpan = document.getElementById('kalanHakSpan');
                            var ekInput = document.getElementById('ek_resimler');
                            if (kalanEkResimHakki > 0) {
                                if (kalanHakSpan) {
                                    kalanHakSpan.className = 'kalan-hak text-success';
                                    kalanHakSpan.textContent = ' (Kalan ekleme hakkı: ' + kalanEkResimHakki + ')';
                                }
                                if (ekInput) ekInput.disabled = false;

                                var warningAlert = document.querySelector('.panel-body .alert-warning');
                                if (warningAlert) warningAlert.remove();

                                if (ekInput) {
                                    var wrapper = ekInput.closest('.file-input-wrapper');
                                    var customFileLabel = wrapper ? wrapper.querySelector('.custom-file-label') : null;
                                    if (customFileLabel) {
                                        customFileLabel.className = 'custom-file-label';
                                        customFileLabel.innerHTML = '<svg class="ik" aria-hidden="true"><use href="#lk-upload-cloud"></use></svg> Yeni Ek Resimleri Seç (Çoklu Seçim)';
                                    }
                                }
                            } else if (kalanHakSpan) {
                                kalanHakSpan.className = 'kalan-hak text-danger';
                                kalanHakSpan.textContent = ' (Maksimum 5 ek resim sınırına ulaşıldı)';
                            }

                            if (mevcutEkResimSayisi === 0) {
                                var grid = document.getElementById('mevcutEkResimlerGrid');
                                if (grid) {
                                    grid.innerHTML = '<div class="alert alert-info" style="grid-column: 1/-1; text-align: center; margin: 0;"><svg class="ik" aria-hidden="true"><use href="#lk-info"></use></svg> Henüz ek resim yüklenmemiş</div>';
                                }
                            }

                            uyarIkon('Başarılı!', data.message, 'success');
                        } else {
                            uyarIkon('Hata!', data.message || 'Silme başarısız!', 'error');
                        }
                    })
                    .catch(function () {
                        uyarIkon('Hata!', 'Sunucu hatası!', 'error');
                    });
            });
        });

        // ---- Kategori secimi gorunumu ----
        var kategoriInput = document.getElementById('kategori_id');
        if (kategoriInput) {
            kategoriInput.addEventListener('change', function () {
                var selectedText = this.options[this.selectedIndex].text;
                var selectedValue = this.value;
                var categoryDisplay = document.getElementById('categoryDisplay');
                var selectedCategoryName = document.getElementById('selectedCategoryName');

                if (selectedValue) {
                    selectedCategoryName.textContent = selectedText;
                    categoryDisplay.style.display = 'block';
                    this.style.borderColor = 'var(--ad-yesil-metin)';
                    this.style.boxShadow = '0 0 0 3px rgba(25, 135, 84, 0.2)';
                } else {
                    categoryDisplay.style.display = 'none';
                    this.style.borderColor = 'var(--ad-kenar)';
                    this.style.boxShadow = 'none';
                }
            });
        }

        // ---- Fiyat alanini 2 ondaliga sabitle ----
        var fiyatInput = document.getElementById('fiyat');
        if (fiyatInput) {
            fiyatInput.addEventListener('input', function () {
                var value = this.value.replace(/[^0-9.]/g, '');
                var parts = value.split('.');
                if (parts.length > 1) {
                    parts[1] = parts[1].slice(0, 2);
                    value = parts[0] + '.' + parts[1];
                }
                this.value = value;
            });
        }

        // ---- Urun adi odak Efekti ----
        var urunadiInput = document.getElementById('urunadi');
        if (urunadiInput) {
            urunadiInput.style.color = 'var(--ad-lacivert-metin)';
            urunadiInput.addEventListener('focus', function () {
                this.style.borderBottom = '2px solid var(--ad-lacivert-metin)';
            });
            urunadiInput.addEventListener('blur', function () {
                this.style.borderBottom = '2px solid var(--ad-kenar)';
            });
        }

        // ---- Kaydet / Guncelle ----
        var kaydetBtn = document.getElementById(ekleModu ? 'btnKaydet' : 'btnGuncelle');
        if (kaydetBtn) {
            kaydetBtn.addEventListener('click', function (e) {
                e.preventDefault();

                var urunadi = document.getElementById('urunadi').value;
                var fiyat = document.getElementById('fiyat').value;
                var stok = document.getElementById('stok').value;
                var kategori_id = document.getElementById('kategori_id').value;

                if (!urunadi || !fiyat || !kategori_id) {
                    uyarIkon('Uyarı!', 'Lütfen zorunlu alanları doldurun! (* ile işaretli alanlar)', 'warning');
                    return;
                }
                if (isNaN(fiyat) || fiyat <= 0) {
                    uyarIkon('Uyarı!', 'Lütfen geçerli bir fiyat girin!', 'warning');
                    return;
                }
                if (stok !== '' && (isNaN(stok) || Number(stok) < 0 || !Number.isInteger(Number(stok)))) {
                    uyarIkon('Uyarı!', 'Stok negatif olmayan bir tam sayı olmalıdır!', 'warning');
                    return;
                }

                var anaResim = document.getElementById('resim');
                if (anaResim.files.length > 0 && !resimGecerliMi(anaResim.files[0])) {
                    uyarIkon('Uyarı!', 'Ana resim geçersiz! (' + kuralMetni + ')', 'warning');
                    return;
                }

                var ekResimler = document.getElementById('ek_resimler');
                var ekSecili = ekResimler && ekResimler.files.length > 0 && !ekResimler.disabled;
                if (ekSecili) {
                    if (ekResimler.files.length > yeniResimSiniri()) {
                        uyarIkon('Uyarı!', 'En fazla ' + yeniResimSiniri() + ' '
                            + (ekleModu ? 'ek resim yükleyebilirsiniz!' : 'yeni ek resim yükleyebilirsiniz!'), 'warning');
                        return;
                    }
                    for (var i = 0; i < ekResimler.files.length; i++) {
                        if (!resimGecerliMi(ekResimler.files[i])) {
                            uyarIkon('Uyarı!', ekResimler.files[i].name + ' geçersiz! (' + kuralMetni + ')', 'warning');
                            return;
                        }
                    }
                }

                var mesaj = ekleModu
                    ? 'Yeni ürün kaydedilecek.'
                    : 'Ürün bilgileri güncellenecek.';
                if (ekSecili) {
                    mesaj = ekleModu
                        ? 'Yeni ürün ve ' + ekResimler.files.length + ' adet ek resim kaydedilecek.'
                        : 'Ürün bilgileri ve ' + ekResimler.files.length + ' adet yeni ek resim güncellenecek.';
                }

                Swal.fire({
                    title: 'Emin misiniz?',
                    text: mesaj,
                    icon: 'info',
                    showCancelButton: true,
                    confirmButtonColor: '#0d3b66',
                    cancelButtonColor: '#dc3545',
                    confirmButtonText: ekleModu ? 'Evet, kaydet!' : 'Evet, güncelle!',
                    cancelButtonText: 'Hayır'
                }).then(function (result) {
                    if (!result.isConfirmed) return;

                    var formData = new FormData();
                    formData.append('urunadi', urunadi);
                    formData.append('fiyat', fiyat);
                    formData.append('stok', stok === '' ? 0 : stok);
                    formData.append('kategori_id', kategori_id);
                    formData.append('aciklama', editor ? editor.getData() : document.getElementById('aciklama').value);

                    if (anaResim.files.length > 0) {
                        formData.append('resim', anaResim.files[0]);
                    }
                    if (ekSecili) {
                        for (var j = 0; j < ekResimler.files.length; j++) {
                            formData.append('ek_resimler[]', ekResimler.files[j]);
                        }
                    }

                    fetch(actionUrl, { method: 'POST', body: formData })
                        .then(function (response) { return response.text(); })
                        .then(function () { location.reload(); })
                        .catch(function (error) {
                            uyarIkon('Hata!', 'Bir hata oluştu: ' + error, 'error');
                        });
                });
            });
        }
    });
})();
