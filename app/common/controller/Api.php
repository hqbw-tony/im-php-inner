<?php

namespace app\common\controller;

use think\App;
use app\common\model\{ThirdPlatform,ThirdUserMap};
use app\enterprise\model\{User,Friend,Message};
use think\facade\Cache;
use think\facade\Db;
use app\manage\model\Config;


/**
 * API接口类
 */
class Api
{
    /**
     * Request实例
     * @var \think\Request
     */
    protected $request;

    /**
     * 应用实例
     * @var \think\App
     */
    protected $app;

    protected $middleware=['apiAuth'];

    /**
     * 构造方法
     * @access public
     * @param  App  $app  应用对象
     */
    public function __construct(App $app)
    {
        $this->app     = $app;
        $this->request = $this->app->request;
    }

    // 创建用户
    public function createUser()
    {
        $data = $this->request->param();
        if(!isset($data['account']) || !isset($data['realname'])){
            return warning(lang('system.parameterError'));
        }
        $user=new User();
        $verify=$user->checkAccount($data);
        if(!$verify){
            return success(lang('user.exist'));
        }
        $salt=\utils\Str::random(4);
        $data['password'] = password_hash_tp(rand(100000,999999),$salt);
        $data['salt'] =$salt;
        $data['register_ip'] =$this->request->ip();
        $data['name_py'] = pinyin_sentence($data['realname']);
        $user->save($data);
        $data['user_id']=$user->user_id;
        $data['open_id']=encryptIds($user->user_id);
        // 监听用户注册后的操作
        event('UserRegister',$data);
        return success(lang('user.registerOk'), $data);
    }

    // 用户登录
    public function login()
    {
        $param=$this->request->param();
        if(!isset($param['account']) || !isset($param['open_id'])){
            return warning(lang('system.parameterError'));
        }
        $userInfo=User::where(['account'=> $param['account']])->withoutField('register_ip,login_count,update_time,create_time')->find();
        if(!$userInfo){
            return warning(lang('user.exist'));
        }
        try{
            $hash_id=decryptIds($param['open_id']);
            if($hash_id!=$userInfo['user_id']){
                return warning(lang('user.exist'));
            }
        }catch (\Exception $e){
            return error($e->getMessage());
        }
        $md5=md5(json_encode($userInfo));
        // 将用户信息缓存5分钟
        Cache::set($md5,$userInfo,300);
        // 生成Url
        $url=getMainHost().'?token='.$md5;
        return success(lang('user.loginOk'),$url);
        
    }

