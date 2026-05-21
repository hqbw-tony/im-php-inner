<?php

namespace app\enterprise\controller;

use app\BaseController;
use think\facade\Session;
use think\facade\Db;
use app\enterprise\model\{User, Message, GroupUser, Friend,Group,ChatDelog};
use GatewayClient\Gateway;
use Exception;
use League\Flysystem\Util;
use think\facade\Cache;

class Im extends BaseController
{
    protected $fileType = ['file', 'image','video','voice','emoji'];
    // 获取联系人列表
    public function getContacts()
    {
        $data = User::getUserList([['status', '=', 1], ['user_id', '<>', $this->userInfo['user_id']]], $this->userInfo['user_id']);
        $count=Friend::where(['status'=>2,'friend_user_id'=>$this->uid])->count();
        $time=Friend::where(['friend_user_id'=>$this->uid,'is_invite'=>1])->order('create_time desc')->value('create_time');
        return success('', $data,$count,$time*1000);
    }

    // 获取聊天列表
    public function getChatList()
    {
        $data = User::getChatList($this->userInfo['user_id']);
        $count=Friend::where(['status'=>2,'friend_user_id'=>$this->uid])->count();
        $time=Friend::where(['friend_user_id'=>$this->uid,'is_invite'=>1])->order('create_time desc')->value('create_time');
        return success('', $data,$count,$time*1000);
    }

    // 获取好友列表
    public function getFriendList()
    {
        $data = User::getFriendList([['status', '=', 1], ['user_id', '<>', $this->userInfo['user_id']]],$this->userInfo['user_id']);
        return success('', $data);
    }

    // 获取群聊列表
    public function getGroupList()
    {
        $data = User::getGroupList($this->userInfo['user_id']);
        return success('', $data);
    }

    // 获取单个人员的信息
    public function getContactInfo(){
        $id = $this->request->param('id');
        $is_group = is_string($id) ? 1 : 0;
        $user=new User;
        $data=$user->setContact($id,$is_group);
        if(!$data){
            return warning($user->getError());
        }
        return success('',$data);
    }


    //发送消息
    public function sendMessage()
    {
        $param = $this->request->param();
        $param['user_id'] = $this->userInfo['user_id'];
        $message=new Message();
        $data = $message->sendMessage($param,$this->globalConfig);
        if ($data) {
            return success('', $data);
        } else {
            return warning($message->getError(), $message->getErrorData());
        }
    }

    //转发消息
    public function forwardMessage()
    {
        $param = $this->request->param();
        $userIds=$param['user_ids'] ?? [];
        if(!$userIds || count($userIds)>5){
            return warning(lang('im.forwardLimit',['count'=>5]));
        }
        $msg_id=$param['msg_id'] ?? 0;
        $message=Message::find($msg_id);
        if(!$message){
            return warning(lang('im.exist'));
        }
        $msg=$message->toArray();
        $userInfo=$this->userInfo;
        queuePush([
            'action'=>'forwardMessage',
            'message'=>$msg,
            'user_ids'=>$userIds,
            'config'=>$this->globalConfig,
            'userInfo'=>$userInfo
        ]);
        return success(lang('im.forwardOk'));
    }

        // 获取用户信息
    public function getUserInfo()
    {
        $user_id = $this->request->param('user_id');
        $group_id = $this->request->param('group_id');
        $groupInfo=[];
        if($group_id){
            $group_id = explode('-', $group_id)[1];
            $groupInfo=Group::where(['group_id'=>$group_id])->find();
            if($groupInfo){
                // 查询操作对象的角色
                $groupInfo['userInfo']=GroupUser::where(['user_id'=>$user_id,'group_id'=>$group_id])->find() ?: [];
                // 查询我的角色
                $groupInfo['manageRole']=GroupUser::where(['user_id'=>$this->userInfo['user_id'],'group_id'=>$group_id])->value('role') ?: 3;
            }
        }
        $user=User::find($user_id);
        if(!$user){
            return error(lang('user.exist'));
        }
        $user->avatar=avatarUrl($user->avatar,$user->realname,$user->user_id,120);
        // 账号前面截取3位，后面截取两位，中间星号展示
        $user->account=substr($user->account, 0, 3).'******'.substr($user->account, -2, 2);
        // 查询好友关系
        $friend=Friend::where(['friend_user_id'=>$user_id,'create_user'=>$this->userInfo['user_id'],'status'=>1])->find();
        $user->friend=$friend ? : '';
        $location='';
        if($user->last_login_ip){
            $location=implode(" ", \Ip::find($user->last_login_ip));
        }
        $user->location=$location;
        $user->groupInfo=$groupInfo ? : [];
        $user->password='';
        $userModel=new User;
        $user->contactInfo=$userModel->setContact($user_id,0) ?? [];
        return success('', $user);
    }

