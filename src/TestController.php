<?php
namespace tale\debug;

use tale\debug\lib\Tag;

class TestController
{

    public function index($tag = '', $page = 0)
    {
        $debug = Tag::instance(Config::get('debug_log'));
        $logs = $debug->getLogList($tag, $page);

        $root = $debug->getRootMd5();
        $rootUrl = url('\tale\debug\TestController@index',['tag' => $root]);

        foreach ($logs as $val) {
            echo date('Y-m-d H:i:s',$val['time']) . '<br>';
            foreach ($val['tag'] as $v) {
                echo '<a href="' . $rootUrl . '" title="root">root</a>';
                foreach ($v as $key => $tag) {
                    $url = url('\tale\debug\TestController@index', ['tag' => $key]);
                    echo '-<a href="' . $url . '" title="' . $tag . '">' . $tag . '</a>';
                }
                echo ' | ';
            }
            echo '<br>';
            $val['log'] = is_array($val['log']) ? dump($val['log'],false) : $val['log'];
            echo '<pre>' . $val['log'] . '</pre><br><br>';
        }
    }

}