    // 创建或复用三方客服会话，返回一次性登录短码地址
    public function customerSession()
    {
        $platform = $this->getThirdPlatform();
        if (!$platform) {
            return warning('请使用三方平台AppID调用客服会话接口');
        }
        $param = $this->request->param();
        $externalUserId = trim((string)($param['external_user_id'] ?? ''));
        if ($externalUserId === '') {
            return warning('请传入三方用户ID');
        }
        $nickname = trim((string)($param['nickname'] ?? '')) ?: ('用户' . substr(md5($externalUserId), 0, 6));
        $avatar = trim((string)($param['avatar'] ?? ''));
        $userType = trim((string)($param['user_type'] ?? 'user')) ?: 'user';
        $tags = $param['tags'] ?? null;
        $extra = $param['extra'] ?? null;
        $isMobile = $param['is_mobile'] ?? false;
        $csUid = (int)($platform['default_cs_uid'] ?? 0);
        if (!$csUid) {
            $csUid = $this->getPublicDefaultCsUid();
        }
        if (!$csUid) {
            return warning('请配置默认客服');
        }
        $csUser = User::where(['user_id' => $csUid, 'status' => 1])->find();
        if (!$csUser) {
            return warning('客服用户不存在或已禁用');
        }

        Db::startTrans();
        $isNewUser = false;
        try {
            [$user, $isNewUser] = $this->findOrCreateThirdUser($platform, $externalUserId, $nickname, $avatar, $userType, $tags, $extra);
            User::where('user_id', $user['user_id'])->update([
                'cs_uid' => $csUid,
                'update_time' => time(),
            ]);
            Friend::acceptPair((int)$user['user_id'], $csUid, time());
            ThirdUserMap::where(['platform_id' => $platform['id'], 'external_user_id' => $externalUserId])->update([
                'last_login_time' => time(),
                'update_time' => time(),
            ]);
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            return error($e->getMessage());
        }

        if ($isNewUser) {
            try {
                $this->sendWelcomeMessage($platform, $user, $csUser);
            } catch (\Exception $e) {
                // 欢迎语失败不阻断三方客服会话初始化。
            }
        }

        $loginUser = $this->buildLoginUserInfo((int)$user['user_id']);
        $ttl = $this->normalizeCodeTtl($platform['code_ttl'] ?? 120);
        $code = $this->makeLoginCode();
        Cache::set('third_login:' . $code, $loginUser, $ttl);
        $url = $this->buildCustomerLoginUrl($code, $csUid, $isMobile);
        return success('', [
            'url' => $url,
            'token' => $code,
            'expires_in' => $ttl,
            'im_user_id' => (int)$user['user_id'],
            'cs_uid' => $csUid,
            'contact_id' => $csUid,
            'platform_id' => (int)$platform['id'],
        ]);
    }

    // 业务方指定客户和代理建立聊天关系，并返回客户的一次性登录链接。
    public function pairSession()
    {
        $platform = $this->getThirdPlatform();
        if (!$platform) {
            return warning('请使用三方平台AppID调用指定代理会话接口');
        }
        $param = $this->request->param();
        $externalUserId = trim((string)($param['external_user_id'] ?? ''));
        $externalAgentId = trim((string)($param['external_agent_id'] ?? ($param['external_staff_id'] ?? '')));
        if ($externalUserId === '' || $externalAgentId === '') {
            return warning('请传入三方用户ID和代理ID');
        }

        $userNickname = trim((string)($param['user_nickname'] ?? ($param['nickname'] ?? ''))) ?: ('用户' . substr(md5($externalUserId), 0, 6));
        $userAvatar = trim((string)($param['user_avatar'] ?? ($param['avatar'] ?? '')));
        $agentNickname = trim((string)($param['agent_nickname'] ?? ($param['staff_nickname'] ?? ''))) ?: ('代理' . substr(md5($externalAgentId), 0, 6));
        $agentAvatar = trim((string)($param['agent_avatar'] ?? ($param['staff_avatar'] ?? '')));

        Db::startTrans();
        try {
            [$user] = $this->findOrCreateThirdUser(
                $platform,
                $externalUserId,
                $userNickname,
                $userAvatar,
                '1',
                $param['user_tags'] ?? null,
                $param['user_extra'] ?? null,
                true
            );
            [$agent] = $this->findOrCreateThirdUser(
                $platform,
                $externalAgentId,
                $agentNickname,
                $agentAvatar,
                '2',
                $param['agent_tags'] ?? ($param['staff_tags'] ?? null),
                $param['agent_extra'] ?? ($param['staff_extra'] ?? null),
                true
            );
            User::where('user_id', $user['user_id'])->update([
                'cs_uid' => (int)$agent['user_id'],
                'update_time' => time(),
            ]);
            Friend::acceptPair((int)$user['user_id'], (int)$agent['user_id'], time());
            $this->touchThirdUserMap((int)$platform['id'], $externalUserId, '1');
            $this->touchThirdUserMap((int)$platform['id'], $externalAgentId, '2');
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            return error($e->getMessage());
        }

        $ttl = $this->normalizeCodeTtl($platform['code_ttl'] ?? 120);
        [$code, $url] = $this->makeCustomerPairLoginUrl((int)$user['user_id'], $ttl, [
            'contact_id' => (int)$agent['user_id'],
            'embed' => 1,
        ]);
        return success('', [
            'url' => $url,
            'token' => $code,
            'expires_in' => $ttl,
            'im_user_id' => (int)$user['user_id'],
            'agent_im_user_id' => (int)$agent['user_id'],
            'contact_id' => (int)$agent['user_id'],
            'platform_id' => (int)$platform['id'],
        ]);
    }

