<?php
/**
 * Kullanici kimlik kapisi. Satis hattindaki sayfa/endpoint'lerin (satinal.php,
 * siparis_onay.php, hesabim.php, siparislerim.php) ilk satirlarinda cagrilir.
 *
 * Neden ayri dosya/tablo: admin/login.php dogrudan `yetkililer` tablosundan
 * dogrulama yapiyor ve o tabloya kaydedilen HER hesabin panel erisimi olurdu.
 * Site kullanicilari `kullanicilar` tablosunda, oturum anahtarlari ise
 * `kullanici_id` altinda tutulur; admin (`loginkey`/`yetkili_id`) ile kesisim
 * yoktur. Iki tablonun sinirlari sql/shopcore.sql'de tanimlidir; panel
 * erisimi yalnizca `yetkililer` kaydina baglidir.
 */

/** Oturumda kullanici girisi var mi? */
function kullanici_girisi_var_mi(): bool
{
    if (!session_id()) {
        app_session_start();
    }
    return !empty($_SESSION['kullanici_id']);
}

/** Giris yapan kullanicinin id'si (yoksa null). */
function kullanici_id()
{
    return isset($_SESSION['kullanici_id']) ? (int)$_SESSION['kullanici_id'] : null;
}

/**
 * Giris kimligi normalizasyonu: hem kayit hem giris AYNI kurali uygulamali,
 * aksi halde "Ahmet@X.com" ile kayit olan "ahmet@x.com" ile giremez.
 */
function kullanici_eposta_normalize($eposta): string
{
    return mb_strtolower(trim((string)$eposta), 'UTF-8');
}

/**
 * Oturumdaki kullanici kaydi (id, adsoyad, eposta, telefon, il, ilce, adres,
 * posta_kodu). Ayni istekte tekrar tekrar sorgu calismaz (static ozet); header,
 * odeme formu ve hesap sayfasi ayni veriyi okur. Kayit silinmisse bos dizi
 * doner ve kapilar kendiliginden misafir davranisina doner.
 */
function kullanici_bilgisi(): array
{
    static $bilgi = null;

    if ($bilgi !== null) {
        return $bilgi;
    }

    $id = kullanici_id();
    if (!$id) {
        return $bilgi = [];
    }

    try {
        $stmt = db()->prepare('SELECT id, adsoyad, eposta, telefon, il, ilce, adres, posta_kodu FROM kullanicilar WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $satir = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('kullanici_bilgisi: ' . $e->getMessage());
        return $bilgi = [];
    }

    if (!$satir) {
        // Hesap silinmis: oturumdaki bayil anahtari birakma
        unset($_SESSION['kullanici_id']);
        return $bilgi = [];
    }

    return $bilgi = $satir;
}

/**
 * Kullanici girisi ister. Giris yoksa:
 *  - AJAX/JSON => 401 JSON,
 *  - sayfa     => giris.php'ye yonlendirir (sepet ve CSRF tokeni KORUNUR).
 * $csrf_iste: true ise gelen istegin CSRF tokenini de dogrular (yazici islemler).
 */
function kullanici_zorunlu($csrf_iste = false)
{
    // Oturumdaki id'ye karsilik gelen hesap gercekten var mi? Silinmis bir
    // hesapla yola devam etmek FK'li yazimlarda (siparisler.kullanici_id)
    // kirildigi icin kapi bunu da denetler.
    if (!kullanici_girisi_var_mi() || !kullanici_bilgisi()) {
        kullanici_cikis(); // oturumdaki bayil anahtarlari temizle

        http_response_code(401);
        if (app_is_ajax()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'error', 'message' => 'Kullanici girisi gerekli. Lutfen giris yapin.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        flash('uyari', 'Sipariş verebilmek için giriş yapmanız gerekiyor.');
        redirect('giris.php?geri=' . urlencode(geri_hedefi('sepetim.php')));
    }
    if ($csrf_iste) {
        csrf_dogrula();
    }
}

/**
 * Kullanici cikisi. admin_cikis()'inden BILINCLI olarak farkli: oturumu tamamen
 * yok etmez, yalnizca kullanici anahtarlarini duser. Boylece sepet, CSRF tokeni
 * ve tema tercihi korunur; kullanici cikis yaptiktan sonra alisverise
 * kaldigi yerden misafir olarak devam edebilir.
 */
function kullanici_cikis()
{
    if (!session_id()) {
        app_session_start();
    }
    unset($_SESSION['kullanici_id'], $_SESSION['_kullanici_giris_deneme'], $_SESSION['_kayit_deneme']);
}

/**
 * Giris/kayit kaba kuvvet sayaci ($_SESSION tabanli, admin/login.php deseniyle
 * ayni esik). $artirilmadiysa mevcut degeri dondurur.
 */
function kullanici_giris_sayaci($alan = '_kullanici_giris_deneme', $artir = false): int
{
    if (!session_id()) {
        app_session_start();
    }
    $deneme = (int)($_SESSION[$alan] ?? 0);

    if ($artir) {
        $deneme++;
        $_SESSION[$alan] = $deneme;
    }

    return $deneme;
}

/**
 * `geri` hedefini yalnizca BEYAZ LISTEDEKI proje sayfalarindan birine izin
 * verecek sekilde temizler. once POST (formun tasidigi deger), sonra GET
 * okunur. Harici URL, protokol ve sorgu dizesi kabul edilmez; boylece giris
 * sonrasi acik yonlendirme (open redirect) kapisi kalmaz.
 * Eslesme yoksa $varsayilan doner.
 */
function geri_hedefi($varsayilan = 'index.php'): string
{
    $gelen = (string)($_POST['geri'] ?? $_GET['geri'] ?? '');
    $gelen = strtolower(trim($gelen));
    // "urunler.php?sayfa=2" gibi gelen degerlerde sorgu kismini at
    $gelen = basename((string)strtok($gelen, '?#'));

    $izinli = [
        'index.php', 'urunler.php', 'urundetay.php', 'sepetim.php',
        'hesabim.php', 'siparislerim.php', 'giris.php', 'kayit.php',
        'hakkimizda.php',
    ];

    return in_array($gelen, $izinli, true) ? $gelen : $varsayilan;
}
