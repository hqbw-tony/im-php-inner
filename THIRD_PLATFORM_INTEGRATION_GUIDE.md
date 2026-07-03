# 三方业务平台客服接入文档

本文档面向三方业务平台后端和前端开发。三方业务平台只需要在自己的后端对接 IM 开放接口，前端拿到 IM 返回的聊天地址后打开即可。

## 一、测试环境

| 项目 | 值 |
| --- | --- |
| IM 网关 | `https://inner-admin.bvugw.sbs` |
| 测试商户号 app_id | `tp_a6cdf980d8082762` |
| 测试商户密钥 app_secret | 由 IM 对接人单独提供 |

注意：`app_secret` 只能保存在三方业务平台后端，不能写进前端、App、小程序或网页源码。

## 二、整体接入流程

1. IM 平台创建三方业务平台，分配 `app_id` 和 `app_secret`，并配置默认客服。
2. 三方业务平台后端保存 `app_id` 和 `app_secret`。
3. 用户在三方业务平台前端点击“联系客服”。
4. 三方业务平台前端请求自己的业务后端，例如 `/api/im/customer-session`。
5. 三方业务平台后端识别当前业务用户，生成稳定的 `external_user_id`。
6. 三方业务平台后端使用 `app_id/app_secret` 签名，请求 IM 接口 `/common/api/customerSession`。
7. IM 自动创建或复用该用户对应的 IM 账号，并添加平台默认客服。
8. IM 返回一次性登录短码和聊天地址 `url`。
9. 三方业务平台前端使用 iframe、弹窗或新窗口打开 `url`，用户即可进入聊天。

如果业务方需要“用户 A 找指定代理 B”，由业务方先判断 A 对应哪个代理 B，然后调用 `/common/api/pairSession`。IM 只负责创建/复用 A 和 B 的 IM 账号、建立好友关系、返回登录链接。

`pairSession` 和 `agentSession` 返回的 `/index.html` 自动登录链接域名由 IM 服务端 `[APP] AGENT_CHAT_HOST` 配置决定；未配置时回退使用 `[APP] HOST`。

重要规则：

- `external_user_id` 必须是三方业务平台内稳定且唯一的用户 ID。
- 当前未启用游客模式，未登录用户也必须由业务平台后端提供稳定的 `external_user_id`。
- 同一个平台下，相同 `external_user_id` 会复用同一个 IM 用户。
- 新用户首次创建时才发送欢迎语，后续重复打开不会重复发送。
- 登录短码是一次性的，成功登录后立即失效。
- 指定代理链路使用 `third_user_map.user_type` 区分身份：`1` 客户、`2` 代理、`3` 管理员。

## 三、开放接口签名规则

所有 `/common/api/*` 开放接口都需要请求头签名。

请求头：

```http
x-im-appid: 平台 app_id
x-im-timestamp: 当前秒级时间戳
x-im-sign: md5(app_id + timestamp + app_secret)
Content-Type: application/json
```

说明：

- `timestamp` 使用秒级 Unix 时间戳。
- 服务端允许约 60 秒时间偏差。
- `sign` 为 32 位小写 MD5。
- 签名原文直接拼接，不加分隔符。

示例：

```text
app_id = tp_xxxxx
timestamp = 1782990000
app_secret = abc123
sign = md5("tp_xxxxx1782990000abc123")
```

## 四、核心接口：创建客服会话

三方业务平台后端必须对接此接口。

```http
POST /common/api/customerSession
Host: inner-admin.bvugw.sbs
Content-Type: application/json
x-im-appid: {app_id}
x-im-timestamp: {timestamp}
x-im-sign: {sign}
```

请求参数：

| 字段 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| `external_user_id` | string | 是 | 三方业务平台用户唯一 ID。 |
| `nickname` | string | 否 | 用户昵称，不传会自动生成。 |
| `avatar` | string | 否 | 用户头像 URL。 |
| `user_type` | string | 否 | 用户分类，默认 `user`，例如 `member`、`vip`。 |
| `tags` | array/object | 否 | 用户标签。 |
| `extra` | object | 否 | 扩展信息。 |
| `is_mobile` | number | 否 | 预留字段，可由业务方透传；当前返回地址不再区分 H5/PC 路径。 |

