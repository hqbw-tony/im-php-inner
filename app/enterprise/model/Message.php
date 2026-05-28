<?php
/**
 * raingad IM [ThinkPHP6]
 * @author xiekunyu <raingad@foxmail.com>
 */
namespace app\enterprise\model;

use app\BaseModel;
use think\facade\Db;
use think\facade\Cache;
class Message extends BaseModel
{
    protected $pk="msg_id";
    protected $json      = ["extends"];
    protected $jsonAssoc = true;
    protected $errorData = '';
    protected static $fileType=['file','image','video','voice','emoji'];

    public function getErrorData()
    {
        return $this->errorData;
    }

    public static function getSearchContent($content,$type='',$fileName='')
    {
        if(in_array($type,self::$fileType)){
            return trim((string)$fileName);
        }
        if(is_array($content)){
            $content=json_encode($content,JSON_UNESCAPED_UNICODE);
        }
        $content=html_entity_decode(strip_tags((string)$content),ENT_QUOTES,'UTF-8');
        return trim($content);
    }

    public static function i18nExtends($key,$params=[],$extends=null)
    {
        if(is_string($extends)){
            $extends=json_decode($extends,true) ?: [];
        }
        if(!is_array($extends)){
            $extends=[];
        }
        $extends['i18n']=[
            'key'=>$key,
            'params'=>$params ?: [],
        ];
        return $extends;
    }

    public static function renderI18n($key,$params=[],$user_id=0,$language='')
    {
        $language=User::normalizeLanguage($language) ?: User::getUserLanguage($user_id);
        return lang($key,$params ?: [],$language);
    }

    public static function getI18nInfo($extends)
    {
        if(is_string($extends)){
            $extends=json_decode($extends,true) ?: [];
        }
        if(!is_array($extends) || empty($extends['i18n']['key'])){
            return [];
        }
        return [
            'key'=>$extends['i18n']['key'],
            'params'=>$extends['i18n']['params'] ?? [],
        ];
    }

    public static function renderEventContent($content,$extends,$user_id=0,$language='')
    {
        $i18n=self::getI18nInfo($extends);
        if(!$i18n){
            return $content;
        }
        return self::renderI18n($i18n['key'],$i18n['params'],$user_id,$language);
    }

    public static function renderMessageContent($message,$content='',$user_id=0)
    {
        $type=$message['type'] ?? '';
        if($type!='event'){
            return $content;
        }
        return self::renderEventContent($content,$message['extends'] ?? null,$user_id);
    }

    public static function renderSendDataForUser($data,$user_id)
    {
        if(($data['type'] ?? '')!='event'){
            return $data;
        }
        $data['content']=self::renderEventContent($data['content'] ?? '',$data['extends'] ?? null,$user_id);
        if(isset($data['contactInfo']) && is_array($data['contactInfo'])){
            $data['contactInfo']['lastContent']=$data['content'];
        }
        return $data;
    }

    public static function wsSendGroupI18n($group_id,$type,$data)
    {
        $i18n=self::getI18nInfo($data['extends'] ?? null);
        if(!$i18n || ($data['type'] ?? '')!='event'){
            wsSendMsg($group_id,$type,$data,1);
            return;
        }
        $userIds=GroupUser::where(['group_id'=>$group_id,'status'=>1])->column('user_id');
        foreach($userIds as $userId){
            wsSendMsg($userId,$type,self::renderSendDataForUser($data,$userId),0);
        }
    }

    // 添加聊天记录
    public static function addData($data){
       return Db::name('message')->insert($data);
    }

    // 更新消息状态
    public static function editData($update,$map){
        return Db::name('message')->where($map)->update($update);
    }

    // 查询聊天记录
    public static function getList($map,$where,$sort,$listRows,$pageSize){
        $list= Db::name('message')
        ->where($map)
        ->where($where)
        ->order($sort)
        ->paginate(['list_rows'=>$listRows,'page'=>$pageSize]);
        return $list;
     }

    // 查询@我的消息
    public static function getAtList($map,$where,$user_id){
        return Db::name('message')
            ->where($map)
            ->where($where)
            ->whereFindInSet('at',$user_id)
            ->order('msg_id desc')
            ->select()
            ->toArray();
    }

