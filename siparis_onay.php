<?php
/**
 * Siparisin GERCEKTEN kaydedildigi tek nokta.
 *
 * Eski hali: sepeti silip "Siparişiniz Alındı" yaziyordu; hicbir siparis kaydi
 * kalmiyordu. Artik akis su adimlarla ilerler ve her adim basarisiz oldugunda
 * ne oldugu kullaniciya dogru sekilde soylenir:
 *   1) yalnizca POST + CSRF + giris zorunlulugu,
 *   2) teslimat alanlarinin dogrulanmasi -> hata VARSA idempotency tokeni HIC
 *      tuketilmez; degerler ve mesajlar tek kullanimlik bir snapshot olarak
 *      oturuma alinip satinal.php'ye (odeme ekrani) donulur, yazilanlar kaybolmaz,
 *   3) idempotency tokeni (tek kullanimlik) -> ayni form iki kez siparis uretemez,
 *   4) tutarlar istemciden degil, sepet_ozeti() ile DB'den yeniden hesaplanir,
 *   5) tek transaction: siparis + kalemler + stok dusme (SELECT ... FOR UPDATE),
 *   6) yetersiz stok/urun yoksa rollBack + sepete yonlendirme (sepet KALIR),
 *   7) sepet yalnizca commit sonrasinda temizlenir.
 */
require_once __DIR__ . '/includes/bootstrap.php';
app_session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('sepetim.php');
}
csrf_dogrula(); // satinal.php odeme formundan gelen CSRF tokeni

// Giris yapmayan kullanici siparis veremez. Kapinin yeri bilincli: idempotency
// tokenindan ONCE gelir, boylece kapıya takilan istek tokeni tuketmez ve
// sepet/kullanici adimi oldugu gibi tekrarlayabilir.
kullanici_zorunlu();

// 2) Teslimat alanlari - sunucu tarafinda dogrulama (istemci JS'i sadece rahatlik)
$hatalar = [];
// Hatanin hangi alana ait oldugu da tutulur: snapshot ile odeme ekranina donerken
// kirmizi isaret (is-invalid) ve alan mesajlari yalnizca gercekten hatali
// alanlarda gorunsun; Aksi halde "isaretli alanlari kontrol edin" bos kalir.
$hatali_alanlar = [];
$hata = static function (array $alanlar, string $mesaj) use (&$hatalar, &$hatali_alanlar): void {
    $hatalar[] = $mesaj;
    foreach ($alanlar as $alan) {
        if (!in_array($alan, $hatali_alanlar, true)) {
            $hatali_alanlar[] = $alan;
        }
    }
};

$isim      = trim((string)($_POST['isim'] ?? ''));
$soyisim   = trim((string)($_POST['soyisim'] ?? ''));
$adsoyad   = trim($isim . ' ' . $soyisim);
$eposta    = temiz_metin($_POST['eposta'] ?? '');
$telefon   = preg_replace('/[^\d+() -]/u', '', (string)($_POST['telefon'] ?? ''));
$il        = temiz_metin($_POST['il'] ?? '');
$ilce      = temiz_metin($_POST['ilce'] ?? '');
$adres     = trim((string)($_POST['adres'] ?? ''));
$kurum     = temiz_metin($_POST['kurum'] ?? '');
$posta_kodu = preg_replace('/[^\d]/', '', (string)($_POST['postakodu'] ?? ''));
$odeme_tipi = (string)($_POST['odemesekli'] ?? '');

