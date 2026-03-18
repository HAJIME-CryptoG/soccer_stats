<?php
namespace Tests;

use PDO;
use PHPUnit\Framework\TestCase;

/**
 * DB接続を持つテストの基底クラス。
 * 各テスト前後にテスト用データを自動クリーンアップする。
 *
 * ルール:
 *  - バッチID は 'phpunit_' で始める
 *  - 対戦相手名は '[TEST]' で始める
 *  - 選手名は '[TEST]' で始める
 */
abstract class DbTestCase extends TestCase
{
    protected PDO $pdo;

    /** テストデータ識別プレフィックス */
    protected const BATCH_PREFIX  = 'phpunit_';
    protected const ENTITY_PREFIX = '[TEST]';

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = get_pdo();
        $this->cleanTestData();
    }

    protected function tearDown(): void
    {
        $this->cleanTestData();
        parent::tearDown();
    }

    /** テスト用データを全削除 */
    private function cleanTestData(): void
    {
        $this->pdo->exec(
            "DELETE FROM play_logs WHERE batch_id LIKE 'phpunit_%'"
        );
        $this->pdo->exec(
            "DELETE FROM matches WHERE opponent LIKE '[TEST]%'"
        );
        $this->pdo->exec(
            "DELETE FROM players WHERE name LIKE '[TEST]%'"
        );
    }

    /** テスト用選手を挿入して ID を返す */
    protected function insertTestPlayer(
        string $name   = '[TEST]選手A',
        int    $number = 99,
        string $team   = '[TEST]チーム'
    ): int {
        $this->pdo->prepare(
            'INSERT INTO players (name, number, team) VALUES (?, ?, ?)'
        )->execute([$name, $number, $team]);
        return (int)$this->pdo->lastInsertId();
    }

    /** テスト用試合を挿入して ID を返す */
    protected function insertTestMatch(
        string $date       = '2099-01-01',
        string $opponent   = '[TEST]相手チーム',
        string $type       = 'friendly',
        string $tournament = '',
        string $location   = '[TEST]グラウンド'
    ): int {
        $this->pdo->prepare(
            'INSERT INTO matches
                 (match_date, opponent, location, match_type, tournament_name)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([$date, $opponent, $location, $type, $tournament]);
        return (int)$this->pdo->lastInsertId();
    }

    /** テスト用 play_log を挿入する */
    protected function insertTestPlayLog(
        string  $batchId,
        string  $playerName,
        string  $action,
        string  $phase   = 'offense',
        ?int    $matchId = null
    ): void {
        $this->pdo->prepare(
            'INSERT INTO play_logs
                 (batch_id, player_name, action, phase, match_id)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([$batchId, $playerName, $action, $phase, $matchId]);
    }

    /** play_logs の件数を返す汎用ヘルパー */
    protected function countPlayLogs(array $where): int
    {
        $conditions = [];
        $params     = [];
        foreach ($where as $col => $val) {
            $conditions[] = "{$col} = ?";
            $params[]     = $val;
        }
        $sql  = 'SELECT COUNT(*) FROM play_logs WHERE ' . implode(' AND ', $conditions);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }
}
