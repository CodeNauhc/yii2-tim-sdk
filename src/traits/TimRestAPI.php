<?php

namespace CodeNauhc\TIM\traits;

/**
 * Trait TimRestAPI
 * @package CodeNauhc\TIM\traits
 */
trait TimRestAPI
{

    #REST API 访问接口集合
    #参数详情见RestInterface

    public function openim_send_msg($account_id, $receiver, $text_content)
    {

        #构造高级接口所需参数
        $msg_content = [];
        //创建array 所需元素
        $msg_content_elem = [
            'MsgType' => 'TIMTextElem',       // 文本类型
            'MsgContent' => [
                'Text' => $text_content,      // hello 为文本信息
            ]
        ];
        //将创建的元素$msg_content_elem, 加入array $msg_content
        array_push($msg_content, $msg_content_elem);

        $ret = $this->openim_send_msg2($account_id, $receiver, $msg_content);
        return $ret;
    }

    public function openpic_pic_upload($account_id, $receiver, $pic_path, $busi_type)
    {

        #获取长度和md5值
        $pic_data = file_get_contents($pic_path);
        $md5 = md5($pic_data);
        $pic_size = filesize($pic_path);

        #进行base64处理
        $fp = fopen($pic_path, "r");
        $pic_data = fread($fp, $pic_size);

        $slice_data = array();
        $slice_size = array();
        $SLICE_SIZE = 32 * 4096;

        //对文件进行分片
        for ($i = 0; $i < $pic_size; $i = $i + $SLICE_SIZE) {
            if ($i + $SLICE_SIZE > $pic_size) {
                break;
            }
            $slice_tmp = substr($pic_data, $i, $SLICE_SIZE);
            $slice_tmp = chunk_split(base64_encode($slice_tmp));
            $slice_tmp = str_replace("\r\n", '', $slice_tmp);
            $slice_size[] = $SLICE_SIZE;
            $slice_data[] = $slice_tmp;
        }

        //最后一个分片
        if ($i - $SLICE_SIZE < $pic_size) {
            $slice_size[] = $pic_size - $i;
            $tmp = substr($pic_data, $i, $pic_size - $i);
            $slice_size[] = strlen($tmp);
            $tmp = chunk_split(base64_encode($tmp));
            $tmp = str_replace("\r\n", '', $tmp);

            $slice_data[] = $tmp;
        }

        $pic_rand = rand(1, 65535);
        $time_stamp = time();
        $req_data_list = [];
        $sentOut = 0;
        printf("handle %d segments\n", count($slice_data) - 1);
        for ($i = 0; $i < count($slice_data) - 1; $i++) {
            #构造消息
            $msg = [
                "From_Account" => $account_id,  //发送者
                "To_Account" => $receiver,      //接收者
                "App_Version" => 1.4,       //应用版本号
                "Seq" => $i + 1,                      //同一个分片需要保持一致
                "Timestamp" => $time_stamp,         //同一张图片的不同分片需要保持一致
                "Random" => $pic_rand,              //同一张图片的不同分片需要保持一致
                "File_Str_Md5" => $md5,         //图片MD5，验证图片的完整性
                "File_Size" => $pic_size,       //图片原始大小
                "Busi_Id" => $busi_type,                    //群消息:1 c2c消息:2 个人头像：3 群头像：4
                "PkgFlag" => 1,                 //同一张图片要保持一致: 0表示图片数据没有被处理 ；1-表示图片经过base64编码，固定为1
                "Slice_Offset" => $i * $SLICE_SIZE,           //必须是4K的整数倍
                "Slice_Size" => $slice_size[$i],        //必须是4K的整数倍,除最后一个分片列外
                "Slice_Data" => $slice_data[$i]     //PkgFlag=1时，为base64编码
            ];
            array_push($req_data_list, $msg);
            $sentOut = 0;
            if ($i != 0 && ($i + 1) % 4 == 0) {
                //将消息序列化为json串
                $req_data_list = json_encode($req_data_list);
                printf("\ni = %d, call multi_api once\n", $i);
                $ret = $this->multi_api("openpic", "pic_up", $this->identifier, $this->userSign, $req_data_list, false);
                if (gettype($ret) == "string") {
                    $ret = json_decode($ret, true);
                    return $ret;
                }
                $req_data_list = [];
                $sentOut = 1;
            }
        }

        if ($sentOut == 0) {
            $req_data_list = json_encode($req_data_list);
            printf("\ni = %d, call multi_api once\n", $i);
            $this->multi_api("openpic", "pic_up", $this->identifier, $this->userSign, $req_data_list, false);
        }

        #最后一个分片
        $msg = array(
            "From_Account" => $account_id,    //发送者
            "To_Account" => $receiver,        //接收者
            "App_Version" => 1.4,        //应用版本号
            "Seq" => $i + 1,                        //同一个分片需要保持一致
            "Timestamp" => $time_stamp,            //同一张图片的不同分片需要保持一致
            "Random" => $pic_rand,                //同一张图片的不同分片需要保持一致
            "File_Str_Md5" => $md5,            //图片MD5，验证图片的完整性
            "File_Size" => $pic_size,        //图片原始大小
            "Busi_Id" => $busi_type,                    //群消息:1 c2c消息:2 个人头像：3 群头像：4
            "PkgFlag" => 1,                    //同一张图片要保持一致: 0表示图片数据没有被处理 ；1-表示图片经过base64编码，固定为1
            "Slice_Offset" => $i * $SLICE_SIZE,            //必须是4K的整数倍
            "Slice_Size" => $slice_size[count($slice_data) - 1],        //必须是4K的整数倍,除最后一个分片列外
            "Slice_Data" => $slice_data[count($slice_data) - 1]        //PkgFlag=1时，为base64编码
        );

        $req_data = json_encode($msg);
        $ret = $this->api("openpic", "pic_up", $this->identifier, $this->userSign, $req_data, false);
        $ret = json_decode($ret, true);
        echo json_format($ret);
        return $ret;
    }

