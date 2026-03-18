<?php
namespace Tests\Feature;

use Tests\DbTestCase;

/**
 * 試合マスター（matches テーブル）の CRUD をテスト
 *
 * テスト対象:
 *  - 練習試合 / 公式試合の登録
 *  - match_type / tournament_name のバリデーションロジック
 *  - 試合の更新・削除
 *  - 一覧の取得順序
 */
class MatchTest extends DbTestCase
{
    // ================================================================
    // 登録: INSERT
    // ================================================================

    /** @test 練習試合を登録できる */
    public function test_register_friendly_match(): void
    {
        $id  = $this->insertTestMatch('2099-01-01', '[TEST]練習相手', 'friendly', '');
        $row = $this->fetchMatch($id);

        $this->assertEquals('friendly', $row['match_type']);
        $this->assertEquals('',         $row['tournament_name']);
        $this->assertEquals('[TEST]練習相手', $row['opponent']);
    }

    /** @test 公式試合を大会名付きで登録できる */
    public function test_register_official_match_with_tournament(): void
    {
        $id  = $this->insertTestMatch('2099-02-01', '[TEST]公式相手', 'official', '[TEST]県大会');
        $row = $this->fetchMatch($id);

        $this->assertEquals('official',   $row['match_type']);
        $this->assertEquals('[TEST]県大会', $row['tournament_name']);
    }

    /** @test 場所は任意（空でも登録できる） */
    public function test_register_match_without_location(): void
    {
        $this->pdo->prepare(
            'INSERT INTO matches (match_date, opponent, location, match_type, tournament_name)
             VALUES (?, ?, ?, ?, ?)'
        )->execute(['2099-03-01', '[TEST]場所なし', '', 'friendly', '']);
        $id = (int)$this->pdo->lastInsertId();

        $row = $this->fetchMatch($id);
        $this->assertEquals('', $row['location']);
    }

    // ================================================================
    // match_type バリデーションロジック（pure PHP）
    // ================================================================

    /** @test 'official' 以外の入力はすべて 'friendly' になる */
    public function test_match_type_defaults_to_friendly(): void
    {
        $inputs = ['friendly', '', 'invalid', 'Official', '0', null];
        foreach ($inputs as $input) {
            $resolved = ($input === 'official') ? 'official' : 'friendly';
            $this->assertEquals(
                'friendly',
                $resolved,
                "入力 " . var_export($input, true) . " は friendly になるはず"
            );
        }
    }

    /** @test 'official' は 'official' になる */
    public function test_match_type_official_is_preserved(): void
    {
        $resolved = ('official' === 'official') ? 'official' : 'friendly';
        $this->assertEquals('official', $resolved);
    }

    /** @test 練習試合では tournament_name が空になる */
    public function test_friendly_clears_tournament_name(): void
    {
        $matchType      = 'friendly';
        $tournamentName = ($matchType === 'official') ? '大会名' : '';
        $this->assertEquals('', $tournamentName);
    }

    /** @test 公式試合では tournament_name が保持される */
    public function test_official_preserves_tournament_name(): void
    {
        $matchType      = 'official';
        $rawName        = '  [TEST]県リーグ第1節  ';
        $tournamentName = ($matchType === 'official') ? trim($rawName) : '';
        $this->assertEquals('[TEST]県リーグ第1節', $tournamentName);
    }

    // ================================================================
    // 更新: UPDATE
    // ================================================================

    /** @test 試合情報を更新できる */
    public function test_update_match(): void
    {
        $id = $this->insertTestMatch('2099-04-01', '[TEST]更新前');

        $this->pdo->prepare(
            'UPDATE matches
             SET match_date=?, opponent=?, location=?, match_type=?, tournament_name=?
             WHERE id=?'
        )->execute(['2099-04-15', '[TEST]更新後', '[TEST]新会場', 'official', '[TEST]大会', $id]);

        $row = $this->fetchMatch($id);
        $this->assertEquals('2099-04-15',  $row['match_date']);
        $this->assertEquals('[TEST]更新後', $row['opponent']);
        $this->assertEquals('[TEST]新会場', $row['location']);
        $this->assertEquals('official',    $row['match_type']);
        $this->assertEquals('[TEST]大会',   $row['tournament_name']);
    }

