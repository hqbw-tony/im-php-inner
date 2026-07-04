# 三方业务平台 IM 客服对接文档（最新版）

本文档面向三方业务平台后端开发。业务平台前端不直接调用 IM 开放接口，所有签名、账号创建、默认头像配置都由业务平台后端调用 IM 完成。

## 1. 本次修改点

本次 IM 侧新增和调整的能力如下：

1. 新增平台默认头像配置：
   - `default_customer_avatar`：客户默认头像。
   - `default_agent_avatar`：代理默认头像。
2. 业务后台可以通过 `/common/api/savePlatformConfig` 保存默认头像，也可以继续在 IM 后台手动配置。
3. 业务后台可以通过 `/common/api/platformConfig` 查询当前平台配置，包括默认客服信息、默认头像、欢迎语等。
4. 创建业务方聊天账号时，如果没有传头像：
   - 客户账号使用 `default_customer_avatar`。
   - 代理账号使用 `default_agent_avatar`。
   - 如果平台默认头像也没配置，则头像为空，由 IM 前端按原有默认头像逻辑展示。
5. 如果业务方传了头像，以业务方传入的头像为准。
6. 已存在的 IM 账号再次打开客服时，如果业务方不传头像，不会覆盖已有头像。
7. 业务方可以在用户或代理资料修改后调用 `/common/api/savePlatformUser` 主动同步昵称和头像。

## 2. 业务方需要改什么

业务平台后端建议补充以下能力：

| 改动 | 是否必须 | 说明 |
| --- | --- | --- |
| 保存 IM 接入配置 | 是 | 保存 `im_gateway`、`app_id`、`app_secret`，`app_secret` 只能放后端。 |
| 调用 `savePlatformConfig` 保存默认头像 | 否 | 如果业务方想自己维护客户/代理默认头像，可以调用。 |
| 调用 `platformConfig` 查询默认客服信息 | 否 | 用于后台展示当前 IM 默认客服、默认头像、欢迎语。 |
| 调用 `pairSession` 创建用户和代理聊天关系 | 是，指定代理模式需要 | 用户 A 找代理 B 时使用。 |
| 调用 `agentSession` 给代理一键登录 IM | 是，代理后台入口需要 | 代理在业务后台点击“内嵌客服”时使用。 |
| 传昵称和头像 | 建议 | 打开客服时传最新昵称、头像；不传头像时 IM 使用默认头像兜底。 |
| 调用 `savePlatformUser` 主动同步资料 | 可选 | 资料修改后想立即同步到 IM 时使用。 |

## 3. 公共签名规则

所有 IM 开放接口 `/common/api/*` 都需要请求头签名。

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
- `timestamp` 使用秒级时间戳，和 IM 服务端时间允许约 60 秒偏差。
- `app_secret` 只能保存在业务后台，不能返回给前端。

PHP 示例：

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

## 4. 保存平台默认头像和配置

业务后台可以用这个接口保存默认头像、默认客服、欢迎语等配置。

```http
POST {im_gateway}/common/api/savePlatformConfig
```

只保存默认头像时，请求示例：

```json
{
  "default_customer_avatar": "https://static.example.com/avatar/default-customer.png",
  "default_agent_avatar": "https://static.example.com/avatar/default-agent.png"
}
```

可保存字段：

| 字段 | 必填 | 说明 |
| --- | --- | --- |
| `default_customer_avatar` | 否 | 客户默认头像 URL，最大 255 字符。传空字符串可清空。 |
| `default_agent_avatar` | 否 | 代理默认头像 URL，最大 255 字符。传空字符串可清空。 |
| `default_cs_uid` | 否 | 平台默认客服 IM 用户 ID。 |
| `welcome` | 否 | 平台欢迎语。 |
| `code_ttl` | 否 | 临时登录码有效期，单位秒。 |
| `allowed_origins` | 否 | 允许的前端来源配置。 |

返回示例：

```json
{
  "code": 0,
  "msg": "保存成功",
  "data": {
    "default_cs_uid": 100,
    "default_cs_user": {
      "user_id": 100,
      "realname": "客服A",
      "avatar": "https://static.example.com/avatar/cs.png",
      "status": 1
    },
    "default_customer_avatar": "https://static.example.com/avatar/default-customer.png",
    "default_agent_avatar": "https://static.example.com/avatar/default-agent.png",
    "welcome": "您好，请问有什么可以帮您？",
    "effective_welcome": "您好，请问有什么可以帮您？"
  }
}
```