    public function openim_send_msg_pic($account_id, $receiver, $pic_path)
    {

        #构造高级接口所需参数
        //上传图片并获取url
        $busi_type = 2; //表示C2C消息
        $ret = $this->openpic_pic_upload($account_id, $receiver, $pic_path, $busi_type);
        $tmp = $ret["URL_INFO"];

        $uuid = $ret["File_UUID"];
        $pic_url = $tmp[0]["DownUrl"];

        $img_info = array();
        $img_tmp = $ret["URL_INFO"][0];
        if ($img_tmp["PIC_TYPE"] == 4) {
            $img_tmp["PIC_TYPE"] = 3;
        }
        $img_info_elem1 = array(
            "URL" => $img_tmp["DownUrl"],
            "Height" => $img_tmp["PIC_Height"],
            "Size" => $img_tmp["PIC_Size"],
            "Type" => $img_tmp["PIC_TYPE"],
            "Width" => $img_tmp["PIC_Width"]
        );

        $img_tmp = $ret["URL_INFO"][1];
        if ($img_tmp["PIC_TYPE"] == 4) {
            $img_tmp["PIC_TYPE"] = 3;
        }
        $img_info_elem2 = array(
            "URL" => $img_tmp["DownUrl"],
            "Height" => $img_tmp["PIC_Height"],
            "Size" => $img_tmp["PIC_Size"],
            "Type" => $img_tmp["PIC_TYPE"],
            "Width" => $img_tmp["PIC_Width"]
        );

        $img_tmp = $ret["URL_INFO"][2];
        if ($img_tmp["PIC_TYPE"] == 4) {
            $img_tmp["PIC_TYPE"] = 3;
        }
        $img_info_elem3 = array(
            "URL" => $img_tmp["DownUrl"],
            "Height" => $img_tmp["PIC_Height"],
            "Size" => $img_tmp["PIC_Size"],
            "Type" => $img_tmp["PIC_TYPE"],
            "Width" => $img_tmp["PIC_Width"]
        );

        array_push($img_info, $img_info_elem1);
        array_push($img_info, $img_info_elem2);
        array_push($img_info, $img_info_elem3);
        $msg_content = array();
        //创建array 所需元素
        $msg_content_elem = array(
            'MsgType' => 'TIMImageElem',       //文本类型
            'MsgContent' => array(
                'UUID' => $uuid,
                'ImageInfoArray' => $img_info,
            )
        );
        //将创建的元素$msg_content_elem, 加入array $msg_content
        array_push($msg_content, $msg_content_elem);

        $ret = $this->openim_send_msg2($account_id, $receiver, $msg_content);
        return $ret;
    }

    public function openim_send_msg2($account_id, $receiver, $msg_content)
    {

        #构造新消息
        $msg = [
            'To_Account' => $receiver,
            'MsgSeq' => rand(1, 65535),
            'MsgRandom' => rand(1, 65535),
            'MsgTimeStamp' => time(),
            'MsgBody' => $msg_content,
            'From_Account' => $account_id
        ];
        #将消息序列化为json串
        $req_data = json_encode($msg);

        $ret = $this->api("openim", "sendmsg", $this->identifier, $this->userSign, $req_data);
        $ret = json_decode($ret, true);
        return $ret;
    }

    public function openim_batch_sendmsg($account_list, $text_content)
    {

        #构造高级接口所需参数
        $msg_content = array();
        //创建array 所需元素
        $msg_content_elem = array(
            'MsgType' => 'TIMTextElem',       //文本类型
            'MsgContent' => array(
                'Text' => $text_content,                //hello 为文本信息
            )
        );
        //将创建的元素$msg_content_elem, 加入array $msg_content
        array_push($msg_content, $msg_content_elem);

        $ret = $this->openim_batch_sendmsg2($account_list, $msg_content);
        return $ret;
    }

