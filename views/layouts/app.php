<?php
$shell = app_shell($currentPage ?? '');
?>
<!DOCTYPE html>
<html lang="th">
<head>
<?php require app_root() . '/views/partials/head.php'; ?>
<title><?= e($shell['pageTitle'] !== '' ? $shell['pageTitle'] . ' · ' : '') ?>จัดตารางอาชีวศึกษา</title>
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <a class="brand" href="<?= e(url('/')) ?>">
            <span class="logo-mark">สอศ.</span>
            <span><strong>จัดตารางอาชีวศึกษา</strong><small>VEC SMART TIMETABLE</small></span>
        </a>
        <nav>
            <span class="nav-label">งานจัดตาราง</span>
            <?php foreach ($shell['navMain'] as $item): ?>
                <a class="<?= $item['active'] ? 'active' : '' ?>" href="<?= e($item['href']) ?>"><i class="bi <?= e($item['icon']) ?>"></i><?= e($item['label']) ?></a>
            <?php endforeach; ?>
            <span class="nav-label">ข้อมูลและระบบ</span>
            <?php foreach ($shell['navSys'] as $item): ?>
                <a class="<?= $item['active'] ? 'active' : '' ?>" href="<?= e($item['href']) ?>"><i class="bi <?= e($item['icon']) ?>"></i><?= e($item['label']) ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="ai-pill">
            <span class="dot <?= $shell['aiConnected'] ? 'on' : '' ?>"></span>
            <?= $shell['aiConnected'] ? 'AI เชื่อมต่อแล้ว' : 'AI ยังไม่ได้เชื่อมต่อ' ?> · <?= e($shell['providerName']) ?>
        </div>
    </aside>
    <div class="workspace">
        <header class="topbar">
            <?php if ($shell['canSwitch']): ?>
                <form method="post" action="<?= e(url('/school')) ?>" class="school-switch">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="back" value="<?= e(request_path() . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '')) ?>">
                    <i class="bi bi-building"></i>
                    <select name="school_id" onchange="this.form.submit()">
                        <?php foreach ($shell['schools'] as $school): ?>
                            <option value="<?= (int) $school['id'] ?>" <?= $shell['school'] && (int) $shell['school']['id'] === (int) $school['id'] ? 'selected' : '' ?>><?= e($school['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            <?php else: ?>
                <div class="school-switch static"><i class="bi bi-building"></i><strong><?= e($shell['school']['name'] ?? 'ยังไม่มีสถานศึกษา') ?></strong></div>
            <?php endif; ?>
            <span class="term-pill"><?= e($shell['termLabel']) ?></span>
            <div class="spacer"></div>
            <div class="who">
                <span class="avatar"><?= e($shell['initial']) ?></span>
                <span><strong><?= e($shell['user']['display_name'] ?? '') ?></strong><small><?= e($shell['roleLabel']) ?></small></span>
            </div>
            <form method="post" action="<?= e(url('/logout')) ?>">
                <?= Csrf::field() ?>
                <button class="btn btn-ghost" type="submit">ออกจากระบบ</button>
            </form>
        </header>
        <main class="page">
            <?php if ($shell['flash']): ?>
                <div class="banner <?= $shell['flash']['type'] === 'err' ? 'err' : 'ok' ?>"><?= e($shell['flash']['text']) ?></div>
            <?php endif; ?>
            <div class="page-title">
                <span><?= e($shell['school']['name'] ?? '') ?></span>
                <h1><?= e($shell['pageTitle']) ?></h1>
            </div>
            <?= $content ?>
        </main>
    </div>
</div>
<?php require app_root() . '/views/partials/chat.php'; ?>
<script src="<?= e(url('/assets/js/app.js')) ?>?v=<?= (int) (@filemtime(app_root() . '/assets/js/app.js') ?: time()) ?>"></script>
</body>
</html>
