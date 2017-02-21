<?php

/**
 * Calling senscritique API in POST mode
 *
 * @param object $sc
 * @param string $path
 * @param array  $postedData
 *
 * @return array HTTP code, CURL output & cookie path
 */
function senscritiquePost($sc, $path, $postedData){

    $headers = array(
        "Origin: ".$sc->root,
        "Accept-Language: en-US,en;q=0.8,fr;q=0.6",
        "Accept:application/json, text/javascript, */*; q=0.01",
        "Referer: ".$sc->root."/",
        "X-Requested-With: XMLHttpRequest",
        "Connection: keep-alive",
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $sc->root.$path);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postedData);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); // don't check certificate
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); // don't check certificate
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_VERBOSE, 0); // --no-verbose

    curl_setopt($ch, CURLOPT_COOKIEFILE, $sc->cookiePath); // --keep-session-cookies
    curl_setopt($ch, CURLOPT_COOKIEJAR, $sc->cookiePath); // --save-cookies
    curl_setopt($ch, CURLOPT_COOKIE, $sc->cookiePath); // --load-cookies

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $thisOutput = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    return array(
        'httpCode' => $httpCode,
        'data' => $thisOutput,
        'cookiePath' => $sc->cookiePath
    );
}
