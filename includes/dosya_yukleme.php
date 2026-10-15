<?php
/**
 * Guvenli gorsel yukleme.
 * Eskiden sadece dosya uzantisina bakan dogrulamayi MIME (getimagesize)
 * ile saglamlastirir; istemci dosya adini KULLANMADAN benzersiz ad uretir.
 */

/** Yuklenebilir azami bayt - sunucu ve istemci ayni degeri okur. */
function resim_yukleme_siniri(): int
{
    return 1024000; // 1 MB
}

/**
 * Kabul edilen gercek gorsel turleri: getimagesize() sonucu => uretilecek uzanti.
 * Tek kaynak burasi; sunucu dogrulamasi, <input accept> ve istemci JS listesi
 * bu tablodan turetilir (ayni listede ucuruk olmasi engellenir).
 */
function resim_izinli_turler(): array
{
    return [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_GIF  => 'gif',
        IMAGETYPE_WEBP => 'webp',
    ];
}

/** Istemci tarafi/accept listesi: yukaridaki tablodan turetilir. */
function resim_izinli_uzantilar(): array
{
    $liste = [];
    foreach (resim_izinli_turler() as $uzanti) {
        $liste[] = $uzanti;
        if ($uzanti === 'jpg') {
            $liste[] = 'jpeg'; // ayni turun diger yazilimi
        }
    }
    return $liste;
}

/** <input type="file" accept="..."> degeri. */
function resim_accept_ozelligi(): string
{
    return '.' . implode(',.', resim_izinli_uzantilar());
}

/** Kullaniciya gosterilen yukleme kurali (JS uyarilari da buradan okur). */
function resim_kural_metni(): string
{
    $turler = implode('/', array_map('strtoupper', resim_izinli_uzantilar()));
    return 'izinli turler: ' . $turler . ', en fazla ' . round(resim_yukleme_siniri() / 1024000, 1) . ' MB';
}

/**
 * Sunucu tarafi gorsel isleme gd gerektirir. XAMPP'in varsayilan php.ini'sinde
 * `extension=gd` yorumdur; o ortamda su fonksiyon false doner ve isleme hattinin
 * tamami atlanir - yukleme davranisi hic degismez (bozuk/eksik dosya uretilmez).
 */
function resim_isleme_var_mi(): bool
{
    return function_exists('imagecreatetruecolor')
        && function_exists('imagecopyresampled')
        && function_exists('imagewebp')
        && function_exists('imagefill');
}

/** getimagesize() tur kodundan gd kaynagi okur; okunamazsa false. */
function resim_kaynak_oku(string $dosya, int $tip)
{
    switch ($tip) {
        case IMAGETYPE_JPEG: return @imagecreatefromjpeg($dosya);
        case IMAGETYPE_PNG:  return @imagecreatefrompng($dosya);
        case IMAGETYPE_GIF:  return @imagecreatefromgif($dosya);
        case IMAGETYPE_WEBP: return @imagecreatefromwebp($dosya);
    }
    return false;
}

/**
 * Saydam zeminli truecolor tuval. PNG/WebP alfasini korur; GIF animasyonu gd
 * ile tek kareye dusur (bilinen kisit - girizde hareketli gorsel yerine
 * statik gorsel kullanilmasi onerilir).
 */
function resim_tuval($gen, $yuk)
{
    $tuval = imagecreatetruecolor((int) $gen, (int) $yuk);
    imagesavealpha($tuval, true);
    imagealphablending($tuval, false);
    imagefill($tuval, 0, 0, imagecolorallocatealpha($tuval, 0, 0, 0, 127));
    return $tuval;
}

/** Tuvali $dosya'ya tur koduna gore yazar. */
function resim_dosyaya_yaz($gorsel, string $dosya, int $tip): bool
{
    switch ($tip) {
        case IMAGETYPE_JPEG: return imagejpeg($gorsel, $dosya, 88);
        case IMAGETYPE_PNG:  return imagepng($gorsel, $dosya, 8);
        case IMAGETYPE_GIF:  return imagegif($gorsel, $dosya);
        case IMAGETYPE_WEBP: return imagewebp($gorsel, $dosya, 82);
    }
    return false;
}

/**
 * Yuklenen gorseli sunucu tarafinda isler:
 *  1) genislik RESIM_MAX_GENISLIK'i asiyorsa esit oranda kucultup DOSYANIN
 *     KENDISINE yazar - uzanti/tur korunur, cunku PNG'yi jpg'ye cevirmek
 *     alfa kaybidir,
 *  2) kart boyutu icin "{taban}-{RESIM_KART_GENISLIK}.webp" varyanti uretir
 *     (ufak gorseller buyutulmez).
 * Varyant yoksa resim_yolu() orijinali kullanir; bu yuzden gd'siz ortamlar ve
 * onceki kayitlar bozulmaz. Tek adimin basarisiz olmasi yikim degildir: asil
 * dosya zaten diskte duruyordur, varyant yazilamazsa yarim dosya silinir.
 */