    public function openim_batch_sendmsg_pic($account_list, $pic_path)
    {

        #构造高级接口所需参数
        //上传图片并获取url
        $busi_type = 2; //表示C2C消息
        $ret = $this->openpic_pic_upload($this->identifier, $account_list[0], $pic_path, $busi_type);
        $tmp = $ret["URL_INFO"];

        $uuid = $ret["File_UUID"];
        $pic_url = $tmp[0]["DownUrl"];

        $img_info = array();
        $img_tmp = $ret["URL_INFO"][0];
        if ($img_tmp["PIC_TYPE"] == 4) {
            $img_tmp["PIC_TYPE"] = 3;
        }
        $img_info_elem1 = array(
            "URL" => $img_tmp["DownUrl"],
            "Height" => $img_tmp["PIC_Height"],
            "Size" => $img_tmp["PIC_Size"],
            "Type" => $img_tmp["PIC_TYPE"],
            "Width" => $img_tmp["PIC_Width"]
        );

        $img_tmp = $ret["URL_INFO"][1];
        if ($img_tmp["PIC_TYPE"] == 4) {
            $img_tmp["PIC_TYPE"] = 3;
        }
        $img_info_elem2 = array(
            "URL" => $img_tmp["DownUrl"],
            "Height" => $img_tmp["PIC_Height"],
            "Size" => $img_tmp["PIC_Size"],
            "Type" => $img_tmp["PIC_TYPE"],
            "Width" => $img_tmp["PIC_Width"]
        );

        $img_tmp = $ret["URL_INFO"][2];
        if ($img_tmp["PIC_TYPE"] == 4) {
            $img_tmp["PIC_TYPE"] = 3;
        }
        $img_info_elem3 = array(
            "URL" => $img_tmp["DownUrl"],
            "Height" => $img_tmp["PIC_Height"],
            "Size" => $img_tmp["PIC_Size"],
            "Type" => $img_tmp["PIC_TYPE"],
            "Width" => $img_tmp["PIC_Width"]
        );

        array_push($img_info, $img_info_elem1);
        array_push($img_info, $img_info_elem2);
        array_push($img_info, $img_info_elem3);
        $msg_content = array();
        //创建array 所需元素
        $msg_content_elem = array(
            'MsgType' => 'TIMImageElem',       //文本类型
            'MsgContent' => array(
                'UUID' => $uuid,
                'ImageInfoArray' => $img_info,
            )
        );
        //将创建的元素$msg_content_elem, 加入array $msg_content
        array_push($msg_content, $msg_content_elem);

        $ret = $this->openim_batch_sendmsg2($account_list, $msg_content);
        return $ret;
    }

    public function openim_batch_sendmsg2($account_list, $msg_content)
    {

        #构造新消息
        $msg = array(
            'To_Account' => $account_list,
            'MsgRandom' => rand(1, 65535),
            'MsgBody' => $msg_content,
        );
        #将消息序列化为json串
        $req_data = json_encode($msg);

        $ret = $this->api("openim", "batchsendmsg", $this->identifier, $this->userSign, $req_data);
        $ret = json_decode($ret, true);
        return $ret;
    }

    public function account_import($identifier, $nick, $face_url)
    {

        #构造新消息
        $msg = [
            'Identifier' => $identifier,
            'Nick' => $nick,
            'FaceUrl' => $face_url,
        ];
        #将消息序列化为json串
        $req_data = json_encode($msg);

        $ret = $this->api("im_open_login_svc", "account_import", $this->identifier, $this->userSign, $req_data);
        $ret = json_decode($ret, true);
        return $ret;
    }

    public function register_account($identifier, $identifierType, $password)
    {

        #构造新消息
        $msg = array(
            'Identifier' => $identifier,
            'IdentifierType' => $identifierType,
            'Password' => $password,
        );
        #将消息序列化为json串
        $req_data = json_encode($msg);

        $ret = $this->api("registration_service", "register_account_v1", $this->identifier, $this->userSign, $req_data);
        $ret = json_decode($ret, true);
        return $ret;
    }

    /**
     * @param string $account_id
     * @return array|mixed|string
     */
    public function profile_portrait_get($account_id)
    {

        #构造高级接口所需参数
        $account_list = [];
        array_push($account_list, $account_id);
        $tag_list = [
            "Tag_Profile_IM_Nick",
            "Tag_Profile_IM_AllowType"
        ];

        $ret = $this->profile_portrait_get2($account_list, $tag_list);
        return $ret;
    }

