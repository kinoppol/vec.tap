<form method="post" action="<?= e(url('/schedule')) ?>" class="card timetable-card">
    <?= Csrf::field() ?>
    <input type="hidden" name="group_id" value="<?= (int) $model['group']['id'] ?>">
    <div class="timetable-scroll"><div class="timetable" data-grid style="--periods: <?= count($model['periods']) ?>">
        <?php foreach ($model['periods'] as $period): ?>
            <div class="period-head" style="grid-column: <?= (int) $period['column'] ?>; grid-row: 1"><strong>คาบ <?= (int) $period['no'] ?></strong><span><?= e($period['time']) ?></span></div>
        <?php endforeach; ?>
        <?php foreach (ScheduleEngine::DAYS as $index => $day): ?>
            <div class="day-head" style="grid-row: <?= $index + 2 ?>"><?= e($day) ?></div>
        <?php endforeach; ?>
        <?php foreach ($model['cells'] as $cell): ?>
            <?php $twinMerged = !empty($cell['twin_merged']); ?>
            <button class="cell <?= $cell['blocked'] ? 'is-blocked' : '' ?> <?= $cell['picked'] ? 'is-picked' : '' ?> <?= ($cell['twin'] ?? '') !== '' && !$twinMerged ? 'is-twin' : '' ?> <?= $twinMerged ? 'is-twin-run' : '' ?>" type="button"
                style="grid-column: <?= (int) $cell['column'] ?>; grid-row: <?= (int) $cell['row'] ?>"
                data-day="<?= (int) $cell['day'] ?>" data-period="<?= (int) $cell['period'] ?>" data-blocked="<?= $cell['blocked'] ? '1' : '0' ?>"
                data-action="pick:<?= (int) $cell['day'] ?>:<?= (int) $cell['period'] ?>"
                <?= ($cell['twin_title'] ?? '') !== '' ? 'title="' . e((string) $cell['twin_title']) . '"' : '' ?>
                <?= ($cell['blocked'] || $cell['occupied'] || !$model['can_edit']) ? 'disabled' : '' ?>><?php if (($cell['twin'] ?? '') !== '' && !$twinMerged): ?><small><?= e((string) $cell['twin']) ?></small><?php endif; ?></button>
        <?php endforeach; ?>
        <?php foreach ($model['twin_spans'] ?? [] as $span): ?>
            <div class="twin-span" style="grid-column: <?= e($span['column']) ?>; grid-row: <?= (int) $span['row'] ?>" title="<?= e($span['title']) ?>"><small><?= e($span['label']) ?></small></div>
        <?php endforeach; ?>
        <?php foreach ($model['blocks'] as $block): ?>
            <?php if ($block['lunch']): ?>
                <div class="block lunch <?= !empty($block['soft']) ? 'soft' : '' ?>" style="grid-column: <?= e($block['column']) ?>; grid-row: <?= e($block['row']) ?>">
                    <em><?= e($block['code']) ?></em><strong><?= e($block['name']) ?></strong><small><?= e($block['meta']) ?></small>
                </div>
            <?php else:
                $tone = $block['warning'] !== '' ? 'warn' : ($block['manual'] ? 'manual' : 'ai');
                $column = (string) $block['column'];
                $start = 1;
                $length = 1;
                if (preg_match('/^(\d+) \/ span (\d+)$/', $column, $match)) {
                    $start = (int) $match[1] - 1;
                    $length = (int) $match[2];
                }
                $day = (int) $block['row'] - 2;
                ?>
                <div class="block <?= e($tone) ?> <?= $block['selected'] ? 'selected' : '' ?>"
                    style="grid-column: <?= e($column) ?>; grid-row: <?= e($block['row']) ?>"
                    data-entry="<?= (int) $block['entry_id'] ?>" data-day="<?= $day ?>" data-start="<?= $start ?>" data-length="<?= $length ?>"
                    data-action="select:<?= (int) $block['entry_id'] ?>" role="button" tabindex="0">
                    <?php if ($model['can_edit']): ?><span class="grip grip-start" data-grip="start"></span><?php endif; ?>
                    <em><i class="bi <?= $block['manual'] ? 'bi-lock-fill' : ($block['warning'] !== '' ? 'bi-exclamation-triangle-fill' : 'bi-stars') ?>"></i> <?= e($block['code']) ?></em>
                    <strong><?= e($block['name']) ?></strong>
                    <small><?= e($block['meta']) ?></small>
                    <?php if ($model['can_edit']): ?><span class="grip grip-end" data-grip="end"></span><?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div></div>
    <p class="hint"><i class="bi bi-hand-index"></i> คลิกช่องว่างเพื่อลงรายวิชา · ลากรายวิชาไปวางที่คาบเริ่ม · ลากขอบซ้ายหรือขวาเพื่อย่อขยาย<?php if (($model['twin_names'] ?? []) !== []): ?> · สีเหลืองคือคาบที่กลุ่มแฝดลงไว้แล้ว (<?= e(implode(', ', $model['twin_names'])) ?>) และชั่วโมงของรหัสเดียวกันถูกหักจากชั่วโมงคงเหลือ<?php endif; ?></p>

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
                    <button type="button" data-action="clear_pick" aria-label="ปิด"><i class="bi bi-x-lg"></i></button>
                </header>
                <?php foreach ($model['pick']['options'] as $option): ?>
                    <button class="choice" type="button" data-action="add:<?= (int) $option['subject_id'] ?>"><span><?= e($option['name']) ?></span><small>เหลือ <?= (int) $option['left'] ?> ชม.</small></button>
                <?php endforeach; ?>
                <?php if ($model['pick']['options'] === []): ?><p class="empty">ทุกรายวิชาลงครบชั่วโมงแล้ว</p><?php endif; ?>
            </section>
        <?php endif; ?>
        <?php if ($model['selected']): $selected = $model['selected']; ?>
            <section class="card pick">
                <header><em><?= e($selected['code']) ?></em>
                    <button type="button" data-action="clear_select" aria-label="ปิด"><i class="bi bi-x-lg"></i></button>
                </header>
                <strong><?= e($selected['name']) ?></strong>
                <dl class="meta">
                    <dt>ท-ป-น</dt><dd><?= e($selected['tpn']) ?></dd>
                    <dt>เวลา</dt><dd><?= e($selected['time']) ?></dd>
                    <?php if (!$model['can_edit']): ?>
                        <dt>ครู</dt><dd><?= e($selected['teacher']) ?></dd>
                        <dt>ห้อง</dt><dd><?= e($selected['room']) ?></dd>
                    <?php endif; ?>
                    <dt>สถานะ</dt><dd><?= e($selected['kind']) ?></dd>
                </dl>
                <?php if ($model['can_edit']): ?>
                    <div class="assign" data-assign="assign:<?= (int) $selected['id'] ?>">
                        <label>ครู<input name="teacher_name" list="schedule-teachers" value="<?= e($selected['teacher_name']) ?>" data-assign-field placeholder="พิมพ์ชื่อครู" autocomplete="off"></label>
                        <label>ห้อง<input name="room_code" list="schedule-rooms" value="<?= e($selected['room_code']) ?>" data-assign-field placeholder="พิมพ์รหัสห้อง" autocomplete="off"></label>
                        <button class="btn btn-primary" type="button" data-action="assign:<?= (int) $selected['id'] ?>"><i class="bi bi-check2"></i> บันทึกครูและห้อง</button>
                        <datalist id="schedule-teachers">
                            <?php foreach ($model['teacher_names'] as $name): ?><option value="<?= e($name) ?>"></option><?php endforeach; ?>
                        </datalist>
                        <datalist id="schedule-rooms">
                            <?php foreach ($model['room_codes'] as $code): ?><option value="<?= e($code) ?>"></option><?php endforeach; ?>
                        </datalist>
                    </div>
                <?php endif; ?>
                <?php if ($selected['warn'] !== ''): ?><p class="warn-note"><?= e($selected['warn']) ?></p><?php endif; ?>
                <div class="row-actions">
                    <button class="btn" type="button" data-action="toggle:<?= (int) $selected['id'] ?>" <?= $model['can_edit'] ? '' : 'disabled' ?>><i class="bi <?= $selected['locked'] ? 'bi-unlock' : 'bi-lock' ?>"></i> <?= $selected['locked'] ? 'ปลดล็อก' : 'ล็อกไว้' ?></button>
                    <button class="btn btn-danger" type="button" data-action="remove:<?= (int) $selected['id'] ?>" <?= $model['can_edit'] ? '' : 'disabled' ?>><i class="bi bi-trash3"></i> ลบออกจากตาราง</button>
                </div>
            </section>
        <?php endif; ?>
        <section class="card">
            <header class="card-head"><strong>ตรวจชั่วโมงตาม ท-ป-น</strong><em><?= (int) $model['placed'] ?>/<?= (int) $model['need'] ?></em></header>
            <?php if ($model['can_edit']): ?><p class="hint">แก้ ท-ป-น แล้วกดบันทึก คาบที่ต้องลงเท่ากับชั่วโมงทฤษฎีบวกปฏิบัติ</p><?php endif; ?>
            <?php foreach ($model['hours'] as $hour): ?>
                <div class="hour-row">
                    <span><?= e($hour['name']) ?></span>
                    <?php if ($model['can_edit']): ?>
                        <span class="tpn-edit" data-hours="hours:<?= (int) $hour['id'] ?>">
                            <input name="theory" type="number" min="0" max="40" value="<?= (int) $hour['theory'] ?>" aria-label="ทฤษฎี" data-hours-field>
                            <input name="practice" type="number" min="0" max="40" value="<?= (int) $hour['practice'] ?>" aria-label="ปฏิบัติ" data-hours-field>
                            <input name="extra" type="number" min="0" max="40" value="<?= (int) $hour['extra'] ?>" aria-label="ศึกษาด้วยตนเอง" data-hours-field>
                            <button type="button" data-action="hours:<?= (int) $hour['id'] ?>">บันทึก</button>
                        </span>
                    <?php else: ?>
                        <em><?= e($hour['tpn']) ?></em>
                    <?php endif; ?>
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
