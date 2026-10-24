<?php
/**
 * Uygulama cekirdegi - tek giris noktasi.
 * Her sayfa/endpoint ilk satirda bunu include eder.
 */

define('APP_ROOT', dirname(__DIR__));

/**
 * Yerel yapilandirma okuyucusu.
 * config/yerel.php depoda tutulmaz (gercek degerler); Ornegini
 * config/yerel.orneke.php. Dosya yoksa guvenli varsayilanlar kullanilir.
 */
function app_env($anahtar, $varsayilan = null)
{
    static $yapilandirma = null;

    if ($yapilandirma === null) {
        $dosya = APP_ROOT . '/config/yerel.php';
        if (is_file($dosya)) {
            $yapilandirma = (array) require $dosya;
        } else {
            $yapilandirma = [];
            error_log('app_env: config/yerel.php bulunamadi, varsayilan degerler kullaniliyor.');
        }
    }

    return array_key_exists($anahtar, $yapilandirma) ? $yapilandirma[$anahtar] : $varsayilan;
}

// Gelistirme ortaminda hatalari goster, prod'da logla
define('APP_DEBUG', (bool) app_env('APP_DEBUG', false));

if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('log_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    // Hata logu: LOG_DOSYASI ayarlanmissa oraya, aksi halde php.ini'nin kendi
    // error_log hedefine yazilir. Varsayilan yol (uygulama klasorunun bir
    // ustu) web agacinin icine denk geliyorsa hicbir sey yazilmaz - log
    // dosyasi tarayiciyla indirilebilir hale gelir.
    $log_dosyasi = (string) app_env('LOG_DOSYASI', dirname(APP_ROOT) . '/php-errors.log');
    $log_klasor  = $log_dosyasi !== '' ? (realpath(dirname($log_dosyasi)) ?: '') : '';
    $web_koku    = !empty($_SERVER['DOCUMENT_ROOT']) ? (realpath($_SERVER['DOCUMENT_ROOT']) ?: '') : '';
    $disarida    = $web_koku === '' || rtrim(str_replace('\\', '/', $log_klasor), '/') !== rtrim(str_replace('\\', '/', $web_koku), '/');
    if ($log_klasor !== '' && $disarida && is_writable($log_klasor)) {
        ini_set('error_log', rtrim(str_replace('\\', '/', $log_klasor), '/') . '/' . basename($log_dosyasi));
    }
}

mb_internal_encoding('UTF-8');
date_default_timezone_set('Europe/Istanbul');

// Proje taban URL'i (XAMPP alt klasor bagimliligini bitirir: /shopcore/ gibi).
// SCRIPT_NAME icinde proje kok klasorunun adini bularak taban yol uretir.
$app_base = '/';
if (!empty($_SERVER['SCRIPT_NAME'])) {
    $kok_ad  = basename(APP_ROOT);                 // or. "shopcore"
    $yol     = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
    $konum   = strpos($yol, '/' . $kok_ad . '/');
    if ($konum !== false) {
        $app_base = substr($yol, 0, $konum) . '/' . $kok_ad . '/';
    }
}
define('APP_BASE_URL', $app_base);

// Kargo politikasi - esik ve ucret tek kaynaktan yonetilsin
define('KARGO_ESIGI', (float) app_env('KARGO_ESIGI', 1000));      // bu tutar ve uzeri siparislerde kargo ucretsiz
define('KARGO_UCRETI', (float) app_env('KARGO_UCRETI', 99.99));   // esik altindaki siparislerde uygulanan sabit ucret

// Gorseli olmayan urunlerde kullanilan ortak dosya. Sunucudaki adla birebir
// ayni olmali; silme kodlari bu sabit uzerinden karsilastirir (yanlis ad
// yazilirsa koruma sessizce devre disi kalir).
define('APP_DEFAULT_GORSEL', (string) app_env('DEFAULT_GORSEL', 'default.png'));

// Gorsel isleme hattinin boyut kurallari (yalnizca gd etkinse isler; kapaliysa
// yuklenen dosya oldugu gibi kalir). RESIM_MAX: sunucuda tutulan gorselin
// azami genisligi. RESIM_KART: urun kartlarinda render edilen genislik -
// resim_yolu($ad, RESIM_KART_GENISLIK) o boyutun .webp varyantini arar.
define('RESIM_MAX_GENISLIK',  (int) app_env('RESIM_MAX_GENISLIK', 1200));
define('RESIM_KART_GENISLIK', (int) app_env('RESIM_KART_GENISLIK', 400));

// Iletisim bilgileri - footer, iletisim karti ve e-posta alicisi buradan okunur.
// Degerler config/yerel.php'de tutulur; bos birakilan alan ekranda gosterilmez.
define('SITE_EPOSTA',  (string) app_env('SITE_EPOSTA', ''));
define('SITE_TELEFON', (string) app_env('SITE_TELEFON', ''));
define('SITE_FAX',     (string) app_env('SITE_FAX', ''));
define('SITE_ADRES',   (string) app_env('SITE_ADRES', ''));
define('SITE_WEB',     (string) app_env('SITE_WEB', ''));

