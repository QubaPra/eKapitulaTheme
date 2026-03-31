<?php
// Parametry GitHub (dostosuj do swojego repozytorium)
$github_owner = '5kdhpiorun';
$github_repo = '5kdhpiorun';
$github_branch = 'main'; // branch zawierający pliki
$github_index_path = 'index.html'; // ścieżka do index.html w repo

// URL do surowej zawartości z GitHub Raw
$github_raw_url = "https://raw.githubusercontent.com/{$github_owner}/{$github_repo}/{$github_branch}/{$github_index_path}";

// Ścieżka do cache
$cache_dir = get_stylesheet_directory() . '/cache/';
$cache_file = $cache_dir . '5kdh_cache.html';
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
    $response = wp_remote_get($github_raw_url, array(
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
    <title>5 KDH Piorun - Strona Niedostępna</title>
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
        <p>Nie udało się pobrać zawartości strony 5 KDH Piorun z GitHub Raw API.</p>
        <p>Możesz odwiedzić stronę bezpośrednio pod adresem:</p>
        <p><a href="https://github.com/5kdhpiorun/5kdhpiorun" target="_blank">github.com/5kdhpiorun/5kdhpiorun</a></p>
    </div>
</body>
</html>';
        }
    }
}

// Napraw wszystkie względne ścieżki na bezwzględne URL GitHub Raw
$github_raw_base = "https://raw.githubusercontent.com/{$github_owner}/{$github_repo}/{$github_branch}/";

// Zamienia src="ścieżka/plik.ext" na src="https://raw.githubusercontent.com/...ścieżka/plik.ext"
// Działa dla wszystkich plików, niezależnie od nazwy czy lokalizacji
$content = preg_replace_callback(
    '/src="(?!https:\/\/|data:)([^"]+)"/',
    function($matches) use ($github_raw_base) {
        // Usuń ./ jeśli jest na początku ścieżki
        $path = ltrim($matches[1], './');
        return 'src="' . $github_raw_base . $path . '"';
    },
    $content
);

// Napraw również tła CSS (background-image, --hero-img itp.)
$content = preg_replace_callback(
    "/url\(['\"]?(?!https:\/\/|data:)([^'\")\s]+)['\"]?\)/",
    function($matches) use ($github_raw_base) {
        // Usuń ./ jeśli jest na początku ścieżki
        $path = ltrim($matches[1], './');
        return "url('" . $github_raw_base . $path . "')";
    },
    $content
);

// Napraw linki href (działaj ostrożnie — sprawdź czy to fragment)
$content = preg_replace_callback(
    '/href="(?!#|https:\/\/|\/|mailto:|tel:)([^"]+)"/',
    function($matches) use ($github_raw_base) {
        $path = ltrim($matches[1], './');
        // Sprawdzenie czy to plik (.html, .php, itp.) czy katalog
        if (strpos($path, '.') !== false || !str_ends_with($path, '/')) {
            return 'href="' . $github_raw_base . $path . '"';
        }
        return $matches[0]; // Zostaw katalogi bez zmian
    },
    $content
);

// Wyślij nagłówek i treść
header('Content-Type: text/html; charset=UTF-8');

// Dodaj komentarz informacyjny (opcjonalnie)
if ($use_cache) {
    echo "<!-- Wczytano z cache: " . date('Y-m-d H:i:s', filemtime($cache_file)) . " -->\n";
} else {
    echo "<!-- Pobrano świeżo z GitHub Raw API: " . date('Y-m-d H:i:s') . " -->\n";
}

echo $content;
exit;
