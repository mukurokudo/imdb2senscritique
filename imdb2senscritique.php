<?php
require_once "./src/simple_html_dom.php";
require_once "./src/senscritiquePost.php";
require_once "./src/senscritiqueGet.php";

// error_reporting(E_ALL);
// ini_set('display_errors', 'on');

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
    'failed'=>array(),
    'skipped'=>array(),
    'notFound'=>array(),
);

function iconvutf8( $str ) {
    return iconv( "Windows-1252", "UTF-8", $str );
}
function parseCSV($path) {
    global $params;
    if (!file_exists($path))
        die('CSV file doesn\'t exists : '+ $path);
    if(!($fHandle = fopen($path, 'r')))
        die('error opening CSV file : '+ $path);

    $curr = 0;
    $max = $params->start + $params->nbr;
    while (($row = fgetcsv($fHandle)) && $curr < $max) {
        if($curr >= $params->start) {
            $row = array_map( "iconvutf8", $row );
            parseMovie($curr, $row[5], $row[11], $row[8]);
        }
        $curr++;
    }
    return $curr;
}
function getMovie($resultsPage, $title, $year, $rating) {
    $items = str_get_html($resultsPage)->find('li.esco-item');
    foreach($items as $item) {
        $thisTitle = html_entity_decode($item->find('a.elco-anchor',0)->plaintext, ENT_QUOTES);
        $thisOTitle = html_entity_decode($item->find('p.elco-original-title',0)->plaintext, ENT_QUOTES);
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
            //'currentRating' => $item->find('span.erra-action-item', 0)->plaintext,
            'path' => $item->find('a', 0)->href,
            'img' => $item->find('img', 0),
        );
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

function parseMovie($pos, $title, $year, $rating) {
    global $params;
    global $stats;
    global $sc;

    $scFindMovieURI = $sc->root."/recherche?query=".urlencode(strtolower($title))."&filter=movies";
    $scFindResults = senscritiqueGet($scFindMovieURI, $sc->root, $sc->cookiePath);

    $movie = getMovie($scFindResults['data'], $title, $year, $rating);

    if (!$movie || !$movie->id)
        return $stats->notFound[] = $title;

    $movie->pos = $pos;

    // TODO : should be possible to get that from the find page, see @getMovie
    if(!$params->over)
        $movie->currentRating = getCurrentRating($sc, $movie->id);

    if(!$params->over && $movie->currentRating)
        return $stats->skipped[] = $movie;

    $success = postRating($sc, $movie->id, $rating);
    if($success) return $stats->updated[] = $movie;

    $stats->failed[] = $title;
}


if($params->mail) {
    $scConnect = senscritiquePost($sc->root.$sc->loginPath, $sc->credentials,$sc->root,$sc->cookiePath);
    if($scConnect['httpCode'] === 200 && $scConnect['data'] !== ''){
        $connectReturn = json_decode($scConnect['data']);
        if($connectReturn->json->success === true)
            $totalCount = parseCSV($filepath);
        else die('connection to SC failed : wrong credentials');
    }
    else die('connection to SC failed : '.$scConnect['httpCode']);

    // removing the cookie after script execution
    if (file_exists($sc->cookiePath)) {
        unlink($sc->cookiePath);
    }
}
?>
<!DOCTYPE html>
<html>
<title>imdb2senscritique</title>
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
<style>span.alert{padding: 0 .25em;}</style>
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
        <label><input type="checkbox" name="over"<?php echo $params->over ? ' checked':'';?>> Overwrite existing ratings</label>
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
<?php if ($totalCount): ?>
<h2>Titles <?php echo $params->start; ?>-<?php echo $params->start+$params->nbr; ?> (out of <?php echo $totalCount; ?>)</h2><hr>
<?php endif; ?>
<?php if ($count = count($stats->updated)): ?>
    <p><strong><?php echo $count;?> titles where successfully updated</strong></p>
    <ul><?php
    foreach($stats->updated as $movie) {
        $updateinfos = sprintf('<span class="alert alert-success">updated %s to %d</span>',
            isset($movie->currentRating) ? 'from '.$movie->currentRating : '',
            $movie->rating);
        $originalinfos = sprintf('<span class="alert alert-warning">seems to be <em>%s</em> %s</span>',
            $movie->title == $movie->foundtitle ? '' : $movie->title.',',
            $movie->year);
        printf('<li>#%d - <a href="%s">%s (%s)</a> %s %s</li>',
            $movie->pos,
            $sc->root . $movie->path,
            $movie->foundtitle,
            $movie->foundyear,
            $updateinfos,
            $movie->exactmatch ? '' : $originalinfos
        );
    }?>
    </ul>
<?php endif; ?>

<?php if ($count = count($stats->skipped)): ?>
    <p><strong><?php echo $count;?> titles where skipped because already rated</strong></p>
    <ul><?php
    foreach($stats->skipped as $movie)
        printf('<li>#%d - <a href="%s">%s (%s)</a> <span class="alert alert-info">already rated %d %s</span></li>',
            $movie->pos,
            $sc->root . $movie->path,
            $movie->title, $movie->year,
            $movie->currentRating,
            $movie->rating != $movie->currentRating ? '(file rating is '. $movie->rating.')':'' ); ?>
    </ul>
<?php endif; ?>

<?php if ($count = count($stats->notFound)):
    $msg = ' <span class="alert alert-danger">not recognized</span>';?>
    <p><strong><?php echo $count;?> titles where not recognized</strong></p>
    <ul><li><span class="alert alert-error">
        <?php echo implode($stats->notFound, $msg.'</li><li>'); echo $msg; ?>
    </li></ul>
<?php endif; ?>

<?php if ($count = count($stats->failed)):
    $msg = ' <span class="alert alert-danger">unknown fail</span>';?>
    <p><strong><?php echo $count;?> titles failed for unknown reasons</strong></p>
    <ul><li>
        <?php echo implode($stats->failed, $msg.'</li><li>'); echo $msg; ?>
    </li></ul>
<?php endif; ?>
</section>
</main>
</body>
</html>
