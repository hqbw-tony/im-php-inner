<?php

namespace app\enterprise\controller;

use app\BaseController;
use app\enterprise\model\{User,Group as GroupModel,GroupUser,Message,Friend as FriendModel};
use think\Exception;
use think\facade\Db;
use app\common\controller\Upload;
use GatewayClient\Gateway;
use utils\Str;

class Group extends BaseController
{

   protected $setting=['manage' => 0, 'invite' => 1, 'nospeak' => 0];
      // 获取联系人列表
   public function getAllUser(){
      $param=$this->request->param();
      $user_ids=isset($param['user_ids'])?$param['user_ids']:[];
      $groupId=$param['group_id'] ?? '';
      $group_id='';
      if($groupId){
         $group_id=explode('-',$groupId)[1];
      }
      $data=User::getAllUser([['status','=',1],['user_id','<>',$this->userInfo['user_id']]],$user_ids,$this->uid,$group_id);
      return success('',$data);
   }

   // 获取群成员
   public function groupUserList()
   {
      $param = $this->request->param();
      try {
         $group_id = explode('-', $param['group_id'])[1];
         $listRows = $this->request->param('limit',0);
         $pageSize = $this->request->param('page',1);
         $map=['group_user.group_id' => $group_id,'group_user.status'=>1];
          $field="user_id,realname,account,avatar,name_py";
         if($listRows){
            $list=GroupUser::where($map)->order('role asc')->withJoin(['userInfo' => explode(',',$field)], 'LEFT')->paginate(['list_rows'=>$listRows,'page'=>$pageSize]);
            $data=$list->toArray()['data'];
            $count=$list->total();
         }else{
            $data=GroupUser::where($map)->order('role asc')
               ->withJoin(['userInfo' => explode(',',$field)], 'LEFT')
               ->select()
               ->toArray();
            $count=count($data);
         }
         $friendList=FriendModel::getFriend(['create_user'=>$this->uid,'status'=>1]);
         foreach($data as $k=>$v){
            $user=$v['userInfo'];
            $friend=$friendList[$user['user_id']] ?? [];
            $data[$k]['userInfo']['id']=$user['user_id'];
            $data[$k]['userInfo']['displayName']=User::getFriendDisplayName($friend,$user['realname']);
            $data[$k]['userInfo']['avatar']=avatarUrl($user['avatar'], $user['realname'], $user['user_id']);
            $data[$k]['realname']=$user['realname'];
            $data[$k]['name_py']=$user['name_py'];
         }
         return success('', $data,$count);
      } catch (Exception $e) {
         return error($e->getMessage());
      }
   }

   // 获取群基本信息
   public function groupInfo()
   {
      $param = $this->request->param();
      try {
         $jm='qr';
         $groupId=$param['group_id'] ?? '';
         $groupInfo = explode('-', $groupId);
         $group_id=$groupInfo[1];
         $group=GroupModel::find($group_id)->toArray();
         $userList=User::matchUser($group,false,'owner_id');
         $userCount=GroupUser::where(['group_id'=>$group_id,'status'=>1])->count();
         $userInfo=$userList[$group['owner_id']];
         $expire=time()+7*86400;
         $token=urlencode(authcode($this->uid.'-'.$group_id,'ENCODE', $jm,7*86400));
         $qrUrl=getMainHost().'/scan/g/'.$token;
         $group['id']=$groupId;
         $group['qrUrl']=$qrUrl;
         $group['qrExpire']=date('m-d',$expire);
         $group['userInfo']=$userInfo;
         $group['ownerName']=$userInfo['realname'];
         $group['groupUserCount']=$userCount;
         $group['displayName']=$group['name'];
         $group['avatar']=avatarUrl($group['avatar'],$group['name'],$group['group_id'],120);
         $group['setting']=$group['setting']?json_decode($group['setting'],true):['manage' => 0, 'invite' => 1, 'nospeak' => 0];
         $group['isJoin']=GroupUser::where(['group_id'=>$group_id,'user_id'=>$this->uid])->value('role') ?: 0;
         return success('', $group);
      } catch (Exception $e) {
         return error($e->getMessage());
      }
   }

