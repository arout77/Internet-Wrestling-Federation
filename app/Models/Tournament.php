<?php
namespace App\Models;

use PDO;
use Rhapsody\Core\BaseModel;

class Tournament extends BaseModel
{
    /**
     * Checks user gold and deducts entry fee.
     * @param string $userId
     * @param int $cost
     * @return bool
     */
    public function deductEntryFee(string $userId, int $cost = 1): bool
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("SELECT gold FROM users WHERE user_id = ? FOR UPDATE");
            $stmt->execute([$userId]);
            $gold = $stmt->fetchColumn();
            if ($gold < $cost) {
                $this->db->rollBack();
                return false;
            }
            $stmt = $this->db->prepare("UPDATE users SET gold = gold - ? WHERE user_id = ?");
            $stmt->execute([$cost, $userId]);
            $this->db->commit();
            return true;
        } catch (\PDOException $e) {
            $this->db->rollBack();
            return false;
        }
    }

    /**
     * Awards gold to a user.
     * @param string $userId
     * @param int $reward
     * @return bool
     */
    public function awardGold(string $userId, int $reward): bool
    {
        $stmt = $this->db->prepare("UPDATE users SET gold = gold + ? WHERE user_id = ?");
        return $stmt->execute([$reward, $userId]);
    }

    /**
     * Pay to advance (deduct from user gold).
     * @param string $userId
     * @param int $cost
     * @return bool
     */
    public function payToAdvance(string $userId, int $cost = 3): bool
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("SELECT gold FROM users WHERE user_id = ? FOR UPDATE");
            $stmt->execute([$userId]);
            $gold = $stmt->fetchColumn();
            if ($gold < $cost) {
                $this->db->rollBack();
                return false;
            }
            $stmt = $this->db->prepare("UPDATE users SET gold = gold - ? WHERE user_id = ?");
            $stmt->execute([$cost, $userId]);
            $this->db->commit();
            return true;
        } catch (\PDOException $e) {
            $this->db->rollBack();
            return false;
        }
    }

    /**
     * Creates a new tournament record.
     * @param string $userId
     * @param array $wrestlerIds
     * @param int $initialSize
     * @return int|false tournament ID or false on failure
     */
    public function createTournament(string $userId, array $wrestlerIds, int $initialSize): int | false
    {
        $stmt = $this->db->prepare("INSERT INTO tournaments (user_id, wrestler_ids, initial_size) VALUES (?, ?, ?)");
        if ($stmt->execute([$userId, json_encode($wrestlerIds), $initialSize])) {
            return (int) $this->db->lastInsertId();
        }
        return false;
    }

    /**
     * Retrieves a tournament by ID and user ID.
     * @param int $tournamentId
     * @param string $userId
     * @return object|null
     */
    public function getTournament(int $tournamentId, string $userId): ?object
    {
        $stmt = $this->db->prepare("SELECT * FROM tournaments WHERE id = ? AND user_id = ? FOR UPDATE");
        $stmt->execute([$tournamentId, $userId]);
        return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
    }

    /**
     * Updates tournament after a round.
     * @param int $tournamentId
     * @param array $userPicks
     * @param int $currentRound
     * @param array|null $nextWrestlerIds
     * @return bool
     */
    public function updateTournamentRound(int $tournamentId, array $userPicks, int $currentRound, ?array $nextWrestlerIds = null): bool
    {
        $data = ['picks' => json_encode($userPicks)];
        $sql  = "UPDATE tournaments SET user_picks = :picks";
        if ($nextWrestlerIds !== null) {
            $sql                  .= ", wrestler_ids = :wrestler_ids, current_round = current_round + 1";
            $data['wrestler_ids']  = json_encode($nextWrestlerIds);
        }
        $sql        .= " WHERE id = :id";
        $data['id']  = $tournamentId;
        $stmt        = $this->db->prepare($sql);
        return $stmt->execute($data);
    }
}
