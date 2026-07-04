# 三方平台内嵌客服接入说明

## 接入流程

1. IM 后台或管理接口创建三方平台，得到 `app_id` 和 `app_secret`。
2. 已有环境导入 `public/sql/6.2.2-third-platform.sql`；已安装三方平台表的环境再导入 `public/sql/6.2.3-third-user-map-type-index.sql`。
3. 三方业务后端保存 `app_id` 和 `app_secret`，不要下发到浏览器。
4. 三方业务前端打开客服时，请求自己的业务后端。
5. 业务后端使用平台密钥签名，调用 IM 的 `common/api/customerSession`。
6. IM 返回一次性短码登录地址，业务前端用 iframe 或新窗口打开即可聊天。

`pairSession` 返回的用户聊天链接使用 IM 服务端 `[APP] HOST` 拼接；`agentSession` 返回的代理后台登录链接优先使用 `[APP] AGENT_CHAT_HOST`，未配置时回退 `[APP] HOST`。

## 开放 API 签名

所有 `common/api/*` 开放接口使用相同签名头：

```text
x-im-appid: 平台 app_id
x-im-timestamp: 当前秒级时间戳
x-im-sign: md5(app_id + timestamp + app_secret)
```

时间戳允许 60 秒内的偏差。

## 创建客服会话

请求：

```http
POST /common/api/customerSession
Content-Type: application/json
```

参数：

| 字段 | 必填 | 说明 |
| --- | --- | --- |
| `external_user_id` | 是 | 三方业务侧稳定用户 ID。游客模式暂未实现。 |
| `nickname` | 否 | 用户昵称，不传会自动生成。 |
| `avatar` | 否 | 用户头像地址。 |
| `user_type` | 否 | 用户分类，默认 `user`。 |
| `tags` | 否 | 用户标签，JSON 数组或对象。 |
| `extra` | 否 | 扩展信息，JSON 对象。 |
| `is_mobile` | 否 | 预留字段，可由业务方透传；当前返回地址不再区分 H5/PC 路径。 |

返回：

```json
{
  "code": 0,
  "msg": "",
  "data": {
    "url": "https://im.example.com?token=xxxx&contact_id=2&embed=1",
    "token": "xxxx",
    "expires_in": 120,
    "im_user_id": 21,
    "cs_uid": 2,
    "contact_id": 2,
    "platform_id": 1
  }
}
```

规则：

- 同一个平台下，`external_user_id` 只会映射一个 IM 用户。
- 新用户才发送一次欢迎语。
- 默认客服只从平台配置读取；平台未配置时回退公共默认客服。
- `token` 是短码，缓存 key 为 `third_login:{token}`，登录成功后一次性消费。
- `url` 会附带 `contact_id` 和 `embed=1`，用于前端识别默认客服和嵌入场景；旧前端忽略这两个参数也不影响登录。
- 登录接口收到 `contact_id/embed` 时也会在返回数据中带回，方便后续前端源码支持自动打开指定客服会话。
- `app_secret` 只允许三方业务后端保存，不能暴露给浏览器。

## 指定代理聊天接口

当业务方已经判断出“用户 A 应该找代理 B”时，调用此接口。IM 不判断代理归属，只创建或复用双方 IM 账号、建立好友关系，并返回用户 A 的登录聊天链接。

```http
POST /common/api/pairSession
Content-Type: application/json
```

参数：

| 字段 | 必填 | 说明 |
| --- | --- | --- |
| `external_user_id` | 是 | 业务用户 A 的外部 ID。 |
| `user_nickname` | 否 | 用户 A 昵称。 |
| `user_avatar` | 否 | 用户 A 头像。 |
| `user_tags` | 否 | 用户 A 标签，JSON 数组或对象。 |
| `user_extra` | 否 | 用户 A 扩展信息，JSON 对象。 |
| `external_agent_id` | 是 | 代理 B 的外部 ID，兼容 `external_staff_id`。 |
| `agent_nickname` | 否 | 代理 B 昵称。 |
| `agent_avatar` | 否 | 代理 B 头像。 |
| `agent_tags` | 否 | 代理 B 标签，兼容 `staff_tags`。 |
| `agent_extra` | 否 | 代理 B 扩展信息，兼容 `staff_extra`。 |

返回：

