# 业务后台接入 IM 客服流程

本文档面向三方业务平台后端开发。业务平台前端不直接调用 IM 开放接口，所有 `app_secret` 签名和 IM 账号创建都必须放在业务后台完成。

## 1. 接入目标

业务后台需要实现两个入口：

1. 用户端入口：用户在业务平台点击“联系客服”，业务后台判断该用户应该联系哪个代理，然后调用 IM 的 `pairSession`，返回聊天链接给前端打开。
2. 代理端入口：代理在业务后台点击“内嵌客服”，业务后台调用 IM 的 `agentSession`，返回代理自己的 IM 登录链接给前端打开。

如果业务平台暂时不需要指定代理，可以调用 `customerSession`，由 IM 使用平台默认客服。

## 2. 业务后台需要保存的配置

业务后台需要保存以下 IM 接入配置：

| 配置 | 说明 |
| --- | --- |
| `im_gateway` | IM 网关地址，例如 `https://inner-admin.bvugw.sbs`。 |
| `app_id` | IM 分配给三方业务平台的商户号。 |
| `app_secret` | IM 分配给三方业务平台的密钥，只能保存在后端。 |

IM 服务端生成 `pairSession` 和 `agentSession` 的 `/index.html` 自动登录链接时，链接域名取服务端配置 `[APP] AGENT_CHAT_HOST`；如果未配置，则回退使用 `[APP] HOST`。`APP.HOST` 仍可继续给 H5 端和主站资源使用。

注意：

- `app_secret` 不能返回给前端，不能写进 HTML、JS、App、小程序包。
- 业务前端只调用自己的业务后台接口，由业务后台转调 IM。

## 3. 签名规则

所有 IM 开放接口 `/common/api/*` 都需要签名请求头。

请求头：

```http
Content-Type: application/json
x-im-appid: {app_id}
x-im-timestamp: {timestamp}
x-im-sign: {sign}
```

签名算法：

```text
timestamp = 当前秒级 Unix 时间戳
sign = md5(app_id + timestamp + app_secret)
```

说明：

- `sign` 是 32 位小写 MD5。
- 拼接字符串中间不加分隔符。
- `timestamp` 与 IM 服务端时间允许约 60 秒偏差。
- 如果业务后台配置了来源白名单，后端代调 IM 时可以把前端来源通过 `x-im-origin` 透传给 IM。

PHP 签名示例：

```php
$timestamp = time();
$sign = md5($appId . $timestamp . $appSecret);

$headers = [
    'Content-Type: application/json',
    'x-im-appid: ' . $appId,
    'x-im-timestamp: ' . $timestamp,
    'x-im-sign: ' . $sign,
];
```

## 4. 推荐业务数据映射

业务后台建议记录业务用户、代理与 IM 用户 ID 的映射，方便后续排查和复用。

| 业务字段 | IM 字段 | 说明 |
| --- | --- | --- |
| 业务用户 ID | `external_user_id` | 传给 IM 的客户外部 ID，必须稳定唯一。 |
| 业务用户 IM ID | `im_user_id` | `pairSession` 返回，建议保存。 |
| 业务代理 ID | `external_agent_id` | 传给 IM 的代理外部 ID，必须稳定唯一。 |
| 业务代理 IM ID | `agent_im_user_id` 或 `im_user_id` | `pairSession` / `agentSession` 返回，建议保存。 |

身份规则：

| IM 映射身份 | 值 | 场景 |
| --- | --- | --- |
| 客户 | `1` | `pairSession` 中的 `external_user_id`。 |
| 代理 | `2` | `pairSession` / `agentSession` 中的 `external_agent_id`。 |
| 管理员 | `3` | 预留。 |

同一个平台下，同一个外部 ID 可以分别作为客户和代理存在，IM 会通过身份类型区分。

## 5. 用户端：打开指定代理聊天

### 5.1 业务流程

1. 用户登录业务平台。
2. 用户点击“联系客服”。
3. 业务前端请求业务后台，例如 `POST /api/im/customer-chat-url`。
4. 业务后台根据业务关系找到该用户对应的代理，例如用户 A 对应代理 B。
5. 业务后台调用 IM：

```http
POST {im_gateway}/common/api/pairSession
```

6. IM 创建或复用客户 A、代理 B 的 IM 账号。
7. IM 建立客户 A 和代理 B 的双向好友关系。
8. IM 返回客户 A 的自动登录聊天链接。
9. 业务后台把 `url` 返回给前端，前端打开这个地址即可聊天。