请求示例：

```json
{
  "external_user_id": "10086",
  "nickname": "张三",
  "avatar": "https://example.com/avatar/10086.png",
  "user_type": "member",
  "tags": ["vip", "paid"],
  "extra": {
    "mobile": "138****8888",
    "order_count": 12
  },
  "is_mobile": 0
}
```

成功返回：

```json
{
  "code": 0,
  "msg": "",
  "data": {
    "url": "https://inner-admin.bvugw.sbs?token=xxxx&contact_id=1&embed=1",
    "token": "xxxx",
    "expires_in": 120,
    "im_user_id": 82,
    "cs_uid": 1,
    "contact_id": 1,
    "platform_id": 1
  }
}
```

返回字段说明：

| 字段 | 说明 |
| --- | --- |
| `url` | 聊天登录地址，三方前端直接打开此地址。 |
| `token` | 一次性登录短码，通常不需要三方业务前端单独处理。 |
| `expires_in` | 短码有效期，单位秒。 |
| `im_user_id` | 自动创建或复用的 IM 用户 ID。 |
| `cs_uid` | 本次分配的客服 IM 用户 ID。 |
| `contact_id` | 前端默认打开的客服联系人 ID，等同于 `cs_uid`。 |
| `platform_id` | IM 平台内的三方平台 ID。 |

三方业务前端处理方式：

```js
// 业务后端返回 IM 的 customerSession 结果后：
if (res.code === 0 && res.data && res.data.url) {
  window.open(res.data.url, '_blank');
}
```

如果要内嵌到 iframe：

```html
<iframe
  src="https://inner-admin.bvugw.sbs?token=xxxx&contact_id=1&embed=1"
  style="width: 100%; height: 640px; border: 0;"
></iframe>
```

## 四-A、指定代理聊天接口

业务方已经判断出“用户 A 应该找代理 B”时，调用此接口。IM 不判断业务归属关系，只负责创建或复用双方 IM 账号、建立好友关系，并返回用户 A 的登录聊天链接。

```http
POST /common/api/pairSession
Host: inner-admin.bvugw.sbs
Content-Type: application/json
x-im-appid: {app_id}
x-im-timestamp: {timestamp}
x-im-sign: {sign}
```

请求参数：

| 字段 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| `external_user_id` | string | 是 | 业务用户 A 的外部 ID。 |
| `user_nickname` | string | 否 | 用户 A 昵称。未传会自动生成。 |
| `user_avatar` | string | 否 | 用户 A 头像 URL。 |
| `user_tags` | array/object | 否 | 用户 A 标签。 |
| `user_extra` | object | 否 | 用户 A 扩展信息。 |
| `external_agent_id` | string | 是 | 代理 B 的外部 ID。也兼容 `external_staff_id`。 |
| `agent_nickname` | string | 否 | 代理 B 昵称。未传会自动生成。 |
| `agent_avatar` | string | 否 | 代理 B 头像 URL。 |
| `agent_tags` | array/object | 否 | 代理 B 标签。也兼容 `staff_tags`。 |
| `agent_extra` | object | 否 | 代理 B 扩展信息。也兼容 `staff_extra`。 |

请求示例：

```json
{
  "external_user_id": "user_A_10001",
  "user_nickname": "用户A",
  "user_avatar": "",
  "external_agent_id": "agent_B_20001",
  "agent_nickname": "代理B",
  "agent_avatar": "",
  "user_extra": {
    "level": "vip"
  },
  "agent_extra": {
    "group": "east"
  }
}
```

成功返回：

```json
{
  "code": 0,
  "msg": "",
  "data": {
    "url": "https://inner-admin.bvugw.sbs/index.html?token=xxxx&contact_id=202&embed=1",
    "token": "xxxx",
    "expires_in": 120,
    "im_user_id": 101,
    "agent_im_user_id": 202,
    "contact_id": 202,
    "platform_id": 1
  }
}
```

返回字段说明：

