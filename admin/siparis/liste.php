<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
app_session_start();
admin_zorunlu();
$con = db();

// ---- Aksiyonlar: HICBIR cikti basmadan once calisir ----
// Durum degistirme bilincli olarak POST + CSRF ile yapilir (GET mutasyon yok;
// admin/urun/liste.php kalibiyla ayni disiplin).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_dogrula();

    $siparis_id = (int)($_POST['siparis_id'] ?? 0);
    $yeni_durum = (string)($_POST['durum'] ?? '');

    if ($siparis_id <= 0) {
        admin_hata('HATA: Sipariş bilgisi bulunamadı.');
    }
    if (!array_key_exists($yeni_durum, siparis_durumlari())) {
        admin_hata('HATA: Geçersiz sipariş durumu.', $yeni_durum);
    }

    $kontrol = $con->prepare("SELECT id, numarasi FROM siparisler WHERE id = ? LIMIT 1");
    $kontrol->execute([$siparis_id]);
    $siparis_var = $kontrol->fetch(PDO::FETCH_ASSOC);
    if (!$siparis_var) {
        admin_hata('HATA: Sipariş bulunamadı.', '', 404);
    }

    $guncelle = $con->prepare("UPDATE siparisler SET durum = ? WHERE id = ?");
    $guncelle->execute([$yeni_durum, $siparis_id]);

    // PRG: yenilemede ayni islemin tekrar calismamasi icin listeye donulur.
    // Filtreler formda tasinir; yonetici bir satiri guncellediginde arama
    // sonucunu kaybetmez.
    $filtre_aranan = arama_girdisi($_POST['filtre_aranan'] ?? '');
    $filtre_durum = (string)($_POST['filtre_durum'] ?? '');
    if (!array_key_exists($filtre_durum, siparis_durumlari())) {
        $filtre_durum = '';
    }
    $geri = array_filter([
        'aranan' => $filtre_aranan !== '' ? $filtre_aranan : null,
        'durum'  => $filtre_durum !== '' ? $filtre_durum : null,
    ]);
    redirect('admin/siparis/liste.php' . ($geri ? '?' . http_build_query($geri) : ''));
}

// ---- Liste sorgusu ----
$sayfa             = (isset($_GET['sayfa']) && is_numeric($_GET['sayfa'])) ? max(1, (int)$_GET['sayfa']) : 1;
$sayfa_kayit_sayisi = 5;
$offset            = ($sayfa - 1) * $sayfa_kayit_sayisi;

$aranan = arama_girdisi($_GET['aranan'] ?? '');
$durum  = isset($_GET['durum']) ? (string)$_GET['durum'] : '';

// Durum filtresi yalnizca ENUM'de tanimli degerlerle calisir (whitelist)
$durum_filtreli = array_key_exists($durum, siparis_durumlari()) ? $durum : '';

$kosullar = [];
$baglananlar = [];
if ($aranan !== '') {
    // LIKE kacisi: kullanicinin yazdigi % ve _ desen karakteri degil, veri.
    // Tek :aranan baglamasi icin alanlar CONCAT_WS ile birlestirilir (PDO
    // hazirlikta ayni adli parametreyi birden fazla kez kabul etmez).
    $kosullar[] = "CONCAT_WS(' ', s.numarasi, s.adsoyad, s.eposta) LIKE :aranan ESCAPE '!'";
    $baglananlar[':aranan'] = like_deseni($aranan);
}
if ($durum_filtreli !== '') {
    $kosullar[] = "s.durum = :durum";
    $baglananlar[':durum'] = $durum_filtreli;
}
$where = $kosullar ? ('WHERE ' . implode(' AND ', $kosullar)) : '';

// Kalem sayisi ve toplam adet tek sorguda (N+1 yok)
$sorgu = "SELECT s.*, COUNT(k.id) AS kalem_sayisi, COALESCE(SUM(k.adet), 0) AS adet_toplam
          FROM siparisler s
          LEFT JOIN siparis_kalemleri k ON k.siparis_id = s.id
          $where
          GROUP BY s.id
          ORDER BY s.id DESC
          LIMIT :offset, :limit";
