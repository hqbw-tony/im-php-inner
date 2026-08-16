<?php
/**
 * Created by PhpStorm
 * User raingad@foxmail.com
 * Date 2022/12/14 17:24
 */
namespace app\manage\controller;
use app\BaseController;
use app\common\controller\Upload;
use app\enterprise\model\{User as UserModel,GroupUser,Group as GroupModel,Message};
use think\facade\Db;
use utils\Str;

class Group extends BaseController
{
    /**
     * 更新后台管理群聊的自定义头像，并通知全部群成员刷新本地群资料。
     */
    public function editAvatar()
    {
        $groupId = (int)$this->request->param('group_id', 0);
        $group = GroupModel::where('group_id', $groupId)->find();
        if (!$group) {
            return warning(lang('group.exist'));
        }

        $file = $this->request->file('file');
        if (!$file) {
            return warning(lang('system.notNull'));
        }

        $extension = strtolower((string)$file->extension());
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)
            || !@getimagesize($file->getRealPath())) {
            return warning(lang('file.typeNotSupport'));
        }

        try {
            $upload = new Upload();
            $fileInfo = $upload->upload([], $file, 'group-avatar/' . $groupId . '/');
            $avatar = (string)($fileInfo['src'] ?? '');
            if ($avatar === '') {
                return warning(lang('system.editFail'));
            }

            GroupModel::where('group_id', $groupId)->update([
                'avatar' => $avatar,
                'avatar_mode' => 1,
            ]);

            $avatarUrl = avatarUrl($avatar, $group['name'], $groupId, 120, 1);
            wsSendMsg($groupId, 'setManager', [
                'group_id' => 'group-' . $groupId,
                'avatar' => $avatarUrl,
            ], 1);
            return success(lang('system.editOk'), ['avatar' => $avatarUrl, 'avatar_mode' => 1]);
        } catch (\Exception $e) {
            return error($e->getMessage());
        }
    }

    // 获取群聊列表
    public function index()
    {
        $map = [];
        $model=new GroupModel();
        $param = $this->request->param();
        //搜索关键词
        if ($keyword = $this->request->param('keywords')) {
            $model = $model->whereLike('name|name_py', '%' . $keyword . '%');
        }
        // 排序
        $order='group_id DESC';
        if ($param['order_field'] ?? '') {
            $order = orderBy($param['order_field'],$param['order_type'] ?? 1);
        }
        $list = $this->paginate($model->where($map)->order($order));
        if ($list) {
            $data = $list->toArray()['data'];
            $userList=UserModel::matchUser($data,true,'owner_id',120);
            foreach($data as $k=>$v){
                $data[$k]['avatar']=avatarUrl($v['avatar'],$v['name'],$v['group_id'],120,1);
                $data[$k]['owner_id_info']=$userList[$v['owner_id']] ?? [];
            }
        }
        return success('', $data, $list->total(), $list->currentPage());
    }

    // 更换群主
    public function changeOwner()
    {
        $group_id = $this->request->param('group_id');
        $user_id = $this->request->param('user_id');
        $group=GroupModel::where('group_id',$group_id)->find();
        if(!$group){
            return warning(lang('group.exist'));
        }
        $user=UserModel::where('user_id',$user_id)->find();
        if(!$user){
            return warning(lang('user.exist'));
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

    // 解散群聊
    public function del()
    {
        $group_id = $this->request->param('group_id');
        $group=GroupModel::where('group_id',$group_id)->find();
        if(!$group){
            return warning(lang('group.exist'));
        }
        Db::startTrans();
        try{
            // 删除团队成员
            GroupUser::where('group_id',$group_id)->delete();
            // 删除团队
            GroupModel::destroy($group_id);
            wsSendMsg($group_id,"removeGroup",['group_id'=>'group-'.$group_id],1);
            Db::commit();
            return success('');
        }catch (\Exception $e){
            Db::rollback();
            return warning('');
        }
    }

    // 添加群成员
    public function addGroupUser(){
        $param = $this->request->param();
        $uid=$this->userInfo['user_id'];
        $group_id = $param['group_id'];
        $group=GroupModel::where('group_id',$group_id)->find();
        if(!$group){
            return warning(lang('group.exist'));
        }
        $user_ids=$param['user_ids'];
        $data=[];
        try{
            $joinedUserIds=[];
            foreach($user_ids as $k=>$v){
                $data[]=[
                    'group_id'=>$group_id,
                    'user_id'=>$v,
                    'role'=>3,
                    'invite_id'=>$uid
                ];
                $joinedUserIds[]=$v;
            }
            $groupUser=new GroupUser;
            $groupUser->saveAll($data);
            $joinedUserIds=array_values(array_unique($joinedUserIds));
            if($joinedUserIds){
                $joinUserMap=UserModel::where([['user_id','in',$joinedUserIds]])->column('realname','user_id');
                $joinNames=[];
                foreach($joinedUserIds as $joinUserId){
                    if(!empty($joinUserMap[$joinUserId])){
                        $joinNames[]=$joinUserMap[$joinUserId];
                    }
                }
                if($joinNames){
                    $fromUser=$this->userInfo;
                    $fromUser['id']=$uid;
                    $fromUser['avatar']=avatarUrl($fromUser['avatar'],$fromUser['realname'],$uid);
                    $msg=[
                        'id'=>Str::getUuid(),
                        'user_id'=>$uid,
                        'content'=>lang('group.join',['username'=>implode('、',$joinNames)]),
                        'toContactId'=>'group-'.$group_id,
                        'sendTime'=>time()*1000,
                        'type'=>'event',
                        'is_group'=>1,
                        'status'=>'succeed',
                        'fromUser'=>$fromUser,
                        'at'=>[],
                    ];
                    Message::sendMsg($msg,1);
                }
            }
            queuePush(['action'=>'createAvatar','group_id'=>$group_id]);
            return success(lang('system.addOk'));
        }catch(\Exception $e){
                return error($e->getMessage());
        }
        
    }

    // 删除群成员
    public function delGroupUser(){
        $param = $this->request->param();
        $group_id = $param['group_id'];
        $group=GroupModel::where('group_id',$group_id)->find();
        if(!$group){
            return warning(lang('group.exist'));
        }
        $user_id=$param['user_id'];
        $groupUser=GroupUser::where(['group_id'=>$group_id,'user_id'=>$user_id])->find();
        if($groupUser){
            $groupUser->delete();
            wsSendMsg($group_id,"removeUser",['group_id'=>'group-'.$group_id],1);
            return success('');
        }else{
            return warning('');
        }
        
    }

    // 设置管理员
    public function setManager(){
       $param = $this->request->param();
       $group_id = $param['group_id'];
        $group=GroupModel::where('group_id',$group_id)->find();
        if(!$group){
            return warning(lang('group.exist'));
        }
       $user_id=$param['user_id'];
       $role=$param['role'];
       $groupUser=GroupUser::where(['group_id'=>$group_id,'user_id'=>$user_id])->find();
       if($groupUser){
          $groupUser->role=$role;
          $groupUser->save();
          wsSendMsg($group_id,"setManager",['group_id'=>'group-'.$group_id],1);
          return success('');
       }else{
          return warning('');
       }
       
    }


}