    // 代理后台一键打开 IM 时，创建或复用代理账号并返回一次性登录链接。
    public function agentSession()
    {
        $platform = $this->getThirdPlatform();
        if (!$platform) {
            return warning('请使用三方平台AppID调用代理登录接口');
        }
        $param = $this->request->param();
        $externalAgentId = trim((string)($param['external_agent_id'] ?? ($param['external_staff_id'] ?? '')));
        if ($externalAgentId === '') {
            return warning('请传入代理ID');
        }
        $nickname = trim((string)($param['nickname'] ?? ($param['agent_nickname'] ?? ($param['staff_nickname'] ?? '')))) ?: ('代理' . substr(md5($externalAgentId), 0, 6));
        $avatar = trim((string)($param['avatar'] ?? ($param['agent_avatar'] ?? ($param['staff_avatar'] ?? ''))));

        Db::startTrans();
        try {
            [$agent] = $this->findOrCreateThirdUser(
                $platform,
                $externalAgentId,
                $nickname,
                $avatar,
                '2',
                $param['tags'] ?? ($param['agent_tags'] ?? ($param['staff_tags'] ?? null)),
                $param['extra'] ?? ($param['agent_extra'] ?? ($param['staff_extra'] ?? null)),
                true
            );
            $this->touchThirdUserMap((int)$platform['id'], $externalAgentId, '2');
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            return error($e->getMessage());
        }

        $ttl = $this->normalizeCodeTtl($platform['code_ttl'] ?? 120);
        [$code, $url] = $this->makeThirdLoginUrl((int)$agent['user_id'], $ttl, [
            'embed' => 1,
            'staff' => 1,
        ]);
        return success('', [
            'url' => $url,
            'token' => $code,
            'expires_in' => $ttl,
            'im_user_id' => (int)$agent['user_id'],
            'platform_id' => (int)$platform['id'],
            'user_type' => '2',
        ]);
    }

    // 查询当前三方平台可由业务方维护的客服接入配置。
    public function platformConfig()
    {
        $platform = $this->getThirdPlatform();
        if (!$platform) {
            return warning('请使用三方平台AppID调用平台配置接口');
        }
        return success('', $this->buildPlatformConfig($platform));
    }

    // 保存当前三方平台的默认客服、欢迎语和短码有效期。
    public function savePlatformConfig()
    {
        $platform = $this->getThirdPlatform();
        if (!$platform) {
            return warning('请使用三方平台AppID调用平台配置接口');
        }
        $param = $this->request->param();
        $update = [];

        if (array_key_exists('default_cs_uid', $param)) {
            $csUid = (int)$param['default_cs_uid'];
            if (!$csUid) {
                return warning('请传入默认客服用户ID');
            }
            if (!$this->findEnabledUser($csUid)) {
                return warning('默认客服用户不存在或已禁用');
            }
            $update['default_cs_uid'] = $csUid;
        }
        if (array_key_exists('welcome', $param)) {
            $update['welcome'] = trim((string)$param['welcome']);
        }
        if (array_key_exists('code_ttl', $param)) {
            $update['code_ttl'] = $this->normalizeCodeTtl($param['code_ttl']);
        }
        if (array_key_exists('allowed_origins', $param)) {
            $extra = $this->normalizePlatformExtra($platform['extra'] ?? []);
            $extra['allowed_origins'] = ThirdPlatform::normalizeOrigins($param['allowed_origins']);
            $update['extra'] = $extra;
        }
        if (!$update) {
            return warning('请传入需要保存的配置');
        }
        $update['update_time'] = time();
        $platformModel = ThirdPlatform::where(['id' => $platform['id'], 'delete_time' => 0])->find();
        if (!$platformModel) {
            return warning('è¯·ä½¿ç”¨ä¸‰æ–¹å¹³å°AppIDè°ƒç”¨å¹³å°é…ç½®æŽ¥å£');
        }
        $platformModel->save($update);
        return success(lang('system.editOk'), $this->buildPlatformConfig($platformModel));
    }

