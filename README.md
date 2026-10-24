# 🛍️ ShopCore

> Türkçe, XAMPP hedefli, **sıfır framework** e-ticaret çekirdeği. PHP + PDO + Bootstrap 5.3 üzerine kurulu; Composer, npm, build adımı ve CDN yok — klasörü `htdocs`'e koy, `sql/shopcore.sql`'i import et, çalıştır.

---

## 🧰 Gereksinimler

| Zorunlu | İsteğe bağlı |
|---|---|
| 🐘 PHP **8.0+** (PDO + `pdo_mysql` eklentisi ile) | 🖼️ `gd` eklentisi → yüklenen görsellerde `.webp` varyant üretimi ve yeniden boyutlama |
| 🗄️ MySQL **5.7+** / MariaDB **10.4+** | ⚙️ `opcache` → dosya bazlı PHP'de performans kazanımı |
| 🌐 Apache **2.4+** (XAMPP bu üçünü tek pakette sunar) — IIS de desteklenir (web.config kabukları hazır) | 📦 HTTP/2 + HTTPS → `session.cookie_secure` otomatik devreye girer |

> 💡 **Not:** Tüm harici asset'ler (`Bootstrap`, `SweetAlert2`, `CKEditor 5` + Türkçe çevirisi, `Quicksand` font) `content/vendor/` altında **yerel kopya** olarak durur — çevrimdışı da çalışır. CDN bağımlılığı **yoktur**.

---

## ✨ Öne çıkanlar

### 🛒 Müşteri tarafı

- 🧭 **Ürün listesi** — kategori filtreleme, serbest metin araması, çok kriterli sıralama, sayfalama
- 🖼️ **Ürün detayı** — galeri, stok göstergesi, sepete ekle (AJAX)
- 🧺 **Sepet** — oturum bazlı, adet güncelleme, kargo eşiği kontrolü, server-side özet tek kaynak (`sepet_ozeti`)
- 👤 **Üyelik** — kayıt, giriş, hesabım (profil/adres), siparişlerim
- 💳 **Ödeme akışı** — havale / kart / kapıda; sunucu tarafı toplam hesabı + tek-kullanımlık sipariş token'ı (idempotency) + `SELECT … FOR UPDATE` stok kilidi
- ✉️ **İletişim formu** — SMTP header injection'a kapalı, `mail()` sonucu dürüst raporlanır
- 🏳️ **Hakkımızda** — statik bilgi sayfası
- ☀️🌙 **Tema** — açık/koyu iki mod (varsayılan açık, sistem "auto" yok), FOUC'suz inline boot script
- 📱 **Responsive** — 320 px – geniş ekran; Bootstrap 5 grid + yerel CSS katmanları

### 🎛️ Yönetim paneli (`/admin`)

- 🔐 **Giriş throttling** — aynı oturumda 5 hatalı denemede HTTP 429 + `sleep(3)`, eski düz-metin kayıtlar için lazy bcrypt upgrade
- 📦 **Ürün CRUD** — çoklu silme, galeri (çoklu resim yükleme), stok, kategori bağlama, transaction + dosya geri-alma
- 🏷️ **Kategori CRUD** — kategori altındaki ürünlerle birlikte yönetim, `urun_var` koruması
- 👮 **Yetkili CRUD** — admin hesapları; **id=1 ana yönetici silinemez**, şifre **min 8 karakter**, bcrypt hash'lenir
- 👥 **Site üyesi CRUD** — kullanıcı silindiğinde siparişleri `ON DELETE SET NULL` ile korunur (tarihsel fiş bozulmaz)
- 🧾 **Sipariş yönetimi** — liste + hızlı durum değiştirme (POST + CSRF + PRG); detay sayfası salt okunur
- 📊 **Dashboard** — özet kartlar (bekleyen sipariş, ciro, kullanıcı sayısı, toplam sipariş)
- 🍦 **SweetAlert2** — onay diyalogları, arama + filtre + sayfalama, satır-aksiyon ikon-butonları