    // 扫码登录，向客户端推送数据
    public function tokenLogin(){
        $param = $this->request->param();
        $token=$param['token'] ?? '';
        $client_id='';
        if($token){
            $client_id=authcode(urldecode($token),"DECODE", config('app.app_id'));
            if(!$client_id){
                 return warning(lang('user.loginError'));
            }
        }else{
            return warning(lang('user.loginError'));
        }
        $userInfo=$this->userInfo;
        if(!$userInfo){
            return warning(lang('user.loginError'));
        }
        //    如果用户已经有设置
        $setting=$userInfo['setting'] ?: '';
        if($setting){
            $setting['hideMessageName']= $setting['hideMessageName']=='true' ? true : false;
            $setting['hideMessageTime']= $setting['hideMessageTime']=='true' ? true : false;
            $setting['avatarCricle']= $setting['avatarCricle']=='true' ? true : false;
            $setting['isVoice']= $setting['isVoice']=='true' ? true : false;
            $setting['sendKey']=(int)$setting['sendKey'];
        }
        $userInfo['setting']=$setting;
         Gateway::$registerAddress = config('gateway.registerAddress');
        //如果登录信息中含有client——id则自动进行绑定
        if($client_id){
            $user_id=$userInfo['user_id'];
           
            // 如果当前ID在线，将其他地方登陆挤兑下线
            if(Gateway::isUidOnline($user_id)){
                wsSendMsg($user_id,'offline',['id'=>$user_id,'client_id'=>$client_id,'isMobile'=>false]);
            }
            Gateway::bindUid($client_id, $user_id);
            // 查询团队，如果有团队则加入团队
            $group=Group::getMyGroup(['gu.user_id'=>$user_id,'gu.status'=>1]);
            if($group){
                $group=$group->toArray();
                $group_ids=arrayToString($group,'group_id',false);
                foreach($group_ids as $v){
                    Gateway::joinGroup($client_id, $v); 
                }
            }
        }
        $update=[
            'last_login_time'=>time(),
            'last_login_ip'=>$this->request->ip(),
            'login_count'=>Db::raw('login_count+1')
        ];
        User::where('user_id',$userInfo['user_id'])->update($update);
        $authToken=User::refreshToken($userInfo,'web');
        $data=[
            'sessionId'=>Session::getId(),
            'authToken'=>$authToken,
            'userInfo'=>$userInfo
        ];
        Gateway::sendToClient($client_id, json_encode(array(
            'type' => 'tokenLogin',
            'data' => $data,
        )));
        return success(lang('user.loginOk'),$data);
    }

    // 搜索用户
    public function searchUser(){
        $keywords=$this->request->param('keywords','');
        if(!$keywords){
            return success('',[]);
        }
        $map=['status'=>1,'account'=>$keywords];
        $list=User::where($map)->field(User::$defaultField)->where([['account','<>',$this->userInfo['account']]])->select()->toArray();
        if($list){
            $ids=array_column($list,'user_id');
            $friendList=Friend::getFriend([['create_user','=',$this->uid],['friend_user_id','in',$ids]]);
            foreach($list as $k=>$v){
                $list[$k]['avatar']=avatarUrl($v['avatar'],$v['realname'],$v['user_id'],120);
                $list[$k]['friend']=$friendList[$v['user_id']] ?? '';
            }
        }
        return success('', $list);
    }

    // 获取系统所有人员加搜索
    public function userList(){
        $keywords=$this->request->param('keywords','');
        $listRows=$this->request->param('limit',20);
        $page=$this->request->param('page',1);
        $map=['status'=>1];
        $field="user_id,realname,avatar";
        if(!$keywords){
            $list=User::where($map)->field($field)->order('user_id asc')->limit(20)->paginate(['list_rows'=>$listRows,'page'=>$page]);;
            if($list){
                $list=$list->toArray()['data'];
            }
        }else{
            $list=User::where($map)->field($field)->where([['account','<>',$this->userInfo['account']]])->whereLike('account|realname|name_py','%'.$keywords.'%')->select()->toArray();
        }
        if($list){
            foreach($list as $k=>$v){
                $list[$k]['avatar']=avatarUrl($v['avatar'],$v['realname'],$v['user_id'],120);
                $list[$k]['id']=$v['user_id'];
            }
        }
        return success('', $list);
    }

