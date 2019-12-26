<?php
/**
 * Created by PhpStorm.
 * User: jksen
 * Date: 2019-03-09
 * Time: 01:59
 */
require_once "./RedPackageService.php";

$redis_conf = [
    'host' => '127.0.0.1',
    'port' => 6379,
    'auth' => '',
];

$client = new RedPackageService($redis_conf);
$group  = 'TestGrpou1';
$uid    = 10086;
$money  = 1000;
$num    = 9;
$words  = "大吉大利，今晚吃鸡";
//发红包
$res = $client->sendRedPackage($group, $uid,'jikesen', $money, $num, $words);
//抢红包
$res = $client->receiveRedPackage(json_decode($res,true)['redKey'],10041);
echo '<pre>';
print_r($res);