### 5.2 IM 请求参数

```json
{
  "external_user_id": "user_A_10001",
  "user_nickname": "用户A",
  "user_avatar": "https://example.com/avatar/user-a.png",
  "external_agent_id": "agent_B_20001",
  "agent_nickname": "代理B",
  "agent_avatar": "https://example.com/avatar/agent-b.png",
  "user_extra": {
    "level": "vip"
  },
  "agent_extra": {
    "group": "east"
  }
}
```

参数说明：

| 字段 | 必填 | 说明 |
| --- | --- | --- |
| `external_user_id` | 是 | 业务用户唯一 ID。 |
| `user_nickname` | 否 | 业务用户昵称。 |
| `user_avatar` | 否 | 业务用户头像 URL。 |
| `user_tags` | 否 | 业务用户标签，数组或对象。 |
| `user_extra` | 否 | 业务用户扩展信息，对象。 |
| `external_agent_id` | 是 | 业务代理唯一 ID，也兼容 `external_staff_id`。 |
| `agent_nickname` | 否 | 业务代理昵称。 |
| `agent_avatar` | 否 | 业务代理头像 URL。 |
| `agent_tags` | 否 | 业务代理标签，数组或对象，也兼容 `staff_tags`。 |
| `agent_extra` | 否 | 业务代理扩展信息，对象，也兼容 `staff_extra`。 |

### 5.3 IM 成功返回

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

返回字段说明：

| 字段 | 说明 |
| --- | --- |
| `url` | 客户 A 的自动登录聊天链接，业务前端直接打开。 |
| `token` | 一次性登录短码，前端通常不需要单独处理。 |
| `expires_in` | 短码有效期，单位秒。 |
| `im_user_id` | 客户 A 的 IM 用户 ID。 |
| `agent_im_user_id` | 代理 B 的 IM 用户 ID。 |
| `contact_id` | 默认打开的聊天对象，等于 `agent_im_user_id`。 |
| `platform_id` | IM 平台 ID。 |

业务后台建议：

- 保存 `external_user_id -> im_user_id`。
- 保存 `external_agent_id -> agent_im_user_id`。
- 把 `url` 返回给业务前端。
- 不要把 `app_secret` 返回给前端。

业务前端打开方式：

```js
window.open(res.data.url, '_blank');
```

如果要内嵌：

```html
<iframe src="{url}" style="width: 100%; height: 640px; border: 0;"></iframe>
```

## 6. 代理端：代理后台一键登录 IM

### 6.1 业务流程

1. 代理登录业务后台。
2. 代理点击“内嵌客服”。
3. 业务前端请求业务后台，例如 `POST /api/im/agent-chat-url`。
4. 业务后台调用 IM：

```http
POST {im_gateway}/common/api/agentSession
```

5. IM 创建或复用代理 IM 账号。
6. IM 返回代理的自动登录链接。
7. 业务后台把 `url` 返回给业务前端。
8. 业务前端打开 `/index.html#/login?token=...&embed=1&staff=1`，代理进入原 IM 登录后的首页。

### 6.2 IM 请求参数

```json
{
  "external_agent_id": "agent_B_20001",
  "nickname": "代理B",
  "avatar": "https://example.com/avatar/agent-b.png",
  "extra": {
    "group": "east"
  }
}
```

参数说明：

| 字段 | 必填 | 说明 |
| --- | --- | --- |
| `external_agent_id` | 是 | 业务代理唯一 ID，也兼容 `external_staff_id`。 |
| `nickname` | 否 | 代理昵称，也兼容 `agent_nickname`、`staff_nickname`。 |
| `avatar` | 否 | 代理头像，也兼容 `agent_avatar`、`staff_avatar`。 |
| `tags` | 否 | 代理标签，也兼容 `agent_tags`、`staff_tags`。 |
| `extra` | 否 | 代理扩展信息，也兼容 `agent_extra`、`staff_extra`。 |

### 6.3 IM 成功返回

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

业务后台建议：

- 保存 `external_agent_id -> im_user_id`。
- 把 `url` 返回给代理后台前端打开。
- `agentSession` 只负责代理登录，不会自动建立客户好友关系；客户和代理关系由 `pairSession` 建立。

## 7. 不指定代理：使用平台默认客服

