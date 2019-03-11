<?php
/**
 * Created by PhpStorm.
 * User: jksen
 * Date: 2019-03-09
 * Time: 02:05
 */

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
     * redis 缓存库
     *
     * @var int
     */
    public $indexDb = 1;


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
     * 发红包
     */
    public function sendRedPackage()
    {

    }

    /**
     * 抢红包
     */
    public function receiveRedPackage()
    {

    }

    /**
     * 二分法拆分红包
     */
    private function fixRedPacage()
    {

    }

    /**
     * 生成redis 前缀
     */
    private function createHotKey()
    {

    }
}