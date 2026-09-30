<section class="card stack narrow">
    <h2>เพิ่มสถานศึกษา</h2>
    <p class="hint">แต่ละสถานศึกษาได้ภาคเรียน นโยบายเริ่มต้น และแถวตั้งค่า AI ของตนเอง แยกจากแห่งอื่น</p>
    <form method="post" action="<?= e(url('/schools')) ?>" class="stack">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="create">
        <label>ชื่อสถานศึกษา<input name="name" required></label>
        <button class="btn btn-primary" type="submit">เพิ่มสถานศึกษา</button>
    </form>
</section>
<?php foreach ($rows as $school): ?>
<section class="card">
    <header class="card-head"><strong><?= e($school['name']) ?></strong></header>
    <ul class="user-list">
        <?php foreach ($school['users'] as $person): ?>
            <li><strong><?= e($person['display_name']) ?></strong> <span><?= e(role_label((string) $person['role'])) ?></span> <code><?= e($person['username']) ?></code></li>
        <?php endforeach; ?>
        <?php if ($school['users'] === []): ?><li class="empty">ยังไม่มีผู้ใช้ของสถานศึกษานี้</li><?php endif; ?>
    </ul>
    <form method="post" action="<?= e(url('/schools')) ?>" class="user-form">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="user">
        <input type="hidden" name="school_id" value="<?= (int) $school['id'] ?>">
        <input name="display_name" placeholder="ชื่อที่แสดง" required>
        <input name="username" placeholder="ชื่อผู้ใช้หรืออีเมล" required maxlength="254" pattern="[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}|[A-Za-z0-9._-]{3,64}" autocomplete="username">
        <input type="password" name="password" placeholder="รหัสผ่านอย่างน้อย 8 ตัว" required minlength="8">
        <select name="role">
            <option value="school_admin">ผู้ดูแลระบบสถานศึกษา</option>
            <option value="scheduler">ผู้จัดตาราง</option>
            <option value="teacher">ครูผู้สอน</option>
        </select>
        <button class="btn btn-primary" type="submit">เพิ่มผู้ใช้</button>
    </form>
</section>
<?php endforeach; ?>
<?php if ($rows === []): ?><p class="empty">ยังไม่มีสถานศึกษา</p><?php endif; ?>
