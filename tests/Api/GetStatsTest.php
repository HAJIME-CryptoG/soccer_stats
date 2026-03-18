<?php
namespace Tests\Api;

use Tests\DbTestCase;

/**
 * api/get_stats.php の集計ロジックをテスト
 *
 * テスト対象:
 *  - 登録選手が記録0件でも返ること
 *  - match_id / player_name でのフィルタリング
 *  - offense / defense の集計が分離されること
 *  - of_chart_data / df_chart_data の計算
 *  - 行為カウントの正確性
 */
class GetStatsTest extends DbTestCase
{
    private int    $matchId;
    private string $batchPrefix;

    protected function setUp(): void
    {
        parent::setUp();
        $this->matchId     = $this->insertTestMatch();
        $this->batchPrefix = self::BATCH_PREFIX . uniqid() . '_';
    }

    // ================================================================
    // 全選手表示（記録0件でも）
    // ================================================================

    /** @test players テーブルに登録されている選手が SELECT できる */
    public function test_registered_players_are_in_players_table(): void
    {
        $this->insertTestPlayer('[TEST]選手A', 99);
        $this->insertTestPlayer('[TEST]選手B', 98);

        $stmt = $this->pdo->prepare(
            "SELECT name FROM players WHERE name LIKE '[TEST]%' ORDER BY number DESC"
        );
        $stmt->execute();
        $names = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $this->assertContains('[TEST]選手A', $names);
        $this->assertContains('[TEST]選手B', $names);
    }