    // 清空当前三方平台默认客服，后续会话会回退到公共默认客服。
    public function clearDefaultCs()
    {
        $platform = $this->getThirdPlatform();
        if (!$platform) {
            return warning('请使用三方平台AppID调用平台配置接口');
        }
        ThirdPlatform::where(['id' => $platform['id'], 'delete_time' => 0])->update([
            'default_cs_uid' => 0,
            'update_time' => time(),
        ]);
        $platform = ThirdPlatform::where(['id' => $platform['id'], 'delete_time' => 0])->find();
        return success(lang('system.editOk'), $this->buildPlatformConfig($platform));
    }

    // 查询当前平台下已创建的三方用户映射，便于业务方按分类或外部ID排查。
    public function platformUsers()
    {
        $platform = $this->getThirdPlatform();
        if (!$platform) {
            return warning('请使用三方平台AppID调用平台用户接口');
        }
        $keywords = trim((string)$this->request->param('keywords', ''));
        $userType = trim((string)$this->request->param('user_type', ''));
        $limit = (int)$this->request->param('limit', 20);
        if ($limit < 1) {
            $limit = 20;
        }
        if ($limit > 100) {
            $limit = 100;
        }
        $model = ThirdUserMap::where([
            'platform_id' => $platform['id'],
            'delete_time' => 0,
        ]);
        if ($userType !== '') {
            $model = $model->where('user_type', $userType);
        }
        if ($keywords !== '') {
            $model = $model->where(function ($query) use ($keywords) {
                $query->whereLike('external_user_id|nickname', '%' . $keywords . '%');
                if (is_numeric($keywords)) {
                    $query->whereOr('user_id', (int)$keywords);
                }
            });
        }
        $list = $model->order('id desc')->paginate($limit);
        $data = $list ? $list->toArray()['data'] : [];
        $this->appendPlatformUserInfo($data);
        return success('', $data, $list->total(), $list->currentPage());
    }

    public function platformUserDetail()
    {
        $platform = $this->getThirdPlatform();
        if (!$platform) {
            return warning('请使用三方平台AppID调用平台用户接口');
        }
        $externalUserId = trim((string)$this->request->param('external_user_id', ''));
        $userId = (int)$this->request->param('user_id', 0);
        if ($externalUserId === '' && !$userId) {
            return warning(lang('system.parameterError'));
        }
        $model = ThirdUserMap::where([
            'platform_id' => $platform['id'],
            'delete_time' => 0,
        ]);
        if ($externalUserId !== '') {
            $model = $model->where('external_user_id', $externalUserId);
        } else {
            $model = $model->where('user_id', $userId);
        }
        $data = $model->find();
        if (!$data) {
            return warning('三方用户映射不存在');
        }
        $data = $data->toArray();
        $this->appendPlatformUserInfo($data);
        return success('', $data);
    }

    public function savePlatformUser()
    {
        $platform = $this->getThirdPlatform();
        if (!$platform) {
            return warning('请使用三方平台AppID调用平台用户接口');
        }
        $externalUserId = trim((string)$this->request->param('external_user_id', ''));
        if ($externalUserId === '') {
            return warning('请传入三方用户ID');
        }
        $map = ThirdUserMap::where([
            'platform_id' => $platform['id'],
            'external_user_id' => $externalUserId,
            'delete_time' => 0,
        ])->find();
        if (!$map) {
            return warning('三方用户映射不存在');
        }
        $update = $this->platformUserUpdateData($this->request->param());
        if (!$update) {
            return warning('请传入需要保存的用户信息');
        }
        $update['update_time'] = time();
        $map->save($update);
        $data = $map->toArray();
        $this->appendPlatformUserInfo($data);
        return success(lang('system.editOk'), $data);
    }

