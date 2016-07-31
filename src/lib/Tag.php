<?php
namespace tale\debug\lib;

use think\queue\Queue;

class Tag
{

    protected static $instance;

    protected $redis;

    protected $options = [
        'expire'     => 604800,
        'default'    => 'default',
        'queue_name' => 'debug_log',
        'host'       => '127.0.0.1',
        'port'       => 6379,
        'password'   => '',
        'timeout'    => 0,
        'persistent' => false,
        'log_prefix' => 'debug_log:',
        'tag_prefix' => 'debug_tag:',
        'root_name'  => 'root',
        'page_num'   => 100,
    ];

    public function __construct($options = [])
    {
        if (!extension_loaded('redis')) {
            throw new Exception('redis not installed!');
        }
        if (!empty($options)) {
            $this->options = array_merge($this->options, $options);
        }

        $func        = $this->options['persistent'] ? 'pconnect' : 'connect';
        $this->redis = new \Redis;
        $this->redis->$func($this->options['host'], $this->options['port'], $this->options['timeout']);

        if ('' != $this->options['password']) {
            $this->redis->auth($this->options['password']);
        }
    }

    public static function instance($options = [])
    {
        if (is_null(self::$instance)) {
            self::$instance = new self($options);
        }
        return self::$instance;
    }

    public function addTask($data)
    {
        Queue::push('\\tale\\debug\\Task', $data, $this->options['queue_name']);
    }

    public function addLog($data)
    {
        // 启动事务
        $this->redis->multi();

        // 获取root队列key
        $rootKey = $this->getRootKey();

        // 生成唯一日志id
        $logKey = $this->createLogKey();

        // 根日志的队列中增加日志id
        $this->redis->lpush($rootKey, $logKey);

        // 给每个标签的队列增加日志id
        $tagArr = $this->parseTag($data['tag']);

        $tags   = [];
        $exists = [];
        foreach ($tagArr as $v) {
            $base = $this->options['root_name'];
            $key  = [];
            foreach ($v as $tag) {
                $base .= ' ' . $tag;

                $md5       = md5($base);
                $key[$md5] = $tag;
                if (in_array($md5, $exists)) {
                    continue;
                }
                $exists[] = $md5;

                $tagKey = $this->options['tag_prefix'] . $md5;
                $this->redis->lpush($tagKey, $logKey);
            }
            $tags[] = $key;
        }

        // 记录标签
        $data['tag'] = $tags;
        $this->redis->set($logKey . ':tag', json_encode($tags));

        // 保存日志
        $this->redis->set($logKey, json_encode($data));

        // 设置过期时间
        $this->redis->expire($logKey, is_null($data['expire']) ? $this->options['expire'] : $data['expire']);

        // 执行事务并返回执行结果
        return $this->redis->exec();
    }

    public function getLogList($tag = '', $page = 0, $pageNum = 0)
    {
        $tag    = $tag ?: md5($this->options['root_name']);
        $tagKey = $this->options['tag_prefix'] . $tag;

        $page    = $page > 1 ? $page : 1;
        $pageNum = $pageNum > 0 ? $pageNum : $this->options['page_num'];
        $start   = ($page - 1) * $pageNum;
        $stop    = $start + $pageNum - 1;

        $result = $this->redis->lRange($tagKey, $start, $stop);

        $logs = [];
        foreach ($result as $v) {
            if ($this->redis->exists($v)) {
                $data = $this->redis->get($v);
                $data = json_decode($data, true);
                $logs[] = $data;
            } else {
                $this->removeLog($v);
            }
        }

        return $logs;
    }

    public function getRootMd5()
    {
        return md5($this->options['root_name']);
    }

    private function getRootKey()
    {
        return $this->createTagKey($this->options['root_name']);
    }

    private function createTagKey($tag = '')
    {
        return $this->options['tag_prefix'] . md5($tag);
    }

    private function createLogKey()
    {
        return $this->options['log_prefix'] . md5(uniqid(md5(microtime(true)), true));
    }

    private function removeLog($logKey = '')
    {
        $tags = $this->redis->get($logKey . ':tag');
        $tags = json_decode($tags, true);
        if ($tags) {
            foreach ($tags as $tag) {
                foreach ($tag as $k => $v) {
                    $tagKey = $this->options['tag_prefix'] . $k;
                    $this->redis->lrem($tagKey, $logKey, -1);
                }
            }
            $this->redis->del($logKey . ':tag');
            $this->redis->lrem($this->getRootKey(), $logKey, -1);
        }
    }

    private function parseTag($tag = '')
    {

        $tag = preg_replace('/^[\s|,]*/', '', $tag);
        $tag = preg_replace('/[\s|,]*$/', '', $tag);
        $tag = preg_replace('/\s{2,}/', ' ', $tag);
        $tag = preg_replace('/\s*,\s*/', ',', $tag);

        if (!strlen($tag)) {
            return [];
        }

        $tagArr = [];
        $arr    = explode(',', $tag);
        foreach ($arr as $v) {
            $tagArr[] = explode(' ', $v);
        }

        return $tagArr;
    }

}
