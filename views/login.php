<!DOCTYPE html>
<html lang="th">
<head>
<?php require app_root() . '/views/partials/head.php'; ?>
<title>เข้าสู่ระบบ · จัดตารางอาชีวศึกษา</title>
</head>
<body class="auth-body">
<main class="auth-card">
    <a class="brand-light" href="<?= e(url('/')) ?>">
        <span class="logo-mark">สอศ.</span>
        <span><strong>จัดตารางอาชีวศึกษา</strong><small>VEC SMART TIMETABLE</small></span>
    </a>
    <h1>เข้าสู่ระบบ</h1>
    <p class="lead">ผู้ดูแลระบบสถานศึกษาเห็นเฉพาะข้อมูลและการเชื่อมต่อ AI ของสถานศึกษาตนเอง</p>
    <?php if (!empty($error)): ?><div class="banner err"><?= e($error['text']) ?></div><?php endif; ?>
    <form method="post" action="<?= e(url('/login')) ?>" class="stack">
        <?= Csrf::field() ?>
        <label>ชื่อผู้ใช้หรืออีเมล<input name="username" required maxlength="254" autocomplete="username"></label>
        <label>รหัสผ่าน<input type="password" name="password" required autocomplete="current-password"></label>
        <button class="btn btn-primary" type="submit">เข้าสู่ระบบ</button>
    </form>
</main>
</body>
</html>
