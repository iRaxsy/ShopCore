<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
app_session_start();
admin_zorunlu();
$con = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_dogrula();

    $kategoriadi = temiz_metin($_POST['kategoriadi'] ?? '');
    $aciklama    = temiz_html($_POST['aciklama'] ?? '');

    if ($kategoriadi === '') {
        flash('hata', 'Kategori adı zorunludur.');
    } elseif (mb_strlen($kategoriadi, 'UTF-8') < 3) {
        flash('hata', 'Kategori adı en az 3 karakter olmalıdır.');
    } else {
        try {
            $stmt = $con->prepare("INSERT INTO kategoriler SET kategoriadi=?, aciklama=?");
            if ($stmt->execute([$kategoriadi, $aciklama])) {
                flash('basari', 'Yeni kategori başarıyla kaydedildi.');
            } else {
                flash('hata', 'Kategori kaydedilemedi. Lütfen tekrar deneyin.');
            }
        } catch (PDOException $e) {
            error_log('kategori/ekle: ' . $e->getMessage());
            flash('hata', 'İşlem sırasında bir hata oluştu. Lütfen tekrar deneyin.');
        }
    }
}

include "../header.php";
?>

<div class="container">
    <div class="page-header">
        <h1>Yeni Kategori Ekle</h1>
    </div>

    <div class="form-card">
        <div class="form-header">
            <h2>
                <svg class="ik" aria-hidden="true"><use href="#lk-plus-circle"></use></svg>
                Yeni Kategori Oluştur
            </h2>
        </div>

        <div class="form-body">
            <form id="kategoriEkleForm" action="<?= esc($_SERVER["PHP_SELF"]) ?>" method="post">
                <?= csrf_alan() ?>
                <div class="form-group">
                    <label class="form-label" for="kategoriadi">Kategori Adı</label>
                    <input type='text' name='kategoriadi' id="kategoriadi" 
                           class='form-control' 
                           placeholder="Örn: Elektronik, Giyim, Gıda..."
                           required 
                           minlength="3" />
                    <div class="form-hint">Zorunlu alan. Benzersiz ve açıklayıcı bir kategori adı girin.</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="aciklama">Açıklama</label>
                    <textarea name='aciklama' id="aciklama" 
                              class='form-control' 
                              placeholder="Kategori ile ilgili detaylı açıklama yazın..."></textarea>
                    <div class="form-hint">İsteğe bağlı. Zengin metin editörü ile biçimlendirme yapabilirsiniz.</div>
                </div>

                <div class="required-note">* Zorunlu alanlar</div>
            </form>

            <div class="info-text">
                <svg class="ik text-lacivert" style="margin-right: 8px;" aria-hidden="true"><use href="#lk-info"></use></svg>
                Yeni kategori oluşturmak için lütfen tüm zorunlu alanları doldurun. Kategori adı benzersiz olmalıdır.
            </div>
        </div>
    </div>

    <div class="button-container">
        <button type="submit" form="kategoriEkleForm" class='btn btn-success'>
            <svg class="ik" aria-hidden="true"><use href="#lk-check"></use></svg> Kaydet
        </button>

        <button type="button" id="btnTemizle" class='btn btn-warning'>
            <svg class="ik" aria-hidden="true"><use href="#lk-eraser"></use></svg> Formu Temizle
        </button>

        <a href='liste.php' class='btn btn-danger'>
            <svg class='ik' aria-hidden='true'><use href='#lk-list'></use></svg> Listeye Dön
        </a>
    </div>
</div>

<!-- CKEditor 5 (yerel: content/vendor/ckeditor/) -->
<?= ckeditor() ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('kategoriEkleForm');
    const kategoriAdiInput = document.getElementById('kategoriadi');
    const temizleBtn = document.getElementById('btnTemizle');
    const kaydetBtn = document.querySelector('button[type="submit"]');
    let editorInstance = null;

    try {
        ClassicEditor.create(document.querySelector('#aciklama'), {
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
        }).then(editor => {
            editorInstance = editor;
        }).catch(error => {
            document.getElementById('aciklama').style.minHeight = '150px';
        });
    } catch (error) {
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

        if (editorInstance) {
            editorInstance.updateSourceElement();
        }

        Swal.fire({
            title: "Yeni Kategori Ekle",
            text: "Bu kategoriyi kaydetmek istediğinizden emin misiniz?",
            icon: "question",
            showCancelButton: true,
            confirmButtonText: "Evet, Kaydet",
            cancelButtonText: "Hayır",
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                if (kaydetBtn) {
                    kaydetBtn.disabled = true;
                    kaydetBtn.innerHTML = '<svg class="ik ik-spin" aria-hidden="true"><use href="#lk-refresh-cw"></use></svg> Kaydediliyor...';
                }
                form.submit();
            }
        });
    });

    if (temizleBtn) {
        temizleBtn.addEventListener('click', function() {
            Swal.fire({
                title: "Emin misiniz?",
                text: "Formdaki tüm veriler temizlenecek.",
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Evet, Temizle",
                cancelButtonText: "İptal",
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    kategoriAdiInput.value = '';
                    if (editorInstance) {
                        editorInstance.setData('');
                    }
                    kategoriAdiInput.focus();
                    Swal.fire({ title: "Tamam!", text: "Form başarıyla temizlendi.", icon: "success", timer: 1500, showConfirmButton: false });
                }
            });
        });
    }

    kategoriAdiInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            form.dispatchEvent(new Event('submit'));
        }
    });

    setTimeout(function() { kategoriAdiInput.focus(); }, 500);
});
</script>

<?php include "../footer.php"; ?>
