<div class="card toolbar">
    <div>
        <strong>แบ่งรายวิชาให้ครูก่อนจัดตาราง</strong>
        <p class="hint">AI เทียบทักษะการสอน วุฒิการศึกษา ประสบการณ์ และภาระสอนสูงสุดของครู กับเนื้อหารายวิชาในแผนการเรียน</p>
    </div>
    <form method="post" action="<?= e(url('/skills')) ?>">
        <?= Csrf::field() ?>
        <button class="btn btn-primary" <?= $canEdit ? '' : 'disabled' ?>><i class="bi bi-stars"></i> <?= $ready ? 'วิเคราะห์ใหม่' : 'ให้ AI วิเคราะห์ทักษะครู' ?></button>
    </form>
</div>
<div class="split skills-split">
    <div class="card table-wrap">
        <table>
            <thead><tr><th>รายวิชา</th><th>ท-ป-น</th><th>ครูที่ AI แนะนำ</th><th>ทางเลือกอื่น</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><small class="mono"><?= e($row['code']) ?></small><strong><?= e($row['name']) ?></strong></td>
                    <td class="mono"><?= (int) $row['theory'] ?>-<?= (int) $row['practice'] ?>-<?= (int) $row['extra'] ?></td>
                    <?php if ($raw): ?>
                        <td class="muted">—</td><td class="muted">—</td>
                    <?php else: ?>
                        <td>
                            <strong><?= e($row['teacher_name'] ?: '—') ?></strong>
                            <span class="score"><?= (int) $row['score'] ?>%</span>
                            <small><?= e($row['reason']) ?></small>
                        </td>
                        <td><?= e($row['alt_text']) ?></td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            <?php if ($rows === []): ?><tr><td colspan="4" class="empty">ยังไม่มีรายวิชา</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <section class="card">
        <header class="card-head"><strong>ภาระสอนหลังแบ่งรายวิชา</strong></header>
        <?php foreach ($loads as $load): ?>
            <div class="load-row">
                <div><span><?= e($load['name']) ?></span><em style="color: <?= e($load['fg']) ?>"><?= (int) $load['hrs'] ?>/<?= (int) $load['max'] ?> ชม.</em></div>
                <span class="bar"><span style="width: <?= (int) $load['pct'] ?>%; background: <?= e($load['bar']) ?>"></span></span>
            </div>
        <?php endforeach; ?>
        <?php if ($loads === []): ?><p class="empty">ยังไม่มีครูผู้สอน</p><?php endif; ?>
    </section>
</div>
