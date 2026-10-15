<?php
/**
 * Yetkilendirme kapisi. Admin sayfa/endpoint'lerinin ilk satirinda cagrilir.
 *
 * Oturum anahtarlari site kullanicisindan AYRIDIR: admin loginkey/yetkili_id,
 * kullanici kullanici_id (bkz. includes/kullanici.php). Ayni anahti paylasan iki
 * kimlik katmani, birinin digeri ile panel erisimi kazanmasina yol acar.
 */

/** Oturumda admin girisi var mi? */
function admin_girisi_var_mi(): bool
{
    if (!session_id()) {
        app_session_start();
    }
    return !empty($_SESSION['loginkey']);
}

/**
 * Admin yetkisi ister. Giris yoksa:
 *  - AJAX/JSON => 401 JSON,
 *  - sayfa     => login.php'ye yonlendirir.
 * $csrf_iste: true ise gelen istegin CSRF tokenini de dogrular (yazici islemler).
 */
function admin_zorunlu($csrf_iste = false)
{
    if (!admin_girisi_var_mi()) {
        http_response_code(401);
        if (app_is_ajax()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'error', 'message' => 'Oturum gerekli. Lutfen giris yapin.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        redirect('admin/login.php');
    }
    if ($csrf_iste) {
        csrf_dogrula();
    }
}

/** Giris yapan yetkilinin id'si (yoksa null). */
function admin_id()
{
    return isset($_SESSION['yetkili_id']) ? (int)$_SESSION['yetkili_id'] : null;
}

/** Admin cikis islemi (oturumu tamamen temizler). */
function admin_cikis()
{
    if (!session_id()) {
        app_session_start();
    }
    $_SESSION = [];
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    session_destroy();
}
