<?php

require_once "./src/simple_html_dom.php";

require_once "./src/senscritiquePost.php";
require_once "./src/senscritiqueGet.php";

// If you get a Fatal error: Maximum execution time, feel free to uncomment & change this value (0 = no limit)
//ini_set('max_execution_time', 0);

// users's parameters
$imdbRatings = "./web/exempleFile.csv"; // the filePath of the imdb generated file
$scEmail = "YOUR_EMAIL_ADDRESS"; // your senscritique email
$scPwd = "YOUR_PASSWORD"; // your senscritique password

// generic vars
$scRoot = "http://www.senscritique.com";
$cookiePath = "./this_cookie";
$scCredentials = array('email'=>$scEmail,'pass'=>$scPwd);
$scConnectURI = $scRoot."/sc2/auth/login.json";

// stats array
$statsArray = array('updated'=>0,'failed'=>0,'nbLines'=>0);

// connexion to senscritique
$scConnect = senscritiquePost($scConnectURI, $scCredentials, $scRoot, $cookiePath);

if($scConnect['httpCode'] === 200 && $scConnect['data'] !== ''){
    $connectReturn = json_decode($scConnect['data']);
    if($connectReturn->json->success === true){
        // if connexion is sucessful, parsing the csv file
        if (file_exists($imdbRatings)) {
            if($fHandle = fopen($imdbRatings, 'r')){
                $statsArray['nbLines'] = 1;
                while ($row = fgetcsv($fHandle)) {
                    if($statsArray['nbLines']>1){
                        $title = $row[5];
                        $author = $row[7];
                        $year = $row[11];
                        $rating = $row[8];

                        $movieId = null;

                        // finding the sensecritique movie based on its title
                        $scFindMovieURI = "$scRoot/recherche?query=".urlencode($title)."&filter=movies";

                        $thisMovie = senscritiqueGet($scFindMovieURI, $scRoot, $cookiePath);
                        $html = str_replace('\n\r','',$thisMovie['data']);

                        // we parse the html return to find the original title, the author and its sensecritique ID
                        $movies = str_get_html($html)->find('li.esco-item');
                        foreach($movies as $thisMovie){
                            $thisMovieRaw = str_get_html($thisMovie->outerText());

                            $thisMovieId = $thisMovie->getAttribute('data-sc-product-id');

                            $thisOriginalTitle = (isset($thisMovieRaw->find('p.elco-original-title',0)->plaintext)?$thisMovieRaw->find('p.elco-original-title',0)->plaintext:null);
                            $thisOriginalAuthor = (isset($thisMovieRaw->find('a.elco-baseline-a',0)->plaintext)?$thisMovieRaw->find('a.elco-baseline-a',0)->plaintext:null);
                            // if the title and author match, we suppose this is our movie
                            if ($thisOriginalTitle === $title && $thisOriginalAuthor === $author) {
                                $movieId = $thisMovieId;
                            }
                        }

                        // if the movie has been found, we update its rating
                        if ($movieId !== null) {
                            $scRateMovieURI = "$scRoot/collections/rate/$movieId.json";
                            $return = senscritiquePost($scRateMovieURI, array('rating'=>$rating), $scRoot, $cookiePath);
                            if($return['httpCode'] === 200 && $return['data'] !== ''){
                                $thisReturn = json_decode($return['data']);
                                if($thisReturn->json->success){
                                    $statsArray['updated']++;
                                }
                            } else {
                                // echo "Error update rating movie $movieId<br/>";
                            }
                        }
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
        echo "connection to senscritique failed : wrong credentials";
    }
} else {
    echo "connection to senscritique failed : ".$scConnect['httpCode'];
}

// getting of the cookie after script execution
if (file_exists($cookiePath)) {
    unlink($cookiePath);
}

$statsArray['failed'] = $statsArray['nbLines']-$statsArray['updated'];
var_dump($statsArray);