function resim_isle(string $dosyaYolu): void
{
    if (!resim_isleme_var_mi()) {
        return;
    }
    $bilgi = @getimagesize($dosyaYolu);
    if ($bilgi === false || $bilgi[0] < 1 || $bilgi[1] < 1) {
        return;
    }
    $gen = (int) $bilgi[0];
    $yuk = (int) $bilgi[1];
    $tip = (int) $bilgi[2];

    // 1) Azami genisliga olcekle
    if ($gen > RESIM_MAX_GENISLIK) {
        $kaynak = resim_kaynak_oku($dosyaYolu, $tip);
        if ($kaynak !== false) {
            $yeniGen = RESIM_MAX_GENISLIK;
            $yeniYuk = max(1, (int) round($yuk * ($yeniGen / $gen)));
            $tuval = resim_tuval($yeniGen, $yeniYuk);
            imagecopyresampled($tuval, $kaynak, 0, 0, 0, 0, $yeniGen, $yeniYuk, $gen, $yuk);
            if (!resim_dosyaya_yaz($tuval, $dosyaYolu, $tip)) {
                error_log('resim_isle: olceklenmis kopya yazilamadi - ' . $dosyaYolu);
            }
            imagedestroy($tuval);
            imagedestroy($kaynak);
            $gen = $yeniGen;
            $yuk = $yeniYuk;
        }
    }

    // 2) Kart boyutu webp varyanti
    if ($gen >= RESIM_KART_GENISLIK) {
        $kaynak = resim_kaynak_oku($dosyaYolu, $tip);
        if ($kaynak !== false) {
            $varyant = preg_replace('/\.[a-z0-9]+$/i', '', $dosyaYolu)
                . '-' . RESIM_KART_GENISLIK . '.webp';
            $yeniGen = RESIM_KART_GENISLIK;
            $yeniYuk = max(1, (int) round($yuk * ($yeniGen / $gen)));
            $tuval = resim_tuval($yeniGen, $yeniYuk);
            imagecopyresampled($tuval, $kaynak, 0, 0, 0, 0, $yeniGen, $yeniYuk, $gen, $yuk);
            if (!resim_dosyaya_yaz($tuval, $varyant, IMAGETYPE_WEBP)) {
                @unlink($varyant); // yarim yazilmis varyant sunulmasin
            }
            imagedestroy($tuval);
            imagedestroy($kaynak);
        }
    }
}

/**
 * Tek bir $_FILES girisini dogrular ve $hedefKlasor'e tasir.
 *
 * @param array  $dosya        $_FILES['resim'] veya bir ek resim satiri
 * @param string $hedefKlasor  Dosya sistemi yolu (sonda / ile)
 * @return array ['ok'=>bool, 'ad'=>string, 'hata'=>string]
 */
function resim_yukle(array $dosya, $hedefKlasor): array
{
    $sonuc = ['ok' => false, 'ad' => '', 'hata' => ''];

    // Yukleme hatasi (UPLOAD_ERR_OK disindaki her durum)
    if (!isset($dosya['error']) || $dosya['error'] !== UPLOAD_ERR_OK) {
        $sonuc['hata'] = 'Dosya yuklenemedi (yukleme hatasi).';
        return $sonuc;
    }

    // Boyut siniri - tek kaynak
    if ($dosya['size'] > resim_yukleme_siniri()) {
        $sonuc['hata'] = 'Resim dosyasi ' . round(resim_yukleme_siniri() / 1024000, 1) . ' MB sinirini asamaz.';
        return $sonuc;
    }

    // MIME / boyut dogrulama - istemci adina guvenmez
    $bilgi = @getimagesize($dosya['tmp_name']);
    if ($bilgi === false) {
        $sonuc['hata'] = 'Gecerli bir gorsel dosyasi degil.';
        return $sonuc;
    }

    $izin = resim_izinli_turler();
    $tip = $bilgi[2];
    if (!isset($izin[$tip])) {
        $sonuc['hata'] = 'Gorsel turu kabul edilmiyor (' . resim_kural_metni() . ').';
        return $sonuc;
    }

    // Benzersiz ad: istemci dosya adi KULLANILMAZ
    $ext = $izin[$tip];
    $ad  = uniqid() . '-' . bin2hex(random_bytes(6)) . '.' . $ext;

    $hedef = rtrim($hedefKlasor, '/\\') . DIRECTORY_SEPARATOR . $ad;

    // Hedef klasor yoksa olustur
    if (!is_dir(rtrim($hedefKlasor, '/\\'))) {
        @mkdir(rtrim($hedefKlasor, '/\\'), 0755, true);
    }

    if (!move_uploaded_file($dosya['tmp_name'], $hedef)) {
        $sonuc['hata'] = 'Dosya sunucuya tasinarak kaydedilemedi.';
        return $sonuc;
    }

    @chmod($hedef, 0644);
    // gd etkinse olcek + kart varyanti uretilir; degilse dosya oldugu gibi kalir
    resim_isle($hedef);
    $sonuc['ok'] = true;
    $sonuc['ad'] = $ad;
    return $sonuc;
}
