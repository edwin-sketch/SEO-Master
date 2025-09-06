
<?php
if (!defined('ABSPATH')) exit;

/**
 * Logger — keeps last 100 API responses
 */
function seo_master_log($service,$message,$code=200){
  $logs = get_option('seo_master_logs',[]);
  $logs[] = [
    'time'=>current_time('mysql'),
    'service'=>$service,
    'code'=>$code,
    'message'=>substr($message,0,500)
  ];
  if(count($logs)>100) $logs = array_slice($logs,-100);
  update_option('seo_master_logs',$logs);
}
function seo_master_logs_page(){
  $logs=get_option('seo_master_logs',[]);
  echo '<div class="wrap"><h1>SEO Master Logs</h1><table class="widefat"><thead><tr><th>Time</th><th>Service</th><th>Code</th><th>Message</th></tr></thead><tbody>';
  if(empty($logs)) echo '<tr><td colspan="4">No logs yet.</td></tr>';
  else foreach(array_reverse($logs) as $l){
    echo '<tr><td>'.esc_html($l['time']).'</td><td>'.esc_html($l['service']).'</td><td>'.esc_html($l['code']).'</td><td>'.esc_html($l['message']).'</td></tr>';
  }
  echo '</tbody></table></div>';
}