   // 修改团队名称
   public function editGroupName()
   {
      $param = $this->request->param();
      $lang=User::resolveLanguage($this->uid,$this->request);
      \think\facade\Lang::switchLangSet($lang);
      $msg=function($key,$vars=[]) use($lang){
         return lang($key,$vars,$lang);
      };
      $group_id = explode('-', $param['id'])[1];
      $displayName=trim((string)($param['displayName'] ?? ''));
      if($displayName===''){
         return warning($msg('system.notNull'));
      }
      if(!GroupUser::canEditGroupInfo($group_id,$this->userInfo['user_id'])){
         return warning($msg('group.notAuth'));
      }
      $group=GroupModel::where(['group_id' => $group_id])->find();
      if(!$group){
         return warning($msg('group.exist'));
      }
      $oldName=(string)$group['name'];
      if($oldName===$displayName){
         return success($msg('system.editOk'));
      }
      GroupModel::where(['group_id' => $group_id])->update(['name' => $displayName,'name_py'=>pinyin_sentence($displayName)]);
      $param['displayName'] = $displayName;
      $param['oldDisplayName'] = $oldName;
      $param['editUserName'] = $this->userInfo['realname'];
      $param['editUserId'] = $this->uid;
      $action='editGroupName';
      event('GroupChange', ['action' => $action, 'group_id' => $group_id, 'param' => $param]);
      wsSendMsg($group_id, $action, $param, 1);
      return success($msg('system.editOk'));
   }

   // 添加群成员
   public function addGroupUser(){
      $param = $this->request->param();
      $uid=$this->userInfo['user_id'];
      $group_id = explode('-', $param['id'])[1] ?? 0;
      if(!$group_id){
         return warning(lang('system.exits'));
      }
      $groupInfo=GroupModel::where(['group_id'=>$group_id])->find();
      if(!$groupInfo){
         return warning(lang('system.exits'));
      }
      $user_ids=$param['user_ids'];
      $groupUserCount=GroupUser::where(['group_id'=>$group_id,'status'=>1])->count();
      if((count($user_ids) + $groupUserCount) > $this->chatSetting['groupUserMax'] && $this->chatSetting['groupUserMax']!=0){
         return warning(lang('group.userLimit',['userMax'=>$this->chatSetting['groupUserMax']]));
      }
      if(count($user_ids)>20){
         return warning(lang('group.inviteLimit',['limit'=>20]));
      }
      try{
         $joinedUserIds=[];
         foreach($user_ids as $k=>$v){
            $item=[
               'group_id'=>$group_id,
               'user_id'=>$v,
               'role'=>3,
               'invite_id'=>$uid
            ];
            $hasUser=GroupUser::where(['group_id'=>$group_id,'user_id'=>$v])->find();
            // 查询是否有人
            if(!$hasUser){
               GroupUser::create($item);
               $joinedUserIds[]=$v;
            }
            // 如果是黑名单用户，则更新状态为正常,只有群主才有重新拉回黑名单的人权限
            if($hasUser && $hasUser['status']==3 && $groupInfo['owner_id']==$uid){
               GroupUser::where(['group_id'=>$group_id,'user_id'=>$v])->update(['status'=>1,'invite_id'=>$uid]);
               $joinedUserIds[]=$v;
            }
         }
         $joinedUserIds=array_values(array_unique($joinedUserIds));
         if($joinedUserIds){
            $joinUserMap=User::where([['user_id','in',$joinedUserIds]])->column('realname','user_id');
            $joinNames=[];
            foreach($joinedUserIds as $joinUserId){
               if(!empty($joinUserMap[$joinUserId])){
                  $joinNames[]=$joinUserMap[$joinUserId];
               }
            }
            if($joinNames){
               $inviteJoinParams=['inviter'=>$this->userInfo['realname'],'username'=>implode('、',$joinNames)];
               $fromUser=$this->userInfo;
               $fromUser['id']=$uid;
               $fromUser['avatar']=avatarUrl($fromUser['avatar'],$fromUser['realname'],$uid);
               $msg=[
                  'id'=>Str::getUuid(),
                  'user_id'=>$uid,
                  'content'=>Message::renderI18n('group.inviteJoin',$inviteJoinParams,$uid),
                  'toContactId'=>'group-'.$group_id,
                  'sendTime'=>time()*1000,
                  'type'=>'event',
                  'is_group'=>1,
                  'status'=>'succeed',
                  'fromUser'=>$fromUser,
                  'at'=>[],
                  'extends'=>Message::i18nExtends('group.inviteJoin',$inviteJoinParams),
               ];
               Message::sendMsg($msg,1);
            }
         }
         // 给新成员添加新群聊信息
         $user=new User();
         $inviteParams=['username'=>$this->userInfo['realname']];
         $data=$user->setContact($group_id,1,'text',Message::renderI18n('group.invite',$inviteParams,$uid),$groupInfo);
         queuePush(['action'=>'createAvatar','group_id'=>$group_id]);
         foreach(array_values(array_unique($user_ids)) as $toUserId){
            $sendData=$data;
            $sendData['lastContent']=Message::renderI18n('group.invite',$inviteParams,$toUserId);
            wsSendMsg($toUserId, 'addGroup', $sendData);
         }
         return success(lang('system.addOk'));
      }catch(Exception $e){
         return error($e->getMessage());
      }
   }

