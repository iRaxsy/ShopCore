<?php
/**
 * Ortak fonksiyonlar - tekrar eden kodlarin tek merkezi.
 */

/** HTML-escaped cikti (varsayilan: ENT_QUOTES, UTF-8). */
function esc($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

/**
 * Koyu tema altyapisi: CSS yuklenmeden ONCE calisan satir ici script.
 * Tercih yalnizca tarayicida (localStorage) saklanir. Varsayilan ACIKtir;
 * isletim sistemi tercihi okunmaz, "auto" modu yoktur. Kayitli deger
 * "dark" degilse (eski kayitlar dahil) acik tema uygulanir.
 * Bootstrap 5.3'ün yerli data-bs-theme mekanizmasi kullanildigi icin ek CSS dosyasi gerekmez.
 * Ciktisi sabit bir metin oldugu icin esc() ile sarilmaz.
 */
function tema_head(): string
{
    return '<meta name="theme-color" content="#198754" data-tema-meta>' . "\n    "
         . '<script>(function(){var t="light";'
         . 'try{if(localStorage.getItem("shopcore_tema")==="dark"){t="dark";}}catch(e){}'
         . 'var e=document.documentElement;'
         . 'e.setAttribute("data-bs-theme",t);'
         . 'e.setAttribute("data-tema-secim",t);})();</script>';
}

/**
 * Urun gorselinin diskteki TABAN adi. Dosya yoksa (veya hic secilmemisse)
 * varsayilan gorsele duser. resim_yolu()/resim_boyut() ayni cozumlemeyi
 * paylasmali ki <img> adresi ile width/height birbirini tutsun.
 */
function resim_ad($ad): string
{
    $ad = $ad ? basename($ad) : '';
    if ($ad === '' || !is_file(APP_ROOT . '/content/images/' . $ad)) {
        $ad = APP_DEFAULT_GORSEL;
    }
    return $ad;
}

/**
 * Istenen genislik icin varyant ad: "{taban}-{genislik}.webp".
 * Varyant YOKSA orijinal dosya doner - boylece eski kayitlar (gd kapaliyken
 * yuklenmis, islenmemis gorseller) oldugu gibi calismaya devam eder.
 */
function resim_varyant_ad($ad, $genislik = null): string
{
    $taban = resim_ad($ad);
    $genislik = (int) $genislik;
    if ($genislik <= 0) {
        return $taban;
    }
    $sansli = preg_replace('/\.[a-z0-9]+$/i', '', $taban) . '-' . $genislik . '.webp';
    return is_file(APP_ROOT . '/content/images/' . $sansli) ? $sansli : $taban;
}

/**
 * Urun gorselinin WEB yolu. Dosya yoksa default.png doner.
 * APP_BASE_URL ile uretilir; her klasor derinliginden (kok/admin) calisir.
 * @param int|null $genislik 400 gibi bir deger verilirse o boyutun webp
 *                           varyanti (varsa) kullanilir.
 */
function resim_yolu($ad, $genislik = null): string
{
    return APP_BASE_URL . 'content/images/' . rawurlencode(resim_varyant_ad($ad, $genislik));
}

/**
 * <img> icin gercek piksel boyutu nitelikleri: ' width="400" height="300"'.
 * Amac CLS'i onlemek (gorsel yuklenirken yer acilmaya kalmasin). Degerler
 * dosyanin KENDISINDEN okundugu icin mevcut gorunumu degistirmez; dosya
 * okunamiyorsa bos string doner ve niteliksiz <img> eskisi gibi calisir.
 * getimagesize() gd gerektirmez; sonuc istek boyunca tekrar kullanilir.
 */
function resim_boyut($ad, $genislik = null): string
{
    static $olcu = [];
    $dosya = resim_varyant_ad($ad, $genislik);
    if (!isset($olcu[$dosya])) {
        $bilgi = @getimagesize(APP_ROOT . '/content/images/' . $dosya);
        $olcu[$dosya] = $bilgi
            ? ' width="' . (int) $bilgi[0] . '" height="' . (int) $bilgi[1] . '"'
            : '';
    }
    return $olcu[$dosya];
}

/** Urun gorselinin dosya sistemi yolu (unlink/file islemleri icin). */
function resim_dosya($ad): string
{
    return APP_ROOT . '/content/images/' . basename((string)$ad);
}

/**
 * Bir gorsele ait TUM dosyalar: orijinal + uretilen boyut varyantlari
 * ("{taban}-{genislik}.webp"). Silme ve geri alma kodlari bunu kullanmali,
 * yoksa urun kalkarken varyant dosyalari diskte yetim kalir.
 * @return string[] mutlak yollar (yalnizca gercekten var olanlar)
 */
function resim_dosyalari($ad): array
{
    $ad = basename((string) $ad);
    if ($ad === '') {
        return [];
    }
    $klasor = APP_ROOT . '/content/images/';
    $taban  = preg_replace('/\.[a-z0-9]+$/i', '', $ad);
    $liste  = [$klasor . $ad];
    foreach (glob($klasor . $taban . '-*.webp') ?: [] as $varyant) {
        $liste[] = $varyant;
    }
    return array_values(array_filter($liste, 'is_file'));
}

/** Para bicimi: 1234.5 -> "1.234,50 TL" (mevcut &#8378; simgesi yerine 'TL' + sembol). */
function para($v): string
{
    return number_format((float)$v, 2, ',', '.');
}

/** Kargo ucretsiz esiginin gorunum degeri: 1000 -> "1.000" */
function kargo_esigi(): string
{
    return number_format(KARGO_ESIGI, 0, ',', '.');
}

/** Kargo kurali - tek yer: esik ve ucret bootstrap'taki sabitlerden gelir. */
function kargo_tutari($ara_toplam): float
{
    return ((float)$ara_toplam >= KARGO_ESIGI) ? 0.0 : (float)KARGO_UCRETI;
}

/**
 * Iletisim bilgisi tek okuma noktasi (degerler config/yerel.php'den gelir).
 * Alan tanimsiz veya bos ise bos metin doner; cagri tarafinda hicbir sey
 * basilmaz (bir zamanlar kaynakta duran "x", "(0000) 000 00 00" gibi
 * tutucu degerler artik yayinlanmiyor).
 */
function site_bilgisi($alan): string
{
    $bilgiler = [
        'eposta'  => SITE_EPOSTA,
        'telefon' => SITE_TELEFON,
        'fax'     => SITE_FAX,
        'adres'   => SITE_ADRES,
        'web'     => SITE_WEB,
    ];

    return trim((string)($bilgiler[$alan] ?? ''));
}

/**
 * Sepetin sunucu tarafindaki TEK ozeti (rozet, tablo ve odeme sayfasi ayni
 * degerleri gorur). Session'daki id'ler tek sorguda DB'den dogrulanir; boylece
 * silinmis bir urun toplamlara ve rozete katilmaz. Fiyat/adet daima sunucudan
 * okunur, istemci degerine guvenilmez.
 *
 * Ayni istekte tekrar cagrildiginda sorgu yinelenmez (static ozet).
 *
 * @return array{kalemler: array, urun_sayisi: int, adet_toplam: int, ara_toplam: float, kargo: float, toplam: float}
 */
function sepet_ozeti($sepet = null): array
{
    // Fonksiyon icinden superglobale erisim: $_SESSION yerine $GLOBALS okunur
    $sepet = is_array($sepet) ? $sepet : (array)($GLOBALS['_SESSION']['sepet'] ?? []);

    // Adetleri gecerli araliga cek (form min=1 max=99 ile ayni kural)
    $adetler = [];
    foreach ($sepet as $id => $kalem) {
        $adet = (int)($kalem['adet'] ?? 1);
        if ($adet < 1) {
            $adet = 1;
        } elseif ($adet > 99) {
            $adet = 99;
        }
        $adetler[(int)$id] = $adet;
    }
    ksort($adetler);

    static $ozetler = [];
    $anahtar = serialize($adetler);
    if (isset($ozetler[$anahtar])) {
        return $ozetler[$anahtar];
    }

    $ozet = [
        'kalemler'    => [],
        'urun_sayisi' => 0,
        'adet_toplam' => 0,
        'ara_toplam'  => 0.0,
        'kargo'       => 0.0,
        'toplam'      => 0.0,
    ];

    if ($adetler) {
        $yer_tutucu = implode(',', array_fill(0, count($adetler), '?'));
        $stmt = db()->prepare("SELECT id, urunadi, fiyat, resim FROM urunler WHERE id IN ($yer_tutucu) ORDER BY urunadi");
        $stmt->execute(array_keys($adetler));

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $urun) {
            $id    = (int)$urun['id'];
            $adet  = $adetler[$id];
            $birim = (float)$urun['fiyat'];

            $ozet['kalemler'][] = [
                'id'           => $id,
                'urunadi'      => $urun['urunadi'],
                'resim'        => $urun['resim'],
                'birim_fiyat'  => $birim,
                'adet'         => $adet,
                'satir_toplam' => $birim * $adet,
            ];
            $ozet['adet_toplam'] += $adet;
            $ozet['ara_toplam']  += $birim * $adet;
        }

        $ozet['urun_sayisi'] = count($ozet['kalemler']);

        if ($ozet['kalemler']) {
            $ozet['kargo']  = kargo_tutari($ozet['ara_toplam']);
            $ozet['toplam'] = $ozet['ara_toplam'] + $ozet['kargo'];
        }
    }

    return $ozetler[$anahtar] = $ozet;
}

