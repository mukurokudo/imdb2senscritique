<?php
require_once "./src/simple_html_dom.php";
require_once "./src/senscritiquePost.php";
require_once "./src/senscritiqueGet.php";

// error_reporting(E_ALL);
// ini_set('display_errors', 'on');

$totalCount = 0;
$filePath = 'movies.csv';
$uploaded = isset($_FILES['file']['tmp_name']) ? $_FILES['file']['tmp_name'] : false;
if($uploaded) move_uploaded_file($uploaded, $filePath);

$params = (object) array(
    'mail' => isset($_POST['mail']) ? $_POST['mail'] : '',
    'pswd' => isset($_POST['pswd']) ? $_POST['pswd'] : '',
    'over' => isset($_POST['over']) ? $_POST['over'] : '',
    'start' => isset($_POST['start']) && $_POST['start'] > 0 ? $_POST['start'] : 1,
    'nbr' => isset($_POST['number']) && $_POST['number'] > 0 ? $_POST['number'] : 100,
);
if(isset($_POST['next']) && $_POST['next']) $params->start += $params->nbr;

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
    global $params;
    if (!file_exists($path))
        die('CSV file doesn\'t exists : '.$path);
    if(!($fHandle = fopen($path, 'r')))
        die('error opening CSV file : '.$path);

    $curr = 0;
    $max = $params->start + $params->nbr;
    while (($row = fgetcsv($fHandle)) && $curr < $max) {
        if($curr >= $params->start) {
            $row = array_map( "iconvUtf8", $row );
            parseMovie($curr, $row[5], $row[11], $row[8], $row[2]);
        }
        $curr++;
    }
    return $curr;
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


if($params->mail) {
    $scConnect = senscritiquePost($sc, $sc->loginPath, $sc->credentials);
    if($scConnect['httpCode'] === 200 && $scConnect['data'] !== ''){
        $connectReturn = json_decode($scConnect['data']);
        if($connectReturn->json->success === true)
            $totalCount = parseCSV($filePath);
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
    <head>
        <title>imdb2senscritique</title>
        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
        <style>span.alert{padding: 0 .25em;}</style>
        <script src="http://cdnjs.cloudflare.com/ajax/libs/jquery/2.1.3/jquery.min.js" type="text/javascript"></script>
        <script src="http://maxcdn.bootstrapcdn.com/bootstrap/3.2.0/js/bootstrap.min.js" type="text/javascript"></script>
        <script src="http://cdnjs.cloudflare.com/ajax/libs/bootstrap-validator/0.4.5/js/bootstrapvalidator.min.js" type="text/javascript"></script>
        <script type="text/javascript">
            $(document).ready(function() {
                $('#import_form').bootstrapValidator({
                    // To use feedback icons, ensure that you use Bootstrap v3.1.0 or later
                    feedbackIcons: {
                        valid: 'glyphicon glyphicon-ok',
                        invalid: 'glyphicon glyphicon-remove',
                        validating: 'glyphicon glyphicon-refresh'
                    },
                    fields: {
                        mail: {
                            validators: {
                                stringLength: {
                                    min: 1
                                },
                                notEmpty: {
                                    message: 'Please supply your email address'
                                }
                            }
                        },
                        start: {
                            validators: {
                                stringLength: {
                                    min: 1
                                },
                                notEmpty: {
                                    message: 'Please supply starting number'
                                }
                            }
                        },
                        number: {
                            validators: {
                                stringLength: {
                                    min: 1
                                },
                                notEmpty: {
                                    message: 'Please supply a number'
                                }
                            }
                        },
                        pswd: {
                            validators: {
                                stringLength: {
                                    min: 1
                                },
                                notEmpty: {
                                    message: 'Please supply your password'
                                }
                            }
                        }
                    }
                }).on('success.form.bv', function(e) {
                    $('#import_form').data('bootstrapValidator').resetForm();

                    // Prevent form submission
                    e.preventDefault();

                    // Get the form instance
                    var $form = $(e.target);

                    // Use Ajax to submit form data
                    $.post($form.attr('action'), $form.serialize(), function(result) {
                        // console.log(result);
                    }, 'json');
                });
            });
        </script>
    </head>
    <body>
        <main class="container">
            <h1>imdb2senscritique</h1>
            <p>Updates <a href="https://www.senscritique.com">SensCritique</a> ratings from a IMDB export CSV file.</p>
            <hr/>
            <form id="import_form" method="post" class="form-horizontal" enctype="multipart/form-data">
                <div class="row form-group">
                    <div class="col-md-4 inputGroupContainer">
                        <label for="file">IMDB export file</label>
                        <div class="input-group">
                            <input type="file" name="file" />
                            <?php if(file_exists($filePath)):?>
                                <p class="help-block">Existing file uploaded on <?php echo date ("F d, Y", filemtime($filePath)); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-4 inputGroupContainer">
                        <label for="start">Start item</label>
                        <div class="input-group">
                            <input type="number" class="form-control" name="start" id="start" value=<?php echo $params->start; ?>>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label for="number">Item number</label>
                        <div class="input-group">
                            <input type="number" class="form-control" name="number" id="number" value=<?php echo $params->nbr; ?>>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <div class="col-md-4">
                        <label for="mail">SC Email</label>
                        <div class="input-group">
                            <span class="input-group-addon"><i class="glyphicon glyphicon-user"></i></span>
                            <input type="email" class="form-control" name="mail" id="mail" placeholder="email address" value=<?php echo $params->mail; ?>>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label for="pswd">SC Password</label>
                        <div class="input-group">
                            <span class="input-group-addon"><i class="glyphicon glyphicon-lock"></i></span>
                            <input type="password" class="form-control" name="pswd" id="pswd" placeholder="password" autocomplete="new-password" value=<?php echo $params->pswd; ?>>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label for="over">Overwrite</label>
                        <div class="checkbox">
                            <label><input type="checkbox" name="over"<?php echo $params->over ? ' checked':'';?>> Overwrite existing ratings</label>
                        </div>
                    </div>
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
            <hr/>
            <section>
                <?php if ($totalCount): ?>
                <h2>Titles <?php echo $params->start; ?>-<?php echo $params->start+$params->nbr; ?> (out of <?php echo $totalCount; ?>)</h2><hr>
                <?php endif; ?>
                <?php if ($count = count($stats->updated)): ?>
                    <p><strong><?php echo $count;?> titles where successfully updated</strong></p>
                    <ul><?php
                    foreach($stats->updated as $movie) {
                        $updateInfo = sprintf('<span class="alert alert-success">updated %s to <strong>%d</strong> at date <em>%s</em></span>',
                            isset($movie->currentRating) ? 'from '.$movie->currentRating : '',
                            $movie->rating, $movie->date);
                        $originalInfo = sprintf('<span class="alert alert-warning">seems to be <em>%s</em> %s</span>',
                            $movie->title == $movie->foundtitle ? '' : $movie->title.',',
                            $movie->year);
                        printf('<li>#%d - <a href="%s">%s (%s)</a> %s %s</li>',
                            $movie->pos,
                            $sc->root . $movie->path,
                            $movie->foundtitle,
                            $movie->foundyear,
                            $updateInfo,
                            $movie->exactmatch ? '' : $originalInfo
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
