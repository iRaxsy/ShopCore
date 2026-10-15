<?php
/**
 * CSRF korumasi. Token oturumda tutulur; form + AJAX header desteklenir.
 * Kullanici: csrf_alan() form icine, csf_dogrula() handler basinda.
 */

/** Geceli oturum tokeni (yoksa uretilir). */
function csrf_token(): string
{
    if (!session_id()) {
        app_session_start();
    }
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

/** Gizli form alani. */
function csrf_alan(): string
{
    return '<input type="hidden" name="_csrf" value="' . esc(csrf_token()) . '">';
}

/** <head> icine konulacak meta (AJAX X-CSRF-Token icin). */
function csrf_meta(): string
{
    return '<meta name="csrf-token" content="' . esc(csrf_token()) . '">';
}

/**
 * Gonderilen token'i okur.
 * Yalniz POST alani ve istek basligi kabul edilir: GET parametresi bilincli
 * olarak desteklenmiyor (token URL'e tasinirsa gecmis/Referer/proxy loglarina
 * ve ekran gorsellerine siziyor).
 */
function csrf_gelen(): string
{
    if (isset($_POST['_csrf'])) {
        return (string)$_POST['_csrf'];
    }
    if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        return (string)$_SERVER['HTTP_X_CSRF_TOKEN'];
    }
    return '';
}

/**
 * Token dogrular. Gecersiz/eksik ise durdurur.
 * JSON isteklerine JSON, diğerlerine 403 metin doner.
 */
function csrf_dogrula()
{
    if (!session_id()) {
        app_session_start();
    }
    $beklenen = $_SESSION['_csrf'] ?? '';
    $gelen = csrf_gelen();
    if ($beklenen === '' || $gelen === '' || !hash_equals($beklenen, $gelen)) {
        http_response_code(403);
        if (app_is_ajax()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'error', 'message' => 'Oturum dogrulamasi basarisiz (CSRF).'], JSON_UNESCAPED_UNICODE);
        } else {
            die('Guvensiz istek. Lutfen sayfayi yenileyip tekrar deneyin.');
        }
        exit;
    }
}

/** Login sonrasi eski token'i yeniler (session fixation korumasiyla birlikte). */
function csrf_yenile()
{
    unset($_SESSION['_csrf']);
    return csrf_token();
}

/** Istek AJAX/fetch mi? */
function app_is_ajax(): bool
{
    return (isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
}
