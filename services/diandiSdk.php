<?php
/**
 * @Author: Wang chunsheng  email:2192138785@qq.com
 * @Date:   2022-06-21 13:50:41
 * @Last Modified by:   Wang chunsheng  email:2192138785@qq.com
 * @Last Modified time: 2022-08-22 19:52:58
 */

namespace diandi\iot\services;

use common\helpers\loggingHelper;
use common\helpers\ResultHelper;
use Common\Sign\CodeConst;
use Common\Sign\ProjectSign;
use diandi\iot\sign\Sign;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Yii;
use yii\base\BaseObject;
use yii\base\ErrorException;
use yii\base\InvalidCallException;

class diandiSdk extends BaseObject
{
    public static string $apiUrl = 'https://iot.ddicms.com';

    /**
     * app_secret
     * @var string
     */
    public static string $appSecret;

    /**
     * app_id
     * @var string
     */
    public static string $appId;

    public static array $header = [
        'ContentType' => 'application/x-www-form-urlencoded',
    ];

    /**
     * @throws ErrorException
     */
    public static function __init(): void
    {
        $confPath = yii::getAlias('@common/config/diandi.php');
        if (file_exists($confPath)) {
            $config = require $confPath;
            self::$apiUrl = $config['apiUrl'];
            self::$appSecret = $config['appSecret'];
            self::$appId = (int)$config['appId'];
        } else {
            self::putAuthConf();
        }
    }

    /**
     * 统一请求
     *
     * @param $datas
     * @param $url
     * @param array $headers 请求头部
     *
     * @return array
     * @throws GuzzleException
     * @date 2022-05-11
     *
     * @example
     *
     * @author Wang Chunsheng
     *
     * @since
     */
    public static function postHttp($datas, $url, array $headers = []): array
    {
        $headersToeken = array_merge(self::$header, [
            'access-token' => self::$access_token,
            'store-id' => self::$store_id,
            'bloc-id' => self::$bloc_id,
        ]);
        $headers = array_merge(self::$header, $headers, $headersToeken);
        $client = new Client([
            // Base URI is used with relative requests
            'base_uri' => self::$apiUrl,
            // You can set any number of default request options.
            'timeout' => 10,
        ]);



        $res = $client->request('POST', $url, [
            'form_params' => $datas,
            'headers' => $headers,
        ]);

        $body = $res->getBody();
        $remainingBytes = $body->getContents();

        return self::analysisRes(json_decode($remainingBytes, true));
    }