    // 查询群聊@我的未读消息
    public static function getAtMsgList($group_id,$user_id,$deleteMsgId=0){
        $where=[];
        if($deleteMsgId){
            $where[]=['msg_id','>',$deleteMsgId];
        }
        return self::getAtList(['to_user'=>$group_id,'is_group'=>1],$where,$user_id);
    }

    // 查询多个群聊@我的未读消息
    public static function getGroupAtMsg($group_ids,$user_id,$field='*',$deleteMap=[]){
        $list=Db::name('message')
            ->field($field)
            ->where([['to_user','in',$group_ids],['is_group','=',1]])
            ->whereFindInSet('at',$user_id)
            ->select();
        if(!$deleteMap){
            return $list;
        }
        $data=[];
        foreach($list as $v){
            $deleteMsgId=(int)($deleteMap['group-'.$v['to_user']] ?? 0);
            if(!$deleteMsgId || (int)($v['msg_id'] ?? 0)>$deleteMsgId){
                $data[]=$v;
            }
        }
        return $data;
    }

    // 查询多个群聊@我的未读数量
    public static function getGroupAtMsgCount($group_ids,$user_id,$deleteMap=[]){
        if(!$deleteMap){
            return Db::name('message')
                ->field('to_user,count(msg_id) as count')
                ->where([['to_user','in',$group_ids],['is_group','=',1]])
                ->whereFindInSet('at',$user_id)
                ->group('to_user')
                ->select();
        }
        $list=Db::name('message')
            ->field('to_user,msg_id')
            ->where([['to_user','in',$group_ids],['is_group','=',1]])
            ->whereFindInSet('at',$user_id)
            ->select();
        $data=[];
        foreach($list as $v){
            $groupKey='group-'.$v['to_user'];
            $deleteMsgId=(int)($deleteMap[$groupKey] ?? 0);
            if($deleteMsgId && (int)$v['msg_id']<=$deleteMsgId){
                continue;
            }
            if(!isset($data[$v['to_user']])){
                $data[$v['to_user']]=['to_user'=>$v['to_user'],'count'=>0];
            }
            ++$data[$v['to_user']]['count'];
        }
        return array_values($data);
    }

