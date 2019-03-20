<?php
/**
 * Created by PhpStorm.
 * User: jksen
 * Date: 2019-03-09
 * Time: 02:05
 */
require "./vendor/autoload.php";

class RedPackageService
{
    /**
     * 红包群ID
     *
     * @var string
     */
    public $groupId = '';

    /**
     * 抢红包用户
     *
     * @var string
     */
    public $getter = '';

    /**
     * redis 存储
     *
     * @var string
     */
    public $part = 'GroupRed:';

    /**
     * 和 $part保持一致
     *
     * @var string
     */
    static $part_static = 'GroupRed:';

    /**
     * 红包具体信息 存储类型：string
     *
     * @var string
     */
    public $group_red_detail = 'Detail';

    /**
     * 红包拆分成多个的信息：存储类型：集合
     *
     * @var string
     */
    public $group_red_split = 'Split';

    /**
     * 红包领取纪录：存储类型：集合
     *
     * @var string
     */
    public $group_red_records = 'Records';

    /**
     * 红包领取到的详细信息 存储类型：hash
     *
     * @var string
     */
    public $group_red_geter_list = 'GeterList:';

    /**
     * red lock
     *
     * @var string
     */
    public $lock = 'geterLock';

    /**
     * 分离符
     *
     * @var string
     */
    static $dispatch = '_';

    /**
     * @var redis 实例
     */
    public $redis;

    /**
     * redis 缓存库
     *
     * @var int
     */
    public $indexDb = 10;

    /**
     * 红包过期时间
     *
     * @var int
     */
    public $passTime = 3600;

    const RED_SEND_ERR    = 4000;
    const RED_PASS_CODE   = 4001;
    const RED_VAINLY_CODE = 4002;
    const RED_GATED_CODE  = 4003;
    const RED_GATED_QUEUE = 4004;

    static $redErrLang = [
        self::RED_SEND_ERR    => '红包派发失败！',
        self::RED_PASS_CODE   => '手慢了，红包派完了',
        self::RED_VAINLY_CODE => '该红包已过期',
        self::RED_GATED_CODE  => '您已领过红包',
        self::RED_GATED_QUEUE => '抢红包排队中',
    ];

    /**
     * RedPackageService constructor.
     *
     * @param array $config
     */
    public function __construct($config = [])
    {
        if (empty($config)) {
            $config = [
                'host' => '127.0.0.1',
                'port' => 6379,
                'auth' => '',
            ];
        }

        if (!extension_loaded('redis')) {
            return "Missing redis component detected";
        }

        $this->redis = new \Predis\Client($config);
        try {
            $this->redis->ping();
        }
        catch (Exception $e) {
            return ("You must first turn on redis-server");
        }

        $this->redis->select($this->indexDb);
    }

    /**
     * 发红包
     *
     * @param $group
     * @param $uid
     * @param $sender
     * @param $money
     * @param $num
     * @param $words
     *
     * @return array|string
     */
    public function sendRedPackage($group, $uid, $sender, $money, $num, $words)
    {
        //初始化时间
        $runTime = time();

        //红包key
        $key = $this->createHotKey($group, $uid, $runTime);

        //拆分key
        $splitKey = $key . $this->group_red_split;

        //红包详情
        $detailKey = $key . $this->group_red_detail;

        //拆分红包
        $splily = $this->doubleAveragePackage($money, $num);

        //红包详情
        $red_detail = [
            'sender'      => $sender,
            'total_money' => $money,
            'total_num'   => $num,
            'send_word'   => $words,
            'left_num'    => $num,
            'receive_num' => 0,
            'init_time'   => $runTime,
            'start_time'  => $runTime,
            'end_time'    => $runTime,
            'expire_time' => $runTime + $this->passTime,
            'is_valid'    => 1,
        ];
        try {
            $this->redis->hmset($detailKey, $red_detail);
            $this->redis->lpush($splitKey, $splily);
        }
        catch (\Exception $e) {
            return json_encode(['code' => self::RED_SEND_ERR, 'msg' => static::$redErrLang[self::RED_SEND_ERR]]);
        }
        return json_encode(['redKey' => $key]);
    }

