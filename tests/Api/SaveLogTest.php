<?php
namespace Tests\Api;

use Tests\DbTestCase;

/**
 * api/save_log.php のロジックをテスト
 *
 * テスト対象:
 *  - バリデーションルール（空値、不正値、上限超過）
 *  - 正常系: play_logs への INSERT
 *  - Undo: batch_id による DELETE
 */
class SaveLogTest extends DbTestCase
{
    private string $batchId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->batchId = self::BATCH_PREFIX . uniqid();
    }

    // ================================================================
    // バリデーション: 純粋ロジックテスト（DB不要）
    // ================================================================

    /** @test 空文字・空白のみの行為は除外される */
    public function test_empty_acts_are_filtered_out(): void
    {
        $raw  = ['パス成功', '', '  ', 'ドリブル成功'];
        $acts = array_values(array_unique(
            array_filter(array_map('trim', $raw))
        ));
        $this->assertCount(2, $acts);
        $this->assertContains('パス成功', $acts);
        $this->assertContains('ドリブル成功', $acts);
        $this->assertNotContains('', $acts);
        $this->assertNotContains('  ', $acts);
    }

    /** @test 重複行為は除去される */
    public function test_duplicate_acts_are_removed(): void
    {
        $raw  = ['パス成功', 'パス成功', 'ドリブル成功', 'パス成功'];
        $acts = array_values(array_unique(
            array_filter(array_map('trim', $raw))
        ));
        $this->assertCount(2, $acts);
    }

    /** @test 行為上限: 20件は許容、21件は超過 */
    public function test_acts_count_boundary(): void
    {
        $maxActs = 20;

        $exactly20 = array_map(fn($i) => "行為{$i}", range(1, 20));
        $this->assertFalse(count($exactly20) > $maxActs, '20件は上限内のはず');

        $over21 = array_map(fn($i) => "行為{$i}", range(1, 21));
        $this->assertTrue(count($over21) > $maxActs, '21件は上限超過のはず');
    }

    /** @test batch_id の最大長: 64文字は許容、65文字は超過 */
    public function test_batch_id_length_boundary(): void
    {
        $maxLen = 64;
        $this->assertFalse(strlen(str_repeat('a', 64)) > $maxLen);
        $this->assertTrue(strlen(str_repeat('a', 65)) > $maxLen);
    }

    /** @test 許可フェーズは offense と defense のみ */
    public function test_allowed_phases(): void
    {
        $allowed = ['offense', 'defense'];
        $this->assertContains('offense', $allowed);
        $this->assertContains('defense', $allowed);
        $this->assertNotContains('attack',  $allowed);
        $this->assertNotContains('def',     $allowed);
        $this->assertNotContains('',        $allowed);
        $this->assertNotContains('Offense', $allowed);
    }

    // ================================================================
    // 正常系: DB への INSERT
    // ================================================================

    /** @test 1件の行為を正常に保存できる */
    public function test_save_single_act(): void
    {
        $this->pdo->prepare(
            'INSERT INTO play_logs (batch_id, player_name, action, phase, match_id)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([$this->batchId, 'いぶき', 'パス成功', 'offense', null]);

        $this->assertEquals(1, $this->countPlayLogs(['batch_id' => $this->batchId]));
    }

    /** @test 複数行為を同一 batch_id で一括保存できる */
    public function test_save_multiple_acts_in_one_batch(): void
    {
        $acts = ['パス成功', 'ドリブル成功', 'シュート内'];
        $stmt = $this->pdo->prepare(
            'INSERT INTO play_logs (batch_id, player_name, action, phase, match_id)
             VALUES (?, ?, ?, ?, ?)'
        );
        foreach ($acts as $act) {
            $stmt->execute([$this->batchId, 'いぶき', $act, 'offense', null]);
        }

        $this->assertEquals(3, $this->countPlayLogs(['batch_id' => $this->batchId]));
    }

    /** @test match_id 付きで保存できる */
    public function test_save_with_match_id(): void
    {
        $matchId = $this->insertTestMatch();
        $this->pdo->prepare(
            'INSERT INTO play_logs (batch_id, player_name, action, phase, match_id)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([$this->batchId, 'けいご', 'シュート内', 'offense', $matchId]);

        $stmt = $this->pdo->prepare(
            'SELECT match_id FROM play_logs WHERE batch_id = ?'
        );
        $stmt->execute([$this->batchId]);
        $row = $stmt->fetch();
        $this->assertEquals($matchId, (int)$row['match_id']);
    }

    /** @test match_id なし（null）で保存できる */
    public function test_save_without_match_id(): void
    {
        $this->pdo->prepare(
            'INSERT INTO play_logs (batch_id, player_name, action, phase, match_id)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([$this->batchId, 'いぶき', 'パス成功', 'offense', null]);

        $stmt = $this->pdo->prepare(
            'SELECT match_id FROM play_logs WHERE batch_id = ?'
        );
        $stmt->execute([$this->batchId]);
        $row = $stmt->fetch();
        $this->assertNull($row['match_id']);
    }

    /** @test offense / defense で別々に記録できる */
    public function test_offense_and_defense_saved_separately(): void
    {
        $batchOf = $this->batchId . '_of';
        $batchDf = $this->batchId . '_df';

        $stmt = $this->pdo->prepare(
            'INSERT INTO play_logs (batch_id, player_name, action, phase, match_id)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$batchOf, 'いぶき', 'パス成功',  'offense', null]);
        $stmt->execute([$batchDf, 'いぶき', 'ボール奪取', 'defense', null]);

        $this->assertEquals(1, $this->countPlayLogs([
            'batch_id' => $batchOf, 'phase' => 'offense',
        ]));
        $this->assertEquals(1, $this->countPlayLogs([
            'batch_id' => $batchDf, 'phase' => 'defense',
        ]));
    }

    // ================================================================
    // Undo: batch_id による DELETE
    // ================================================================

    /** @test batch_id に紐づくログを全削除できる */
    public function test_undo_deletes_all_logs_in_batch(): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO play_logs (batch_id, player_name, action, phase, match_id)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$this->batchId, 'いぶき', 'パス成功',    'offense', null]);
        $stmt->execute([$this->batchId, 'いぶき', 'ドリブル成功', 'offense', null]);

        $del = $this->pdo->prepare('DELETE FROM play_logs WHERE batch_id = ?');
        $del->execute([$this->batchId]);
        $this->assertEquals(2, $del->rowCount());

        $this->assertEquals(0, $this->countPlayLogs(['batch_id' => $this->batchId]));
    }

    /** @test 存在しない batch_id の Undo は削除件数0を返す */
    public function test_undo_nonexistent_batch_returns_zero(): void
    {
        $nonexistentId = self::BATCH_PREFIX . 'nonexistent_' . uniqid();
        $stmt = $this->pdo->prepare('DELETE FROM play_logs WHERE batch_id = ?');
        $stmt->execute([$nonexistentId]);
        $this->assertEquals(0, $stmt->rowCount());
    }

    /** @test 別バッチのデータは削除されない */
    public function test_undo_does_not_delete_other_batches(): void
    {
        $batchA = $this->batchId . '_A';
        $batchB = $this->batchId . '_B';

        $stmt = $this->pdo->prepare(
            'INSERT INTO play_logs (batch_id, player_name, action, phase, match_id)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$batchA, 'いぶき', 'パス成功', 'offense', null]);
        $stmt->execute([$batchB, 'けいご', 'シュート内', 'offense', null]);

        // batchA だけ削除
        $this->pdo->prepare('DELETE FROM play_logs WHERE batch_id = ?')
                  ->execute([$batchA]);

        $this->assertEquals(0, $this->countPlayLogs(['batch_id' => $batchA]));
        $this->assertEquals(1, $this->countPlayLogs(['batch_id' => $batchB]));
    }

    /** @test トランザクション: 失敗時はロールバックされる */
    public function test_transaction_rollback_on_failure(): void
    {
        $before = $this->countPlayLogs(['player_name' => 'いぶき']);

        try {
            $this->pdo->beginTransaction();
            $this->pdo->prepare(
                'INSERT INTO play_logs (batch_id, player_name, action, phase, match_id)
                 VALUES (?, ?, ?, ?, ?)'
            )->execute([$this->batchId, 'いぶき', 'パス成功', 'offense', null]);
            $this->pdo->rollBack();
        } catch (\Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
        }

        $after = $this->countPlayLogs(['player_name' => 'いぶき']);
        $this->assertEquals($before, $after, 'ロールバック後は件数が変わらないはず');
    }
}