| 字段 | 说明 |
| --- | --- |
| `url` | 用户 A 的自动登录聊天链接，打开后默认聊天对象为代理 B。 |
| `im_user_id` | 用户 A 在 IM 侧的用户 ID，业务方可保存。 |
| `agent_im_user_id` | 代理 B 在 IM 侧的用户 ID，业务方可保存。 |
| `contact_id` | 前端默认打开的聊天对象，即代理 B 的 IM 用户 ID。 |

规则：

- 用户 A 写入 `third_user_map.user_type=1`。
- 代理 B 写入 `third_user_map.user_type=2`。
- 如果 A 或 B 不存在，IM 会自动创建普通 IM 用户。
- 如果 A 或 B 已存在，IM 会复用原 IM 用户并更新昵称、头像、扩展信息。
- IM 会给 A 和 B 建立双向好友关系。
- IM 会把 A 的 `cs_uid` 更新为 B 的 IM 用户 ID。
- 该接口不会发送欢迎语，也不会使用平台默认客服。

## 四-B、代理后台自动登录接口

代理后台点击“内嵌客服”时，业务方后端调用此接口，获取代理 B 自动登录 `/index.html` 的链接。

```http
POST /common/api/agentSession
Host: inner-admin.bvugw.sbs
Content-Type: application/json
x-im-appid: {app_id}
x-im-timestamp: {timestamp}
x-im-sign: {sign}
```

请求参数：

| 字段 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| `external_agent_id` | string | 是 | 代理 B 的外部 ID。也兼容 `external_staff_id`。 |
| `nickname` | string | 否 | 代理昵称。也兼容 `agent_nickname`、`staff_nickname`。 |
| `avatar` | string | 否 | 代理头像。也兼容 `agent_avatar`、`staff_avatar`。 |
| `tags` | array/object | 否 | 代理标签。也兼容 `agent_tags`、`staff_tags`。 |
| `extra` | object | 否 | 代理扩展信息。也兼容 `agent_extra`、`staff_extra`。 |

请求示例：

```json
{
  "external_agent_id": "agent_B_20001",
  "nickname": "代理B",
  "avatar": ""
}
```

成功返回：

```json
{
  "code": 0,
  "msg": "",
  "data": {
    "url": "https://inner-admin.bvugw.sbs/index.html?token=yyyy&embed=1&staff=1",
    "token": "yyyy",
    "expires_in": 120,
    "im_user_id": 202,
    "platform_id": 1,
    "user_type": "2"
  }
}
```

规则：

- 代理 B 写入 `third_user_map.user_type=2`。
- 该接口只生成代理自己的登录链接，不建立好友关系。
- `/index.html` 没有 `token` 时仍然保留原账号密码登录；有 `token` 时由前端自动调用 `/common/pub/login` 换正式登录态。

## 五、可选接口：查询平台配置

用于三方业务后端查询当前平台配置。

```http
POST /common/api/platformConfig
```

请求体可为空：

```json
{}
```

成功返回示例：

```json
{
  "code": 0,
  "msg": "",
  "data": {
    "id": 1,
    "name": "测试平台",
    "app_id": "tp_xxxxx",
    "default_cs_uid": 1,
    "welcome": "您好，请问有什么可以帮您？",
    "code_ttl": 120,
    "status": 1,
    "remark": "",
    "allowed_origins": []
  }
}
```

注意：该接口不会返回 `app_secret`。

## 六、可选接口：保存平台配置

用于三方业务后端维护默认客服、欢迎语、短码有效期和来源白名单。

```http
POST /common/api/savePlatformConfig
Content-Type: application/json
```

请求参数：

| 字段 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| `default_cs_uid` | number | 否 | 默认客服 IM 用户 ID。只校验用户存在且启用。 |
| `welcome` | string | 否 | 平台欢迎语。空字符串表示使用公共欢迎语。 |
| `code_ttl` | number | 否 | 短码有效期，范围 `60-1800` 秒。 |
| `allowed_origins` | array/string | 否 | 允许来源白名单。空数组表示不限制来源。 |

请求示例：

```json
{
  "default_cs_uid": 1,
  "welcome": "您好，请问有什么可以帮您？",
  "code_ttl": 120,
  "allowed_origins": [
    "https://www.example.com",
    "https://*.example.com"
  ]
}
```

