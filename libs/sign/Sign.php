<?php

/**
 * @Author: Wang chunsheng  email:2192138785@qq.com
 * @Date:   2022-07-16 09:18:03
 * @Last Modified by:   Wang chunsheng  email:2192138785@qq.com
 * @Last Modified time: 2023-05-26 16:13:46
 */

namespace diandi\iot\sign;

class Sign
{

    const C_TIME_LOSE = 30 * 60; // 30分钟失效

    /**
     * var string key 密钥.
     */
    public string $key;

    /**
     * var array optional 需要过滤的方法.
     */
    public array $optional = ['*'];

    /**
     * 需要进行验签的环境.
     */
    private array $needSignEnvironment = ['beta', 'production'];


    public static function verifySign($Arg)
    {
        $Sign = new ProjectSign();
        $Res = $Sign->checkParams($Arg);
        if($Res['code'] === (int) CodeConst::CODE_90010){
            return $Sign->validateSign($Arg);
        }else{
            return $Res;
        }
    }

    /**
     * 根据key生成密钥 secret是由MD5(key+appid)生成 32位.
     *
     * @param string $AppID
     * @return string
     * @throws SignException
     * @throws SignException
     */
    public static function generateSecret(string $AppID): string
    {
        $confPath = yii::getAlias('@common/config/diandi.php');
        if (file_exists($confPath)) {
            $config = require $confPath;
            $AppSecret = $config['appSecret'];
            return md5($AppSecret . $AppID);
        } else {
            throw new SignException(CodeConst::CODE_90007);
        }
    }

    /**
     * 签名验证
     *
     * @param array $params
     *
     * @return true
     * @throws SignException
     */
    public function validateSign(array $params): bool
    {
        if (empty($params['appid'])) {
            throw new SignException(CodeConst::CODE_90006);
        }

        // 验证签名(若通用型签名及固定商户签名均不满足，抛出异常)
        if (empty($params['sign'])) {
            throw new SignException(CodeConst::CODE_90001);
        }

        if (!isset($params['timestamp']) || !$params['timestamp']) {
            throw new SignException(CodeConst::CODE_90002);
        }
        // 验证请求， 10分钟失效
        if (time() - $params['timestamp'] > self::C_TIME_LOSE) {
            throw new SignException(CodeConst::CODE_90004);
        }

        // 获取通用型的签名
        $forAllString = $this->paramFilter($params);  // 参数处理
        echo '排序处理';
        $forAllSign = $this->md5Sign($forAllString, $params['appid']);
        if ($params['sign'] != $forAllSign) {
            throw  new SignException(CodeConst::CODE_90005);
        } else {

            return true;
        }
    }

    /**
     * 除去数组中的空值和签名参数.
     *
     * @param $param
     *
     * @return array|string
     */
    public function paramFilter($param): array|string
    {
        $paraFilter = $param;
        unset($paraFilter['sign'], $paraFilter['appid']); // 剔除sign本身
        foreach ($paraFilter as $key => &$value) {
            if ($value === '') {
                unset($paraFilter[$key]);
            }
        }
        ksort($paraFilter); // 对数组根据键名升序排序
        // 函数将内部指针指向数组中的第一个元素，并输出
        return http_build_query($paraFilter);
    }

    /**
     * 生成md5签名字符串.
     *
     * @param $preStr string 需要签名的字符串
     * @param string $appId
     * @return string 签名结果
     * @throws SignException
     */
    public function md5Sign(string $preStr, string $appId = ''): string
    {
        // 生成sign  字符串和密钥拼接
        $str = $preStr . '&key=' . self::generateSecret($appId);
        echo '签名字符串：' . $str;
        $sign = md5(urldecode($str));
        return strtoupper($sign); // 转成大写
    }

    /**
     * 获取二级域名前缀
     *
     * @return string
     */
    public static function getPrefixOfDomain(): string
    {
        $url = '//' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];
        preg_match("#//(.*?)\.#i", $url, $match);

        return $match[1];
    }
}