    protected function findOrCreateThirdUser($platform, $externalUserId, $nickname, $avatar, $userType, $tags, $extra, $strictUserType = false)
    {
        $mapQuery = ThirdUserMap::where([
            'platform_id' => $platform['id'],
            'external_user_id' => $externalUserId,
            'delete_time' => 0,
        ]);
        if ($strictUserType) {
            $mapQuery = $mapQuery->where('user_type', $userType);
        }
        $map = $mapQuery->find();
        if ($map) {
            $user = User::where(['user_id' => $map['user_id'], 'status' => 1])->find();
            if (!$user) {
                throw new \Exception('映射用户不存在或已禁用');
            }
            $map->save([
                'nickname' => $nickname,
                'avatar' => $avatar,
                'user_type' => $userType,
                'tags' => $tags,
                'extra' => $extra,
                'update_time' => time(),
            ]);
            $this->syncThirdUserProfile($user, $nickname, $avatar);
            return [$user, false];
        }

        $account = $this->makeThirdAccount($platform['id'], $externalUserId, $strictUserType ? $userType : '');
        $user = User::where('account', $account)->find();
        $isNewUser = false;
        if (!$user) {
            $salt = \utils\Str::random(4);
            $data = [
                'account' => $account,
                'realname' => $nickname,
                'password' => password_hash_tp(\utils\Str::random(16), $salt),
                'salt' => $salt,
                'avatar' => mb_strlen($avatar) <= 128 ? $avatar : '',
                'register_ip' => $this->request->ip(),
                'name_py' => pinyin_sentence($nickname),
                'status' => 1,
            ];
            $user = new User();
            $user->save($data);
            $isNewUser = true;
        } elseif ((int)$user['status'] !== 1) {
            throw new \Exception('用户已禁用');
        }
        ThirdUserMap::create([
            'platform_id' => $platform['id'],
            'external_user_id' => $externalUserId,
            'user_id' => $user['user_id'],
            'nickname' => $nickname,
            'avatar' => $avatar,
            'user_type' => $userType,
            'tags' => $tags,
            'extra' => $extra,
            'create_time' => time(),
            'update_time' => time(),
        ]);
        return [$user, $isNewUser];
    }

    protected function touchThirdUserMap($platformId, $externalUserId, $userType = '')
    {
        $query = ThirdUserMap::where([
            'platform_id' => $platformId,
            'external_user_id' => $externalUserId,
            'delete_time' => 0,
        ]);
        if ($userType !== '') {
            $query = $query->where('user_type', $userType);
        }
        $query->update([
            'last_login_time' => time(),
            'update_time' => time(),
        ]);
    }

    protected function syncThirdUserProfile($user, $nickname, $avatar)
    {
        $update = [];
        if ($nickname && $nickname !== $user['realname']) {
            $update['realname'] = $nickname;
            $update['name_py'] = pinyin_sentence($nickname);
        }
        if ($avatar && mb_strlen($avatar) <= 128 && $avatar !== $user['avatar']) {
            $update['avatar'] = $avatar;
        }
        if ($update) {
            $update['update_time'] = time();
            $user->save($update);
        }
    }

    protected function makeThirdAccount($platformId, $externalUserId, $userType = '')
    {
        if ($userType !== '') {
            return md5('third:' . $platformId . ':' . $userType . ':' . $externalUserId);
        }
        return md5('third:' . $platformId . ':' . $externalUserId);
    }