## 5. 查询平台默认客服和默认头像

业务后台可以用这个接口查看当前 IM 平台配置。

```http
POST {im_gateway}/common/api/platformConfig
```

返回里重点关注：

| 字段 | 说明 |
| --- | --- |
| `default_cs_uid` | 当前平台默认客服 IM 用户 ID。 |
| `default_cs_user` | 当前平台默认客服信息，包含 `user_id`、`realname`、`avatar`、`status`。 |
| `default_customer_avatar` | 客户默认头像。 |
| `default_agent_avatar` | 代理默认头像。 |
| `welcome` | 平台单独配置的欢迎语。 |
| `effective_welcome` | 实际生效欢迎语，平台没配置时会使用公共欢迎语。 |
| `code_ttl` | 临时登录码有效期。 |

## 6. 用户联系指定代理

业务方判断“用户 A 应该联系代理 B”后，调用该接口。IM 不判断业务关系，只负责创建或复用双方 IM 账号、建立好友关系、返回用户聊天链接。

```http
POST {im_gateway}/common/api/pairSession
```

请求示例：

```json
{
  "external_user_id": "user_10001",
  "user_nickname": "张三",
  "user_avatar": "https://static.example.com/avatar/user_10001.png",
  "external_agent_id": "agent_20001",
  "agent_nickname": "代理李四",
  "agent_avatar": "https://static.example.com/avatar/agent_20001.png"
}
```

字段说明：

| 字段 | 必填 | 说明 |
| --- | --- | --- |
| `external_user_id` | 是 | 业务平台用户稳定唯一 ID。 |
| `user_nickname` | 否 | 用户昵称，建议传业务平台当前最新昵称。 |
| `user_avatar` | 否 | 用户头像 URL。不传时新账号使用客户默认头像。 |
| `external_agent_id` | 是 | 业务平台代理稳定唯一 ID。 |
| `agent_nickname` | 否 | 代理昵称，建议传业务平台当前最新昵称。 |
| `agent_avatar` | 否 | 代理头像 URL。不传时新账号使用代理默认头像。 |
| `user_tags` | 否 | 客户业务标签。 |
| `user_extra` | 否 | 客户业务扩展信息。 |
| `agent_tags` | 否 | 代理业务标签。 |
| `agent_extra` | 否 | 代理业务扩展信息。 |

返回示例：

```json
{
  "code": 0,
  "msg": "",
  "data": {
    "url": "https://im.example.com?token=xxx&contact_id=200&embed=1",
    "token": "xxx",
    "expires_in": 120,
    "im_user_id": 101,
    "agent_im_user_id": 200,
    "contact_id": 200,
    "platform_id": 1
  }
}
```

业务前端拿到 `data.url` 后直接打开即可，可以使用 iframe、弹窗或新窗口。

## 7. 代理后台一键登录 IM

代理在业务后台点击“内嵌客服”时，业务后台调用该接口，返回代理自己的 IM 登录链接。

```http
POST {im_gateway}/common/api/agentSession
```

请求示例：

```json
{
  "external_agent_id": "agent_20001",
  "nickname": "代理李四",
  "avatar": "https://static.example.com/avatar/agent_20001.png"
}
```

兼容字段：

| 标准字段 | 兼容字段 | 说明 |
| --- | --- | --- |
| `external_agent_id` | `external_staff_id` | 代理唯一 ID。 |
| `nickname` | `agent_nickname`、`staff_nickname` | 代理昵称。 |
| `avatar` | `agent_avatar`、`staff_avatar` | 代理头像 URL。 |

返回示例：

```json
{
  "code": 0,
  "msg": "",
  "data": {
    "url": "https://agent-im.example.com/index.html?token=xxx&embed=1&staff=1",
    "token": "xxx",
    "expires_in": 120,
    "im_user_id": 200,
    "platform_id": 1,
    "user_type": "2"
  }
}
```

业务前端拿到 `data.url` 后直接打开即可。该链接用于代理登录 IM 原有首页，不影响用户端内嵌客服链接。

## 8. 普通默认客服模式

如果业务平台不需要“用户联系指定代理”，可以调用默认客服接口。IM 会使用平台默认客服，平台没配置时回退公共默认客服。

