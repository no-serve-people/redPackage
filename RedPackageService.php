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
     * 发红包用户
     *
     * @var string
     */
    public $sender = '';

    /**
     * 抢红包用户
     *
     * @var string
     */
    public $geter = '';

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
    static $part_statuc = 'GroupRed:';

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


    const RED_PASS_CODE   = 4001;
    const RED_VAINLY_CODE = 4002;
    const RED_GATED_CODE  = 4003;
    const RED_GATED_QUEUE = 4006;

    static $redErrLang = [
        self::RED_PASS_CODE   => '手慢了，红包派完了',
        self::RED_VAINLY_CODE => '该红包已过期',
        self::RED_GATED_CODE  => '您已领过红包',
        self::RED_GATED_QUEUE => '抢红包排队中',
    ];

    /**
     * RedPackageService constructor.
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
        } catch (Exception $e) {
            return ("You must first turn on redis-server");
        }

        $this->redis->select($this->indexDb);
    }

    /**
     * 发红包
     * @param $group
     * @param $uid
     * @param $money
     * @param $num
     * @param $words
     */
    public function sendRedPackage($group, $uid, $money, $num, $words)
    {
        //初始化时间
        $runTime = time();

        //红包key
        $key = $this->createHotKey($group, $uid, $runTime);

        //拆分key
        $splily_key = $key . $this->group_red_split;

        //红包详情
        $detail_key = $key . $this->group_red_detail;

        //拆分红包
        $splily = $this->doubleAveragePackage($money, $num);

        //红包详情
        $red_detail = [
            'sender'      => $this->sender,
            'total_money' => $money,
            'total_num'   => $num,
            'send_word'   => $words,
            'left_num'    => $num,
            'receive_num' => 0,
            'init_time'   => $runTime,
            'start_time'  => $runTime,
            'end_time'    => $runTime,
            'is_valid'    => 1,
        ];
        try {
            $this->redis->hmset($detail_key, $red_detail);
            $this->redis->lpush($splily_key, $splily);
        } catch (\Exception $e) {
            return "发红包失败";
        }
        return ['key' => $key];
    }

    /**
     * 抢红包
     */
    public function receiveRedPackage($grop_id, $geter)
    {

    }

    /**
     * 二分法拆分红包
     * @param $total
     * @param $num
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
     */
    protected function createHotKey($group, $uid, $runTime)
    {
        $key = $this->part . $group . static::$dispatch . $uid . static::$dispatch . $runTime;
        return $key;
    }
}