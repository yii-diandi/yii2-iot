<?php
/*
 * @Author: Wang chunsheng  email:2192138785@qq.com
 * @Date:   2022-05-11 20:03:54
 * @Last Modified by:   Wang chunsheng  email:2192138785@qq.com
 * @Last Modified time: 2022-09-24 18:17:49
 */

namespace diandi\iot\services;

use common\helpers\ResultHelper;
use common\services\BaseService;
use GuzzleHttp\Client;
use Yii;

/**
 * 公寓app开放接口.
 *
 * @date 2022-05-19
 * https://open.ttlock.com/document/doc?urlName=userGuide%2Fekey.html
 *
 * @example
 *
 * @author Wang Chunsheng
 *
 * @since
 */
class TtlockServer extends BaseService
{
    const EVENT_LOCK_OPEN = 'diandi_ttdoorlock.lockopen';

    // 从平台获取的tokenId
    public static $tokenId = '';

    // 正式环境
    public static $apiUrl = 'https://cnapi.ttlock.com';

    // int	消息ID
    public static $msgId = 0;
    // string	访问的业务方法
    public static $method = '';
    // string	业务字段
    public static $data = '';

    public static $apiVersion = '1.0';

    public static $header = [
        'ContentType' => 'application/x-www-form-urlencoded',
    ];

    // 正式环境
    public static $username = '17615836361';

    public static $password = 'diandaoweizhi888';

    public static $client_id = '33b89d236be642d8858709112b5a0a1a';

    public static $client_secret = '788e6d400935fbf8b7884953542c2280';

    public static $access_token;
    public static $uid;
    public static $refresh_token;
    public static $expires_in;

    /**
     * 请求后错误代码
     *
     * @var array
     * @date 2022-05-11
     *
     * @example
     *
     * @author Wang Chunsheng
     *
     * @since
     */
    public static $errorCode = [];

    public static function __init()
    {
        // 鉴权
        self::apartmentLogin(self::$username, self::$password, self::$client_id, self::$client_secret);
    }

    public static function createData($data)
    {
        return $data;
    }

    public static function buildUrl($url)
    {
        return  self::$apiUrl.$url;
    }

    /**
     * 统一请求
     *
     * @param [type] $datas   请求参数
     * @param [type] $url     请求地址
     * @param array  $params  地址栏的参数
     * @param array  $headers 请求头部
     *
     * @return void
     * @date 2022-05-11
     *
     * @example
     *
     * @author Wang Chunsheng
     *
     * @since
     */
    public static function postHttp($datas, $url, $params = [], $headers = [])
    {
        $url = self::buildUrl($url);
        $headers = array_merge(self::$header, $headers);

        $client = new Client([
            // Base URI is used with relative requests
            'base_uri' => $url,
            // You can set any number of default request options.
            'timeout' => 10,
        ]);

        $res = $client->request('POST', '', [
            'form_params' => $datas,
            'headers' => $headers,
        ]);
        $body = $res->getBody();
        $remainingBytes = $body->getContents();

        return self::analysisRes(json_decode($remainingBytes, true));
    }

    // 解析返回的内容
    public static function analysisRes($Res)
    {
        if ((int) $Res['errcode']) {
            return ResultHelper::serverJson($Res['errcode'], $Res['errmsg'], $Res);
        } else {
            $data = [
                'code' => $Res['resultCode'],
                'content' => $Res['reason'],
            ];

            return ResultHelper::serverJson(200, '获取成功', $Res);
        }
    }

    /**
     * 鉴权V1.0.
     *
     * @param [type] $password
     *
     * @return void
     */
    public static function apartmentLogin($username, $password, $client_id, $client_secret)
    {
        $key = 'tttokenId';
        $tokenIdS = Yii::$app->cache->get($key);
        if (!empty($tokenIdS['access_token'])) {
            self::$access_token = $tokenIdS['access_token'];
            self::$uid = $tokenIdS['uid'];
            self::$refresh_token = $tokenIdS['refresh_token'];
            self::$expires_in = $tokenIdS['expires_in'];
        } else {
            $data = self::createData([
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'username' => $username,
                'password' => md5($password),
             ]);
            $Res = self::postHttp($data, '/oauth2/token');
            if ($Res['status'] === 200) {
                self::$access_token = $Res['data']['access_token'];
                self::$uid = $Res['data']['uid'];
                self::$refresh_token = $Res['data']['refresh_token'];
                self::$expires_in = $Res['data']['expires_in'];
                Yii::$app->cache->set($key, [
                    'access_token' => $Res['data']['access_token'],
                    'uid' => $Res['data']['uid'],
                    'refresh_token' => $Res['data']['refresh_token'],
                    'expires_in' => $Res['data']['expires_in'],
                ], $Res['data']['expires_in']);
            }
        }
    }

    public function refresh_token()
    {
    }

    /**
     * 网关开锁
     *
     * @return void
     * @date 2022-06-20
     *
     * @example
     *
     * @author Wang Chunsheng
     *
     * @since
     */
    public static function lockUnlock($lockId = 5752095)
    {
        $date = self::getMillisecond();
        $accessToken = self::$access_token;
        $clientId = self::$client_id;

        $data = self::createData([
            'date' => $date,
            'accessToken' => $accessToken,
            'clientId' => $clientId,
            'lockId' => $lockId,
        ]);

        $Res = self::postHttp($data, '/v3/lock/unlock');

        return $Res;
    }

    public function queryOpenState($lockId = 5752095)
    {
        $date = self::getMillisecond();
        $accessToken = self::$access_token;
        $clientId = self::$client_id;

        $data = self::createData([
            'date' => $date,
            'accessToken' => $accessToken,
            'clientId' => $clientId,
            'lockId' => md5($lockId),
         ]);
        $Res = self::postHttp($data, '/v3/lock/queryOpenState');
        if (!$Res['status']) {
            // {
            //     "state": 1  锁的开关状态:0-关,1-开,2-未知
            // }
            return $Res;
        }
    }

    /**
     * 获取时间戳到毫秒.
     *
     * @return bool|string
     */
    public static function getMillisecond()
    {
        list($msec, $sec) = explode(' ', microtime());
        $msectime = (float) sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);

        return $msectimes = substr($msectime, 0, 13);
    }
}
TtlockServer::__init();
