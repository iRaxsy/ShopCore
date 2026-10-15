<?php
/**
 * Tek merkezi Bootstrap 5 sayfalandirma.
 * 5 farkli dosyada kopyalanan mantigin yerini alir.
 *
 * @param int    $sayfa         Aktif sayfa (1'den basar)
 * @param int    $toplam_sayfa
 * @param string $taban         Script yolu (or. 'urunler.php', 'liste.php')
 * @param array  $params        Korunacak sorgu parametreleri
 * @param int    $gosterilecek  Gorunur sayfa dümesi sayisi
 * @param string $nav_sinifi    <nav> etiketinin CSS sinifi ('' ile basliksiz)
 */
function sayfalama($sayfa, $toplam_sayfa, $taban, array $params = [], $gosterilecek = 5, $nav_sinifi = 'mt-4')
{
    if ($toplam_sayfa <= 1) {
        return '';
    }
    $sayfa = max(1, (int)$sayfa);

    /* Bos parametreler linki gereksiz sisirmez (?aranan= yerine hic) */
    $params = array_filter($params, static function ($deger) {
        return $deger !== '' && $deger !== null;
    });

    $link = function ($p) use ($taban, $params) {
        $q = $params;
        $q['sayfa'] = (int)$p;
        return url($taban, $q);
    };

    $sinif = $nav_sinifi !== '' ? ' class="' . esc($nav_sinifi) . '"' : '';
    $html = '<nav aria-label="Sayfalandirma"' . $sinif . '><ul class="pagination justify-content-center">';

    // Önceki
    if ($sayfa > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $link($sayfa - 1) . '" aria-label="Önceki"><span aria-hidden="true">&laquo;</span></a></li>';
    } else {
        $html .= '<li class="page-item disabled"><a class="page-link" href="#" aria-label="Önceki" tabindex="-1"><span aria-hidden="true">&laquo;</span></a></li>';
    }

    $baslangic = max(1, $sayfa - (int)floor($gosterilecek / 2));
    $bitis     = min($toplam_sayfa, $baslangic + $gosterilecek - 1);
    if ($bitis - $baslangic < $gosterilecek - 1) {
        $baslangic = max(1, $bitis - $gosterilecek + 1);
    }

    for ($i = $baslangic; $i <= $bitis; $i++) {
        $aktif = ($i == $sayfa) ? ' active' : '';
        $aria  = ($i == $sayfa) ? ' aria-current="page"' : '';
        $html .= '<li class="page-item' . $aktif . '"><a class="page-link" href="' . $link($i) . '"' . $aria . '>' . (int)$i . '</a></li>';
    }

    // Sonraki
    if ($sayfa < $toplam_sayfa) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $link($sayfa + 1) . '" aria-label="Sonraki"><span aria-hidden="true">&raquo;</span></a></li>';
    } else {
        $html .= '<li class="page-item disabled"><a class="page-link" href="#" aria-label="Sonraki" tabindex="-1"><span aria-hidden="true">&raquo;</span></a></li>';
    }

    $html .= '</ul></nav>';
    return $html;
}

/**
 * Admin liste sayfalarinin sarji: sayfalama() + "sayfaya git" formu.
 * Bes dosyada elle kopyalanan <ul> bloku ve jump formu burada toplanir;
 * CSS siniflari (pagination-container/-left/-right, page-jump-form)
 * admin-style.css'te tanimli oldugu gibi birakilir.
 *
 * @param string $taban  bulunulan sayfaya GORELI ad ('liste.php', 'urunler.php')
 * @param array  $params formda korunacak gizli alanlar (filtreler)
 */
function admin_sayfalama($sayfa, $toplam_sayfa, $taban, array $params = [], $gosterilecek = 5): string
{
    if ($toplam_sayfa <= 1) {
        return '';
    }
    $sayfa = max(1, (int)$sayfa);

    $params = array_filter($params, static function ($deger) {
        return $deger !== '' && $deger !== null;
    });

    $gizli_alanlar = '';
    foreach ($params as $ad => $deger) {
        $gizli_alanlar .= '<input type="hidden" name="' . esc($ad) . '" value="' . esc($deger) . '">' . "\n            ";
    }

    return '<div class="pagination-container">' . "\n"
         . '    <div class="pagination-left">' . sayfalama($sayfa, $toplam_sayfa, $taban, $params, $gosterilecek, '') . '</div>' . "\n"
         . '    <div class="pagination-right">' . "\n"
         . '        <form method="get" action="' . esc($taban) . '" class="page-jump-form">' . "\n"
         . '            ' . $gizli_alanlar
         . '            <input type="number" name="sayfa" class="page-input" min="1" max="' . (int) $toplam_sayfa . '" value="' . (int) $sayfa . '" placeholder="Sayfa">' . "\n"
         . '            <button type="submit" class="page-jump-btn">' . "\n"
         . '                <svg class="ik" aria-hidden="true"><use href="#lk-log-in"></use></svg> Git' . "\n"
         . '            </button>' . "\n"
         . '        </form>' . "\n"
         . '    </div>' . "\n"
         . '</div>';
}