    protected function getPublicDefaultCsUid()
    {
        $systemInfo = Config::getSystemInfo();
        $userIds = $systemInfo['chatInfo']['autoAddUser']['user_ids'] ?? [];
        if (!$userIds) {
            return 0;
        }
        return (int)$userIds[0];
    }

    protected function getThirdPlatform()
    {
        $platform = $this->request->thirdPlatform ?? null;
        if (!$platform) {
            return null;
        }
        $platform = ThirdPlatform::where([
            'id' => $platform['id'],
            'status' => 1,
            'delete_time' => 0,
        ])->find();
        return $platform ? $platform->toArray() : null;
    }

    protected function buildPlatformConfig($platform)
    {
        if ($platform instanceof ThirdPlatform) {
            $platform = $platform->toArray();
        }
        $data = [
            'id' => (int)$platform['id'],
            'name' => $platform['name'],
            'app_id' => $platform['app_id'],
            'default_cs_uid' => (int)($platform['default_cs_uid'] ?? 0),
            'default_cs_user' => $this->formatUserInfo((int)($platform['default_cs_uid'] ?? 0)),
            'welcome' => $platform['welcome'] ?? '',
            'effective_welcome' => $this->getWelcome($platform),
            'code_ttl' => $this->normalizeCodeTtl($platform['code_ttl'] ?? 120),
            'status' => (int)$platform['status'],
            'remark' => $platform['remark'] ?? '',
            'allowed_origins' => ThirdPlatform::extractAllowedOrigins($platform['extra'] ?? []),
            'extra' => $platform['extra'] ?? null,
        ];
        return $data;
    }

    protected function normalizePlatformExtra($extra)
    {
        if (is_string($extra)) {
            $extra = json_decode($extra, true) ?: [];
        }
        return is_array($extra) ? $extra : [];
    }

    protected function findEnabledUser($userId)
    {
        if (!$userId) {
            return null;
        }
        return User::where(['user_id' => $userId, 'status' => 1])->find();
    }

    protected function formatUserInfo($userId)
    {
        $user = $this->findEnabledUser($userId);
        if (!$user) {
            return null;
        }
        $user = $user->toArray();
        return [
            'user_id' => (int)$user['user_id'],
            'realname' => $user['realname'],
            'avatar' => avatarUrl($user['avatar'], $user['realname'], $user['user_id'], 120),
            'status' => (int)$user['status'],
        ];
    }

    protected function normalizeCodeTtl($ttl)
    {
        $ttl = (int)$ttl;
        if ($ttl < 60) {
            return 60;
        }
        if ($ttl > 1800) {
            return 1800;
        }
        return $ttl;
    }

    protected function platformUserUpdateData($data)
    {
        $result = [];
        foreach (['nickname', 'avatar', 'user_type'] as $field) {
            if (array_key_exists($field, $data)) {
                $result[$field] = trim((string)$data[$field]);
            }
        }
        if (array_key_exists('tags', $data)) {
            $result['tags'] = $data['tags'];
        }
        if (array_key_exists('extra', $data)) {
            $result['extra'] = $data['extra'];
        }
        return $result;
    }

    protected function appendPlatformUserInfo(&$data)
    {
        $rows = isset($data['id']) ? [$data] : $data;
        if (!$rows) {
            return;
        }
        $uids = array_values(array_unique(array_filter(array_column($rows, 'user_id'))));
        $users = $uids ? User::where('user_id', 'in', $uids)->field('user_id,realname,avatar,status,cs_uid,last_login_time')->select()->toArray() : [];
        $userMap = [];
        foreach ($users as $user) {
            $user['avatar'] = avatarUrl($user['avatar'], $user['realname'], $user['user_id'], 120);
            $userMap[$user['user_id']] = $user;
        }
        $apply = function (&$item) use ($userMap) {
            $uid = (int)($item['user_id'] ?? 0);
            $item['im_user'] = $uid && isset($userMap[$uid]) ? $userMap[$uid] : null;
        };
        if (isset($data['id'])) {
            $apply($data);
            return;
        }
        foreach ($data as &$item) {
            $apply($item);
        }
    }

