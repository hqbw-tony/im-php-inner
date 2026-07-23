# 三方平台总后台代理会话接入

本文档用于业务平台的总后台接入代理会话管理页。业务平台后端只需要调用一个接口获取登录链接；IM 页面会自行加载代理、会话和消息。

## 上线前配置

执行 SQL 文件：

```text
public/sql/6.2.5-third-chat-manager.sql
```

部署以下静态文件到总后台聊天前端所在站点：

```text
public/manager.html
public/assets/css/manager-chat.css
public/assets/js/manager-chat.js
```

可选环境变量：

```ini
# 三方平台总后台聊天页面域名；留空时复用 AGENT_CHAT_HOST，仍留空时使用 HOST
MANAGER_CHAT_HOST = https://im.example.com
```

`MANAGER_CHAT_HOST` 应指向已部署 `manager.html` 的站点根目录，并且该域名能够访问 IM 的 `/common/*` 与 `/enterprise/*` 接口。

## 业务后台调用

### 获取总后台聊天链接

```text
POST /common/api/managerSession
```

请求仍使用现有三方平台验签，不要在浏览器中保存 `app_secret`。

请求头：

```text
x-im-appid: 平台 AppID
x-im-timestamp: 当前 Unix 时间戳（秒）
x-im-sign: md5(appid + timestamp + app_secret)
```

请求参数：

| 参数 | 必填 | 说明 |
| --- | --- | --- |
| `external_manager_id` | 是 | 业务平台总后台管理员的稳定 ID |
| `nickname` | 否 | 管理员显示名称 |
| `avatar` | 否 | 管理员头像 URL |
| `tags` | 否 | 管理员标签，可为 JSON |
| `extra` | 否 | 扩展资料，可为 JSON |

兼容别名：`external_admin_id`、`manager_nickname`、`manager_avatar`、`admin_nickname`、`admin_avatar`。

请求示例：

```json
{
  "external_manager_id": "merchant-admin-1001",
  "nickname": "平台总后台"
}
```

成功响应中的 `data.url` 直接用于打开新页或嵌入 iframe：

```json
{
  "code": 0,
  "data": {
    "url": "https://im.example.com/manager.html?token=...&embed=1&manager=1",
    "token": "...",
    "expires_in": 120,
    "im_user_id": 301,
    "platform_id": 1,
    "user_type": "3"
  }
}
```

`token` 是一次性短码，只供页面首次调用 `/common/pub/login` 换取正式登录态。业务前端不需要自行处理短码或调用会话、消息接口。

## 总后台页面行为

- 默认展示当前三方平台下全部代理的客户会话。
- 选择代理后，只展示该代理的会话。
- 打开某个会话会读取原有客户与代理私聊记录。
- 总后台读取消息会将该会话内“客户发给代理”的未读消息设置为已读；代理打开 `/index.html` 后会看到相同的已读状态。
- 总后台发送消息时，IM 会验证当前平台、客户、代理和双向好友关系，随后以所选代理的 IM 账号发送。客户和代理均在原会话中看到该消息。
- 总后台不会记录“是否由总后台代回复”；消息展示为代理本人发送，符合当前需求。

## 安全边界

- 一个总后台管理员只能访问自己所属 `platform_id` 下的代理和客户会话。
- 页面不接受由浏览器任意指定的客户或代理 ID；读取和发送均通过 `session_id` 再次校验会话归属。
- 即使业务平台请求参数被篡改，也不能跨三方平台查看历史或冒充其他平台代理发送消息。
- 原 `/index.html`、`/common/api/customerSession`、`/common/api/pairSession`、`/common/api/agentSession` 不受此功能影响。