    // 发送消息
    public function sendMessage($param,$globalConfig=false){
        $is_group = $param['is_group'] ?? 0;
        $uid=self::$uid ? : ($param['user_id'] ?? 1);
        if($param['toContactId']==-1){
            $is_group=0;
        }
        // 如果是系统账号，直接禁言
        if($is_group>1){
            $this->error=lang('im.forbidChat');
            return false;
        }
        $isForward=$param['is_forward'] ?? 0;
        $sendInterval = $globalConfig['chatInfo']['sendInterval'] ?? 0;
        // 如果设置了消息频率则验证，转发不收限制
        if ($sendInterval && !$isForward) {
            if (Cache::has('send_' . $uid)) {
                $this->error=lang('im.sendTimeLimit',['time'=>$sendInterval]);
                return false;
            }
        }
        if($param['type']=='text'){
            // 限制文字内容长度
            $text = strip_tags($param['content']);
            $textLen = mb_strlen($text);
            if ($textLen > 2048) {
                $this->error=lang('im.msgContentLimit') . $textLen;
                return false;
            }
            $param['content'] = preg_link($param['content']);
            // 接入聊天内容检测服务
            event('GreenText',['content'=>$param['content'],'service'=>"chat_detection"]);
        }
        $chatSetting = $globalConfig['chatInfo'];
        if($param['toContactId']!=-1){
            if ($is_group == 0) {
                $kefuUser=$chatSetting['autoAddUser']['user_ids'] ?? [];
                $manageUser=User::where([['status','=',1],['role','>',0]])->column('user_id');
                $kefu=array_unique(array_merge($kefuUser,$manageUser));
                $csUid = self::$userInfo['cs_uid'] ?? 0;
                $manage=false;
                // 发送者和接受者是客服或者管理员也可以发送消息
                if(in_array($uid,$kefu) || in_array($param['toContactId'],$kefu)){
                    $manage=true;
                }
                if($chatSetting['simpleChat'] == 0 && !$manage){
                    $this->error=lang('im.forbidChat');
                    return false;
                }
                // 如果是单聊，并且是社区模式和不是自己的客服、需要判断是否是好友
                if ($globalConfig['sysInfo']['runMode'] == 2 && $csUid != $param['toContactId'] && !$manage) {
                    // 判断我是不是对方的客服
                    $cus = User::where(['user_id' => $param['toContactId']])->value('cs_uid');
                    if ($cus != $uid) {
                        $friend = Friend::where(['friend_user_id' => $uid, 'create_user' => $param['toContactId'], 'status' => 1])->find();
                        if (!$friend) {
                            $this->error=lang('im.notFriend');
                            return false;
                        }
                        $otherFriend = Friend::where(['friend_user_id' => $param['toContactId'], 'create_user' => $uid, 'status' => 1])->find();
                        if (!$otherFriend) {
                            $this->error=lang('im.friendNot');
                            return false;
                        }
                    }
                }
            }else{
                // 群聊必须群成员才能发送消息
                $group_id = explode('-', $param['toContactId'])[1] ?? '';
                if(!$group_id){
                    $this->error=lang('system.parameterError');
                    return false;
                }
                $group=Group::find($group_id);
                if(!$group){
                    $this->error=lang('group.exist');
                    $this->errorData=[
                        'reason'=>'group_dismissed',
                        'status'=>'group_dismissed',
                        'toContactId'=>$param['toContactId'],
                        'group_id'=>$group_id,
                    ];
                    return false;
                }
                if(!self::nospeak($group_id,$uid,$group)){
                    if($isForward){
                        return false;
                    }
                    return shutdown(lang('group.notSpeak'));
                }
                // 群聊必须群成员才能发送消息
                $groupUser=GroupUser::where(['user_id'=>$uid,'status'=>1,'group_id'=>$group_id,'delete_time'=>0])->find();
                if(!$groupUser){
                    $this->error = lang('group.notCustom');
                    return false;
                }
                if($groupUser['no_speak_time']>time()){
                    $this->error = lang('group.notSpeak',['time'=>date('Y-m-d H:i:s',$groupUser['no_speak_time'])]);
                    return false;
                }
            }
        }
        
        if ($sendInterval) {
            Cache::set('send_' . $uid, time(), $sendInterval);
        }
        return self::sendMsg($param,$is_group);
    }

    public function validateForwardTargets($userIds,$userInfo,$globalConfig)
    {
        $uid=(int)($userInfo['user_id'] ?? 0);
        if(!$uid || !is_array($userIds)){
            $this->error=lang('system.parameterError');
            return false;
        }
        foreach($userIds as $toContactId){
            $toContactId=(string)$toContactId;
            if($toContactId===''){
                $this->error=lang('system.parameterError');
                return false;
            }
            if(strpos($toContactId,'group')!==false || $toContactId==='-1'){
                continue;
            }
            if(!is_numeric($toContactId)){
                $this->error=lang('system.parameterError');
                return false;
            }
            if(!$this->checkSimpleForwardTarget($toContactId,$uid,$userInfo,$globalConfig)){
                return false;
            }
        }
        return true;
    }

    protected function checkSimpleForwardTarget($toContactId,$uid,$userInfo,$globalConfig)
    {
        $chatSetting=$globalConfig['chatInfo'] ?? [];
        $kefuUser=$chatSetting['autoAddUser']['user_ids'] ?? [];
        $manageUser=User::where([['status','=',1],['role','>',0]])->column('user_id');
        $kefu=array_unique(array_merge($kefuUser,$manageUser));
        $manage=false;
        if(in_array($uid,$kefu) || in_array($toContactId,$kefu)){
            $manage=true;
        }
        if(($chatSetting['simpleChat'] ?? 1) == 0 && !$manage){
            $this->error=lang('im.forbidChat');
            return false;
        }
        $friend=Friend::where(['friend_user_id'=>$toContactId,'create_user'=>$uid,'status'=>1])->find();
        if(!$friend){
            $this->error=lang('im.friendNot');
            return false;
        }
        $otherFriend=Friend::where(['friend_user_id'=>$uid,'create_user'=>$toContactId,'status'=>1])->find();
        if(!$otherFriend){
            $this->error=lang('im.notFriend');
            return false;
        }
        return true;
    }

