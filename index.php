<?php
$theme_dir = get_stylesheet_directory();
$pages_dir = $theme_dir . '/pages/';

// Pobierz ścieżkę URL bez query stringa
$uri  = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
$path = parse_url($uri, PHP_URL_PATH);

// Wyodrębnij wszystkie segmenty ścieżki
$segments = array_values(array_filter(explode('/', trim($path, '/')), 'strlen'));

// Prosta sanityzacja każdego segmentu
$clean_segments = array_map(function($segment) {
    return strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '', $segment));
}, $segments);

$possible_files = [];

if (!empty($clean_segments)) {
    // 1. Z podfolderami (np. huragan/skladki.php)
    $possible_files[] = implode('/', $clean_segments);
    
    // 2. Łączone myślnikiem (np. huragan-skladki.php)
    $possible_files[] = implode('-', $clean_segments);
    
    // 3. Fallback do pierwszego segmentu (np. huragan.php)
    $possible_files[] = $clean_segments[0];
} else {
    $possible_files[] = 'index';
}

// Usuwamy duplikaty w wariantach
$possible_files = array_unique($possible_files);

// Priorytet: dla każdego wariantu najpierw sprawdzamy .php, potem .html
foreach ($possible_files as $file_base) {
    $target_php = $pages_dir . $file_base . '.php';
    $target_html = $pages_dir . $file_base . '.html';

    if (file_exists($target_php)) {
        include($target_php);
        exit;
    } elseif (file_exists($target_html)) {
        header('Content-Type: text/html; charset=UTF-8');
        readfile($target_html);
        exit;
    }
}

// Brak statycznego pliku – renderuj standardowy szablon WordPressa
$has_header = locate_template(array('header.php'), false, false);
$has_footer = locate_template(array('footer.php'), false, false);

if ($has_header) {
    get_header();
} else {
    ?>
    <!DOCTYPE html>
    <html <?php language_attributes(); ?>>
    <head>
        <meta charset="<?php bloginfo('charset'); ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <?php wp_head(); ?>
    </head>
    <body <?php body_class(); ?>>
    <?php
}
?>

<main class="site-main" role="main">
    <?php if (have_posts()) : ?>
        <?php while (have_posts()) : the_post(); ?>
            <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
                <header class="entry-header">
                    <?php the_title('<h1 class="entry-title">', '</h1>'); ?>
                </header>
                <div class="entry-content">
                    <?php the_content(); ?>
                </div>
            </article>
        <?php endwhile; ?>
    <?php else : ?>
        <p><?php echo esc_html__('Brak treści do wyświetlenia.', 'blank-theme'); ?></p>
    <?php endif; ?>
</main>

<?php
if ($has_footer) {
    get_footer();
} else {
    wp_footer();
    ?>
    </body>
    </html>
    <?php
}