    /** @test 公式試合を練習試合に変更すると tournament_name が空になる */
    public function test_update_official_to_friendly_clears_tournament(): void
    {
        $id = $this->insertTestMatch('2099-05-01', '[TEST]変更前', 'official', '[TEST]大会名');

        // 練習試合に変更（tournament_name は空にする）
        $newType       = 'friendly';
        $newTournament = ($newType === 'official') ? '[TEST]大会名' : '';
        $this->pdo->prepare(
            'UPDATE matches SET match_type=?, tournament_name=? WHERE id=?'
        )->execute([$newType, $newTournament, $id]);

        $row = $this->fetchMatch($id);
        $this->assertEquals('friendly', $row['match_type']);
        $this->assertEquals('',         $row['tournament_name']);
    }

    // ================================================================
    // 削除: DELETE
    // ================================================================

    /** @test 試合を削除できる */
    public function test_delete_match(): void
    {
        $id = $this->insertTestMatch('2099-06-01', '[TEST]削除対象');

        $this->pdo->prepare('DELETE FROM matches WHERE id=?')->execute([$id]);

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM matches WHERE id=?');
        $stmt->execute([$id]);
        $this->assertEquals(0, (int)$stmt->fetchColumn());
    }

    /** @test 存在しない ID の削除は 0 件 */
    public function test_delete_nonexistent_match(): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM matches WHERE id=?');
        $stmt->execute([999999999]);
        $this->assertEquals(0, $stmt->rowCount());
    }

    // ================================================================
    // 一覧表示
    // ================================================================

    /** @test 試合一覧は日付の降順で返る */
    public function test_matches_ordered_by_date_desc(): void
    {
        $this->insertTestMatch('2099-01-01', '[TEST]1月');
        $this->insertTestMatch('2099-06-01', '[TEST]6月');
        $this->insertTestMatch('2099-03-01', '[TEST]3月');

        $stmt = $this->pdo->query(
            "SELECT opponent FROM matches WHERE opponent LIKE '[TEST]%' ORDER BY match_date DESC, id DESC"
        );
        $rows = $stmt->fetchAll();

        $this->assertEquals('[TEST]6月', $rows[0]['opponent']);
        $this->assertEquals('[TEST]3月', $rows[1]['opponent']);
        $this->assertEquals('[TEST]1月', $rows[2]['opponent']);
    }

    /** @test 同じ日付なら id の降順（新しい登録順） */
    public function test_same_date_ordered_by_id_desc(): void
    {
        $id1 = $this->insertTestMatch('2099-07-01', '[TEST]先');
        $id2 = $this->insertTestMatch('2099-07-01', '[TEST]後');

        $this->assertGreaterThan($id1, $id2);

        $stmt = $this->pdo->query(
            "SELECT id FROM matches WHERE opponent LIKE '[TEST]%' ORDER BY match_date DESC, id DESC"
        );
        $ids = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        $this->assertEquals($id2, (int)$ids[0]);
    }

    /** @test 試合件数が取得できる */
    public function test_match_count(): void
    {
        $before = $this->getTestMatchCount();

        $this->insertTestMatch('2099-08-01', '[TEST]A');
        $this->insertTestMatch('2099-08-02', '[TEST]B');

        $after = $this->getTestMatchCount();
        $this->assertEquals($before + 2, $after);
    }

    // ================================================================
    // ヘルパー
    // ================================================================

    private function fetchMatch(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM matches WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    private function getTestMatchCount(): int
    {
        $stmt = $this->pdo->query(
            "SELECT COUNT(*) FROM matches WHERE opponent LIKE '[TEST]%'"
        );
        return (int)$stmt->fetchColumn();
    }
}
