<?php

namespace app\enterprise\controller;

use app\BaseController;

use app\enterprise\model\{Friend as FriendModel,User,Message};

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
                $applyTime=time();
                FriendModel::acceptPair($this->uid,$user_id,$applyTime,[
                    'remark'=>$param['remark'] ?? '',
                    'is_invite'=>1,
                    'apply_time'=>$applyTime,
                ]);
                $this->pushAcceptedContacts($this->uid,$user_id);
                return success(lang('system.addOk'));
            }
        }
        $applyTime=time();
        $data=[
            'friend_user_id'=>$user_id,
            'status'=>$status,
            'create_user'=>$this->uid,
            'remark'=>$param['remark'] ?? '',
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
                'displayName'=>Message::renderI18n('friend.new',[],$user_id),
                'avatar'=>'',
            ],
            'toContactId'=>'system',
            'id'=>uniqid(),
            'is_group'=>2,
            'content'=>Message::renderI18n('friend.apply',[],$user_id),
            'status'=>'succeed',
            'sendTime'=>time()*1000,
            'type'=>'event',
            'fileSize'=>0,
            'fileName'=>'',
            'extends'=>Message::i18nExtends('friend.apply'),
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
        if((int)$friend->friend_user_id !== (int)$this->uid){
            return warning(lang('system.notAuth'));
        }
        // 如果是接收，就添加到好友列表
        if((int)$param['status'] === 1){
            $reverseFriend=FriendModel::where(['create_user'=>$this->uid,'friend_user_id'=>$friend->create_user])->find();
            if((int)$friend->status === 1 && $reverseFriend && (int)$reverseFriend->status === 1){
                return success(lang('friend.already'));
            }
            FriendModel::acceptPair($friend->create_user,$this->uid,time());
            $this->pushAcceptedContacts($this->uid,$friend->create_user);
        }else{
            FriendModel::where(['friend_id'=>$param['friend_id']])->update(['status'=>$param['status'],'update_time'=>time()]);
        }
        return success(lang('system.success'));
    }

    protected function pushAcceptedContacts($uid,$friendUserId)
    {
        $userInfo=User::field('user_id,realname,avatar')->where(['user_id'=>$uid])->find();
        if(!$userInfo){
            return;
        }
        $userInfo=$userInfo->toArray();
        $userInfo['id']=$userInfo['user_id'];
        $userInfo['avatar']=avatarUrl($userInfo['avatar'],$userInfo['realname'],$userInfo['user_id']);
        $msg=[
            'id'=>\utils\Str::getUuid(),
            'user_id'=>$uid,
            'content'=>Message::renderI18n('friend.newChat',[],$uid),
            'toContactId'=>$friendUserId,
            'sendTime'=>time()*1000,
            'type'=>'event',
            'is_group'=>0,
            'status'=>'succeed',
            'fromUser'=>$userInfo,
            'at'=>[],
            'extends'=>Message::i18nExtends('friend.newChat'),
        ];
        Message::sendMsg($msg);
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