if ($adsoyad === '' || mb_strlen($adsoyad) < 3) {
    $hata(['isim', 'soyisim'], 'Ad ve soyad bilgilerinizi eksiksiz girin.');
}
if (!gecerli_email($eposta)) {
    $hata(['eposta'], 'Geçerli bir e-posta adresi girin.');
}
if (strlen((string)$telefon) < 10) {
    $hata(['telefon'], 'Telefon numarası en az 10 haneli olmalıdır.');
}
// Uc zorunlu alan tek mesajda toplanmiyordu: eksik olan yalnizca adres ise il ve
// ilce de "hatali" gorunurdu. Kurallar ayri ayri sorgulanir, mesajlar daralir.
if ($il === '') {
    $hata(['il'], 'Lütfen bir il seçin.');
}
if ($ilce === '') {
    $hata(['ilce'], 'İlçe bilgisi zorunludur.');
}
if (mb_strlen($adres) < 10) {
    $hata(['adres'], 'Açık adres zorunludur ve en az 10 karakter olmalıdır.');
}
if (!in_array($odeme_tipi, ['havale', 'kart', 'kapida'], true)) {
    $hata(['odemesekli'], 'Geçerli bir ödeme yöntemi seçin.');
}
// Üst sinirlar: odeme formundaki alanlarin istendigi kadar doldurulmasini engeller.
// Icerik alanlari (isim, soyisim, ilce, eposta) bilincli olarak kolon
// genisliklerinden (sql/shopcore.sql :: CREATE TABLE siparisler) DAHA KUCUK
// secildi; hicbir sinir kolonun ustune cikamaz, yoksa INSERT kesilir.
// İstemcideki maxlength atlanabilir (DevTools, tarayici eki), bu yuzden asiri
// uzunluk sunucuda da reddedilir; MySQL'e kirpik ya da dogrudan hata giden
// deger degil, kullaniciya soylenen net bir mesaj gider.
if (mb_strlen($isim) > 32) {
    $hata(['isim'], 'Ad bilgisi en fazla 32 karakter olabilir.');
}
if (mb_strlen($soyisim) > 32) {
    $hata(['soyisim'], 'Soyad bilgisi en fazla 32 karakter olabilir.');
}
if (mb_strlen($eposta) > 64) {
    $hata(['eposta'], 'E-posta adresi en fazla 64 karakter olabilir.');
}
if (mb_strlen((string)$telefon) > 20) {
    $hata(['telefon'], 'Telefon numarası en fazla 20 karakter olabilir.');
}
if (mb_strlen($il) > 64) {
    $hata(['il'], 'İl bilgisi en fazla 64 karakter olabilir.');
}
if (mb_strlen($ilce) > 32) {
    $hata(['ilce'], 'İlçe bilgisi en fazla 32 karakter olabilir.');
}
if (mb_strlen($adres) > 500) {
    $hata(['adres'], 'Açık adres en fazla 500 karakter olabilir.');
}
if (mb_strlen($kurum) > 128) {
    $hata(['kurum'], 'Kurum/şirket adı en fazla 128 karakter olabilir.');
}
if (mb_strlen((string)$posta_kodu) > 5) {
    $hata(['postakodu'], 'Posta kodu en fazla 5 haneli olabilir.');
}
// SMTP header/enjeksiyon korumasi: girerlerde satir sonu karakteri kalmamali
$adsoyad = header_temizle($adsoyad);
$eposta  = header_temizle($eposta);
$telefon = header_temizle((string)$telefon);

if ($hatalar) {
    // Yazilanlar kaybolmasin: alan degerleri + mesajlar tek kullanimlik bir
    // snapshot olarak oturuma alinir ve odeme ekranina donulur (bkz.
    // satinal.php). Token tuketilmedigi icin ayni form tekrar gonderilebilir.
    $snapshot = static function ($deger, int $limit): string {
        $deger = str_replace("\0", '', trim((string)$deger));
        return mb_substr($deger, 0, $limit, 'UTF-8');
    };

    $_SESSION['odeme_formu'] = [
        'degerler' => [
            'isim'       => $snapshot($isim, 32),
            'soyisim'    => $snapshot($soyisim, 32),
            'eposta'     => $snapshot($eposta, 64),
            'telefon'    => $snapshot($telefon, 20),
            'il'         => $snapshot($il, 64),
            'ilce'       => $snapshot($ilce, 32),
            'adres'      => $snapshot($adres, 500),
            'kurum'      => $snapshot($kurum, 128),
            'postakodu'  => $snapshot($posta_kodu, 5),
            'odemesekli' => in_array($odeme_tipi, ['havale', 'kart', 'kapida'], true) ? $odeme_tipi : '',
            // Sozlesme onayi de kullanıcının kendi verdigi bir karardir; hatali
            // gonderimde yeniden isaretlenmek zorunda kalmasi gereksiz surtunme.
            'onay'       => !empty($_POST['onay']) ? 1 : 0,
        ],
        'hatalar' => $hatalar,
        'hatali_alanlar' => $hatali_alanlar,
    ];
    redirect('satinal.php');
}

// 3) Idempotency: token dogrulanip aninda tuketilr. Eksi/gecersiz ise siparis
//    hic yazilmaz (formu iki kez gonderme, geri don + gonder, proxy yeniden
//    gondermesi hepsi burada kirilir).
if (!siparis_token_dogrula($_POST['siparis_token'] ?? '')) {
    flash('uyari', 'Bu ödeme formu zaten işlenmiş ya da süresi dolmuş. Sepetiniz duruyor, lütfen ödeme adımını tekrarlayın.');
    redirect('sepetim.php');
}

// 4) Sepet yeniden DB'den okunur; istemci fiyat/adet degerine guvenilmez
$con        = db();
$sepetOzeti = sepet_ozeti();

if (!$sepetOzeti['kalemler']) {
    flash('uyari', 'Sepetiniz boş ya da içindeki ürünler artık satışta değil.');
    redirect('sepetim.php');
}

