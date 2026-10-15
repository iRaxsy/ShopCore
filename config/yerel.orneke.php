<?php
/**
 * Yerel yapilandirma ORNEGI.
 *
 * Bu dosyayi config/yerel.php adıyla kopyalayin ve gercek degerleri girin.
 * config/yerel.php surum kontrolunde tutulmaz (.gitignore); boylece sifre,
 * veritabani bilgisi ve iletisim adresleri depoya girmez.
 *
 * Okuyucu: includes/bootstrap.php::app_env()
 */

return [
    // Gelistirme ortaminda hatalar ekrana basilir; yayinda kapali tutulmali
    'APP_DEBUG'    => false,

    // Hata logunun yolu (APP_DEBUG kapaliyken). Web agaci disinda bir yol verin;
    // null/bos birakilirsa php.ini'nin kendi error_log hedefi kullanilir.
    'LOG_DOSYASI'  => null,

    // Veritabani (includes/db.php)
    'DB_HOST'      => 'localhost',
    'DB_ADI'       => 'shopcore',
    'DB_KULLANICI' => 'root',
    'DB_SIFRE'     => '',

    // Kargo politikasi
    'KARGO_ESIGI'  => 1000,
    'KARGO_UCRETI' => 99.99,

    // Gorseli olmayan urunlerde kullanilan dosya (content/images altinda)
    'DEFAULT_GORSEL' => 'default.png',

    // CKEditor 5 artik YEREL: content/vendor/ckeditor/ckeditor.js (Bootstrap ve
    // SweetAlert2 ile ayni kalip). Surum dosyanin kendisindedir; config'ten
    // yonetilmez. Yenilemek icin dosyayi indirip uzerine yazmaniz yeterli.

    // Iletisim bilgileri. Bos birakilan alan ekranda hic gosterilmez.
    // SITE_EPOSTA ayni zamanda iletisim formunun alici adresidir; bos ise
    // form mesaj iletmaz (eposta.php).
    'SITE_EPOSTA'  => '',
    'SITE_TELEFON' => '',
    'SITE_FAX'     => '',
    'SITE_ADRES'   => '',
    'SITE_WEB'     => '',
];
