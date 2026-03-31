<?php

// Parametry GitHub (dostosuj do swojego repozytorium)
$github_owner = 'Antynnon';
$github_repo = '40_kgz';
$github_branch = 'main'; // branch zawierający pliki
$github_index_path = 'index.html'; // ścieżka do index.html w repo

// URL do surowej zawartości z GitHub Raw
$github_raw_url = "https://raw.githubusercontent.com/{$github_owner}/{$github_repo}/{$github_branch}/{$github_index_path}";

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

// Napraw wszystkie względne ścieżki na bezwzględne URL GitHub Raw
$github_raw_base = "https://raw.githubusercontent.com/{$github_owner}/{$github_repo}/{$github_branch}/";

// Funkcja pomocnicza do czyszczenia ścieżek
function normalize_path($path) {
    // Usuń ./ na początku jeśli jest
    if (strpos($path, './') === 0) {
        $path = substr($path, 2);
    }
    return $path;
}

// Napraw atrybuty src (obrazki, skrypty itp.)
$content = preg_replace_callback(
    '/src="(?!https:\/\/|data:|\/\/)([^"]+)"/',
    function($matches) use ($github_raw_base) {
        $path = normalize_path($matches[1]);
        return 'src="' . $github_raw_base . $path . '"';
    },
    $content
);

// Napraw atrybuty srcset (responsywne obrazki)
$content = preg_replace_callback(
    '/srcset="([^"]*)"/',
    function($matches) use ($github_raw_base) {
        $srcset = $matches[1];
        // Obsłuż format: "image.png 1x, image2.png 2x"
        return 'srcset="' . preg_replace_callback(
            '/(?!https:\/\/|data:|\/\/)([^\s,]+)(\s+[0-9.]+[wx])?,?/i',
            function($m) use ($github_raw_base) {
                if (strpos($m[1], 'https://') === 0 || strpos($m[1], 'data:') === 0) {
                    return $m[0];
                }
                $path = normalize_path($m[1]);
                return $github_raw_base . $path . ($m[2] ?? '');
            },
            $srcset
        ) . '"';
    },
    $content
);

// Napraw tła CSS (background-image, background, --hero-img itp.)
$content = preg_replace_callback(
    "/url\(['\"]?(?!https:\/\/|data:|\/\/)([^'\")\s]+)['\"]?\)/i",
    function($matches) use ($github_raw_base) {
        $path = normalize_path($matches[1]);
        // Zwróć property CSS - jeśli ścieżka zawiera spację, użyj cudzysłowu
        if (strpos($path, ' ') !== false) {
            return "url('" . $github_raw_base . $path . "')";
        }
        return 'url(' . $github_raw_base . $path . ')';
    },
    $content
);

// Napraw linki href (działaj ostrożnie — sprawdź czy to nie są fragmenty ani linki zewnętrzne)
$content = preg_replace_callback(
    '/href="(?![\#|https:\/\/|\/|mailto:|tel:|javascript:|data:ftp:\/\/])([^"]+)"/',
    function($matches) use ($github_raw_base) {
        $path = normalize_path($matches[1]);
        // Nie podmieniaj czystych fragmentów typu #sekcja
        if ($path === '' || strpos($path, '#') === 0) {
            return 'href="' . $matches[1] . '"';
        }
        // Podmieniaj na .html/.json/.xml itd. ale nie zwykłe katalogi
        if (preg_match('/\.\w+$/', $path) || strpos($path, '.') !== false) {
            return 'href="' . $github_raw_base . $path . '"';
        }
        return 'href="' . $matches[1] . '"';
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