    // 获取聊天记录
    public function getMessageList()
    {
        $param = $this->request->param();
        $is_group = isset($param['is_group']) ? $param['is_group'] : 0;
        $toContactId = (string)($param['toContactId'] ?? '');
        if($toContactId===''){
            return success('', [], 0);
        }
        if(strpos($toContactId,'group-')===0){
            $is_group = 1;
        }
        // 如果toContactId是数字，绝对是单聊
        $is_group = is_numeric($toContactId) ? 0 : $is_group;
        $type = isset($param['type']) ? $param['type'] : '';
        $is_at = isset($param['is_at']) ? $param['is_at'] : '';
        $where = [];
        $groupManage=[];
        // 群聊查询入群时间以后的消息
        if($is_group==1){
            $group_id = explode('-', $toContactId)[1] ?? '';
            if(!$group_id){
                return warning(lang('system.parameterError'));
            }
            $group=Group::where(['group_id'=> $group_id])->find();
            if(!$group){
                return warning(lang('group.exist'), [
                    'reason'=>'group_dismissed',
                    'status'=>'group_dismissed',
                    'toContactId'=>$toContactId,
                    'group_id'=>$group_id,
                ]);
            }
            $groupManage=GroupUser::getGroupManage($group_id);
            if($group['setting']){
                $groupSetting=json_decode($group['setting'],true);
                $history=$groupSetting['history'] ?? false;
                // 如果开启了历史记录才可以查看所有记录，否者根据进群时间查询记录
                if(!$history){
                    $createTime=GroupUser::where(['group_id'=> $group_id,'user_id'=>$this->userInfo['user_id']])->value('create_time');
                    $where[] = ['create_time', '>=', $createTime ? : 0];
                }
            }
        }
        // 设置当前聊天消息为已读
        $chat_identify = $this->setIsRead($is_group, $toContactId);
        $map = ['chat_identify' => $chat_identify, 'status' => 1];
        if ($type && $type != "all") {
            $map['type'] = $type;
        } else {
            if (isset($param['type'])) {
                $where[] = ['type', '<>', 'event'];
            }
        }
        $keywords = isset($param['keywords']) ? $param['keywords'] : '';
        if ($keywords && in_array($type, ['text', 'all'])) {
            $where[] = ['content', 'like', '%' . $keywords . '%'];
        }
        // 如果是查询@数据
        if($is_at){
            $atList=Message::getAtList($map,$where,$this->userInfo['user_id']);
            if($atList){
                $data = $this->recombileMsg($atList,false);
                Message::setAtread($data,$this->userInfo['user_id']);
                return success('', $data, count($data));
            }else{
                return success('', [], 0);
            }
        }
        $listRows = ($param['limit'] ?? 20) ?: 20;
        $pageSize = ($param['page'] ?? 1) ?: 1;
        $last_id = $param['last_id'] ?? 0;
        if($last_id){
            $where[]=['msg_id','<',$last_id];
            $pageSize=1;
        }
        $list = Message::getList($map, $where, 'msg_id desc', $listRows, $pageSize);
        $data = $this->recombileMsg($list,true,$groupManage);
        // 如果是群聊并且是第一页消息，需要推送@数据给用户
        if($is_group==1 && $pageSize==1){
            $isPush=Cache::get('atMsgPush'.$chat_identify) ?? '';
            $atList=Message::getAtList(['chat_identify'=>$chat_identify,'is_group'=>1],[],$this->userInfo['user_id']);
            $msgIda=array_column($atList,'msg_id');
            // 如果两次推送at数据的列表不一样，则推送
            if($isPush!=json_encode($msgIda)){
                $atData=$this->recombileMsg($atList,false);
                wsSendMsg($this->userInfo['user_id'],'atMsgList',[
                    'list'=>$atData,
                    'count'=>count($atData),
                    'toContactId'=>$toContactId
                ]);
                Cache::set('atMsgPush'.$chat_identify,json_encode($msgIda),60);
            }
        }
        // 如果是消息管理器则不用倒序
        if (!isset($param['type'])) {
            $data = array_reverse($data);
        }
        return success('', $data, $list->total());
    }

    // 获取群聊@我的未读消息
    public function getAtMsgList()
    {
        $param = $this->request->param();
        $toContactId = $param['toContactId'] ?? '';
        if (!$toContactId || strpos($toContactId, 'group-') !== 0) {
            return warning(lang('system.parameterError'));
        }
        $group_id = (int)str_replace('group-', '', $toContactId);
        if (!$group_id) {
            return warning(lang('system.parameterError'));
        }
        if(!GroupUser::checkGroupUser($group_id,$this->userInfo['user_id'])){
            return warning(lang('group.notCustom'));
        }
        $atList=Message::getAtMsgList($group_id,$this->userInfo['user_id']);
        $data=$atList ? $this->recombileMsg($atList,false) : [];
        return success('', [
            'count'=>count($atList),
            'list'=>$data
        ]);
    }