    /**
     * @param array $account_list
     * @param array $tag_list
     * @return array|mixed|string
     */
    public function profile_portrait_get2($account_list, $tag_list)
    {

        #构造高级接口所需参数
        $msg = [
            'From_Account' => $this->identifier,
            'To_Account' => $account_list,
            'TagList' => $tag_list,
            'LastStandardSequence' => 0
        ];
        #将消息序列化为json串
        $req_data = json_encode($msg);

        $ret = $this->api("profile", "portrait_get", $this->identifier, $this->userSign, $req_data);
        $ret = json_decode($ret, true);
        return $ret;
    }

    public function profile_portrait_set($account_id, $new_name)
    {

        #构造高级接口所需参数
        $profile_list = array();
        $profile_nick = array(
            "Tag" => "Tag_Profile_IM_Nick",
            "Value" => $new_name
        );
        //加好友验证方式
        $profile_allow = array(
            "Tag" => "Tag_Profile_IM_AllowType",
            "Value" => "NeedPermission"
        );
        array_push($profile_list, $profile_nick);
        //array_push($profile_list, $profile_allow);

        $ret = $this->profile_portrait_set2($account_id, $profile_list);
        return $ret;
    }

    public function profile_portrait_set2($account_id, $profile_list)
    {

        #构造新消息
        $msg = array(
            'From_Account' => $account_id,
            'ProfileItem' => $profile_list
        );
        #将消息序列化为json串
        $req_data = json_encode($msg);

        $ret = $this->api("profile", "portrait_set", $this->identifier, $this->userSign, $req_data);
        $ret = json_decode($ret, true);
        return $ret;
    }

    public function sns_friend_import($account_id, $receiver)
    {

        #构造新消息
        $msg = array(
            'From_Account' => $account_id,
            'AddFriendItem' => array()
        );
        $receiver_arr = array(
            'To_Account' => $receiver,
            'Remark' => "",
            'AddSource' => "AddSource_Type_Unknow",
            'AddWording' => ""
        );
        array_push($msg['AddFriendItem'], $receiver_arr);
        #将消息序列化为json串
        $req_data = json_encode($msg);

        $ret = $this->api("sns", "friend_import", $this->identifier, $this->userSign, $req_data);
        $ret = json_decode($ret, true);
        return $ret;
    }


    public function sns_friend_delete($account_id, $frd_id)
    {

        #构造新消息
        $frd_list = array();
        //要添加的好友用户
        array_push($frd_list, $frd_id);

        $msg = array(
            'From_Account' => $account_id,
            'To_Account' => $frd_list,
            'DeleteType' => "Delete_Type_Both"
        );
        #将消息序列化为json串
        $req_data = json_encode($msg);

        $ret = $this->api("sns", "friend_delete", $this->identifier, $this->userSign, $req_data);
        $ret = json_decode($ret, true);
        return $ret;
    }

    public function sns_friend_delete_all($account_id)
    {

        #构造新消息
        $msg = array(
            'From_Account' => $account_id,
        );
        #将消息序列化为json串
        $req_data = json_encode($msg);

        $ret = $this->api("sns", "friend_delete_all", $this->identifier, $this->userSign, $req_data);
        $ret = json_decode($ret, true);
        return $ret;
    }

    public function sns_friend_check($account_id, $to_account)
    {

        #构造高级接口所需参数
        $to_account_list = array();
        //要添加的好友用户
        array_push($to_account_list, $to_account);

        $msg = array(
            'From_Account' => $account_id,
            'To_Account' => $to_account_list,
        );

        $ret = $this->sns_friend_check2($account_id, $to_account_list, "CheckResult_Type_Both");
        return $ret;
    }

    public function sns_friend_check2($account_id, $to_account_list, $check_type)
    {

        #构造新消息
        $msg = array(
            'From_Account' => $account_id,
            'To_Account' => $to_account_list,
            'CheckType' => $check_type
        );
        #将消息序列化为json串
        $req_data = json_encode($msg);

        $ret = $this->api("sns", "friend_check", $this->identifier, $this->userSign, $req_data);
        $ret = json_decode($ret, true);
        return $ret;
    }

    function sns_friend_get_all($account_id)
    {

        #构造高级接口所需参数
        $tag_list = array(
            "Tag_Profile_IM_Nick",
            "Tag_SNS_IM_Remark"
        );

        $ret = $this->sns_friend_get_all2($account_id, $tag_list);
        return $ret;
    }

    function sns_friend_get_all2($account_id, $tag_list)
    {

        #构造新消息
        $msg = array(
            'From_Account' => $account_id,
            'TimeStamp' => 0,
            'TagList' => $tag_list,
            'LastStandardSequence' => 1,
        );
        #将消息序列化为json串
        $req_data = json_encode($msg);

        $ret = $this->api("sns", "friend_get_all", $this->identifier, $this->userSign, $req_data);
        $ret = json_decode($ret, true);
        return $ret;
    }

