<?php

require_once "./src/simple_html_dom.php";

require_once "./src/senscritiquePost.php";
require_once "./src/senscritiqueGet.php";

// If you get a Fatal error: Maximum execution time, feel free to uncomment & change this value (0 = no limit)
//ini_set('max_execution_time', 0);

// users's parameters
$imdbRatings = "./web/exempleFile.csv"; // the filePath of the IMDB generated file
$scEmail = "YOUR_EMAIL_ADDRESS"; // your SC email
$scPwd = "YOUR_PASSWORD"; // your SC password
$forceUpdate = false; // if true, will update even if movie have already been rated

// generic vars
$scRoot = "https://www.senscritique.com";
$cookiePath = "./this_cookie";
$scCredentials = array('email'=>$scEmail,'pass'=>$scPwd);
$scConnectURI = $scRoot."/sc2/auth/login.json";

// stats array
$statsArray = array(
    'updated'=>array(),
    'failed'=>0,
    'skipped'=>array(),
    'notFound'=>array(),
    'nbLines'=>0,
    'lines'=>array(),
);

// connexion to SC
$scConnect = senscritiquePost($scConnectURI, $scCredentials, $scRoot, $cookiePath);

if($scConnect['httpCode'] === 200 && $scConnect['data'] !== ''){
    $connectReturn = json_decode($scConnect['data']);
    if($connectReturn->json->success === true){
        // if connexion is successful, parsing the csv file
        if (file_exists($imdbRatings)) {
            if($fHandle = fopen($imdbRatings, 'r')){
                $statsArray['nbLines'] = 1;
                while ($row = fgetcsv($fHandle)) {
                    if($statsArray['nbLines']>1){
                        $title = $row[5];
                        $year = $row[11];
                        $rating = $row[8];

                        $movieId = null;

                        // finding the SC movie based on its title
                        $scFindMovieURI = "$scRoot/recherche?query=".urlencode($title)."&filter=movies";

                        $thisMovie = senscritiqueGet($scFindMovieURI, $scRoot, $cookiePath);
                        $html = str_replace('\n\r','',$thisMovie['data']);

                        // we parse the html return to find the original title, the author and its sensecritique ID
                        $movies = str_get_html($html)->find('li.esco-item');
                        foreach($movies as $thisMovie){
                            $thisMovieRaw = str_get_html($thisMovie->outerText());

                            $thisMovieId = $thisMovie->getAttribute('data-sc-product-id');

                            $thisMainTitle = (isset($thisMovieRaw->find('a.elco-anchor',0)->plaintext)?$thisMovieRaw->find('a.elco-anchor',0)->plaintext:null);
                            $thisOriginalTitle = (isset($thisMovieRaw->find('p.elco-original-title',0)->plaintext)?$thisMovieRaw->find('p.elco-original-title',0)->plaintext:null);
                            $thisYear = (isset($thisMovieRaw->find('span.elco-date',0)->plaintext)?$thisMovieRaw->find('span.elco-date',0)->plaintext:null);

                            // if the title and author match, we suppose this is our movie
                            if ((html_entity_decode($thisOriginalTitle, ENT_QUOTES) === $title || html_entity_decode($thisMainTitle, ENT_QUOTES) === $title) && str_replace(array('(', ')', ' '), '', $thisYear) === $year) {
                                $movieId = $thisMovieId;
                            }
                        }

                        // if the movie has been found, we update its rating
                        if ($movieId !== null) {
                            $getUserActions = senscritiquePost("$scRoot/sc2/userActions/index.json", "productIdCollections%5B%5D=$movieId", $scRoot, $cookiePath);
                            $getUserActionsArray = json_decode($getUserActions['data']);

                            if(!isset($getUserActionsArray->json->collectionsRatings->$movieId->rating) || ($forceUpdate === true && $getUserActionsArray->json->collectionsRatings->$movieId->rating != $rating)){
                                $scRateMovieURI = "$scRoot/collections/rate/$movieId.json";
                                $return = senscritiquePost($scRateMovieURI, array('rating'=>$rating), $scRoot, $cookiePath);
                                if($return['httpCode'] === 200 && $return['data'] !== ''){
                                    $thisReturn = json_decode($return['data']);
                                    if($thisReturn->json->success){
                                        $statsArray['updated'][] = $title;
                                    }
                                } else {
                                    // echo "Error update rating movie $movieId<br/>";
                                }
                            } else {
                                $statsArray['skipped'][] = $title;
                            }
                        } else {
                            $statsArray['notFound'][] = $title;
                        }
                        $statsArray['lines'][] = $title;
                    }
                    $statsArray['nbLines']++;
                }
            } else {
                echo "error opening file";
            }
        } else {
            echo "file doesn't exists";
        }
    } else {
        echo "connection to SC failed : wrong credentials";
    }
} else {
    echo "connection to SC failed : ".$scConnect['httpCode'];
}

// removing the cookie after script execution
if (file_exists($cookiePath)) {
    unlink($cookiePath);
}

$statsArray['failed'] = array_diff($statsArray['lines'], $statsArray['updated'], $statsArray['skipped'], $statsArray['notFound']);
echo "<h2>Out of a total of ".($statsArray['nbLines']-2)." titles, </h2>";
if (count($statsArray['updated']) > 0) {
    echo "<b>These titles where successfully updated</b><ul>";
    foreach ($statsArray['updated'] as $title) {
        echo "<li>$title</li>";
    }
    echo "</ul>";
}
if (count($statsArray['skipped']) > 0) {
    echo "<b>These titles where skipped because already rated</b><ul>";
    foreach ($statsArray['skipped'] as $title) {
        echo "<li>$title</li>";
    }
    echo "</ul>";
}
if (count($statsArray['notFound']) > 0) {
    echo "<b>These titles where not found</b><ul>";
    foreach ($statsArray['notFound'] as $title) {
        echo "<li>$title</li>";
    }
    echo "</ul>";
}
if (count($statsArray['failed']) > 0) {
    echo "<b>These titles failed for unknown reasons</b><ul>";
    foreach ($statsArray['failed'] as $title) {
        echo "<li>$title</li>";
    }
    echo "</ul>";
}
