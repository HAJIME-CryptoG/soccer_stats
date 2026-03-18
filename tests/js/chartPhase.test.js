/**
 * スパイダーチャートのフェーズ切り替えロジックのテスト
 * chart_config.js / view.php の renderChart / switchChartPhase に対応。
 *
 * テスト対象:
 *  - フェーズ選択によるデータキーとラベルの切り替え
 *  - of_chart_data / df_chart_data の整合性
 *  - タブのCSSクラス切り替えロジック
 */

// ---- chart_config.js から抽出したフェーズ選択ロジック ----
function selectChartData(data, phase) {
    let labels, dataKey;
    if (phase === 'offense') {
        labels  = data.of_actions || [];
        dataKey = 'of_chart_data';
    } else if (phase === 'defense') {
        labels  = data.df_actions || [];
        dataKey = 'df_chart_data';
    } else {
        labels  = (data.actions || []).map(a => a.name);
        dataKey = 'chart_data';
    }
    return { labels, dataKey };
}

// ---- view.php の switchChartPhase タブ切り替えロジック ----
function getTabClasses(phase) {
    return {
        ofClass: 'matrix-tab' + (phase === 'offense' ? ' active' : ''),
        dfClass: 'matrix-tab' + (phase === 'defense' ? ' df-active' : ''),
    };
}

// ---- テスト用モックデータ ----
const mockData = {
    of_actions: ['パス成功', 'パス失敗', 'キーパス', 'アシスト', 'シュート内'],
    df_actions: ['ボール奪取', 'インターセプト', 'シュートブロック'],
    actions: [
        { name: 'パス成功' }, { name: 'ボール奪取' },
    ],
    players: [
        {
            name: 'いぶき',
            number: 1,
            chart_data:    [3, 2],
            of_chart_data: [3, 1, 0, 0, 2],
            df_chart_data: [0, 2, 1],
        },
        {
            name: 'けいご',
            number: 7,
            chart_data:    [1, 4],
            of_chart_data: [1, 0, 0, 0, 0],
            df_chart_data: [4, 0, 0],
        },
    ],
};

describe('chartPhase — フェーズ選択ロジック', () => {

    // ---- フェーズ別データキー・ラベル ----

    test('offense フェーズでは of_actions をラベルに使う', () => {
        const { labels } = selectChartData(mockData, 'offense');
        expect(labels).toEqual(mockData.of_actions);
    });

    test('offense フェーズでは of_chart_data をデータキーに使う', () => {
        const { dataKey } = selectChartData(mockData, 'offense');
        expect(dataKey).toBe('of_chart_data');
    });

    test('defense フェーズでは df_actions をラベルに使う', () => {
        const { labels } = selectChartData(mockData, 'defense');
        expect(labels).toEqual(mockData.df_actions);
    });

    test('defense フェーズでは df_chart_data をデータキーに使う', () => {
        const { dataKey } = selectChartData(mockData, 'defense');
        expect(dataKey).toBe('df_chart_data');
    });

    test('フェーズ未指定 (undefined) では全行為の chart_data を使う', () => {
        const { dataKey } = selectChartData(mockData, undefined);
        expect(dataKey).toBe('chart_data');
    });

    test('フェーズ未指定ではすべての actions をラベルに使う', () => {
        const { labels } = selectChartData(mockData, undefined);
        expect(labels).toEqual(['パス成功', 'ボール奪取']);
    });

    // ---- データ整合性 ----

    test('of_chart_data の要素数は of_actions の件数と一致する', () => {
        const { labels, dataKey } = selectChartData(mockData, 'offense');
        mockData.players.forEach(player => {
            expect(player[dataKey].length).toBe(labels.length);
        });
    });

    test('df_chart_data の要素数は df_actions の件数と一致する', () => {
        const { labels, dataKey } = selectChartData(mockData, 'defense');
        mockData.players.forEach(player => {
            expect(player[dataKey].length).toBe(labels.length);
        });
    });

    test('全選手が of_chart_data を持つ', () => {
        mockData.players.forEach(player => {
            expect(player).toHaveProperty('of_chart_data');
            expect(Array.isArray(player.of_chart_data)).toBe(true);
        });
    });

    test('全選手が df_chart_data を持つ', () => {
        mockData.players.forEach(player => {
            expect(player).toHaveProperty('df_chart_data');
            expect(Array.isArray(player.df_chart_data)).toBe(true);
        });
    });

    // ---- タブ CSS クラス ----

    describe('switchChartPhase — タブCSSクラス切り替え', () => {

        test('offense 選択時: ofタブに "active" クラスが付く', () => {
            const { ofClass } = getTabClasses('offense');
            expect(ofClass).toContain('active');
        });

        test('offense 選択時: dfタブに "df-active" は付かない', () => {
            const { dfClass } = getTabClasses('offense');
            expect(dfClass).not.toContain('df-active');
        });

        test('defense 選択時: dfタブに "df-active" クラスが付く', () => {
            const { dfClass } = getTabClasses('defense');
            expect(dfClass).toContain('df-active');
        });

        test('defense 選択時: ofタブに "active" は付かない', () => {
            const { ofClass } = getTabClasses('defense');
            expect(ofClass).not.toContain('active');
        });

        test('どちらのタブも常に基本クラス "matrix-tab" を持つ', () => {
            ['offense', 'defense'].forEach(phase => {
                const { ofClass, dfClass } = getTabClasses(phase);
                expect(ofClass).toContain('matrix-tab');
                expect(dfClass).toContain('matrix-tab');
            });
        });
    });

    // ---- データなし（空配列）の場合 ----

    test('players が空でもエラーにならない', () => {
        const emptyData = { ...mockData, players: [] };
        expect(() => selectChartData(emptyData, 'offense')).not.toThrow();
    });

    test('of_actions が空のときラベルも空配列', () => {
        const noOfData = { ...mockData, of_actions: [] };
        const { labels } = selectChartData(noOfData, 'offense');
        expect(labels).toEqual([]);
    });
});