    function sns_friend_get_list($account_id, $frd_id)
    {

        #构造高级接口所需参数
        $frd_list = array();
        array_push($frd_list, $frd_id);
        $tag_list = array(
            "Tag_Profile_IM_Nick",
            "Tag_SNS_IM_Remark"
        );

        $ret = $this->sns_friend_get_list2($account_id, $frd_list, $tag_list);
        return $ret;
    }

    function sns_friend_get_list2($account_id, $frd_list, $tag_list)
    {

        #构造新消息
        $msg = array(
            'From_Account' => $account_id,
            'To_Account' => $frd_list,
            'TagList' => $tag_list,
        );
        #将消息序列化为json串
        $req_data = json_encode($msg);

        $ret = $this->api("sns", "friend_get_list", $this->identifier, $this->userSign, $req_data);
        $ret = json_decode($ret, true);
        return $ret;
    }

    function group_get_appid_group_list()
    {

        #构造高级接口所需参数
        $ret = $this->group_get_appid_group_list2(50, null, null);
        return $ret;
    }

    function group_get_appid_group_list2($limit, $offset, $group_type)
    {

        #构造新消息
        $msg = [
            'Limit' => $limit,
            'Offset' => $offset,
            'GroupType' => $group_type
        ];
        #将消息序列化为json串
        $req_data = json_encode($msg);

        $ret = $this->api("group_open_http_svc", "get_appid_group_list", $this->identifier, $this->userSign, $req_data);
        $ret = json_decode($ret, true);
        return $ret;
    }

    function group_create_group($group_type, $group_name, $owner_id, $intro = null)
    {

        #构造高级接口所需参数
        $info_set = array(
            'group_id' => null,
            'introduction' => null,
            'notification' => $intro,
            'face_url' => null,
            'max_member_num' => 500,
        );
        $mem_list = array();

        $ret = $this->group_create_group2($group_type, $group_name, $owner_id, $info_set, $mem_list);
        return $ret;
    }

    function group_create_group2($group_type, $group_name, $owner_id, $info_set, $mem_list)
    {

        #构造新消息
        $msg = array(
            'Type' => $group_type,
            'Name' => $group_name,
            'Owner_Account' => $owner_id,
            'GroupId' => $info_set['group_id'],
            'Introduction' => $info_set['introduction'],
            'Notification' => $info_set['notification'],
            'FaceUrl' => $info_set['face_url'],
            'MaxMemberCount' => $info_set['max_member_num'],
            //	'ApplyJoinOption' => $info_set['apply_join'],
            'MemberList' => $mem_list
        );
        #将消息序列化为json串
        $req_data = json_encode($msg);

        $ret = $this->api("group_open_http_svc", "create_group", $this->identifier, $this->userSign, $req_data);
        $ret = json_decode($ret, true);
        return $ret;
    }

    function group_change_group_owner($group_id, $new_owner)
    {

        #构造新消息
        $msg = array(
            'GroupId' => $group_id,
            'NewOwner_Account' => $new_owner
        );
        #将消息序列化为json串
        $req_data = json_encode($msg);

        $ret = $this->api("group_open_http_svc", "change_group_owner", $this->identifier, $this->userSign, $req_data);
        $ret = json_decode($ret, true);
        return $ret;
    }

    function group_get_group_info($group_id)
    {

        #构造高级接口所需参数
        $group_list = [];
        array_push($group_list, $group_id);

        $base_info_filter = [
            "Type",               //群类型(包括Public(公开群), Private(私密群), ChatRoom(聊天室))
            "Name",               //群名称
            "Introduction",       //群简介
            "Notification",       //群公告
            "FaceUrl",            //群头像url地址
            "CreateTime",         //群组创建时间
            "Owner_Account",      //群主id
            "LastInfoTime",       //最后一次系统通知时间
            "LastMsgTime",        //最后一次消息发送时间
            "MemberNum",          //群组当前成员数目
            "MaxMemberNum",       //群组内最大成员数目
            "ApplyJoinOption"     //加群处理方式(比如FreeAccess 自由加入)
        ];
        $member_info_filter = [
            "Account",         // 成员ID
            "Role",            // 成员身份
            "JoinTime",        // 成员加入时间
            "LastSendMsgTime", // 该成员最后一次发送消息时间
            "ShutUpUntil"      // 该成员被禁言直到某时间
        ];
        $app_define_filter = [
            "GroupTestData1",  //自定义数据
        ];

        $ret = $this->group_get_group_info2($group_list, $base_info_filter, $member_info_filter, $app_define_filter);
        return $ret;
    }

    function group_get_group_info2($group_list, $base_info_filter, $member_info_filter, $app_define_filter)
    {

        #构造新消息
//        $filter = new Filter();
//        $filter->GroupBaseInfoFilter = $base_info_filter;
//        $filter->MemberInfoFilter = $member_info_filter;
//        $filter->AppDefinedDataFilter_Group = $app_define_filter;
        $msg = array(
            'GroupIdList' => $group_list,
//            'ResponseFilter' => $filter
        );
        #将消息序列化为json串
        $req_data = json_encode($msg);

        $ret = $this->api("group_open_http_svc", "get_group_info", $this->identifier, $this->userSign, $req_data);
        $ret = json_decode($ret, true);
        return $ret;
    }

