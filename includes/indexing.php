<?php
if (!defined('ABSPATH')) exit;

/**
 * Google Indexing API — classic PHP syntax (array(), explicit strings)
 * - Loads service JSON from the stored Media URL
 * - Builds JWT with RS256
 * - Exchanges for access token
 * - Publishes URL_UPDATED / URL_DELETED
 * - Logs all steps
 */

/** Base64 URL-safe helper */
function seo_master_b64url($data){
    $b = base64_encode($data);
    $b = str_replace(array('+','/'), array('-','_'), $b);
    return rtrim($b, '=');
}

/** Get OAuth access token using service account JSON */
function seo_master_google_get_token(){
    $json_url = trim(get_option('seo_master_google_json_path', ''));
    if ($json_url === '' || $json_url === '(none)') {
        seo_master_log('INDEX_AUTH', 'Missing service JSON URL', 0);
        return '';
    }

    // Fetch JSON from Media URL
    $resp = wp_remote_get($json_url, array('timeout' => 20));
    if (is_wp_error($resp)){
        seo_master_log('INDEX_AUTH', $resp->get_error_message(), 0);
        return '';
    }
    $rc = intval(wp_remote_retrieve_response_code($resp));
    if ($rc !== 200){
        seo_master_log('INDEX_AUTH', 'HTTP '.$rc.' fetching JSON', $rc);
        return '';
    }

    $creds = json_decode(wp_remote_retrieve_body($resp), true);
    if (!is_array($creds) || empty($creds['client_email']) || empty($creds['private_key'])){
        seo_master_log('INDEX_AUTH', 'Invalid JSON: missing client_email/private_key', 0);
        return '';
    }

    // Build JWT (header.claims.signature)
    $now   = time();
    $hdr   = seo_master_b64url(json_encode(array('alg' => 'RS256', 'typ' => 'JWT')));
    $claim = seo_master_b64url(json_encode(array(
        'iss'   => $creds['client_email'],
        'scope' => 'https://www.googleapis.com/auth/indexing',
        'aud'   => 'https://oauth2.googleapis.com/token',
        'iat'   => $now,
        'exp'   => $now + 3600
    )));
    $input = $hdr . '.' . $claim;

    $pkey  = openssl_pkey_get_private($creds['private_key']);
    if (!$pkey){
        seo_master_log('INDEX_AUTH', 'openssl_pkey_get_private failed', 0);
        return '';
    }
    $sig = '';
    $ok  = openssl_sign($input, $sig, $pkey, 'sha256WithRSAEncryption');
    openssl_free_key($pkey);
    if (!$ok){
        seo_master_log('INDEX_AUTH', 'openssl_sign failed', 0);
        return '';
    }
    $jwt = $input . '.' . seo_master_b64url($sig);

    // Exchange JWT for access token
    $token_resp = wp_remote_post(
        'https://oauth2.googleapis.com/token',
        array(
            'timeout' => 20,
            'headers' => array('Content-Type' => 'application/x-www-form-urlencoded'),
            'body'    => http_build_query(array(
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt
            ))
        )
    );
    if (is_wp_error($token_resp)){
        seo_master_log('INDEX_AUTH', $token_resp->get_error_message(), 0);
        return '';
    }
    $trc = intval(wp_remote_retrieve_response_code($token_resp));
    $tbd = wp_remote_retrieve_body($token_resp);
    if ($trc !== 200){
        seo_master_log('INDEX_AUTH', 'HTTP '.$trc.' token: '.$tbd, $trc);
        return '';
    }
    $tok = json_decode($tbd, true);
    if (!is_array($tok) || empty($tok['access_token'])){
        seo_master_log('INDEX_AUTH', 'No access_token in response: '.$tbd, 0);
        return '';
    }
    return $tok['access_token'];
}

/** Publish a single URL to Indexing API */
function seo_master_google_index_now($url, $type){
    $url  = trim((string)$url);
    $type = ($type === 'URL_DELETED') ? 'URL_DELETED' : 'URL_UPDATED';

    $json_url = trim(get_option('seo_master_google_json_path', ''));
    if ($json_url === '' || $json_url === '(none)'){
        $msg = 'No Google service JSON URL set.';
        seo_master_log('INDEX_SUBMIT', $msg.' URL: '.$url, 0);
        return array('ok' => false, 'message' => $msg);
    }

    $token = seo_master_google_get_token();
    if ($token === ''){
        return array('ok' => false, 'message' => 'Auth failed — see Logs.');
    }

    $endpoint = 'https://indexing.googleapis.com/v3/urlNotifications:publish';
    $body     = json_encode(array('url' => $url, 'type' => $type));

    $resp = wp_remote_post(
        $endpoint,
        array(
            'timeout' => 20,
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json'
            ),
            'body'    => $body
        )
    );

    if (is_wp_error($resp)){
        seo_master_log('INDEX_SUBMIT', $resp->get_error_message(), 0);
        return array('ok' => false, 'message' => $resp->get_error_message());
    }
    $rc  = intval(wp_remote_retrieve_response_code($resp));
    $raw = wp_remote_retrieve_body($resp);
    $ok  = ($rc >= 200 && $rc < 300);

    seo_master_log('INDEX_SUBMIT', 'HTTP '.$rc.' '.$raw, $rc);

    return array(
        'ok'      => $ok,
        'message' => $ok ? 'Submitted to Google' : ('Submit failed: HTTP '.$rc)
    );
}

/**
 * Back-compat alias (some earlier builds call this name)
 */
function seo_master_submit_to_google($url, $type){
    return seo_master_google_index_now($url, $type);
}
