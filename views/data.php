<?php
$tabs = [
    'teachers' => ['ครูผู้สอน', 'bi-person-badge'],
    'groups' => ['กลุ่มผู้เรียน', 'bi-people'],
    'plans' => ['แผนการเรียน', 'bi-journal-bookmark'],
    'subjects' => ['รายวิชา', 'bi-journal-text'],
    'rooms' => ['อาคารและห้องเรียน', 'bi-building'],
];
?>
<div class="toolbar plain">
    <div class="tabs">
        <?php foreach ($tabs as $key => $meta): ?>
            <a class="tab <?= $tab === $key ? 'on' : '' ?>" href="<?= e(url('/data?tab=' . $key)) ?>"><i class="bi <?= e($meta[1]) ?>"></i> <?= e($meta[0]) ?></a>
        <?php endforeach; ?>
    </div>
    <div class="spacer"></div>
    <?php if (in_array($tab, ['teachers', 'groups', 'subjects'], true)): ?>
        <a class="btn" href="<?= e(url('/data/template?tab=' . $tab)) ?>"><i class="bi bi-file-earmark-arrow-down"></i> ดาวน์โหลดแม่แบบ</a>
        <form method="post" action="<?= e(url('/data')) ?>" enctype="multipart/form-data" class="inline">
            <?= Csrf::field() ?>
            <input type="hidden" name="tab" value="<?= e($tab) ?>">
            <label class="btn btn-primary file-btn"><i class="bi bi-upload"></i> นำเข้า Excel / CSV<input type="file" name="file" accept=".csv,.txt,.xlsx" onchange="this.form.submit()"></label>
        </form>
    <?php endif; ?>
</div>

<?php if ($tab === 'teachers'): ?>
<div class="card table-wrap">
    <table>
        <thead><tr><th>ครูผู้สอน</th><th>แผนก / วุฒิ</th><th>ทักษะการสอน</th><th>ชม.สูงสุด/สัปดาห์</th></tr></thead>
        <tbody>
        <?php foreach ($teachers as $teacher): ?>
            <tr>
                <td><strong><?= e($teacher['name']) ?></strong></td>
                <td><?= e($teacher['dept']) ?><small><?= e($teacher['degree']) ?></small></td>
                <td class="tags"><?php foreach ($teacher['skills'] as $skill): ?><span><?= e($skill) ?></span><?php endforeach; ?></td>
                <td class="mono"><?= (int) $teacher['max_hours'] ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($teachers === []): ?><tr><td colspan="4" class="empty">ยังไม่มีครูในสถานศึกษานี้</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php elseif ($tab === 'groups'): ?>
<div class="card table-wrap">
    <table>
        <thead><tr><th>กลุ่มผู้เรียน</th><th>ระดับ</th><th>ผู้เรียน</th><th>ครูที่ปรึกษา</th><th>ข้อสังเกตขนาดห้อง</th></tr></thead>
        <tbody>
        <?php foreach ($groups as $group): ?>
            <tr>
                <td><strong><?= e($group['name']) ?></strong></td>
                <td><?= e($group['level']) ?></td>
                <td class="mono"><?= (int) $group['student_count'] ?> คน</td>
                <td><?= e($group['advisor_name'] ?: '—') ?></td>
                <td class="tone-<?= e((string) $group['note_tone']) ?>"><?= e($group['note'] ?: '—') ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($groups === []): ?><tr><td colspan="5" class="empty">ยังไม่มีกลุ่มผู้เรียน</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php elseif ($tab === 'plans'): ?>
<div class="plan-grid">
    <?php foreach ($plans as $plan): ?>
        <article class="card plan">
            <small><?= e($plan['term_label']) ?></small>
            <strong><?= e($plan['name']) ?></strong>
            <p><b><?= (int) $plan['subject_count'] ?></b> รายวิชา <b><?= (int) $plan['hours_per_week'] ?></b> ชม./สัปดาห์ <b><?= (int) $plan['credits'] ?></b> หน่วยกิต</p>
            <span>ใช้กับ: <?= e($plan['groups'] ? implode(', ', $plan['groups']) : '—') ?></span>
        </article>
    <?php endforeach; ?>
    <?php if ($plans === []): ?><p class="empty">ยังไม่มีแผนการเรียน</p><?php endif; ?>
</div>
<?php elseif ($tab === 'subjects'): ?>
<div class="card table-wrap">
    <table>
        <thead><tr><th>รหัสวิชา</th><th>ชื่อรายวิชา</th><th>ท</th><th>ป</th><th>น</th><th>ชม./สัปดาห์</th><th>แผน</th></tr></thead>
        <tbody>
        <?php foreach ($subjects as $subject): ?>
            <tr>
                <td class="mono"><?= e($subject['code']) ?></td>
                <td><?= e($subject['name']) ?></td>
                <td><?= (int) $subject['theory'] ?></td>
                <td><?= (int) $subject['practice'] ?></td>
                <td><?= (int) $subject['extra'] ?></td>
                <td><strong><?= (int) $subject['theory'] + (int) $subject['practice'] ?></strong></td>
                <td><?= e($subject['plan_name']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($subjects === []): ?><tr><td colspan="7" class="empty">ยังไม่มีรายวิชา</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php else: ?>
<div class="split">
    <section class="card">
        <header class="card-head"><strong>อาคารเรียนและพิกัด</strong><span class="hint">ระยะเดินจากอาคาร 1</span></header>
        <?php foreach ($buildings as $building): ?>
            <div class="building-row">
                <span><strong><?= e($building['name']) ?></strong><small class="mono"><?= e($building['campus']) ?> · <?= e($building['lat']) ?>, <?= e($building['lng']) ?></small></span>
                <em style="background: <?= e($building['badge_bg']) ?>; color: <?= e($building['badge_fg']) ?>"><?= e($building['dist_label']) ?></em>
            </div>
        <?php endforeach; ?>
        <?php if ($buildings === []): ?><p class="empty">ยังไม่มีอาคาร</p><?php endif; ?>
    </section>
    <div class="stack-cards">
        <section class="card">
            <header class="card-head"><strong>ตำแหน่งอาคาร (ตามพิกัด)</strong></header>
            <div class="map">
                <?php foreach ($buildings as $building): ?>
                    <span style="left: <?= e((string) $building['map_x']) ?>%; top: <?= e((string) $building['map_y']) ?>%">
                        <i style="background: <?= e($building['dot_color']) ?>"></i><?= e($building['short_name']) ?>
                    </span>
                <?php endforeach; ?>
            </div>
            <p class="hint">วิทยาเขตที่ห่างกันมาก ระบบจะเตือนเมื่อคาบติดกันต้องย้ายวิทยาเขต</p>
        </section>
        <section class="card">
            <header class="card-head"><strong>ห้องเรียน</strong></header>
            <?php foreach ($rooms as $room): ?>
                <div class="room-row"><span class="mono"><?= e($room['code']) ?></span><span><?= e($room['room_type']) ?></span><em><?= (int) $room['capacity'] ?> ที่</em></div>
            <?php endforeach; ?>
            <?php if ($rooms === []): ?><p class="empty">ยังไม่มีห้องเรียน</p><?php endif; ?>
        </section>
    </div>
</div>
<?php endif; ?>