    function group_get_group_member_info($group_id, $limit, $offset)
    {

        #构造新消息
        $msg = [
            "GroupId" => $group_id,
            "Limit" => $limit,
            "Offset" => $offset
        ];
        #将消息序列化为json串
        $req_data = json_encode($msg);

        $ret = $this->api("group_open_http_svc", "get_group_member_info", $this->identifier, $this->userSign, $req_data);
        $ret = json_decode($ret, true);
        return $ret;
    }

    function group_modify_group_base_info($group_id, $group_name)
    {

        #构造高级接口所需参数
        $info_set = array(
            'introduction' => null,
            'notification' => null,
            'face_url' => null,
            'max_member_num' => null,
            //	'apply_join' => "NeedPermission"
        );
        $app_define_list = array();

        $ret = $this->group_modify_group_base_info2($group_id, $group_name, $info_set, $app_define_list);
        return $ret;
    }

    function group_modify_group_base_info2($group_id, $group_name, $info_set, $app_define_list)
    {

        #构造新消息
        $msg = array(
            "GroupId" => $group_id,
            "Name" => $group_name,
            "Introduction" => $info_set['introduction'],
            "Notification" => $info_set['notification'],
            "FaceUrl" => $info_set['face_url'],
            "MaxMemberNum" => $info_set['max_member_num'],
            //	"ApplyJoinOption" => $info_set['apply_join'],
            "AppDefinedData" => $app_define_list
        );
        #将消息序列化为json串
        $req_data = json_encode($msg);

        $ret = $this->api("group_open_http_svc", "modify_group_base_info", $this->identifier, $this->userSign, $req_data);
        $ret = json_decode($ret, true);
        return $ret;

    }

    /**
     * @param string $group_id
     * @param string $member_id
     * @param int $silence
     * @return array|mixed|string
     */
    function group_add_group_member($group_id, $member_id, $silence = 1)
    {

        #构造新消息
        $mem_list = [];
        $mem_elem = [
            "Member_Account" => $member_id
        ];
        array_push($mem_list, $mem_elem);
        $msg = [
            "GroupId" => $group_id,
            "MemberList" => $mem_list,
            "Silence" => $silence
        ];
        #将消息序列化为json串
        $req_data = json_encode($msg);

        $ret = $this->api("group_open_http_svc", "add_group_member", $this->identifier, $this->userSign, $req_data);
        $ret = json_decode($ret, true);
        return $ret;
    }

    function group_delete_group_member($group_id, $member_id, $silence)
    {

        #构造新消息
        $mem_list = array();
        array_push($mem_list, $member_id);
        $msg = array(
            "GroupId" => $group_id,
            "MemberToDel_Account" => $mem_list,
            "Silence" => $silence
        );
        #将消息序列化为json串
        $req_data = json_encode($msg);

        $ret = $this->api("group_open_http_svc", "delete_group_member", $this->identifier, $this->userSign, $req_data);
        $ret = json_decode($ret, true);
        return $ret;
    }

    function group_modify_group_member_info($group_id, $account_id, $role)
    {

        #构造高级接口所需参数
        $ret = $this->group_modify_group_member_info2($group_id, $account_id, $role, "AcceptAndNotify", 0);
        return $ret;
    }

    function group_modify_group_member_info2($group_id, $account_id, $role, $msg_flag, $shutup_time)
    {

        #构造新消息
        $msg = array(
            "GroupId" => $group_id,
            "Member_Account" => $account_id,
            "Role" => $role
        );
        #将消息序列化为json串
        $req_data = json_encode($msg);

        $ret = $this->api("group_open_http_svc", "modify_group_member_info", $this->identifier, $this->userSign, $req_data);
        $ret = json_decode($ret, true);
        return $ret;
    }

    function group_destroy_group($group_id)
    {

        #构造新消息
        $msg = array(
            "GroupId" => $group_id,
        );
        #将消息序列化为json串
        $req_data = json_encode($msg);

        $ret = $this->api("group_open_http_svc", "destroy_group", $this->identifier, $this->userSign, $req_data);
        $ret = json_decode($ret, true);
        return $ret;
    }