---

## 🧱 Teknoloji yığını

| Katman | Seçim | Not |
|---|---|---|
| 🐘 Dil | PHP **8.x** | XAMPP 8.2.12 ile test edildi; PHP 8.5 uyumlu (PDO init-komut sabiti çalışma zamanında seçilir) |
| 🗄️ Veritabanı | MySQL / MariaDB (InnoDB) | `utf8mb4` / `utf8mb4_turkish_ci` |
| 🔌 Veri erişimi | PDO + prepared statement | `ERRMODE_EXCEPTION`, `ATTR_EMULATE_PREPARES = false`, singleton `db()` |
| 🎨 UI çekirdeği | Bootstrap **5.3.2** | yerel kopya: `content/vendor/bootstrap/` |
| 💬 Diyaloqlar | SweetAlert2 | yerel kopya: `content/vendor/sweetalert2/` |
| ✒️ İkonlar | Yerel **Lucide SVG sprite** | `includes/ikonlar.php` — harici ikon fontu **yok** |
| 📝 Zengin metin | CKEditor 5 classic build | yerel kopya: `content/vendor/ckeditor/` — yalnız **4 form** sayfasında yüklenir |
| 🔤 Yazı tipi | Quicksand | `content/vendor/fonts/` |
| 🏗️ Build | **yok** | `.editorconfig` dışında derleme/paketleme adımı yok |
| 📦 Paket yöneticisi | **yok** | Composer/PHPStan kasıtlı olarak çıkarıldı; her şey repoda |

---

## 🚀 Kurulum (XAMPP, Windows)

**1️⃣** Projeyi `C:\xampp\htdocs\ShopCore` altına kopyala.

**2️⃣** XAMPP Control Panel'de Apache + MySQL'i başlat, veritabanını kur:

```
C:\xampp\mysql\bin\mysql.exe -u root --default-character-set=utf8mb4 < sql\shopcore.sql
```

(Alternatif: phpMyAdmin → Import → `sql/shopcore.sql`.) Tek dosya `shopcore` veritabanını + 7 tabloyu + tohum admin'i (id=1) kurar. İsteğe bağlı demo veri için ayrıca `sql/ornek-veri.sql` çalıştırılabilir.