    // 获取单条消息详情
    public function getMessageInfo()
    {
        $param = $this->request->param();
        $id = $param['msg_id'] ?? 0;
        $message = Message::where(['msg_id' => $id])->find();
        if ($message) {
            $data = $this->recombileMsg([$message], false);
            return success('', $data);
        } else {
            return warning(lang('im.exist'));
        }
    }


    // 获取单条消息上下文
    public function getMessageContext()
    {
        $param = $this->request->param();
        $is_group = isset($param['is_group']) ? $param['is_group'] : 0;
        $id = $param['msg_id'] ?? 0;
        $direction = $param['direction'] ?? 0;
        $message = Message::where(['msg_id' => $id])->find();
        if (!$message) {
            return warning(lang('im.exist'));
        }
        $groupManage=[];
        $where = [];
        $map = ['chat_identify' => $message['chat_identify'], 'status' => 1];
        if($is_group==1 && $direction<2){
            $group_id = $message['to_user'];
            $group=Group::where(['group_id'=> $group_id])->find();
            $groupManage=GroupUser::getGroupManage($group_id);
            if($group && $group['setting']){
                $groupSetting=json_decode($group['setting'],true);
                $history=$groupSetting['history'] ?? false;
                // 如果开启了历史记录才可以查看所有记录，否者根据进群时间查询记录
                if(!$history){
                    $createTime=GroupUser::where(['group_id'=> $group_id,'user_id'=>$this->userInfo['user_id']])->value('create_time');
                    $where[] = ['create_time', '>=', $createTime ? : 0];
                }
            }
        }
        if($direction==0){
            $where[] = ['msg_id', '<', $id];
            $beforeList = Message::where($map)->where($where)->order('msg_id desc')->limit(5)->select()->toArray();
            $beforeList = array_reverse($beforeList);
            $where2 = [];
            $where2[] = ['msg_id', '>=', $id];
            $afterList = Message::where($map)->where($where2)->order('msg_id asc')->limit(5)->select()->toArray();
            $data = array_merge($beforeList, $afterList);
        }elseif($direction==1){
            $where[] = ['msg_id', '<', $id];
            $data = Message::where($map)->where($where)->order('msg_id desc')->limit(5)->select()->toArray();
            $data = array_reverse($data);
        }else{
            $where[] = ['msg_id', '>', $id];
            $data = Message::where($map)->where($where)->order('msg_id asc')->limit(5)->select()->toArray();
        }
        if($data){
            $data = $this->recombileMsg($data, false,$groupManage);  
        }
        return success('', $data);
    }

    protected function recombileMsg($list,$isPagination=true,$manage=[])
    {
        $data = [];
        $userInfo = $this->userInfo;
        if ($list) {
            $listData = $isPagination ? $list->toArray()['data'] : $list;
            $userList = User::matchUser($listData, true, 'from_user', 120);
            
            foreach ($listData as $k => $v) {
                // 屏蔽已删除的消息
                if ($v['del_user']) {
                    $delUser = explode(',', $v['del_user']);
                    if (in_array($userInfo['user_id'], $delUser)) {
                        unset($list[$k]);
                        continue;
                        // $v['type']="event";
                        // $v['content']="删除了一条消息";
                    }
                }
                $content = str_encipher($v['content'],false);
                $preview = '';
                $ext='';
                if (in_array($v['type'], $this->fileType)) {
                    $content = getFileUrl($content);
                    $preview = previewUrl($content);
                    $ext=getExtUrl($content);
                }
                
                $fromUser = $userList[$v['from_user']];
                // 处理撤回的消息
                if ($v['type'] == "event" && $v['is_undo']==1) {
                    if ($v['from_user'] == $userInfo['user_id']) {
                        $content = lang('im.you'). $content;
                    } elseif ($v['is_group'] == 1) {
                        $content = $fromUser['realname'] . $content;
                    } else {
                        $content = lang('im.other') . $content;
                    }
                }
                $toContactId=$v['is_group'] ==1 ?  'group-'.$v['to_user'] : $v['to_user'];
                $atList=($v['at'] ?? null) ? explode(',',$v['at']): [];
                $role=$manage[$v['from_user']] ?? 3;
                $data[] = [
                    'msg_id' => $v['msg_id'],
                    'id' => $v['id'],
                    'status' => "succeed",
                    'type' => $v['type'],
                    'sendTime' => (is_string($v['create_time']) ? strtotime($v['create_time']) : $v['create_time'])* 1000,
                    'content' => $content,
                    'preview' => $preview,
                    'download' => $v['file_id'] ? getMainHost().'/filedown/'.encryptIds($v['file_id']) : '',
                    'is_read' => $v['is_read'],
                    'is_group' => $v['is_group'],
                    'at' => $atList,
                    'toContactId' => $toContactId,
                    'from_user' => $v['from_user'],
                    'file_id' => $v['file_id'],
                    'file_cate' => $v['file_cate'],
                    'fileName' => $v['file_name'],
                    'fileSize' => $v['file_size'],
                    'fromUser' => $fromUser,
                    'extUrl'=>$ext,
                    'role'=>$role,
                    'extends'=>is_string($v['extends'])?json_decode($v['extends'],true) : $v['extends']
                ];
            }
        }
        return $data;
    }