    function group_get_joined_group_list($account_id)
    {

        #构造高级接口所需参数
        $base_info_filter = [
            "Type",               //群类型(包括Public(公开群), Private(私密群), ChatRoom(聊天室))
            "Name",               //群名称
            "Introduction",       //群简介
            "Notification",       //群公告
            "FaceUrl",            //群头像url地址
            "CreateTime",         //群组创建时间
            "Owner_Account",      //群主id
            "LastInfoTime",       //最后一次系统通知时间
            "LastMsgTime",        //最后一次消息发送时间
            "MemberNum",          //群组当前成员数目
            "MaxMemberNum",       //群组内最大成员数目
            "ApplyJoinOption"     //申请加群处理方式(比如FreeAccess 自由加入, NeedPermission 需要同意)
        ];


        $self_info_filter = [
            "Role",            //群内身份(Amin/Member)
            "JoinTime",        //入群时间
            "MsgFlag",         //消息屏蔽类型
            "UnreadMsgNum"     //未读消息数量
        ];

        $ret = $this->group_get_joined_group_list2($account_id, null, $base_info_filter, $self_info_filter);
        return $ret;
    }

    function group_get_joined_group_list2($account_id, $group_type, $base_info_filter, $self_info_filter)
    {

        #构造新消息
//        $filter = new Filter();
//        $filter->GroupBaseInfoFilter = $base_info_filter;
//        $filter->SelfInfoFilter = $self_info_filter;
        $msg = [
            "Member_Account" => $account_id,
//            "ResponseFilter" => $filter
        ];
        #将消息序列化为json串
        $req_data = json_encode($msg);

        $ret = $this->api("group_open_http_svc", "get_joined_group_list", $this->identifier, $this->userSign, $req_data);
        $ret = json_decode($ret, true);
        return $ret;
    }

    function group_get_role_in_group($group_id, $member_id)
    {

        #构造新消息
        $mem_list = array();
        array_push($mem_list, $member_id);
        $msg = array(
            "GroupId" => $group_id,
            "User_Account" => $mem_list,
        );
        #将消息序列化为json串
        $req_data = json_encode($msg);

        $ret = $this->api("group_open_http_svc", "get_role_in_group", $this->identifier, $this->userSign, $req_data);
        $ret = json_decode($ret, true);
        return $ret;
    }

    function group_forbid_send_msg($group_id, $member_id, $second)
    {

        #构造新消息
        $mem_list = array();
        array_push($mem_list, $member_id);
        $msg = array(
            "GroupId" => $group_id,
            "Members_Account" => $mem_list,
            "ShutUpTime" => $second
        );

        #将消息序列化为json串
        $req_data = json_encode($msg);

        $ret = $this->api("group_open_http_svc", "forbid_send_msg", $this->identifier, $this->userSign, $req_data);
        $ret = json_decode($ret, true);
        return $ret;
    }

    function group_send_group_msg($account_id, $group_id, $text_content)
    {

        #构造高级接口所需参数
        $msg_content = array();
        //创建array 所需元素
        $msg_content_elem = array(
            'MsgType' => 'TIMTextElem',       //文本类型
            'MsgContent' => array(
                'Text' => $text_content,                //hello 为文本信息
            )
        );
        array_push($msg_content, $msg_content_elem);
        $ret = $this->group_send_group_msg2($account_id, $group_id, $msg_content);
        return $ret;
    }

    function group_send_group_msg_pic($account_id, $group_id, $pic_path)
    {

        #构造高级接口所需参数
        //上传图片并获取url
        $busi_type = 1; //表示群消息
        $ret = $this->openpic_pic_upload($account_id, $group_id, $pic_path, $busi_type);
        $tmp = $ret["URL_INFO"];

        $uuid = $ret["File_UUID"];
        $pic_url = $tmp[0]["DownUrl"];

        $img_info = array();
        $img_tmp = $ret["URL_INFO"][0];
        if ($img_tmp["PIC_TYPE"] == 4) {
            $img_tmp["PIC_TYPE"] = 3;
        }
        $img_info_elem1 = array(
            "URL" => $img_tmp["DownUrl"],
            "Height" => $img_tmp["PIC_Height"],
            "Size" => $img_tmp["PIC_Size"],
            "Type" => $img_tmp["PIC_TYPE"],
            "Width" => $img_tmp["PIC_Width"]
        );

        $img_tmp = $ret["URL_INFO"][1];
        if ($img_tmp["PIC_TYPE"] == 4) {
            $img_tmp["PIC_TYPE"] = 3;
        }
        $img_info_elem2 = array(
            "URL" => $img_tmp["DownUrl"],
            "Height" => $img_tmp["PIC_Height"],
            "Size" => $img_tmp["PIC_Size"],
            "Type" => $img_tmp["PIC_TYPE"],
            "Width" => $img_tmp["PIC_Width"]
        );

        $img_tmp = $ret["URL_INFO"][2];
        if ($img_tmp["PIC_TYPE"] == 4) {
            $img_tmp["PIC_TYPE"] = 3;
        }
        $img_info_elem3 = array(
            "URL" => $img_tmp["DownUrl"],
            "Height" => $img_tmp["PIC_Height"],
            "Size" => $img_tmp["PIC_Size"],
            "Type" => $img_tmp["PIC_TYPE"],
            "Width" => $img_tmp["PIC_Width"]
        );

        array_push($img_info, $img_info_elem1);
        array_push($img_info, $img_info_elem2);
        array_push($img_info, $img_info_elem3);
        $msg_content = array();
        //创建array 所需元素
        $msg_content_elem = array(
            'MsgType' => 'TIMImageElem',       //文本类型
            'MsgContent' => array(
                'UUID' => $uuid,
                'ImageInfoArray' => $img_info,
            )
        );
        //将创建的元素$msg_content_elem, 加入array $msg_content
        array_push($msg_content, $msg_content_elem);

        $ret = $this->group_send_group_msg2($account_id, $group_id, $msg_content);
        return $ret;
    }

