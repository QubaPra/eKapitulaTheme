<?php
/**
 * Dynamiczne proxy dla strony 5 KDH Piorun z GitHub Pages
 * Pobiera zawartość z https://5kdhpiorun.github.io/5kdhpiorun/
 * Cache'uje na 1 godzinę dla wydajności
 */

// URL do GitHub Pages
$github_pages_url = 'https://5kdhpiorun.github.io/5kdhpiorun/';

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
        <p>Nie udało się pobrać zawartości strony 5 KDH Piorun z GitHub Pages.</p>
        <p>Możesz odwiedzić stronę bezpośrednio pod adresem:</p>
        <p><a href="https://5kdhpiorun.github.io/5kdhpiorun/" target="_blank">5kdhpiorun.github.io/5kdhpiorun</a></p>
    </div>
</body>
</html>';
        }
    }
}

// Opcjonalnie: popraw ścieżki do zasobów, aby wskazywały na GitHub Pages
// To zapewni, że obrazy, CSS inline już są w kodzie, ale obrazy muszą mieć pełne URL
$content = str_replace(
    array(
        'src="logo.png"',
        'src="tlo.jpg"',
        'src="foto1.jpg"',
        'src="foto2.jpg"',
        'src="foto3.jpg"',
        'src="foto4.jpg"',
        'src="foto5.jpg"',
        'src="foto6.jpg"',
        'src="druzynowy.jpg"'
    ),
    array(
        'src="https://5kdhpiorun.github.io/5kdhpiorun/logo.png"',
        'src="https://5kdhpiorun.github.io/5kdhpiorun/tlo.jpg"',
        'src="https://5kdhpiorun.github.io/5kdhpiorun/foto1.jpg"',
        'src="https://5kdhpiorun.github.io/5kdhpiorun/foto2.jpg"',
        'src="https://5kdhpiorun.github.io/5kdhpiorun/foto3.jpg"',
        'src="https://5kdhpiorun.github.io/5kdhpiorun/foto4.jpg"',
        'src="https://5kdhpiorun.github.io/5kdhpiorun/foto5.jpg"',
        'src="https://5kdhpiorun.github.io/5kdhpiorun/foto6.jpg"',
        'src="https://5kdhpiorun.github.io/5kdhpiorun/druzynowy.jpg"'
    ),
    $content
);

// Popraw również tło w CSS
$content = str_replace(
    "--hero-img: url('tlo.jpg');",
    "--hero-img: url('https://5kdhpiorun.github.io/5kdhpiorun/tlo.jpg');",
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
