<?php
namespace tale\debug\lib;

use think\Config;
use think\queue\Job;

class Task
{

    public function fire(Job $job, $data)
    {
        if (Tag::instance(Config::get('debug_log'))->addLog($data)) {
            $job->delete();
        }
    }

}
