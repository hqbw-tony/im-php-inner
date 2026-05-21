<?php
/**
 * raingad IM [ThinkPHP6]
 * @author xiekunyu <raingad@foxmail.com>
 */
namespace app\enterprise\model;

use app\BaseModel;
class ChatDelog extends BaseModel
{
    public static function normalizeToUser($to_user,$is_group=0)
    {
        $to_user=trim((string)$to_user);
        if($to_user===''){
            return '';
        }
        if(strpos($to_user,'group-')===0){
            $group_id=explode('-',$to_user,2)[1] ?? '';
            return $group_id !== '' ? 'group-'.$group_id : '';
        }
        if((int)$is_group===1){
            return 'group-'.$to_user;
        }
        if(strpos($to_user,'user-')===0){
            $to_user=substr($to_user,5);
        }
        return is_numeric($to_user) ? (string)(int)$to_user : $to_user;
    }

    protected static function normalizeList($list,$is_group=0)
    {
        $data=[];
        foreach($list as $value){
            $value=self::normalizeToUser($value,$is_group);
            if($value !== ''){
                $data[]=$value;
            }
        }
        return array_values(array_unique($data));
    }

    protected static function normalizeCacheData($data)
    {
        return [
            'userList'=>self::normalizeList($data['userList'] ?? [],0),
            'groupList'=>self::normalizeList($data['groupList'] ?? [],1),
        ];
    }

    protected static function contactVariants($to_user,$is_group=0)
    {
        $to_user=trim((string)$to_user);
        $normal=self::normalizeToUser($to_user,$is_group);
        if($normal===''){
            return [];
        }
        $data=[$normal];
        if((int)$is_group===1 && strpos($normal,'group-')===0){
            $data[]=substr($normal,6);
        }
        if((int)$is_group===0){
            $data[]='user-'.$normal;
        }
        if($to_user !== ''){
            $data[]=$to_user;
        }
        return array_values(array_unique($data));
    }

    public static function updateCache($user_id)
    {
        $userList=self::normalizeList(self::where(['user_id'=>$user_id,'is_group'=>0])->column('to_user'),0);
        $groupList=self::normalizeList(self::where(['user_id'=>$user_id,'is_group'=>1])->column('to_user'),1);
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
        return self::normalizeCacheData($data ? : []);
    }

    public static function isDeleted($user_id,$to_user,$is_group=0)
    {
        $to_user=self::normalizeToUser($to_user,$is_group);
        if($to_user===''){
            return false;
        }
        $data=self::getCache($user_id);
        $key=(int)$is_group===1 ? 'groupList' : 'userList';
        return in_array($to_user,$data[$key] ?? [],true);
    }

    public static function deleteContact($user_id,$to_user,$is_group=0)
    {
        $values=self::contactVariants($to_user,$is_group);
        if(!$values){
            return false;
        }
        self::where(['user_id'=>$user_id,'is_group'=>(int)$is_group])->where('to_user','in',$values)->delete();
        self::updateCache($user_id);
        return true;
    }
}
