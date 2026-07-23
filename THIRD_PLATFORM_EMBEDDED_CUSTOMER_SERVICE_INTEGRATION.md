# 三方业务平台内嵌客服完整对接文档

本文档面向三方业务平台后端和前端开发。目标是让业务平台用户在自己的业务页面点击“联系客服”后，自动进入 IM 客服聊天页面；业务平台代理或客服也可以从业务后台一键进入 IM。

## 1. 对接目标

业务平台需要实现三类能力：

| 能力 | 是否必须 | 说明 |
| --- | --- | --- |
| 用户打开内嵌客服 | 是 | 用户点击“联系客服”，业务后端向 IM 获取聊天链接，前端打开链接。 |
| 用户联系指定代理/客服 | 按业务需要 | 业务后端判断用户应该找哪个代理，然后调用 `pairSession`。 |
| 代理/客服一键登录 IM | 按业务需要 | 代理在业务后台点击“内嵌客服”，调用 `agentSession` 自动登录 IM。 |

推荐架构：

```text
业务前端 -> 业务后端 -> IM 开放接口
业务前端 <- 业务后端 <- IM 返回客服链接
业务前端打开 IM 返回的 data.url
```

重要原则：

- 业务前端不要直接调用 IM 开放接口。
- `app_secret` 只能保存在业务后端，不能写进前端代码、App、小程序或网页源码。
- 业务前端不要自己拼接 IM 登录链接，直接打开 IM 返回的 `data.url`。

## 2. IM 侧提供的信息

IM 方需要给业务平台提供：

| 配置 | 说明 |
| --- | --- |
| `im_gateway` | IM 开放接口网关，例如 `https://im-api.example.com`。 |
| `app_id` | 三方业务平台商户号。 |
| `app_secret` | 三方业务平台密钥，只能放在业务后端。 |
| 用户聊天页域名 | 由 IM 后端配置并生成到返回的 `data.url`。业务方不用拼。 |
| 代理登录页域名 | 由 IM 后端配置并生成到 `agentSession` 返回的 `data.url`。 |

IM 后台可配置：

- 平台默认客服。
- 平台欢迎语。
- 客户默认头像。
- 代理默认头像。
- 登录短码有效期。
- 来源白名单。

## 3. 名词说明

| 名词 | 说明 |
| --- | --- |
| `external_user_id` | 业务平台用户唯一 ID，必须稳定不变。 |
| `external_agent_id` | 业务平台代理/客服唯一 ID，必须稳定不变。 |
| `im_user_id` | IM 系统里的用户 ID。 |
| `agent_im_user_id` | IM 系统里的代理/客服用户 ID。 |
| `contact_id` | 进入聊天后要打开的联系人 ID，通常是客服/代理的 IM 用户 ID。 |
| `token` | IM 返回的一次性登录短码，只用于打开 IM 页面自动登录。 |
| `data.url` | IM 返回的完整客服链接，业务前端直接打开。 |

不要用昵称、手机号、头像 URL 作为 `external_user_id` 或 `external_agent_id`。

## 4. 开放接口签名

所有 `/common/api/*` 开放接口都需要签名请求头。

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
- `timestamp` 使用秒级时间戳。
- IM 服务端允许约 60 秒时间偏差。

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

Node.js 示例：

```js
const crypto = require('crypto');

const timestamp = Math.floor(Date.now() / 1000).toString();
const sign = crypto
  .createHash('md5')
  .update(appId + timestamp + appSecret)
  .digest('hex');

const headers = {
  'Content-Type': 'application/json',
  'x-im-appid': appId,
  'x-im-timestamp': timestamp,
  'x-im-sign': sign,
};
```

## 5. 推荐对接流程

### 5.1 用户端打开客服

1. 用户在业务前端点击“联系客服”。
2. 业务前端请求业务后端，例如 `POST /api/im/customer-session`。
3. 业务后端读取当前登录用户信息，得到稳定的 `external_user_id`、昵称、头像。
4. 如果业务平台有“用户所属代理/客服”关系，业务后端查出 `external_agent_id`。
5. 业务后端调用 IM：
   - 用户联系指定代理/客服：调用 `/common/api/pairSession`。
   - 用户联系平台默认客服：调用 `/common/api/customerSession`。
6. IM 创建或复用双方 IM 账号，建立好友关系，生成一次性登录短码。
7. IM 返回 `data.url`。
8. 业务后端把 `data.url` 返回给业务前端。
9. 业务前端使用 iframe、弹窗或新窗口打开 `data.url`。

### 5.2 代理/客服后台打开 IM

1. 代理在业务后台点击“内嵌客服”。
2. 业务后端读取代理 ID、昵称、头像。
3. 业务后端调用 `/common/api/agentSession`。
4. IM 创建或复用代理 IM 账号，生成一次性登录短码。
5. 业务前端打开 IM 返回的 `data.url`。

