<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
app_session_start();
admin_zorunlu();
$con = db();

$id = (isset($_GET['id']) && is_numeric($_GET['id'])) ? (int)$_GET['id'] : 0;
$kategoriadi = '';
$aciklama = '';

if ($id <= 0) {
    flash('hata', 'Geçersiz ID.');
} else {
    // Mevcut kaydı yükle
    try {
        $stmt = $con->prepare("SELECT id, kategoriadi, aciklama FROM kategoriler WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $kayit = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$kayit) {
            flash('hata', 'Kayıt bulunamadı.');
            $id = 0;
        } else {
            $kategoriadi = $kayit['kategoriadi'];
            $aciklama = $kayit['aciklama'];
        }
    } catch (PDOException $e) {
        error_log('kategori/duzelt sorgu: ' . $e->getMessage());
        flash('hata', 'Kayıt okunurken bir sorun oluştu.');
        $id = 0;
    }

    // Güncelleme (çıktıdan ÖNCE işlenir → csrf_dogrula gerekirse güvenli şekilde yönlendirebilir)
    if ($id > 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_dogrula();

        $giris_ad = temiz_metin($_POST['kategoriadi'] ?? '');
        $giris_ac = temiz_html($_POST['aciklama'] ?? '');

        if ($giris_ad === '') {
            flash('hata', 'Kategori adı zorunludur.');
        } elseif (mb_strlen($giris_ad, 'UTF-8') < 3) {
            flash('hata', 'Kategori adı en az 3 karakter olmalıdır.');
        } else {
            try {
                $stmt = $con->prepare("UPDATE kategoriler SET kategoriadi = ?, aciklama = ? WHERE id = ?");
                if ($stmt->execute([$giris_ad, $giris_ac, $id])) {
                    if ($stmt->rowCount() > 0) {
                        flash('basari', 'Kategori başarıyla güncellendi.');
                    } else {
                        flash('uyari', 'Değişiklik yapılmadı. Veriler aynı olabilir.');
                    }
                    $kategoriadi = $giris_ad;
                    $aciklama = $giris_ac;
                } else {
                    flash('hata', 'Kayıt güncellenemedi.');
                }
            } catch (PDOException $e) {
                error_log('kategori/duzelt guncelle: ' . $e->getMessage());
                flash('hata', 'Güncelleme sırasında bir sorun oluştu.');
            }
        }
    }
}

include "../header.php";
?>

<div class="container">
    <div class="page-header">
        <h1>Kategori Güncelleme</h1>
    </div>

    <?php if ($id > 0): ?>
    <div class="form-card">
        <div class="form-header">
            <h2>
                <svg class="ik" aria-hidden="true"><use href="#lk-square-pen"></use></svg>
                Kategori Düzenleme
                <span class="id-badge">ID: <?= (int)$id ?></span>
            </h2>
        </div>

        <div class="form-body">
            <form id="kategoriGuncelleForm" action="<?= esc($_SERVER["PHP_SELF"] . "?id=" . (int)$id) ?>" method="post">
                <?= csrf_alan() ?>
                <div class="form-group">
                    <label class="form-label" for="kategoriadi">Kategori Adı</label>
                    <input type='text' name='kategoriadi' id="kategoriadi"
                           value="<?= esc($kategoriadi) ?>"
                           class='form-control'
                           placeholder="Kategori adını giriniz..."
                           required
                           minlength="3"
                           maxlength="100" />
                    <div class="form-hint">Zorunlu alan. Kategori adı en az 3, en fazla 100 karakter olmalıdır.</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="aciklama">Açıklama</label>
                    <textarea name='aciklama' id="aciklama"
                              class='form-control'
                              placeholder="Kategori açıklamasını buraya yazın..."
                              maxlength="5000"><?= esc($aciklama) ?></textarea>
                    <div class="form-hint">Zengin metin editörü ile biçimlendirme yapabilirsiniz. (Maksimum 5000 karakter)</div>
                </div>

                <div class="required-note">* Zorunlu alanlar</div>
            </form>

            <div class="info-text">
                <svg class="ik text-lacivert" style="margin-right: 8px;" aria-hidden="true"><use href="#lk-info"></use></svg>
                Lütfen kategori bilgilerini dikkatlice düzenleyin. Tüm zorunlu alanları doldurun.
            </div>
        </div>
    </div>

    <div class="button-container">
        <button type="submit" form="kategoriGuncelleForm" class='btn btn-success'>
            <svg class="ik" aria-hidden="true"><use href="#lk-check"></use></svg> Güncelle
        </button>

        <a href='detay.php?id=<?= (int)$id ?>' class='btn btn-primary'>
            <svg class='ik' aria-hidden='true'><use href='#lk-eye'></use></svg> Detaya Dön
        </a>

        <a href='liste.php' class='btn btn-danger'>
            <svg class='ik' aria-hidden='true'><use href='#lk-list'></use></svg> Listeye Dön
        </a>
    </div>
    <?php endif; ?>
</div>

<!-- CKEditor 5 (yerel: content/vendor/ckeditor/) -->
<?= ckeditor() ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('kategoriGuncelleForm');
    if (!form) return;
    const kategoriAdiInput = document.getElementById('kategoriadi');
    const guncelleBtn = document.querySelector('button[type="submit"]');
    let editorInstance = null;

    try {
        ClassicEditor
            .create(document.querySelector('#aciklama'), {
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
                placeholder: 'Kategori açıklamasını buraya yazın...',
                initialData: document.querySelector('#aciklama').value,
                removePlugins: ['Markdown']
            })
            .then(editor => { editorInstance = editor; })
            .catch(() => { document.querySelector('#aciklama').style.display = 'block'; });
    } catch (error) {
        // CKEditor başlatma hatası
    }

    form.addEventListener('submit', function(e) {
        e.preventDefault();

        const kategoriAdi = kategoriAdiInput.value.trim();

        if (!kategoriAdi) {
            Swal.fire({ title: "Uyarı!", text: "Lütfen kategori adını girin!", icon: "warning", confirmButtonText: "Tamam" });
            kategoriAdiInput.focus();
            return;
        }
        if (kategoriAdi.length < 3) {
            Swal.fire({ title: "Uyarı!", text: "Kategori adı en az 3 karakter olmalıdır!", icon: "warning", confirmButtonText: "Tamam" });
            kategoriAdiInput.focus();
            return;
        }
        if (kategoriAdi.length > 100) {
            Swal.fire({ title: "Uyarı!", text: "Kategori adı en fazla 100 karakter olmalıdır!", icon: "warning", confirmButtonText: "Tamam" });
            kategoriAdiInput.focus();
            return;
        }

        if (editorInstance) {
            editorInstance.updateSourceElement();
        }

        Swal.fire({
            title: "Emin misiniz?",
            text: "Kategori bilgileri güncellenecek.",
            icon: "question",
            showCancelButton: true,
            confirmButtonColor: "#198754",
            cancelButtonColor: "#dc3545",
            confirmButtonText: "Evet, Güncelle",
            cancelButtonText: "Hayır, İptal",
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                if (guncelleBtn) {
                    guncelleBtn.disabled = true;
                    guncelleBtn.innerHTML = '<svg class="ik ik-spin" aria-hidden="true"><use href="#lk-refresh-cw"></use></svg> Güncelleniyor...';
                }
                form.submit();
            }
        });
    });

    kategoriAdiInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            form.dispatchEvent(new Event('submit'));
        }
    });
});
</script>

<?php include "../footer.php"; ?>
