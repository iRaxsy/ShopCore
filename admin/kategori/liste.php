<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
app_session_start();
admin_zorunlu();
$con = db();

// Tum mantik (sorgular) cikti baslamadan ONCE calisir
$sayfa = isset($_GET['sayfa']) && is_numeric($_GET['sayfa']) ? max(1, (int)$_GET['sayfa']) : 1;
$sayfa_kayit_sayisi = 5;
$ilk_kayit_no = ($sayfa - 1) * $sayfa_kayit_sayisi;

// islem GET bayragi (silme redirect'i ile tasinan tek seferlik bildirim):
// burada yalnizca tip+mesaj VERISI tutulur; HTML asagida sablon parcasıyla uretilir.
$uyari = null;

if (isset($_GET['islem'])) {
    switch ($_GET['islem']) {
        case 'silindi':
            $uyari = ['sinif' => 'success', 'mesaj' => 'Kategori başarıyla silindi.'];
            break;
        case 'silinemedi':
            $uyari = ['sinif' => 'danger', 'mesaj' => 'Kategori silinemedi.'];
            break;
        case 'urun_var':
            $uyari = ['sinif' => 'warning', 'mesaj' => 'Bu kategoriye ait ürünler bulunmaktadır. Önce ürünleri silmelisiniz.'];
            break;
        case 'hata':
            $uyari = [
                'sinif' => 'danger',
                'mesaj' => 'Hata: ' . (isset($_GET['mesaj']) ? $_GET['mesaj'] : 'Bilinmeyen bir hata oluştu.'),
            ];
            break;
    }
}

$aranan = arama_girdisi($_GET['aranan'] ?? '');
// like_deseni(): kullanici girdisindeki % ve _ desen karakteri degil, veridir;
// ESCAPE '!' ile birlikte aranani oldugu gibi bulur, istenmeyen genislemeyi onler.
$arama_sarti = like_deseni($aranan);

$kategoriler = [];
$kayit_sayisi = 0;
$toplam_sayfa = 0;

try {
    $stmt = $con->prepare("SELECT id, kategoriadi, aciklama FROM kategoriler WHERE kategoriadi LIKE :aranan ESCAPE '!' ORDER BY id DESC LIMIT :ilk_kayit_no, :sayfa_kayit_sayisi");
    $stmt->bindParam(":ilk_kayit_no", $ilk_kayit_no, PDO::PARAM_INT);
    $stmt->bindParam(":sayfa_kayit_sayisi", $sayfa_kayit_sayisi, PDO::PARAM_INT);
    $stmt->bindParam(":aranan", $arama_sarti);
    $stmt->execute();
    $kategoriler = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $toplam_kayit = $con->prepare("SELECT COUNT(*) FROM kategoriler WHERE kategoriadi LIKE :aranan ESCAPE '!'");
    $toplam_kayit->bindParam(":aranan", $arama_sarti);
    $toplam_kayit->execute();
    $kayit_sayisi = (int)$toplam_kayit->fetchColumn();
    $toplam_sayfa = (int)ceil($kayit_sayisi / $sayfa_kayit_sayisi);
    
} catch (PDOException $e) {
    admin_hata(db_hata_mesaji($e, 'Veritabanı hatası: İşlem sırasında bir sorun oluştu.'), $e->getMessage(), 500);
}

include "../header.php";
?>


