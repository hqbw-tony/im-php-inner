# 业务平台 IM 昵称和头像对接变更说明

本文只说明业务平台需要怎么调整 IM 对接，用于支持内嵌客服用户、代理客服的昵称和头像同步。

## 1. 业务平台需要改什么

如果业务平台已经在调用 IM 接口时传了昵称和头像，基本不需要改流程，只需要确认字段是最新值。

如果之前没有传头像或昵称，建议补充以下字段：

- 用户打开客服时，调用 `/common/api/pairSession`，传用户和代理的最新昵称、头像。
- 代理打开 IM 时，调用 `/common/api/agentSession`，传代理的最新昵称、头像。
- 如果用户或代理资料在业务平台修改后，希望不等下次打开 IM 就立即同步，可以调用 `/common/api/savePlatformUser`。

默认头像既可以在 IM 后台配置，也可以由业务后台通过 `/common/api/savePlatformConfig` 保存。业务后台可以通过 `/common/api/platformConfig` 查询当前默认客服、欢迎语、默认头像等配置。

业务平台不传单个用户头像时，IM 会按平台配置兜底；所以如果业务平台暂时没有头像字段，可以先不传。

## 2. 头像和昵称同步规则

### 2.1 打开客服时同步

业务平台调用 `/common/api/pairSession` 时：

- 传了 `user_nickname`，IM 会同步客户 IM 昵称。
- 传了 `user_avatar`，IM 会同步客户 IM 头像。
- 传了 `agent_nickname`，IM 会同步代理 IM 昵称。
- 传了 `agent_avatar`，IM 会同步代理 IM 头像。
- 不传或传空昵称时，IM 会保留已有昵称；新用户没有昵称时自动生成。
- 不传或传空头像时，IM 会保留已有头像；新用户没有头像时使用 IM 后台配置的默认头像。

### 2.2 代理登录时同步

业务平台调用 `/common/api/agentSession` 时：

- 传了 `nickname` 或 `agent_nickname`，IM 会同步代理 IM 昵称。
- 传了 `avatar` 或 `agent_avatar`，IM 会同步代理 IM 头像。
- 不传或传空时，IM 会保留已有资料；新代理没有头像时使用 IM 后台配置的代理默认头像。

### 2.3 资料修改后主动同步

业务平台如果有“用户资料修改”“代理资料修改”的场景，可以在修改成功后调用 `/common/api/savePlatformUser`。

注意：

- `nickname` 为空时，不会把 IM 昵称改空。
- `avatar` 传空字符串会清空 IM 头像；不想清空时不要传 `avatar` 字段。
- 头像 URL 建议使用完整 HTTPS 地址，且浏览器可以直接访问。

## 3. 用户联系指定代理

接口：

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
| `external_user_id` | 是 | 业务平台用户唯一 ID，必须稳定不变。 |
| `user_nickname` | 否 | 用户昵称。建议传业务平台当前最新昵称。 |
| `user_avatar` | 否 | 用户头像 URL。不传则使用 IM 已有头像或客户默认头像。 |
| `external_agent_id` | 是 | 业务平台代理唯一 ID，必须稳定不变。 |
| `agent_nickname` | 否 | 代理昵称。建议传业务平台当前最新昵称。 |
| `agent_avatar` | 否 | 代理头像 URL。不传则使用 IM 已有头像或代理默认头像。 |

返回后业务前端继续打开 `data.url` 即可，原有登录流程不需要改。

## 4. 代理后台一键打开 IM

接口：

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

返回后业务前端继续打开 `data.url` 即可。

## 5. 资料修改后主动同步

如果业务平台希望用户或代理修改资料后立即同步到 IM，可以调用：

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

字段说明：

| 字段 | 必填 | 说明 |
| --- | --- | --- |
| `external_user_id` | 是 | 已经在 IM 创建过映射的业务用户或代理 ID。 |
| `nickname` | 否 | 要同步到 IM 的昵称。 |
| `avatar` | 否 | 要同步到 IM 的头像 URL。 |
| `tags` | 否 | 业务标签，数组或对象。 |
| `extra` | 否 | 业务扩展信息，对象。 |

推荐用法：

- 用户修改资料后：传用户的 `external_user_id`、最新 `nickname`、最新 `avatar`。
- 代理修改资料后：传代理的 `external_agent_id` 作为 `external_user_id`，再传最新 `nickname`、`avatar`。
- 如果只想在下次打开客服时同步，可以不调用该接口，只在 `pairSession` / `agentSession` 里传最新资料。

## 6. 默认头像怎么使用

默认头像支持两种维护方式：

- IM 后台配置。
- 业务后台调用 IM 开放接口保存。

IM 后台配置入口：

```text
后台管理 -> 三方平台管理 -> 编辑平台 -> 客户默认头像 / 代理默认头像
```

业务后台保存配置接口：

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

字段说明：

| 字段 | 必填 | 说明 |
| --- | --- | --- |
| `default_customer_avatar` | 否 | 客户默认头像 URL。传空字符串可以清空当前配置。 |
| `default_agent_avatar` | 否 | 代理默认头像 URL。传空字符串可以清空当前配置。 |
| `default_cs_uid` | 否 | 平台默认客服 IM 用户 ID。 |
| `welcome` | 否 | 平台欢迎语。 |
| `code_ttl` | 否 | 临时登录码有效期，单位秒。 |
| `allowed_origins` | 否 | 允许的前端来源配置。 |

业务后台查询配置接口：

```http
POST {im_gateway}/common/api/platformConfig
```

返回里重点关注：

```json
{
  "code": 0,
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

业务平台侧规则：

- 想使用业务平台自己的头像：调用 IM 接口时传头像 URL。
- 想使用平台默认头像：不要传头像字段，或者传空。
- 新客户默认用“客户默认头像”。
- 新代理默认用“代理默认头像”。

## 7. 签名保持不变

本次变更没有修改签名规则。

业务后台请求 IM 开放接口时继续带请求头：

```http
x-im-appid: {app_id}
x-im-timestamp: {timestamp}
x-im-sign: md5(app_id + timestamp + app_secret)
```

`app_secret` 只能保存在业务后台，不能返回给前端。

## 8. 对接建议

- `external_user_id` 和 `external_agent_id` 必须使用业务平台稳定唯一 ID，不要使用昵称、手机号、头像 URL。
- 每次打开客服前，业务后台最好从业务数据库读取最新昵称和头像，再调用 IM。
- 头像 URL 建议使用 HTTPS，且不要需要登录态才能访问。
- 业务前端不需要处理昵称和头像同步逻辑，只需要打开 IM 返回的 `data.url`。
- 原有 `token`、`contact_id`、`embed` 参数处理方式不变。