    // 设置当前窗口的消息默认为已读
    public function setMsgIsRead()
    {
        $param = $this->request->param();
        
        // 判断是否是一个二维数组
        if (is_array($param['messages'][0] ?? '')) {
           $messages=$param['messages'];
        } else {
            $messages=[$param['messages']];
        }
        $this->setIsRead($param['is_group'], $param['toContactId'],$messages);
        if (!$param['is_group']) {
            wsSendMsg($param['fromUser'], 'isRead', $messages, 0);
        }
        return success('');
    }

    // 设置全部为已读
    public function readAllMsg()
    {
        // 阅读所有单聊
        $map = ['to_user' => $this->userInfo['user_id'], 'is_read' => 0, 'is_group' => 0];
        Message::where($map)->update(['is_read' => 1]);
        // 阅读所有群聊
        GroupUser::where(['user_id' => $this->userInfo['user_id'], 'status' => 1])->update(['unread'=>0]);
        return success('');
    }

    // 设置消息已读
    protected function setIsRead($is_group, $to_user,$messages=[])
    {
        if ($is_group==1) {
            $chat_identify = $to_user;
        } else if($is_group==0) {
            $chat_identify = chat_identify($this->userInfo['user_id'], $to_user);
        } else if($is_group==2){
            $chat_identify = $to_user; 
        }
        $data=[
            'action'=>'setIsRead',
            'is_group'=>$is_group,
            'to_user'=>$to_user,
            'messages'=>$messages,
            'user_id'=>$this->userInfo['user_id']
        ];
        queuePush($data,3);
        return $chat_identify;
    }

    // 聊天设置
    public function setting()
    {
        $param = $this->request->param();
        if ($param) {
            User::where(['user_id' => $this->userInfo['user_id']])->update(['setting' => $param]);
            return success('');
        }
        return warning('');
    }

    // 撤回消息
    public function undoMessage()
    {
        $param = $this->request->param();
        $id = $param['id'];
        $message = Message::where(['id' => $id])->find();
        if ($message) {
            // 如果时间超过了2分钟也不能撤回
            $createTime=is_string($message['create_time']) ? strtotime($message['create_time']) : $message['create_time'];
            $redoTime=$this->globalConfig['chatInfo']['redoTime'] ?? 120;
            if(time()-$createTime>$redoTime && $message['is_group']==0){
                return warning(lang('im.redoLimitTime',['time'=>floor($redoTime/60)]));
            }
            $text = lang('im.redo');
            $fromUserName = lang('im.other');
            $toContactId = $message['to_user'];
            if ($message['is_group'] == 1) {
                $fromUserName = $this->userInfo['realname'];
                $toContactId = explode('-', $message['chat_identify'])[1];
                // 如果是群聊消息撤回，需要判断是否是群主或者管理员，如果是则可以撤回
                if($message['from_user']!=$this->userInfo['user_id']){
                    $groupUser=GroupUser::where(['user_id'=>$this->userInfo['user_id'],'group_id'=>$toContactId])->find();
                    if(!$groupUser || !in_array($groupUser['role'],[1,2])){
                        return warning(lang('system.notAuth'));
                    }
                    $text=lang('im.manageRedo');
                }
            }
            $message->content = str_encipher($text);
            $message->type = 'event';
            $message->is_undo = 1;
            //@的数据清空
            $message->at = ''; 
            $message->save();
            $info = $message->toArray();
            // $data = $info;
            $data['content'] = $fromUserName . $text;
            $data['sendTime'] = $createTime * 1000;
            $data['id'] = $info['id'];
            $data['from_user'] = $info['from_user'];
            $data['msg_id'] = $info['msg_id'];
            $data['status'] = $info['status'];
            $data['type'] = 'event';
            $data['is_last'] = $info['is_last'];
            $data['toContactId'] = $message['is_group'] == 1 ? $info['chat_identify'] : $toContactId;
            $data['isMobile'] = $this->request->isMobile() ? 1 : 0;
            wsSendMsg($toContactId, 'undoMessage', $data, $info['is_group']); 
            if($info['is_group']==0){
               // 给自己也发一份推送，多端同步
                $data['content'] =lang('im.you'). $text;
                wsSendMsg($this->userInfo['user_id'], 'undoMessage', $data, $info['is_group']); 
            }
            return success('');
        } else {
            return warning();
        }
    }