    protected function getWelcome($platform)
    {
        $welcome = trim((string)($platform['welcome'] ?? ''));
        if ($welcome !== '') {
            return $welcome;
        }
        $systemInfo = Config::getSystemInfo();
        return trim((string)($systemInfo['chatInfo']['autoAddUser']['welcome'] ?? ''));
    }

    protected function sendWelcomeMessage($platform, $user, $csUser)
    {
        $welcome = $this->getWelcome($platform);
        if ($welcome === '') {
            return;
        }
        $fromUser = [
            'id' => (int)$csUser['user_id'],
            'user_id' => (int)$csUser['user_id'],
            'displayName' => $csUser['realname'],
            'realname' => $csUser['realname'],
            'avatar' => avatarUrl($csUser['avatar'], $csUser['realname'], $csUser['user_id']),
        ];
        $msg = [
            'id' => \utils\Str::getUuid(),
            'user_id' => (int)$csUser['user_id'],
            'content' => $welcome,
            'toContactId' => (int)$user['user_id'],
            'sendTime' => time() * 1000,
            'type' => 'text',
            'is_group' => 0,
            'status' => 'succeed',
            'fromUser' => $fromUser,
            'at' => [],
        ];
        Message::sendMsg($msg, 0, 1);
    }

    protected function buildLoginUserInfo($userId)
    {
        $userInfo = User::where(['user_id' => $userId])->withoutField('register_ip,login_count,update_time,create_time')->find();
        if (!$userInfo) {
            throw new \Exception('用户不存在');
        }
        $userInfo = $userInfo->toArray();
        $userInfo['avatar'] = avatarUrl($userInfo['avatar'], $userInfo['realname'], $userInfo['user_id']);
        $userInfo['setting'] = User::normalizeSetting($userInfo['setting'] ?: [], $userInfo['user_id']);
        $userInfo['qrUrl'] = $this->mainHostPath('/scan/u/' . encryptIds($userInfo['user_id']));
        $userInfo['displayName'] = $userInfo['realname'];
        $userInfo['id'] = $userInfo['user_id'];
        unset($userInfo['password'], $userInfo['salt']);
        return $userInfo;
    }

    protected function buildCustomerLoginUrl($code, $csUid, $isMobile = false)
    {
        $query = http_build_query([
            'token' => $code,
            'contact_id' => $csUid,
            'embed' => 1,
        ]);
        return getMainHost() . '?' . $query;
    }

    protected function makeThirdLoginUrl($userId, $ttl, $params = [])
    {
        $loginUser = $this->buildLoginUserInfo($userId);
        $code = $this->makeLoginCode();
        Cache::set('third_login:' . $code, $loginUser, $ttl);
        $query = http_build_query(array_merge(['token' => $code], $params));
        return [$code, $this->agentChatHostPath('/index.html#/login') . '?' . $query];
    }

    protected function makeCustomerPairLoginUrl($userId, $ttl, $params = [])
    {
        $loginUser = $this->buildLoginUserInfo($userId);
        $code = $this->makeLoginCode();
        Cache::set('third_login:' . $code, $loginUser, $ttl);
        $query = http_build_query(array_merge(['token' => $code], $params));
        return [$code, $this->mainHostPath('/index.html#/login') . '?' . $query];
    }

    protected function makeLoginCode()
    {
        return rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
    }

    protected function mainHostPath($path)
    {
        return rtrim((string)getMainHost(), '/') . '/' . ltrim($path, '/');
    }

    protected function agentChatHostPath($path)
    {
        $host = (string)config('app.agent_chat_host', '');
        if ($host === '') {
            $host = (string)getMainHost();
        }
        return rtrim($host, '/') . '/' . ltrim($path, '/');
    }
}