成功返回：

```json
{
  "code": 0,
  "msg": "修改成功",
  "data": {
    "default_cs_uid": 1,
    "welcome": "您好，请问有什么可以帮您？",
    "code_ttl": 120,
    "allowed_origins": [
      "https://www.example.com",
      "https://*.example.com"
    ]
  }
}
```

注意：

- 如果要清空默认客服，不要传 `default_cs_uid=0`，请调用 `/common/api/clearDefaultCs`。
- `allowed_origins` 配置后，浏览器来源不在白名单内会被拒绝。
- 如果 `customerSession` 是由业务后端代调，建议业务后端把前端请求的 `Origin` 通过 `x-im-origin` 请求头转发给 IM。

## 七、可选接口：清空默认客服

```http
POST /common/api/clearDefaultCs
```

请求体可为空：

```json
{}
```

成功返回：

```json
{
  "code": 0,
  "msg": "修改成功",
  "data": {
    "default_cs_uid": 0
  }
}
```

清空后，创建客服会话时会回退使用 IM 公共默认客服。

## 八、可选接口：查询平台用户映射列表

用于三方业务后端排查用户映射关系。

```http
POST /common/api/platformUsers
Content-Type: application/json
```

请求参数：

| 字段 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| `keywords` | string | 否 | 按 `external_user_id`、昵称或 IM 用户 ID 搜索。 |
| `user_type` | string | 否 | 按用户分类筛选。 |
| `page` | number | 否 | 页码，默认 `1`。 |
| `limit` | number | 否 | 每页数量，最大 `100`。 |

请求示例：

```json
{
  "keywords": "10086",
  "page": 1,
  "limit": 10
}
```

成功返回示例：

```json
{
  "code": 0,
  "msg": "",
  "count": 1,
  "data": [
    {
      "id": 2,
      "platform_id": 1,
      "external_user_id": "10086",
      "user_id": 82,
      "nickname": "张三",
      "avatar": "",
      "user_type": "member",
      "tags": ["vip"],
      "extra": {
        "order_count": 12
      },
      "last_login_time": 1782998450,
      "create_time": "2026-07-02 21:20:27",
      "update_time": "2026-07-02 21:20:50",
      "im_user": {
        "user_id": 82,
        "realname": "张三",
        "avatar": "https://inner-admin.bvugw.sbs/static/img/avatar.png",
        "status": 1,
        "cs_uid": 1
      }
    }
  ],
  "page": 1
}
```

## 九、可选接口：查询单个用户映射

```http
POST /common/api/platformUserDetail
Content-Type: application/json
```

传 `external_user_id` 或 `user_id` 其中一个即可。

请求示例：

```json
{
  "external_user_id": "10086"
}
```

成功返回：

```json
{
  "code": 0,
  "msg": "",
  "data": {
    "external_user_id": "10086",
    "user_id": 82,
    "nickname": "张三",
    "user_type": "member",
    "im_user": {
      "user_id": 82,
      "realname": "张三",
      "status": 1
    }
  }
}
```

## 十、可选接口：更新用户分类和扩展信息

只更新已经存在的三方用户映射，不会创建新 IM 用户。创建用户仍然通过 `/common/api/customerSession` 完成。

```http
POST /common/api/savePlatformUser
Content-Type: application/json
```

请求参数：

| 字段 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| `external_user_id` | string | 是 | 三方用户 ID。 |
| `nickname` | string | 否 | 三方昵称快照。 |
| `avatar` | string | 否 | 三方头像快照。 |
| `user_type` | string | 否 | 用户分类。 |
| `tags` | array/object | 否 | 用户标签。 |
| `extra` | object | 否 | 扩展信息。 |

请求示例：

```json
{
  "external_user_id": "10086",
  "nickname": "张三",
  "user_type": "vip",
  "tags": ["vip", "high_value"],
  "extra": {
    "level": 5
  }
}
```

成功返回：

```json
{
  "code": 0,
  "msg": "修改成功"
}
```

## 十一、业务后端 PHP 示例

