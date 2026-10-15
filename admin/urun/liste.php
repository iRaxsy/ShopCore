<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
app_session_start();
admin_zorunlu();
$con = db();

/**
 * Sütun basligi siralama linki. Ayni sutuna tiklama yonu cevirir, yeni sutun
 * ASC ile baslar. `sayfa` bilincli olarak yok: siralama degisince 1. sayfaya doner.
 */
function siralama_linki($kolon, $aktif_kolon, $aktif_yon, $aranan)
{
    $yeni_yon = ($kolon === $aktif_kolon && $aktif_yon === 'ASC') ? 'desc' : 'asc';
    return url('liste.php', ['siralama' => $kolon, 'yon' => $yeni_yon, 'aranan' => $aranan]);
}

/** Siralama yon ikonu: aktif sutun ok, digerleri pasif cift ok. */
function siralama_ikonu($kolon, $aktif_kolon, $aktif_yon)
{
    if ($kolon !== $aktif_kolon) {
        return '<svg class="ik sira-pasif" aria-hidden="true"><use href="#lk-chevrons-up-down"></use></svg>';
    }
    return $aktif_yon === 'ASC'
        ? '<svg class="ik sira-aktif" aria-hidden="true"><use href="#lk-arrow-up"></use></svg>'
        : '<svg class="ik sira-aktif" aria-hidden="true"><use href="#lk-arrow-down"></use></svg>';
}

// Tum mantik (sorgular) cikti baslamadan ONCE calisir
$sayfa = (isset($_GET['sayfa']) && is_numeric($_GET['sayfa'])) ? max(1, (int)$_GET['sayfa']) : 1;
$sayfa_kayit_sayisi = 5;
$offset = ($sayfa - 1) * $sayfa_kayit_sayisi;

$aranan = arama_girdisi($_GET['aranan'] ?? '');
// like_deseni(): kullanici girdisindeki % ve _ desen karakteri degil, veridir;
// ESCAPE '!' ile birlikte aranani oldugu gibi bulur, istenmeyen genislemeyi onler.
$arama_sarti = like_deseni($aranan);

// Siralama beyaz-listesi: GET degeri yalnizca sabit SQL parcalarina eslenir,
// kullanici girdisi asla ORDER BY'a ham gitmez. Varsayilan 'id' = mevcut davranis.
$siralama_harita = [
    'id'       => 'u.id',
    'urunadi'  => 'u.urunadi',
    'fiyat'    => 'u.fiyat',
    'stok'     => 'u.stok',
    'kategori' => 'k.kategoriadi',
];
$siralama = (isset($_GET['siralama']) && isset($siralama_harita[$_GET['siralama']]))
    ? $_GET['siralama'] : 'id';
$yon = (isset($_GET['yon']) && strtolower($_GET['yon']) === 'asc') ? 'ASC' : 'DESC';

if ($siralama === 'id') {
    // id benzersiz: yon tek basina yeterli, tie-breaker'a gerek yok.
    $orderby_sql = 'u.id ' . $yon;
} else {
    // Secilen sutun + id DESC tie-breaker (esit fiyatt/stok'ta stabil sira).
    $orderby_sql = $siralama_harita[$siralama] . ' ' . $yon . ', u.id DESC';
}

// N+1 önleme: ek resim sayısı alt sorguyla ana sorguda hesaplanır
$sorgu = "SELECT u.id, u.urunadi, u.aciklama, u.fiyat, u.stok, k.kategoriadi,
                 (SELECT COUNT(*) FROM urun_resimleri r WHERE r.urun_id = u.id) AS ek_resim_sayisi
          FROM urunler u 
          LEFT JOIN kategoriler k ON u.kategori_id = k.id 
          WHERE u.urunadi LIKE :aranan ESCAPE '!' 
          ORDER BY " . $orderby_sql . " 
          LIMIT :offset, :limit";

$stmt = $con->prepare($sorgu);
$stmt->bindValue(":offset", $offset, PDO::PARAM_INT);
$stmt->bindValue(":limit", $sayfa_kayit_sayisi, PDO::PARAM_INT);
$stmt->bindValue(":aranan", $arama_sarti);
$stmt->execute();
$urunler = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Sayfalama icin toplam kayit
$toplam_kayit = $con->prepare("SELECT COUNT(*) FROM urunler WHERE urunadi LIKE :aranan ESCAPE '!'");
$toplam_kayit->bindValue(":aranan", $arama_sarti);
$toplam_kayit->execute();
$kayit_sayisi = (int)$toplam_kayit->fetchColumn();
$toplam_sayfa = (int)ceil($kayit_sayisi / $sayfa_kayit_sayisi);