    //实际发送消息
    public static function sendMsg($param,$is_group=0,$is_sys=0){
        $uid=self::$uid ?: ($param['user_id'] ?? 1);
        $toContactId=$param['toContactId'];
        $manage=[];
        if($is_group==1){
            $group_id = explode('-', $param['toContactId'])[1] ?? '';
            $chat_identify=$toContactId;
            $toContactId=$group_id;
            $manage=GroupUser::getGroupManage($group_id);
        }else{
            $chat_identify=chat_identify($param['user_id'],$toContactId);
        }
        if($is_group==1){
            ChatDelog::ensureBoundaryBeforeNewMessage(0,$param['toContactId'],1);
        }else{
            ChatDelog::ensureBoundaryBeforeNewMessage($uid,$toContactId,0);
            ChatDelog::ensureBoundaryBeforeNewMessage($toContactId,$uid,0);
        }
        $fileSzie=isset($param['file_size'])?$param['file_size']:'';
        $fileName=isset($param['file_name'])?$param['file_name']:'';
        $ossUrl=getDiskUrl();
        // 如果是转发图片文件的消息，必须把域名去除掉
        $content=$param['content'];
        if(in_array($param['type'],self::$fileType)){
            if(strpos($param['content'],$ossUrl)!==false){
                $content=str_replace($ossUrl,'',$param['content']);
            }
        }
        $param['content']=$content;
        $atList=($param['at'] ?? null) ? array_map('intval', $param['at']): [];
        // 如果at里面有0，代表@所有人
        if($atList && in_array(0,$atList)){
            $atList=GroupUser::where([['group_id','=',$toContactId],['status','=',1],['user_id','<>',$param['user_id']]])->column('user_id');
        }
        $at=$atList ? implode(',',$atList) : null;
        $data=[
            'from_user'=>$param['user_id'],
            'to_user'=>$toContactId,
            'id'=>$param['id'],
            'content'=>str_encipher($param['content'],true),
            'search_content'=>self::getSearchContent($param['content'],$param['type'],$fileName),
            'chat_identify'=>$chat_identify,
            'create_time'=>time(),
            'type'=>$param['type'],
            'status'=>1,
            'is_group'=>$toContactId==-1 ? 3 : $is_group,
            'is_read'=>$is_group ? 1 : 0,
            'file_id'=>$param['file_id'] ?? 0,
            "file_cate"=>$param['file_cate'] ?? 0,
            'file_size'=>$fileSzie,
            'file_name'=>$fileName,
            'at'=>$at,
            'pid'=>$param['pid'] ?? 0,
            'extends'=>($param['extends'] ?? null) ? $param['extends'] : null,
        ];
        $message=new self();
        $message->update(['is_last'=>0],['chat_identify'=>$chat_identify]);
        $message->save($data);
        
        // 拼接消息推送
        $type=$is_group?'group':'simple';
        $sendData=$param;
        $sendData['status']='succeed';
        $sendData['at']=$atList;
        $sendData['msg_id']=$message->msg_id;
        $sendData['is_read']=0;
        $sendData['to_user']=$toContactId;
        $sendData['role']=$manage[self::$uid] ?? 3;
        $sendData['sendTime']=(int)$sendData['sendTime'];
        if($is_group){
            $sendData['toContactId']=$param['toContactId'];
            // 将团队所有成员的未读状态+1
            GroupUser::editGroupUser([['group_id','=',$toContactId],['user_id','<>',$uid]],['unread'=>Db::raw('unread+1')]);
        }
        $sendData['fromUser']['id']=(int)$sendData['fromUser']['id'];
        $sendData['fileSize']=$fileSzie;
        $sendData['fileName']=$fileName;
        if(in_array($sendData['type'],self::$fileType)){
            $sendData['content']=getFileUrl($sendData['content']);
            if($sendData['type']=='image'){
                $pre=1;
            }else{
                $pre=2;
            }
            $sendData['preview']=previewUrl($sendData['content'],$pre);
            $sendData['extUrl']=getExtUrl($sendData['content']);
            $sendData['download']= $sendData['file_id'] ? getMainHost().'/filedown/'.encryptIds($sendData['file_id']) : '';
        }
        $sendData['toUser']=$param['toContactId'];
        $user=new User();
        // 将聊天窗口的联系人信息带上，方便临时会话
        
        if($is_group){
            $sendData['contactInfo']=$user->setContact($sendData['toContactId'],$is_group,$sendData['type'],$sendData['content']);
            self::wsSendGroupI18n($toContactId,$type,$sendData);
            return $sendData;
        }
        // 单聊需要按接收方和发送方自己的其他端分别组装 contactInfo，避免重建会话时头像视角错误。
        $receiverData=$sendData;
        $receiverData['toContactId']=$uid;
        $receiverData=self::renderSendDataForUser($receiverData,$toContactId);
        $receiverData['contactInfo']=$user->setContact($uid,0,$receiverData['type'],$receiverData['content']);
        wsSendMsg($toContactId,$type,$receiverData,0);
        $senderData=$sendData;
        $senderData['toContactId']=$param['toContactId'];
        $senderData=self::renderSendDataForUser($senderData,$uid);
        $senderData['contactInfo']=$user->setContact($param['toContactId'],0,$senderData['type'],$senderData['content']);
        wsSendMsg($uid,$type,$senderData,0,false);
        return $senderData;
}

