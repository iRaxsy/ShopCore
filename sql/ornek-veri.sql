-- ---------------------------------------------------------------------------
-- ShopCore - ORNEK VERI (demo seed) — YALNIZCA VERI
-- ---------------------------------------------------------------------------
-- Bu dosya SEMA OLUSTURMAZ; sql/shopcore.sql'in kurmus oldugu tablolara
-- demo veri yucar. ONCE shopcore.sql calistirilmalidir (bu dosya tek basina
-- calistirilirsa "Table ... doesn't exist" hatasi alinir).
--
-- shopcore.sql sunucu/sema + 2 panel hesabi (admin id=1, editor id=2) kurar;
-- BU dosya yalniz magaza verisini (kategori/urun/uye/siparis) ekler.
-- yetkililer tablosuna DOKUNMAZ.
--
-- Calistirma:
--   C:\xampp\mysql\bin\mysql.exe -u root --default-character-set=utf8mb4 < sql\ornek-veri.sql
--   (veya phpMyAdmin → ShopCore DB → SQL sekmesi → yapıştır → Git)
--
-- ICERIK:
--   * 5 kategori, 15 urun (urunler)
--   * 5 site uyesi      (kullanicilar)  → hepsinin parolasi "123456"
--   * 3 ornek siparis   (siparisler + siparis_kalemleri)
--
-- UYARI:
--   * Tablolari DELETE ile temizler, sonra demo veriyi yeniden yazar.
--     yetkililer (admin/editor) korunur; onlara DOKUNULMAZ.
--   * YAYIN ORTAMINDA ASLA CALISTIRMA — mevcut magaza verisini siler.
--   * Urun resimleri 'default.png' olarak isaretlidir; gorsel dosyalari
--     repoda yoktur, fonksiyonlar.php :: resim_yolu() varsayilana duser.
--   * Demo kullanicilar bilerek AYNI bcrypt hash'i paylasir (kolaylik);
--     gercek kayit akisinda password_hash() benzersiz salt uretir.
-- ---------------------------------------------------------------------------

USE `ShopCore`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- --- Temizle (child → parent sirasiyla; yetkililer ATLANIR) ---
DELETE FROM `siparis_kalemleri`;
DELETE FROM `siparisler`;
DELETE FROM `urun_resimleri`;
DELETE FROM `urunler`;
DELETE FROM `kullanicilar`;
DELETE FROM `kategoriler`;

ALTER TABLE `kategoriler`       AUTO_INCREMENT = 1;
ALTER TABLE `urunler`           AUTO_INCREMENT = 1;
ALTER TABLE `urun_resimleri`    AUTO_INCREMENT = 1;
ALTER TABLE `kullanicilar`      AUTO_INCREMENT = 1;
ALTER TABLE `siparisler`        AUTO_INCREMENT = 1;
ALTER TABLE `siparis_kalemleri` AUTO_INCREMENT = 1;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------------
-- KATEGORILER (5 adet)
-- ---------------------------------------------------------------------------
-- aciklama kolonu CKEditor HTML'i kabul eder; temiz_html() allow-list'i
-- p/strong/em/ul/li/h2-h4/blokquote gibi temel etiketleri korur.
-- kategoriadi DUZ METIN tutulur; HTML entity (&amp; gibi) KULLANILMAZ cunku
-- ciktilama esc()/htmlspecialchars() ile ikinci kez kodlanir ve ekranda
-- "&amp;" gorunur. Gerekirse "&" karakteri oldugu gibi birakilir.

INSERT INTO `kategoriler` (`id`, `kategoriadi`, `aciklama`) VALUES
(1, 'Elektronik',        '<p>Telefon, kulaklik, klavye, mouse ve dijital aksesuarlar. <strong>2 yil</strong> servis garantisiyle.</p>'),
(2, 'Ev & Yasam',        '<p>Mutfak gerecleri, kucik ev aletleri ve dekorasyon urunleri.</p>'),
(3, 'Giyim',             '<p>Kadin, erkek ve cocuk icin <em>gunluk</em> giyim secenekleri. Mevsim normalleri bedenler stoklardadir.</p>'),
(4, 'Kitap & Kirtasiye', '<p>Roman, hikaye, defter, ajanda ve ofis malzemeleri.</p>'),
(5, 'Spor & Outdoor',    '<p>Fitness ekipmanlari, yoga matlari, acikhava malzemeleri.</p>');

