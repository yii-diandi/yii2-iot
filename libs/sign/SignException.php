<?php

/**
 * @Author: Wang chunsheng  email:2192138785@qq.com
 * @Date:   2022-07-16 09:18:03
 * @Last Modified by:   Wang chunsheng  email:2192138785@qq.com
 * @Last Modified time: 2023-04-27 16:45:10
 */


namespace diandi\iot\sign;



use Exception;

/**
 * MethodNotAllowedHttpException represents a "Sign Not Pass" HTTP exception with status code 403.
 *
 * @see https://tools.ietf.org/html/rfc7231#section-6.5.5
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class SignException extends Exception
{
    /**
     * Constructor.
     * @param int $code error code
     * @param null $message error message
     */
    public function __construct(int $code = 0, $message = null)
    {
        // 未传$message 取错误映射表默认值
        $errorMsg = CodeConst::codeMap()[$code] ?? '';
        $message = $message ?? $errorMsg;
        parent::__construct($code , $message);
    }

    /**
     * Constructor.
     * @param int $code error code
     * @param null $message error message
     */
    public static function message(int $code = 0, $message = null):array
    {
        // 未传$message 取错误映射表默认值
        $errorMsg = CodeConst::codeMap()[$code] ?? '';
        $message = $message ?: $errorMsg;
        return [
            'code'=>$code,
            'message'=>$message
        ];
    }
}
