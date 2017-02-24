<?php
require_once "./senscritiqueGet.php";
require_once "./senscritiquePost.php";
require_once "./simple_html_dom.php";

/**
 * @param $str
 * @return string
 */
function iconvUtf8( $str ) {
    return iconv( "Windows-1252", "UTF-8", $str );
}

/**
 * @param $path
 * @return int
 */
function parseCSV($path) {
    global $stats;
    global $params;

    if (!file_exists($path)) {
        $stats->fileErr = 'no CSV file imported';

        return 0;
    }
    if(!($fHandle = fopen($path, 'r'))) {
        $stats->fileErr = 'error opening CSV file : '.$path;

        return 0;
    }

    $curr = 0;
    $max = $params->start + $params->nbr;
    while (($row = fgetcsv($fHandle)) && $curr < $max) {
        if($curr >= $params->start) {
            $row = array_map( "iconvUtf8", $row );
            parseMovie($curr, $row[5], $row[11], $row[8], $row[2]);
        }
        $curr++;
    }

    fclose($fHandle);

    return $curr;
}

/**
 * @param $path
 * @return int
 */
function getCSVnbLines($path) {
    if (!file_exists($path) || !($fHandle = fopen($path, 'r'))) {
        return 0;
    }
    $nbLines = 0;
    while (($row = fgetcsv($fHandle))) {
        $nbLines++;
    }

    fclose($fHandle);

    return $nbLines;
}

/**
 * @param $resultsPage
 * @param $title
 * @param $year
 * @param $rating
 * @return object
 */
function getMovie($resultsPage, $title, $year, $rating) {
    $items = str_get_html($resultsPage)->find('li.esco-item');
    foreach($items as $item) {
        $thisTitle = html_entity_decode($item->find('a.elco-anchor',0)->plaintext, ENT_QUOTES);
        $thisOTitle = isset($item->find('p.elco-original-title',0)->plaintext) ? html_entity_decode($item->find('p.elco-original-title',0)->plaintext, ENT_QUOTES) : '';
        $thisYear = substr(trim($item->find('span.elco-date',0)->plaintext), 1, -1);

        $isCloseYear = abs(intval($thisYear) - intval($year)) < 2;
        if(!$isCloseYear) continue;

        return (object) array(
            'title' => $title,
            'year' => $year,
            'rating' => $rating,
            'exactmatch' => $thisTitle == $title || $thisOTitle == $title,
            'id' => $item->getAttribute('data-sc-product-id'),
            'foundtitle' => $thisTitle,
            'foundotitle' => $thisOTitle,
            'foundyear' => $thisYear,
            'path' => $item->find('a', 0)->href,
            'img' => $item->find('img', 0),
        );
    }
}

/**
 * @param $sc
 * @param $movieId
 * @return mixed
 */
function getCurrentRating($sc, $movieId) {
    $getUserActions = senscritiquePost($sc, $sc->actionsPath, "productIdCollections%5B%5D=$movieId");
    $getUserActionsArray = json_decode($getUserActions['data']);
    return $getUserActionsArray->json->collectionsRatings->$movieId->rating;
}

/**
 * @param $sc
 * @param $movieId
 * @param $rating
 * @return mixed
 */
function postRating($sc, $movieId, $rating) {
    $return = senscritiquePost($sc, "/collections/rate/$movieId.json", array('rating'=>$rating));
    if($return['httpCode'] === 200 && $return['data'] !== ''){
        $thisReturn = json_decode($return['data']);
        return $thisReturn->json->success;
    }

    return false;
}

/**
 * @param $pos
 * @param $title
 * @param $year
 * @param $rating
 * @param $date
 * @return mixed
 */
function parseMovie($pos, $title, $year, $rating, $date) {
    global $params;
    global $stats;
    global $sc;

    $scFindMovieURI = $sc->root."/recherche?query=".urlencode(strtolower($title))."&filter=movies";
    $scFindResults = senscritiqueGet($sc, $scFindMovieURI);

    $movie = getMovie($scFindResults['data'], $title, $year, $rating);

    if (!$movie || !$movie->id)
        return $stats->notFound[] = $title;

    $movie->pos = $pos;
    $movie->date = date("Y-m-d", strtotime($date));

    if(!$params->over)
        $movie->currentRating = getCurrentRating($sc, $movie->id);

    if(!$params->over && $movie->currentRating)
        return $stats->skipped[] = $movie;

    $success = postRating($sc, $movie->id, $rating);
    if($success) {
        senscritiquePost($sc, '/collections/datedone/'.$movie->id.'.json', array(
            'date_done' => $movie->date,
            'save' => true));
        $stats->updated[] = $movie;

        return true;
    }

    $stats->failed[] = $title;

    return true;
}
