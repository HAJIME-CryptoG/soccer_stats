/**
 * escHtml 関数のユニットテスト
 * app.js / view.php のインラインスクリプトに存在する純粋関数。
 *
 * 対象: XSS 対策のHTML特殊文字エスケープ
 */

// app.js / view.php から抽出したエスケープ関数
function escHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

describe('escHtml — HTML特殊文字エスケープ', () => {

    // ---- 基本エスケープ ----

    test('& を &amp; にエスケープする', () => {
        expect(escHtml('a & b')).toBe('a &amp; b');
    });

    test('< を &lt; にエスケープする', () => {
        expect(escHtml('<div>')).toBe('&lt;div&gt;');
    });

    test('> を &gt; にエスケープする', () => {
        expect(escHtml('a > b')).toBe('a &gt; b');
    });

    test('" を &quot; にエスケープする', () => {
        expect(escHtml('say "hello"')).toBe('say &quot;hello&quot;');
    });

    // ---- 複合ケース ----

    test('複数のHTML特殊文字を同時にエスケープする', () => {
        expect(escHtml('<a href="url">&click</a>'))
            .toBe('&lt;a href=&quot;url&quot;&gt;&amp;click&lt;/a&gt;');
    });

    test('<script> タグをエスケープする（XSS対策）', () => {
        const xss = '<script>alert("xss")</script>';
        const escaped = escHtml(xss);
        expect(escaped).not.toContain('<script>');
        expect(escaped).not.toContain('</script>');
        expect(escaped).toContain('&lt;script&gt;');
    });

    test('連続する & をすべてエスケープする', () => {
        expect(escHtml('&&&&')).toBe('&amp;&amp;&amp;&amp;');
    });

    // ---- エッジケース ----

    test('特殊文字なしの文字列はそのまま返す', () => {
        expect(escHtml('普通のテキスト')).toBe('普通のテキスト');
        expect(escHtml('パス成功')).toBe('パス成功');
        expect(escHtml('FC ライバル')).toBe('FC ライバル');
    });

    test('空文字列はそのまま返す', () => {
        expect(escHtml('')).toBe('');
    });

    test('数値は文字列に変換してから処理する', () => {
        expect(escHtml(42)).toBe('42');
        expect(escHtml(0)).toBe('0');
    });

    test('null は "null" として処理する', () => {
        expect(escHtml(null)).toBe('null');
    });

    test('undefined は "undefined" として処理する', () => {
        expect(escHtml(undefined)).toBe('undefined');
    });

    test('日本語の選手名はそのまま返す', () => {
        const names = ['いぶき', 'けいご', 'りょう'];
        names.forEach(name => {
            expect(escHtml(name)).toBe(name);
        });
    });
});