/**
 * CKEditor 5 (classic build) betigi - yalnizca 4 form sayfasinda cagrilir.
 * ~1.3 MB'lik bir varlik oldugu icin liste/detay sayfalarina asla konmaz.
 * Yerel kopya: content/vendor/ckeditor/ckeditor.js (Bootstrap/Swal ile ayni
 * kalip; CDN bagimligi yok, cevrimdisi calisir). translations/tr.js,
 * ckeditor.js'in kardestesi olarak tarayici tarafinda otomatik cozumlenir.
 * defer: sayfadaki baslatici kod DOMContentLoaded icinde kaldigi surece
 * `ClassicEditor` globali handler calismadan once hazir olur.
 */
function ckeditor(): string
{
    return varlik_js(APP_VARLIK_CKEDITOR_JS);
}

/** UTF-8 güvenli metin kisa ozet (substr yerine mb_substr). Zengin icerikteki etiketleri kirpar. */
function ozetle($metin, $n = 100): string
{
    $metin = trim(strip_tags((string)$metin));
    if (mb_strlen($metin, 'UTF-8') <= $n) {
        return $metin;
    }
    return mb_substr($metin, 0, $n, 'UTF-8') . '...';
}

/**
 * Sorgu parametreli guvenli URL uretir (urlencode + esc).
 * url('urunler.php', ['sayfa'=>2, 'aranan'=>$q])
 */
