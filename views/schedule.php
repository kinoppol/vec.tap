<?php if ($groups === []): ?>
    <div class="banner warn">สถานศึกษานี้ยังไม่มีกลุ่มผู้เรียน นำเข้าได้ที่ข้อมูลพื้นฐาน</div>
<?php elseif ($model): ?>
<div class="card toolbar">
    <form method="get" action="<?= e(url('/schedule')) ?>">
        <select name="group" onchange="this.form.submit()">
            <?php foreach ($groups as $group): ?>
                <option value="<?= (int) $group['id'] ?>" <?= (int) $group['id'] === (int) $model['group']['id'] ? 'selected' : '' ?>><?= e($group['name']) ?> (<?= (int) $group['student_count'] ?> คน)</option>
            <?php endforeach; ?>
        </select>
    </form>
    <div class="legend">
        <span><i class="swatch manual"></i>ลงด้วยมือ (ล็อก)</span>
        <span><i class="swatch ai"></i>AI จัดให้</span>
        <span><i class="swatch warn"></i>ขัดข้อแนะนำ</span>
        <span><i class="swatch break"></i>พัก / นอกเวลา</span>
    </div>
    <div class="spacer"></div>
    <form method="post" action="<?= e(url('/schedule')) ?>" class="inline">
        <?= Csrf::field() ?>
        <input type="hidden" name="group_id" value="<?= (int) $model['group']['id'] ?>">
        <button class="btn" name="action" value="reset" <?= $model['can_edit'] ? '' : 'disabled' ?>><i class="bi bi-arrow-counterclockwise"></i> เริ่มใหม่</button>
        <button class="btn btn-primary" name="action" value="run" <?= $model['can_edit'] ? '' : 'disabled' ?>><i class="bi bi-stars"></i> ให้ AI จัดตารางตามนโยบาย</button>
    </form>
</div>
<?php if (!$model['can_edit']): ?>
    <div class="banner warn"><i class="bi bi-info-circle"></i> บทบาทครูผู้สอนดูตารางได้อย่างเดียว หากต้องการเปลี่ยนคาบ ให้ติดต่อผู้จัดตาราง งานพัฒนาหลักสูตรการเรียนการสอน</div>
