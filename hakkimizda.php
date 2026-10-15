<?php include "header.php"; ?>
<div class="container p-4 p-md-5">
    <?php
    $islem = isset($_GET['islem']) ? $_GET['islem'] : "";
    if($islem == 'tamam') {
        echo "<div class='alert alert-success alert-dismissible fade show' role='alert'>
                Mesajınız başarıyla iletildi.
                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
              </div>";
    }
    else if($islem == 'hata') {
        echo "<div class='alert alert-danger alert-dismissible fade show' role='alert'>
                Mesajınız iletilemedi!
                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
              </div>";
    }
    else if($islem == 'kapali') {
        echo "<div class='alert alert-warning alert-dismissible fade show' role='alert'>
                Mesaj kabulü şu anda kapalı: site yöneticisi alıcı e-posta adresini tanımlamamış.
                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
              </div>";
    }
    ?>
    
    <h1 class="text-center baslik mb-4">ShopCore</h1>
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <p class="text-justify p-3 p-md-4 bg-body-tertiary rounded">
                ShopCore olarak ilk önceliğimiz olan "müşteri memnuniyeti" ve ardından gelen kalite anlayışımızla, satışlarımızı hız kesmeden sürdürmeye devam ederken, sektörün ihtiyaçları doğrultusunda, kaliteli ürün tedarik etmek ve ürünlerimizden daha uzun yıllar faydalanmanız için çalışıyoruz. Türkiye'de değer kazanan bir marka olarak, uluslararası bir marka olma yolunda hızla ilerliyoruz. Kuruluşumuzun 10. yılını kutladığımız bu günlerde, heyecanı ve mutluluğu yaşıyoruz.
            </p>
        </div>
    </div>
    
    <hr class="my-5">
    
    <div class="row mt-4">
        <div class="col-md-4 mb-4 mb-md-0">
            <h2 class="baslik mb-3">İletişim Formu</h2>
            <div class="card border-0 bg-body-tertiary">
                <div class="card-body">
                    <h5 class="card-title">İletişim Bilgileri</h5>
                    <p class="card-text">
                        <?php
                        // Bilgiler config/yerel.php'den okunur; bos alan ikonla birlikte gosterilmez
                        $iletisim_kalemleri = [
                            ['ikon' => 'map-pin', 'deger' => site_bilgisi('adres')],
                            ['ikon' => 'phone',   'deger' => site_bilgisi('telefon')],
                            ['ikon' => 'mail',    'deger' => site_bilgisi('eposta')],
                        ];
                        $gorunen = [];
                        foreach ($iletisim_kalemleri as $kalem) {
                            if ($kalem['deger'] === '') {
                                continue;
                            }
                            $gorunen[] = '<svg class="ik me-2 text-primary" aria-hidden="true"><use href="#lk-' . $kalem['ikon'] . '"></use></svg>' . esc($kalem['deger']);
                        }
                        if ($gorunen === []) {
                            echo '<span class="text-body-secondary">İletişim bilgileri henüz tanımlanmadı.</span>';
                        } else {
                            echo implode('<br><br>', $gorunen);
                        }
                        ?>
                    </p>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <form action="eposta.php" method="post" name="iletisim" id="iletisimForm">
                <?= csrf_alan() ?>
                <div class="mb-3">
                    <label for="exampleInputText1" class="form-label">İsim</label>
                    <input type="text" class="form-control" id="exampleInputText1" placeholder="Adınızı girin" name="kimden" required>
                </div>
                
                <div class="mb-3">
                    <label for="exampleInputEmail1" class="form-label">E-Posta adresi</label>
                    <input type="email" class="form-control" id="exampleInputEmail1" aria-describedby="emailHelp" placeholder="E-posta adresinizi girin" name="eposta" required>
                    <div id="emailHelp" class="form-text">E-posta adresiniz başkalarıyla paylaşılmayacaktır.</div>
                </div>
                
                <div class="mb-3">
                    <label for="exampleInputText2" class="form-label">Konu</label>
                    <input type="text" class="form-control" id="exampleInputText2" placeholder="Mesajınızın konusunu girin" name="konu" required>
                </div>
                
                <div class="mb-3">
                    <label for="exampleFormControlTextarea1" class="form-label">Mesaj</label>
                    <textarea class="form-control" id="exampleFormControlTextarea1" rows="5" placeholder="Bize iletmek istediğiniz mesajı girin" name="mesaj" required></textarea>
                </div>
                
                <button type="submit" class="btn btn-success btn-lg">
                    <svg class="ik me-2" aria-hidden="true"><use href="#lk-send"></use></svg>Gönder
                </button>
                
                <button type="reset" class="btn btn-outline-secondary btn-lg ms-2">
                    <svg class="ik me-2" aria-hidden="true"><use href="#lk-rotate-ccw"></use></svg>Temizle
                </button>
            </form>
        </div>
    </div>
</div>

<?php include "footer.php"; ?>
