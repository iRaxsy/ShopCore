-- ---------------------------------------------------------------------------
-- ShopCore - veritabanı KURULUM DOSYASI (tek dosya, kendi kendine yeter)
-- ---------------------------------------------------------------------------
-- Bu dosya veritabanini sifirdan kurar:
--   * Tum tablolari DROP TABLE IF EXISTS + CREATE TABLE ile yeniden olusturur.
--   * `yetkililer` tablosuna TEK admin hesabi ekler (bkz. yetkililer CREATE
--     TABLE sonrasindaki INSERT). Giris noktasi: /ShopCore/admin/login.php
--       kusanci : admin
--       sifre   : 123456
--
-- DIKKAT: MEVCUT bir kurulumda calistirilirsa TUM VERI SILINIR. Yalniz
-- ilk kurulum veya temiz test icin kullan. Veri kaybetmeden sema guncellemek
-- icin ayri migration dosyalari gerekir (proje konvansiyonunda tutulmuyor;
-- gelistirme sirasinda panelden ekle/duzelt/sil akislari kullanilir).
--
-- Guvenlik notu: sql/ klasoru .htaccess ile HTTP erisimine kapalidir; bu
-- dokum yalniz yerel araclarla okunur (mysql istemcisi, phpMyAdmin > Import).
--
-- Yayina almadan once yapilacaklar:
--   1. Panelde admin parolasini degistir (admin/yetkili/duzelt.php).
--   2. Bu dosyadaki INSERT satirindaki hash'i ya sil ya da kendi hash'inle
--      degistir - kaynak dosyada "123456" hash'i acik duruyor.
--
-- Sema notlari:
--   * Her sey utf8mb4 / utf8mb4_turkish_ci; PDO baglantisiyla ayni kolasyon,
--     "Illegal mix of collations" riski yok.
--   * urunler.fiyat DECIMAL(10,2) (double degil) ve urunler.stok INT UNSIGNED.
--   * urunler.giris_tarihi / dzltm_tarihi henuz duruyor; kod her ikisini de
--     okuyor. Kolon dusumu, tarih okumalari tek kaynaga indirildikten sonra
--     ayri bir adim olacak.
--   * siparisler + siparis_kalemleri: siparis donem kaydidir; kalem, urun
--     adini ve birim fiyatini kopyalar (urun silinirse urun_id NULL olur,
--     kayit kaybolmaz).
--   * Cift kimlik yigini: admin paneli hesaplari `yetkililer`, site uye
--     hesaplari `kullanicilar`. Oturum anahtarlari da ayri: admin
--     $_SESSION['yetkili_id'] + loginkey, uye $_SESSION['kullanici_id'].
--     siparisler.kullanici_id -> kullanicilar.id FK ON DELETE SET NULL;
--     hesap silinse siparis donem kaydi (adsoyad/eposta/adres kopyasi) kalir.
--   * siparisler.notlar kolonu YOK; Admin Notlari arayuzu kaldirildi, siparis
--     detayi salt okunur. Durum degisimi yalniz admin/siparis/liste.php'deki
--     hizli durum formundan yapilir.
-- ---------------------------------------------------------------------------

CREATE DATABASE IF NOT EXISTS `shopcore` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_turkish_ci;
USE `shopcore`;

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------
--
-- Tablo yapisi: `kategoriler`
--
-- NOT: kategoriadi UNIQUE. Ayni adda iki kategori olusamaz; sunucu tarafi
-- dogrulamasi da var (admin/kategori/ekle.php), ama yaris kosulunda DB son
-- sozu soyler.
--