```http
POST {im_gateway}/common/api/customerSession
```

请求示例：

```json
{
  "external_user_id": "user_10001",
  "nickname": "张三",
  "avatar": "https://static.example.com/avatar/user_10001.png"
}
```

返回重点字段：

| 字段 | 说明 |
| --- | --- |
| `url` | 用户聊天链接，前端直接打开。 |
| `token` | 一次性登录短码。 |
| `expires_in` | 登录短码有效期。 |
| `im_user_id` | 用户对应的 IM 用户 ID。 |
| `cs_uid` | 本次会话客服 IM 用户 ID。 |
| `contact_id` | 前端进入会话使用的联系人 ID。 |

## 9. 主动同步用户或代理资料

如果业务平台用户或代理修改了昵称、头像，想立即同步到 IM，可以调用：

```http
POST {im_gateway}/common/api/savePlatformUser
```

请求示例：

```json
{
  "external_user_id": "user_10001",
  "nickname": "张三新昵称",
  "avatar": "https://static.example.com/avatar/user_10001_new.png"
}
```

代理资料同步时，把代理的 `external_agent_id` 作为 `external_user_id` 传入：

```json
{
  "external_user_id": "agent_20001",
  "nickname": "代理李四新昵称",
  "avatar": "https://static.example.com/avatar/agent_20001_new.png"
}
```

规则：

- `nickname` 不传或传空，不会把 IM 昵称改空。
- `avatar` 不传，不会改 IM 头像。
- `avatar` 传空字符串，会清空 IM 头像。
- 也可以不调用该接口，只在下次 `pairSession` 或 `agentSession` 时传最新昵称和头像。

## 10. 默认头像生效规则

默认头像只用于“新建 IM 账号且业务方没有传头像”的场景。

| 场景 | 结果 |
| --- | --- |
| 新建客户账号，传了 `user_avatar` 或 `avatar` | 使用业务方传入的头像。 |
| 新建客户账号，没传头像 | 使用 `default_customer_avatar`。 |
| 新建代理账号，传了 `agent_avatar` 或 `avatar` | 使用业务方传入的头像。 |
| 新建代理账号，没传头像 | 使用 `default_agent_avatar`。 |
| 账号已存在，本次没传头像 | 保留已有头像，不覆盖。 |
| 平台默认头像没配置 | 头像为空，由 IM 前端默认头像逻辑展示。 |

## 11. 业务方推荐流程

### 11.1 初始化配置

1. 业务后台保存 `im_gateway`、`app_id`、`app_secret`。
2. 业务后台调用 `/common/api/savePlatformConfig` 保存客户默认头像、代理默认头像。
3. 业务后台调用 `/common/api/platformConfig` 展示或校验默认客服、默认头像配置。

### 11.2 用户打开客服

1. 业务前端调用业务后台自己的接口。
2. 业务后台确认当前用户 `external_user_id`。
3. 业务后台按自己的业务关系找到代理 `external_agent_id`。
4. 业务后台调用 `/common/api/pairSession`，尽量传用户和代理最新昵称、头像。
5. 业务后台把 IM 返回的 `data.url` 返回给业务前端。
6. 业务前端打开 `data.url`，用户进入和指定代理的聊天。

### 11.3 代理打开 IM

1. 代理在业务后台点击“内嵌客服”。
2. 业务后台调用 `/common/api/agentSession`。
3. 业务后台把 IM 返回的 `data.url` 返回给前端。
4. 前端打开 `data.url`，代理自动登录 IM。

## 12. 注意事项

- `external_user_id` 和 `external_agent_id` 必须稳定唯一，不要使用昵称、手机号、头像 URL。
- 业务后台建议保存 IM 返回的 `im_user_id`、`agent_im_user_id`，方便排查。
- 头像 URL 建议使用完整 HTTPS 地址，并且浏览器可以直接访问。
- 前端不要自己拼 IM 登录链接，直接打开 IM 返回的 `data.url`。
- `token` 是一次性临时登录短码，有过期时间，前端不需要自己换正式登录态。
- `app_secret` 不能下发给前端。
- 用户端链接和代理端链接使用不同用途，不要混用：
  - 用户聊天：`pairSession` 或 `customerSession` 返回的 `url`。
  - 代理登录：`agentSession` 返回的 `url`。