## 6. 核心接口一：用户联系指定代理/客服

当业务平台自己能判断“用户 A 应该找代理 B”时，推荐使用该接口。

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
| `user_nickname` | 否 | 用户昵称，建议每次传业务平台最新昵称。 |
| `user_avatar` | 否 | 用户头像 URL，建议每次传最新头像。 |
| `external_agent_id` | 是 | 业务平台代理/客服稳定唯一 ID。 |
| `agent_nickname` | 否 | 代理/客服昵称，建议每次传最新昵称。 |
| `agent_avatar` | 否 | 代理/客服头像 URL，建议每次传最新头像。 |
| `user_tags` | 否 | 用户业务标签。 |
| `user_extra` | 否 | 用户业务扩展信息。 |
| `agent_tags` | 否 | 代理业务标签。 |
| `agent_extra` | 否 | 代理业务扩展信息。 |

返回示例：

```json
{
  "code": 0,
  "msg": "",
  "data": {
    "url": "https://im-chat.example.com?token=xxx&contact_id=200&embed=1",
    "token": "xxx",
    "expires_in": 120,
    "im_user_id": 101,
    "agent_im_user_id": 200,
    "contact_id": 200,
    "platform_id": 1
  }
}
```

业务方处理：

- 前端直接打开 `data.url`。
- `im_user_id` 是用户在 IM 里的 ID，建议业务后端保存映射。
- `agent_im_user_id` 是代理/客服在 IM 里的 ID，建议业务后端保存映射。
- `contact_id` 是本次聊天对象 ID，通常等于 `agent_im_user_id`。

## 7. 核心接口二：用户联系平台默认客服

如果业务平台不区分代理/客服，或者只需要联系平台默认客服，使用该接口。

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

字段说明：

| 字段 | 必填 | 说明 |
| --- | --- | --- |
| `external_user_id` | 是 | 业务平台用户稳定唯一 ID。 |
| `nickname` | 否 | 用户昵称，建议传最新昵称。 |
| `avatar` | 否 | 用户头像 URL，建议传最新头像。 |
| `tags` | 否 | 用户业务标签。 |
| `extra` | 否 | 用户业务扩展信息。 |
| `is_mobile` | 否 | 是否移动端，按业务页面需要传。 |

返回示例：

```json
{
  "code": 0,
  "msg": "",
  "data": {
    "url": "https://im-chat.example.com?token=xxx&contact_id=102&embed=1",
    "token": "xxx",
    "expires_in": 120,
    "im_user_id": 101,
    "cs_uid": 102,
    "contact_id": 102,
    "platform_id": 1
  }
}
```

说明：

- `cs_uid` 是本次分配到的客服 IM 用户 ID。
- `contact_id` 是聊天对象 ID，通常等于 `cs_uid`。
- 平台未配置默认客服时，IM 会回退公共默认客服；如果公共默认客服也未配置，会返回错误。

## 8. 核心接口三：代理/客服一键登录 IM

业务后台需要给代理/客服提供“进入 IM”的入口时，使用该接口。

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
| `external_agent_id` | `external_staff_id` | 代理/客服稳定唯一 ID。 |
| `nickname` | `agent_nickname`、`staff_nickname` | 代理/客服昵称。 |
| `avatar` | `agent_avatar`、`staff_avatar` | 代理/客服头像 URL。 |

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

业务方处理：

- 前端直接打开 `data.url`。
- 该链接用于代理/客服进入 IM 原有首页。
- 该流程不影响用户端内嵌客服链接。

## 9. 查询和维护平台配置

### 9.1 查询平台配置

```http
POST {im_gateway}/common/api/platformConfig
```

