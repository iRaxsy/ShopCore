<?php
/**
 * Kullanici cikis endpoint'i.
 *
 * Yalnizca POST + CSRF: GET ile cikis, herhangi bir baglantiya tiklanarak
 * (veya bir CSRF saldirisiyla) oturumu dusurebildigi icin admin panelinde de
 * kaldirilmisti; ayni kural burada da gecerli.
 */
require_once __DIR__ . '/includes/bootstrap.php';
app_session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}
csrf_dogrula();

// Sepet, CSRF tokeni ve tema tercihi KORUNUR: yalnizca kullanici anahtarlari
// silinir (bkz. kullanici_cikis()).
kullanici_cikis();

redirect('index.php');