      // 设置管理员
      public function setManager(){
         $param = $this->request->param();
         $uid=$this->userInfo['user_id'];
         $group_id = explode('-', $param['id'])[1];
         $user_id=$param['user_id'];
         $role=$param['role'];
         if(!GroupUser::checkAuth(['group_id'=>$group_id,'user_id'=>$uid])){
            return warning(lang('system.notAuth'));
         }
         $groupUser=GroupUser::where(['group_id'=>$group_id,'user_id'=>$user_id])->find();
         if($groupUser){
            $groupUser->role=$role;
            $groupUser->save();
            $avatar=GroupModel::where(['group_id'=>$group_id])->value('avatar');
            $url=avatarUrl($avatar);
            wsSendMsg($group_id,"setManager",['group_id'=>$param['id'],'user_id'=>$user_id,'avatar'=>$url],1);
            return success(lang('system.settingOk'));
         }else{
            return warning('');
         }
         
      }

      // 添加群聊
      public function add(){
         $param = $this->request->param();
         $uid=$this->userInfo['user_id'];
         $user_ids=$param['user_ids'] ?? [];
         if($this->chatSetting['groupChat']==0){
            return warning(lang('system.notAuth'));
         }
         // 查看是否限制了群聊创建的个数
         if($this->userInfo['group_limit']!=0 && $this->userInfo['role']==0){
            $myGroup=GroupModel::where(['owner_id'=>$uid])->count();
            // 群聊已达上限
            if($myGroup>$this->userInfo['group_limit'] || $this->userInfo['group_limit']<0){
               return warning(lang('group.limit'));
            }
         }
         if(count($user_ids)>$this->chatSetting['groupUserMax'] && $this->chatSetting['groupUserMax']!=0){
            return warning(lang('group.userLimit',['userMax'=>$this->chatSetting['groupUserMax']]));
         }
         // 管理员可以单独创建一个人的群
         if(count($user_ids)<=1 && $this->userInfo['role']>=2){
            return warning(lang('group.atLeast'));
         }
         // 将自己也加入群聊
         $user_ids[]=$uid;
         Db::startTrans();
         $setting=$this->setting;
         try{
            $create=[
               'create_user'=>$uid,
               'owner_id'=>$uid,
               'name'=>lang('group.name'),
               'name_py'=>"qunliao",
               'setting'=>json_encode($setting),
            ];
            $name=$param['name'] ?? '';
            if($name){
               $create['name']=$name;
               $create['name_py']=pinyin_sentence($name);
            }
            $group=new GroupModel();
            $group->save($create);
            $group_id=$group->group_id;
            $data=[];
            array_unique($user_ids);
            sort($user_ids);
            foreach($user_ids as $k=>$v){
               $info=[
                  'user_id'=>$v,
                  'invite_id'=>$uid,
                  'status'=>1,
                  'role'=>3,
                  'group_id'=>$group_id
               ];
               if($v==$uid){
                  $info['invite_id']=0;
                  $info['role']=1;
               }
               $data[]=$info;
            }
            $groupUser=new GroupUser();
            $groupUser->saveAll($data);
            $createGroupParams=['username'=>$this->userInfo['realname']];
            $createGroupContent=Message::renderI18n('group.add',$createGroupParams,$uid);
            $groupInfo=[
               'displayName'=>$create['name'],
               'owner_id'=>$create['owner_id'],
               'role'=>3,
               'name_py'=>$create['name_py'],
               'id'=>'group-'.$group_id,
               'avatar'=>avatarUrl('',$create['name'],$group_id,120,1),
               'is_group'=>1,
               'lastContent'=>$createGroupContent,
               'lastSendTime'=>time()*1000,
               'index'=>"[2]".lang('group.name'),
               'is_notice'=>1,
               'is_top'=>0,
               'setting'=>$setting,
            ];
            Message::create([
               'from_user'=>$uid,
               'to_user'=>$group_id,
               'content'=>str_encipher($createGroupContent),
               'search_content'=>Message::getSearchContent($createGroupContent,'event'),
               'type'=>'event',
               'extends'=>Message::i18nExtends('group.add',$createGroupParams),
               'is_group'=>1,
               'is_read'=>1,
               'is_last'=>1,
               'chat_identify'=>'group-'.$group_id
            ]);
            foreach($user_ids as $toUserId){
               $sendInfo=$groupInfo;
               $sendInfo['lastContent']=Message::renderI18n('group.add',$createGroupParams,$toUserId);
               wsSendMsg($toUserId, 'addGroup', $sendInfo);
            }
            Db::commit();
            $groupInfo['role']=1;
            queuePush(['action'=>'createAvatar','group_id'=>$group_id]);
            return success('',$groupInfo);
         }catch(Exception $e){
            Db::rollback();
            return error($e->getMessage());
         }
      }

