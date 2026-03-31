<?php
/**
 * Wspólny skrypt do pobierania i parsowania stron z GitHuba.
 */

function load_github_page($owner, $repo, $branch = 'main', $index_path = 'index.html', $title = 'Strona Niedostępna') {
    date_default_timezone_set('Europe/Warsaw');

    $raw_base_url = "https://raw.githubusercontent.com/{$owner}/{$repo}/{$branch}/";
    $raw_url = $raw_base_url . $index_path;
    
    $cache_dir = get_stylesheet_directory() . '/cache/';
    $cache_file = $cache_dir . "{$repo}_cache.html";
    
    if (!file_exists($cache_dir)) {
        mkdir($cache_dir, 0755, true);
    }

    $content = false;
    $use_cache = false;

    // Sprawdź cache omijając gdy przekazano ?nocache w URL
    if (file_exists($cache_file) && !isset($_GET['nocache'])) {
        if ((time() - filemtime($cache_file)) < 3600) { // 1h cache
            $content = file_get_contents($cache_file);
            $use_cache = true;
        }
    }

    if ($content === false) {
        $response = wp_remote_get($raw_url, ['timeout' => 15, 'sslverify' => true]);
        
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $content = wp_remote_retrieve_body($response);
            file_put_contents($cache_file, $content);
        } else {
            if (file_exists($cache_file)) {
                $content = file_get_contents($cache_file);
                $use_cache = true;
            } else {
                echo generate_fallback_html($title, "https://github.com/{$owner}/{$repo}");
                exit;
            }
        }
    }

    // --- Parser HTML ---
    
    // Zablokuj Cloudflare Rocket Loader dla skryptów
    $content = preg_replace('/<script\b(?![^>]*\bdata-cfasync=)/i', '<script data-cfasync="false"', $content);
    $content = str_replace('type="text/javascript"', '', $content); // Usunięcie starego fixa, Rocket Loader obejdzie się sam
    
    // Pomocnicza funkcja do budowania ścieżek
    $fix_path = function($path) use ($raw_base_url) {
        $path = ltrim($path, './');
        return (strpos($path, 'http') === 0 || strpos($path, 'data:') === 0 || strpos($path, '//') === 0) 
            ? $path 
            : $raw_base_url . $path;
    };

    // Src
    $content = preg_replace_callback('/src="([^"]+)"/i', function($m) use ($fix_path) {
        return 'src="' . $fix_path($m[1]) . '"';
    }, $content);
    
    // Href (tylko wewnątrz zasobów, np. css. Pomija #, mailto, tel)
    $content = preg_replace_callback('/href="(?!#|https?:\/\/|\/\/|mailto:|tel:|javascript:|data:|ftp:\/\/)([^"]+)"/i', 
        function($m) use ($fix_path) {
            return 'href="' . $fix_path($m[1]) . '"';
        }, $content);

    // CSS url()
    $content = preg_replace_callback("/url\(['\"]?([^'\")\s]+)['\"]?\)/i", 
        function($m) use ($fix_path) {
            return "url('" . $fix_path($m[1]) . "')";
        }, $content);

    // Srcset w obrazkach
    $content = preg_replace_callback('/srcset="([^"]*)"/i', function($matches) use ($fix_path) {
        $parts = array_map(function($part) use ($fix_path) {
            $m = explode(' ', trim($part), 2);
            return $fix_path($m[0]) . (isset($m[1]) ? " {$m[1]}" : "");
        }, explode(',', $matches[1]));
        return 'srcset="' . implode(', ', $parts) . '"';
    }, $content);

    // Dynamiczne atrybuty (np. z galerii zdjecia/kolonia.jpg) wpisane w data-images
    $content = preg_replace_callback('/data-images="([^"]*)"/i', function($matches) use ($fix_path) {
        $parts = array_map(function($part) use ($fix_path) {
            return $fix_path(trim($part));
        }, explode(',', $matches[1]));
        return 'data-images="' . implode(',', $parts) . '"';
    }, $content);


    // --- Renderowanie strony ---
    header('Content-Type: text/html; charset=UTF-8');
    
    $cache_time = date('Y-m-d H:i:s', $use_cache ? filemtime($cache_file) : time());
    $cache_msg = $use_cache ? "Wczytano z cache" : "Pobrano świeżo z GitHub";
    echo "<!-- {$cache_msg}: {$cache_time} (Czas PL) -->\n";
    
    echo $content;
    exit;
}

function generate_fallback_html($title, $repo_link) {
    return <<<HTML
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
    <style>
        body { font-family: sans-serif; background: #121212; color: #e0e0e0; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .box { background: #1e1e1e; padding: 40px; border-radius: 10px; border-top: 4px solid #9e1b1b; text-align: center; }
        a { color: #9e1b1b; text-decoration: none; font-weight: bold; } a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="box">
        <h1 style="color: #9e1b1b;">⚠️ Strona Niedostępna</h1>
        <p>Nie udało się pobrać zawartości domyślnej.</p>
        <p><a href="{$repo_link}" target="_blank">Zobacz źródło GitHub</a></p>
    </div>
</body>
</html>
HTML;
}