DROP TABLE IF EXISTS `kategoriler`;
CREATE TABLE `kategoriler` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kategoriadi` varchar(128) NOT NULL,
  `aciklama` mediumtext NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_kategoriadi` (`kategoriadi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- --------------------------------------------------------
--
-- Tablo yapisi: `urunler`
--

DROP TABLE IF EXISTS `urunler`;
CREATE TABLE `urunler` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `urunadi` varchar(128) NOT NULL,
  `aciklama` mediumtext NOT NULL,
  `fiyat` decimal(10,2) NOT NULL DEFAULT 0.00,
  `stok` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `giris_tarihi` datetime NOT NULL,
  `dzltm_tarihi` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `resim` varchar(128) DEFAULT NULL,
  `kategori_id` int(11) DEFAULT NULL,
  `olusturma_tarihi` timestamp NOT NULL DEFAULT current_timestamp(),
  `guncelleme_tarihi` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_kategori_id` (`kategori_id`),
  KEY `ix_giris_tarihi` (`giris_tarihi`),
  KEY `ix_kategori_id_sira` (`kategori_id`,`id`),
  CONSTRAINT `fk_kategori_id` FOREIGN KEY (`kategori_id`) REFERENCES `kategoriler` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- --------------------------------------------------------
--
-- Tablo yapisi: `urun_resimleri`
--
-- ix_urun_sira: galeri okumasi (WHERE urun_id = ? ORDER BY sira) artik
-- tam tarama degil, indeks taramasi.
--

DROP TABLE IF EXISTS `urun_resimleri`;
CREATE TABLE `urun_resimleri` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `urun_id` int(11) NOT NULL,
  `resim_yolu` varchar(255) NOT NULL,
  `sira` int(11) DEFAULT 1,
  `eklenme_tarihi` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `urun_id` (`urun_id`),
  KEY `ix_urun_sira` (`urun_id`,`sira`),
  CONSTRAINT `urun_resimleri_ibfk_1` FOREIGN KEY (`urun_id`) REFERENCES `urunler` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- --------------------------------------------------------
--
-- Tablo yapisi: `yetkililer`
--
-- Admin paneli hesaplari. Erisim yalnizca bu tablodaki kayitlarla kurulur;
-- `sifre` password_hash() (bcrypt) degeri tutar, duz metin parola bu sema ve
-- kod tarafinda hicbir noktada kabul edilmez. kadi UNIQUE (uk_yetkili_kadi)
-- -> giris tekil anahtari. `kullanicilar` (site uye) tablosundan AYRIDIR;
-- siteye kayitli bir kullanicinin `kullanicilar` kaydinin varligi, ona admin
-- paneline erisim HAKKI vermez.
--

DROP TABLE IF EXISTS `yetkililer`;
CREATE TABLE `yetkililer` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `adsoyad` varchar(128) NOT NULL,
  `kadi` varchar(64) NOT NULL,
  `sifre` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_yetkili_kadi` (`kadi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- --------------------------------------------------------
--
-- TEK ADMIN HESABI (kurulum tohumu)
--
--   id   : 1        <-- sabit; panel buraya kilitli davranis uygular
--   kadi : admin
--   sifre: 123456   (asagidaki string PASSWORD_BCRYPT hash'idir; duz metin
--                     degil. admin/login.php password_verify ile dogrular.)
--
-- !!! KORUMALI KAYIT !!!
-- id=1 satiri admin/yetkili/sil.php tarafindan "korunuyor" yanitiyla reddedilir;
-- admin/yetkili/liste.php'de bu satirin "Sil" butonu render edilmez (shield
-- ikonu var). Baska bir yetkili (id != 1) oturum acsa bile bu hesap
-- silinemez. Adi/kadi/sifre guncellemesi admin/yetkili/duzelt.php ile
-- serbesttir - koruma yalniz DELETE ucuna uygulanir.
--
-- YAYINA ALMADAN ONCE PAROLAYI MUTLAKA DEGISTIR:
--   1. Panele gir -> admin/yetkili/duzelt.php?id=1 -> yeni parola.
--   2. Sonraki kurulumlarda bu INSERT'i ya sil ya da yeni hash'inle degistir
--      (hash = C:\xampp\php\php.exe -r "echo password_hash('YENI-PAROLA',
--      PASSWORD_BCRYPT);").
--
-- Neden id=1 sabit yazildi? AUTO_INCREMENT 1'den baslar; sabit id, koruma
-- kuralinin (sil.php :: $id === 1 hard-block) tek tasinabilir referansi.
-- Dosya tekrar import edildiginde (DROP TABLE + CREATE TABLE bastan oldugu
-- icin) celisma cikmaz.
--
INSERT INTO `yetkililer` (`id`, `adsoyad`, `kadi`, `sifre`) VALUES
(1, 'Site Yoneticisi', 'admin', '$2y$10$KYf4npcEgxwSomE0oEnAZ.4lmnkfhDCDODkdgRv5mf41pjOkxHqVa');

-- --------------------------------------------------------
--
-- Tablo yapisi: `kullanicilar`
--
-- Siteye kayitli kullanicilarin (magaza musterilerinin) giris kimligi. Admin
-- tablosu `yetkililer` ile AYRI tutulur: oturum anahtari farkli
-- ($_SESSION['kullanici_id'] vs loginkey/yetkili_id), bu tablonun admin
-- yetkisiyle hicbir ilgisi yoktur.
--
-- eposta UNIQUE (uk_kullanici_eposta) -> giris tekil; sunucu tarafi kontrolun
-- yanisira yarismali iki kayitta DB kod 23000 ile INSERT'i kirar (kayit.php
-- yakalar). sifre password_hash() (bcrypt) degeri tutar; duz metin parola kabul
-- edilmez. Adres kolonlari siparisler ile ayni ust sinirlari tasiyor ki profil
-- formundan dolan odeme formu oldugu gibi gonderebilsin (adres VARCHAR(500):
-- sunucu dogrulama tavani 500 karakter).
--

