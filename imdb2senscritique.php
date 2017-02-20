<?php
require_once "./src/simple_html_dom.php";
require_once "./src/senscritiquePost.php";
require_once "./src/senscritiqueGet.php";

$filepath = 'movies.csv';
$uploaded = $_FILES['file']['tmp_name'];
if($uploaded) move_uploaded_file($uploaded, $filepath);

$params = (object) array(
    'mail' => $_POST['mail'],
    'pswd' => $_POST['pswd'],
    'over' => $_POST['over'],
    'start' => $_POST['start'] > 0 ? $_POST['start'] : 1,
    'nbr' => $_POST['number'] > 0 ? $_POST['number'] : 100,
);
if($_POST['next']) $params->start += $params->nbr;

$sc = (object) array(
    'root' => 'https://www.senscritique.com',
    'cookiePath' => './this_cookie',
    'credentials' => array('email'=>$params->mail,'pass'=>$params->pswd),
    'loginPath' => '/sc2/auth/login.json',
    'actionsPath' => '/sc2/userActions/index.json',
);
$stats = (object) array(
    'updated'=>array(),
    'failed'=>0,
    'skipped'=>array(),
    'notFound'=>array(),
    'lines'=>array(),
);

function parseCSV($path) {
    global $params;
    if (!file_exists($path))
        die('CSV file doesn\'t exists : '+ $path);
    if(!($fHandle = fopen($path, 'r')))
        die('error opening CSV file : '+ $path);

    $curr = 0;
    $max = $params->start + $params->nbr;
    while (($row = fgetcsv($fHandle)) && $curr < $max) {
        if($curr >= $params->start)
            parseMovie($row[5], $row[11], $row[8]);
        $curr++;
    }
}
function getMovieID($resultsPage, $title, $year) {
    $html = str_replace('\n\r','',$resultsPage);
    $movies = str_get_html($html)->find('li.esco-item');
    foreach($movies as $movie){
        $movieRaw = str_get_html($movie->outerText());

        $id = $movie->getAttribute('data-sc-product-id');

        $thisTitle = html_entity_decode($movieRaw->find('a.elco-anchor',0)->plaintext, ENT_QUOTES);
        $originalTitle = html_entity_decode($movieRaw->find('p.elco-original-title',0)->plaintext, ENT_QUOTES);
        $thisYear = str_replace(array('(',')',' '), '', $movieRaw->find('span.elco-date',0)->plaintext);
        $isCloseYear = abs(intval($thisYear) - intval($year)) < 2;

        if (($originalTitle == $title || $thisTitle == $title) && $isCloseYear) {
            return $id;
        }
    }
}
function getCurrentRating($sc, $movieId) {
    $getUserActions = senscritiquePost($sc->root.$sc->actionsPath, "productIdCollections%5B%5D=$movieId", $sc->root, $sc->cookiePath);
    $getUserActionsArray = json_decode($getUserActions['data']);
    return $getUserActionsArray->json->collectionsRatings->$movieId->rating;
}
function postRating($sc, $movieId, $rating) {
    $scRateMovieURI = $sc->root."/collections/rate/$movieId.json";
    $return = senscritiquePost($scRateMovieURI, array('rating'=>$rating), $sc->root, $sc->cookiePath);
    if($return['httpCode'] === 200 && $return['data'] !== ''){
        $thisReturn = json_decode($return['data']);
        return $thisReturn->json->success;
    }
}

function parseMovie($title, $year, $rating) {
    global $params;
    global $stats;
    global $sc;

    $stats->lines[] = $title;

    $scFindMovieURI = $sc->root."/recherche?query=".urlencode($title)."&filter=movies";
    $scFindResults = senscritiqueGet($scFindMovieURI, $sc->root, $sc->cookiePath);

    $movieId = getMovieID($scFindResults['data'], $title, $year);

    if (!$movieId)
        return $stats->notFound[] = $title;

    if(!$params->over)
        $currRating = getCurrentRating($sc, $movieId);

    if(!$params->over && $currRating == $rating)
        return $stats->skipped[] = $title;

    $success = postRating($sc, $movieId, $rating);
    if($success) $stats->updated[] = $title;
}