    /**
     * 签名处理
     * @param $data
     * @return mixed
     */
    public static function createData($data)
    {
        try {
            $ProjectSign = new ProjectSign();
            $data['timestamp'] = time();
            return  $ProjectSign->createSign($data);
        } catch (\ErrorException $e) {
            throw new BaseException(401, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            throw new BaseException(401, $e->getMessage());
        }
    }

    // 解析返回的内容
    public static function analysisRes($Res)
    {
        if ((int)$Res['errcode']) {
            throw new InvalidCallException($Res['message']);
        } else {
//            $data = [
//                'code' => $Res['resultCode'],
//                'content' => $Res['reason'],
//            ];

            return $Res;
        }
    }


    public static function putAuthConf($apiUrl = '', $appSecret = '', $appId = ''): void
    {
        $confPath = yii::getAlias('@common/config/diandi.php');
        if (!file_exists($confPath)) {
            $config = self::local_auth_config();
            $config = str_replace([
                '{apiUrl}', '{appSecret}', '{appId}'
            ], [
                $apiUrl, $appSecret, $appId
            ], $config);
            file_put_contents($confPath, $config);
        }
    }

    /**
     * 智能开关控制.
     *
     * @param $ext_room_id
     * @param $switch_type
     * @param $delay
     * @param $ext_event_id
     * @param bool $is_queue （是否使用队列）
     *
     * @return array
     * @throws GuzzleException
     * @date 2022-06-28
     *
     * @example
     *
     * @author Wang Chunsheng
     *
     * @since
     */
    public static function switchStatue($ext_room_id, $switch_type, $delay, $ext_event_id, bool $is_queue = false): array
    {
        $data = self::createData([
            'ext_room_id' => $ext_room_id,
            'switch_type' => $switch_type,
            'delay' => $delay,
            'ext_event_id' => $ext_event_id,
            'is_queue' => $is_queue,
        ]);
        loggingHelper::writeLog('diandi_tea', 'diandiLockSdk/switchStatue', '开关操作数据', $data);
        $Res = self::postHttp($data, '/api/diandi_switch/open/switch');
        loggingHelper::writeLog('diandi_tea', 'diandiLockSdk/switchStatue', '开关操作数据结果', $Res);

        if ($Res['code'] === 200) {
            return $Res['data'];
        } elseif (in_array($Res['code'], [402, 403])) {
            $key = self::$auth_key;
            Yii::$app->cache->set($key, '');
            try {
                self::__init();
            } catch (ErrorException $e) {
                return ResultHelper::json(400, $e->getMessage(), (array)$e);
            }
            return self::switchStatue($ext_room_id, $switch_type, $delay, $is_queue);
        }
        return ResultHelper::json(200, '控制成功');
    }

    /**
     * 创建开锁订单.修改结束时间表示延迟权限.
     *
     * @param $ext_order_id
     * @param $member_id
     * @param $password
     * @param $ext_room_id
     * @param $start_time
     * @param $end_time
     * @return array
     * @throws GuzzleException
     * @date 2022-06-28
     *
     * @example
     *
     * @author Wang Chunsheng
     *
     * @since
     */
    public function createLockOrder($ext_order_id, $member_id, $password, $ext_room_id, $start_time, $end_time): array
    {
        $data = self::createData([
            'ext_order_id' => $ext_order_id,
            'member_id' => $member_id,
            'password' => $password,
            'ext_room_id' => $ext_room_id,
            'start_time' => $start_time,
            'end_time' => $end_time,
        ]);
        loggingHelper::writeLog('diandi_tea', 'diandiLockSdk/createLockOrder', '创建开锁订单数据', $data);

        $Res = self::postHttp($data, '/api/diandi_doorlock/order/create');
        loggingHelper::writeLog('diandi_tea', 'diandiLockSdk/createLockOrder', '创建开锁订单结果', $Res);

        if ($Res['code'] === 200) {
            return $Res['data'];
        } elseif (in_array($Res['code'], [402, 403])) {
            $key = self::$auth_key;
            Yii::$app->cache->set($key, '');
            try {
                self::__init();
            } catch (ErrorException $e) {
                return ResultHelper::json(400, $e->getMessage(), (array)$e);
            }
            return self::createLockOrder($ext_order_id, $member_id, $password, $ext_room_id, $start_time, $end_time);
        }
        return ResultHelper::json(400, '创建订单失败');
    }

    /**
     * 网关开锁
     * @param $hourse_id
     * @param $pwd
     * @param $phoneNo
     * @param $keyName
     * @param $lock_type
     * @param $member_id
     * @param $ext_order_id
     * @return array|mixed|object[]|string[]
     */
    public function LockOpen($lockMac)
    {
        $data = self::createData([
            'lockMac' => $lockMac
        ]);

        loggingHelper::writeLog('diandi_tea', 'diandiLockSdk/LockOpen', '开锁数据', $data);

        try {
            $Res = self::postHttp($data, '/doorlock/gw/hxjOpenDoorLock');
            loggingHelper::writeLog('diandi_tea', 'diandiLockSdk/LockOpen', '开锁结果', $Res);

            if ((int) $Res['code'] === 200) {
                return $Res['data'];
            } elseif (in_array($Res['code'], [402, 403])) {
                $key = self::$auth_key;
                Yii::$app->cache->set($key, '');
                try {
                    self::__init();
                    return self::LockOpen($lockMac);
                } catch (ErrorException $e) {
                    return ResultHelper::json(400, $e->getMessage(), (array)$e);
                }
            }
        } catch (GuzzleException $e) {
            return ResultHelper::json(400, $e->getMessage(), (array)$e);
        }

        return ResultHelper::json(400,'开锁失败');
    }

    /**
     * 初始化配置文件
     * @return string
     */
    public static function local_auth_config(): string
    {
        $cfg = <<<EOF
<?php

/**
 * @Author: Wang chunsheng  email:2192138785@qq.com
 * @Date:   2021-01-18 16:51:31
 * @Last Modified by:   Wang chunsheng  email:2192138785@qq.com
 * @Last Modified time: 2022-02-28 10:21:41
 */

return [
    'apiUrl' => '{apiUrl}',
    'appSecret' => '{appSecret}',
    'appId' => '{appId}'
];
EOF;

        return trim($cfg);
    }
}

try {
    diandiLockSdk::__init();
} catch (ErrorException $e) {
    throw new ErrorException($e->getMessage());
}
