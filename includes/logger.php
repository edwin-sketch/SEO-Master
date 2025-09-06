<?php
if (!defined('ABSPATH')) exit;

/**
 * Lightweight logger — keeps last 100 API responses
 * Only storage helpers here (no admin page functions to avoid duplicates)
 */

function seo_master_log($service, $message, $code = 200){
    $logs = get_option('seo_master_logs', array());
    $logs[] = array(
        'time'    => current_time('mysql'),
        'service' => (string)$service,
        'code'    => (int)$code,
        'message' => is_string($message) ? substr($message, 0, 2000) : substr(wp_json_encode($message), 0, 2000),
    );
    if (count($logs) > 100) {
        $logs = array_slice($logs, -100);
    }
    update_option('seo_master_logs', $logs, false);
}

function seo_master_get_logs(){
    $logs = get_option('seo_master_logs', array());
    return is_array($logs) ? $logs : array();
}
