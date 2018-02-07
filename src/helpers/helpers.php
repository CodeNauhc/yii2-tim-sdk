<?php


if (!function_exists('p')) {

    function p($var, $die = true)
    {
        echo '<pre>' . print_r($var, true), '</pre>';
        if ($die) {
            die;
        }
    }
}

if (!function_exists('json_format')) {

    /** Json数据格式化方法
     * @param array $data 数组数据
     * @param string $indent 缩进字符，默认4个空格
     * @return string json格式字符串
     */
    function json_format($data, $indent = null)
    {

        // 对数组中每个元素递归进行urlencode操作，保护中文字符
        array_walk_recursive($data, 'json_format_protect');

        // json encode
        $data = json_encode($data);

        // 将urlencode的内容进行urldecode
        $data = urldecode($data);

        // 缩进处理
        $ret = '';
        $pos = 0;
        $length = strlen($data);
        $indent = isset($indent) ? $indent : '    ';
        $newline = "\n";
        $prevchar = '';
        $outofquotes = true;
        for ($i = 0; $i <= $length; $i++) {
            $char = substr($data, $i, 1);
            if ($char == '"' && $prevchar != '\\') {
                $outofquotes = !$outofquotes;
            } elseif (($char == '}' || $char == ']') && $outofquotes) {
                $ret .= $newline;
                $pos--;
                for ($j = 0; $j < $pos; $j++) {
                    $ret .= $indent;
                }
            }
            $ret .= $char;
            if (($char == ',' || $char == '{' || $char == '[') && $outofquotes) {
                $ret .= $newline;
                if ($char == '{' || $char == '[') {
                    $pos++;
                }

                for ($j = 0; $j < $pos; $j++) {
                    $ret .= $indent;
                }
            }
            $prevchar = $char;
        }
        return $ret;
    }
}

if (!function_exists('json_format_protect')) {

    /**
     * json_format辅助函数
     * @param String $val 数组元素
     */
    function json_format_protect(&$val)
    {
        if ($val !== true && $val !== false && $val !== null) {
            $val = urlencode($val);
        }
    }
}

if (!function_exists('is_64bit')) {

    /**
     * 判断操作系统位数
     */
    function is_64bit()
    {
        $int = "9223372036854775807";
        $int = intval($int);
        if ($int == 9223372036854775807) {
            /* 64bit */
            return true;
        } elseif ($int == 2147483647) {
            /* 32bit */
            return false;
        } else {
            /* error */
            return "error";
        }
    }
}