$stmt = $con->prepare($sorgu);
foreach ($baglananlar as $anahtar => $deger) {
    $stmt->bindValue($anahtar, $deger);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit', $sayfa_kayit_sayisi, PDO::PARAM_INT);
$stmt->execute();
$siparisler = $stmt->fetchAll(PDO::FETCH_ASSOC);

$toplam_sorgu = $con->prepare("SELECT COUNT(*) FROM siparisler s $where");
foreach ($baglananlar as $anahtar => $deger) {
    $toplam_sorgu->bindValue($anahtar, $deger);
}
$toplam_sorgu->execute();
$kayit_sayisi = (int)$toplam_sorgu->fetchColumn();
$toplam_sayfa = (int)ceil($kayit_sayisi / $sayfa_kayit_sayisi);

// Panelin ust satiri: filtrelerden BAGIMSIZ toplam (yaniltici "su an gorunen"
// sayisi yerine gercek genel tablo). Bekleyen ciro ifadesi admin/index.php ile
// birebir aynidir, iki sayfa farkli rakamlar gostermez.
$genel = $con->query(
    "SELECT COUNT(*) AS adet, COALESCE(SUM(toplam), 0) AS ciro,
            COALESCE(SUM(CASE WHEN durum = 'alindi' THEN 1 ELSE 0 END), 0) AS bekleyen,
            COALESCE(SUM(CASE WHEN durum IN ('alindi', 'hazirlaniyor', 'kargoda') THEN toplam ELSE 0 END), 0) AS bekleyen_ciro
     FROM siparisler"
)->fetch(PDO::FETCH_ASSOC);

$ortalama_tutar = ((int)$genel['adet'] > 0) ? ((float)$genel['ciro'] / (int)$genel['adet']) : 0;

include "../header.php";
?>

<div class="container">
    <div class="page-header">
        <h1>Siparişler</h1>

        <!-- Basligin satirinda: solda baslik, sagda arama + durum filtresi.
             .pg-siparis-liste .page-header flex kurali admin-style.css'te. -->
        <div class="right-search">
            <form action="<?= esc($_SERVER['PHP_SELF']) ?>" method="get" class="search-form">
                <div class="input-group">
                    <input type="text" class="form-control" placeholder="No / ad / e-posta ara..."
                           name="aranan" value="<?= esc($aranan) ?>" maxlength="64" />
                    <select name="durum" class="form-select" aria-label="Durum filtresi" style="max-width: 170px;">
                        <option value="">Tüm durumlar</option>
                        <?php foreach (siparis_durumlari() as $kod => $bilgi): ?>
                            <option value="<?= esc($kod) ?>" <?= $durum_filtreli === $kod ? 'selected' : '' ?>>
                                <?= esc($bilgi['etiket']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-primary btn-search" type="submit">
                        <svg class="ik" aria-hidden="true"><use href="#lk-search"></use></svg>
                    </button>
                    <?php if ($aranan !== '' || $durum_filtreli !== ''): ?>
                        <a href="liste.php" class="btn btn-outline-secondary" title="Filtreleri temizle">
                            <svg class="ik" aria-hidden="true"><use href="#lk-x"></use></svg>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Ozet rakamlar: sade rozet yerine panel kartlari (Katman 2'deki
         body.admin .panel-kart bileseni, kompakt varyanti). Kartlar arama/
         durum filtresinden bagimsizdir; genel tablo toplamlarini gosterir. -->
    <div class="panel-kartlar panel-kartlar-kompakt">
        <div class="panel-kart panel-kart-siparis panel-kart-kompakt">
            <div class="panel-kart-ust">
                <span class="panel-kart-ikon">
                    <svg class="ik" aria-hidden="true"><use href="#lk-receipt"></use></svg>
                </span>
                <span class="panel-kart-baslik">Toplam Sipariş</span>
            </div>
            <div class="panel-kart-deger"><?= number_format((int)$genel['adet'], 0, ',', '.') ?></div>
            <div class="panel-kart-alt">Tüm sipariş kayıtları</div>
        </div>

        <div class="panel-kart panel-kart-ciro panel-kart-kompakt">
            <div class="panel-kart-ust">
                <span class="panel-kart-ikon">
                    <svg class="ik" aria-hidden="true"><use href="#lk-banknote"></use></svg>
                </span>
                <span class="panel-kart-baslik">Ciro</span>
            </div>
            <div class="panel-kart-deger"><?= para($genel['ciro']) ?>&#8378;</div>
            <div class="panel-kart-alt">
                Ortalama <strong><?= para($ortalama_tutar) ?>&#8378;</strong> / sipariş
            </div>
        </div>

        <div class="panel-kart panel-kart-bekleyen panel-kart-kompakt">
            <div class="panel-kart-ust">
                <span class="panel-kart-ikon">
                    <svg class="ik" aria-hidden="true"><use href="#lk-truck"></use></svg>
                </span>
                <span class="panel-kart-baslik">Bekleyen</span>
            </div>
            <div class="panel-kart-deger"><?= number_format((int)$genel['bekleyen'], 0, ',', '.') ?></div>
            <div class="panel-kart-alt">
                Yaklaşık <strong><?= para($genel['bekleyen_ciro']) ?>&#8378;</strong> tutarında işlem
            </div>
        </div>
    </div>

    <?php if ($siparisler): ?>
        <div class="table-responsive">
            <table class="table table-hover table-bordered">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Sipariş No</th>
                        <th>Tarih</th>
                        <th>Müşteri</th>
                        <th>Kalem / Adet</th>
                        <th>Ödeme</th>
                        <th>Toplam</th>
                        <th>Durum</th>
                        <th>İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($siparisler as $kayit):
                        $durum_gorunum = siparis_durum_gorunum($kayit['durum']);
                    ?>
                    <tr>
                        <td><strong><?= (int)$kayit['id'] ?></strong></td>
                        <td><code><?= esc($kayit['numarasi']) ?></code></td>
                        <td><?= esc(date('d.m.Y H:i', strtotime($kayit['olusturma_tarihi']))) ?></td>
                        <td>
                            <strong><?= esc($kayit['adsoyad']) ?></strong><br>
                            <small class="text-muted"><?= esc($kayit['eposta']) ?></small>
                        </td>
                        <td><?= (int)$kayit['kalem_sayisi'] ?> kalem / <?= (int)$kayit['adet_toplam'] ?> adet</td>
                        <td><?= esc(siparis_odeme_etiketi($kayit['odeme_tipi'])) ?></td>
                        <td class="price-cell"><strong><?= para($kayit['toplam']) ?></strong></td>
                        <td>
                            <span class="badge bg-<?= esc($durum_gorunum['sinif']) ?>"><?= esc($durum_gorunum['etiket']) ?></span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <a href='detay.php?id=<?= (int)$kayit['id'] ?>' class='btn btn-info' title='Detay' aria-label='Detay'>
                                    <svg class='ik' aria-hidden='true'><use href='#lk-eye'></use></svg>
                                </a>
                                <form method="post" action="liste.php" class="durum-form">
                                    <?= csrf_alan() ?>
                                    <input type="hidden" name="siparis_id" value="<?= (int)$kayit['id'] ?>">
                                    <input type="hidden" name="filtre_aranan" value="<?= esc($aranan) ?>">
                                    <input type="hidden" name="filtre_durum" value="<?= esc($durum_filtreli) ?>">
                                    <select name="durum" class="form-select form-select-sm durum-secim"
                                            aria-label="Durumu değiştir" data-onceki="<?= esc($kayit['durum']) ?>">
                                        <?php foreach (siparis_durumlari() as $kod => $bilgi): ?>
                                            <option value="<?= esc($kod) ?>" <?= $kayit['durum'] === $kod ? 'selected' : '' ?>>
                                                <?= esc($bilgi['etiket']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <!-- Buton JS'siz de calisir; JS acikken secim degisikligi onayla birlikte gonderilir -->
                                    <button type="submit" class="btn btn-sm btn-outline-secondary" title="Durumu kaydet">
                                        <svg class="ik" aria-hidden="true"><use href="#lk-check"></use></svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?= admin_sayfalama($sayfa, $toplam_sayfa, 'liste.php', [
            'aranan' => $aranan,
            'durum'  => $durum_filtreli,
        ]) ?>

    <?php else: ?>
        <div class="alert alert-warning text-center">
            <svg class="ik" style="font-size: 1.5em; margin-right: 10px;" aria-hidden="true"><use href="#lk-info"></use></svg>
            <strong>
                <?= ($aranan !== '' || $durum_filtreli !== '') ? 'Filtreye uyan siparis bulunamadi.' : 'Henuz siparis kaydi yok.' ?>
            </strong>
        </div>
    <?php endif; ?>
</div>

<?= varlik_js('content/js/admin-siparis-liste.js') ?>

<?php include "../footer.php"; ?>
