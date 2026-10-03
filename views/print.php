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
        <?php if ($kind === 'teacher'): ?>
            <?php
            $selectedLabel = '';
            if (!$showAll) {
                foreach ($options as $option) {
                    if ((int) $option['id'] === $selectedId) {
                        $selectedLabel = (string) $option['label'];
                        break;
                    }
                }
            }
            ?>
            <div class="group-filter" data-teacher-filter>
                <input type="search" value="<?= e($selectedLabel) ?>" placeholder="พิมพ์ชื่อครู" autocomplete="off" aria-label="ค้นชื่อครู" aria-expanded="false" aria-controls="teacher-filter-list">
                <div class="group-filter-list" id="teacher-filter-list" data-teacher-list>
                    <?php foreach ($options as $option): ?>
                        <a href="<?= e(url('/print?kind=teacher' . $termQuery . '&id=' . (int) $option['id'])) ?>" data-search="<?= e((string) $option['label']) ?>" <?= !$showAll && (int) $option['id'] === $selectedId ? 'aria-current="true"' : '' ?>>
                            <strong><?= e((string) $option['label']) ?></strong>
                        </a>
                    <?php endforeach; ?>
                    <p class="empty" data-teacher-empty hidden>ไม่พบครูที่ตรงกับคำค้น</p>
                </div>
            </div>
        <?php else: ?>
        <form method="get" action="<?= e(url('/print')) ?>" class="inline">
            <input type="hidden" name="kind" value="<?= e($kind) ?>">
            <?php if ($termId > 0): ?><input type="hidden" name="term" value="<?= (int) $termId ?>"><?php endif; ?>
            <select name="id" onchange="this.form.submit()" aria-label="เลือกรายการที่จะพิมพ์">
                <?php foreach ($options as $option): ?>
                    <option value="<?= (int) $option['id'] ?>" <?= (int) $option['id'] === $selectedId ? 'selected' : '' ?>><?= e($option['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php endif; ?>
        <div class="print-actions">
        <?php if ($showAll): ?>
            <a class="btn" href="<?= e(url('/print?' . $kindQuery . '&id=' . $selectedId)) ?>">แสดงทีละรายการ</a>
        <?php else: ?>
            <a class="btn" href="<?= e(url('/print?' . $kindQuery . '&all=1')) ?>">แสดงทั้งหมด</a>
        <?php endif; ?>
        <button class="btn btn-primary" type="button" onclick="window.print()"><i class="bi bi-printer"></i> พิมพ์</button>
        </div>
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
                    <?php
                    $periodNos = array_keys($sheet['grid'][0] ?? []);
                    if ($periodNos === []) {
                        $periodNos = range(1, 9);
                    }
                    foreach ($periodNos as $period): ?>
                        <th>คาบ <?= $period ?><small><?= e(ScheduleEngine::TIMES[$period - 1]) ?>–<?= e(ScheduleEngine::TIMES[$period]) ?></small></th>
                    <?php endforeach; ?>
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
<?php if ($kind === 'teacher' && $options !== []): ?>
<script>
(() => {
    const root = document.querySelector("[data-teacher-filter]");
    if (!root) return;
    const input = root.querySelector("input");
    const list = root.querySelector("[data-teacher-list]");
    if (!input || !list) return;
    const items = [...list.querySelectorAll("[data-search]")];
    const empty = list.querySelector("[data-teacher-empty]");
    const current = input.value;
    let active = -1;

    const shown = () => items.filter((item) => !item.hidden);
    const place = () => {
        const box = input.getBoundingClientRect();
        list.style.position = "fixed";
        list.style.zIndex = "80";
        list.style.left = box.left + "px";
        list.style.top = (box.bottom + 4) + "px";
        list.style.width = Math.max(box.width, 280) + "px";
    };
    const paint = () => {
        items.forEach((item) => item.classList.remove("on"));
        const rows = shown();
        if (rows[active]) {
            rows[active].classList.add("on");
            rows[active].scrollIntoView({ block: "nearest" });
        }
    };
    const open = (query) => {
        const q = query.trim().toLowerCase();
        items.forEach((item) => {
            const hay = (item.dataset.search || "").toLowerCase();
            item.hidden = q !== "" && !hay.includes(q);
        });
        const rows = shown();
        if (empty) empty.hidden = rows.length > 0;
        active = rows.findIndex((item) => item.getAttribute("aria-current") === "true");
        if (active < 0 && q !== "" && rows.length > 0) active = 0;
        list.classList.add("is-open");
        list.style.display = "block";
        input.setAttribute("aria-expanded", "true");
        place();
        paint();
    };
    const close = (restore) => {
        list.classList.remove("is-open");
        list.style.display = "none";
        input.setAttribute("aria-expanded", "false");
        active = -1;
        if (restore) input.value = current;
    };

    input.addEventListener("focus", () => {
        input.select();
        open("");
    });
    input.addEventListener("input", () => open(input.value));
    input.addEventListener("search", () => open(input.value));
    input.addEventListener("keydown", (event) => {
        const rows = shown();
        if (event.key === "ArrowDown" || event.key === "ArrowUp") {
            event.preventDefault();
            if (!list.classList.contains("is-open")) open(input.value === current ? "" : input.value);
            const next = shown();
            if (next.length === 0) return;
            active = event.key === "ArrowDown" ? (active + 1) % next.length : (active - 1 + next.length) % next.length;
            paint();
        } else if (event.key === "Enter") {
            const target = rows[active] || (rows.length === 1 ? rows[0] : null);
            if (!target) return;
            event.preventDefault();
            window.location = target.href;
        } else if (event.key === "Escape") {
            close(true);
            input.blur();
        }
    });
    document.addEventListener("pointerdown", (event) => {
        if (root.contains(event.target) || list.contains(event.target)) return;
        close(true);
    });
    window.addEventListener("resize", place);
    window.addEventListener("scroll", place, true);
})();
</script>
<?php endif; ?>
