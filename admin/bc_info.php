<?php
require_once '../config/config.php';
require_once '../common/middleware.php';
checkRole('admin');

$pdo = getDB();
$groupId = (int)($_GET['group_id'] ?? $_GET['id'] ?? 0);

if (!$groupId) {
    $groups = getAllGroups();
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BC Info - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Plus Jakarta Sans', system-ui, sans-serif; }
        body {
            min-height: 100vh;
            background: linear-gradient(160deg, #0f172a 0%, #1e3a5f 40%, #312e81 100%);
            padding: 1.5rem 1rem;
        }
        .picker-wrap { max-width: 480px; margin: 0 auto; }
        .picker-card {
            border: none;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 25px 50px rgba(0,0,0,.35);
        }
        .picker-hero {
            background: linear-gradient(135deg, #6366f1, #8b5cf6, #d946ef);
            color: #fff;
            padding: 2rem 1.5rem 1.5rem;
            text-align: center;
        }
        .picker-hero h4 { font-weight: 800; margin-bottom: .25rem; }
        .group-pick-item {
            border: none;
            border-bottom: 1px solid #f1f5f9;
            padding: 1rem 1.25rem;
            transition: all .2s;
        }
        .group-pick-item:hover { background: linear-gradient(90deg, #eef2ff, #faf5ff); padding-left: 1.5rem; }
        .group-pick-item:last-child { border-bottom: none; }
        .pick-icon {
            width: 42px; height: 42px; border-radius: 12px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: #fff; display: flex; align-items: center; justify-content: center;
            font-size: 1rem; flex-shrink: 0;
        }
    </style>
</head>
<body>
    <div class="picker-wrap">
        <div class="card picker-card">
            <div class="picker-hero">
                <div style="font-size:2.5rem;margin-bottom:.5rem;">📊</div>
                <h4>BC Info</h4>
                <small class="opacity-75">Select a group to view & share</small>
            </div>
            <div class="list-group list-group-flush">
                <?php if (empty($groups)): ?>
                    <div class="p-4 text-center text-muted">No groups found.</div>
                <?php else: ?>
                    <?php foreach ($groups as $g): ?>
                        <a href="bc_info.php?group_id=<?= $g['id'] ?>" class="list-group-item group-pick-item d-flex align-items-center gap-3 text-decoration-none text-dark">
                            <div class="pick-icon"><i class="fas fa-users"></i></div>
                            <div class="flex-grow-1">
                                <strong><?= htmlspecialchars($g['group_name']) ?></strong>
                                <div class="small text-muted">
                                    <?= (int)$g['total_members'] ?> members · <?= formatCurrency($g['monthly_contribution']) ?>/mo
                                </div>
                            </div>
                            <span class="badge rounded-pill bg-<?= $g['status'] === 'active' ? 'success' : 'secondary' ?>"><?= ucfirst($g['status']) ?></span>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="card-footer bg-white border-0 py-3 px-3">
                <a href="index.php" class="btn btn-outline-secondary btn-sm rounded-pill"><i class="fas fa-arrow-left"></i> Dashboard</a>
            </div>
        </div>
    </div>
</body>
</html>
    <?php
    exit;
}

$group = getGroupById($groupId);
if (!$group) {
    setMessage('Group not found.', 'error');
    redirect('bc_info.php');
}

$members = getGroupMembers($groupId);
$monthlyBids = getMonthlyBids($groupId);

$completedMonths = count($monthlyBids);
$totalMonths = (int)$group['total_members'];
$progressPct = $totalMonths > 0 ? round(($completedMonths / $totalMonths) * 100) : 0;
$remainingMonths = max(0, $totalMonths - $completedMonths);
$currentMonth = getCurrentActiveMonthNumber($groupId);

$endDate = null;
if (!empty($group['start_date']) && $totalMonths > 0) {
    $endDate = (new DateTime($group['start_date']))->add(new DateInterval('P' . ($totalMonths - 1) . 'M'));
}

$bidsByMonth = [];
foreach ($monthlyBids as $bid) {
    $bidsByMonth[(int)$bid['month_number']] = $bid;
}

$wonMemberIds = array_filter(array_column($monthlyBids, 'taken_by_member_id'));
$membersAwaitingTurn = count($members) - count(array_unique($wonMemberIds));

$currentMonthStatus = null;
if ($currentMonth) {
    $stmt = $pdo->prepare("SELECT bidding_status FROM month_bidding_status WHERE group_id = ? AND month_number = ?");
    $stmt->execute([$groupId, $currentMonth]);
    $currentMonthStatus = $stmt->fetchColumn() ?: 'not_started';
}

$clientName = $_SESSION['client_name'] ?? null;
$generatedAt = date('d M Y, h:i A');
$statusLabel = ucfirst($group['status']);
$statusIcon = $group['status'] === 'active' ? 'fa-play-circle' : ($group['status'] === 'completed' ? 'fa-check-circle' : 'fa-pause-circle');

// SVG ring math (compact)
$ringRadius = 38;
$ringCircumference = 2 * M_PI * $ringRadius;
$ringOffset = $ringCircumference - ($progressPct / 100) * $ringCircumference;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BC Info — <?= htmlspecialchars($group['group_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Plus Jakarta Sans', system-ui, sans-serif; box-sizing: border-box; }

        body {
            background: linear-gradient(160deg, #0f172a 0%, #1e3a5f 50%, #312e81 100%);
            min-height: 100vh;
            padding-bottom: 2rem;
        }

        .toolbar {
            max-width: 440px;
            margin: 1rem auto 0;
            padding: 0 10px;
        }

        .toolbar .btn {
            font-size: 0.8rem;
            border-radius: 999px;
            font-weight: 600;
            border: none;
        }

        .btn-snap {
            background: linear-gradient(135deg, #10b981, #059669);
            color: #fff;
        }

        .btn-snap:hover { color: #fff; opacity: .9; }

        #bc-info-card {
            max-width: 440px;
            margin: .75rem auto 0;
            border-radius: 22px;
            overflow: hidden;
            box-shadow: 0 30px 60px rgba(0,0,0,.4), 0 0 0 1px rgba(255,255,255,.08);
        }

        .bc-header {
            background: linear-gradient(145deg, #4f46e5 0%, #7c3aed 45%, #c026d3 100%);
            color: #fff;
            padding: 1.1rem 1rem 1.85rem;
            position: relative;
            overflow: hidden;
        }

        .bc-header::before {
            content: '';
            position: absolute;
            width: 140px; height: 140px;
            border-radius: 50%;
            background: rgba(255,255,255,.08);
            top: -50px; right: -40px;
        }

        .bc-header-inner { position: relative; z-index: 1; }

        .bc-brand {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            font-size: .6rem;
            font-weight: 700;
            letter-spacing: 1.4px;
            text-transform: uppercase;
            background: rgba(255,255,255,.15);
            padding: .2rem .55rem;
            border-radius: 999px;
            margin-bottom: .45rem;
        }

        .bc-title {
            font-size: 1.25rem;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: .45rem;
        }

        .bc-meta-row { display: flex; flex-wrap: wrap; gap: .3rem; }

        .meta-chip {
            display: inline-flex;
            align-items: center;
            gap: .25rem;
            font-size: .65rem;
            font-weight: 600;
            background: rgba(255,255,255,.18);
            border: 1px solid rgba(255,255,255,.22);
            padding: .18rem .5rem;
            border-radius: 999px;
        }

        .bc-body {
            background: #fff;
            margin-top: -1rem;
            border-radius: 18px 18px 0 0;
            position: relative;
            z-index: 2;
            padding: .85rem .85rem .65rem;
        }

        .overview-panel {
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: .65rem .7rem;
            margin-bottom: .6rem;
        }

        .overview-top {
            display: flex;
            align-items: center;
            gap: .65rem;
            margin-bottom: .55rem;
        }

        .ring-wrap { position: relative; width: 84px; height: 84px; flex-shrink: 0; }
        .ring-wrap svg { transform: rotate(-90deg); width: 84px; height: 84px; }
        .ring-bg { fill: none; stroke: #e2e8f0; stroke-width: 8; }
        .ring-fill { fill: none; stroke: url(#ringGrad); stroke-width: 8; stroke-linecap: round; }

        .ring-center {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .ring-pct { font-size: 1.05rem; font-weight: 800; color: #4f46e5; line-height: 1; }
        .ring-sub { font-size: .55rem; color: #64748b; font-weight: 700; text-transform: uppercase; }

        .overview-text { flex: 1; min-width: 0; }
        .overview-text h6 { font-size: .78rem; font-weight: 800; color: #0f172a; margin: 0 0 .2rem; }
        .overview-text p { font-size: .68rem; color: #64748b; margin: 0 0 .35rem; line-height: 1.3; }

        .month-dots { display: flex; gap: 2px; flex-wrap: wrap; }
        .month-dot { width: 6px; height: 6px; border-radius: 50%; background: #cbd5e1; }
        .month-dot.done { background: #22c55e; }
        .month-dot.current { background: #f59e0b; }

        .status-line {
            font-size: .67rem;
            font-weight: 600;
            padding: .3rem .5rem;
            border-radius: 8px;
            margin-bottom: .5rem;
            line-height: 1.3;
        }
        .status-line.active { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
        .status-line.done { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; text-align: center; }

        .stat-pills {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: .35rem;
        }

        .stat-pill {
            text-align: center;
            padding: .4rem .2rem;
            border-radius: 10px;
            color: #fff;
            line-height: 1.15;
        }

        .stat-pill .val {
            font-size: .72rem;
            font-weight: 800;
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .stat-pill .lbl {
            font-size: .5rem;
            font-weight: 600;
            opacity: .9;
            text-transform: uppercase;
            letter-spacing: .3px;
        }

        .stat-pill.c1 { background: linear-gradient(135deg, #3b82f6, #2563eb); }
        .stat-pill.c2 { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
        .stat-pill.c3 { background: linear-gradient(135deg, #10b981, #059669); }
        .stat-pill.c4 { background: linear-gradient(135deg, #f59e0b, #d97706); }

        .section-head {
            display: flex;
            align-items: center;
            gap: .4rem;
            margin: .55rem 0 .4rem;
        }

        .section-head .line { flex: 1; height: 1px; background: #e2e8f0; }

        .section-head .title {
            font-size: .65rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .8px;
            color: #475569;
            white-space: nowrap;
        }

        .section-head .badge-count {
            background: #eef2ff;
            color: #4f46e5;
            font-size: .58rem;
            font-weight: 700;
            padding: .1rem .4rem;
            border-radius: 999px;
        }

        .winner-list {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
        }

        .winner-row {
            display: grid;
            grid-template-columns: 34px 1fr auto;
            align-items: center;
            gap: .4rem;
            padding: .32rem .55rem;
            font-size: .68rem;
            border-bottom: 1px solid #f1f5f9;
            background: #fff;
        }

        .winner-row:last-child { border-bottom: none; }
        .winner-row.done { background: #f0fdf4; }
        .winner-row.current { background: #fffbeb; }

        .winner-row .wr-month { font-weight: 800; color: #64748b; font-size: .62rem; text-align: center; }
        .winner-row.done .wr-month { color: #16a34a; }
        .winner-row.current .wr-month { color: #d97706; }

        .winner-row .wr-name {
            font-weight: 700;
            color: #1e293b;
            line-height: 1.25;
            word-break: break-word;
            overflow-wrap: break-word;
        }

        .winner-row .wr-amt { font-size: .6rem; font-weight: 700; color: #059669; white-space: nowrap; }
        .winner-row .wr-pending { font-size: .58rem; color: #94a3b8; font-style: italic; }

        .member-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: .3rem .4rem;
        }

        .member-chip {
            display: flex;
            align-items: flex-start;
            gap: .35rem;
            padding: .35rem .4rem;
            border-radius: 9px;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            min-width: 0;
        }

        .member-chip.won { border-color: #86efac; background: #f0fdf4; }

        .member-chip .m-no {
            flex-shrink: 0;
            width: 20px;
            height: 20px;
            border-radius: 6px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: #fff;
            font-size: .58rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: 1px;
        }

        .member-chip.won .m-no { background: linear-gradient(135deg, #22c55e, #16a34a); }

        .member-chip .m-body { flex: 1; min-width: 0; }

        .member-chip .m-name {
            font-size: .67rem;
            font-weight: 700;
            color: #1e293b;
            line-height: 1.25;
            word-break: break-word;
            overflow-wrap: break-word;
        }

        .member-chip .m-won-tag { font-size: .5rem; font-weight: 800; color: #16a34a; margin-top: .1rem; }

        .bc-footer {
            background: #f8fafc;
            text-align: center;
            padding: .5rem .75rem;
            border-top: 1px solid #e2e8f0;
        }

        .bc-footer .app-name { font-size: .65rem; font-weight: 700; color: #6366f1; }
        .bc-footer .gen-date { font-size: .58rem; color: #94a3b8; }

        @media print {
            body { background: #fff; }
            .toolbar, .no-print { display: none !important; }
            #bc-info-card { box-shadow: none; margin: 0; max-width: 100%; }
        }
    </style>
</head>
<body>
    <div class="toolbar no-print d-flex flex-wrap gap-2 justify-content-between align-items-center">
        <div class="d-flex gap-2">
            <a href="view_group.php?id=<?= $groupId ?>" class="btn btn-light btn-sm">
                <i class="fas fa-arrow-left"></i>
            </a>
            <a href="bc_info.php" class="btn btn-light btn-sm">
                <i class="fas fa-th-list"></i>
            </a>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-snap btn-sm" id="btnSnapshot">
                <i class="fas fa-camera"></i> Save Image
            </button>
            <button type="button" class="btn btn-light btn-sm" onclick="window.print()">
                <i class="fas fa-print"></i>
            </button>
        </div>
    </div>

    <div id="bc-info-card">
        <div class="bc-header">
            <div class="bc-header-inner">
                <div class="bc-brand">
                    <i class="fas fa-coins"></i>
                    BC Info<?= $clientName ? ' · ' . htmlspecialchars($clientName) : '' ?>
                </div>
                <div class="bc-title"><?= htmlspecialchars($group['group_name']) ?></div>
                <div class="bc-meta-row">
                    <span class="meta-chip"><i class="fas <?= $statusIcon ?>"></i> <?= $statusLabel ?></span>
                    <?php if (!empty($group['start_date'])): ?>
                        <span class="meta-chip"><i class="fas fa-calendar-alt"></i> <?= formatDate($group['start_date']) ?></span>
                    <?php endif; ?>
                    <?php if ($endDate): ?>
                        <span class="meta-chip"><i class="fas fa-flag-checkered"></i> <?= $endDate->format('d/m/Y') ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="bc-body">
            <div class="overview-panel">
                <div class="overview-top">
                    <div class="ring-wrap">
                        <svg viewBox="0 0 120 120">
                            <defs>
                                <linearGradient id="ringGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                                    <stop offset="0%" stop-color="#6366f1"/>
                                    <stop offset="100%" stop-color="#c026d3"/>
                                </linearGradient>
                            </defs>
                            <circle class="ring-bg" cx="60" cy="60" r="<?= $ringRadius ?>"/>
                            <circle class="ring-fill" cx="60" cy="60" r="<?= $ringRadius ?>"
                                stroke-dasharray="<?= $ringCircumference ?>"
                                stroke-dashoffset="<?= $ringOffset ?>"/>
                        </svg>
                        <div class="ring-center">
                            <div class="ring-pct"><?= $progressPct ?>%</div>
                            <div class="ring-sub">Done</div>
                        </div>
                    </div>
                    <div class="overview-text">
                        <h6><?= $completedMonths ?>/<?= $totalMonths ?> Months Complete</h6>
                        <p><?= $remainingMonths > 0 ? $remainingMonths . ' month' . ($remainingMonths > 1 ? 's' : '') . ' left' : 'All done!' ?></p>
                        <div class="month-dots">
                            <?php for ($m = 1; $m <= $totalMonths; $m++):
                                $dotClass = isset($bidsByMonth[$m]) ? 'done' : ($m === $currentMonth ? 'current' : '');
                            ?>
                                <div class="month-dot <?= $dotClass ?>"></div>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>
                <?php if ($group['status'] === 'active' && $currentMonth): ?>
                <div class="status-line active">
                    <i class="fas fa-bolt"></i>
                    Month <?= $currentMonth ?> — <?= ucwords(str_replace('_', ' ', $currentMonthStatus)) ?>
                    <?php if ($membersAwaitingTurn > 0): ?> · <?= $membersAwaitingTurn ?> awaiting<?php endif; ?>
                </div>
                <?php elseif ($group['status'] === 'completed'): ?>
                <div class="status-line done"><i class="fas fa-trophy"></i> BC Completed!</div>
                <?php endif; ?>
                <div class="stat-pills">
                    <div class="stat-pill c1"><span class="val"><?= $totalMonths ?></span><span class="lbl">Members</span></div>
                    <div class="stat-pill c2"><span class="val"><?= formatCurrency($group['monthly_contribution']) ?></span><span class="lbl">/ Month</span></div>
                    <div class="stat-pill c3"><span class="val"><?= formatCurrency($group['total_monthly_collection']) ?></span><span class="lbl">Pool</span></div>
                    <div class="stat-pill c4"><span class="val"><?= $remainingMonths ?></span><span class="lbl">Left</span></div>
                </div>
            </div>

            <?php if ($totalMonths > 0): ?>
            <div class="section-head">
                <span class="title"><i class="fas fa-trophy text-warning"></i> Winners</span>
                <span class="badge-count"><?= $completedMonths ?>/<?= $totalMonths ?></span>
                <div class="line"></div>
            </div>
            <div class="winner-list">
                <?php for ($m = 1; $m <= $totalMonths; $m++):
                    $bid = $bidsByMonth[$m] ?? null;
                    $rowClass = $bid ? 'done' : ($m === $currentMonth ? 'current' : '');
                    $winnerName = $bid ? ($bid['member_name'] ?: '—') : ($m === $currentMonth ? 'Pending' : '—');
                ?>
                <div class="winner-row <?= $rowClass ?>">
                    <span class="wr-month">M<?= $m ?></span>
                    <span class="wr-name"><?= htmlspecialchars($winnerName) ?></span>
                    <?php if ($bid && $bid['net_payable']): ?>
                        <span class="wr-amt"><?= formatCurrency($bid['net_payable']) ?></span>
                    <?php elseif ($m === $currentMonth && !$bid): ?>
                        <span class="wr-pending">Now</span>
                    <?php else: ?>
                        <span class="wr-pending">—</span>
                    <?php endif; ?>
                </div>
                <?php endfor; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($members)): ?>
            <div class="section-head">
                <span class="title"><i class="fas fa-user-friends text-primary"></i> Members</span>
                <span class="badge-count"><?= count($members) ?></span>
                <div class="line"></div>
            </div>
            <div class="member-grid">
                <?php foreach ($members as $member):
                    $hasWon = in_array($member['id'], $wonMemberIds);
                ?>
                <div class="member-chip <?= $hasWon ? 'won' : '' ?>">
                    <span class="m-no"><?= $member['member_number'] ?></span>
                    <div class="m-body">
                        <div class="m-name"><?= htmlspecialchars($member['member_name']) ?></div>
                        <?php if ($hasWon): ?><div class="m-won-tag">✓ Won</div><?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="bc-footer">
            <div class="app-name"><i class="fas fa-coins"></i> <?= APP_NAME ?></div>
            <div class="gen-date">Updated <?= $generatedAt ?></div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script>
        document.getElementById('btnSnapshot').addEventListener('click', function () {
            const btn = this;
            const card = document.getElementById('bc-info-card');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            html2canvas(card, {
                scale: 2,
                useCORS: true,
                backgroundColor: '#ffffff',
                logging: false
            }).then(function (canvas) {
                const link = document.createElement('a');
                const safeName = <?= json_encode(preg_replace('/[^a-zA-Z0-9_-]/', '_', $group['group_name'])) ?>;
                link.download = 'BC_Info_' + safeName + '_' + new Date().toISOString().slice(0, 10) + '.png';
                link.href = canvas.toDataURL('image/png');
                link.click();
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-camera"></i> Save Image';
            }).catch(function () {
                alert('Could not create image. Try Print or take a screenshot.');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-camera"></i> Save Image';
            });
        });
    </script>
</body>
</html>