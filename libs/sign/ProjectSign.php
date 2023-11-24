<?php

/**
 * @Author: Wang chunsheng  email:2192138785@qq.com
 * @Date:   2022-07-16 09:18:03
 * @Last Modified by:   Wang chunsheng  email:2192138785@qq.com
 * @Last Modified time: 2023-07-27 18:57:08
 */

namespace diandi\iot\sign;


use ErrorException;

/**
 * 基于项目的签名验证
 * @date 2023-05-21
 * @example
 * @author Wang Chunsheng
 * @since
 */
class ProjectSign
{
    #[Inject]
    protected HttpResponse $response;


    const  C_TIME_LOSE = 30 * 60; // 30分钟失效

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



    /**
     * 校验签名参数
     * @param array $params
     * @return array
     */
    public function checkParams(array $params): array
    {
        if (empty($params['app_id'])) {
            return SignException::message(CodeConst::CODE_90006);
        }

        // 验证签名(若通用型签名及固定商户签名均不满足，抛出异常)
        if (empty($params['sign'])) {
            return SignException::message(CodeConst::CODE_90001);
        }

        if (!isset($params['timestamp']) || !$params['timestamp']) {
            return SignException::message(CodeConst::CODE_90002);
        }

        // 验证请求， 10分钟失效
        if (time() - $params['timestamp'] > self::C_TIME_LOSE) {
            return SignException::message(CodeConst::CODE_90004);
        }


        return SignException::message(CodeConst::CODE_90010);

    }

    public function createSign(array $params)
    {
        // 获取通用型的签名
        $forAllString = $this->paramFilter($params);  // 参数处理
        $params['sign'] = $this->md5Sign($forAllString, $params['app_id']);
        return $params;
    }

    /**
     * 签名验证
     *
     * @param array $params
     *
     * @return array
     * @throws ErrorException
     */
    public function validateSign(array $params): array
    {
        // 获取通用型的签名
        $forAllString = $this->paramFilter($params);  // 参数处理
        try {
            $forAllSign = $this->md5Sign($forAllString, $params['app_id']);
            if ($params['sign'] != $forAllSign) {
                return SignException::message(CodeConst::CODE_90005);
            } else {
                return SignException::message(CodeConst::CODE_90011);
            }
        } catch (ErrorException $e) {
            throw new ErrorException($e->getMessage(), 401);
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
        unset($paraFilter['sign'], $paraFilter['app_id'], $paraFilter['accessToken']); // 剔除sign本身
        // 过滤空值
//        array_filter($paraFilter, function ($value) {
//            return !empty($value);
//        });
        foreach ($paraFilter as $key => &$value) {
            if ($value === '') {
                unset($paraFilter[$key]);
            }
        }
        $paraFilter = $this->sortArrayKeys($paraFilter);
        // 函数将内部指针指向数组中的第一个元素，并输出
        return http_build_query($paraFilter);
    }

    /**
     * 多层级嵌套排序 ASCII 字符顺序进行升序
     * @param $array
     * @return mixed
     */
    private function sortArrayKeys($array):array
    {
        ksort($array);

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] =  $this->sortArrayKeys($value);
            }
        }

        return $array;
    }

    /**
     * 生成md5签名字符串.
     *
     * @param $preStr string 需要签名的字符串
     *
     * @return string 签名结果
     * @throws ErrorException
     */
    public function md5Sign(string $preStr, $app_id = ''): string
    {
        // 生成sign  字符串和密钥拼接
        try {
            $str = $preStr . '&key=' . Sign::generateSecret($app_id);
            echo '排序处理:' . urldecode($str) . PHP_EOL;

            $sign = md5(urldecode($str));
            echo '签名结果:' . strtoupper($sign) . PHP_EOL;

            return strtoupper($sign); // 转成大写
        } catch (SignException $e) {
            throw new ErrorException($e->getMessage());
        }

    }

    /**
     * 获取二级域名前缀
     *
     * @return mixed
     */
    public static function getPrefixOfDomain(): mixed
    {
        $url = '//' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];
        preg_match("#//(.*?)\.#i", $url, $match);

        return $match[1];
    }
}