-- ---------------------------------------------------------------------------
-- URUNLER (15 adet — 5 Elektronik / 3 Ev & Yasam / 3 Giyim / 2 Kitap / 2 Spor)
-- ---------------------------------------------------------------------------
-- Fiyatlar gercekci TL degerleri; stok INT UNSIGNED (0 = tukendi ornegi id=5).
-- resim='default.png' — kod, dosya bulunamayinca varsayilana duser.
-- giris_tarihi DATETIME; urun listesinde IX giris_tarihi ile siralama icin.

INSERT INTO `urunler` (`id`, `urunadi`, `aciklama`, `fiyat`, `stok`, `giris_tarihi`, `resim`, `kategori_id`) VALUES
-- === Elektronik (5) ===
(1,  'Bluetooth Kablosuz Kulaklik Siyah',
     '<p>Aktivite gurbultu engelleme (ANC) teknolojisine sahip <strong>kulak ustu</strong> kablosuz kulaklik. 30 saat pil omru, hizli sarj destegi.</p><ul><li>Bluetooth 5.3</li><li>30 saat pil omru</li><li>Katlanan tasarim + tasima cantasi</li></ul>',
     1299.90, 42, '2026-08-15 10:30:00', 'default.png', 1),
(2,  'Ergonomik Wireless Mouse',
     '<p>El anatomisine uygun <em>kavisli</em> tasarim, sessiz tukalama ve 6 programlanabilir buton. 2.4 GHz alici ile 10 m menzil.</p>',
     449.90, 128, '2026-08-20 14:15:00', 'default.png', 1),
(3,  'Mekanik Klavye RGB TR-Q',
     '<p>Hot-swap switch destekli, TR-Q dizilimli <strong>mekanik klavye</strong>. Her tus icin ayri RGB isiklandirma, makro profilleri.</p>',
     1899.00, 15, '2026-09-01 09:00:00', 'default.png', 1),
(4,  '20000 mAh Powerbank Hizli Sarj',
     '<p>PD 22.5W ve QC 3.0 cikis destekli tasinabilir sarj istasyonu. USB-C + 2x USB-A port.</p>',
     799.50, 67, '2026-09-05 11:45:00', 'default.png', 1),
(5,  'Full HD 1080p Web Kamerasi',
     '<p>Otomatik fokus, cift Stereo mikrofona sahip <strong>canli gorus</strong> kamerasi. Tripod sabitleme, Windows/Mac/Chromebook uyumlu.</p>',
     649.90, 0, '2026-09-10 16:20:00', 'default.png', 1),

-- === Ev & Yasam (3) ===
(6,  'Celik Caydanlik Takimi 6+1',
     '<p>Indüksiyon dahil tum ocaklara uyumlu <em>18/10 celik</em> caydanlik. Demlik + 5 parca corba tenceresi hediye.</p>',
     1250.00, 24, '2026-08-25 13:00:00', 'default.png', 2),
(7,  'Granit Tava Seti 5 Parca',
     '<p>Yapismayan granit kaplama, isiya dayanikli bakalit sap. 20/22/24/26 cm tava + 24 cm derin tencere.</p>',
     2899.90, 8, '2026-09-03 10:10:00', 'default.png', 2),
(8,  'LED Masa Lambasi Dokunmatik',
     '<p>Uc kademeli isi ayari (2700K/4000K/6500K), kisayol parlaklik, USB sarj portu.</p>',
     399.00, 55, '2026-09-12 18:00:00', 'default.png', 2),

-- === Giyim (3) ===
(9,  '%100 Pamuk Basic Tisort Beyaz',
     '<p>Yumuşak dokumali, <strong>nefes alan</strong> %100 organik pamuk tisort. S-M-L-XL bedenleri mevcuttur.</p>',
     179.90, 200, '2026-08-01 12:00:00', 'default.png', 3),
(10, 'Su Itici Outdoor Mont Antrasit',
     '<p>Ruzgar ve su gecirmeyen 3 katmanli kumaş, ic file astar, ayarlanabilir sap. -10°C''ye kadar konfor.</p>',
     2199.00, 12, '2026-09-08 08:30:00', 'default.png', 3),
(11, 'Jogging Esofman Alti Siyah',
     '<p>Spor ve gunluk kullanim icin elastik bel baglantili, iki cepli <em>unisex</em> eşofman alti.</p>',
     549.50, 48, '2026-09-14 19:45:00', 'default.png', 3),

