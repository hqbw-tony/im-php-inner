<?php
namespace app\common\model;

use app\BaseModel;

/**
 * 三方平台客户与代理的一对一会话归属。
 * 只记录平台可管辖的聊天，不替代 IM 原有消息表。
 */
class ThirdChatSession extends BaseModel
{
    protected $pk = 'id';

    /**
     * 保证客户与代理在当前平台下有可管辖会话记录。
     */
    public static function ensurePair($platformId, array $customerMap, array $agentMap, $time = 0)
    {
        $time = $time ?: time();
        $customerId = (int)($customerMap['user_id'] ?? 0);
        $agentId = (int)($agentMap['user_id'] ?? 0);
        if (!$platformId || !$customerId || !$agentId) {
            throw new \InvalidArgumentException('三方会话用户参数无效');
        }

        $map = [
            'platform_id' => (int)$platformId,
            'customer_user_id' => $customerId,
            'agent_user_id' => $agentId,
        ];
        $data = array_merge($map, [
            'external_user_id' => (string)($customerMap['external_user_id'] ?? ''),
            'external_agent_id' => (string)($agentMap['external_user_id'] ?? ''),
            'chat_identify' => chat_identify($customerId, $agentId),
            'status' => 1,
            'update_time' => $time,
        ]);
        $session = self::where($map)->find();
        if ($session) {
            $session->save($data);
            return $session;
        }

        $data['create_time'] = $time;
        return self::create($data);
    }

    /**
     * 消息发送时更新会话最后消息；仅在双方都属于同一平台的客户/代理映射时生效。
     */
    public static function touchByMessage($fromUserId, $toUserId, $messageId, $messageTime = 0)
    {
        $fromUserId = (int)$fromUserId;
        $toUserId = (int)$toUserId;
        if (!$fromUserId || !$toUserId || $fromUserId === $toUserId) {
            return;
        }
        $messageTime = $messageTime ?: time();
        $maps = ThirdUserMap::where('user_id', 'in', [$fromUserId, $toUserId])
            ->where('delete_time', 0)
            ->where('user_type', 'in', ['1', '2'])
            ->select()
            ->toArray();
        if (count($maps) < 2) {
            return;
        }

        foreach ($maps as $first) {
            foreach ($maps as $second) {
                if ((int)$first['user_id'] === (int)$second['user_id']
                    || (int)$first['platform_id'] !== (int)$second['platform_id']
                    || (string)$first['user_type'] === (string)$second['user_type']) {
                    continue;
                }
                $customerMap = (string)$first['user_type'] === '1' ? $first : $second;
                $agentMap = (string)$first['user_type'] === '2' ? $first : $second;
                $session = self::ensurePair((int)$first['platform_id'], $customerMap, $agentMap, $messageTime);
                if ((int)$messageTime >= (int)$session['last_msg_time']) {
                    $session->save([
                        'last_msg_id' => (int)$messageId,
                        'last_msg_time' => (int)$messageTime,
                        'update_time' => (int)$messageTime,
                    ]);
                }
                return;
            }
        }
    }
}
