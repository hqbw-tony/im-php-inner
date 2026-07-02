<?php
namespace app\manage\controller;

use app\BaseController;
use app\common\model\ThirdPlatform as ThirdPlatformModel;
use app\common\model\ThirdUserMap;
use app\enterprise\model\User;

class ThirdPlatform extends BaseController
{
    public function index()
    {
        $model = new ThirdPlatformModel();
        $keywords = $this->request->param('keywords', '');
        $status = $this->request->param('status', '');
        if ($keywords !== '') {
            $model = $model->whereLike('name|app_id|remark', '%' . $keywords . '%');
        }
        if ($status !== '') {
            $model = $model->where('status', (int)$status);
        }
        $list = $this->paginate($model->where('delete_time', 0)->order('id desc'));
        $data = $list ? $list->toArray()['data'] : [];
        $this->appendCsInfo($data);
        return success('', $data, $list->total(), $list->currentPage());
    }

    public function add()
    {
        if (env('app.demon_mode', false)) {
            return warning(lang('system.demoMode'));
        }
        $data = $this->platformData();
        if ($data instanceof \think\Response) {
            return $data;
        }
        $data['app_id'] = $data['app_id'] ?: ThirdPlatformModel::newAppId();
        if (ThirdPlatformModel::where(['app_id' => $data['app_id'], 'delete_time' => 0])->find()) {
            return warning('AppID已存在');
        }
        $data['app_secret'] = ThirdPlatformModel::newSecret();
        $data['create_time'] = time();
        $data['update_time'] = time();
        $platform = ThirdPlatformModel::create($data);
        $result = $platform->toArray();
        $this->appendCsInfo($result);
        return success(lang('system.addOk'), $result);
    }

    public function edit()
    {
        if (env('app.demon_mode', false)) {
            return warning(lang('system.demoMode'));
        }
        $id = (int)$this->request->param('id', 0);
        $platform = ThirdPlatformModel::where(['id' => $id, 'delete_time' => 0])->find();
        if (!$platform) {
            return warning('平台不存在');
        }
        $data = $this->platformData();
        if ($data instanceof \think\Response) {
            return $data;
        }
        if ($data['app_id'] && ThirdPlatformModel::where([['app_id', '=', $data['app_id']], ['id', '<>', $id], ['delete_time', '=', 0]])->find()) {
            return warning('AppID已存在');
        }
        if (!$data['app_id']) {
            unset($data['app_id']);
        }
        $data['update_time'] = time();
        $platform->save($data);
        $result = $platform->toArray();
        $this->appendCsInfo($result);
        return success(lang('system.editOk'), $result);
    }

    public function setStatus()
    {
        if (env('app.demon_mode', false)) {
            return warning(lang('system.demoMode'));
        }
        $id = (int)$this->request->param('id', 0);
        $status = (int)$this->request->param('status', 1);
        $platform = ThirdPlatformModel::where(['id' => $id, 'delete_time' => 0])->find();
        if (!$platform) {
            return warning('平台不存在');
        }
        $platform->save(['status' => $status ? 1 : 0, 'update_time' => time()]);
        return success(lang('system.editOk'));
    }

    public function resetSecret()
    {
        if (env('app.demon_mode', false)) {
            return warning(lang('system.demoMode'));
        }
        $id = (int)$this->request->param('id', 0);
        $platform = ThirdPlatformModel::where(['id' => $id, 'delete_time' => 0])->find();
        if (!$platform) {
            return warning('平台不存在');
        }
        $secret = ThirdPlatformModel::newSecret();
        $platform->save(['app_secret' => $secret, 'update_time' => time()]);
        return success(lang('system.editOk'), ['app_secret' => $secret]);
    }

    public function del()
    {
        if (env('app.demon_mode', false)) {
            return warning(lang('system.demoMode'));
        }
        $id = (int)$this->request->param('id', 0);
        $platform = ThirdPlatformModel::where(['id' => $id, 'delete_time' => 0])->find();
        if (!$platform) {
            return warning('平台不存在');
        }
        $platform->save(['status' => 0, 'delete_time' => time(), 'update_time' => time()]);
        return success(lang('system.delOk'));
    }

    public function users()
    {
        $platformId = (int)$this->request->param('platform_id', 0);
        $keywords = trim((string)$this->request->param('keywords', ''));
        $userType = trim((string)$this->request->param('user_type', ''));
        $model = ThirdUserMap::where('delete_time', 0);
        if ($platformId) {
            $model = $model->where('platform_id', $platformId);
        }
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
        $list = $this->paginate($model->order('id desc'));
        $data = $list ? $list->toArray()['data'] : [];
        $this->appendThirdUserInfo($data, true);
        return success('', $data, $list->total(), $list->currentPage());
    }

    public function userDetail()
    {
        $id = (int)$this->request->param('id', 0);
        $platformId = (int)$this->request->param('platform_id', 0);
        $externalUserId = trim((string)$this->request->param('external_user_id', ''));
        $model = ThirdUserMap::where('delete_time', 0);
        if ($id) {
            $model = $model->where('id', $id);
        } else {
            if (!$platformId || $externalUserId === '') {
                return warning(lang('system.parameterError'));
            }
            $model = $model->where([
                'platform_id' => $platformId,
                'external_user_id' => $externalUserId,
            ]);
        }
        $data = $model->find();
        if (!$data) {
            return warning('三方用户映射不存在');
        }
        $data = $data->toArray();
        $this->appendThirdUserInfo($data, true);
        return success('', $data);
    }