      // 移除成员
      public function removeUser(){
         $param = $this->request->param();
         $uid=$this->userInfo['user_id'];
         $group_id = explode('-', $param['id'])[1];
         $groupInfo=GroupModel::where(['group_id'=>$group_id])->find();
         if(!$groupInfo){
            return warning(lang('group.exist'));
         }
         $user_id=$param['user_id'];
         $role=GroupUser::where(['group_id'=>$group_id,'user_id'=>$uid])->value('role');
         if($role>2 && $user_id!=$uid){
            return warning(lang('system.notAuth'));
         }
         $groupUser=GroupUser::where(['group_id'=>$group_id,'user_id'=>$user_id])->find();
         if(($groupUser && $groupUser['role']>$role) || $user_id==$uid){
             $is_black=$param['is_black'] ?? 0;
            // 如果是加入黑名单，则更新状态为3，禁止加入群聊
            if($is_black==1){
               GroupUser::where(['group_id'=>$group_id,'user_id'=>$user_id])->update(['status'=>3]);
            }else{
               GroupUser::destroy($groupUser->id);
            }
            Gateway::$registerAddress = config('gateway.registerAddress');
            wsSendMsg($group_id,"removeUser",['group_id'=>$param['id'],'avatar'=>avatarUrl($groupInfo['avatar'],$groupInfo['name']),'user_id'=>$user_id],1);
            $clientIds=Gateway::getClientIdByUid($user_id);
            // 解绑群组
            if($clientIds){
               foreach($clientIds as $k=>$v){
                  Gateway::leaveGroup($v, $group_id);
               }
            }
            // 更新群聊头像
            queuePush(['action'=>'createAvatar','group_id'=>$group_id]);
         }else{
            return warning(lang('system.notAuth'));
         }
         return success(lang('system.delOk'));
      }

