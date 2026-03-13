<?php
// app/Config/database.php

function getDbConnection() {
    static $pdo = null;

    if ($pdo === null) {
        $host = getenv('DB_HOST');
        $port = getenv('DB_PORT') ?: 3306;
        $dbname = getenv('DB_NAME');
        $user = getenv('DB_USER');
        $pass = getenv('DB_PASS');
        $ca = getenv('DB_CA_CERT'); // caminho para o arquivo ca-certificate.crt

        $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";

        try {
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ];

            if ($ca) {
                $options[PDO::MYSQL_ATTR_SSL_CA] = $ca;
            }

            $pdo = new PDO($dsn, $user, $pass, $options);

        } catch (PDOException $e) {
            die("Erro ao conectar ao banco de dados: " . $e->getMessage());
        }
    }

    return $pdo;
}