function url($yol, array $params = []): string
{
    if ($params) {
        $query = http_build_query($params, '', '&');
        $yol .= (strpos($yol, '?') === false ? '?' : '&') . $query;
    }
    return esc($yol);
}

/**
 * Varlik parmak izi: dosyanin filemtime degeri ("bayat CSS" sorununu bitirir).
 * Dosya degistiginde sorgu adresi de degisir; boylece uzun sureli
 * Cache-Control basligi (M3.2) guvenli olur - tarayici/esnek proxy eski
 * kopyayi gosteremez. Ayni istekte tekrar tekrar stat okunmaz.
 */
function varlik_suru($goreli_yol): string
{
    static $parmak_izi = [];

    $yol = ltrim(str_replace('\\', '/', (string) $goreli_yol), '/');
    if (!isset($parmak_izi[$yol])) {
        $dosya = APP_ROOT . '/' . $yol;
        $parmak_izi[$yol] = is_file($dosya) ? (string) filemtime($dosya) : '0';
    }

    return $parmak_izi[$yol];
}

/** Yerel CSS varligi: parmak izli <link> etiketi. */
function varlik_css($goreli_yol): string
{
    $yol = ltrim(str_replace('\\', '/', (string) $goreli_yol), '/');
    return '<link rel="stylesheet" href="' . esc(APP_BASE_URL . $yol) . '?v=' . varlik_suru($yol) . '">';
}

/**
 * Yerel JS varligi: parmak izli <script defer> etiketi.
 * defer parser'i bekletmez, calisma sirasi etiket sirasiyla ayni kalir ve
 * tum betikler DOMContentLoaded'dan ONCE biter - sayfa icindeki baslatici
 * kodlar bu siralamaya guvenir (jQuery kaldirildiktan sonra tek gereksinim).
 */
function varlik_js($goreli_yol): string
{
    $yol = ltrim(str_replace('\\', '/', (string) $goreli_yol), '/');
    return '<script src="' . esc(APP_BASE_URL . $yol) . '?v=' . varlik_suru($yol) . '" defer></script>';
}

/**
 * Zengin icerik temizligi - ALLOW-LIST DOMDocument sanitizeri.
 *
 * Eski hali regex kara listesiydi; olay niteligi deseni (`\son...`) basinda
 * bosluk istediginden `<img src=x onerror=...>` gibi girdiler sessizce
 * geciyordu. Yeni hali DOM ağaci üzerinden calisir: serialization'i HTML
 * parser'i yaptigi icin bosluk/tyrnak/tur farkli etmez.
 *
 * Kural: izinli etiketler ve izinli nitelikler korunur; diger etiketler
 * SOLUNUR (ic metin kalir), script/style/iframe gibi tehlikeli dugumler ise
 * iceriyle birlikte SILINIR. href/src degerlerinde riskli protokoller,
 * her turlu `on*` niteligi ve HTML yorumlari surulur.
 */
