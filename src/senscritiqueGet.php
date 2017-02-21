<?php

/**
 * @param object $sc
 * @param string $url
 *
 * @return array HTTP code & CURL output
 */
function senscritiqueGet($sc, $url){
    $headers = array(
        "Origin: $sc->root",
        "Accept-Language: en-US,en;q=0.8,fr;q=0.6",
        "Referer: $sc->root/",
        "Connection: keep-alive",
        "Upgrade-Insecure-Requests: 1",
        "Cache-Control: max-age=0",
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPGET, 1);
    curl_setopt($ch, CURLOPT_POST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); // don't check certificate
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); // don't check certificate
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_VERBOSE, 0); // --no-verbose

    curl_setopt($ch, CURLOPT_COOKIEFILE, $sc->cookiePath); // --keep-session-cookies
    curl_setopt($ch, CURLOPT_COOKIEJAR, $sc->cookiePath); // --save-cookies
    curl_setopt($ch, CURLOPT_COOKIE, $sc->cookiePath); // --load-cookies

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $thisOutput = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    return array(
        'httpCode' => $httpcode,
        'data' => $thisOutput
    );
}