DROP TABLE IF EXISTS `kullanicilar`;
CREATE TABLE `kullanicilar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `adsoyad` varchar(128) NOT NULL,
  `eposta` varchar(128) NOT NULL,
  `telefon` varchar(32) NOT NULL DEFAULT '',
  `il` varchar(64) NOT NULL DEFAULT '',
  `ilce` varchar(64) NOT NULL DEFAULT '',
  `adres` varchar(500) NOT NULL DEFAULT '',
  `posta_kodu` varchar(10) DEFAULT NULL,
  `sifre` varchar(255) NOT NULL,
  `olusturma_tarihi` timestamp NOT NULL DEFAULT current_timestamp(),
  `guncelleme_tarihi` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_kullanici_eposta` (`eposta`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- --------------------------------------------------------
--
-- Tablo yapisi: `siparisler`
--
-- numarasi CHAR(16): 'SC' + yymmddHis + 2 rastgele rakam (fonksiyonlar.php
-- ::siparis_numarasi()). UNIQUE oldugu icin celisme INSERT'i kirar ve
-- siparis_onay.php farkli numarayla yeniden dener.
--
-- Tutar kolonlari SIPARIS ANINDAKI sunucu hesaplamasinin kopyasidir; istemci
-- degeri hicbir zaman DB'ye yazilmaz.
--
-- kullanici_id: siparisi veren hesabin kopyasi degil, referansi. Misafir
-- siparislerde NULL kalir (hesap silinirse FK ON DELETE SET NULL ile siparis
-- kaydi korunur; adsoyad/eposta/adres kolonlari zaten siparide yazili).
--
-- Bu tablonun `notlar` kolonu YOK; Admin Notlari arayuzu kaldirildi, siparis
-- detayi (admin/siparis/detay.php) salt okunur. Durum degisimi yalniz
-- admin/siparis/liste.php'deki hizli durum formundan yapilir.
--

DROP TABLE IF EXISTS `siparisler`;
CREATE TABLE `siparisler` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `numarasi` char(16) NOT NULL,
  `kullanici_id` int(11) DEFAULT NULL,
  `adsoyad` varchar(128) NOT NULL,
  `eposta` varchar(128) NOT NULL,
  `telefon` varchar(32) NOT NULL,
  `il` varchar(64) NOT NULL,
  `ilce` varchar(64) NOT NULL,
  `adres` text NOT NULL,
  `posta_kodu` varchar(10) DEFAULT NULL,
  `kurum` varchar(128) DEFAULT NULL,
  `odeme_tipi` enum('havale','kart','kapida') NOT NULL DEFAULT 'havale',
  `ara_toplam` decimal(10,2) NOT NULL DEFAULT 0.00,
  `kargo` decimal(10,2) NOT NULL DEFAULT 0.00,
  `toplam` decimal(10,2) NOT NULL DEFAULT 0.00,
  `durum` enum('alindi','hazirlaniyor','kargoda','kapandi','iptal') NOT NULL DEFAULT 'alindi',
  `olusturma_tarihi` timestamp NOT NULL DEFAULT current_timestamp(),
  `guncelleme_tarihi` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_siparis_no` (`numarasi`),
  KEY `ix_siparis_tarih` (`olusturma_tarihi`),
  KEY `ix_siparis_durum` (`durum`),
  KEY `ix_siparis_kullanici` (`kullanici_id`),
  CONSTRAINT `fk_siparis_kullanici` FOREIGN KEY (`kullanici_id`) REFERENCES `kullanicilar` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- --------------------------------------------------------
--
-- Tablo yapisi: `siparis_kalemleri`
--
-- urun_adi ve birim_fiyat donem kaydidir (kopya): urun sonradan silinse ya da
-- fiyat degisse bile gecmis siparis oldugu gibi okunur.
--

DROP TABLE IF EXISTS `siparis_kalemleri`;
CREATE TABLE `siparis_kalemleri` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `siparis_id` int(11) NOT NULL,
  `urun_id` int(11) DEFAULT NULL,
  `urun_adi` varchar(128) NOT NULL,
  `birim_fiyat` decimal(10,2) NOT NULL DEFAULT 0.00,
  `adet` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `satir_toplam` decimal(10,2) NOT NULL DEFAULT 0.00,
  `olusturma_tarihi` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_kalem_siparis` (`siparis_id`),
  KEY `ix_kalem_urun` (`urun_id`),
  CONSTRAINT `fk_kalem_siparis` FOREIGN KEY (`siparis_id`) REFERENCES `siparisler` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_kalem_urun` FOREIGN KEY (`urun_id`) REFERENCES `urunler` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

SET FOREIGN_KEY_CHECKS = 1;
