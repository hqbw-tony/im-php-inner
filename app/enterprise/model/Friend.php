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
   
}