-- === Kitap & Kirtasiye (2) ===
(12, 'Roman - Kürk Mantolu Madonna',
     '<p>Sabahattin Ali''nin klasik eseri. 240 sayfa, kuşe kağıt, ciltsiz baskı.</p>',
     89.90, 35, '2026-08-05 15:00:00', 'default.png', 4),
(13, '200 Sayfa Cizgili Defter Kraft',
     '<p>Doğa dostu kraft kapak, 80 gr/m² asitsiz kağıt, saglam dikis ciltleme.</p>',
     45.00, 300, '2026-08-18 09:00:00', 'default.png', 4),

-- === Spor & Outdoor (2) ===
(14, 'Yoga Mati Kaymaz 6mm Mor',
     '<p>TPE malzemeden uretilen, cift yuzu kaymaz yoga/pilates matı. 183x61 cm, 6mm kalınlık.</p>',
     349.90, 26, '2026-09-02 17:20:00', 'default.png', 5),
(15, 'Ayarlanabilir Dambıl Seti 2x10kg',
     '<p>Krom kaplama, 2-10 kg arası <strong>6 kademeli</strong> ayar. Kol ve omuz antrenmanları için ideal.</p>',
     1499.00, 7, '2026-09-11 07:00:00', 'default.png', 5);

-- ---------------------------------------------------------------------------
-- KULLANICILAR (site üyesi — 5 adet)
-- ---------------------------------------------------------------------------
-- Oturum anahtari $_SESSION['kullanici_id']; panele erisimleri YOK.
-- Telefon formati serbest; turkish standart: 05xx xxx xx xx.
-- Posta kodlari gercek 5 haneli TR kodlari.

INSERT INTO `kullanicilar`
    (`id`, `adsoyad`, `eposta`, `telefon`, `il`, `ilce`, `adres`, `posta_kodu`, `sifre`) VALUES
(1, 'Ahmet Yılmaz',  'ahmet.yilmaz@example.com',  '0532 111 22 33', 'İstanbul', 'Kadıköy',     'Moda Caddesi No:14 D:3, Caferağa Mah.',       '34710', '$2y$10$AhIF4HICuyuYOpYClYYeDegTmcu1SGIwiLB14tVSCifD43lyspf8q'),
(2, 'Ayşe Demir',    'ayse.demir@example.com',    '0533 444 55 66', 'Ankara',   'Çankaya',     'Kızılırmak Mah. 1456. Sokak No:8 D:12',       '06510', '$2y$10$AhIF4HICuyuYOpYClYYeDegTmcu1SGIwiLB14tVSCifD43lyspf8q'),
(3, 'Mehmet Kaya',   'mehmet.kaya@example.com',   '0542 777 88 99', 'İzmir',    'Bornova',     'Erzene Mah. Fak Baykurt Cad. No:22',           '35040', '$2y$10$AhIF4HICuyuYOpYClYYeDegTmcu1SGIwiLB14tVSCifD43lyspf8q'),
(4, 'Zeynep Şahin',  'zeynep.sahin@example.com',  '0505 121 31 41', 'Bursa',    'Nilüfer',     'Büyükbalıklı Mah. Konak Sok. No:5',            '16150', '$2y$10$AhIF4HICuyuYOpYClYYeDegTmcu1SGIwiLB14tVSCifD43lyspf8q'),
(5, 'Mustafa Çelik', 'mustafa.celik@example.com', '0502 515 15 15', 'Antalya',  'Muratpaşa',   'Fener Mah. Tekelioğlu Cad. No:36 D:7',         '07040', '$2y$10$AhIF4HICuyuYOpYClYYeDegTmcu1SGIwiLB14tVSCifD43lyspf8q');

-- ---------------------------------------------------------------------------
-- SIPARISLER (3 ornek — farkli durum + odeme tipinde)
-- ---------------------------------------------------------------------------
-- numarasi CHAR(16): SC + YYMMDDHHMMSS + 2 random (siparis_numarasi() formati).
-- ara_toplam/kargo/toplam SIPARIS ANINDAKI sunucu kopyalari; KARGO_ESIGI=1000
-- varsayildi: 1000 TL ve uzeri kargo=0; altinda 99.99.
-- Siparis adsoyad/eposta/adres, kullanici tablosundan BAGIMSIZ donmus kopya.