如果业务方只需要“用户找平台默认客服”，调用：

```http
POST {im_gateway}/common/api/customerSession
```

请求示例：

```json
{
  "external_user_id": "user_A_10001",
  "nickname": "用户A",
  "avatar": "https://example.com/avatar/user-a.png",
  "user_type": "member",
  "extra": {
    "level": "vip"
  }
}
```

成功返回：

```json
{
  "code": 0,
  "msg": "",
  "data": {
    "url": "https://im.example.com?token=xxxx&contact_id=1&embed=1",
    "token": "xxxx",
    "expires_in": 120,
    "im_user_id": 101,
    "cs_uid": 1,
    "contact_id": 1,
    "platform_id": 1
  }
}
```

说明：

- `contact_id` 是默认客服 IM 用户 ID。
- 新用户首次创建时才会发送欢迎语。
- 如果平台没有配置欢迎语，会使用 IM 公共欢迎语。

## 8. 业务后台接口建议

业务平台可以封装两个自己的后端接口给前端调用。

### 8.1 客户获取聊天链接

```http
POST /api/im/customer-chat-url
```

业务后台处理逻辑：

1. 校验当前业务用户登录态。
2. 获取业务用户 ID、昵称、头像。
3. 根据业务规则找到该用户对应的代理 ID。
4. 如果有指定代理，调用 IM `pairSession`。
5. 如果没有指定代理且允许默认客服，调用 IM `customerSession`。
6. 保存 IM 返回的 `im_user_id`、`agent_im_user_id`。
7. 返回 `url` 给业务前端。

业务后台返回给前端的示例：

```json
{
  "code": 0,
  "msg": "ok",
  "data": {
    "url": "https://im.example.com/index.html#/login?token=xxxx&contact_id=202&embed=1",
    "im_user_id": 101,
    "agent_im_user_id": 202,
    "contact_id": 202,
    "expires_in": 120
  }
}
```

### 8.2 代理获取 IM 登录链接

```http
POST /api/im/agent-chat-url
```

业务后台处理逻辑：

1. 校验当前代理后台登录态。
2. 获取代理 ID、昵称、头像。
3. 调用 IM `agentSession`。
4. 保存 IM 返回的代理 `im_user_id`。
5. 返回 `url` 给代理后台前端。

业务后台返回给前端的示例：

```json
{
  "code": 0,
  "msg": "ok",
  "data": {
    "url": "https://im.example.com/index.html#/login?token=yyyy&embed=1&staff=1",
    "im_user_id": 202,
    "expires_in": 120
  }
}
```

## 9. 错误处理

IM 接口统一返回 JSON。

成功：

```json
{
  "code": 0,
  "msg": "",
  "data": {}
}
```

失败：

```json
{
  "code": 1,
  "msg": "错误原因",
  "data": null
}
```

业务后台处理建议：

- `code !== 0` 时，不要打开聊天窗口，直接把 `msg` 转成业务提示或记录日志。
- 签名失败通常检查 `app_id`、`app_secret`、时间戳和 MD5 小写。
- 短码过期或已使用时，重新调用业务后台接口获取新的 `url`。
- 不要复用旧 `url`，每次打开客服建议重新向业务后台申请。

## 10. Token 和登录态说明

IM 返回的 `token` 是一次性短码，不是正式登录态。

前端打开 `url` 后，IM 前端会用 URL 参数中的 `token` 调用：

```http
POST {im_gateway}/common/pub/login
```

登录成功后，IM 会返回正式登录态 `authToken`，后续 IM 前端聊天接口使用正式登录态。

业务平台通常不需要处理 `token` 和 `authToken`，只需要打开 IM 返回的 `url`。

## 11. 注意事项

- `external_user_id` 和 `external_agent_id` 必须稳定，不要使用昵称、手机号脱敏值、随机数。
- 业务方自己判断“用户 A 找代理 B”的关系，IM 不判断业务归属。
- `pairSession` 会创建双向好友关系，客户和代理可以按原 IM 聊天逻辑互发消息。
- `agentSession` 保留原 `/index.html` 账号密码登录逻辑；有 `token` 时自动登录，没有 `token` 时仍可手动登录。
- 登录短码默认有效期通常是 120 秒，具体以接口返回 `expires_in` 为准。
- 生产环境必须使用正式 `app_id/app_secret`，测试环境凭据由 IM 对接人单独提供。