try {
    $con->beginTransaction();

    // 5) Stok kontrolu: satirlar ISLEM icinde kilitlenir (FOR UPDATE), boylece
    //    iki es zamanli siparis ayni stoku iki kez dusemez.
    $idler     = array_column($sepetOzeti['kalemler'], 'id');
    $yer_tutucu = implode(',', array_fill(0, count($idler), '?'));
    $stokSorgu = $con->prepare("SELECT id, urunadi, fiyat, stok FROM urunler WHERE id IN ($yer_tutucu) FOR UPDATE");
    $stokSorgu->execute($idler);
    $stoklar = [];
    foreach ($stokSorgu->fetchAll(PDO::FETCH_ASSOC) as $satir) {
        $stoklar[(int)$satir['id']] = $satir;
    }

    $stokYetersiz = [];
    foreach ($sepetOzeti['kalemler'] as $kalem) {
        $urun = $stoklar[$kalem['id']] ?? null;
        if ($urun === null) {
            $stokYetersiz[] = $kalem['urunadi'] . ' (artık satışta değil)';
        } elseif ((int)$urun['stok'] < $kalem['adet']) {
            $stokYetersiz[] = $kalem['urunadi'] . ' (son ' . (int)$urun['stok'] . ' adet)';
        }
    }

    if ($stokYetersiz) {
        $con->rollBack();
        flash('hata', 'Yetersiz stok: ' . implode(', ', $stokYetersiz) . '. Sepetiniz korunuyor, adetleri güncelleyebilirsiniz.');
        redirect('sepetim.php');
    }

    // 6) Siparis kaydi. numarasi UNIQUE: ayni saniyede celisme olursa farkli
    //    numarayla yeniden denenir (islem icinde basarisiz INSERT islemi
    //    MariaDB'de transaction'i dusurmez).
    $numarasi = null;
    for ($deneme = 0; $deneme < 5; $deneme++) {
        $aday = siparis_numarasi();
        try {
            $stmt = $con->prepare(
                "INSERT INTO siparisler
                    (numarasi, kullanici_id, adsoyad, eposta, telefon, il, ilce, adres, posta_kodu, kurum,
                     odeme_tipi, ara_toplam, kargo, toplam)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $aday,
                // Kimlik daima oturumdan gelir; POST'tan gelen bir id/deger asla
                // kullanilmaz (baska bir hesaba siparis yazma ihtimali yok).
                kullanici_id(),
                $adsoyad, $eposta, $telefon, $il, $ilce, $adres,
                $posta_kodu !== '' ? $posta_kodu : null,
                $kurum !== '' ? $kurum : null,
                $odeme_tipi,
                round($sepetOzeti['ara_toplam'], 2),
                round($sepetOzeti['kargo'], 2),
                round($sepetOzeti['toplam'], 2),
            ]);
            $numarasi = $aday;
            break;
        } catch (PDOException $e) {
            if ((string)$e->getCode() !== '23000') {
                throw $e; // benzersizlik celismesi disindaki hata yukari cikirilir
            }
        }
    }

    if ($numarasi === null) {
        $con->rollBack();
        flash('hata', 'Sipariş numarası üretilemedi. Lütfen tekrar deneyin.');
        redirect('sepetim.php');
    }

    $siparis_id = (int)$con->lastInsertId();

    // Kalemler: urun adi ve birim fiyat SIPARIS ANINDAKI degerle kopyalanir
    // (urunkaydi sonradan degisse bile gecmis siparis bozulmaz).
    $kalemStmt = $con->prepare(
        "INSERT INTO siparis_kalemleri (siparis_id, urun_id, urun_adi, birim_fiyat, adet, satir_toplam)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stokDusStmt = $con->prepare("UPDATE urunler SET stok = stok - ? WHERE id = ?");

    foreach ($sepetOzeti['kalemler'] as $kalem) {
        // Fiyat, kilitli okumada gelen sunucu degerinden alinir (sepet_ozeti ile
        // ayni istekte okundugu icin sapma yok; yine de tek kaynak kullanilir)
        $birim = (float)$stoklar[$kalem['id']]['fiyat'];
        $kalemStmt->execute([
            $siparis_id,
            $kalem['id'],
            mb_substr($stoklar[$kalem['id']]['urunadi'], 0, 128),
            round($birim, 2),
            (int)$kalem['adet'],
            round($birim * (int)$kalem['adet'], 2),
        ]);
        $stokDusStmt->execute([(int)$kalem['adet'], (int)$kalem['id']]);
    }

    $con->commit();
} catch (PDOException $e) {
    if ($con->inTransaction()) {
        $con->rollBack();
    }
    error_log('siparis_onay: ' . $e->getMessage());
    flash('hata', 'Sipariş kaydedilemedi. Sepetiniz korunuyor; lütfen tekrar deneyin.');
    redirect('sepetim.php');
}

// 7) Sepet yalnizca commit SONRASI temizlenir
$_SESSION['sepet'] = array();

