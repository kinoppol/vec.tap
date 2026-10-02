<?php
$kinds = [
    'group' => 'ตารางเรียน',
    'teacher' => 'ตารางสอน',
    'room' => 'ตารางใช้ห้องเรียน',
];
$termQuery = $termId > 0 ? '&term=' . (int) $termId : '';
$kindQuery = 'kind=' . rawurlencode($kind) . $termQuery;
?>
<div class="toolbar print-tools">
    <?php if ($terms !== []): ?>
        <form method="get" action="<?= e(url('/print')) ?>" class="term-switch">
            <input type="hidden" name="kind" value="<?= e($kind) ?>">
            <label>ภาคเรียน
                <select name="term" onchange="this.form.submit()" aria-label="ภาคเรียนที่พิมพ์">
                    <?php foreach ($terms as $item): ?>
                        <option value="<?= (int) $item['id'] ?>" <?= (int) $item['id'] === (int) $termId ? 'selected' : '' ?>><?= e($item['label']) ?><?= (int) $item['is_current'] === 1 ? ' (ปัจจุบัน)' : '' ?> · <?= (int) $item['group_count'] ?> กลุ่ม</option>
                    <?php endforeach; ?>
                </select>
            </label>
        </form>
    <?php endif; ?>
    <div class="tabs">
        <?php foreach ($kinds as $key => $label): ?>
            <a class="tab <?= $kind === $key ? 'on' : '' ?>" href="<?= e(url('/print?kind=' . $key . $termQuery)) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </div>
    <?php if ($options !== []): ?>
        <form method="get" action="<?= e(url('/print')) ?>" class="inline">
            <input type="hidden" name="kind" value="<?= e($kind) ?>">
            <?php if ($termId > 0): ?><input type="hidden" name="term" value="<?= (int) $termId ?>"><?php endif; ?>
            <select name="id" onchange="this.form.submit()" aria-label="เลือกรายการที่จะพิมพ์">
                <?php foreach ($options as $option): ?>
                    <option value="<?= (int) $option['id'] ?>" <?= (int) $option['id'] === $selectedId ? 'selected' : '' ?>><?= e($option['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php if ($showAll): ?>
            <a class="btn" href="<?= e(url('/print?' . $kindQuery . '&id=' . $selectedId)) ?>">แสดงทีละรายการ</a>
        <?php else: ?>
            <a class="btn" href="<?= e(url('/print?' . $kindQuery . '&all=1')) ?>">แสดงทั้งหมด</a>
        <?php endif; ?>
        <button class="btn btn-primary" type="button" onclick="window.print()"><i class="bi bi-printer"></i> พิมพ์</button>
    <?php endif; ?>
</div>
<?php if ($showAll): ?><p class="hint print-tools">กำลังแสดงเฉพาะรายการที่มีคาบเรียนแล้ว กดพิมพ์เพื่อสั่งพิมพ์ทั้งหมด</p><?php endif; ?>
<?php if ($options === []): ?>
    <p class="empty">ยังไม่มีข้อมูลสำหรับพิมพ์ตารางนี้</p>
<?php elseif ($sheets === []): ?>
    <p class="empty">ยังไม่มีคาบเรียนให้พิมพ์</p>
<?php endif; ?>
<?php foreach ($sheets as $sheet): ?>
    <article class="print-sheet">
        <header>
            <h2><?= e($sheet['title']) ?></h2>
            <p><?= e(trim($schoolName . ($termLabel !== '' ? ' · ' . $termLabel : ''))) ?><?php if ($sheet['note'] !== ''): ?> · <?= e($sheet['note']) ?><?php endif; ?></p>
        </header>
        <table class="print-table">
            <thead>
                <tr>
                    <th>วัน</th>
                    <?php for ($period = 1; $period <= 10; $period++): ?>
                        <th>คาบ <?= $period ?><small><?= e(ScheduleEngine::TIMES[$period - 1]) ?>–<?= e(ScheduleEngine::TIMES[$period]) ?></small></th>
                    <?php endfor; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach (ScheduleEngine::DAYS as $day => $dayName): ?>
                <tr>
                    <th><?= e($dayName) ?></th>
                    <?php foreach (ScheduleEngine::printDaySpans($sheet['grid'][$day]) as $cell): ?>
                        <?php $items = $cell['items']; ?>
                        <td class="<?= $items === [] && (int) $sheet['lunch'] === (int) $cell['period'] ? 'is-lunch' : '' ?><?= (int) $cell['span'] > 1 ? ' is-merged' : '' ?>"<?= (int) $cell['span'] > 1 ? ' colspan="' . (int) $cell['span'] . '"' : '' ?>>
                            <?php if ($items === [] && (int) $sheet['lunch'] === (int) $cell['period']): ?>
                                <span class="print-break">พัก</span>
                            <?php endif; ?>
                            <?php foreach ($items as $item): ?>
                                <span class="print-lesson">
                                    <strong><?= e((string) $item['subject_code']) ?></strong>
                                    <?= e((string) $item['subject_name']) ?>
                                    <?php if ($kind === 'group'): ?>
                                        <small><?= e((string) ($item['teacher_name'] ?: 'ยังไม่ระบุครู')) ?> · <?= e((string) ($item['room_code'] ?: 'ยังไม่ระบุห้อง')) ?></small>
                                    <?php elseif ($kind === 'teacher'): ?>
                                        <small><?= e((string) $item['group_name']) ?> · <?= e((string) ($item['room_code'] ?: 'ยังไม่ระบุห้อง')) ?></small>
                                    <?php else: ?>
                                        <small><?= e((string) $item['group_name']) ?> · <?= e((string) ($item['teacher_name'] ?: 'ยังไม่ระบุครู')) ?></small>
                                    <?php endif; ?>
                                </span>
                            <?php endforeach; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </article>
<?php endforeach; ?>
