<?php

// URL do GitHub Pages
$github_pages_url = 'https://github.com/Antynnon/40_kgz/';

// Ścieżka do cache
$cache_dir = get_stylesheet_directory() . '/cache/';
$cache_file = $cache_dir . '40kgz_cache.html';
$cache_duration = 3600; // 1 godzina w sekundach

// Utwórz katalog cache jeśli nie istnieje
if (!file_exists($cache_dir)) {
    mkdir($cache_dir, 0755, true);
}

$content = false;
$use_cache = false;

// Sprawdź czy istnieje cache i czy jest aktualny
if (file_exists($cache_file)) {
    $cache_age = time() - filemtime($cache_file);
    if ($cache_age < $cache_duration) {
        $content = file_get_contents($cache_file);
        $use_cache = true;
    }
}

// Jeśli brak cache lub jest przestarzały, pobierz ze źródła
if ($content === false) {
    // Użyj wp_remote_get dla lepszej kompatybilności z WordPressem
    $response = wp_remote_get($github_pages_url, array(
        'timeout' => 15,
        'sslverify' => true
    ));
    
    if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
        $content = wp_remote_retrieve_body($response);
        
        // Zapisz do cache
        file_put_contents($cache_file, $content);
    } else {
        // Jeśli nie udało się pobrać, spróbuj użyć starego cache
        if (file_exists($cache_file)) {
            $content = file_get_contents($cache_file);
            $use_cache = true;
        } else {
            // Ostateczna fallback strona błędu
            $content = '<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>40 KGZ Bractwo Smoczej Doliny - Strona Niedostępna</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #121212;
            color: #e0e0e0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .error-box {
            background: #1e1e1e;
            padding: 40px;
            border-radius: 10px;
            border-top: 4px solid #9e1b1b;
            text-align: center;
            max-width: 500px;
        }
        h1 { color: #9e1b1b; }
        a {
            color: #9e1b1b;
            text-decoration: none;
            font-weight: bold;
        }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="error-box">
        <h1>⚠️ Strona Tymczasowo Niedostępna</h1>
        <p>Nie udało się pobrać zawartości strony 40 KGZ Bractwo Smoczej Doliny z GitHub Pages.</p>
        <p>Możesz odwiedzić stronę bezpośrednio pod adresem:</p>
        <p><a href="https://github.com/Antynnon/40_kgz/" target="_blank">github.com/Antynnon/40_kgz</a></p>
    </div>
</body>
</html>';
        }
    }
}

// Dynamicznie napraw wszystkie ścieżki do obrazów
// Zastępuje src="..." z relatywnymi ścieżkami na pełne URL GitHub Raw
$github_raw_base = 'https://raw.githubusercontent.com/Antynnon/40_kgz/main/';

// Zamienia src="ścieżka/plik.ext" na src="https://raw.githubusercontent.com/...ścieżka/plik.ext"
// Działa dla wszystkich plików, niezależnie od nazwy czy lokalizacji
$content = preg_replace_callback(
    '/src="(?!https:\/\/|data:)([^"]+)"/',
    function($matches) use ($github_raw_base) {
        return 'src="' . $github_raw_base . $matches[1] . '"';
    },
    $content
);

// Napraw również tła CSS (background-image, --hero-img itp.)
$content = preg_replace_callback(
    "/url\(['\"]?(?!https:\/\/|data:)([^'\")\s]+)['\"]?\)/",
    function($matches) use ($github_raw_base) {
        return "url('" . $github_raw_base . $matches[1] . "')";
    },
    $content
);

// Wyślij nagłówek i treść
header('Content-Type: text/html; charset=UTF-8');

// Dodaj komentarz informacyjny (opcjonalnie)
if ($use_cache) {
    echo "<!-- Wczytano z cache: " . date('Y-m-d H:i:s', filemtime($cache_file)) . " -->\n";
} else {
    echo "<!-- Pobrano świeżo z GitHub Pages: " . date('Y-m-d H:i:s') . " -->\n";
}

echo $content;
exit;