    public function editUser()
    {
        if (env('app.demon_mode', false)) {
            return warning(lang('system.demoMode'));
        }
        $id = (int)$this->request->param('id', 0);
        $map = ThirdUserMap::where(['id' => $id, 'delete_time' => 0])->find();
        if (!$map) {
            return warning('三方用户映射不存在');
        }
        $data = $this->thirdUserData();
        if (!$data) {
            return warning('请传入需要保存的用户信息');
        }
        $data['update_time'] = time();
        $map->save($data);
        $result = $map->toArray();
        $this->appendThirdUserInfo($result, true);
        return success(lang('system.editOk'), $result);
    }

    protected function platformData($requireName = true)
    {
        $data = $this->request->param();
        $name = trim((string)($data['name'] ?? ''));
        if ($requireName && $name === '') {
            return warning('请填写平台名称');
        }
        $csUid = (int)($data['default_cs_uid'] ?? 0);
        if ($csUid && !User::where(['user_id' => $csUid, 'status' => 1])->find()) {
            return warning('默认客服用户不存在或已禁用');
        }
        $codeTtl = (int)($data['code_ttl'] ?? 120);
        if ($codeTtl < 60) {
            $codeTtl = 60;
        }
        if ($codeTtl > 1800) {
            $codeTtl = 1800;
        }
        $extra = $this->normalizeExtra($data['extra'] ?? []);
        if (array_key_exists('allowed_origins', $data)) {
            $extra['allowed_origins'] = ThirdPlatformModel::normalizeOrigins($data['allowed_origins']);
        } elseif (isset($extra['allowed_origins'])) {
            $extra['allowed_origins'] = ThirdPlatformModel::normalizeOrigins($extra['allowed_origins']);
        }
        return [
            'name' => $name,
            'app_id' => trim((string)($data['app_id'] ?? '')),
            'default_cs_uid' => $csUid,
            'welcome' => trim((string)($data['welcome'] ?? '')),
            'code_ttl' => $codeTtl,
            'status' => isset($data['status']) ? ((int)$data['status'] ? 1 : 0) : 1,
            'remark' => trim((string)($data['remark'] ?? '')),
            'extra' => $extra,
        ];
    }

    protected function normalizeExtra($extra)
    {
        if (is_string($extra)) {
            $extra = json_decode($extra, true) ?: [];
        }
        return is_array($extra) ? $extra : [];
    }

    protected function appendCsInfo(&$data)
    {
        $rows = isset($data['id']) ? [$data] : $data;
        $originApply = function (&$item) {
            $item['allowed_origins'] = ThirdPlatformModel::extractAllowedOrigins($item['extra'] ?? []);
        };
        if (isset($data['id'])) {
            $originApply($data);
        } else {
            foreach ($data as &$item) {
                $originApply($item);
            }
        }
        $uids = array_values(array_unique(array_filter(array_column($rows, 'default_cs_uid'))));
        if (!$uids) {
            return;
        }
        $users = User::where('user_id', 'in', $uids)->field('user_id,realname,avatar,status')->select()->toArray();
        $userMap = [];
        foreach ($users as $user) {
            $user['avatar'] = avatarUrl($user['avatar'], $user['realname'], $user['user_id'], 120);
            $userMap[$user['user_id']] = $user;
        }
        $apply = function (&$item) use ($userMap) {
            $uid = (int)($item['default_cs_uid'] ?? 0);
            $item['default_cs_user'] = $uid && isset($userMap[$uid]) ? $userMap[$uid] : null;
        };
        if (isset($data['id'])) {
            $apply($data);
            return;
        }
        foreach ($data as &$item) {
            $apply($item);
        }
    }

    protected function thirdUserData()
    {
        $data = $this->request->param();
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

    protected function appendThirdUserInfo(&$data, $withPlatform = false)
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
        $platformMap = [];
        if ($withPlatform) {
            $platformIds = array_values(array_unique(array_filter(array_column($rows, 'platform_id'))));
            $platforms = $platformIds ? ThirdPlatformModel::where('id', 'in', $platformIds)->field('id,name,app_id,status,default_cs_uid')->select()->toArray() : [];
            foreach ($platforms as $platform) {
                $platformMap[$platform['id']] = $platform;
            }
        }
        $apply = function (&$item) use ($userMap, $platformMap, $withPlatform) {
            $uid = (int)($item['user_id'] ?? 0);
            $item['im_user'] = $uid && isset($userMap[$uid]) ? $userMap[$uid] : null;
            if ($withPlatform) {
                $platformId = (int)($item['platform_id'] ?? 0);
                $item['platform'] = $platformId && isset($platformMap[$platformId]) ? $platformMap[$platformId] : null;
            }
        };
        if (isset($data['id'])) {
            $apply($data);
            return;
        }
        foreach ($data as &$item) {
            $apply($item);
        }
    }
}