    // 删除消息
    public function removeMessage()
    {
        $param = $this->request->param();
        $id = $param['id'];
        $map = ['id' => $id];
        $message = Message::where($map)->find();
        if ($message) {
            $message->del_user = $this->userInfo['user_id'];
            if ($message['is_group'] == 1) {
                if ($message['del_user']) {
                    $message->del_user .= ',' . $this->userInfo['user_id'];
                }
            } else {
                if ($message['del_user'] > 0) {
                    $message->where($map)->delete();
                    return success(lang('system.delOk'));
                }
            }
            $message->save();
            return success('');
        } else {
            return warning('');
        }
    }

    // 消息免打扰
    public function isNotice()
    {
        $param = $this->request->param();
        $user_id = $this->userInfo['user_id'];
        $id = $param['id'];
        if ($param['is_group'] == 1) {
            $group_id = explode('-', $param['id'])[1];
            GroupUser::update(['is_notice' => $param['is_notice']], ['user_id' => $user_id, 'group_id' => $group_id]);
        } else {
            $map = ['create_user' => $user_id, 'friend_user_id' => $id];
            $friend = Friend::where($map)->find();
            try {
                if ($friend) {
                    $friend->is_notice = $param['is_notice'];
                    $friend->save();
                } else {
                    $info = [
                        'create_user' => $user_id,
                        'friend_user_id' => $id,
                        'is_notice' => $param['is_notice']
                    ];
                    Friend::create($info);
                }
                return success('');
            } catch (Exception $e) {
                return error($e->getMessage());
            }
        }
        wsSendMsg($user_id,"setIsNotice",['id'=>$id,'is_notice'=>$param['is_notice'],'is_group'=>$param['is_group']]);
        return success('');
    }

    // 设置聊天置顶
    public function setChatTop()
    {
        $param = $this->request->param();
        $user_id = $this->userInfo['user_id'];
        $is_group = $param['is_group'] ?: 0;
        $id = $param['id'];
        
        try {
            if ($is_group == 1) {
                $group_id = explode('-', $param['id'])[1];
                GroupUser::update(['is_top' => $param['is_top']], ['user_id' => $user_id, 'group_id' => $group_id]);
            } else {
                $map = ['create_user' => $user_id, 'friend_user_id' => $id];
                $friend = Friend::where($map)->find();
                if ($friend) {
                    $friend->is_top = $param['is_top'];
                    $friend->save();
                } else {
                    $info = [
                        'create_user' => $user_id,
                        'friend_user_id' => $id,
                        'is_top' => $param['is_top']
                    ];
                    Friend::create($info);
                }
            }
            wsSendMsg($user_id,"setChatTop",['id'=>$id,'is_top'=>$param['is_top'],'is_group'=>$is_group]);
            return success('');
        } catch (Exception $e) {
            return error($e->getMessage());
        }
    }
    
    // 删除聊天
    public function delChat()
    {
        $param = $this->request->param();
        $user_id = $this->userInfo['user_id'];
        $is_group = $param['is_group'] ?: 0;
        $id = $param['id'];
        $data=[
            'user_id'=>$user_id,
            'is_group'=>$is_group,
            'to_user'=>$id
        ];
        ChatDelog::create($data);
        ChatDelog::updateCache($user_id);
        return success('');
    }