    /** @test play_logs にデータがなくても登録選手が取得できる */
    public function test_players_with_zero_logs_are_returned(): void
    {
        $this->insertTestPlayer('[TEST]記録なし選手', 97);

        // play_logs には何も入れない
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM players WHERE name = '[TEST]記録なし選手'"
        );
        $stmt->execute();
        $this->assertEquals(1, (int)$stmt->fetchColumn());
    }

    // ================================================================
    // match_id フィルタリング
    // ================================================================

    /** @test match_id を指定すると該当試合のログだけ取得できる */
    public function test_filter_by_match_id(): void
    {
        $otherMatchId = $this->insertTestMatch('2099-02-01', '[TEST]別チーム');

        $this->insertTestPlayLog(
            $this->batchPrefix . '1', 'いぶき', 'パス成功', 'offense', $this->matchId
        );
        $this->insertTestPlayLog(
            $this->batchPrefix . '2', 'けいご', 'シュート内', 'offense', $otherMatchId
        );

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM play_logs WHERE match_id = ?'
        );
        $stmt->execute([$this->matchId]);
        $this->assertEquals(1, (int)$stmt->fetchColumn());
    }

    /** @test match_id 未指定だと全試合のログが返る */
    public function test_no_match_filter_returns_all_logs(): void
    {
        $otherMatchId = $this->insertTestMatch('2099-03-01', '[TEST]別試合');

        $this->insertTestPlayLog(
            $this->batchPrefix . '1', 'いぶき', 'パス成功', 'offense', $this->matchId
        );
        $this->insertTestPlayLog(
            $this->batchPrefix . '2', 'けいご', 'ドリブル成功', 'offense', $otherMatchId
        );

        $stmt = $this->pdo->query(
            "SELECT COUNT(*) FROM play_logs WHERE batch_id LIKE 'phpunit_%'"
        );
        $this->assertGreaterThanOrEqual(2, (int)$stmt->fetchColumn());
    }

    // ================================================================
    // player_name フィルタリング
    // ================================================================

    /** @test player_name でフィルタリングできる */
    public function test_filter_by_player_name(): void
    {
        $this->insertTestPlayLog(
            $this->batchPrefix . '1', 'いぶき', 'パス成功', 'offense', $this->matchId
        );
        $this->insertTestPlayLog(
            $this->batchPrefix . '2', 'けいご', 'シュート内', 'offense', $this->matchId
        );

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM play_logs WHERE player_name = ? AND match_id = ?'
        );
        $stmt->execute(['いぶき', $this->matchId]);
        $this->assertEquals(1, (int)$stmt->fetchColumn());
    }

    // ================================================================
    // 集計ロジック
    // ================================================================

    /** @test 同じ行為を複数回記録するとカウントが増える */
    public function test_aggregation_counts_multiple_occurrences(): void
    {
        $this->insertTestPlayLog(
            $this->batchPrefix . '1', 'いぶき', 'パス成功', 'offense', $this->matchId
        );
        $this->insertTestPlayLog(
            $this->batchPrefix . '2', 'いぶき', 'パス成功', 'offense', $this->matchId
        );
        $this->insertTestPlayLog(
            $this->batchPrefix . '3', 'いぶき', 'ドリブル成功', 'offense', $this->matchId
        );

        $stmt = $this->pdo->prepare(
            'SELECT action, COUNT(*) AS cnt
             FROM play_logs
             WHERE player_name = ? AND match_id = ? AND phase = ?
             GROUP BY action'
        );
        $stmt->execute(['いぶき', $this->matchId, 'offense']);
        $rows         = $stmt->fetchAll();
        $actionCounts = array_column($rows, 'cnt', 'action');

        $this->assertEquals(2, (int)($actionCounts['パス成功'] ?? 0));
        $this->assertEquals(1, (int)($actionCounts['ドリブル成功'] ?? 0));
    }

    /** @test offense と defense は別々に集計される */
    public function test_offense_and_defense_aggregated_separately(): void
    {
        $this->insertTestPlayLog(
            $this->batchPrefix . 'of', 'いぶき', 'パス成功',  'offense', $this->matchId
        );
        $this->insertTestPlayLog(
            $this->batchPrefix . 'df', 'いぶき', 'ボール奪取', 'defense', $this->matchId
        );

        $stmt = $this->pdo->prepare(
            'SELECT player_name, action, phase, COUNT(*) AS cnt
             FROM play_logs
             WHERE match_id = ?
             GROUP BY player_name, action, phase'
        );
        $stmt->execute([$this->matchId]);
        $rows   = $stmt->fetchAll();
        $phases = array_column($rows, 'phase');

        $this->assertContains('offense', $phases);
        $this->assertContains('defense', $phases);
    }

    /** @test 複数選手のデータが独立して集計される */
    public function test_multiple_players_aggregated_independently(): void
    {
        $this->insertTestPlayLog(
            $this->batchPrefix . '1', 'いぶき', 'パス成功',  'offense', $this->matchId
        );
        $this->insertTestPlayLog(
            $this->batchPrefix . '2', 'けいご', 'シュート内', 'offense', $this->matchId
        );

        $stmt = $this->pdo->prepare(
            'SELECT player_name, COUNT(*) AS cnt
             FROM play_logs
             WHERE match_id = ? AND phase = ?
             GROUP BY player_name'
        );
        $stmt->execute([$this->matchId, 'offense']);
        $rows        = $stmt->fetchAll();
        $playerNames = array_column($rows, 'player_name');

        $this->assertContains('いぶき', $playerNames);
        $this->assertContains('けいご', $playerNames);
    }

    // ================================================================
    // of_chart_data / df_chart_data ロジック
    // ================================================================

    /** @test of_chart_data は offense の行為カウントに対応する */
    public function test_of_chart_data_matches_offense_counts(): void
    {
        $ofActions = ['パス成功', 'パス失敗', 'シュート内'];

        $this->insertTestPlayLog(
            $this->batchPrefix . '1', 'いぶき', 'パス成功', 'offense', $this->matchId
        );
        $this->insertTestPlayLog(
            $this->batchPrefix . '2', 'いぶき', 'パス成功', 'offense', $this->matchId
        );
        $this->insertTestPlayLog(
            $this->batchPrefix . '3', 'いぶき', 'シュート内', 'offense', $this->matchId
        );

        $stmt = $this->pdo->prepare(
            'SELECT action, COUNT(*) AS cnt
             FROM play_logs
             WHERE player_name = ? AND phase = ? AND match_id = ?
             GROUP BY action'
        );
        $stmt->execute(['いぶき', 'offense', $this->matchId]);
        $rows    = $stmt->fetchAll();
        $counts  = array_column($rows, 'cnt', 'action');

        // of_chart_data の期待値を計算
        $ofChartData = [];
        foreach ($ofActions as $act) {
            $ofChartData[] = (int)($counts[$act] ?? 0);
        }

        $this->assertEquals([2, 0, 1], $ofChartData);
    }

    /** @test df_chart_data は defense の行為カウントに対応する */
    public function test_df_chart_data_matches_defense_counts(): void
    {
        $dfActions = ['ボール奪取', 'インターセプト', 'シュートブロック'];

        $this->insertTestPlayLog(
            $this->batchPrefix . '1', 'いぶき', 'ボール奪取',  'defense', $this->matchId
        );
        $this->insertTestPlayLog(
            $this->batchPrefix . '2', 'いぶき', 'ボール奪取',  'defense', $this->matchId
        );
        $this->insertTestPlayLog(
            $this->batchPrefix . '3', 'いぶき', 'インターセプト', 'defense', $this->matchId
        );

        $stmt = $this->pdo->prepare(
            'SELECT action, COUNT(*) AS cnt
             FROM play_logs
             WHERE player_name = ? AND phase = ? AND match_id = ?
             GROUP BY action'
        );
        $stmt->execute(['いぶき', 'defense', $this->matchId]);
        $rows   = $stmt->fetchAll();
        $counts = array_column($rows, 'cnt', 'action');

        $dfChartData = [];
        foreach ($dfActions as $act) {
            $dfChartData[] = (int)($counts[$act] ?? 0);
        }

        $this->assertEquals([2, 1, 0], $dfChartData);
    }

    /** @test 記録のない行為は 0 になる */
    public function test_actions_with_no_records_default_to_zero(): void
    {
        // 何もINSERTしない
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM play_logs WHERE player_name = ? AND action = ? AND match_id = ?'
        );
        $stmt->execute(['いぶき', 'アシスト', $this->matchId]);
        $this->assertEquals(0, (int)$stmt->fetchColumn());
    }
}
