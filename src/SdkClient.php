<?php

namespace CodeNauhc\TIM;

use CodeNauhc\TIM\traits\TimRestAPI;
use Exception;
use GuzzleHttp\Client;

require_once(__DIR__ . '/helpers/helpers.php');

/**
 * Class Client 请求客户端
 * @package CodeNauhc\TIM
 *
 * @property string $userSign
 */
class SdkClient implements TimInterface
{
    use TimRestAPI;

    #app基本信息
    /**
     * @var int 应用id
     */
    public $sdkAppId = 0;

    /**
     * @var string tim 账号
     */
    public $identifier = '';

    /**
     * @var string 用户签名
     */
    public $userSign = '';

    #开放IM https接口参数, 一般不需要修改
    protected $http_type = 'https://';
    protected $method = 'post';
    protected $im_yun_url = 'console.tim.qq.com';
    protected $version = 'v4';


    public function __construct($config = [])
    {
        if (!empty($config)) {
            $this->init($config['sdkAppId'], $config['identifier']);
            $this->userSign = self::generateUserSign($this->identifier, $this->sdkAppId, $config['private_key']);
        }
    }

    /**
     * 初始化函数
     * @param int $sdkAppId 应用的 appId
     * @param string $identifier 访问接口的用户
     */
    function init($sdkAppId, $identifier)
    {
        $this->sdkAppId = $sdkAppId;
        $this->identifier = $identifier;
    }


    /**
     * @return string
     */
    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * @param string $identifier
     */
    public function setIdentifier(string $identifier)
    {
        $this->identifier = $identifier;
    }

    /**
     * @return int
     */
    public function getSdkAppId(): int
    {
        return $this->sdkAppId;
    }

    /**
     * @param int $sdkAppId
     */
    public function setSdkAppId(int $sdkAppId)
    {
        $this->sdkAppId = $sdkAppId;
    }

    /**
     * 获取用户签名
     * @return string
     */
    public function getUserSign()
    {
        return $this->userSign;
    }

    /**
     * 托管模式设置用户凭证
     * @param String $usr_sig
     * @return bool 返回成功与否
     */
    public function set_user_sig($usr_sig)
    {
        $this->userSign = $usr_sig;
        return true;
    }


    /**
     * @param $method
     * @param $url
     * @param $data
     * @return mixed
     */
    public static function httpRequest($method, $url, $data)
    {
        $client = new Client();
        $res = $client->request($method, $url, [
            'body' => $data,
            'connect_timeout' => 2 // 两秒超时时间
        ]);
        if ($res->getStatusCode()) {
            $body = $res->getBody();

            return $body;
        };
        return '';
    }


    /**
     * 构造访问REST服务器的参数,并访问REST接口
     * @param string $service_name 服务名
     * @param string $cmd_name 命令名
     * @param string $identifier 用户名
     * @param string $usersig 用来鉴权的usersig
     * @param string $req_data 传递的json结构
     * @param bool $print_flag 是否打印请求，默认为打印
     * @return mixed
     */
    public function api($service_name, $cmd_name, $identifier, $usersig, $req_data, $print_flag = false)
    {
        # 构建HTTP请求参数，具体格式请参考 REST API接口文档 (http://avc.qcloud.com/wiki/im/)(即时通信云-数据管理REST接口)
        $parameter = http_build_query([
            'usersig' => $this->userSign,
            'identifier' => $this->identifier,
            'sdkappid' => $this->sdkAppId,
            'contenttype' => self::CONTENT_TYPE,
        ]);

        $url = $this->http_type . $this->im_yun_url . '/' . $this->version . '/' . $service_name . '/' . $cmd_name . '?' . $parameter;

        $ret = $this->httpRequest('POST', $url, $req_data);

        return $ret;

    }


    /**
     * 构造访问REST服务器参数,并发访问REST服务器
     * @param string $service_name 服务名
     * @param string $cmd_name 命令名
     * @param string $identifier 用户名
     * @param string $usersig 用来鉴权的usersig
     * @param string $req_data 传递的json结构
     * @param bool $print_flag 是否打印请求，默认为打印
     * @return string $out 返回的签名字符串
     */
    public function multi_api($service_name, $cmd_name, $identifier, $usersig, $req_data, $print_flag = false)
    {

        //$req_tmp用来做格式化控制台输出,同时作为多路访问需要的数组结构
        $req_tmp = json_decode($req_data, true);
        # 构建HTTP请求参数，具体格式请参考 REST API接口文档 (http://avc.qcloud.com/wiki/im/)(即时通信云-数据管理REST接口)
        $parameter = http_build_query([
            'usersig' => $this->userSign,
            'identifier' => $this->identifier,
            'sdkappid' => $this->sdkAppId,
            'contenttype' => self::CONTENT_TYPE,
        ]);
        $url = $this->http_type . $this->im_yun_url . '/' . $this->version . '/' . $service_name . '/' . $cmd_name . '?' . $parameter;

        $ret = $this->http_req_multi('https', 'post', $url, $req_tmp);
        return $ret;

    }