      // 设置群成员禁言
      public function setNoSpeak(){
         $param = $this->request->param();
         $uid=$this->userInfo['user_id'];
         $group_id = explode('-', $param['id'])[1];
         $user_id=$param['user_id'];
         $role=GroupUser::where(['group_id'=>$group_id,'user_id'=>$uid])->value('role');
         if($role>2 && $user_id!=$uid){
            return warning(lang('system.notAuth'));
         }
         $groupUser=GroupUser::where(['group_id'=>$group_id,'user_id'=>$user_id])->find();
         if(!$groupUser){
            return warning(lang('system.notAuth'));
         }
         $noSpeakTimer=$param['noSpeakTimer'] ?? 0;
         $noSpeakList=[600,3600,10800,86400];
         $noSpeakDay=$param['noSpeakDay'] ?? 1;
         $noSpeakTime=$noSpeakDay*86400;
         if($noSpeakTimer>0){
            $noSpeakTime=$noSpeakList[$noSpeakTimer-1];
         }
         wsSendMsg($group_id,"setNoSpeak",['group_id'=>$param['id'],'user_id'=>$user_id],1);
         GroupUser::where(['group_id'=>$group_id,'user_id'=>$user_id])->update(['no_speak_time'=>time()+$noSpeakTime]);
         return success(lang('system.success'));
      }

      // 解散团队
      public function removeGroup(){
         $param = $this->request->param();
         $uid=$this->userInfo['user_id'];
         $group_id = explode('-', $param['id'])[1];
         $groupInfo=GroupModel::where(['group_id'=>$group_id])->find();
         if(!$groupInfo){
            return warning(lang('group.exist'));
         }
         $role=GroupUser::where(['group_id'=>$group_id,'user_id'=>$uid])->value('role');
         if($role>1){
            return warning(lang('system.notAuth'));
         }
         Db::startTrans();
         try{
            // 删除团队成员
            GroupUser::where(['group_id'=>$group_id])->delete();
            // 删除团队
            GroupModel::destroy($group_id);
            wsSendMsg($group_id,"removeGroup",['group_id'=>$param['id']],1);
            Db::commit();
            return success('');
         }catch(Exception $e){
            Db::rollback();
            return error($e->getMessage());
         }
      }

      // 设置公告
      public function setNotice(){
         $param = $this->request->param();
         $uid=$this->userInfo['user_id'];
         // 公告内容检测服务
         event('GreenText',['content'=>$param['notice'],'service'=>"comment_detection"]);
         $group_id = explode('-', $param['id'])[1];
         if($param['notice']==''){
            return warning(lang('system.notNull'));
         }
         if(!GroupUser::canEditGroupInfo($group_id,$uid)){
            return warning(lang('system.notAuth'));
         }
         GroupModel::update(['notice'=>$param['notice']],['group_id'=>$group_id]);
         $extends=Message::groupNoticeExtends($param['notice']);
         $msg=[
            'id'=>\utils\Str::getUuid(),
            'user_id'=>$uid,
            'content'=>Message::renderGroupNoticeContent($param['notice'],$uid),
            'toContactId'=>$param['id'],
            'sendTime'=>time()*1000,
            'type'=>'text',
            'is_group'=>1,
            'status'=>'succeed',
            'fromUser'=>$this->userInfo,
            'at'=>[0],
            'extends'=>$extends,
         ];
         $message=new Message();
         $data = $message->sendMessage($msg,$this->globalConfig);
         if (!$data) {
             return warning($message->getError());
         }
         return success('');
      }

