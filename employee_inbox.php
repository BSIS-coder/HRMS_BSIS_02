<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'employee') {
    header('Location: login.php'); exit;
}

require_once 'dp.php';

$username    = $_SESSION['username'] ?? '';
$employee_id = $_SESSION['user_id'] ?? null;
$current_page = basename($_SERVER['PHP_SELF']);

// ── DB ────────────────────────────────────────────────
try {
    $pdo = new PDO("mysql:host=localhost;dbname=hr_system", 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// ── Ensure inbox table exists ─────────────────────────
$pdo->exec("
    CREATE TABLE IF NOT EXISTS employee_inbox (
        inbox_id     INT AUTO_INCREMENT PRIMARY KEY,
        employee_id  INT          NOT NULL,
        exit_id      INT          NULL,
        sender_label VARCHAR(100) NOT NULL DEFAULT 'HR Department',
        subject      VARCHAR(255) NOT NULL,
        message      TEXT         NOT NULL,
        is_read      TINYINT(1)   NOT NULL DEFAULT 0,
        created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX (employee_id),
        INDEX (is_read)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ── Resolve real employee_id from username ────────────
$parts     = explode('.', $username);
$firstName = $parts[0] ?? '';
$lastName  = $parts[1] ?? '';

$stmtResolve = $pdo->prepare("
    SELECT ep.employee_id
    FROM employee_profiles ep
    JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    WHERE LOWER(pi.first_name) = LOWER(?) AND LOWER(pi.last_name) = LOWER(?)
    LIMIT 1
");
$stmtResolve->execute([$firstName, $lastName]);
$profileRow      = $stmtResolve->fetch(PDO::FETCH_ASSOC);
$resolved_emp_id = $profileRow ? $profileRow['employee_id'] : $employee_id;

// ── AJAX: mark as read ────────────────────────────────
if (isset($_POST['ajax']) && $_POST['ajax'] === 'true') {
    header('Content-Type: application/json');
    if ($_POST['action'] === 'mark_read') {
        $pdo->prepare("UPDATE employee_inbox SET is_read=1 WHERE inbox_id=? AND employee_id=?")
            ->execute([$_POST['inbox_id'], $resolved_emp_id]);
        echo json_encode(['success' => true]);
    } elseif ($_POST['action'] === 'mark_all_read') {
        $pdo->prepare("UPDATE employee_inbox SET is_read=1 WHERE employee_id=?")
            ->execute([$resolved_emp_id]);
        echo json_encode(['success' => true]);
    }
    exit;
}

// ── Fetch messages ────────────────────────────────────
$stmtMsgs = $pdo->prepare("
    SELECT * FROM employee_inbox
    WHERE employee_id = ?
    ORDER BY created_at DESC
");
$stmtMsgs->execute([$resolved_emp_id]);
$messages = $stmtMsgs->fetchAll(PDO::FETCH_ASSOC);

$unreadCount = count(array_filter($messages, fn($m) => !$m['is_read']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Inbox - HR System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        .section-title { color: var(--primary-color); margin-bottom: 30px; font-weight: 600; }
        .container-fluid { padding: 0; }
        .row { margin-right: 0; margin-left: 0; }

        /* ── Welcome banner — matches employee_index.php exactly ── */
        .inbox-welcome {
            background: linear-gradient(135deg, #E91E63 0%, #C2185B 100%);
            color: white; padding: 30px; border-radius: 15px;
            margin-bottom: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            display: flex; align-items: center;
            justify-content: space-between; flex-wrap: wrap; gap: 15px;
        }
        .inbox-welcome h2 { margin: 0; font-weight: 700; }
        .inbox-welcome p  { margin: 10px 0 0; opacity: .9; }

        .unread-pill {
            background: rgba(255,255,255,0.25);
            border: 2px solid rgba(255,255,255,0.5);
            color: #fff; font-weight: 700; font-size: 14px;
            padding: 6px 18px; border-radius: 25px;
            animation: pillPulse 2s ease-in-out infinite;
            white-space: nowrap;
        }
        @keyframes pillPulse { 0%,100%{transform:scale(1)} 50%{transform:scale(1.05)} }

        /* ── Controls row ── */
        .inbox-controls {
            display: flex; align-items: center;
            justify-content: space-between;
            margin-bottom: 20px; flex-wrap: wrap; gap: 10px;
        }

        /* ── Tabs ── */
        .inbox-tabs { display: flex; gap: 8px; flex-wrap: wrap; }
        .tab-btn {
            padding: 8px 22px; border-radius: 25px;
            border: 2px solid #e0e0e0; background: #fff;
            font-weight: 600; font-size: 14px; cursor: pointer;
            transition: all .25s; color: #666;
        }
        .tab-btn.active, .tab-btn:hover {
            border-color: #E91E63; background: #E91E63; color: #fff;
        }
        .tab-count {
            display: inline-block; background: #E91E63; color: #fff;
            font-size: 10px; font-weight: 800; padding: 1px 6px;
            border-radius: 10px; margin-left: 4px; vertical-align: middle;
        }
        .tab-btn.active .tab-count, .tab-btn:hover .tab-count {
            background: rgba(255,255,255,.35);
        }

        /* ── Mark all read ── */
        .btn-mark-all {
            background: #fff; border: 2px solid #E91E63; color: #E91E63;
            padding: 8px 20px; border-radius: 25px;
            font-weight: 600; font-size: 14px; cursor: pointer; transition: all .25s;
        }
        .btn-mark-all:hover { background: #E91E63; color: #fff; }

        /* ── Message cards — matches info-card style ── */
        .message-card {
            background: white; border-radius: 15px;
            margin-bottom: 14px; box-shadow: 0 5px 15px rgba(0,0,0,.08);
            border: 1px solid #e9ecef; border-left: 5px solid #dee2e6;
            transition: all .25s; cursor: pointer; overflow: hidden;
        }
        .message-card.unread {
            border-left-color: #E91E63;
            box-shadow: 0 5px 20px rgba(233,30,99,.12);
        }
        .message-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 28px rgba(233,30,99,.15);
        }

        .msg-header {
            display: flex; align-items: center;
            gap: 16px; padding: 18px 22px;
        }
        .msg-icon {
            width: 46px; height: 46px; border-radius: 12px; flex-shrink: 0;
            background: linear-gradient(135deg, #E91E63, #F06292);
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: 18px;
        }
        .msg-icon.read { background: linear-gradient(135deg, #bbb, #ddd); }

        .msg-meta { flex: 1; min-width: 0; }
        .msg-subject {
            font-size: 15px; font-weight: 700; color: #343a40;
            margin-bottom: 4px;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .message-card.read .msg-subject { font-weight: 500; color: #6c757d; }
        .msg-from { font-size: 12px; color: #adb5bd; }

        .msg-right { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
        .unread-dot {
            width: 10px; height: 10px; border-radius: 50%; background: #E91E63;
            animation: dotPulse 2s ease-in-out infinite;
        }
        @keyframes dotPulse { 0%,100%{opacity:1} 50%{opacity:.35} }

        /* ── Expanded body ── */
        .msg-body {
            display: none;
            padding: 18px 22px 22px 84px;
            font-size: 14px; color: #495057;
            line-height: 1.8; white-space: pre-line;
            border-top: 1px solid #f1f3f5;
            animation: bodyIn .3s ease;
        }
        @keyframes bodyIn { from{opacity:0;transform:translateY(-6px)} to{opacity:1;transform:translateY(0)} }
        .msg-body.open { display: block; }

        /* ── Empty state ── */
        .empty-inbox {
            background: white; border-radius: 15px; padding: 70px 20px;
            text-align: center; box-shadow: 0 5px 15px rgba(0,0,0,.08);
            border: 1px solid #e9ecef;
        }
        .empty-inbox i  { font-size: 4rem; color: #dee2e6; margin-bottom: 20px; display: block; }
        .empty-inbox h5 { color: #adb5bd; font-weight: 600; margin-bottom: 8px; }
        .empty-inbox p  { color: #ced4da; font-size: 14px; margin: 0; }
    </style>
</head>
<body class="employee-page">
<div class="container-fluid">
    <?php include 'employee_navigation.php'; ?>
    <div class="row">
        <?php include 'employee_sidebar.php'; ?>
        <div class="main-content">

            <!-- Banner -->
            <div class="inbox-welcome">
                <div>
                    <h2><i class="fas fa-inbox mr-3"></i>My Inbox</h2>
                    <p>Messages and acknowledgments sent to you by the HR Department</p>
                </div>
                <?php if ($unreadCount > 0): ?>
                <div class="unread-pill">
                    <i class="fas fa-envelope mr-2"></i>
                    <?= $unreadCount ?> unread message<?= $unreadCount > 1 ? 's' : '' ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Controls: tabs + mark all -->
            <div class="inbox-controls">
                <div class="inbox-tabs">
                    <button class="tab-btn active" onclick="filterMessages('all', this)">
                        All <span class="tab-count"><?= count($messages) ?></span>
                    </button>
                    <button class="tab-btn" onclick="filterMessages('unread', this)">
                        Unread
                        <?php if ($unreadCount > 0): ?>
                            <span class="tab-count"><?= $unreadCount ?></span>
                        <?php endif; ?>
                    </button>
                    <button class="tab-btn" onclick="filterMessages('read', this)">
                        Read
                        <span class="tab-count"><?= count($messages) - $unreadCount ?></span>
                    </button>
                </div>
                <?php if ($unreadCount > 0): ?>
                <button class="btn-mark-all" onclick="markAllRead()">
                    <i class="fas fa-check-double mr-1"></i> Mark all as read
                </button>
                <?php endif; ?>
            </div>

            <!-- Messages -->
            <div id="messageList">
                <?php if (empty($messages)): ?>
                <div class="empty-inbox">
                    <i class="fas fa-envelope-open"></i>
                    <h5>Your inbox is empty</h5>
                    <p>Acknowledgments and messages from the HR Department will appear here.</p>
                </div>
                <?php else: ?>
                    <?php foreach ($messages as $msg):
                        $isUnread = !$msg['is_read'];
                        $date     = date('M d, Y · g:i A', strtotime($msg['created_at']));
                    ?>
                    <div class="message-card <?= $isUnread ? 'unread' : 'read' ?>"
                         data-inbox-id="<?= $msg['inbox_id'] ?>"
                         data-read="<?= (int)$msg['is_read'] ?>"
                         onclick="toggleMessage(this)">

                        <div class="msg-header">
                            <div class="msg-icon <?= $isUnread ? '' : 'read' ?>">
                                <i class="fas fa-<?= $isUnread ? 'envelope' : 'envelope-open' ?>"></i>
                            </div>
                            <div class="msg-meta">
                                <div class="msg-subject"><?= htmlspecialchars($msg['subject']) ?></div>
                                <div class="msg-from">
                                    <i class="fas fa-building mr-1"></i>
                                    <?= htmlspecialchars($msg['sender_label']) ?>
                                    &nbsp;·&nbsp; <?= $date ?>
                                </div>
                            </div>
                            <div class="msg-right">
                                <?php if ($isUnread): ?>
                                    <div class="unread-dot" title="Unread"></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="msg-body"><?= htmlspecialchars($msg['message']) ?></div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div><!-- /main-content -->
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script>
function toggleMessage(card) {
    const body   = card.querySelector('.msg-body');
    const isOpen = body.classList.contains('open');

    // Close all others first
    document.querySelectorAll('.msg-body.open').forEach(b => b.classList.remove('open'));

    if (!isOpen) {
        body.classList.add('open');
        if (card.dataset.read === '0') {
            markRead(card.dataset.inboxId, card);
        }
    }
}

function markRead(inboxId, card) {
    const fd = new FormData();
    fd.append('ajax',     'true');
    fd.append('action',   'mark_read');
    fd.append('inbox_id', inboxId);

    fetch('employee_inbox.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;

            card.dataset.read = '1';
            card.classList.remove('unread');
            card.classList.add('read');

            const dot    = card.querySelector('.unread-dot');
            const icon   = card.querySelector('.msg-icon');
            const iconEl = card.querySelector('.msg-icon i');
            if (dot)    dot.remove();
            if (icon)   icon.classList.add('read');
            if (iconEl) iconEl.className = 'fas fa-envelope-open';

            updateSidebarBell(-1);
            refreshCounts();
        });
}

function markAllRead() {
    const fd = new FormData();
    fd.append('ajax',   'true');
    fd.append('action', 'mark_all_read');
    fetch('employee_inbox.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => { if (data.success) location.reload(); });
}

function filterMessages(type, btn) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.message-card').forEach(card => {
        if      (type === 'all')    card.style.display = '';
        else if (type === 'unread') card.style.display = card.dataset.read === '0' ? '' : 'none';
        else                        card.style.display = card.dataset.read === '1' ? '' : 'none';
    });
}

function refreshCounts() {
    const all    = document.querySelectorAll('.message-card').length;
    const unread = document.querySelectorAll('.message-card[data-read="0"]').length;
    const read   = all - unread;

    const counts = document.querySelectorAll('.tab-count');
    if (counts[0]) counts[0].textContent = all;
    if (counts[1]) counts[1].textContent = unread;
    if (counts[2]) counts[2].textContent = read;

    if (unread === 0) {
        const markAllBtn = document.querySelector('.btn-mark-all');
        if (markAllBtn) markAllBtn.style.display = 'none';
        const pill = document.querySelector('.unread-pill');
        if (pill) pill.style.display = 'none';
    }
}

function updateSidebarBell(delta) {
    // Nav badge in sidebar <My Inbox> link
    const navBadge  = document.querySelector('.nav-inbox-badge');
    // Top bell row badge
    const bellBadge = document.getElementById('inboxBellCount');

    [navBadge, bellBadge].forEach(el => {
        if (!el) return;
        let count = parseInt(el.textContent || '0') + delta;
        if (count <= 0) {
            const bellRow = document.querySelector('.inbox-bell-row');
            if (bellRow) bellRow.remove();
            el.remove();
        } else {
            el.textContent = count;
        }
    });
}
</script>
</body>
</html>