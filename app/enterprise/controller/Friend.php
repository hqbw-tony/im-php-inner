<?php

namespace app\enterprise\controller;

use app\BaseController;

use app\enterprise\model\{Friend as FriendModel,User};

class Friend extends BaseController
{
    // 好友申请列表
    public function index()
    {
        $param = $this->request->param();
        $map = [];
        $map[]=['is_invite','=',1];
        $isMine=$param['is_mine'] ?? 0;
        if($isMine){
            // 我发起的
            $map[]=['create_user','=',$this->uid];
        }else{
            // 我收到的
            $map[]=['friend_user_id','=',$this->uid];
        }
        $data=[];
        $model = new FriendModel();
        $list = $this->paginate($model->where($map)->orderRaw(FriendModel::getApplyTimeOrder()));
        if ($list) {
            $data = $list->toArray()['data'];
            $userList = User::matchUser($data, true, ['create_user','friend_user_id'], 120);
            foreach ($data as $k => $v) {
                $data[$k]['create_user_info'] = $userList[$v['create_user']] ?? [];
                $data[$k]['user_id_info'] = $userList[$v['friend_user_id']] ?? [];
                $data[$k]['is_group'] = 0;
            }
        }
        return success('', $data,$list->total(),$list->currentPage());
    }

    // 添加好友
    public function add()
    {
        $param = $this->request->param();
        $user_id=$param['user_id'] ?? 0;
        if(!$user_id){
            return warning(lang('system.notNull'));
        }
        if($user_id==$this->uid){
            return warning(lang('friend.notAddOwn'));
        }
        // 查看是否限制了好友上限
        if($this->userInfo['friend_limit']!=0 && $this->userInfo['role']==0){
            $myFriend=FriendModel::where(['create_user'=>$this->userInfo['user_id'],'status'=>1])->count();
            // 好友已达上限
            if($myFriend>$this->userInfo['friend_limit'] || $this->userInfo['friend_limit']<0){
               return warning(lang('friend.limit'));
            }
         }
        $friend=FriendModel::where(['friend_user_id'=>$user_id,'create_user'=>$this->uid])->find();
        if($friend){
            if($friend->status==1){
                return warning(lang('friend.already'));
            }elseif($friend->status==2){
                return warning(lang('friend.repeatApply'));
            }elseif($friend->status==3){
                return warning(lang('friend.refuse'));
            }
        }
        $status=2;
        $otherFriend=FriendModel::where(['friend_user_id'=>$this->uid,'create_user'=>$user_id])->find();
        if($otherFriend){
            if($otherFriend->status==3){
                return warning(lang('friend.refuse'));
            }
            if($otherFriend->status>0){
                $status=1;
            }
        }
        $applyTime=time();
        $data=[
            'friend_user_id'=>$user_id,
            'status'=>$status,
            'create_user'=>$this->uid,
            'remark'=>$param['remark'],
            'is_invite'=>1, // 是否为发起方
            'apply_time'=>$applyTime,
            'update_time'=>$applyTime
        ];
        if($friend){
            $friend->save($data);
        }else{
            $data['create_time']=$applyTime;
            $model = new FriendModel();
            $model->save($data);
        }
        $msg=[
            'fromUser'=>[
                'id'=>'system',
                'displayName'=>lang('friend.new'),
                'avatar'=>'',
            ],
            'toContactId'=>'system',
            'id'=>uniqid(),
            'is_group'=>2,
            'content'=>lang('friend.apply'),
            'status'=>'succeed',
            'sendTime'=>time()*1000,
            'type'=>'event',
            'fileSize'=>0,
            'fileName'=>'',
        ];
        // 发送好友申请
        wsSendMsg($user_id,'simple',$msg);
        return success(lang('system.addOk'));
    }

    // 接受或者拒绝好友申请
    public function update()
    {
        $param = $this->request->param();
        $friend=FriendModel::find($param['friend_id']);
        if(!$friend){
            return warning(lang('friend.notApply'));
        }
        $map=[
            'friend_id'=>$param['friend_id']
        ];
        FriendModel::where($map)->update(['status'=>$param['status']]);
        // 如果是接收，就添加到好友列表
        if($param['status']){
            $data=[
                'friend_user_id'=>$friend->create_user,
                'create_user'=>$this->uid,
            ];
            $newFriend=FriendModel::where($data)->find();
            if($newFriend){
                if($newFriend->status==1){
                    return success(lang('friend.already'));
                }
                FriendModel::where($data)->update(['status'=>1]);
            }else{
                $data['status']=1;
                FriendModel::create($data);
            }
            $content=lang('friend.newChat');
            $userM=new User;
            // 将对方的信息发送给我，把我的信息发送对方
            $user=$userM->setContact($friend->create_user,0,'event',$content);
            if($user){
                wsSendMsg($this->uid,'appendContact',$user);
            }
            $myInfo=$userM->setContact($this->uid,0,'event',$content);
            if($myInfo){
                wsSendMsg($friend->create_user,'appendContact',$myInfo);
            }
            
        }
        return success(lang('system.success'));
    }


    // 删除好友
    public function del()
    {
        $param = $this->request->param();
        $map=['friend_user_id'=>$param['id'],'create_user'=>$this->uid,'status'=>1];
        $friend=FriendModel::where($map)->find();
        if(!$friend){
            return warning(lang('friend.not'));
        }
        $is_black=$param['is_black'] ?? 0;
        // 如果是加入黑名单，则更新状态为3，禁止加好友
        if($is_black==1){
           FriendModel::where($map)->update(['status'=>3]);
           FriendModel::where(['friend_user_id'=>$this->uid,'create_user'=>$param['id']])->update(['status'=>3]);
        }else{
            // 只解除双方好友关系，保留 is_invite=1 的申请历史
            FriendModel::where($map)->update(['status'=>0]);
            FriendModel::where(['friend_user_id'=>$this->uid,'create_user'=>$param['id'],'status'=>1])->update(['status'=>0]);
        }
        // 性质和删除群聊一样
        wsSendMsg($param['id'],'removeGroup',['group_id'=>$this->uid]);
        return success(lang('system.delOk'));
    }

    // 设置好友备注
    public function setNickname()
    {
        $param = $this->request->param();
        if(!$param['nickname']){
            return warning(lang('system.notNull'));
        }
        FriendModel::update(['nickname'=>$param['nickname']],['friend_id'=>$param['friend_id']]);
        return success(lang('system.editOk'));
    }

    // 获取最新的一条和申请的总数
    public function getApplyMsg(){
        $model = new FriendModel();
        $map[]=['friend_user_id','=',$this->uid];
        $map[]=['status','=',2];
        $count=$model->where($map)->count();
        return success('', $count);
    }

}
