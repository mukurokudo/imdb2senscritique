<?php
require_once "./functions.php";

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

if(isset($_POST['action']) && $_POST['action'] === 'Next') $params->start += $params->nbr;

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


$scConnect = senscritiquePost($sc, $sc->loginPath, $sc->credentials);
if($scConnect['httpCode'] === 200 && $scConnect['data'] !== ''){
    $connectReturn = json_decode($scConnect['data']);
    if($connectReturn->json->success === true) {
        $totalCount = parseCSV($filePath);
    } else {
        header('HTTP/1.1 401 Unauthorized', true, 401);
        exit;
    }
} else {
    header('HTTP/1.1 401 Unauthorized', true, 401);
    exit;
}

// removing the cookie after script execution
if (file_exists($sc->cookiePath)) {
    unlink($sc->cookiePath);
}

$feedback = "";
if (isset($stats->fileErr) && !empty($stats->fileErr)) {
    $feedback .= '<p><strong style="color: red;">'.$stats->fileErr.'</strong></p>';
}
if ($totalCount) {
    $feedback .= '<h2>Titles '.$params->start.'-'.($params->start+$params->nbr).' (out of '.$totalCount.')</h2><hr>';
}
if ($count = count($stats->updated)) {
    $feedback .= '
        <p><strong>'.$count.' titles where successfully updated</strong></p>
        <ul>';
        foreach($stats->updated as $movie) {
            $updateInfo = sprintf('<span class="alert alert-success">updated %s to <strong>%d</strong> at date <em>%s</em></span>',
                isset($movie->currentRating) ? 'from '.$movie->currentRating : '',
                $movie->rating, $movie->date);
            $originalInfo = sprintf('<span class="alert alert-warning">seems to be <em>%s</em> %s</span>',
                $movie->title == $movie->foundtitle ? '' : $movie->title.',',
                $movie->year);
            $feedback .= sprintf('<li>#%d - <a href="%s">%s (%s)</a> %s %s</li>',
                $movie->pos,
                $sc->root . $movie->path,
                $movie->foundtitle,
                $movie->foundyear,
                $updateInfo,
                $movie->exactmatch ? '' : $originalInfo
            );
        }
        $feedback .= '</ul>';
}
if ($count = count($stats->skipped)) {
    $feedback .= '
        <p><strong>'.$count.' titles where skipped because already rated</strong></p>
        <ul>';
        foreach($stats->skipped as $movie) {
            $feedback .= sprintf('<li>#%d - <a href="%s">%s (%s)</a> <span class="alert alert-info">already rated %d %s</span></li>',
                            $movie->pos,
                            $sc->root . $movie->path,
                            $movie->title, $movie->year,
                            $movie->currentRating,
                            $movie->rating != $movie->currentRating ? '(file rating is '. $movie->rating.')':'' );
        }
        $feedback .= '</ul>';
}
if ($count = count($stats->notFound)) {
    $msg = ' <span class="alert alert-danger">not recognized</span>';
    $feedback .= '
        <p><strong>'.$count.' titles where not recognized</strong></p>
        <ul><li><span class="alert alert-error">
            '.implode($stats->notFound, $msg.'</li><li>').$msg.'
        </li></ul>';
}
if ($count = count($stats->failed)) {
    $msg = ' <span class="alert alert-danger">unknown fail</span>';
    $feedback .= '
        <p><strong>'.$count.' titles failed for unknown reasons</strong></p>
        <ul><li><span class="alert alert-error">
            '.implode($stats->failed, $msg.'</li><li>').$msg.'
        </li></ul>';
}

header('HTTP/1.1 200 Success', true, 200);
echo json_encode(['feedback' => $feedback, 'start' => $params->start]);
exit;