INSERT INTO `siparisler`
    (`id`, `numarasi`, `kullanici_id`, `adsoyad`, `eposta`, `telefon`, `il`, `ilce`,
     `adres`, `posta_kodu`, `odeme_tipi`, `ara_toplam`, `kargo`, `toplam`, `durum`, `olusturma_tarihi`) VALUES
-- Siparis 1: Ahmet — Kulaklik 1299.90 x 1 + Mouse 449.90 x 1 = ara 1749.80 -> kargo 0 (esikustu)
(1, 'SC26100112000042', 1, 'Ahmet Yılmaz', 'ahmet.yilmaz@example.com', '0532 111 22 33',
    'İstanbul', 'Kadıköy', 'Moda Caddesi No:14 D:3, Caferağa Mah.', '34710',
    'kart', 1749.80, 0.00, 1749.80, 'kargoda', '2026-10-01 12:00:00'),

-- Siparis 2: Ayşe — Tisort 179.90 x 3 + Defter 45.00 x 2 + Yoga 349.90 x 1
-- = ara 539.70 + 90.00 + 349.90 = 979.60 -> kargo 99.99 -> toplam 1079.59 (esik altinda)
(2, 'SC26100514300017', 2, 'Ayşe Demir', 'ayse.demir@example.com', '0533 444 55 66',
    'Ankara', 'Çankaya', 'Kızılırmak Mah. 1456. Sokak No:8 D:12', '06510',
    'havale', 979.60, 99.99, 1079.59, 'kapandi', '2026-10-05 14:30:00'),

-- Siparis 3: Mehmet — Klavye 1899.00 x 1 + Caydanlik 1250.00 x 1 = ara 3149.00 -> kargo 0
(3, 'SC26101018300088', 3, 'Mehmet Kaya', 'mehmet.kaya@example.com', '0542 777 88 99',
    'İzmir', 'Bornova', 'Erzene Mah. Fak Baykurt Cad. No:22', '35040',
    'kapida', 3149.00, 0.00, 3149.00, 'alindi', '2026-10-10 18:30:00');

-- ---------------------------------------------------------------------------
-- SIPARIS_KALEMLERI (7 satir — urun_adi + birim_fiyat donem kaydi kopyasidir)
-- ---------------------------------------------------------------------------
-- FK urun_id ON DELETE SET NULL: urun silinse bile kalem okunabilir kalir.
-- satir_toplam = birim_fiyat * adet (sunucu kurali; buraya dogrudan yaziyoruz).

INSERT INTO `siparis_kalemleri`
    (`id`, `siparis_id`, `urun_id`, `urun_adi`, `birim_fiyat`, `adet`, `satir_toplam`) VALUES
-- Siparis 1 (Ahmet, SC26100112000042)
(1, 1, 1, 'Bluetooth Kablosuz Kulaklik Siyah', 1299.90, 1, 1299.90),
(2, 1, 2, 'Ergonomik Wireless Mouse',             449.90, 1,  449.90),

-- Siparis 2 (Ayşe, SC26100514300017)
(3, 2,  9, '%100 Pamuk Basic Tisort Beyaz',        179.90, 3,  539.70),
(4, 2, 13, '200 Sayfa Cizgili Defter Kraft',        45.00, 2,   90.00),
(5, 2, 14, 'Yoga Mati Kaymaz 6mm Mor',             349.90, 1,  349.90),

-- Siparis 3 (Mehmet, SC26101018300088)
(6, 3,  3, 'Mekanik Klavye RGB TR-Q',             1899.00, 1, 1899.00),
(7, 3,  6, 'Celik Caydanlik Takimi 6+1',          1250.00, 1, 1250.00);

-- ---------------------------------------------------------------------------
-- GIRIS BILGILERI (demo — yayinda degistir!)
-- ---------------------------------------------------------------------------
-- Panel hesaplari admin/editor admin/login.php — shopcore.sql tohumundan gelir:
--   kadi: admin   | sifre: 123456   (id=1 — silme korumasi aktif)
--   kadi: editor  | sifre: 123456   (id=2 — silinebilir)
--
-- Site uye girisi (giris.php) — bu dosyadan gelir:
--   ahmet.yilmaz@example.com  / 123456
--   ayse.demir@example.com    / 123456
--   mehmet.kaya@example.com   / 123456
--   zeynep.sahin@example.com  / 123456
--   mustafa.celik@example.com / 123456
-- ---------------------------------------------------------------------------