include "../header.php";
?>

<div class="container">
    <div class="page-header">
        <h1>Ürün Listesi</h1>
    </div>
    
    <!-- Butonlar ve Arama Formu -->
    <div class="main-actions-container">
        <div class="left-buttons">
            <a href='#' id='btn_coklu_sil' class='btn btn-danger'>
                <svg class='ik' aria-hidden='true'><use href='#lk-trash-2'></use></svg> Seçilenleri Sil
            </a>
            <a href='ekle.php' class='btn btn-primary'>
                <svg class='ik' aria-hidden='true'><use href='#lk-plus-circle'></use></svg> Yeni Ürün
            </a>
        </div>
        
        <div class="right-search">
            <form action="<?php echo esc($_SERVER["PHP_SELF"]); ?>" method="get" class="search-form">
                <input type="hidden" name="siralama" value="<?= esc($siralama) ?>">
                <input type="hidden" name="yon" value="<?= esc(strtolower($yon)) ?>">
                <div class="input-group">
                    <input type="text" class="form-control" placeholder="Ürün ara..." name="aranan" value="<?php echo esc($aranan); ?>" />
                    <button class="btn btn-primary btn-search" type="submit">
                        <svg class='ik' aria-hidden='true'><use href='#lk-search'></use></svg>
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <?php if ($urunler): ?>
        <div class="table-responsive">
            <table class="table table-hover table-bordered">
                <thead>
                    <tr>
                        <th><input type='checkbox' id='checkAll'></th>
                        <th class="siralana-col <?= $siralama === 'id' ? 'aktif' : '' ?>">
                            <a href="<?= siralama_linki('id', $siralama, $yon, $aranan) ?>">ID <?= siralama_ikonu('id', $siralama, $yon) ?></a>
                        </th>
                        <th class="siralana-col <?= $siralama === 'urunadi' ? 'aktif' : '' ?>">
                            <a href="<?= siralama_linki('urunadi', $siralama, $yon, $aranan) ?>">Ürün Adı <?= siralama_ikonu('urunadi', $siralama, $yon) ?></a>
                        </th>
                        <th>Açıklama</th>
                        <th class="siralana-col <?= $siralama === 'fiyat' ? 'aktif' : '' ?>">
                            <a href="<?= siralama_linki('fiyat', $siralama, $yon, $aranan) ?>">Fiyat <?= siralama_ikonu('fiyat', $siralama, $yon) ?></a>
                        </th>
                        <th class="siralana-col <?= $siralama === 'stok' ? 'aktif' : '' ?>">
                            <a href="<?= siralama_linki('stok', $siralama, $yon, $aranan) ?>">Stok <?= siralama_ikonu('stok', $siralama, $yon) ?></a>
                        </th>
                        <th class="siralana-col <?= $siralama === 'kategori' ? 'aktif' : '' ?>">
                            <a href="<?= siralama_linki('kategori', $siralama, $yon, $aranan) ?>">Kategori <?= siralama_ikonu('kategori', $siralama, $yon) ?></a>
                        </th>
                        <th>İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($urunler as $kayit):
                        $ek_resim_sayisi = (int)$kayit['ek_resim_sayisi'];
                        
                        $kategori_gorunum = $kayit['kategoriadi'] ? esc($kayit['kategoriadi']) : '<span class="empty-category">Kategori Yok</span>';
                    ?>
                    <tr>
                        <td><input type='checkbox' name='sil_id[]' value='<?= $kayit['id'] ?>' class='checkItem'></td>
                        <td><strong><?= $kayit['id'] ?></strong></td>
                        <td><strong><?= esc($kayit['urunadi']) ?></strong></td>
                        <td class="aciklama-hucre"><?= esc(ozetle($kayit['aciklama'], 120)) ?></td>
                        <td class='price-cell'><strong><?= para($kayit['fiyat']) ?></strong></td>
                        <td class="stok-hucre"><?= (int)$kayit['stok'] > 0 ? (int)$kayit['stok'] : '<span class="stok-yok">Tükendi</span>' ?></td>
                        <td><?= $kategori_gorunum ?></td>
                        <td>
                            <div class='action-buttons'>
                                <a href='detay.php?id=<?= $kayit['id'] ?>' class='btn btn-info' title='Detay' aria-label='Detay'>
                                    <svg class='ik' aria-hidden='true'><use href='#lk-eye'></use></svg>
                                </a>
                                <a href='duzelt.php?id=<?= $kayit['id'] ?>' class='btn btn-primary' title='Düzenle' aria-label='Düzenle'>
                                    <svg class='ik' aria-hidden='true'><use href='#lk-pen'></use></svg>
                                </a>
                                <button type='button' onclick='silOnay(<?= (int)$kayit['id'] ?>, <?= json_encode($kayit['urunadi'], JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_TAG|JSON_HEX_AMP) ?>, <?= $ek_resim_sayisi ?>)' class='btn btn-danger' title='Sil' aria-label='Sil'>
                                    <svg class='ik' aria-hidden='true'><use href='#lk-trash-2'></use></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?= admin_sayfalama($sayfa, $toplam_sayfa, 'liste.php', ['aranan' => $aranan, 'siralama' => $siralama, 'yon' => strtolower($yon)]) ?>
        
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
function silOnay(id, urunadi, ekResimSayisi) {
    let mesaj = `'${urunadi}' isimli ürünü ${ekResimSayisi > 0 ? 've ' + ekResimSayisi + ' ek resmini ' : ''}silmek istiyor musunuz?`;
    
    Swal.fire({
        title: "Emin misiniz?",
        text: mesaj,
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#dc3545",
        cancelButtonColor: "#0d3b66",
        confirmButtonText: "Evet, sil!",
        cancelButtonText: "Hayır",
        showLoaderOnConfirm: true,
        preConfirm: () => {
            return fetch('sil.php', {
                    method: 'POST',
                    body: new URLSearchParams({ id: id, sil_tipi: 'urun' })
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Ağ yanıtı alınamadı');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.status === 'success') {
                        return data;
                    } else {
                        throw new Error(data.message || 'Silme işlemi başarısız');
                    }
                })
                .catch(error => {
                    Swal.showValidationMessage(`İstek başarısız: ${error}`);
                });
        },
        allowOutsideClick: () => !Swal.isLoading()
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            Swal.fire({
                title: "Başarılı!",
                text: result.value.message,
                icon: "success",
                confirmButtonColor: "#0d3b66",
                confirmButtonText: "Tamam"
            }).then(() => {
                location.reload();
            });
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const checkAll = document.getElementById('checkAll');
    const checkItems = document.querySelectorAll('.checkItem');
    const btnCokluSil = document.getElementById('btn_coklu_sil');
    
    function guncelleSeciliSayisi() {
        let secili = document.querySelectorAll('.checkItem:checked').length;
        if (btnCokluSil) {
            btnCokluSil.innerHTML = `<svg class='ik' aria-hidden='true'><use href='#lk-trash-2'></use></svg> Seçilenleri Sil${secili ? ' (' + secili + ')' : ''}`;
        }
    }
    
    if (checkAll) {
        checkAll.addEventListener('click', function() {
            checkItems.forEach(item => {
                item.checked = this.checked;
            });
            guncelleSeciliSayisi();
        });
    }
    
    checkItems.forEach(item => {
        item.addEventListener('click', function() {
            if (checkAll) {
                checkAll.checked = document.querySelectorAll('.checkItem:checked').length === checkItems.length;
            }
            guncelleSeciliSayisi();
        });
    });
    
    if (btnCokluSil) {
    btnCokluSil.addEventListener('click', function(e) {
        e.preventDefault();
        let idler = Array.from(document.querySelectorAll('.checkItem:checked')).map(el => el.value);
        
        if (!idler.length) {
            Swal.fire("Uyarı!", "Silmek için ürün seçin!", "warning");
            return;
        }
        
        Swal.fire({
            title: "Emin misiniz?",
            text: `Seçili ${idler.length} ürünü silmek istiyor musunuz?`,
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#dc3545",
            cancelButtonColor: "#0d3b66",
            confirmButtonText: "Evet, sil!",
            cancelButtonText: "Hayır"
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('coklusil.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({id: idler})
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire("Başarılı!", data.message, "success").then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire("Hata!", data.message, "error");
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire("Hata!", "Bir hata oluştu: " + error.message, "error");
                });
            }
        });
    });
}
    
    guncelleSeciliSayisi();
});
</script>
