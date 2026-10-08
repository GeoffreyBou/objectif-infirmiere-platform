<?php
/** Only mounted into the local Docker instance, never packaged for production. */
add_filter('pre_wp_mail',function($return,$mail){
    if(!in_array(wp_parse_url(home_url(),PHP_URL_HOST),['localhost','127.0.0.1'],true))return $return;
    $file='/var/oi-mail/'.bin2hex(random_bytes(16)).'.json';
    $result=file_put_contents($file,wp_json_encode(['created_at'=>gmdate('c')]+$mail),LOCK_EX);
    if($result!==false)chmod($file,0640);
    return $result!==false;
},10,2);