// Yerel varlik klasorleri (CDN bagimligi YOK; surum dosyanin kendisinde gomulu).
// Guncellemek icin content/vendor/... altindaki dosyalari yenisiyle degistir;
// varlik_js() dosya mtime'unu cache-buster olarak kullanir, kod degismez.
define('APP_VARLIK_BOOTSTRAP_CSS', 'content/vendor/bootstrap/bootstrap.min.css');
define('APP_VARLIK_BOOTSTRAP_JS',  'content/vendor/bootstrap/bootstrap.bundle.min.js');
define('APP_VARLIK_SWAL_JS',       'content/vendor/sweetalert2/sweetalert2.min.js');
define('APP_VARLIK_SWAL_CSS',      'content/vendor/sweetalert2/sweetalert2.min.css');
define('APP_VARLIK_FONT_CSS',      'content/vendor/fonts/quicksand.css');
// CKEditor 5 classic build - yalnizca 4 form sayfasinda ckeditor() helper'i ile
// yuklenir (~1.3 MB). translations/tr.js, ckeditor.js'in kardestesi olarak
// otomatik cozumlenir; language: 'tr' config'i bu dosyayi bekler.
define('APP_VARLIK_CKEDITOR_JS',   'content/vendor/ckeditor/ckeditor.js');

require_once __DIR__ . '/fonksiyonlar.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/yetki.php';
require_once __DIR__ . '/kullanici.php';
require_once __DIR__ . '/sayfalama.php';
require_once __DIR__ . '/dosya_yukleme.php';

/**
 * Cikti baslamadan oturumu başlatir; guvenli cookie parametreleri.
 * Idempotent: tekrar cagrilarak hata vermez.
 */
function app_session_start()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    if (headers_sent($dosya, $satir)) {
        // Cikti baslamis; oturumu yine de baslatma try/catch yerine logla
        error_log("app_session_start: headers already sent in {$dosya}:{$satir}");
        return;
    }
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    // Oturum cookie adi varsayilan "PHPSESSID" yerine projeye ozel: sunucuda
    // baska bir uygulama ile carpismasi ve imza tahmini zorlasin.
    session_name('shopcore_sid');
    session_start();

    // Sepet altyapisi (eski header.php:2-4 davranisi korunur)
    $_SESSION['sepet'] = isset($_SESSION['sepet']) ? $_SESSION['sepet'] : array();
}

/**
 * Kuresel guvenlik agi: hicbir yerde yakalanmayan istisnalar (oz. tablosuz
 * veritabanindaki 42S02) artik ham fatal + stack trace yerine duzgun, tek
 * parcali bir uyari sayfasi gosterir. Sablonlar (header/footer) DB'ye muhtac
 * oldugundan sayfa BAGIMSIZ (inline stilli) basilir.
 */
set_exception_handler(function (Throwable $e) {
    error_log('Yakalanmayan hata: ' . get_class($e) . ' - ' . $e->getMessage());

    $mesaj = db_hata_mesaji($e, 'Beklenmeyen bir hata oluştu.');

    try {
        if (app_is_ajax()) {
            // AJAX/fetch ucuna HTML iskelet yerine JSON don; response.json()
            // okuyan turler (urun-form.js) SyntaxError almaz. Anahtar seti
            // sepete_ekle (success) ve csrf/yetki (status) sekilleriyle bagdasir.
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode([
                'success' => false,
                'status'  => 'error',
                'message' => APP_DEBUG ? $e->getMessage() : $mesaj,
            ], JSON_UNESCAPED_UNICODE);
            return;
        }
        if (headers_sent()) {
            // Kismi cikti var: tam iskelet basma, yalnizca kisa bandi ekle.
            echo '<div class="alert alert-danger rounded-0 mb-0 py-3 text-center">' . esc($mesaj) . '</div>';
            return;
        }
        http_response_code(500);
        $detay = APP_DEBUG
            ? '<pre style="margin-top:16px;text-align:left;white-space:pre-wrap;color:#b02a37;font-size:.85rem">'
              . esc((string) $e) . '</pre>'
            : '';
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="tr"><head><meta charset="utf-8">'
           . '<meta name="viewport" content="width=device-width, initial-scale=1">'
           . '<title>ShopCore | Beklenmeyen Hata</title></head>'
           . '<body style="font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;'
           . 'background:#f6f7f9;color:#212529;display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0">'
           . '<div style="max-width:520px;width:90%;background:#fff;border:1px solid #e5e7eb;border-radius:12px;'
           . 'box-shadow:0 2px 12px rgba(13,59,102,.08);padding:28px 24px;text-align:center">'
           . '<div style="font-size:2rem;line-height:1;color:#0d3b66;font-weight:700;margin-bottom:8px">ShopCore</div>'
           . '<h1 style="font-size:1.15rem;margin:0 0 8px;color:#0d3b66">' . esc($mesaj) . '</h1>'
           . '<p style="margin:0;color:#6c757d;font-size:.95rem">Lutfen daha sonra tekrar deneyin.'
           . ' Sorun surerse yonetici ile iletisime gecin.</p>'
           . $detay . '</div></body></html>';
    } catch (Throwable $ice) {
        // Isleyicinin kendisi de patlarsa en kotu durum: duz metin.
        echo 'Sistem su anda kullanilamiyor.';
    }
});