<?php endif; ?>
<div class="schedule-layout">
    <form method="post" action="<?= e(url('/schedule')) ?>" class="card timetable-card">
        <?= Csrf::field() ?>
        <input type="hidden" name="group_id" value="<?= (int) $model['group']['id'] ?>">
        <div class="timetable-scroll"><div class="timetable">
            <?php foreach ($model['periods'] as $period): ?>
                <div class="period-head" style="grid-column: <?= (int) $period['column'] ?>; grid-row: 1"><strong>คาบ <?= (int) $period['no'] ?></strong><span><?= e($period['time']) ?></span></div>
            <?php endforeach; ?>
            <?php foreach (ScheduleEngine::DAYS as $index => $day): ?>
                <div class="day-head" style="grid-row: <?= $index + 2 ?>"><?= e($day) ?></div>
            <?php endforeach; ?>
            <?php foreach ($model['cells'] as $cell): ?>
                <button class="cell <?= $cell['blocked'] ? 'is-blocked' : '' ?> <?= $cell['picked'] ? 'is-picked' : '' ?>"
                    style="grid-column: <?= (int) $cell['column'] ?>; grid-row: <?= (int) $cell['row'] ?>"
                    name="action" value="pick:<?= (int) $cell['day'] ?>:<?= (int) $cell['period'] ?>"
                    <?= ($cell['blocked'] || $cell['occupied'] || !$model['can_edit']) ? 'disabled' : '' ?>></button>
            <?php endforeach; ?>
            <?php foreach ($model['blocks'] as $block): ?>
                <?php if ($block['lunch']): ?>
                    <div class="block lunch" style="grid-column: <?= e($block['column']) ?>; grid-row: <?= e($block['row']) ?>">
                        <em><?= e($block['code']) ?></em><strong><?= e($block['name']) ?></strong><small><?= e($block['meta']) ?></small>
                    </div>
                <?php else:
                    $tone = $block['warning'] !== '' ? 'warn' : ($block['manual'] ? 'manual' : 'ai');
                    ?>
                    <button class="block <?= e($tone) ?> <?= $block['selected'] ? 'selected' : '' ?>"
                        style="grid-column: <?= e($block['column']) ?>; grid-row: <?= e($block['row']) ?>"
                        name="action" value="select:<?= (int) $block['entry_id'] ?>">
                        <em><i class="bi <?= $block['manual'] ? 'bi-lock-fill' : ($block['warning'] !== '' ? 'bi-exclamation-triangle-fill' : 'bi-stars') ?>"></i> <?= e($block['code']) ?></em>
                        <strong><?= e($block['name']) ?></strong>
                        <small><?= e($block['meta']) ?></small>
                    </button>
                <?php endif; ?>
            <?php endforeach; ?>
        </div></div>
        <p class="hint"><i class="bi bi-hand-index"></i> คลิกช่องว่างเพื่อลงรายวิชาด้วยมือ · คลิกรายวิชาเพื่อดูรายละเอียด ล็อก หรือลบ</p>

        <div class="side">
            <?php if ($model['show_report']): ?>
                <section class="card report">
                    <header><i class="bi bi-stars"></i> ผลการจัดตารางโดย AI</header>
                    <p>ลงได้ <?= (int) $model['placed'] ?>/<?= (int) $model['need'] ?> ชั่วโมง · ยังลงไม่ได้ <?= count($model['unplaced']) ?> รายวิชา</p>
                    <?php foreach ($model['unplaced'] as $item): ?>
                        <div class="unplaced"><strong><?= e($item['name']) ?> · ขาด <?= (int) $item['left'] ?> ชม.</strong><span><?= e($item['reason']) ?></span></div>
                    <?php endforeach; ?>
                    <?php if ($model['suggestions']): ?><h3>ข้อแนะนำเพื่อให้จัดตารางเสร็จ</h3><?php endif; ?>
                    <?php foreach ($model['suggestions'] as $suggestion): ?>
                        <article class="suggestion">
                            <div><span class="tag" style="background: <?= e($suggestion['tag_bg']) ?>; color: <?= e($suggestion['tag_fg']) ?>"><?= e($suggestion['tag']) ?></span> <small><?= e($suggestion['effect']) ?></small></div>
                            <p><?= e($suggestion['text']) ?></p>
                            <button class="btn btn-line" name="action" value="apply:<?= e($suggestion['key']) ?>" <?= $model['can_edit'] ? '' : 'disabled' ?>>ใช้ข้อแนะนำนี้</button>
                        </article>
                    <?php endforeach; ?>
                </section>
            <?php endif; ?>
            <?php if ($model['show_done']): ?>
                <div class="banner ok"><i class="bi bi-check-circle-fill"></i> <span><strong>ลงครบ <?= (int) $model['placed'] ?>/<?= (int) $model['need'] ?> ชั่วโมง</strong><small><?= e($model['done_note']) ?></small></span></div>
            <?php endif; ?>
            <?php if ($model['pick']): ?>
                <section class="card pick">
                    <header><strong>ลงด้วยมือ · <?= e($model['pick']['label']) ?></strong>
                        <button name="action" value="clear_pick" aria-label="ปิด"><i class="bi bi-x-lg"></i></button>
                    </header>
                    <?php foreach ($model['pick']['options'] as $option): ?>
                        <button class="choice" name="action" value="add:<?= (int) $option['subject_id'] ?>"><span><?= e($option['name']) ?></span><small>เหลือ <?= (int) $option['left'] ?> ชม.</small></button>
                    <?php endforeach; ?>
                    <?php if ($model['pick']['options'] === []): ?><p class="empty">ทุกรายวิชาลงครบชั่วโมงแล้ว</p><?php endif; ?>
                </section>
            <?php endif; ?>
            <?php if ($model['selected']): $selected = $model['selected']; ?>
                <section class="card pick">
                    <header><em><?= e($selected['code']) ?></em>
                        <button name="action" value="clear_select" aria-label="ปิด"><i class="bi bi-x-lg"></i></button>
                    </header>
                    <strong><?= e($selected['name']) ?></strong>
                    <dl class="meta">
                        <dt>ท-ป-น</dt><dd><?= e($selected['tpn']) ?></dd>
                        <dt>เวลา</dt><dd><?= e($selected['time']) ?></dd>
                        <dt>ครู</dt><dd><?= e($selected['teacher']) ?></dd>
                        <dt>ห้อง</dt><dd><?= e($selected['room']) ?></dd>
                        <dt>สถานะ</dt><dd><?= e($selected['kind']) ?></dd>
                    </dl>
                    <?php if ($selected['warn'] !== ''): ?><p class="warn-note"><?= e($selected['warn']) ?></p><?php endif; ?>
                    <div class="row-actions">
                        <button class="btn" name="action" value="toggle:<?= (int) $selected['id'] ?>" <?= $model['can_edit'] ? '' : 'disabled' ?>><i class="bi <?= $selected['locked'] ? 'bi-unlock' : 'bi-lock' ?>"></i> <?= $selected['locked'] ? 'ปลดล็อก' : 'ล็อกไว้' ?></button>
                        <button class="btn btn-danger" name="action" value="remove:<?= (int) $selected['id'] ?>" <?= $model['can_edit'] ? '' : 'disabled' ?>><i class="bi bi-trash3"></i> ลบออกจากตาราง</button>
                    </div>
                </section>
            <?php endif; ?>
            <section class="card">
                <header class="card-head"><strong>ตรวจชั่วโมงตาม ท-ป-น</strong><em><?= (int) $model['placed'] ?>/<?= (int) $model['need'] ?></em></header>
                <?php foreach ($model['hours'] as $hour): ?>
                    <div class="hour-row">
                        <span><?= e($hour['name']) ?></span>
                        <em><?= e($hour['tpn']) ?></em>
                        <b style="background: <?= e($hour['bg']) ?>; color: <?= e($hour['fg']) ?>"><?= (int) $hour['got'] ?>/<?= (int) $hour['need'] ?></b>
                    </div>
                <?php endforeach; ?>
            </section>
            <?php if ($model['show_compliance']): ?>
                <section class="card">
                    <header class="card-head"><strong>การปฏิบัติตามนโยบาย</strong></header>
                    <?php foreach ($model['compliance'] as $item): ?>
                        <div class="policy-status">
                            <i class="bi <?= $item['ok'] ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill' ?>" style="color: <?= $item['ok'] ? '#198754' : '#C28A17' ?>"></i>
                            <span>ข้อ <?= (int) $item['no'] ?> · <?= e($item['short']) ?><small><?= e($item['status']) ?></small></span>
                        </div>
                    <?php endforeach; ?>
                </section>
            <?php endif; ?>
        </div>
    </form>
</div>
<?php endif; ?>