function temiz_html($html): string
{
    $girdi = (string)$html;
    if (trim($girdi) === '') {
        return '';
    }

    $izinli_etiket = [
        'p', 'br', 'hr', 'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'sub', 'sup',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'blockquote', 'pre', 'code',
        'div', 'span', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th', 'a', 'img',
    ];

    // Icerigiyle birlikte tamamen atilacak dugumler
    $yasakli_etiket = [
        'script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet',
        'form', 'input', 'textarea', 'select', 'option', 'button', 'link', 'meta',
        'base', 'html', 'head', 'body', 'noscript', 'svg', 'math',
    ];

    // Etiket bazli nitelik listesi; '*' tum izinli etiketler icin ortak
    $izinli_nitelik = [
        '*'   => ['class'],
        'a'   => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'width', 'height', 'title', 'class'],
        'td'  => ['colspan', 'rowspan'],
        'th'  => ['colspan', 'rowspan', 'scope'],
        'ol'  => ['start', 'reversed', 'type'],
        'ul'  => ['type'],
        'li'  => ['value'],
        'code' => ['class'],
        'pre'  => ['class'],
    ];

    $belge = new DOMDocument('1.0', 'UTF-8');
    $belge->preserveWhiteSpace = true;
    $belge->formatOutput = false;

    // UTF-8 girdi dogru okunsun; libxml uyarilari yutulur (bozuk HTML atilmaz)
    $onceki = libxml_use_internal_errors(true);
    $belge->loadHTML(
        '<?xml encoding="UTF-8"' . '>' . $girdi,
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();
    libxml_use_internal_errors($onceki);

    // xml PI'si bosluklu bir dugum olarak birakabiliyor: temizle
    foreach (iterator_to_array($belge->childNodes) as $koku) {
        if ($koku instanceof DOMProcessingInstruction || $koku instanceof DOMDocumentType) {
            $belge->removeChild($koku);
        }
    }

    $guvenli_url = static function ($deger) {
        $v = (string)$deger;
        if (trim($v) === '') {
            return false;
        }
        // Protokol adini gizleyen kontrol karakterleri/tyrnaklar once silinir
        $olcut = strtolower(preg_replace('/[\x00-\x20\x22\x27]+/', '', $v) ?? '');
        if (preg_match('#^(javascript|vbscript|livescript|data|file|about|blob):#', $olcut)) {
            return false;
        }
        return stripos($olcut, 'expression(') === false;
    };

    $temizle = static function (DOMNode $kok) use (&$temizle, $izinli_etiket, $yasakli_etiket, $izinli_nitelik, $guvenli_url) {
        // Sondan baslara: dugum silmek index'leri kaydirir
        for ($i = $kok->childNodes->length - 1; $i >= 0; $i--) {
            $dugum = $kok->childNodes->item($i);

            if ($dugum instanceof DOMComment) {
                $kok->removeChild($dugum);
                continue;
            }
            if ($dugum instanceof DOMText || $dugum instanceof DOMProcessingInstruction) {
                continue;
            }
            if (!($dugum instanceof DOMElement)) {
                $kok->removeChild($dugum);
                continue;
            }

            $ad = strtolower($dugum->nodeName);

            if (in_array($ad, $yasakli_etiket, true)) {
                $kok->removeChild($dugum);
                continue;
            }

            if (!in_array($ad, $izinli_etiket, true)) {
                // Izinsiz ama zararsiz: etiketi kaldir, icerigi koru
                $temizle($dugum);
                while ($dugum->firstChild !== null) {
                    $dugum->parentNode->insertBefore($dugum->firstChild, $dugum);
                }
                $dugum->parentNode->removeChild($dugum);
                continue;
            }

            $temizle($dugum);

            // Nitelik suzgeci
            $izinli = array_merge($izinli_nitelik['*'] ?? [], $izinli_nitelik[$ad] ?? []);
            foreach (iterator_to_array($dugum->attributes) as $nitelik) {
                $adNitelik = strtolower($nitelik->nodeName);

                if (strncmp($adNitelik, 'on', 2) === 0 || !in_array($adNitelik, $izinli, true)) {
                    $dugum->removeAttribute($nitelik->nodeName);
                    continue;
                }
                if (($adNitelik === 'href' || $adNitelik === 'src') && !$guvenli_url($nitelik->nodeValue)) {
                    $dugum->removeAttribute($nitelik->nodeName);
                    continue;
                }
                if ($adNitelik === 'src' && strncmp(strtolower((string)$nitelik->nodeValue), 'data:', 5) === 0) {
                    $dugum->removeAttribute($nitelik->nodeName);
                }
            }

            // target="_blank" yeni pencere acar: referans sizintisini kapat
            if ($ad === 'a' && $dugum->hasAttribute('target')) {
                $dugum->setAttribute('rel', 'noopener noreferrer');
            }
        }
    };

    $temizle($belge);

    // saveHTML() non-ASCII karakterleri entity'ye cevirir ("Tuurkce" -> "T&uuml;rk&ccedil;e").
    // Bu haliyle DB'ye yazilirsa okunabilirlik ve LIKE aramasi bozulur. Yalniz non-ASCII
    // karaktere karsilik gelen entity'ler ham UTF-8'e geri alinir; &amp; &lt; &gt; &quot;
    // gibi guvenlik entity'leri bilerek oldugu gibi birakilir.
    $temiz = preg_replace_callback(
        '/&(?:[a-zA-Z][a-zA-Z0-9]*|#\d+|#[xX][0-9a-fA-F]+);/',
        static function (array $m): string {
            $car = html_entity_decode($m[0], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $kod = mb_strlen($car, 'UTF-8') === 1 ? mb_ord($car, 'UTF-8') : 0;
            return $kod > 0x7F ? $car : $m[0];
        },
        (string)$belge->saveHTML()
    );

    return trim((string)$temiz);
}

/** Duz metin girdi normalizasyonu (bosluk/kontrol karakteri kirpma). */
function temiz_metin($v): string
{
    return trim((string)$v);
}

/** Basit e-posta dogrulama. */
function gecerli_email($v): bool
{
    return (bool)filter_var((string)$v, FILTER_VALIDATE_EMAIL);
}

/** SMTP header enjeksiyonunu onler (\r\n kirpma). */
function header_temizle($v): string
{
    return str_replace(["\r", "\n"], '', (string)$v);
}

/**
 * Admin sayfalarinda sayfa iskeleti icinde hata ekrani basar ve durur.
 * die('<div class="alert ...">') kullaniminin yerine gecer: header/footer
 * dogru sekilde kapanir, ayrinti APP_DEBUG kapaliyken yalnizca log'a yazilir.
 * Cagri noktasi mantik bolumunde (header basilmadan once) kalmalidir.
 */
function admin_hata($mesaj, $ayrinti = '', $durum = 400): never
{
    $ayrinti = (string)$ayrinti;
    if ($ayrinti !== '') {
        error_log('admin_hata: ' . $ayrinti);
    }

    if (headers_sent()) {
        // Cikti baslamis: iskeleti ikinci kez basma, yalnizca mesaji goster
        http_response_code($durum);
        echo '<div class="alert alert-danger">' . esc($mesaj) . '</div>';
        exit;
    }

    http_response_code($durum);
    include_once __DIR__ . '/../admin/header.php';
    echo '<div class="container"><div class="alert alert-danger">' . esc($mesaj);
    if (APP_DEBUG && $ayrinti !== '') {
        echo '<br><small>' . esc($ayrinti) . '</small>';
    }
    echo '</div></div>';
    include __DIR__ . '/../admin/footer.php';
    exit;
}

/**
 * PDOException'i kullaniciya gorunecek KISA mesaja cevirir.
 * Tablo eksikse (SQLSTATE 42S02 / MySQL 1146) spesifik uyarir; aksi halde
 * cagiranin kendi varsayilan mesajini korur (ozel mesajlar kaybolmasin).
 */
function db_hata_mesaji(Throwable $e, string $varsayilan = 'Veritabanı hatası oluştu.'): string
{
    if ($e instanceof PDOException) {
        $kod = (string) $e->getCode();
        $drv = (int) ($e->errorInfo[1] ?? 0);
        if ($kod === '42S02' || $drv === 1146) {
            return 'Veritabanında tablolar bulunamadı.';
        }
    }
    return $varsayilan;
}

/** Oturum tabarli tek kullanimlik bildirim (flash). */
function flash($tip, $mesaj = null)
{
    if (!session_id()) {
        return;
    }
    if ($mesaj === null) {
        return isset($_SESSION['_flash'][$tip]) ? $_SESSION['_flash'][$tip] : null;
    }
    $_SESSION['_flash'][$tip] = $mesaj;
}

/** Tum flash bildirimlerini okuyup temizler. */
function flash_get_all(): array
{
    if (!session_id() || empty($_SESSION['_flash'])) {
        return [];
    }
    $f = $_SESSION['_flash'];
    unset($_SESSION['_flash']);
    return $f;
}

/**
 * flash() ile birakilmis bildirimleri Bootstrap alert iskeletiyle basar ve
 * tuketir. Sayfa iskeletinin (header) hemen altinda cagrilir; boylece
 * yonlendirme sonrasi mesaj tek noktadan, tekrar tekrar kod yazilmadan gorunur.
 * Tip -> Bootstrap toni eslemesi bilincli olarak dar tutuldu (basari/uyari/
 * hata/bilgi); beklenmedik tip 'info'ya duser.
 */
function flash_render(): string
{
    $tonler = [
        'basari' => 'success', 'success' => 'success',
        'uyari'  => 'warning', 'warning' => 'warning',
        'hata'   => 'danger',  'error'   => 'danger',
        'bilgi'  => 'info',    'info'    => 'info',
    ];
    $ikonler = [
        'success' => 'lk-check-circle',
        'warning' => 'lk-alert-triangle',
        'danger'  => 'lk-alert-circle',
        'info'    => 'lk-info',
    ];

    $cikti = '';
    foreach (flash_get_all() as $tip => $mesaj) {
        $mesaj = trim((string)$mesaj);
        if ($mesaj === '') {
            continue;
        }
        $ton = $tonler[$tip] ?? 'info';
        $cikti .= '<div class="alert alert-' . $ton . ' alert-dismissible fade show mb-4" role="alert">'
                . '<svg class="ik me-2" aria-hidden="true"><use href="#' . $ikonler[$ton] . '"></use></svg>'
                . esc($mesaj)
                . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>'
                . '</div>';
    }

    return $cikti;
}

/**
 * Siparis idempotency tokeni: odeme sayfasi uretilirken oturuma tek
 * kullanimlik bir deger yazilir, siparis_onay.php dogruladigi anda tuketir.
 * Amac "ayni formu iki kez gonderme"nin cift siparis (ve cift stok dusumu)
 * uretmesini engellemek - kullanici geri/ileri gidip sayfayi yenilediginde
 * yeni token üretilir, eski gonderim artik gecersizdir.
 */
function siparis_token(): string
{
    if (!session_id()) {
        app_session_start();
    }
    if (empty($_SESSION['_siparis_token'])) {
        $_SESSION['_siparis_token'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['_siparis_token'];
}

/** Idempotency tokeninin gizli form alani. */
function siparis_token_alani(): string
{
    return '<input type="hidden" name="siparis_token" value="' . esc(siparis_token()) . '">';
}

/**
 * Token'i dogrula ve TEK KULLANIMLIK olarak tuket.
 * Başarisizsa false doner; cagri tarafinda siparis hic yazilmaz.
 */
function siparis_token_dogrula($gelen): bool
{
    $beklenen = (string)($_SESSION['_siparis_token'] ?? '');
    unset($_SESSION['_siparis_token']); // ne olursa olsun tuketilr (yeniden oynatma yok)

    return $beklenen !== '' && (string)$gelen !== '' && hash_equals($beklenen, (string)$gelen);
}

/**
 * Insana okunabilir, benzersiz siparis numarasi: SC + YYMMDDHHIISS + 2 rastgele
 * rakam = 16 karakter (siparisler.numarasi CHAR(16) ile birebir).
 */
function siparis_numarasi(): string
{
    return 'SC' . date('ymdHis') . random_int(10, 99);
}

/**
 * Siparis durumlarinin TEK tanimi (DB ENUM'iyle birebir ayni liste).
 * Etiket: ekranda gorunen Turkce ad; sinif: Bootstrap badge tonu.
 * Yeni durum eklenirken yalnizca bu dizi degisir - admin listesi, detay
 * sayfasindaki secenekler ve panel kartlari buradan beslenir.
 */
function siparis_durumlari(): array
{
    return [
        'alindi'        => ['etiket' => 'Alındı',        'sinif' => 'secondary'],
        'hazirlaniyor'  => ['etiket' => 'Hazırlanıyor',  'sinif' => 'info'],
        'kargoda'       => ['etiket' => 'Kargoda',       'sinif' => 'primary'],
        'kapandi'       => ['etiket' => 'Tamamlandı',    'sinif' => 'success'],
        'iptal'         => ['etiket' => 'İptal',         'sinif' => 'danger'],
    ];
}

/** Durum kodu -> gorunum bilgisi; tanimsiz kod "bilinmeyen" olarak damitilir. */
function siparis_durum_gorunum($durum): array
{
    $h = siparis_durumlari()[(string)$durum] ?? null;
    return $h ?? ['etiket' => 'Bilinmeyen (' . (string)$durum . ')', 'sinif' => 'light text-dark'];
}

/** Odeme yontemi kodu -> Turkce etiket (DB ENUM: havale/kart/kapida). */
function siparis_odeme_etiketi($kod): string
{
    $etiketler = [
        'havale' => 'Havale / EFT',
        'kart'   => 'Banka / Kredi Kartı',
        'kapida' => 'Kapıda Ödeme',
    ];
    return $etiketler[(string)$kod] ?? (string)$kod;
}

/**
 * Siparis/odeme formunda secilebilir illerin TEK listesi.
 * Odeme formu (satinal.php) ile kullanici profili (hesabim.php) ayni listeyi
 * kullanir; iki yerde elle yazilsa birinde unutulur ve profilinden gelen il
 * degeri odeme formunda secenek bulamaz.
 */
function siparis_illeri(): array
{
    return ['Adana', 'Ankara', 'Bursa', 'Edirne', 'İstanbul', 'Tekirdağ', 'Zonguldak'];
}

/**
 * Il <select> icinin <option> dizesi. $secili eslesen satira selected alani
 * alir (profil formunda ve hatali gonderimde formu dolu geri dondurur).
 */
function il_secenekleri($secili = ''): string
{
    $secili = trim((string)$secili);
    $cikti  = '<option value=""' . ($secili === '' ? ' selected' : '') . '>Seçiniz</option>';

    foreach (siparis_illeri() as $il) {
        $cikti .= '<option value="' . esc($il) . '"' . ($il === $secili ? ' selected' : '') . '>' . esc($il) . '</option>';
    }

    return $cikti;
}

/** LIKE deseni: kullanici girdisindeki % ve _ kacisan karakter olarak sabitlenir. */
function like_deseni($girdi, $basinda_wildcard = true): string
{
    $temiz = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], trim((string)$girdi));
    return ($basinda_wildcard ? '%' : '') . $temiz . '%';
}

/**
 * Arama girdisini tek bicimde sabitler: bosluk kirpilir ve uzunluk sinirlanir.
 * Sinir sart: `LIKE '%...%'` prefix indeksi kullanamadigi icin satir sayisiyla
 * birlikte pahalanir; 64 karakterlik bir tarama sonuclari degistirmez ama
 * "tum tabloyu 1000 karakterle tara" gibi servis disi girdileri keser.
 */
function arama_girdisi($gelen, $azami = 64): string
{
    return mb_substr(trim((string)$gelen), 0, (int)$azami, 'UTF-8');
}

/**
 * Siparis onay e-postasini aliciya gonderir.
 *
 * PHP `mail()` XAMPP'te (ve cok tek sunuculu kurulumda) gercek bir MTA'ya
 * baglanmayabilir; bu yuzden sonuc DURUM doneri olarak kullanilir, ekranda
 * "gonderildi" yalani degil gercek durum soylenir. Basarisizlik siparisin
 * kendisini gecersiz kilmez (kayit DB'de zaten commit olmustur).
 *
 * @return bool e-posta gonderevine teslim edilebildi mi
 */
function siparis_eposta_gonder(array $siparis, array $kalemler): bool
{
    $alici = trim((string)($siparis['eposta'] ?? ''));
    if (!gecerli_email($alici)) {
        error_log('siparis_eposta_gonder: gecersiz alici e-postasi, gidilmedi.');
        return false;
    }

    $satirlar = [];
    $satirlar[] = 'Siparis Numarasi : ' . $siparis['numarasi'];
    $satirlar[] = 'Tarih            : ' . date('d.m.Y H:i');
    $satirlar[] = '';
    $satirlar[] = 'SIPARIS KALEMLERI';
    foreach ($kalemler as $kalem) {
        $satirlar[] = sprintf(
            '- %s | %d x %s TL = %s TL',
            $kalem['urunadi'],
            (int)$kalem['adet'],
            para($kalem['birim_fiyat']),
            para($kalem['satir_toplam'])
        );
    }
    $satirlar[] = '';
    $satirlar[] = 'Ara Toplam : ' . para($siparis['ara_toplam']) . ' TL';
    $satirlar[] = 'Kargo      : ' . ($siparis['kargo'] > 0 ? para($siparis['kargo']) . ' TL' : 'Ucretsiz');
    $satirlar[] = 'TOPLAM     : ' . para($siparis['toplam']) . ' TL';
    $satirlar[] = '';
    $satirlar[] = 'Odeme Yontemi : ' . siparis_odeme_etiketi($siparis['odeme_tipi']);
    $satirlar[] = 'Teslimat      : ' . $siparis['adsoyad'] . ' / ' . $siparis['telefon'];
    $satirlar[] = 'Adres         : ' . $siparis['il'] . ' ' . $siparis['ilce'] . ' - ' . $siparis['adres'];

    $govde = "Sayin " . $siparis['adsoyad'] . ",\r\n\r\n"
           . "Siparisiniz alindi. Ozet bilgileri asagidadir.\r\n\r\n"
           . implode("\r\n", $satirlar) . "\r\n";

    $konu = 'Siparisiniz Alindi - ' . $siparis['numarasi'];

    $tanimlar  = "MIME-Version: 1.0\r\n";
    $tanimlar .= "Content-Type: text/plain; charset=UTF-8\r\n";
    // From: Site adresi tanimli degilse gonderev basligi eksik birakilir; mail()
    // bu durumda sunucu varsayilanini kullanir (patlayici bir davranis degil).
    $site_eposta = site_bilgisi('eposta');
    if ($site_eposta !== '') {
        $tanimlar .= "From: " . '=?UTF-8?B?' . base64_encode('ShopCore') . '?= <' . header_temizle($site_eposta) . ">\r\n";
        $tanimlar .= "Reply-To: " . header_temizle($site_eposta) . "\r\n";
    }
    $tanimlar .= "X-Mailer: PHP";

    // mail() gonderev yoksa PHP ham Warning basar; `@` yalnizca o gorsel gultuyu
    // engeller - SONUC MUTLAKA kontrol edilir ve basarisizlik log'a yazilir.
    // (Eski davranistaki hata `@` degil, doner degerinin hic okunmamasiydi.)
    $gonderildi = @mail($alici, header_temizle($konu), $govde, $tanimlar);

    if (!$gonderildi) {
        error_log('siparis_eposta_gonder: mail() basarisiz (siparis ' . $siparis['numarasi'] . ').');
    }

    return (bool)$gonderildi;
}

/**
 * Yonlendirme hedefini site icinde tutar (acik yonlendirme kapisi birakmaz).
 *
 * Mutlak URL (http://..., https://...) HER ZAMAN yalnizca yol bilesenine
 * indirgenir; host asla kullanilmaz. Host karsilastirmasi HTTP_HOST (istemci
 * basligi - taklit edilebilir) uzerinden yapildigi icin guven tabani olarak
 * birakildi: mevcut hicbir cagiran mutlak URL gecirmiyor, dolayisiyla ayni
 * siteye tam URL dondurme gereksinimi yok. Boylece harici/klik host ve URL'e
 * gomulu kimlik bilgisi (http://x@site/) kendiliginden dusur.
 *
 * Yol basindaki // (protocol-relative) ve CR/LF de temizlenir: ltrim sonrasi
 * //evil.com bile APP_BASE_URL altinda site ici bir yola donusur.
 */
function guvenli_yonlendirme_hedefi($yol): string
{
    $yol = str_replace(["\r", "\n"], '', (string) $yol);

    if (preg_match('#^https?://#i', $yol)) {
        // Host guvenilmez: mutlak URL yalnizca yoluna indirgenir.
        $yol = (string) parse_url($yol, PHP_URL_PATH);
    }

    $yol = ltrim($yol, '/');

    return APP_BASE_URL . ($yol === '' ? 'index.php' : $yol);
}

/** APP_BASE_URL tabanli yonlendirme ve cikis. */
function redirect($yol)
{
    $hedef = guvenli_yonlendirme_hedefi($yol);

    // Cikti baslamissa header() calismaz; meta+JS ile yonlendir ve logla
    if (headers_sent($dosya, $satir)) {
        error_log("redirect: headers already sent in {$dosya}:{$satir} -> {$hedef}");
        echo '<meta http-equiv="refresh" content="0;url=' . esc($hedef) . '">';
        echo '<script>location.replace(' . json_encode($hedef) . ');</script>';
        exit;
    }

    header('Location: ' . $hedef);
    exit;
}
