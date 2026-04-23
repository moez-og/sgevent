<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

class NotificationService
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function notifyUser(
        int $receiverId,
        ?int $senderId,
        string $type,
        string $title,
        string $body,
        string $entityType,
        int $entityId,
        array $metadata = []
    ): void {
        try {
            $this->connection->insert('notifications', [
                'receiver_id' => $receiverId,
                'sender_id' => $senderId,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'read_at' => null,
                'metadata_json' => $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null,
            ]);
        } catch (Exception) {
            // Fail-safe: notifications must not break business flow.
        }
    }

    /**
     * @param array<int, int> $receiverIds
     */
    public function notifyMany(
        array $receiverIds,
        ?int $senderId,
        string $type,
        string $title,
        string $body,
        string $entityType,
        int $entityId,
        array $metadata = []
    ): void {
        $unique = array_values(array_unique(array_map('intval', $receiverIds)));
        foreach ($unique as $receiverId) {
            $this->notifyUser($receiverId, $senderId, $type, $title, $body, $entityType, $entityId, $metadata);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchLatestForUser(int $userId, int $limit = 10): array
    {
        try {
                 $sql = 'SELECT n.id, n.type, n.title, n.body, n.entity_type, n.entity_id, n.created_at, n.read_at, n.metadata_json,
                           su.prenom AS sender_prenom, su.nom AS sender_nom
                    FROM notifications n
                    LEFT JOIN user su ON su.id = n.sender_id
                    WHERE n.receiver_id = ?
                    ORDER BY n.created_at DESC
                    LIMIT '.$limit;

            return $this->connection->fetchAllAssociative($sql, [$userId]);
        } catch (Exception) {
            return [];
        }
    }

    public function countUnreadForUser(int $userId): int
    {
        try {
            return (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM notifications WHERE receiver_id = ? AND read_at IS NULL',
                [$userId]
            );
        } catch (Exception) {
            return 0;
        }
    }

    public function markAllAsRead(int $userId): void
    {
        try {
            $this->connection->executeStatement(
                'UPDATE notifications SET read_at = ? WHERE receiver_id = ? AND read_at IS NULL',
                [(new \DateTimeImmutable())->format('Y-m-d H:i:s'), $userId]
            );
        } catch (Exception) {
            // no-op
        }
    }
}
