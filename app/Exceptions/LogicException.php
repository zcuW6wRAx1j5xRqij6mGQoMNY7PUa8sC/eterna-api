<?php

namespace App\Exceptions;

/**
* 逻辑错误 , 用于快速返回api接口, 不记录日志
*
*/
class LogicException extends \RuntimeException {

    // 未设置交易密码
    const NoSetTradePassword = 20000;

}