返回重点字段：

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
    "effective_welcome": "您好，请问有什么可以帮您？",
    "code_ttl": 120,
    "allowed_origins": []
  }
}
```

### 9.2 保存平台配置

```http
POST {im_gateway}/common/api/savePlatformConfig
```

请求示例：

```json
{
  "default_customer_avatar": "https://static.example.com/avatar/default-customer.png",
  "default_agent_avatar": "https://static.example.com/avatar/default-agent.png",
  "welcome": "您好，请问有什么可以帮您？",
  "code_ttl": 120
}
```

可保存字段：

| 字段 | 必填 | 说明 |
| --- | --- | --- |
| `default_customer_avatar` | 否 | 客户默认头像 URL，传空字符串可清空。 |
| `default_agent_avatar` | 否 | 代理/客服默认头像 URL，传空字符串可清空。 |
| `default_cs_uid` | 否 | 平台默认客服 IM 用户 ID。 |
| `welcome` | 否 | 平台欢迎语，留空则使用公共欢迎语。 |
| `code_ttl` | 否 | 登录短码有效期，单位秒。 |
| `allowed_origins` | 否 | 允许的前端来源配置。 |

## 10. 昵称和头像同步规则

业务方建议每次调用 `pairSession`、`customerSession`、`agentSession` 时都传当前最新昵称和头像。

同步规则：

| 场景 | 规则 |
| --- | --- |
| 新建客户账号，传了头像 | 使用业务方传入的头像。 |
| 新建客户账号，没传头像 | 使用平台 `default_customer_avatar`。 |
| 新建代理/客服账号，传了头像 | 使用业务方传入的头像。 |
| 新建代理/客服账号，没传头像 | 使用平台 `default_agent_avatar`。 |
| 已有账号，再次调用时传了昵称/头像 | IM 会同步更新昵称/头像。 |
| 已有账号，再次调用时没传头像 | 保留已有头像，不覆盖。 |
| 平台默认头像也没配置 | 头像为空，由 IM 前端默认头像逻辑展示。 |

## 11. 主动同步用户或代理资料

如果业务平台在“用户资料修改”“代理资料修改”后希望立即同步到 IM，可以调用：

```http
POST {im_gateway}/common/api/savePlatformUser
```

同步用户资料示例：

```json
{
  "external_user_id": "user_10001",
  "nickname": "张三新昵称",
  "avatar": "https://static.example.com/avatar/user_10001_new.png"
}
```

同步代理资料示例：

```json
{
  "external_user_id": "agent_20001",
  "nickname": "代理李四新昵称",
  "avatar": "https://static.example.com/avatar/agent_20001_new.png"
}
```

说明：

- 代理同步时，把 `external_agent_id` 作为 `external_user_id` 传入。
- `nickname` 不传或传空，不会把 IM 昵称改空。
- `avatar` 不传，不会修改 IM 头像。
- `avatar` 传空字符串，会清空 IM 头像。

## 12. 业务前端如何内嵌

业务前端只需要打开业务后端返回的 `url`。

### 12.1 iframe 方式

```html
<iframe
  id="im-customer-service"
  src=""
  style="width: 100%; height: 720px; border: 0;"
></iframe>
```

```js
async function openCustomerService() {
  const res = await fetch('/api/im/customer-session', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'include'
  });
  const json = await res.json();
  if (json.code !== 0) {
    throw new Error(json.msg || '获取客服链接失败');
  }
  document.getElementById('im-customer-service').src = json.data.url;
}
```

### 12.2 弹窗或新窗口方式

```js
async function openCustomerServiceWindow() {
  const res = await fetch('/api/im/customer-session', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'include'
  });
  const json = await res.json();
  if (json.code !== 0) {
    throw new Error(json.msg || '获取客服链接失败');
  }
  window.open(json.data.url, '_blank');
}
```

前端注意事项：

- 不要缓存 `data.url` 长期复用。
- `token` 是一次性短码，过期或使用后需要重新向业务后端获取链接。
- `embed=1` 表示 IM 前端按内嵌模式处理。
- 如果 iframe 被浏览器或网关策略拦截，需要 IM 部署侧允许业务域名嵌入。

## 13. 业务后端示例

以下示例只演示核心逻辑，实际项目需要结合自己的登录态、数据库和异常处理。

### 13.1 PHP 示例：获取指定代理客服链接

```php
function imSignHeaders($appId, $appSecret) {
    $timestamp = time();
    return [
        'Content-Type: application/json',
        'x-im-appid: ' . $appId,
        'x-im-timestamp: ' . $timestamp,
        'x-im-sign: ' . md5($appId . $timestamp . $appSecret),
    ];
}

function postJson($url, $headers, $payload) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    $body = curl_exec($ch);
    if ($body === false) {
        throw new RuntimeException(curl_error($ch));
    }
    curl_close($ch);
    return json_decode($body, true);
}

$payload = [
    'external_user_id' => (string)$currentUser['id'],
    'user_nickname' => $currentUser['nickname'],
    'user_avatar' => $currentUser['avatar'],
    'external_agent_id' => (string)$agent['id'],
    'agent_nickname' => $agent['nickname'],
    'agent_avatar' => $agent['avatar'],
];

$result = postJson(
    $imGateway . '/common/api/pairSession',
    imSignHeaders($appId, $appSecret),
    $payload
);

if (($result['code'] ?? -1) !== 0) {
    throw new RuntimeException($result['msg'] ?? '获取客服链接失败');
}

return [
    'code' => 0,
    'data' => [
        'url' => $result['data']['url'],
        'im_user_id' => $result['data']['im_user_id'],
        'agent_im_user_id' => $result['data']['agent_im_user_id'],
    ],
];
```

### 13.2 Node.js 示例：获取默认客服链接

```js
const crypto = require('crypto');