      // 群聊设置
      public function groupSetting(){
         $param = $this->request->param();
         $uid=$this->userInfo['user_id'];
         $group_id = explode('-', $param['id'])[1];
         $role=GroupUser::where(['group_id'=>$group_id,'user_id'=>$uid])->value('role');
         if($role!=1){
            return warning(lang('system.notAuth'));
         }
         $setting=json_encode($param['setting']);
         GroupModel::update(['setting'=>$setting],['group_id'=>$group_id]);
         wsSendMsg($group_id,"groupSetting",['group_id'=>$param['id'],'setting'=>$param['setting']],1);
         return success('');
      }

      // 加入群
      public function joinGroup(){
         $param = $this->request->param();
         $uid=$this->userInfo['user_id'];
         try{
            $group_id = explode('-', $param['group_id'])[1];
            $inviteUid=$param['inviteUid'] ?? '';
            $groupUserCount=GroupUser::where(['group_id'=>$group_id,'status'=>1])->count();
            $groupUser=GroupUser::where(['group_id'=>$group_id,'user_id'=>$uid])->find();
            $groupInfo=GroupModel::where(['group_id'=>$group_id])->find();
            if(!$groupInfo){
               return warning(lang('group.exist'));
            }
             if($groupUser && $groupUser['status']==3){
               return success('你已被禁止加入该群');
            }
            if($groupUser){
               return warning(lang('group.alreadyJoin'));
            }
            
            if(($groupUserCount+1) > $this->chatSetting['groupUserMax'] && $this->chatSetting['groupUserMax']!=0){
               return warning(lang('group.userLimit',['userMax'=>$this->chatSetting['groupUserMax']]));
            }
            // 加入者的名称
            $groupInfo['joinerName']=$this->userInfo['realname'];
            GroupUser::joinGroup($uid,$inviteUid,$groupInfo);
            return success(lang('system.joinOk'));
         }catch(Exception $e){
            return error($e->getMessage());
         }
      }

   // 更换群主
    public function changeOwner()
    {
        $user_id = $this->request->param('user_id');
        $id = $this->request->param('id');
        $group_id = explode('-', $id)[1];
        $uid=$this->userInfo['user_id'];
        $group=GroupModel::where('group_id',$group_id)->find();
        if(!$group){
            return warning(lang('group.exist'));
        }
        $user=User::where('user_id',$user_id)->find();
        if(!$user){
            return warning(lang('user.exist'));
        }
        $role=GroupUser::where(['group_id'=>$group_id,'user_id'=>$uid])->value('role');
        if($role>1){
           return warning(lang('system.notAuth'));
        }
        Db::startTrans();
        try{
            GroupUser::where('group_id',$group_id)->where('user_id',$user_id)->update(['role'=>1]);
            GroupUser::where('group_id',$group_id)->where('user_id',$group->owner_id)->update(['role'=>3]);
            $group->owner_id=$user_id;
            $group->save();
            wsSendMsg($group_id,"changeOwner",['group_id'=>'group-'.$group_id,'user_id'=>$user_id],1);
            Db::commit();
            return success('');
        }catch (\Exception $e){
            Db::rollback();
            return warning('');
        }
    }

      // 清理群消息
      public function clearMessage()
      {
         $id = $this->request->param('id');
         $group_id = explode('-', $id)[1];
         $uid=$this->userInfo['user_id'];
         $group=GroupModel::where('group_id',$group_id)->find();
         if(!$group){
            return warning(lang('group.exist'));
         }
         $role=GroupUser::where(['group_id'=>$group_id,'user_id'=>$uid])->value('role');
         // 如果是群主或者后台管理员才有权限
         if($role>1 && $this->userInfo['role']==0){
            return warning(lang('system.notAuth'));
         }
         Db::startTrans();
         try{
            // 删除所有消息
            Message::where(['chat_identify'=>$id])->delete();
            // 该群聊的所有未读置为0
            GroupUser::where('group_id',$group_id)->update(['unread'=>0]);
            wsSendMsg($group_id,"clearMessage",['group_id'=>'group-'.$group_id],1);
            Db::commit();
            return success('');
         }catch (\Exception $e){
            Db::rollback();
            return warning('');
         }
      }
}