    function group_send_group_msg2($account_id, $group_id, $msg_content)
    {

        #构造新消息
        $msg = array(
            "GroupId" => $group_id,
            "From_Account" => $account_id,
            "Random" => rand(1, 65535),
            "MsgBody" => $msg_content
        );

        #将消息序列化为json串
        $req_data = json_encode($msg);

        $ret = $this->api("group_open_http_svc", "send_group_msg", $this->identifier, $this->userSign, $req_data);
        $ret = json_decode($ret, true);
        return $ret;
    }

    function group_send_group_system_notification($group_id, $text_content, $receiver_id)
    {

        #构造高级接口所需参数
        $receiver_list = [];
        if ($receiver_id != null) {
            array_push($receiver_list, $receiver_id);
        }
        $ret = $this->group_send_group_system_notification2($group_id, $text_content, $receiver_list);
        return $ret;
    }

    function group_send_group_system_notification2($group_id, $content, $receiver_list)
    {

        #构造新消息
        $msg = [
            "GroupId" => $group_id,
            "ToMembers_Account" => $receiver_list,
            "Content" => $content
        ];
        #将消息序列化为json串
        $req_data = json_encode($msg);

        $ret = $this->api("group_open_http_svc", "send_group_system_notification", $this->identifier, $this->userSign, $req_data);
        $ret = json_decode($ret, true);
        return $ret;
    }

    function comm_rest($server, $command, $req_body)
    {

        #将消息序列化为json串
        $req_data = json_encode($req_body);

        $ret = $this->api($server, $command, $this->identifier, $this->userSign, $req_data);
        $ret = json_decode($ret, true);
        return $ret;
    }

    function group_import_group_member($group_id, $member_id, $role)
    {

        #构造高级接口所需参数
        $member_list = [];
        $member_elem = [
            "Member_Account" => $member_id,
            "Role" => $role
        ];
        array_push($member_list, $member_elem);
        $ret = $this->group_import_group_member2($group_id, $member_list);
        return $ret;
    }

    function group_import_group_member2($group_id, $member_list)
    {

        #构造新消息
        $msg = array(
            "GroupId" => $group_id,
            "MemberList" => $member_list,
        );
        #将消息序列化为json串
        $req_data = json_encode($msg);

        $ret = $this->api("group_open_http_svc", "import_group_member", $this->identifier, $this->userSign, $req_data);
        $ret = json_decode($ret, true);
        return $ret;
    }

    function group_import_group_msg($group_id, $from_account, $text)
    {

        #构造高级接口所需参数

        //构造MsgBody
        $msg_content = array(
            "Text" => $text
        );
        $msg_body_elem = array(
            "MsgType" => "TIMTextElem",
            "MsgContent" => $msg_content,
        );
        $msg_body_list = array();
        array_push($msg_body_list, $msg_body_elem);

        //构造MsgList的一个元素
        $msg_list_elem = array(
            "From_Account" => $from_account,
            "SendTime" => time(),
            "Random" => rand(1, 65535),
            "MsgBody" => $msg_body_list
        );

        //构造MsgList
        $msg_list = array();
        array_push($msg_list, $msg_list_elem);

        $ret = $this->group_import_group_msg2($group_id, $msg_list);
        return $ret;
    }

    function group_import_group_msg2($group_id, $msg_list)
    {

        #构造新消息
        $msg = array(
            "GroupId" => $group_id,
            "MsgList" => $msg_list,
        );
        var_dump($msg);
        #将消息序列化为json串
        $req_data = json_encode($msg);

        $ret = $this->api("group_open_http_svc", "import_group_msg", $this->identifier, $this->userSign, $req_data);
        $ret = json_decode($ret, true);
        return $ret;
    }

    function group_set_unread_msg_num($group_id, $member_account, $unread_msg_num)
    {

        #构造新消息
        $msg = array(
            "GroupId" => $group_id,
            "Member_Account" => $member_account,
            "UnreadMsgNum" => (int)$unread_msg_num
        );
        #将消息序列化为json串
        $req_data = json_encode($msg);

        $ret = $this->api("group_open_http_svc", "set_unread_msg_num", $this->identifier, $this->userSign, $req_data);
        $ret = json_decode($ret, true);
        return $ret;
    }

}