```php
<?php

$imHost = 'https://inner-admin.bvugw.sbs';
$appId = 'tp_a6cdf980d8082762';
$appSecret = '请填写 IM 对接人提供的 app_secret';

// 这里换成业务平台自己的登录用户。
$currentUser = [
    'id' => 10086,
    'nickname' => '张三',
    'avatar' => 'https://example.com/avatar/10086.png',
];

$timestamp = time();
$sign = md5($appId . $timestamp . $appSecret);

$headers = [
    'Content-Type: application/json',
    'x-im-appid: ' . $appId,
    'x-im-timestamp: ' . $timestamp,
    'x-im-sign: ' . $sign,
];

// 如果 IM 平台配置了来源白名单，建议转发前端 Origin。
if (!empty($_SERVER['HTTP_ORIGIN'])) {
    $headers[] = 'x-im-origin: ' . $_SERVER['HTTP_ORIGIN'];
}

$payload = [
    'external_user_id' => (string)$currentUser['id'],
    'nickname' => $currentUser['nickname'] ?? '',
    'avatar' => $currentUser['avatar'] ?? '',
    'user_type' => 'member',
    'is_mobile' => 0,
    'extra' => [
        'source' => 'business-platform',
    ],
];

$ch = curl_init($imHost . '/common/api/customerSession');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    CURLOPT_TIMEOUT => 10,
]);

$response = curl_exec($ch);
$curlError = curl_error($ch);
curl_close($ch);

if ($response === false) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['code' => 500, 'msg' => $curlError], JSON_UNESCAPED_UNICODE);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
echo $response;
```

## 十二、业务前端示例

业务前端不要直接调用 IM 开放接口，也不要计算签名。业务前端只调用自己的业务后端。

```js
async function openCustomerService() {
  const res = await fetch('/api/im/customer-session', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      page_url: location.href,
      is_mobile: window.innerWidth <= 640 ? 1 : 0
    })
  }).then(function (r) {
    return r.json();
  });

  if (res.code !== 0 || !res.data || !res.data.url) {
    alert(res.msg || '客服连接失败');
    return;
  }

  window.open(res.data.url, '_blank');
}
```

如果业务方使用 IM 提供的悬浮客服按钮 SDK：

```html
<script src="https://inner-admin.bvugw.sbs/static/js/im-customer-embed.js"></script>
<script>
  window.ImCustomerService.init({
    sessionUrl: '/api/im/customer-session',
    title: '在线客服',
    buttonText: '客服',
    position: 'right',
    payload: function () {
      return {
        page_url: location.href,
        is_mobile: window.innerWidth <= 640 ? 1 : 0
      };
    }
  });
</script>
```

## 十三、错误返回

接口统一返回 JSON：

```json
{
  "code": 400,
  "msg": "错误原因",
  "data": null
}
```

常见错误：

| 错误场景 | 说明 |
| --- | --- |
| 参数错误 | 缺少必要请求头或请求参数。 |
| 签名错误 | `x-im-sign` 计算不正确。 |
| 请求超时 | `x-im-timestamp` 与服务器时间偏差过大。 |
| 平台禁用 | 当前三方平台被 IM 后台禁用。 |
| 来源被拒绝 | 配置了 `allowed_origins`，但请求来源不在白名单内。 |
| 三方用户 ID 为空 | `customerSession` 未传 `external_user_id`。 |
| 默认客服不可用 | 平台默认客服或公共默认客服不存在、被禁用。 |
| TOKEN 已失效 | 登录短码过期或已经被使用。 |

## 十四、联调检查清单

1. 三方业务后端确认已拿到 `app_id` 和 `app_secret`。
2. 三方业务后端确认系统时间准确，避免签名时间戳超时。
3. 三方业务后端确认 `app_secret` 没有下发到浏览器。
4. 三方业务后端调用 `/common/api/platformConfig`，确认验签通过。
5. 三方业务后端调用 `/common/api/customerSession`，确认返回 `data.url`。
6. 三方业务前端打开 `data.url`，确认能自动登录聊天。
7. 同一个 `external_user_id` 重复打开客服，确认复用同一个 IM 用户。
8. 如配置了来源白名单，确认业务后端转发 `x-im-origin` 或不在白名单场景能正确拦截。
