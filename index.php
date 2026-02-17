<?php
$theme_dir = get_stylesheet_directory();
$pages_dir = $theme_dir . '/pages/';

// Pobierz ścieżkę URL bez query stringa
$uri  = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
$path = parse_url($uri, PHP_URL_PATH);

// Wyodrębnij pierwszy segment ścieżki
$segments = array_values(array_filter(explode('/', trim($path, '/')), 'strlen'));
$first    = isset($segments[0]) ? $segments[0] : '';

// Prosta sanityzacja – tylko litery, cyfry, myślnik i podkreślenie
$first = preg_replace('/[^a-zA-Z0-9_-]/', '', $first);

// Konwersja na małe litery (case-insensitive routing)
$first = strtolower($first);

// Określ docelowy plik - najpierw sprawdź .php, potem .html
$target_php = $pages_dir . ($first !== '' ? $first . '.php' : 'index.php');
$target_html = $pages_dir . ($first !== '' ? $first . '.html' : 'index.html');

// Priorytet: najpierw PHP (dynamiczny), potem HTML (statyczny)
if (file_exists($target_php)) {
    include($target_php);
    exit;
} elseif (file_exists($target_html)) {
    header('Content-Type: text/html; charset=UTF-8');
    readfile($target_html);
    exit;
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