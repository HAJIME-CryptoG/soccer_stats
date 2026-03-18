<?php
/**
 * api/save_log.php — 記録保存 / Undo API
 *
 * POST action=save
 *   player   : 選手名 (string)
 *   phase    : 'offense' | 'defense'
 *   batch_id : 一意なバッチID（クライアント生成）
 *   acts[]   : 行為名リスト（複数）
 *   → play_logs へ一括 INSERT
 *
 * POST action=undo
 *   batch_id : 取り消すバッチID
 *   → play_logs の該当バッチを全件 DELETE
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../db/connect.php';

/* ---- バリデーション定数 ---- */
const ALLOWED_PHASES   = ['offense', 'defense'];
const MAX_BATCH_ID_LEN = 64;
const MAX_ACTS         = 20;   // 1回の登録で選べる行為の上限

/* ---- リクエスト種別 ---- */
$action = trim($_POST['action'] ?? '');

try {
    $pdo = get_pdo();

    /* ==================================================
       action=save: play_logs への一括 INSERT
       ================================================== */
    if ($action === 'save') {

        $player   = trim($_POST['player']   ?? '');
        $phase    = trim($_POST['phase']    ?? '');
        $batch_id = trim($_POST['batch_id'] ?? '');
        $acts_raw = (array)($_POST['acts']  ?? []);
        $match_id = filter_input(INPUT_POST, 'match_id', FILTER_VALIDATE_INT) ?: null;

        // 行為リスト: 空文字・重複除去
        $acts = array_values(array_unique(
            array_filter(array_map('trim', $acts_raw))
        ));

        // ---- バリデーション ----
        if ($player === '') {
            respond_error(400, '選手名が空です');
        }
        if (!in_array($phase, ALLOWED_PHASES, true)) {
            respond_error(400, 'phase が不正です');
        }
        if ($batch_id === '' || strlen($batch_id) > MAX_BATCH_ID_LEN) {
            respond_error(400, 'batch_id が不正です');
        }
        if (empty($acts)) {
            respond_error(400, 'プレーが選択されていません');
        }
        if (count($acts) > MAX_ACTS) {
            respond_error(400, 'プレーの選択数が多すぎます');
        }

        // ---- 一括 INSERT ----
        $stmt = $pdo->prepare(
            'INSERT INTO play_logs (batch_id, player_name, action, phase, match_id)
             VALUES (?, ?, ?, ?, ?)'
        );

        $pdo->beginTransaction();
        foreach ($acts as $act) {
            $stmt->execute([$batch_id, $player, $act, $phase, $match_id]);
        }
        $pdo->commit();

        echo json_encode([
            'success'  => true,
            'batch_id' => $batch_id,
            'count'    => count($acts),
        ]);

    /* ==================================================
       action=undo: batch_id を指定して全件 DELETE
       ================================================== */
    } elseif ($action === 'undo') {

        $batch_id = trim($_POST['batch_id'] ?? '');

        if ($batch_id === '' || strlen($batch_id) > MAX_BATCH_ID_LEN) {
            respond_error(400, 'batch_id が不正です');
        }

        $stmt = $pdo->prepare('DELETE FROM play_logs WHERE batch_id = ?');
        $stmt->execute([$batch_id]);
        $deleted = $stmt->rowCount();

        if ($deleted === 0) {
            respond_error(404, '該当するログが見つかりません（すでに削除済み？）');
        }

        echo json_encode([
            'success' => true,
            'deleted' => $deleted,
        ]);

    } else {
        respond_error(400, 'action パラメータが不正です');
    }

} catch (PDOException $e) {
    // $pdo が未定義の場合（接続失敗時）を考慮してチェック
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    respond_error(500, 'DB エラー: ' . $e->getMessage());
} catch (Exception $e) {
    respond_error(500, 'サーバーエラー: ' . $e->getMessage());
}

/* ---- エラーレスポンスヘルパー ---- */
// 戻り値型 never は PHP 8.1+ のため、互換性のため省略
function respond_error(int $code, string $message): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}