if($params->mail) {
    $scConnect = senscritiquePost($sc->root.$sc->loginPath, $sc->credentials,$sc->root,$sc->cookiePath);
    if($scConnect['httpCode'] === 200 && $scConnect['data'] !== ''){
        $connectReturn = json_decode($scConnect['data']);
        if($connectReturn->json->success === true)
            parseCSV($filepath);
        else die('connection to SC failed : wrong credentials');
    }
    else die('connection to SC failed : '.$scConnect['httpCode']);

    // removing the cookie after script execution
    if (file_exists($sc->cookiePath)) {
        unlink($sc->cookiePath);
    }
    $stats->failed = array_diff($stats->lines, $stats->updated, $stats->skipped, $stats->notFound);
}
?>
<!DOCTYPE html>
<html>
<title>imdb2senscritique</title>
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
<body>
<main class="container">
<h1>imdb2senscritique</h1>
<p>Update <a href="https://www.senscritique.com">SensCritique</a> ratings from a IMDB export CSV file.</p>
<hr>
<form method="post" class="form-horizontal" enctype="multipart/form-data">
  <div class="row form-group">
    <div class="col-md-4">
      <label for="file">IMDB export file
      </label>
      <input type="file" name="file">
      <?php if(file_exists($filepath)):?>
        <p class="help-block">Existing file uploaded on <?php echo date ("F d, Y", filemtime($filepath)); ?></p>
      <?php endif; ?>
    </div>
    <div class="col-md-4">
      <label for="start">Start item</label>
      <input type="number" class="form-control" name="start" value=<?php echo $params->start; ?>>
    </div>
    <div class="col-md-4">
      <label for="number">Item number</label>
      <input type="number" class="form-control" name="number" value=<?php echo $params->nbr; ?>>
    </div>
  </div>
  <div class="form-group">
    <div class="col-md-4">
      <label for="mail">SC Email</label>
      <input type="email" class="form-control" name="mail" value=<?php echo $params->mail; ?>>
    </div>
    <div class="col-md-4">
      <label for="pswd">SC Password</label>
      <input type="password" class="form-control" name="pswd" autocomplete="new-password" value=<?php echo $params->pswd; ?>>
    </div>
    <div class="col-md-4">
      <label for="over">Overwrite</label>
      <div class="checkbox">
        <label><input type="checkbox" name="over" checked=<?php echo $params->over; ?>> Overwrite existing ratings</label>
      </div></div>
  </div>
  <div class="form-group">
    <div class="col-md-6">
      <input type="submit" class="btn btn-primary btn-lg btn-block" value="Go">
    </div>
    <?php if($params->mail):?>
    <div class="col-md-6">
      <input type="submit" name="next" class="btn btn-primary btn-lg btn-block" value="Next">
    </div>
    <?php endif; ?>
  </div>
</form>
<hr>
<section>
<?php if (count($stats->updated)): ?>
    <p><strong>These titles where successfully updated</strong></p>
    <ul><li><?php echo implode($stats->updated, '</li><li>'); ?></li></ul>
<?php endif; ?>

<?php if (count($stats->skipped)): ?>
    <p><strong>These titles where skipped because already rated</strong></p>
    <ul><li><?php echo implode($stats->skipped, '</li><li>'); ?></li></ul>
<?php endif; ?>

<?php if (count($stats->notFound)): ?>
    <p><strong>These titles where not recognized</strong></p>
    <ul><li><?php echo implode($stats->notFound, '</li><li>'); ?></li></ul>
<?php endif; ?>

<?php if (count($stats->lines) && count($stats->failed)): ?>
    <p><strong>These titles failed for unknown reasons</strong></p>
    <ul><li><?php echo implode($stats->failed, '</li><li>'); ?></li></ul>
<?php endif; ?>
</section>
</main>
</body>
</html>
