/**
 * 試合種別（練習試合 / 公式試合）ロジックのテスト
 * register.php の POST処理・JS部分に対応。
 *
 * テスト対象:
 *  - match_type の決定ルール
 *  - tournament_name の決定ルール
 *  - 試合ドロップダウンの表示ラベル生成
 *  - toggleTournament の表示切り替えロジック
 */

// ---- register.php / record.php から抽出したロジック ----

/** match_type を決定する（'official' 以外はすべて 'friendly'） */
function resolveMatchType(input) {
    return input === 'official' ? 'official' : 'friendly';
}

/** tournament_name を決定する（練習試合は強制的に空） */
function resolveTournamentName(matchType, rawName) {
    return matchType === 'official' ? (rawName || '').trim() : '';
}

/** ドロップダウン / 一覧に表示するラベルを生成する */
function buildMatchLabel(match) {
    const typeIcon   = match.match_type === 'official' ? '🏆' : '🤝';
    const tournament = match.tournament_name
        ? ` [${match.tournament_name}]`
        : '';
    return `${typeIcon}${match.match_date}${tournament} vs ${match.opponent}`;
}

/** 大会名フィールドの表示状態を返す（true=表示, false=非表示） */
function shouldShowTournamentField(matchType) {
    return matchType === 'official';
}

// ================================================================
// match_type の決定ロジック
// ================================================================
describe('resolveMatchType — 試合種別の決定', () => {

    test('"official" は公式試合になる', () => {
        expect(resolveMatchType('official')).toBe('official');
    });

    test('"friendly" は練習試合になる', () => {
        expect(resolveMatchType('friendly')).toBe('friendly');
    });

    test('空文字は練習試合になる', () => {
        expect(resolveMatchType('')).toBe('friendly');
    });

    test('null は練習試合になる', () => {
        expect(resolveMatchType(null)).toBe('friendly');
    });

    test('undefined は練習試合になる', () => {
        expect(resolveMatchType(undefined)).toBe('friendly');
    });

    test('大文字 "Official" は練習試合になる（大文字小文字を区別）', () => {
        expect(resolveMatchType('Official')).toBe('friendly');
    });

    test('不正文字列は練習試合になる', () => {
        expect(resolveMatchType('tournament')).toBe('friendly');
        expect(resolveMatchType('公式')).toBe('friendly');
    });
});

// ================================================================
// tournament_name の決定ロジック
// ================================================================
describe('resolveTournamentName — 大会名の決定', () => {

    test('公式試合は大会名をそのまま保持する', () => {
        expect(resolveTournamentName('official', '県リーグ第1節')).toBe('県リーグ第1節');
    });

    test('公式試合は大会名の前後空白をトリムする', () => {
        expect(resolveTournamentName('official', '  県大会  ')).toBe('県大会');
    });

    test('練習試合は大会名を空にする', () => {
        expect(resolveTournamentName('friendly', '大会名')).toBe('');
    });

    test('練習試合は大会名が空でも空を返す', () => {
        expect(resolveTournamentName('friendly', '')).toBe('');
    });

    test('公式試合で大会名が空の場合は空文字', () => {
        expect(resolveTournamentName('official', '')).toBe('');
    });
});

// ================================================================
// ドロップダウン表示ラベル
// ================================================================
describe('buildMatchLabel — 試合ラベル生成', () => {

    test('公式試合のラベルに🏆が含まれる', () => {
        const match = {
            match_type: 'official',
            match_date: '2025-06-01',
            tournament_name: '県リーグ',
            opponent: 'FCライバル',
        };
        expect(buildMatchLabel(match)).toBe('🏆2025-06-01 [県リーグ] vs FCライバル');
    });

    test('練習試合のラベルに🤝が含まれる', () => {
        const match = {
            match_type: 'friendly',
            match_date: '2025-05-01',
            tournament_name: '',
            opponent: 'FC相手',
        };
        expect(buildMatchLabel(match)).toBe('🤝2025-05-01 vs FC相手');
    });

    test('大会名なしの場合は [ ] が表示されない', () => {
        const match = {
            match_type: 'official',
            match_date: '2025-07-01',
            tournament_name: '',
            opponent: 'FC相手',
        };
        const label = buildMatchLabel(match);
        expect(label).not.toContain('[');
        expect(label).not.toContain(']');
    });

    test('ラベルに対戦相手名が含まれる', () => {
        const match = {
            match_type: 'friendly',
            match_date: '2025-08-01',
            tournament_name: '',
            opponent: 'FCテスト',
        };
        expect(buildMatchLabel(match)).toContain('FCテスト');
    });

    test('ラベルに試合日が含まれる', () => {
        const match = {
            match_type: 'friendly',
            match_date: '2025-09-15',
            tournament_name: '',
            opponent: 'FC相手',
        };
        expect(buildMatchLabel(match)).toContain('2025-09-15');
    });
});

// ================================================================
// toggleTournament — 大会名フィールドの表示切り替え
// ================================================================
describe('shouldShowTournamentField — 大会名フィールド表示制御', () => {

    test('公式試合選択時は大会名フィールドを表示する', () => {
        expect(shouldShowTournamentField('official')).toBe(true);
    });

    test('練習試合選択時は大会名フィールドを非表示にする', () => {
        expect(shouldShowTournamentField('friendly')).toBe(false);
    });

    test('未選択時は大会名フィールドを非表示にする', () => {
        expect(shouldShowTournamentField('')).toBe(false);
        expect(shouldShowTournamentField(null)).toBe(false);
        expect(shouldShowTournamentField(undefined)).toBe(false);
    });
});
