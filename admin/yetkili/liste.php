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

$yetkililer = [];
$kayit_sayisi = 0;
$toplam_sayfa = 0;

try {
    $stmt = $con->prepare("SELECT id, adsoyad, kadi FROM yetkililer WHERE (adsoyad LIKE :aranan1 ESCAPE '!' OR kadi LIKE :aranan2 ESCAPE '!') ORDER BY id DESC LIMIT :ilk_kayit_no, :sayfa_kayit_sayisi");
    $stmt->bindParam(":ilk_kayit_no", $ilk_kayit_no, PDO::PARAM_INT);
    $stmt->bindParam(":sayfa_kayit_sayisi", $sayfa_kayit_sayisi, PDO::PARAM_INT);
    $stmt->bindParam(":aranan1", $arama_sarti);
    $stmt->bindParam(":aranan2", $arama_sarti);
    $stmt->execute();
    $yetkililer = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $toplam_kayit = $con->prepare("SELECT COUNT(*) FROM yetkililer WHERE (adsoyad LIKE :aranan1 ESCAPE '!' OR kadi LIKE :aranan2 ESCAPE '!')");
    $toplam_kayit->bindParam(":aranan1", $arama_sarti);
    $toplam_kayit->bindParam(":aranan2", $arama_sarti);
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
        <h1>Yetkili Listesi</h1>
    </div>

    
    <!-- Butonlar ve Arama Formu -->
    <div class="main-actions-container">
        <div class="left-buttons">
            <a href='ekle.php' class='btn btn-primary'>
                <svg class='ik' aria-hidden='true'><use href='#lk-plus-circle'></use></svg> Yeni Yetkili
            </a>
        </div>
        
        <div class="right-search">
            <form action='<?php echo esc($_SERVER["PHP_SELF"]); ?>' method='get' class='search-form'>
                <div class='input-group'>
                    <input type='text' class='form-control' placeholder='Yetkili ara...' name='aranan' value='<?= esc($aranan) ?>' />
                    <button class='btn btn-primary btn-search' type='submit'>
                        <svg class='ik' aria-hidden='true'><use href='#lk-search'></use></svg>
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <?php if ($yetkililer): ?>
        <div class="table-responsive">
            <table class="table table-hover table-bordered">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Ad ve Soyad</th>
                        <th>Kullanıcı Adı</th>
                        <th>İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($yetkililer as $kayit): ?>
                    <tr>
                        <td><strong><?= (int)$kayit['id'] ?></strong></td>
                        <td><strong><?= esc($kayit['adsoyad']) ?></strong></td>
                        <td><strong><?= esc($kayit['kadi']) ?></strong></td>
                        <td>
                            <div class='action-buttons'>
                                <a href='duzelt.php?id=<?= (int)$kayit['id'] ?>' class='btn btn-primary' title='Düzenle' aria-label='Düzenle'>
                                    <svg class='ik' aria-hidden='true'><use href='#lk-pen'></use></svg>
                                </a>
                                <?php if ((int)$kayit['id'] === 1): ?>
                                    <!-- Ana admin (id=1) korumali: panelden silinemez; sifre/ad guncellemesi Duzelt butonuyla serbest -->
                                    <span class='btn btn-secondary disabled' title='Ana yönetici — silinemez' aria-label='Ana yönetici — silinemez'>
                                        <svg class='ik' aria-hidden='true'><use href='#lk-shield-check'></use></svg>
                                    </span>
                                <?php else: ?>
                                    <button type='button' onclick='silOnay(<?= (int)$kayit['id'] ?>)' class='btn btn-danger' title='Sil' aria-label='Sil'>
                                        <svg class='ik' aria-hidden='true'><use href='#lk-trash-2'></use></svg>
                                    </button>
                                <?php endif; ?>
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
        text: "Seçili yetkili silinecek. Bu işlem geri alınamaz!",
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
                    if (data === 'korunuyor') {
                        Swal.fire({
                            title: 'Korunan hesap',
                            text: 'Ana yönetici (ID 1) hesap silinemez. Gerekirse şifresini Düzenle ekranından değiştirebilirsiniz.',
                            icon: 'info',
                            confirmButtonColor: '#0d3b66',
                            confirmButtonText: 'Tamam'
                        });
                    } else if (data === 'kendini_silme') {
                        Swal.fire({
                            title: 'Uyarı!',
                            text: 'Kendi hesabınızı silemezsiniz!',
                            icon: 'warning',
                            confirmButtonColor: '#0d3b66',
                            confirmButtonText: 'Tamam'
                        });
                    } else if (data === 'silindi') {
                        Swal.fire({
                            title: 'Başarılı!',
                            text: 'Yetkili başarıyla silindi.',
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

document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const islem = urlParams.get('islem');
    
    if (islem === 'silindi') {
        Swal.fire({
            title: 'Başarılı!',
            text: 'Yetkili başarıyla silindi.',
            icon: 'success',
            confirmButtonColor: '#0d3b66',
            confirmButtonText: 'Tamam'
        }).then(() => {
            const url = new URL(window.location);
            url.searchParams.delete('islem');
            window.history.replaceState({}, '', url);
        });
    } else if (islem === 'silinemedi') {
        Swal.fire({
            title: 'Hata!',
            text: 'Yetkili silinemedi.',
            icon: 'error',
            confirmButtonColor: '#0d3b66',
            confirmButtonText: 'Tamam'
        }).then(() => {
            const url = new URL(window.location);
            url.searchParams.delete('islem');
            window.history.replaceState({}, '', url);
        });
    }
});
</script>
