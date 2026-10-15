/* Urun detay galeri davranisi (urundetay.php inline <script>'ten tasindi) */
document.addEventListener('DOMContentLoaded', function () {
    const thumbnails = document.querySelectorAll('.ek-resim-thumbnail');
    const anaResim = document.getElementById('anaResim');

    /* width/height nitelikleri sunucu tarafinda ILK gorselin boyuyla basildi
       (CLS onlemi). Farkli boyutta bir gorsele gecince kutu eski olcude
       kalmasin: yuklenme bittiginde nitelikleri gorselin gercek boyuna
       esitle. */
    if (anaResim) {
        anaResim.addEventListener('load', function () {
            if (anaResim.naturalWidth > 0) {
                anaResim.setAttribute('width', anaResim.naturalWidth);
                anaResim.setAttribute('height', anaResim.naturalHeight);
            }
        });
    }

    thumbnails.forEach(thumbnail => {
        thumbnail.addEventListener('click', function () {
            thumbnails.forEach(t => t.classList.remove('active', 'border-success'));

            this.classList.add('active', 'border-success');

            const yeniResim = this.getAttribute('data-resim');
            if (anaResim) {
                anaResim.src = yeniResim;
                anaResim.setAttribute('data-bs-src', yeniResim);
            }
        });
    });

    const resimModal = document.getElementById('resimModal');
    const modalResim = document.getElementById('modalResim');

    if (resimModal && modalResim) {
        resimModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            if (!button) {
                return;
            }
            const resimSrc = button.getAttribute('data-bs-src');
            const resimAlt = button.getAttribute('alt');

            modalResim.src = resimSrc;
            modalResim.alt = resimAlt;
        });
    }
});