    // 群禁言
    public static function nospeak($group_id,$user_id,$group=null){
        $group=$group ?: Group::find($group_id);
        if(!$group){
            return false;
        }
        if($group->owner_id==$user_id){
            return true;
        }
        if($group->setting){
            $setting=json_decode($group->setting,true);
            $nospeak=isset($setting['nospeak'])?$setting['nospeak']:0;
            $role=GroupUser::where(['group_id'=>$group_id,'user_id'=>$user_id])->value('role');
            if($nospeak==1 && $role>2){
                return false;
            }elseif($nospeak==2 && $role!=1){
                return false;
            }
        }
        return true;
    }

    // 将消息中的@用户交给队列近实时清理
    public static function setAtread($messages,$user_id){
        $msgIds=self::getAtReadMsgIds($messages,$user_id);
        if(!$msgIds){
            return true;
        }
        self::cacheAtRead($msgIds,$user_id);
        return queuePush(['action'=>'setAtRead','msg_ids'=>$msgIds,'user_id'=>$user_id]);
    }

    public static function clearAtRead($msgIds,$user_id){
        if(!$msgIds || !$user_id){
            return true;
        }
        if(!is_array($msgIds)){
            $msgIds=[$msgIds];
        }
        $msgIds=array_values(array_unique(array_filter(array_map('intval',$msgIds))));
        if(!$msgIds){
            return true;
        }
        foreach($msgIds as $msgId){
            $message=self::where('msg_id',$msgId)->value('at');
            $atList=($message ?? null) ? explode(',',$message): [];
            $newAtList=array_filter($atList,function($value) use ($user_id){
                $value=trim((string)$value);
                return $value !== '' && $value !== (string)$user_id;
            });
            if(count($newAtList)==count($atList)){
                continue;
            }
            self::where('msg_id',$msgId)->update(['at'=>implode(',',$newAtList)]);
        }
        return true;
    }

    public static function getAtReadMsgIds($messages,$user_id){
        $msgIds=[];
        if(!$messages || !$user_id || !is_array($messages)){
            return $msgIds;
        }
        foreach($messages as $k=>$v){
            if(!is_array($v) || !isset($v['at']) || !isset($v['msg_id'])){
                continue;
            }
            if($v['at'] && self::isAtUser($v['at'],$user_id)){
                $msgIds[]=(int)$v['msg_id'];
            }
        }
        return array_values(array_unique($msgIds));
    }

    protected static function isAtUser($at,$user_id){
        if(!is_array($at)){
            $at=explode(',',(string)$at);
        }
        foreach($at as $value){
            if(trim((string)$value) === (string)$user_id){
                return true;
            }
        }
        return false;
    }

    protected static function cacheAtRead($msgIds,$user_id){
        $atListQueue=Cache::get("atListQueue");
        if(!$atListQueue || !is_array($atListQueue)){
            $atListQueue=[];
        }
        foreach($msgIds as $msgId){
            $atListQueue[$msgId][]=$user_id;
        }
        Cache::set("atListQueue",$atListQueue);
    }

}
