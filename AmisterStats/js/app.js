/**
 * AmisterStats — メインロジック
 * record.php で使用。ボタン押下・API通信・ログ表示を管理する。
 */
(function () {
    'use strict';

    // ---- 状態管理 ----
    let selectedPlayerId = null;
    let selectedMatchId  = null;
    let lastLogEntry     = null;    // トースト表示用

    // ---- DOM参照 ----
    const matchSelect   = document.getElementById('match-select');
    const playerGrid    = document.getElementById('player-grid');
    const actionSection = document.getElementById('action-section');
    const actionGrid    = document.getElementById('action-grid');
    const logFeed       = document.getElementById('log-feed');
    const undoBtn       = document.getElementById('undo-btn');
    const selectedLabel = document.getElementById('selected-player-label');

    // ---- 試合選択 ----
    if (matchSelect) {
        matchSelect.addEventListener('change', function () {
            selectedMatchId  = this.value || null;
            selectedPlayerId = null;
            updateSelectedLabel();
            toggleActionSection();
        });
        // 初期値がすでに選択されている場合
        if (matchSelect.value) {
            selectedMatchId = matchSelect.value;
        }
    }

    // ---- 選手ボタン ----
    if (playerGrid) {
        playerGrid.addEventListener('click', function (e) {
            const btn = e.target.closest('.player-btn');
            if (!btn) return;

            document.querySelectorAll('.player-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            selectedPlayerId = btn.dataset.playerId;
            updateSelectedLabel();
            toggleActionSection();
        });
    }

    // ---- 行為ボタン ----
    if (actionGrid) {
        actionGrid.addEventListener('click', function (e) {
            const btn = e.target.closest('.action-btn');
            if (!btn) return;

            if (!selectedMatchId || !selectedPlayerId) {
                showToast('試合と選手を先に選択してください');
                return;
            }

            const actionId   = btn.dataset.actionId;
            const actionName = btn.dataset.actionName;
            const pointValue = parseInt(btn.dataset.pointValue, 10);

            // ボタンを一時的に無効化（連打防止）
            btn.disabled = true;

            const body = new URLSearchParams({
                match_id:  selectedMatchId,
                player_id: selectedPlayerId,
                action_id: actionId,
            });

            fetch('../api/save_log.php', { method: 'POST', body })
                .then(r => r.json())
                .then(data => {
                    if (data.error) { showToast('エラー: ' + data.error); return; }

                    const sign = pointValue >= 0 ? '+' : '';
                    showToast(`${actionName}  ${sign}${pointValue}pt`);
                    addLogItem({
                        logId:      data.log_id,
                        actionName: data.action_name,
                        pointValue: data.point_value,
                        category:   pointValue >= 0 ? 'positive' : 'negative',
                        playerName: getSelectedPlayerName(),
                    });
                    lastLogEntry = {
                        matchId:  selectedMatchId,
                        playerId: selectedPlayerId,
                    };
                })
                .catch(() => showToast('通信エラーが発生しました'))
                .finally(() => { btn.disabled = false; });
        });
    }

    // ---- 取り消しボタン ----
    if (undoBtn) {
        undoBtn.addEventListener('click', function () {
            if (!selectedMatchId || !selectedPlayerId) {
                showToast('試合と選手を先に選択してください');
                return;
            }

            undoBtn.disabled = true;
            const body = new URLSearchParams({
                match_id:  selectedMatchId,
                player_id: selectedPlayerId,
            });

            fetch('../api/undo_log.php', { method: 'POST', body })
                .then(r => r.json())
                .then(data => {
                    if (data.error) { showToast(data.error); return; }
                    showToast(`取り消し: ${data.action_name}`);
                    removeLastLogItem();
                })
                .catch(() => showToast('通信エラーが発生しました'))
                .finally(() => { undoBtn.disabled = false; });
        });
    }

    // ---- ヘルパー ----
    function toggleActionSection() {
        if (!actionSection) return;
        if (selectedMatchId && selectedPlayerId) {
            actionSection.style.display = '';
        } else {
            actionSection.style.display = 'none';
        }
    }

    function updateSelectedLabel() {
        if (!selectedLabel) return;
        if (selectedPlayerId) {
            selectedLabel.textContent = '#' + getSelectedPlayerNumber() + ' ' + getSelectedPlayerName();
        } else {
            selectedLabel.textContent = '未選択';
        }
    }

    function getSelectedPlayerName() {
        const btn = document.querySelector('.player-btn.active');
        return btn ? btn.dataset.playerName : '';
    }

    function getSelectedPlayerNumber() {
        const btn = document.querySelector('.player-btn.active');
        return btn ? btn.dataset.playerNumber : '';
    }

    function addLogItem({ actionName, pointValue, category, playerName }) {
        if (!logFeed) return;
        const sign = pointValue >= 0 ? '+' : '';
        const item = document.createElement('div');
        item.className = `log-item ${category}`;
        item.innerHTML = `
            <span class="log-player">${escHtml(playerName)}</span>
            <span class="log-action">${escHtml(actionName)}</span>
            <span class="log-pts">${sign}${pointValue}pt</span>
        `;
        logFeed.prepend(item);
    }

    function removeLastLogItem() {
        if (!logFeed) return;
        const first = logFeed.querySelector('.log-item');
        if (first) first.remove();
    }

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // ---- トースト ----
    let toastTimer = null;
    function showToast(message) {
        let toast = document.getElementById('toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'toast';
            document.body.appendChild(toast);
        }
        toast.textContent = message;
        toast.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => toast.classList.remove('show'), 2200);
    }

    // ---- 初期状態設定 ----
    toggleActionSection();
    updateSelectedLabel();

    // 外部から呼べるようにグローバルに公開
    window.AmisterApp = { showToast };
})();