```json
{
  "code": 0,
  "msg": "",
  "data": {
    "url": "https://im.example.com/index.html#/login?token=xxxx&contact_id=202&embed=1",
    "token": "xxxx",
    "expires_in": 120,
    "im_user_id": 101,
    "agent_im_user_id": 202,
    "contact_id": 202,
    "platform_id": 1
  }
}
```

规则：

- 用户 A 写入 `third_user_map.user_type=1`，代理 B 写入 `third_user_map.user_type=2`，管理员预留为 `3`。
- A 或 B 不存在时自动创建普通 IM 用户，存在时复用并更新昵称、头像、扩展信息。
- IM 会给 A 和 B 建立双向好友关系，并把 A 的 `cs_uid` 更新为 B 的 IM 用户 ID。
- 该接口不发送欢迎语，也不使用平台默认客服。
- 业务方可以保存返回的 `im_user_id` 和 `agent_im_user_id`。

## 代理后台自动登录接口

代理后台点击内嵌客服时，业务方后端调用此接口，获取代理自己的自动登录链接。

```http
POST /common/api/agentSession
Content-Type: application/json
```

参数：

| 字段 | 必填 | 说明 |
| --- | --- | --- |
| `external_agent_id` | 是 | 代理 B 的外部 ID，兼容 `external_staff_id`。 |
| `nickname` | 否 | 代理昵称，兼容 `agent_nickname`、`staff_nickname`。 |
| `avatar` | 否 | 代理头像，兼容 `agent_avatar`、`staff_avatar`。 |
| `tags` | 否 | 代理标签，兼容 `agent_tags`、`staff_tags`。 |
| `extra` | 否 | 代理扩展信息，兼容 `agent_extra`、`staff_extra`。 |

返回：

```json
{
  "code": 0,
  "msg": "",
  "data": {
    "url": "https://im.example.com/index.html#/login?token=yyyy&embed=1&staff=1",
    "token": "yyyy",
    "expires_in": 120,
    "im_user_id": 202,
    "platform_id": 1,
    "user_type": "2"
  }
}
```

规则：

- 代理写入 `third_user_map.user_type=2`。
- 该接口只生成代理自己的登录链接，不建立好友关系。
- `/index.html` 无 `token` 时仍然走原账号密码登录；有 `token` 时前端用 `/common/pub/login` 自动登录。

## 平台配置接口

这些接口同样使用开放 API 签名，只能维护当前 `app_id` 对应的平台。

### 查询配置

```http
POST /common/api/platformConfig
```

返回字段不包含 `app_secret`。

### 保存配置

```http
POST /common/api/savePlatformConfig
Content-Type: application/json
```

可传字段：

| 字段 | 说明 |
| --- | --- |
| `default_cs_uid` | 默认客服用户 ID，只校验用户存在且启用，不限制管理员或客服角色。 |
| `welcome` | 平台欢迎语，空字符串表示使用公共欢迎语。 |
| `code_ttl` | 登录短码有效期，限制在 `60-1800` 秒。 |
| `allowed_origins` | 允许来源白名单，支持数组、逗号或换行字符串，例如 `["https://www.example.com","https://*.example.com"]`；空数组表示不限制 `Origin`。 |

配置 `allowed_origins` 后，IM 会校验浏览器请求的 `Origin`。如果由业务后端代调 `customerSession`，建议业务后端把前端请求的 `Origin` 通过 `x-im-origin` 请求头转发给 IM，IM 会按同一白名单规则校验。未配置白名单时不限制来源，也不会自动对全站开放 CORS。

### 清空默认客服

```http
POST /common/api/clearDefaultCs
```

清空后，会话接口会回退到公共默认客服。

## 平台用户映射接口

这些接口同样使用开放 API 签名，只能查询或维护当前 `app_id` 对应的平台用户映射。

### 查询用户映射列表

```http
POST /common/api/platformUsers
Content-Type: application/json
```

可传字段：

| 字段 | 说明 |
| --- | --- |
| `keywords` | 按 `external_user_id`、昵称、IM 用户 ID 搜索。 |
| `user_type` | 按用户分类筛选。 |
| `page` | 页码。 |
| `limit` | 每页数量，最大 `100`。 |

返回的每条数据会包含 `im_user`，用于查看映射到的 IM 用户状态和当前客服。

### 查询单个用户映射

```http
POST /common/api/platformUserDetail
Content-Type: application/json
```