<div class="container">
    <div class="page-header">
        <h1>Kategori Listesi</h1>
    </div>
    <?php if ($uyari): ?>
        <div class="alert alert-<?= $uyari['sinif'] ?>"><?= esc($uyari['mesaj']) ?></div>
    <?php endif; ?>
    
    <!-- Butonlar ve Arama Formu -->
    <div class="main-actions-container">
        <div class="left-buttons">
            <a href='#' id='btn_coklu_sil' class='btn btn-danger'>
                <svg class='ik' aria-hidden='true'><use href='#lk-trash-2'></use></svg>
                <span class="btn-text">Seçilenleri Sil</span>
            </a>
            <a href='ekle.php' class='btn btn-primary'>
                <svg class='ik' aria-hidden='true'><use href='#lk-plus-circle'></use></svg>
                <span class="btn-text">Yeni Kategori</span>
            </a>
        </div>
        
        <div class="right-search">
            <form action='<?php echo esc($_SERVER["PHP_SELF"]); ?>' method='get' class='search-form'>
                <div class='input-group'>
                    <input type='text' class='form-control' placeholder='Kategori ara' name='aranan' value='<?= esc($aranan) ?>' />
                    <button class='btn btn-primary btn-search' type='submit' title='Ara'>
                        <svg class='ik' aria-hidden='true'><use href='#lk-search'></use></svg>
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <?php if ($kategoriler): ?>
        <div class="table-responsive">
            <table class="table table-hover table-bordered">
                <thead>
                    <tr>
                        <th><input type='checkbox' id='checkAll' title='Tümünü Seç'></th>
                        <th>ID</th>
                        <th>Kategori Adı</th>
                        <th>Açıklama</th>
                        <th>İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($kategoriler as $kayit): ?>
                    <tr>
                        <td><input type='checkbox' name='sil_id[]' value='<?= (int)$kayit['id'] ?>' class='checkItem' title='Seç'></td>
                        <td><strong><?= (int)$kayit['id'] ?></strong></td>
                        <td><strong><?= esc($kayit['kategoriadi']) ?></strong></td>
                        <td class='aciklama-hucre'><?= esc(ozetle($kayit['aciklama'], 120)) ?></td>
                        <td>
                            <div class='action-buttons'>
                                <a href='urunler.php?id=<?= (int)$kayit['id'] ?>' class='btn btn-warning' title='Ürünler'>
                                    <svg class='ik' aria-hidden='true'><use href='#lk-boxes'></use></svg>
                                </a>
                                <a href='detay.php?id=<?= (int)$kayit['id'] ?>' class='btn btn-info' title='Detay'>
                                    <svg class='ik' aria-hidden='true'><use href='#lk-eye'></use></svg>
                                </a>
                                <a href='duzelt.php?id=<?= (int)$kayit['id'] ?>' class='btn btn-primary' title='Düzelt'>
                                    <svg class='ik' aria-hidden='true'><use href='#lk-pen'></use></svg>
                                </a>
                                <a href='#' onclick='silOnay(<?= (int)$kayit['id'] ?>)' class='btn btn-danger' title='Sil'>
                                    <svg class='ik' aria-hidden='true'><use href='#lk-trash-2'></use></svg>
                                </a>
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
        text: "Seçili kategori silinecek. Bu işlem geri alınamaz!",
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
                    if (data === 'urun_var') {
                        Swal.fire({
                            title: 'Uyarı!',
                            text: 'Bu kategoriye ait ürünler bulunmaktadır. Önce ürünleri silmelisiniz.',
                            icon: 'warning',
                            confirmButtonColor: '#0d3b66',
                            confirmButtonText: 'Tamam'
                        });
                    } else if (data === 'silindi') {
                        Swal.fire({
                            title: 'Başarılı!',
                            text: 'Kategori başarıyla silindi.',
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

function cokluSil(idler) {
    Swal.fire({
        title: 'Emin misiniz?',
        html: `<b>Seçili ${idler.length} kategori silinecek.</b><br>Bu işlem geri alınamaz!`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#0d3b66',
        confirmButtonText: 'Evet, sil!',
        cancelButtonText: 'İptal',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            idler.forEach(id => {
                formData.append('id[]', id);
            });
            
            fetch('coklusil.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: 'Başarılı!',
                        text: data.message,
                        icon: 'success',
                        confirmButtonColor: '#0d3b66',
                        confirmButtonText: 'Tamam'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        title: 'Hata!',
                        text: data.message,
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
    const checkAll = document.getElementById('checkAll');
    const checkItems = document.querySelectorAll('.checkItem');
    const btnCokluSil = document.getElementById('btn_coklu_sil');
    
    if (checkAll) {
        checkAll.addEventListener('change', function() {
            checkItems.forEach(item => {
                item.checked = this.checked;
            });
            guncelleSeciliSayisi();
        });
    }
    
    checkItems.forEach(item => {
        item.addEventListener('change', function() {
            if (checkAll) {
                checkAll.checked = Array.from(checkItems).every(item => item.checked);
            }
            guncelleSeciliSayisi();
        });
    });
    
    if (btnCokluSil) {
        btnCokluSil.addEventListener('click', function(e) {
            e.preventDefault();
            
            const seciliIdler = Array.from(checkItems)
                .filter(item => item.checked)
                .map(item => item.value);
            
            if (seciliIdler.length === 0) {
                Swal.fire({
                    title: 'Uyarı!',
                    text: 'Silmek için kategori seçin!',
                    icon: 'warning',
                    confirmButtonColor: '#0d3b66',
                    confirmButtonText: 'Tamam'
                });
                return;
            }
            
            cokluSil(seciliIdler);
        });
    }
    
    function guncelleSeciliSayisi() {
        if (!btnCokluSil) return;
        
        const seciliSayi = Array.from(checkItems).filter(item => item.checked).length;
        const btnText = btnCokluSil.querySelector('.btn-text');
        
        if (btnText) {
            btnText.textContent = seciliSayi > 0 ? `Seçilenleri Sil (${seciliSayi})` : 'Seçilenleri Sil';
        }
    }
    
    guncelleSeciliSayisi();
});
</script>