// 8) Bildirim e-postasi: basarisizlik siparisi gecersiz kilmez, ama gizlenmez
$siparis = [
    'numarasi'   => $numarasi,
    'adsoyad'    => $adsoyad,
    'eposta'     => $eposta,
    'telefon'    => $telefon,
    'il'         => $il,
    'ilce'       => $ilce,
    'adres'      => $adres,
    'odeme_tipi' => $odeme_tipi,
    'ara_toplam' => $sepetOzeti['ara_toplam'],
    'kargo'      => $sepetOzeti['kargo'],
    'toplam'     => $sepetOzeti['toplam'],
];
$eposta_gonderdi = siparis_eposta_gonder($siparis, $sepetOzeti['kalemler']);

include "header.php";
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-7">
            <div class="text-center">
                <!-- Başarı İkonu -->
                <div class="mb-4">
                    <svg class="ik text-success" style="font-size: 5rem;" aria-hidden="true"><use href="#lk-check-circle"></use></svg>
                </div>

                <!-- Başlık -->
                <h1 class="mb-2">Siparişiniz Alındı</h1>
                <p class="fs-5 text-muted mb-4">
                    Sipariş numaranız: <strong class="text-dark"><?= esc($numarasi) ?></strong>
                </p>

                <?php if ($eposta_gonderdi): ?>
                    <div class="alert alert-success mb-4 text-start">
                        <p class="mb-0">
                            <strong>Teşekkür ederiz!</strong><br>
                            Sipariş detayları <strong><?= esc($eposta) ?></strong> adresine gönderildi.
                            Teslimat, belirttiğiniz adrese en kısa sürede yapılacaktır.
                        </p>
                    </div>
                <?php else: ?>
                    <!-- Dürüst davranis: mail sunucusu yoksa "gönderildi" diye yalan soylenmez -->
                    <div class="alert alert-warning mb-4 text-start">
                        <p class="mb-0">
                            <strong>Teşekkür ederiz!</strong><br>
                            Siparişiniz kaydedildi; ancak onay e-postası gönderilemedi.
                            Numaranızı (<strong><?= esc($numarasi) ?></strong>) not almanızı öneririz.
                        </p>
                    </div>
                <?php endif; ?>

                <!-- Siparis özeti -->
                <div class="card shadow-sm mb-4 text-start">
                    <div class="card-header bg-body">
                        <h4 class="baslik mb-0"><svg class="ik me-2" aria-hidden="true"><use href="#lk-receipt"></use></svg>Sipariş Özeti</h4>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless mb-0">
                            <thead>
                                <tr>
                                    <th>Ürün</th>
                                    <th class="text-center">Adet</th>
                                    <th class="text-end">Birim</th>
                                    <th class="text-end">Toplam</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sepetOzeti['kalemler'] as $kalem): ?>
                                <tr>
                                    <td><?= esc($kalem['urunadi']) ?></td>
                                    <td class="text-center"><?= (int)$kalem['adet'] ?></td>
                                    <td class="text-end"><?= para($kalem['birim_fiyat']) ?>&#8378;</td>
                                    <td class="text-end"><?= para($kalem['satir_toplam']) ?>&#8378;</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="border-top">
                                    <td colspan="3" class="text-end">Ara Toplam</td>
                                    <td class="text-end"><?= para($sepetOzeti['ara_toplam']) ?>&#8378;</td>
                                </tr>
                                <tr>
                                    <td colspan="3" class="text-end">Kargo</td>
                                    <td class="text-end">
                                        <?php if ($sepetOzeti['kargo'] == 0): ?>
                                            <span class="text-success">Ücretsiz</span>
                                        <?php else: ?>
                                            <?= para($sepetOzeti['kargo']) ?>&#8378;
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="3" class="text-end fw-bold">Toplam</td>
                                    <td class="text-end fw-bold fs-5 text-success"><?= para($sepetOzeti['toplam']) ?>&#8378;</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <div class="card-footer bg-body text-start">
                        <small class="text-muted">
                            <svg class="ik me-1" aria-hidden="true"><use href="#lk-truck"></use></svg>
                            Teslimat: <?= esc($adsoyad) ?> — <?= esc($il) ?>/<?= esc($ilce) ?>, <?= esc($adres) ?>
                        </small>
                    </div>
                </div>

                <!-- Butonlar -->
                <div class="d-grid gap-2 d-md-flex justify-content-center">
                    <a href="index.php" class="btn btn-primary btn-lg px-4">
                        <svg class="ik me-2" aria-hidden="true"><use href="#lk-home"></use></svg>Ana Sayfa
                    </a>
                    <a href="urunler.php" class="btn btn-outline-primary btn-lg px-4">
                        <svg class="ik me-2" aria-hidden="true"><use href="#lk-shopping-bag"></use></svg>Alışverişe Devam
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include "footer.php"; ?>
