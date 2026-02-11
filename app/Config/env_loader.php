<?php

function loadEnvFile(string $path): void {
    if (!file_exists($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);

        // Ignora comentários e linhas vazias
        if ($line === '' || $line[0] === '#') {
            continue;
        }

        // Divide em chave e valor (suporta '=' no valor)
        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $key = trim($parts[0]);
        $rawValue = trim($parts[1]);

        // Remove aspas externas (simples ou duplas)
        if ((substr($rawValue, 0, 1) === '"' && substr($rawValue, -1) === '"') ||
            (substr($rawValue, 0, 1) === "'" && substr($rawValue, -1) === "'")) {
            $value = substr($rawValue, 1, -1);
        } else {
            $value = $rawValue;
        }

        // Define a variável se ainda não estiver definida
        if (getenv($key) === false) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}