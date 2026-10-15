<footer class="admin-footer mt-auto">
    <div class="container text-center">
        <span>&copy; <?= date('Y') ?> ShopCore &middot; Yönetim Paneli</span>
    </div>
</footer>

<!-- Ortam verisi: AJAX CSRF header'i ve asset tabani icin (inline script'ler
     T21'de dosyaya tasinacak; defer betiklerden ONCE calismalari sart) -->
<script>window.CSRF = <?= json_encode(csrf_token()) ?>; window.BASE_URL = <?= json_encode(APP_BASE_URL) ?>;</script>
<?= varlik_js(APP_VARLIK_BOOTSTRAP_JS) ?>

<!-- SweetAlert2 (admin silme/onay kaliplari; CSS'i header.php'den geldi) -->
<?= varlik_js(APP_VARLIK_SWAL_JS) ?>
<?= varlik_js('content/js/admin.js') ?>

<!-- Acik/koyu tema toggle motoru -->
<?= varlik_js('content/js/tema.js') ?>

</body>
</html>
