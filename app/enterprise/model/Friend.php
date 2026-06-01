<?php
/**
 * raingad IM [ThinkPHP6]
 * @author xiekunyu <raingad@foxmail.com>
 */
namespace app\enterprise\model;

use app\BaseModel;
use think\facade\Db;

class Friend extends BaseModel
{
    protected $pk="friend_id";
    protected static $applyTimeOrder='IF(apply_time > 0, apply_time, create_time) desc, friend_id desc';
    

    public static function getFriend($map){
       $list=self::where($map)->select();
       $data=[];
       if($list){
          $list=$list->toArray();
          foreach($list as $k=>$v){
             $data[$v['friend_user_id']]=$v;
          }
       }
       return $data;
    }

    public static function getApplyTimeOrder()
    {
       return self::$applyTimeOrder;
    }

    public static function getApplyLastTime($user_id)
    {
       $info=self::where(['friend_user_id'=>$user_id,'is_invite'=>1])
          ->field('apply_time,create_time')
          ->orderRaw(self::$applyTimeOrder)
          ->find();
       if(!$info){
          return 0;
       }
       $applyTime=(int)($info['apply_time'] ?? 0);
       $createTime=(int)($info['create_time'] ?? 0);
       return $applyTime ?: $createTime;
    }

    public static function acceptPair($create_user,$friend_user_id,$time=0,$createUserData=[],$friendUserData=[])
    {
       $time=$time ?: time();
       self::saveRelation($create_user,$friend_user_id,array_merge([
          'status'=>1,
          'update_time'=>$time,
       ],$createUserData));
       self::saveRelation($friend_user_id,$create_user,array_merge([
          'status'=>1,
          'update_time'=>$time,
       ],$friendUserData));
    }

    public static function saveRelation($create_user,$friend_user_id,$data=[])
    {
       $map=[
          'create_user'=>$create_user,
          'friend_user_id'=>$friend_user_id,
       ];
       $relation=self::where($map)->find();
       $data=array_merge($map,$data);
       if($relation){
          $relation->save($data);
          return $relation;
       }
       if(!isset($data['create_time'])){
          $data['create_time']=time();
       }
       return self::create($data);
    }
   
}