    /**
     * 独立模式根据Identifier生成UserSig的方法
     * @param int $identifier 用户账号
     * @param int $expiry_after 过期时间
     * @param string $protected_key_path 私钥的存储路径及文件名
     * @param $tool_path
     * @return string $out 返回的签名字符串
     */
    public function generate_user_sig($identifier, $expiry_after, $protected_key_path, $tool_path)
    {

        # 这里需要写绝对路径，开发者根据自己的路径进行调整
        $command = escapeshellarg($tool_path)
            . ' ' . escapeshellarg($protected_key_path)
            . ' ' . escapeshellarg($this->sdkAppId)
            . ' ' . escapeshellarg($identifier);
        $ret = exec($command, $out, $status);
        if ($status == -1) {
            return null;
        }
        $this->userSign = $out[0];
        return $out;
    }


    /**
     * 向Rest服务器发送请求
     * @param string $http_type http类型,比如https
     * @param string $method 请求方式，比如POST
     * @param string $url 请求的url
     * @param $data
     * @return string $data 请求的数据
     */
    public static function http_req($http_type, $method, $url, $data)
    {
        $ch = curl_init();
        if (strstr($http_type, 'https')) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        }

        if ($method == 'post') {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        } else {
            $url = $url . '?' . $data;
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 100000);//超时时间

        try {
            $ret = curl_exec($ch);
        } catch (Exception $e) {
            curl_close($ch);
            return json_encode(array('ret' => 0, 'msg' => 'failure'));
        }
        curl_close($ch);
        return $ret;
    }

    /**
     * 向Rest服务器发送多个请求(并发)
     * @param string $http_type http类型,比如https
     * @param string $method 请求方式，比如POST
     * @param string $url 请求的url
     * @return bool 是否成功
     */
    public static function http_req_multi($http_type, $method, $url, $data)
    {
        $mh = curl_multi_init();
        $ch_list = array();
        $i = -1;
        $req_list = array();
        foreach ($data as $req_data) {
            $i++;
            $req_data = json_encode($req_data);
            $ch = curl_init();
            if ($http_type == 'https://') {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 1);
            }

            if ($method == 'post') {
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $req_data);
            } else {
                $url = $url . '?' . $data;
            }
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 100000);//超时时间
            curl_multi_add_handle($mh, $ch);
            $ch_list[] = $ch;
            $req_list[] = $req_data;
        }
        try {
            do {
                $mret = curl_multi_exec($mh, $active);
            } while ($mret == CURLM_CALL_MULTI_PERFORM);

            while ($active and $mret == CURLM_OK) {
                if (curl_multi_select($mh) === -1) {
                    usleep(100);
                }
                do {
                    $mret = curl_multi_exec($mh, $active);
                } while ($mret == CURLM_CALL_MULTI_PERFORM);
            }
        } catch (Exception $e) {
            curl_close($ch);
            return json_encode(array('ret' => 0, 'msg' => 'failure'));
        }
        for ($i = 0; $i < count($ch_list); $i++) {
            $ret = curl_multi_getcontent($ch_list[$i]);
            if (strstr($ret, "URL_INFO")) {
                curl_multi_close($mh);
                return $ret;
            }
            $ret = json_decode($ret, true);
            echo json_format($ret);
        }
        curl_multi_close($mh);
        return true;
    }


    /**
     * 独立模式 Identifier生成UserSign的方法
     * @param string $identifier 用户账号
     * @param int $sdkAppId 应用id
     * @param string $protected_key_path 私钥的存储路径及文件名
     * @return string $out 返回的签名字符串
     */
    public static function generateUserSign(String $identifier, $sdkAppId, $protected_key_path)
    {
        // 工具路径
        $tool_path = __DIR__ . '/bin/linux-signature64';

        # 这里需要写绝对路径，开发者根据自己的路径进行调整
        $command = escapeshellarg($tool_path)
            . ' ' . escapeshellarg($protected_key_path)
            . ' ' . escapeshellarg($sdkAppId)
            . ' ' . escapeshellarg($identifier);
        $ret = exec($command, $out, $status);
//        p($ret);
        if ($status == -1) {
            return null;
        }
        return $out[0] ?? null;
    }


}