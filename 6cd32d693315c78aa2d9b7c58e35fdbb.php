<?php
header('Content-Type: application/json; charset=utf-8');

$url = 'https://api.open-meteo.com/v1/forecast?latitude=51.834541&longitude=13.800690&daily=sunshine_duration,shortwave_radiation_sum&timeformat=unixtime&timezone=Europe%2FBerlin';
/*
$stream = stream_context_create(array(
    "ssl"=>array(
        "verify_peer"=> false,
        "verify_peer_name"=> false, ),
    'http' => array(
        'timeout' => 30     ) )     );

$array = get_headers($url, 0, $stream);
$string = $array[0];
if(!strpos($string,"200")) {
    echo 'url:  '.$url." does not exist \n<br>";
    return;
}

$response = file_get_contents($url);
*/

$ch = curl_init ();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_HEADER, 0);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$response = curl_exec($ch);
curl_close($ch);

echo $response;
//$json = json_decode($response, true);
//var_dump($json); die;

/*

Warning: get_headers(): SSL: Handshake timed out in /var/lib/symcon/scripts/31871.ips.php on line 40

Warning: get_headers(): Failed to enable crypto in /var/lib/symcon/scripts/31871.ips.php on line 40

Warning: get_headers(https://api.open-meteo.com/v1/forecast?latitude=51.834541&longitude=13.800690&daily=sunshine_duration,shortwave_radiation_sum&timeformat=unixtime&timezone=Europe%2FBerlin): Failed to open stream: operation failed in /var/lib/symcon/scripts/31871.ips.php on line 40

*/