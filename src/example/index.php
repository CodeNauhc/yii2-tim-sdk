<?php
require_once(__DIR__ . '/../../vendor/autoload.php');
require_once(__DIR__ . '/../helpers/helpers.php');


$config = require_once(__DIR__ . '/config.php');

date_default_timezone_set('Asia/Shanghai');

use CodeNauhc\TIM\SdkClient;

/**
 * $config=  [
 * 'sdkAppId' => 1400052116,        // appid
 * 'identifier' => 'master',        // 管理员账号
 * 'private_key' => (__DIR__ . '/../keys/private_key'), // 私钥路径
 * ];
 */
$model = new SdkClient($config);

//p($model);
//echo $model->userSign;
// 导入账户
//p($model->account_import('111','赵日天天','https://avatars1.githubusercontent.com/u/18524421?s=40&v=4'));

// 获取族群信息
//p($model->group_add_group_member('@TGS#3KR742BFB','111'),0);

//p($model->group_get_group_info('@TGS#3KR742BFB'),0);

// 组群成员
//p($model->group_get_group_member_info('@TGS#3KR742BFB',10,0));

//p($model->sns_friend_get_all('111'));

//p($model->group_send_group_system_notification('@TGS#3KR742BFB','hello',''));

// 查询应用中组群 列表50 个
//p($model->group_get_appid_group_list());

// 高级查询应用中组群
//p($model->group_get_appid_group_list2(1000, 0, null));

// 生成独立签名
//p(SdkClient::generateUserSign($config['identifier'], $config['sdkAppId'], $config['private_key']));

//p($model->profile_portrait_get('111'));
// 加入的群
//p($model->group_get_joined_group_list('18021001101'));

p($model);
