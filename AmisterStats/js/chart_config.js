/**
 * AmisterStats — スパイダーチャート設定
 * view.php で使用。Chart.js のレーダーチャートを構築・更新する。
 */
(function () {
    'use strict';

    // パレット（最大10選手）
    const PALETTE = [
        { border: 'rgba(26,127,60,1)',   bg: 'rgba(26,127,60,.15)'   },
        { border: 'rgba(33,150,243,1)',  bg: 'rgba(33,150,243,.15)'  },
        { border: 'rgba(245,166,35,1)',  bg: 'rgba(245,166,35,.15)'  },
        { border: 'rgba(229,57,53,1)',   bg: 'rgba(229,57,53,.15)'   },
        { border: 'rgba(142,36,170,1)',  bg: 'rgba(142,36,170,.15)'  },
        { border: 'rgba(0,188,212,1)',   bg: 'rgba(0,188,212,.15)'   },
        { border: 'rgba(255,112,67,1)',  bg: 'rgba(255,112,67,.15)'  },
        { border: 'rgba(96,125,139,1)',  bg: 'rgba(96,125,139,.15)'  },
        { border: 'rgba(76,175,80,1)',   bg: 'rgba(76,175,80,.15)'   },
        { border: 'rgba(255,64,129,1)',  bg: 'rgba(255,64,129,.15)'  },
    ];

    let chart = null;   // Chart.js インスタンス（再描画時に destroy する）

    /**
     * /api/get_stats.php のレスポンスからチャートを描画する。
     * @param {HTMLCanvasElement} canvas
     * @param {Object} data  — { actions: [], players: [] }
     * @param {number[]} [visiblePlayerIds]  — 表示する選手IDリスト（省略時は全員）
     */
    function renderChart(canvas, data, visiblePlayerIds) {
        if (!canvas || !data) return;

        const labels  = data.actions.map(a => a.name);
        const players = visiblePlayerIds
            ? data.players.filter(p => visiblePlayerIds.includes(p.id))
            : data.players;

        const datasets = players.map((player, idx) => {
            const color = PALETTE[idx % PALETTE.length];
            return {
                label:           `#${player.number} ${player.name}`,
                data:            player.chart_data,
                borderColor:     color.border,
                backgroundColor: color.bg,
                borderWidth:     2,
                pointRadius:     4,
                pointHoverRadius: 6,
            };
        });

        if (chart) {
            chart.destroy();
            chart = null;
        }

        chart = new Chart(canvas, {
            type: 'radar',
            data: { labels, datasets },
            options: {
                responsive:          true,
                maintainAspectRatio: true,
                animation: {
                    duration: 400,
                    easing:   'easeOutQuart',
                },
                scales: {
                    r: {
                        beginAtZero: true,
                        ticks: {
                            stepSize:  1,
                            font:      { size: 10 },
                            color:     '#9e9e9e',
                            backdropColor: 'transparent',
                        },
                        pointLabels: {
                            font:  { size: 11, weight: '600' },
                            color: '#424242',
                        },
                        grid: {
                            color: 'rgba(0,0,0,.08)',
                        },
                        angleLines: {
                            color: 'rgba(0,0,0,.08)',
                        },
                    },
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            font:      { size: 12, weight: '600' },
                            padding:   12,
                            boxWidth:  14,
                            usePointStyle: true,
                            pointStyle: 'circle',
                        },
                    },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                return ` ${ctx.dataset.label}: ${ctx.parsed.r}回`;
                            },
                        },
                    },
                },
            },
        });
    }

    /**
     * 表示する選手を切り替える（チェックボックス連動など）
     */
    function updateVisibility(visiblePlayerIds) {
        if (!chart) return;
        const allDatasets = chart.data.datasets;
        allDatasets.forEach((ds, idx) => {
            const meta = chart.getDatasetMeta(idx);
            meta.hidden = visiblePlayerIds
                ? !visiblePlayerIds.some(id => ds.label.includes(String(id)))
                : false;
        });
        chart.update();
    }

    // グローバルに公開
    window.AmisterChart = { renderChart, updateVisibility };
})();