    // 向用户发送消息
    public function sendToMsg(){
        $param=$this->request->param();
        $event=$param['event'] ?? '';
        $allowEvents=['calling','acceptRtc','hangup','iceCandidate','offer','answer','busy'];
        if(!$event || !in_array($event,$allowEvents,true)){
            return error('Invalid call event');
        }
        if($event=='calling' && (!($param['toContactId'] ?? '') || !isset($param['type']) || ($param['type'] === '') || !($param['id'] ?? ''))){
            return error('Invalid call event');
        }
        $toContactId=$param['toContactId'] ?? '';
        $type=$param['type'] ?? '';
        if($event=='calling'){
            $status=3;
            $code=($param['code'] ?? '') ?: 901;
        }else{
            $status=$param['status'] ?? '';
            $code=$param['code'] ?? '';
        }
        $sdp=$param['sdp'] ?? '';
        $iceCandidate=$param['iceCandidate'] ?? '';
        $callTime=$param['callTime'] ?? '';
        $msg_id=$param['msg_id'] ?? '';
        $id=$param['id'] ?? '';
        // 如果该用户不在线，则发送忙线
        Gateway::$registerAddress = config('gateway.registerAddress');
        if(!Gateway::isUidOnline($toContactId)){
            $toContactId=$this->userInfo['user_id'];
            $code=907;
            $event='busy';
            sleep(1);
        }
        switch($code){
            case 902:
                $content=lang('webRtc.cancel');
                break;
            case 903:
                $content=lang('webRtc.refuse');
                break;
            case 905:
                $content=lang('webRtc.notConnected');
                break;
            case 906:
                $content=lang('webRtc.duration',['time'=>date("i:s",$callTime)]);
                break;
            case 907:
                $content=lang('webRtc.busy');
                break;
            case 908:
                $content=lang('webRtc.other');
                break;
            default:
                $content=$type==1 ?lang('webRtc.video') : lang('webRtc.audio');
                break;
        }
        switch($event){
            case 'calling':
                $content=$type==1 ?lang('webRtc.video'): lang('webRtc.audio');
                break;
            case 'acceptRtc':
                $content=lang('webRtc.answer');
                break;
            case 'iceCandidate':
                $content=lang('webRtc.exchange');
                break;
        }
        $userInfo=$this->userInfo;
        $userInfo['id']=$userInfo['user_id'];
        $user = new User();
        $data=[
            'id'=>$id,
            'msg_id'=>$msg_id,
            'sendTime'=>time()*1000,
            'toContactId'=>$toContactId,
            'content'=>$content,
            'type'=>'webrtc',
            'status'=>'succeed',
            'is_group'=>0,
            'is_read'=>0,
            'fromUser'=>$userInfo,
            'at'=>[],
            'extends'=>[
                'type'=>$type,    //通话类型，1视频，0语音。
                'status'=>$status, //，1拨打方，2接听方
                'event'=>$event,
                'callTime'=>$callTime,
                'sdp'=>$sdp,
                'code'=>$code,  //通话状态:呼叫901，取消902，拒绝903，接听904，未接通905，接通后挂断906，忙线907,其他端操作908
                'iceCandidate'=>$iceCandidate,
                'isMobile'=>$this->request->isMobile() ? 1 : 0,
            ]
        ];
        if($event=='calling'){
            $chat_identify=chat_identify($userInfo['id'],$toContactId);
            $msg=[
                'from_user'=>$userInfo['id'],
                'to_user'=>$toContactId,
                'id'=>$id,
                'content'=>str_encipher($content),
                'chat_identify'=>$chat_identify,
                'create_time'=>time(),
                'type'=>$data['type'],
                'is_group'=>0,
                'is_read'=>0,
                'extends'=>$data['extends'],
            ];
            $message=new Message();
            $message->update(['is_last'=>0],['chat_identify'=>$chat_identify]);
            $message->save($msg);
            $msg_id=$message->msg_id;
            $data['msg_id']=$msg_id;
            // 将接收人设置为发送人才能定位到该消息
            $data['toContactId']=$userInfo['id'];
            $data['toUser']=$toContactId;
        }elseif($event=='hangup'){
            $message=Message::where(['id'=>$id])->find();
            if(!$message){
                return error(lang('webRtc.fail'));
            }
            if($message){
                $message->content=str_encipher($content);
                $extends=$message->extends;
                $extends['code']=$code;
                $extends['callTime']=$callTime;
                $message->extends=$extends;
                $message->save();
            }
        }
        wsSendMsg($toContactId,'webrtc',$data);
        $wsData=$data;
        if(in_array($event,['calling','acceptRtc','hangup'])){
            if(in_array($event,['acceptRtc','hangup'])){
                $data['extends']['event']='otherOpt'; //其他端操作
            }
            $data['toContactId']=$toContactId;
            $data['contactInfo']=$user->setContact($toContactId,0,'webrtc',$content) ? : [];
            wsSendMsg($userInfo['id'],'webrtc',$data);
        }
        return success('',$wsData);
    }

