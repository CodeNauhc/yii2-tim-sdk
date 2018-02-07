<?php

namespace CodeNauhc\TIM;


interface TimInterface
{
    const CONTENT_TYPE = 'json';

    const APN = '0';

    /**
     * 独立模式 Identifier生成UserSign的方法
     * @param string $identifier 用户账号
     * @param int $sdkAppId 应用id
     * @param string $protected_key_path 私钥的存储路径及文件名
     * @return string $out 返回的签名字符串
     */
    public static function generateUserSign(String $identifier, $sdkAppId, $protected_key_path);

}