**3️⃣** `config/yerel.orneke.php` → `config/yerel.php` olarak kopyala, DB bilgilerini gir (XAMPP'te `DB_SIFRE` boş kalabilir).

**4️⃣** Panele gir → <http://localhost/ShopCore/admin/login.php>

> 💡 IIS: klasörü site kökü yap, yeter — Apache dışı sunucuda `.htaccess` işlemez, repodaki `web.config` kabukları aynı korumaları sağlar.

| 👤 Kullanıcı | 🔑 Şifre |
|---|---|
| `admin` | `123456` |

> ⚠️ İlk girişten sonra şifreyi değiştir (*Yetkili → Düzenle*) ve `sql/shopcore.sql`'deki tohum INSERT satırındaki hash'i güncelle ya da satırı sil.

---

## 🛡️ Korumalı id=1 ana yönetici

- ⛔ `admin/yetkili/sil.php` → `$id === 1` hard-block; yanıt `korunuyor`, kayıt **hiçbir koşulda** silinmez.
- 🛡️ `admin/yetkili/liste.php` → Sil butonu yerine pasif shield-check ikonu.
- ✏️ `duzelt.php` id=1 için serbest (ad/kullanıcı adı/şifre güncellenebilir).
- 🚪 **Kendini-silme** koruması da aktif — oturum sahibi kendi satırını silemez.

> phpMyAdmin gibi panel-dışı silmeler mümkün. Kurtarma: `sql/shopcore.sql`'i baştan import ya da sadece id=1 INSERT satırını elle çalıştır.

---

## 📁 Proje haritası

```
ShopCore/
├── index.php                    🏠 Ana sayfa (banner, öne çıkan ürünler)
├── urunler.php                  🧭 Ürün listesi + arama + filtre
├── urundetay.php                🖼️ Ürün detayı + galeri
├── sepetim.php                  🧺 Sepet görünümü
├── sepete_ekle.php              ➕ Sepete ekleme endpoint'i (POST + CSRF)
├── sepeti_guncelle.php          🔁 Adet güncelleme / sepetten çıkarma (POST + CSRF)
├── satinal.php                  💳 Ödeme formu
├── siparis_onay.php             ✅ Sipariş kaydı (transaction + stok düşme + idempotency)
├── giris.php                    🔑 Site üyesi girişi
├── kayit.php                    📝 Site üyesi kaydı
├── cikis.php                    🚪 Oturum kapatma (POST + CSRF)
├── hesabim.php                  👤 Profil / adres güncelleme
├── siparislerim.php             📜 Kullanıcının sipariş geçmişi
├── hakkimizda.php               ℹ️ Statik bilgi sayfası
├── eposta.php                   ✉️ İletişim formu mail endpoint'i
├── header.php, footer.php       🎩 Ortak public kabuk
│
├── admin/
│   ├── index.php                📊 Dashboard (özet kartlar)
│   ├── login.php                🔐 Yönetici girişi (throttling + lazy bcrypt upgrade)
│   ├── header.php, footer.php   🧩 Ortak admin kabuk (sidebar + topbar + CSRF meta)
│   ├── urun/                    📦 Ürün CRUD + coklusil + detay + galeri
│   ├── kategori/                🏷️ Kategori CRUD + kategori ürün listesi
│   ├── kullanici/               👥 Site üyesi CRUD + sipariş geçmişi
│   ├── yetkili/                 👮 Yönetici CRUD (id=1 korumalı, min 8 kr şifre)
│   └── siparis/                 🧾 Liste (hızlı durum) + Detay (salt okunur)
│
├── includes/
│   ├── bootstrap.php            🚪 Her sayfanın ilk include'i: env, sabitler, autoload
│   ├── db.php                   🔌 PDO singleton (`db()`)
│   ├── csrf.php                 🛡️ Token üret/doğrula (`csrf_alan`, `csrf_dogrula`, `csrf_yenile`)
│   ├── yetki.php                👮 `admin_zorunlu()`, `admin_id()`, `admin_cikis()`
│   ├── kullanici.php            👤 `kullanici_zorunlu()`, `kullanici_id()`, `kullanici_cikis()`
│   ├── fonksiyonlar.php         🧠 `esc()`, `temiz_html()`, `sepet_ozeti()`, `para()`, `resim_yolu()`, `siparis_numarasi()` …
│   ├── dosya_yukleme.php        🖼️ Görsel yükleme + `gd` ile `.webp` varyant üretimi
│   ├── sayfalama.php            📄 `admin_sayfalama()` helper
│   └── ikonlar.php              ✒️ Yerel Lucide SVG sprite (`#lk-*` sembol tanımları)
│
├── config/
│   ├── .htaccess                🔒 `Require all denied` — klasör HTTP'ye kapalı
│   ├── web.config               🔒 IIS karşılığı (requestFiltering)
│   ├── yerel.orneke.php         📋 Örnek şablon — kopyala → `yerel.php`
│   └── yerel.php                🤫 Gerçek değerler (SÜRÜM KONTROLÜNE GİRMEZ)
│
├── content/
│   ├── css/                     🎨 style.css (public), admin-style.css (panel)
│   ├── js/                      🧩 tema.js · admin.js · sepet.js · odeme-form.js ·
│   │                               urun-form.js · urun-detay.js ·
│   │                               admin-urun-detay.js · admin-siparis-liste.js
│   ├── images/                  📷 Yüklenen ürün görselleri + `default.png` + `.htaccess` + `web.config` (çalıştırma engeli)
│   └── vendor/                  📦 bootstrap/ · sweetalert2/ · ckeditor/ · fonts/
│
├── sql/
│   ├── .htaccess                🔒 Klasörü HTTP erişimine kapatır (+ `web.config` IIS karşılığı)
│   ├── shopcore.sql             🧱 Şema + tohum admin (id=1) — kurulumda bu import edilir
│   └── ornek-veri.sql           🧪 İsteğe bağlı demo veri (ürün/kategori/sipariş); kurulum zorunlu değil
│
├── .editorconfig                📐 Satır sonu, girinti, charset kuralları
├── .gitignore                   🤝 `config/yerel.php`, `content/images/*` (istisnalarla), `/vendor/`, `var/`, IDE & OS çöpü
├── .htaccess                    🌐 Apache yönlendirme + güvenlik başlıkları
├── web.config                   🌐 IIS karşılığı: requestFiltering + güvenlik başlıkları
└── README.md                    📖 Bu dosya
```

> 🚫 Bu projede **`var/` klasörü ve runtime cache katmanı YOK**. Header/footer menüsü her sayfa açılışında veritabanından doğrudan okunur. Ölçeklenme gerekirse ileride kendi cache-layer'unu ekleyebilirsin.

---

## 🗄️ Veri modeli

`sql/shopcore.sql` şemanın tek kaynağı · **7 tablo**:

| Tablo | Rolü |
|---|---|
| `kategoriler` | 🏷️ UNIQUE ad; ürün FK'i `ON DELETE RESTRICT` |
| `urunler` | 📦 fiyat DECIMAL(10,2), stok INT UNSIGNED |
| `urun_resimleri` | 🖼️ galeri; `ix_urun_sira` sıralı okuma |
| `yetkililer` | 👑 panel hesapları · id=1 korumalı · bcrypt · min 8 kr |
| `kullanicilar` | 👤 site üyesi · e-posta UNIQUE |
| `siparisler` | 🧾 numarasi CHAR(16) UNIQUE · durum ENUM |
| `siparis_kalemleri` | 🧮 dönem kaydı: ad + birim fiyat kopyası |

**🔀 İki kimlik yığını:** `yetkililer` (oturum: `$_SESSION['yetkili_id']` + `loginkey`) ile `kullanicilar` (`$_SESSION['kullanici_id']`) **ayrık**. Site üyesi, `yetkililer` satırı eklenmedikçe panele erişemez.

**📌 Dönem kaydı (snapshot):** Sipariş anında ürün adı/birim fiyatı/adres `siparis_kalemleri` ve `siparisler` kolonlarına **kopyalanır**. Ürün silinse (`SET NULL`) veya fiyatı değişse bile geçmiş fiş olduğu gibi okunur.

---

## 🔒 Mimari ve güvenlik

- 🚪 **Tek giriş:** her PHP dosyasının ilk satırı `require __DIR__.'/includes/bootstrap.php'` → `session_set_cookie_params` (HttpOnly, SameSite=Lax, Secure) header'ları çıktı başlamadan atılmaz.
- 🛡️ **CSRF + POST-only mutasyon:** silme/düzenleme/ekleme endpoint'leri yalnız POST + `csrf_dogrula()`. Token form alanı veya `X-CSRF-Token` header'ı; GET'ten asla okunmaz. Girişte `csrf_yenile()` → fixation koruması.
- 🚨 **Global exception handler** (`bootstrap.php`) + `db_hata_mesaji()`: yakalanmayan hata (ör. tablosuz DB) ham fatal/stack yerine düzgün uyarı sayfası olur; AJAX'a JSON; `APP_DEBUG=false` iken detay sızmaz. Sorgu hatası ile boş veri ayrılır (null vs []), footer her koşulda render olur.
- 💉 **SQLi:** tüm sorgular PDO prepared; `LIKE` için `like_deseni()` + `ESCAPE '!'`; `LIMIT/OFFSET` `PARAM_INT`.
- ⚠️ **XSS:** çıktıda `esc()` (htmlspecialchars ENT_QUOTES UTF-8); CKEditor HTML'i `temiz_html()` allow-list DOMDocument filtresinden geçer.
- 🔑 **Şifre:** `password_hash(..., BCRYPT)` + `password_verify()`; eski düz-metin için lazy upgrade.
- 🖼️ **Görsel yükleme:** `content/images/.htaccess` çalıştırmayı keser; MIME + uzantı beyaz liste; istemci dosya adı asla kullanılmaz; `gd` varsa `.webp` varyant.
- ✅ **Sipariş:** tek kullanımlık token ile idempotency + `SELECT … FOR UPDATE` stok kilidi + transaction / `rollBack()`.
- 🚦 **Throttling:** yönetici girişinde 5 hatada HTTP 429 + `sleep(3)`.
- 🔐 `sql/` · `config/` · `includes/` HTTP'ye kapalı: Apache'de `.htaccess Require all denied`, IIS'te karşılık gelen `web.config` (requestFiltering) ile. Kök: `.sql/.md/.log/.ini/.bak` reddi + güvenlik başlıkları; `content/images/`: script uzantıları (`.php`–`.phar`) kesilir.
- ↪️ `redirect()` yalnız site içi hedefe yönlendirir (açık redirect koruması: `guvenli_yonlendirme_hedefi()`).
- 🍪 Oturum adı `shopcore_sid`; giriş sonrası `session_regenerate_id(true)`.

---

## ⚙️ Ortam değişkenleri (`config/yerel.php`)

| Anahtar | Varsayılan | Not |
|---|---|---|
| `APP_DEBUG` | `false` | 🐞 yayında kapalı |
| `LOG_DOSYASI` | `null` | web kökü dışı |
| `DB_HOST` · `DB_ADI` · `DB_KULLANICI` · `DB_SIFRE` | `localhost` · `shopcore` · `root` · `''` | 🔌 |
| `KARGO_ESIGI` · `KARGO_UCRETI` | `1000` · `99.99` | 🚚 eşik ve üzeri ücretsiz |
| `DEFAULT_GORSEL` | `default.png` | 🖼️ |
| `RESIM_MAX_GENISLIK` · `RESIM_KART_GENISLIK` | `1200` · `400` | 📐 `.webp` varyant |
| `SITE_EPOSTA` · `SITE_TELEFON` · `SITE_FAX` · `SITE_ADRES` · `SITE_WEB` | `''` | Boşsa footer/iletişimde hiç gösterilmez; `SITE_EPOSTA` boşken form iletmez |

---

## 🧪 Geliştirme iş akışı

**Sözdizimi kontrolü:** her PHP değişikliğinden sonra `C:\xampp\php\php.exe -l path\to\dosya.php`.

**Şema değişikliği:** migration klasörü **yok** — `sql/shopcore.sql` hem şema hem tohum. Canlı DB'de: panelden CRUD akışı ya da dosyayı düzenleyip baştan import (**VERİ KAYBI**, yedek al).

**Geçici scriptler:** `_t18_*` / `z_temp_*` adıyla aç, işin sonunda **hemen sil**.

**🚀 Yayın öncesi kontrol listesi**

1. 🐞 `APP_DEBUG=false`, `LOG_DOSYASI` web kökü dışına.
2. 🔑 `DB_SIFRE` boş değil, güçlü bir değer.
3. 👑 id=1 ana yönetici şifresini `admin/yetkili/duzelt.php?id=1` ile değiştir (min 8 kr).
4. 🧱 `sql/shopcore.sql` içindeki tohum INSERT'i sil ya da kendi hash'inle değiştir.
5. 📂 `content/images/` yazma izni; `sql/` · `config/` · `includes/` korumalı (Apache: `.htaccess`, IIS: `web.config`).
6. ✉️ İletişim formu + tam sipariş akışını bir kez elle test et.
7. 🔐 HTTPS'i etkinleştir (`session.cookie_secure` otomatik devreye girer).

---

## 📜 Lisans

Bu proje **MIT Lisansı** altında lisanslanmıştır. Detaylar için [LICENSE](LICENSE) dosyasına bakabilirsiniz.