    /**
     * 抢红包
     *
     * @param $group_red_id
     * @param $getter
     *
     * @return false|string
     */
    public function receiveRedPackage($group_red_id, $getter)
    {
        $redKey    = $group_red_id . $this->group_red_detail;
        $splitKey  = $group_red_id . $this->group_red_split;
        $getterKey = $group_red_id . $this->group_red_geter_list;
        $userLock  = $group_red_id . $this->lock . $getter;
        $recordKey = $group_red_id . $this->group_red_records;
        $lock      = $this->redis->get($userLock);

        //加锁
        if ($lock && $lock == $getter) {
            return json_encode(['code' => self::RED_GATED_QUEUE, 'msg' => static::$redErrLang[self::RED_GATED_QUEUE]]);
        } else {
            $isSet = $this->addLock($userLock, $getter, $this->passTime);
            if (!$isSet) {
                return json_encode(['code' => self::RED_GATED_QUEUE, 'msg' => '抢红包失败了']);
            }
        }

        //判断抢过没有
        $isGet = $this->redis->sismember($recordKey, $getter);

        if ($isGet) {
            $this->removeLock($userLock);
            return json_encode(['code' => self::RED_GATED_CODE, 'msg' => static::$redErrLang[self::RED_GATED_CODE]]);
        }

        $redInfo = $this->redis->hgetall($redKey);

        if (empty($redInfo)) {
            //获取失败 抛出异常
        }

        //过期
        if ($redInfo['expire_time'] < time() && !$redInfo['is_valid']) {
            $this->removeLock($userLock);
            return json_encode(['code' => self::RED_VAINLY_CODE, 'msg' => static::$redErrLang[self::RED_VAINLY_CODE]]);
        }

        //手慢
        if ($redInfo['expire_time'] > time() && $redInfo['left_num'] == 0) {
            $this->removeLock($userLock);
            return json_encode(['code' => self::RED_PASS_CODE, 'msg' => static::$redErrLang[self::RED_PASS_CODE]]);
        }


        //取红包
        $this->redis->multi();
        $this->redis->rpop($splitKey);
        $this->redis->hincrby($redKey, 'left_num', -1);
        $this->redis->hincrby($redKey, 'receive_num', 1);
        $arr = $this->redis->exec();

        if(!$arr){
            $this->removeLock($userLock);
        }

        if ($arr[1] >= 0 || $arr[0] != false) {

            $getTedRed = [
                'uid'          => $getter,
                'get_time'     => time(),
                'get_money'    => $arr[0],
                'is_best'      => false,
                'group_red_id' => $group_red_id,
            ];

            //存入用户抢到的数据
            $this->redis->multi();
            $this->redis->hmset($getterKey . $getter, $getTedRed);
            $this->redis->hset($redKey, 'end_time', time());
            $this->redis->sadd($recordKey, $getter);
            $get_arr = $this->redis->exec();

            if (!$get_arr) {//返回红包
                $this->redis->multi();
                $this->redis->lpush($splitKey, $arr[0]);
                $this->redis->hincrby($redKey, 'left_num', +1);
                $this->redis->hincrby($redKey, 'receive_num', -1);
                $this->redis->exec();
                $this->removeLock($userLock);
                return json_encode(['code' => self::RED_GATED_QUEUE, 'msg' => static::$redErrLang[self::RED_GATED_QUEUE]]);
            }
            //解锁
            $this->removeLock($userLock);
            return json_encode($getTedRed);
        }


    }

    /**
     * 二分法拆分红包
     *
     * @param $total
     * @param $num
     *
     * @return array
     */
    protected function doubleAveragePackage($total, $num)
    {
        
        $red_packet = [];
        $min        = 3;//最小金额
        for ($i = 1; $i < $num; $i++) {
            $safe_total   = ($total - ($num - $i) * $min) / ($num - $i);
            $money        = $min < $safe_total ? mt_rand($min, $safe_total) : mt_rand($safe_total, $min);
            $total        = $total - $money;
            $red_packet[] = $money;
        }
        $red_packet[] = $total;
        shuffle($red_packet);
        return $red_packet;
    }

    /**
     * 生成redis 前缀
     *
     * @param $group
     * @param $uid
     * @param $runTime
     *
     * @return string
     */
    protected function createHotKey($group, $uid, $runTime)
    {
        $key = $this->part . $group . static::$dispatch . $uid . static::$dispatch . $runTime;
        return $key;
    }

    /**
     * 加锁
     * Author: JkSen
     * Time  : 2019/3/14 17:48
     *
     * @param $key
     * @param $value
     * @param $expire
     *
     * @return bool
     */
    protected function addLock($key, $value, $expire)
    {
        $res = $this->redis->set($key, $value, ['nx', 'ex' => $expire]);
        /*$setLock = $this->redis->setnx($key, $value);
        if ($setLock) {
            $setExpire = $this->redis->expire($key, $expire);
            if ($setExpire) {
                return true;
            }
            return false;
        }
         return false;*/
    }

    /**
     * 删除锁
     * Author: JkSen
     * Time  : 2019/3/14 17:51
     *
     * @param $key
     *
     * @return int
     */
    protected function removeLock($key)
    {
        return $this->redis->del([$key]);
    }

    public function test()
    {
       // var_dump('asdaa');die;
        $res = $this->redis->setnx('aaa', 123123, ['nx', 'ex' => 400]);
        echo $res;
    }
}