    // 修改密码
    public function editPassword()
    {
        if(env('app.demon_mode',false)){
            return warning(lang('system.demoMode'));
        }
        
        $user_id = $this->userInfo['user_id'];
        $user=User::find($user_id);
        if(!$user){
            return warning(lang('user.exist'));
        }
        $account=$user->account;
        $code=$this->request->param('code','');
        $originalPassword = $this->request->param('originalPassword', '');
        if($code){
            if(Cache::get($account)!=$code){
                return warning(lang('user.codeErr'));
            }
        }elseif($originalPassword){
            if(password_hash_tp($originalPassword,$user->salt)!= $user->password){
                return warning(lang('user.passErr'));
            }
        }else{
            return warning(lang('system.parameterError'));
        }
        try{
            $password = $this->request->param('password','');
            if($password){
                $salt=$user->salt;
                $user->password= password_hash_tp($password,$salt);
            }
            $user->save();
            return success(lang('system.editOk'));
        }catch (\Exception $e){
            return error(lang('system.editFail'));
        }
    }

    // 修改用户信息
    public function updateUserInfo(){
        try{
            $data = $this->request->param();
            $user=User::find($this->uid);
            if(!$user){
                return warning(lang('user.exist'));
            }
            // 接入用户名检测服务
            event('GreenText',['content'=>$data['realname'],'service'=>"nickname_detection"]);
            // 个性签名检测服务
            event('GreenText',['content'=>$data['motto'],'service'=>"comment_detection"]);
            $user->realname =$data['realname'];
            $user->email =$data['email'];
            $user->motto=$data['motto'];
            $user->sex =$data['sex'];
            $user->name_py= pinyin_sentence($data['realname']);
            $user->save();
            return success(lang('system.editOk'), $data);
        }catch (\Exception $e){
            return error($e->getMessage());
        }
    }

    // 修改账户
    public function editAccount(){
        if(env('app.demon_mode',false)){
            return warning(lang('system.demoMode'));
        }
        $code=$this->request->param('code','');
        $newCode=$this->request->param('newCode','');
        $account=$this->request->param('account','');
        $isUser=User::where('account',$account)->find();
        if($isUser){
            return warning(lang('user.already'));
        }
        $user=User::find($this->uid);
        if(!$user){
            return warning(lang('user.exist'));
        }
        // 如果已经认证过了，则需要验证验证码
        if($user->is_auth){
            if(Cache::get($user->account)!=$code){
                return warning(lang('user.codeErr'));
            }
        }
        if(Cache::get($account)!=$newCode){
            return warning(lang('user.newCodeErr'));
        }
        try{
            $user->account=$account;
            $user->is_auth=1;
            $user->save();
            return success(lang('system.editOk'));
        }catch (\Exception $e){
            return error(lang('system.editFail'));
        }
    }

    // 阅读@消息
    public function readAtMsg(){
        $param = $this->request->param();
        $atList=Message::getAtList(['chat_identify'=>$param['toContactId'],'is_group'=>1],[],$this->userInfo['user_id']);
        $atData=$this->recombileMsg($atList,false);
        Message::setAtRead($atData,$this->userInfo['user_id']);
        // $message=Message::where('msg_id',$param['msg_id'])->select();
        // $atList=($message ?? null) ? explode(',',$message): [];
        // // 两个数组取差集
        // $newAtList = array_diff($atList, [$this->userInfo['user_id']]);
        // Message::where('msg_id',$param['msg_id'])->update(['at'=>implode(',',$newAtList)]);
        return success('');
    }

    // 获取系统公告
    public function getAdminNotice(){
        $data=Message::where(['chat_identify'=>'admin_notice'])->order('msg_id desc')->find();
        $extends=$data['extends'] ?? [];
        if(!$extends){
            $extends['title']='';
        }
        $createTime=$data['create_time'] ?? 0;
        if(!$createTime){
            $extends['create_time']=$createTime;
        }else{
            $extends['create_time']=is_string($data['create_time']) ? strtotime($data['create_time']) : $data['create_time'];
        }
       
        return success('',$extends);
    }

    // 双向删除消息
    public function delMessage(){
        $param = $this->request->param();
        $id = $param['id'];
        if(!$this->globalConfig['chatInfo']['dbDelMsg']){
            return warning(lang('system.notAuth'));
        }
        $message = Message::where(['id' => $id])->find();
        if ($message) {
            if($message['from_user']!=$this->userInfo['user_id']){
                return warning(lang('system.notAuth'));
            }
            Message::where(['id' => $id])->delete();
            // 如果是最后一条消息，需要将上一条设置为最后一条
            if($message['is_last']){
                Message::where(['chat_identify'=>$message['chat_identify']])->order('msg_id desc')->limit(1)->update(['is_last'=>1]);
            }
            $toContactId = $message['to_user'];
            if ($message['is_group'] == 1) {
                $toContactId = explode('-', $message['chat_identify'])[1];
            }
            wsSendMsg($toContactId, 'delMessage', $message, $message['is_group']); 
            return success('');
        } else {
            return warning(lang('im.exist'));
        }
    }
}
