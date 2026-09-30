<?php if (!$hasSchool): ?>
    <div class="banner warn">ยังไม่มีสถานศึกษา ผู้ดูแลระบบเพิ่มได้ที่เมนูสถานศึกษา</div>
<?php endif; ?>
<div class="stat-grid">
    <?php
    $stats = [
        ['bi-person-badge', (string) $counts['teachers'], 'ครูผู้สอน'],
        ['bi-people', (string) $counts['groups'], 'กลุ่มผู้เรียน'],
        ['bi-journal-text', (string) $counts['subjects'], 'รายวิชาในภาคเรียน'],
        ['bi-door-open', (string) $counts['rooms'], 'ห้องเรียน · ' . (int) $counts['campuses'] . ' วิทยาเขต'],
    ];
    foreach ($stats as $stat): ?>
        <article class="stat">
            <i class="bi <?= e($stat[0]) ?>"></i>
            <span><strong><?= e($stat[1]) ?></strong><small><?= e($stat[2]) ?></small></span>
        </article>
    <?php endforeach; ?>
</div>
<div class="split">
    <section class="card">
        <header class="card-head"><strong>ความคืบหน้าการจัดตาราง</strong><a href="<?= e(url('/schedule')) ?>">ไปหน้าจัดตาราง</a></header>
        <?php if ($progress === []): ?>
            <p class="empty">ยังไม่มีกลุ่มผู้เรียน</p>
        <?php endif; ?>
        <?php foreach ($progress as $row): ?>
            <div class="progress-row">
                <span><?= e($row['name']) ?></span>
                <span class="bar"><span style="width: <?= (int) $row['pct'] ?>%"></span></span>
                <em><?= (int) $row['pct'] ?>%</em>
            </div>
        <?php endforeach; ?>
    </section>
    <section class="card">
        <header class="card-head"><strong><i class="bi bi-stars"></i> ข้อสังเกตจากผู้ช่วย AI</strong></header>
        <?php foreach ($alerts as $alert): ?>
            <div class="alert-row">
                <i class="bi <?= e($alert['icon']) ?>" style="color: <?= e($alert['color']) ?>"></i>
                <span><strong><?= e($alert['title']) ?></strong><small><?= e($alert['text']) ?></small></span>
            </div>
        <?php endforeach; ?>
    </section>
</div>
