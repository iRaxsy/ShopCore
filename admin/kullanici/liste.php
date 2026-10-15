<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
app_session_start();
admin_zorunlu();
$con = db();

// Tum mantik (sorgular) cikti baslamadan ONCE calisir
$sayfa = (isset($_GET['sayfa']) && is_numeric($_GET['sayfa'])) ? max(1, (int)$_GET['sayfa']) : 1;
$sayfa_kayit_sayisi = 5;
$ilk_kayit_no = ($sayfa - 1) * $sayfa_kayit_sayisi;

$aranan = arama_girdisi($_GET['aranan'] ?? '');
// like_deseni(): kullanici girdisindeki % ve _ desen karakteri degil, veridir;
// ESCAPE '!' ile birlikte aranani oldugu gibi bulur, istenmeyen genislemeyi onler.
$arama_sarti = like_deseni($aranan);

$kullanicilar = [];
$kayit_sayisi = 0;
$toplam_sayfa = 0;

try {
    // Siparis sayisi ayri sorgu yerine satir icinde alinir (N+1 olmasin);
    // correlated subquery, yalnizca o sayfada gelen 10 satir icin calisir.
    $stmt = $con->prepare("SELECT id, adsoyad, eposta, telefon, il, ilce, olusturma_tarihi,
                                  (SELECT COUNT(*) FROM siparisler s WHERE s.kullanici_id = kullanicilar.id) AS siparis_sayisi
                           FROM kullanicilar
                           WHERE (adsoyad LIKE :aranan1 ESCAPE '!' OR eposta LIKE :aranan2 ESCAPE '!' OR telefon LIKE :aranan3 ESCAPE '!')
                           ORDER BY id DESC LIMIT :ilk_kayit_no, :sayfa_kayit_sayisi");
    $stmt->bindParam(":ilk_kayit_no", $ilk_kayit_no, PDO::PARAM_INT);
    $stmt->bindParam(":sayfa_kayit_sayisi", $sayfa_kayit_sayisi, PDO::PARAM_INT);
    $stmt->bindParam(":aranan1", $arama_sarti);
    $stmt->bindParam(":aranan2", $arama_sarti);
    $stmt->bindParam(":aranan3", $arama_sarti);
    $stmt->execute();
    $kullanicilar = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $toplam_kayit = $con->prepare("SELECT COUNT(*) FROM kullanicilar WHERE (adsoyad LIKE :aranan1 ESCAPE '!' OR eposta LIKE :aranan2 ESCAPE '!' OR telefon LIKE :aranan3 ESCAPE '!')");
    $toplam_kayit->bindParam(":aranan1", $arama_sarti);
    $toplam_kayit->bindParam(":aranan2", $arama_sarti);
    $toplam_kayit->bindParam(":aranan3", $arama_sarti);
    $toplam_kayit->execute();
    $kayit_sayisi = (int)$toplam_kayit->fetchColumn();
    $toplam_sayfa = (int)ceil($kayit_sayisi / $sayfa_kayit_sayisi);
} catch (PDOException $e) {
    admin_hata('Veritabanı hatası: İşlem sırasında bir sorun oluştu.', $e->getMessage(), 500);
}

include "../header.php";
?>


<div class="container">
    <div class="page-header">
        <h1>Kullanıcı Listesi</h1>
    </div>

    
    <!-- Butonlar ve Arama Formu -->
    <div class="main-actions-container">
        <div class="left-buttons">
            <a href='ekle.php' class='btn btn-primary'>
                <svg class='ik' aria-hidden='true'><use href='#lk-plus-circle'></use></svg> Yeni Kullanıcı
            </a>
        </div>
        
        <div class="right-search">
            <form action='<?php echo esc($_SERVER["PHP_SELF"]); ?>' method='get' class='search-form'>
                <div class='input-group'>
                    <input type='text' class='form-control' placeholder='Ad, e-posta veya telefon ara...' name='aranan' value='<?= esc($aranan) ?>' />
                    <button class='btn btn-primary btn-search' type='submit'>
                        <svg class='ik' aria-hidden='true'><use href='#lk-search'></use></svg>
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <?php if ($kullanicilar): ?>
        <div class="table-responsive">
            <table class="table table-hover table-bordered">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Ad Soyad</th>
                        <th>E-posta</th>
                        <th>Telefon</th>
                        <th>İl / İlçe</th>
                        <th>Kayıt Tarihi</th>
                        <th>Sipariş</th>
                        <th>İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($kullanicilar as $kayit): ?>
                    <tr>
                        <td><strong><?= (int)$kayit['id'] ?></strong></td>
                        <td><strong><?= esc($kayit['adsoyad']) ?></strong></td>
                        <td><?= esc($kayit['eposta']) ?></td>
                        <td><?= $kayit['telefon'] !== '' ? esc($kayit['telefon']) : '<span class="text-muted">—</span>' ?></td>
                        <td>
                            <?= esc($kayit['il']) ?><?= $kayit['ilce'] !== '' ? ' / ' . esc($kayit['ilce']) : '' ?>
                            <?php if ($kayit['il'] === '' && $kayit['ilce'] === ''): ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?= esc(date('d.m.Y H:i', strtotime((string)$kayit['olusturma_tarihi']))) ?></td>
                        <td class="siparis-sayisi"><strong><?= (int)$kayit['siparis_sayisi'] ?></strong></td>
                        <td>
                            <div class='action-buttons'>
                                <a href='siparisler.php?id=<?= (int)$kayit['id'] ?>' class='btn btn-info' title='Siparişleri gör' aria-label='Siparişleri gör'>
                                    <svg class='ik' aria-hidden='true'><use href='#lk-receipt'></use></svg>
                                </a>
                                <a href='duzelt.php?id=<?= (int)$kayit['id'] ?>' class='btn btn-primary' title='Düzenle' aria-label='Düzenle'>
                                    <svg class='ik' aria-hidden='true'><use href='#lk-pen'></use></svg>
                                </a>
                                <button type='button' onclick='silOnay(<?= (int)$kayit['id'] ?>)' class='btn btn-danger' title='Sil' aria-label='Sil'>
                                    <svg class='ik' aria-hidden='true'><use href='#lk-trash-2'></use></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?= admin_sayfalama($sayfa, $toplam_sayfa, 'liste.php', ['aranan' => $aranan]) ?>
        
    <?php else: ?>
        <div class="alert alert-warning text-center">
            <svg class="ik" style="font-size: 1.5em; margin-right: 10px;" aria-hidden="true"><use href="#lk-info"></use></svg>
            <strong>Listelenecek kayıt bulunamadı.</strong>
        </div>
    <?php endif; ?>
</div>

<?php include "../footer.php"; ?>

<!-- Bootstrap bundle + SweetAlert2 + admin.js footer.php üzerinden yükleniyor -->

<script>
function silOnay(id) {
    Swal.fire({
        title: 'Emin misiniz?',
        text: "Seçili kullanıcı silinecek. Siparişleri kayıtlı kalır, yalnızca hesap bağlantısı kopar. Bu işlem geri alınamaz!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#0d3b66',
        confirmButtonText: 'Evet, sil!',
        cancelButtonText: 'İptal',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('sil.php', {
                method: 'POST',
                body: new URLSearchParams({ id: id })
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.text();
                })
                .then(data => {
                    if (data === 'silindi') {
                        Swal.fire({
                            title: 'Başarılı!',
                            text: 'Kullanıcı başarıyla silindi.',
                            icon: 'success',
                            confirmButtonColor: '#0d3b66',
                            confirmButtonText: 'Tamam'
                        }).then(() => {
                            location.reload();
                        });
                    } else if (data === 'silinemedi') {
                        Swal.fire({
                            title: 'Hata!',
                            text: 'Silme işlemi başarısız oldu.',
                            icon: 'error',
                            confirmButtonColor: '#0d3b66',
                            confirmButtonText: 'Tamam'
                        });
                    } else {
                        Swal.fire({
                            title: 'Hata!',
                            text: 'Beklenmeyen bir hata oluştu.',
                            icon: 'error',
                            confirmButtonColor: '#0d3b66',
                            confirmButtonText: 'Tamam'
                        });
                    }
                })
                .catch(error => {
                    console.error('Hata:', error);
                    Swal.fire({
                        title: 'Hata!',
                        text: 'Bir hata oluştu: ' + error.message,
                        icon: 'error',
                        confirmButtonColor: '#0d3b66',
                        confirmButtonText: 'Tamam'
                    });
                });
        }
    });
}
</script>
