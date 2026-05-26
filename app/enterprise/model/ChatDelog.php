<?php
/**
 * raingad IM [ThinkPHP6]
 * @author xiekunyu <raingad@foxmail.com>
 */
namespace app\enterprise\model;

use app\BaseModel;
class ChatDelog extends BaseModel
{
    // delete_time 字段用于保存删除时的最后一条 msg_id，作为当前用户的会话可见边界。
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

    protected static function normalizeBoundaryList($list,$is_group=0)
    {
        $data=[];
        foreach($list as $key=>$value){
            if(is_array($value)){
                $to_user=$value['to_user'] ?? '';
                $delete_time=$value['delete_time'] ?? 0;
            }else{
                $to_user=$key;
                $delete_time=$value;
            }
            $to_user=self::normalizeToUser($to_user,$is_group);
            if($to_user !== ''){
                $data[$to_user]=(int)$delete_time;
            }
        }
        return $data;
    }

    protected static function isOldCacheData($data)
    {
        foreach(['userList','groupList'] as $key){
            $list=$data[$key] ?? [];
            if($list && array_key_exists(0,$list)){
                return true;
            }
        }
        return false;
    }

    protected static function normalizeCacheData($data)
    {
        return [
            'userList'=>self::normalizeBoundaryList($data['userList'] ?? [],0),
            'groupList'=>self::normalizeBoundaryList($data['groupList'] ?? [],1),
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
        $userList=self::normalizeBoundaryList(self::where(['user_id'=>$user_id,'is_group'=>0])->column('delete_time','to_user'),0);
        $groupList=self::normalizeBoundaryList(self::where(['user_id'=>$user_id,'is_group'=>1])->column('delete_time','to_user'),1);
        $data=['userList'=>$userList,'groupList'=>$groupList];
        cache('chat_delog_'.$user_id,$data,86400);
    }

    // 获取缓存
    public static function getCache($user_id)
    {
        $data=cache('chat_delog_'.$user_id);
        if(!$data || self::isOldCacheData($data)){
            self::updateCache($user_id);
            $data=cache('chat_delog_'.$user_id);
        }
        return self::normalizeCacheData($data ? : []);
    }

    public static function getDeleteMap($user_id,$is_group=0)
    {
        $data=self::getCache($user_id);
        $key=(int)$is_group===1 ? 'groupList' : 'userList';
        $list=$data[$key] ?? [];
        $hasZero=false;
        foreach($list as $to_user=>$deleteMsgId){
            if((int)$deleteMsgId===0){
                $list[$to_user]=self::initDeleteBoundary($user_id,$to_user,$is_group);
                $hasZero=true;
            }
        }
        if($hasZero){
            self::updateCache($user_id);
            $data=self::getCache($user_id);
            $list=$data[$key] ?? [];
        }
        return $list;
    }

    public static function isDeleted($user_id,$to_user,$is_group=0)
    {
        $to_user=self::normalizeToUser($to_user,$is_group);
        if($to_user===''){
            return false;
        }
        $data=self::getCache($user_id);
        $key=(int)$is_group===1 ? 'groupList' : 'userList';
        return array_key_exists($to_user,$data[$key] ?? []);
    }

    protected static function latestMsgId($user_id,$to_user,$is_group=0)
    {
        $to_user=self::normalizeToUser($to_user,$is_group);
        if($to_user===''){
            return 0;
        }
        $chat_identify=(int)$is_group===1 ? $to_user : chat_identify($user_id,$to_user);
        return (int)Message::where(['chat_identify'=>$chat_identify,'status'=>1])->max('msg_id');
    }

    protected static function initDeleteBoundary($user_id,$to_user,$is_group=0)
    {
        $to_user=self::normalizeToUser($to_user,$is_group);
        if($to_user===''){
            return 0;
        }
        $deleteMsgId=self::latestMsgId($user_id,$to_user,$is_group);
        if($deleteMsgId<=0){
            $deleteMsgId=-1;
        }
        self::where(['user_id'=>$user_id,'is_group'=>(int)$is_group])
            ->where('to_user','in',self::contactVariants($to_user,$is_group))
            ->update(['to_user'=>$to_user,'delete_time'=>$deleteMsgId,'status'=>1]);
        return $deleteMsgId;
    }

    public static function getDeleteMsgId($user_id,$to_user,$is_group=0)
    {
        $to_user=self::normalizeToUser($to_user,$is_group);
        if($to_user===''){
            return 0;
        }
        $deleteMap=self::getDeleteMap($user_id,$is_group);
        if(!array_key_exists($to_user,$deleteMap)){
            return 0;
        }
        $deleteMsgId=(int)$deleteMap[$to_user];
        if($deleteMsgId===0){
            $deleteMsgId=self::initDeleteBoundary($user_id,$to_user,$is_group);
            self::updateCache($user_id);
        }
        return $deleteMsgId;
    }

    public static function recordDelete($user_id,$to_user,$is_group=0)
    {
        $to_user=self::normalizeToUser($to_user,$is_group);
        if($to_user===''){
            return 0;
        }
        $deleteMsgId=self::latestMsgId($user_id,$to_user,$is_group);
        if($deleteMsgId<=0){
            $deleteMsgId=-1;
        }
        $map=['user_id'=>$user_id,'is_group'=>(int)$is_group];
        $chatDelog=self::where($map)->where('to_user','in',self::contactVariants($to_user,$is_group))->find();
        $data=[
            'user_id'=>$user_id,
            'is_group'=>(int)$is_group,
            'to_user'=>$to_user,
            'status'=>1,
            'delete_time'=>$deleteMsgId,
        ];
        if($chatDelog){
            $chatDelog->save($data);
        }else{
            self::create($data);
        }
        self::updateCache($user_id);
        return $deleteMsgId;
    }

    public static function ensureBoundaryBeforeNewMessage($user_id,$to_user,$is_group=0)
    {
        $to_user=self::normalizeToUser($to_user,$is_group);
        if($to_user===''){
            return;
        }
        $where=[
            'is_group'=>(int)$is_group,
            'delete_time'=>0,
        ];
        if($user_id){
            $where['user_id']=$user_id;
        }
        $query=self::where($where)->where('to_user','in',self::contactVariants($to_user,$is_group));
        $userIds=$query->column('user_id');
        if(!$userIds){
            return;
        }
        $deleteMsgId=self::latestMsgId($user_id,$to_user,$is_group);
        if($deleteMsgId<=0){
            $deleteMsgId=-1;
        }
        self::where($where)
            ->where('to_user','in',self::contactVariants($to_user,$is_group))
            ->update(['to_user'=>$to_user,'delete_time'=>$deleteMsgId,'status'=>1]);
        foreach(array_unique($userIds) as $uid){
            self::updateCache($uid);
        }
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
