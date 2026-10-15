<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
app_session_start();
admin_zorunlu();
$con = db();

// Tum mantik (sorgular) cikti baslamadan ONCE calisir
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    admin_hata('HATA: Geçersiz kayıt ID!');
}

$id = (int)$_GET['id'];

try {
    $stmt = $con->prepare("SELECT id, kategoriadi, aciklama FROM kategoriler WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $kayit = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$kayit) {
        admin_hata('HATA: Kayıt bulunamadı.', '', 404);
    }

    // Kategori adi ham tutulur; ciktilamada esc() ile kacirilir
    $kategoriadi = $kayit['kategoriadi'];
    // Aciklama ham halinde saklanir; ciktilamada temiz_html() ile etiketleri kirpilir.
    $aciklama = $kayit['aciklama'];

} catch (PDOException $e) {
    admin_hata('HATA: Kayıt yüklenemedi.', $e->getMessage(), 500);
}

include "../header.php";
?>


<div class="container">
    <div class="page-header">
        <h1>Kategori Detayları</h1>
    </div>

    <div class="detail-card">
        <div class="detail-header">
            <h2>
                <svg class="ik" aria-hidden="true"><use href="#lk-tag"></use></svg>
                Kategori Bilgileri
                <span class="id-badge">ID: <?= $id ?></span>
            </h2>
        </div>
        
        <div class="detail-body">
            <table class='info-table'>
                <tr>
                    <td>Kategori Adı</td>
                    <td><strong><?= esc($kategoriadi) ?></strong></td>
                </tr>
                
                <tr>
                    <td>Açıklama</td>
                    <td>
                        <div class="description-content">
                            <?php if (empty(trim(strip_tags((string)$aciklama)))): ?>
                                <span class="text-muted fst-italic">Bu kategori için açıklama girilmemiş.</span>
                            <?php else: ?>
                                <?= temiz_html($aciklama) ?>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                
                <tr>
                    <td>Kayıt ID</td>
                    <td><code><?= $id ?></code></td>
                </tr>
            </table>
            
            <div class="info-text">
                <svg class="ik text-lacivert" style="margin-right: 8px;" aria-hidden="true"><use href="#lk-info"></use></svg>
                Bu kategoriye ait tüm bilgiler yukarıda görüntülenmektedir.
            </div>
        </div>
    </div>
    
    <div class="button-container">
        <a href='liste.php' class='btn btn-danger'>
            <svg class='ik' aria-hidden='true'><use href='#lk-arrow-left'></use></svg> Listeye Dön
        </a>
        
        <a href='urunler.php?id=<?= $id ?>' class='btn btn-warning'>
            <svg class='ik' aria-hidden='true'><use href='#lk-boxes'></use></svg> Ürünleri Gör
        </a>
        
        <a href='duzelt.php?id=<?= $id ?>' class='btn btn-primary'>
            <svg class='ik' aria-hidden='true'><use href='#lk-pen'></use></svg> Düzenle
        </a>
        
        <a href='#' onclick='silOnay(<?= $id ?>)' class='btn btn-danger'>
            <svg class='ik' aria-hidden='true'><use href='#lk-trash-2'></use></svg> Sil
        </a>
    </div>
</div>

<?php include "../footer.php"; ?>

<!-- Bootstrap bundle + SweetAlert2 + admin.js footer.php üzerinden yükleniyor -->

<script>
function silOnay(id) {
    Swal.fire({
        title: "Emin misiniz?",
        text: "Bu kategori silinecek. Bu işlem geri alınamaz!",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#dc3545",
        cancelButtonColor: "#0d3b66",
        confirmButtonText: "Evet, sil!",
        cancelButtonText: "Hayır, iptal"
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('sil.php', {
                method: 'POST',
                body: new URLSearchParams({ id: id })
            })
                .then(response => response.text())
                .then(data => {
                    if (data === 'urun_var') {
                        Swal.fire({
                            title: "Uyarı!",
                            text: "Bu kategoriye ait ürünler bulunmaktadır. Önce ürünleri silmelisiniz.",
                            icon: "warning",
                            confirmButtonText: "Tamam"
                        });
                    } else if (data === 'silindi') {
                        Swal.fire({
                            title: "Başarılı!",
                            text: "Kategori başarıyla silindi.",
                            icon: "success",
                            confirmButtonText: "Tamam"
                        }).then(() => {
                            window.location.href = 'liste.php';
                        });
                    } else {
                        Swal.fire({
                            title: "Hata!",
                            text: "Kategori silinemedi.",
                            icon: "error",
                            confirmButtonText: "Tamam"
                        });
                    }
                })
                .catch(() => {
                    Swal.fire({
                        title: "Hata!",
                        text: "Bir hata oluştu.",
                        icon: "error",
                        confirmButtonText: "Tamam"
                    });
                });
        }
    });
}
</script>