传 `external_user_id` 或 `user_id` 其中一个即可。

### 更新用户分类和扩展信息

```http
POST /common/api/savePlatformUser
Content-Type: application/json
```

必传：

| 字段 | 说明 |
| --- | --- |
| `external_user_id` | 三方用户 ID。 |

可更新字段：

| 字段 | 说明 |
| --- | --- |
| `nickname` | 三方昵称快照。 |
| `avatar` | 三方头像快照。 |
| `user_type` | 用户分类。 |
| `tags` | 用户标签，JSON 数组或对象。 |
| `extra` | 扩展信息，JSON 对象。 |

该接口只更新已存在的映射，不会创建新 IM 用户；创建仍然通过 `customerSession` 完成。

## 管理端接口

管理端接口走 `manage` 应用登录鉴权和 demo 模式限制，适合后台页面或 Postman 调试使用。自动路由下推荐使用 `third_platform` 控制器路径。

| 接口 | 说明 |
| --- | --- |
| `/manage/third_platform/index` | 平台列表，支持 `keywords`、`status`、`page`、`limit`。 |
| `/manage/third_platform/add` | 创建平台；未传 `app_id` 时自动生成，`app_secret` 自动生成。 |
| `/manage/third_platform/edit` | 编辑平台基础配置、默认客服、欢迎语、短码有效期、来源白名单。 |
| `/manage/third_platform/setStatus` | 启用或禁用平台。 |
| `/manage/third_platform/resetSecret` | 重置平台密钥。 |
| `/manage/third_platform/del` | 软删除平台。 |
| `/manage/third_platform/users` | 查看三方用户映射列表。 |
| `/manage/third_platform/userDetail` | 查看单个三方用户映射。 |
| `/manage/third_platform/editUser` | 编辑三方用户映射的昵称、头像、分类、标签、扩展信息。 |

## 业务后端 PHP 示例

```php
<?php
$imHost = 'https://im.example.com';
$appId = 'tp_xxxxx';
$appSecret = '平台密钥只放后端';
$timestamp = time();
$sign = md5($appId . $timestamp . $appSecret);
$headers = [
    'Content-Type: application/json',
    'x-im-appid: ' . $appId,
    'x-im-timestamp: ' . $timestamp,
    'x-im-sign: ' . $sign,
];
if (!empty($_SERVER['HTTP_ORIGIN'])) {
    $headers[] = 'x-im-origin: ' . $_SERVER['HTTP_ORIGIN'];
}

$payload = [
    'external_user_id' => (string)$currentUser['id'],
    'nickname' => $currentUser['nickname'] ?? '',
    'avatar' => $currentUser['avatar'] ?? '',
    'user_type' => 'member',
    'is_mobile' => !empty($_POST['is_mobile']) ? 1 : 0,
];

$ch = curl_init($imHost . '/common/api/customerSession');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
]);
$response = curl_exec($ch);
curl_close($ch);

header('Content-Type: application/json; charset=utf-8');
echo $response;
```

## 前端嵌入 SDK

三方业务页面引入 IM 静态 SDK：

```html
<script src="https://im.example.com/static/js/im-customer-embed.js"></script>
<script>
  window.ImCustomerService.init({
    sessionUrl: '/api/im/customer-session',
    title: '在线客服',
    buttonText: '客服',
    payload: function () {
      return {
        page_url: location.href,
        is_mobile: window.innerWidth <= 640 ? 1 : 0
      };
    }
  });
</script>
```

`sessionUrl` 指向三方业务自己的后端接口，由业务后端再调用 IM 的 `customerSession`。SDK 兼容以下返回结构：

```json
{"code":0,"data":{"url":"https://im.example.com?token=xxxx&contact_id=2&embed=1"}}
```

也兼容直接返回：

```json
{"url":"https://im.example.com?token=xxxx&contact_id=2&embed=1"}
```

## SDK 自动初始化

也可以通过 script 标签参数自动初始化：

```html
<script
  src="https://im.example.com/static/js/im-customer-embed.js"
  data-session-url="/api/im/customer-session"
  data-title="在线客服"
  data-button-text="客服"
  data-position="right">
</script>
```

## 下一步可选优化

- 增加游客模式：三方前端传稳定 `visitor_id`，业务后端按 `visitor_id` 调用会话接口。
- 增加客服池：平台可配置多个客服，按在线状态、轮询或权重分配。
