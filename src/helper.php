<?php

use tale\debug\lib\Tag;
use think\Config;
use think\Route;

Route::rule(['logs','log/[:tag]/[:page]'], '\tale\debug\TestController@index');

function debug_log($var, $tag = '', $expire = null, $type = null)
{
    $data = [
        'log'    => $var,
        'tag'    => $tag,
        'expire' => $expire,
        'type'   => $type,
        'time'   => $_SERVER['REQUEST_TIME'],
    ];
    Tag::instance(Config::get('debug_log'))->addLog($data);
}

function debug_task($var, $tag = '', $expire = null, $type = null)
{
    $data = [
        'log'    => $var,
        'tag'    => $tag,
        'expire' => $expire,
        'type'   => $type,
        'time'   => $_SERVER['REQUEST_TIME'],
    ];
    Tag::instance(Config::get('debug_log'))->addTask($data);
}
