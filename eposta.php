<?php
require_once __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('hakkimizda.php');
}

app_session_start();
csrf_dogrula();

$alici = site_bilgisi('eposta'); // alici adres config/yerel.php'den gelir

// Alici tanimli degilse (veya gecersizse) hicbir sey gonderilmez; kullanici
// gercek durumla bilgilendirilir (bir zamanlar kaynakta duran "x@shopcore.com"
// tutucu degeri artik yayinda degil).
if (!gecerli_email($alici)) {
    error_log('eposta.php: gecerli alici adresi tanimli degil (SITE_EPOSTA bos).');
    redirect('hakkimizda.php?islem=kapali');
}

$kimden = temiz_metin($_POST['kimden'] ?? '');
$eposta = temiz_metin($_POST['eposta'] ?? '');
$konu   = temiz_metin($_POST['konu'] ?? '');
$mesaj  = trim((string)($_POST['mesaj'] ?? ''));

// Dogrulama: zorunlu alanlar + gecerli e-posta
if ($kimden === '' || $eposta === '' || $konu === '' || $mesaj === '' || !gecerli_email($eposta)) {
    redirect('hakkimizda.php?islem=hata');
}

// SMTP header enjeksiyonunu onle: header/subject degerlerinde \r\n olmamali
$kimden_h = header_temizle($kimden);
$eposta_h = header_temizle($eposta);
$konu_h   = header_temizle($konu);

// govde: HTML yerine duz metin gonder, kullanici girisini escape'le
$body = "Yeni mesaj (web iletisim formu)\r\n\r\n"
      . "Isim: " . $kimden . "\r\n"
      . "E-posta: " . $eposta . "\r\n\r\n"
      . "Mesaj:\r\n" . $mesaj;

$tanimlar  = "MIME-Version: 1.0\r\n";
$tanimlar .= "Content-Type: text/plain; charset=UTF-8\r\n";
$tanimlar .= "From: " . '=?UTF-8?B?' . base64_encode($kimden_h) . '?= <' . $alici . ">\r\n";
$tanimlar .= "Reply-To: " . $eposta_h . "\r\n";
$tanimlar .= "X-Mailer: PHP";

// mail() bool doner; try/catch yerine donus degerini kontrol et
if (@mail($alici, $konu_h, $body, $tanimlar)) {
    redirect('hakkimizda.php?islem=tamam');
} else {
    error_log('eposta.php: mail() basarisiz');
    redirect('hakkimizda.php?islem=hata');
}
