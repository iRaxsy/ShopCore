<?php
/**
 * Merkezi PDO baglantisi (singleton).
 * Tek baglanti = istek basina bir kez PDO; ERRMODE_EXCEPTION + utf8mb4.
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    // Kimlik bilgileri config/yerel.php'de tutulur (app_env); kaynakta sifre yok
    $host          = (string) app_env('DB_HOST', 'localhost');
    $vt_adi        = (string) app_env('DB_ADI', 'shopcore');
    $kullanici_adi = (string) app_env('DB_KULLANICI', 'root');
    $sifre         = (string) app_env('DB_SIFRE', '');

    try {
        // PDO::MYSQL_ATTR_INIT_COMMAND PHP 8.5'te deprecated (karsiligi 8.4'te
        // eklenen Pdo\Mysql::ATTR_INIT_COMMAND). Sabit dogrudan yazilirsa her
        // sayfa acilisinda "Deprecated" uyarisi cikar; APP_DEBUG acikken ekranı
        // bozar, kapaliyken logu doldurur. XAMPP'in eski surumleri de desteklendigi
        // icin sabit calisma zamaninda secilir.
        $init_command = class_exists('Pdo\\Mysql')
            ? Pdo\Mysql::ATTR_INIT_COMMAND
            : PDO::MYSQL_ATTR_INIT_COMMAND;

        $pdo = new PDO(
            "mysql:host={$host};dbname={$vt_adi};charset=utf8mb4",
            $kullanici_adi,
            $sifre,
            [
                $init_command           => 'SET NAMES utf8mb4',
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    } catch (PDOException $e) {
        // Gercek hatayi logla, kullanciya jenerik mesaj goster (bilgi sizintisi yok)
        error_log('DB baglanti hatasi: ' . $e->getMessage());
        http_response_code(500);
        die('Sistem su anda kullanilamiyor. Lutfen daha sonra tekrar deneyin.');
    }

    return $pdo;
}
