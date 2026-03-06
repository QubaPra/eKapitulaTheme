<?php
// Pobieramy najnowszą wersję index.html bezpośrednio z repo GitHub'a.
// branch `master` można zastąpić `master` w zależności jak nazywa się główna gałąź w repozytorium.
$url = 'https://raw.githack.com/QubaPra/huraganGra/master/index.html';

// Pobieranie tresci
$html_content = @file_get_contents($url);

if ($html_content !== false) {
    // Od razu po znaczniku <head> dodajemy `<base url>`, by wszystkie zasoby: css, js, json, obrazki
    // mogły poprawnie i automatycznie pobierać się z GitHuba, omijając WordPressa.
    // Dzięki temu nie ma problemów, że /styles.css będzie szukane w katalogu publicznym serwera krowodrza.zhr.pl.
    $base_tag = "\n    <base href=\"https://raw.githack.com/QubaPra/huraganGra/master/\">\n";
    $html_content = preg_replace('/<head>/i', '<head>' . $base_tag, $html_content, 1);
    
    // Wypuszczamy zawartość
    echo $html_content;
    exit;
} else {
    // Jeżeli cdn (lub github) chwilowo nie odpowiada, można zwrócić stosowny komunikat.
    echo "Nie udało się załadować danych aplikacji HuraganGra z repozytorium. Spróbuj odświeżyć stronę za chwilę.";
    exit;
}
?>