async function requestCustomerSession(currentUser) {
  const timestamp = Math.floor(Date.now() / 1000).toString();
  const sign = crypto
    .createHash('md5')
    .update(appId + timestamp + appSecret)
    .digest('hex');

  const payload = {
    external_user_id: String(currentUser.id),
    nickname: currentUser.nickname,
    avatar: currentUser.avatar,
  };

  const res = await fetch(`${imGateway}/common/api/customerSession`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'x-im-appid': appId,
      'x-im-timestamp': timestamp,
      'x-im-sign': sign,
    },
    body: JSON.stringify(payload),
  });

  const json = await res.json();
  if (json.code !== 0) {
    throw new Error(json.msg || '获取客服链接失败');
  }

  return {
    url: json.data.url,
    im_user_id: json.data.im_user_id,
    contact_id: json.data.contact_id,
  };
}
```

## 14. 如果业务方自研 IM 前端

如果业务方不直接打开 IM 返回的 `data.url`，而是自己解析 `token` 并接入 IM 前端接口，需要额外处理登录态。

推荐方式仍然是直接打开 `data.url`。只有自研前端才需要了解下面内容：

1. 从 `data.url` 或接口响应里拿到 `token`。
2. 调用 IM 登录接口 `/common/pub/login`，传入 `token`。
3. 登录成功后会返回正式登录态，例如：
   - `sessionId`
   - `authToken`
   - `userInfo`
   - `contact_id`
   - `embed`
4. 后续调用 `/enterprise/*` 接口时使用 `authToken`。
5. 如果要查当前登录用户信息，使用 `userInfo.user_id`。
6. 如果要查当前聊天对象信息，使用 `contact_id`。

普通业务平台不建议走这条链路，直接打开 `data.url` 最稳。

## 15. 业务平台建议保存的映射

业务后端建议保存以下映射，方便排查和复用：

| 业务字段 | IM 字段 | 说明 |
| --- | --- | --- |
| 业务用户 ID | `external_user_id` | 调 IM 时传入。 |
| 用户 IM ID | `im_user_id` | IM 返回后保存。 |
| 业务代理/客服 ID | `external_agent_id` | 调 IM 时传入。 |
| 代理/客服 IM ID | `agent_im_user_id` 或 `im_user_id` | IM 返回后保存。 |
| 最近联系对象 | `contact_id` | 可选，方便前端定位会话。 |

不建议长期保存并复用 `token` 或 `data.url`，因为登录短码会过期且可能一次性失效。

## 16. 错误处理建议

常见错误和处理方式：

| 错误场景 | 处理建议 |
| --- | --- |
| 签名失败 | 检查 `app_id`、`app_secret`、时间戳、MD5 拼接顺序。 |
| 时间戳过期 | 确认业务服务器时间准确，使用秒级时间戳。 |
| 未配置默认客服 | 使用 `customerSession` 时需要 IM 后台配置平台默认客服或公共默认客服。 |
| 代理 ID 为空 | 使用 `pairSession` 时必须传 `external_agent_id`。 |
| 打开链接后登录失败 | 重新调用业务后端获取新的 `data.url`，不要复用旧 token。 |
| iframe 无法展示 | 检查 IM 域名是否允许被业务平台域名嵌入。 |
| 头像不显示 | 确认头像 URL 是完整 HTTPS 地址，浏览器可直接访问。 |

## 17. 上线检查清单

业务方上线前请确认：

- 已保存 IM 提供的 `im_gateway`、`app_id`、`app_secret`。
- `app_secret` 没有出现在前端代码或接口返回里。
- `external_user_id` 稳定唯一。
- 如果使用指定代理模式，`external_agent_id` 稳定唯一。
- 业务后端能成功调用 `pairSession` 或 `customerSession`。
- 业务前端能打开 `data.url`。
- token 过期后会重新获取链接。
- 用户昵称、头像会在每次打开客服时传给 IM。
- 代理昵称、头像会在 `pairSession` 或 `agentSession` 时传给 IM。
- 头像 URL 可以公网或业务前端直接访问。
- 生产环境域名已配置为 IM 允许访问和嵌入的域名。

## 18. 最小对接方案

如果业务方只想最快接入内嵌客服，最小实现如下：

1. 业务后端保存 `im_gateway`、`app_id`、`app_secret`。
2. 业务后端提供自己的接口，例如 `POST /api/im/customer-session`。
3. 该接口读取当前登录用户，调用 IM `/common/api/customerSession`。
4. 业务前端点击“联系客服”时调用自己的后端接口。
5. 业务前端打开后端返回的 `data.url`。

如果需要“用户联系自己的代理/客服”，把第 3 步换成调用 `/common/api/pairSession`。
