<?php
/**
 * raingad IM [ThinkPHP6]
 * @author xiekunyu <raingad@foxmail.com>
 */
namespace app\enterprise\model;

use app\BaseModel;
class ChatDelog extends BaseModel
{
    public static function updateCache($user_id)
    {
        $userList=self::where(['user_id'=>$user_id,'is_group'=>0])->column('to_user');
        $groupList=self::where(['user_id'=>$user_id,'is_group'=>1])->column('to_user');
        $data=['userList'=>$userList,'groupList'=>$groupList];
        cache('chat_delog_'.$user_id,$data,86400);
    }

    // 获取缓存
    public static function getCache($user_id)
    {
        $data=cache('chat_delog_'.$user_id);
        if(!$data){
            self::updateCache($user_id);
            $data=cache('chat_delog_'.$user_id);
        }
        return $data;
    }
}