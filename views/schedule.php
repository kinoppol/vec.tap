<?php if ($groups === []): ?>
    <div class="banner warn"><?= !empty($scheduleLimited) ? 'ยังไม่ได้รับมอบหมายให้จัดตารางกลุ่มใด ให้ผู้ดูแลสถานศึกษามอบหมายที่ข้อมูลพื้นฐาน' : 'สถานศึกษานี้ยังไม่มีกลุ่มผู้เรียน นำเข้าได้ที่ข้อมูลพื้นฐาน' ?></div>
<?php elseif ($model): ?>
<div class="card toolbar">
    <?php
    $current = $model['group'];
    $currentCode = trim((string) ($current['rms_group_code'] ?? ''));
    $currentLabel = $current['name'] . ($currentCode !== '' && $currentCode !== $current['name'] ? ' · ' . $currentCode : '');
    ?>
    <div class="group-filter" data-group-filter>
        <input type="search" value="<?= e($currentLabel) ?>" placeholder="พิมพ์ชื่อหรือรหัสกลุ่มเรียน" autocomplete="off" aria-label="กรองกลุ่มผู้เรียน" aria-expanded="false" aria-controls="group-filter-list">
        <div class="group-filter-list" id="group-filter-list" data-group-list>
            <?php foreach ($groups as $group):
                $code = trim((string) ($group['rms_group_code'] ?? ''));
                $search = trim($group['name'] . ' ' . $code);
                ?>
                <a href="<?= e(url('/schedule?group=' . (int) $group['id'])) ?>" data-group-id="<?= (int) $group['id'] ?>" data-search="<?= e($search) ?>" <?= (int) $group['id'] === (int) $current['id'] ? 'aria-current="true"' : '' ?>>
                    <strong><?= e($group['name']) ?></strong>
                    <?php if ($code !== ''): ?><small><?= e($code) ?></small><?php endif; ?>
                    <em><?= (int) $group['student_count'] ?> คน</em>
                </a>
            <?php endforeach; ?>
            <p class="empty" data-group-empty hidden>ไม่พบกลุ่มที่ตรงกับคำค้น</p>
        </div>
    </div>
    <div class="legend">
        <span><i class="swatch manual"></i>ลงด้วยมือ (ล็อก)</span>
        <span><i class="swatch ai"></i>AI จัดให้</span>
        <span><i class="swatch warn"></i>ขัดข้อแนะนำ</span>
        <span><i class="swatch break"></i>พัก / นอกเวลา</span>
    </div>
    <div class="spacer"></div>
    <a class="btn" href="<?= e(url('/print?kind=group&id=' . (int) $model['group']['id'])) ?>"><i class="bi bi-printer"></i> พิมพ์ตารางเรียน</a>
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
<div class="schedule-layout" data-schedule data-url="<?= e(url('/schedule')) ?>" data-csrf="<?= e(Csrf::token()) ?>" data-group="<?= (int) $model['group']['id'] ?>" data-edit="<?= $model['can_edit'] ? '1' : '0' ?>">
    <p class="warn-note" data-schedule-note hidden></p>
    <?php require app_root() . '/views/partials/schedule_board.php'; ?>
</div>
<script>
(() => {
    const board = document.querySelector("[data-schedule]");
    if (!board) return;
    const note = board.querySelector("[data-schedule-note]");
    let busy = false;
    let dragged = false;

    const showNote = (text) => {
        if (!note) return;
        note.hidden = text === "";
        note.textContent = text;
    };
    const post = async (action) => {
        if (busy) return;
        busy = true;
        showNote("");
        const body = new FormData();
        body.set("_csrf", board.dataset.csrf || "");
        body.set("group_id", board.dataset.group || "");
        body.set("action", action);
        const assign = board.querySelector("[data-assign]");
        if (assign && action === (assign.dataset.assign || "")) {
            body.set("teacher_name", assign.querySelector("[name=teacher_name]")?.value || "");
            body.set("room_code", assign.querySelector("[name=room_code]")?.value || "");
        }
        try {
            const response = await fetch(board.dataset.url || "", {
                method: "POST",
                body,
                headers: { Accept: "application/json" },
            });
            const data = await response.json();
            if (!data.ok) throw new Error(data.message || "บันทึกไม่สำเร็จ");
            const form = board.querySelector("form");
            if (form && data.html) {
                const top = window.scrollY;
                form.outerHTML = data.html;
                window.scrollTo(0, top);
            }
        } catch (error) {
            showNote(error.message || "บันทึกไม่สำเร็จ");
        } finally {
            busy = false;
        }
    };
    const cellAt = (x, y) => {
        const cells = [...board.querySelectorAll(".cell")];
        return cells.find((cell) => {
            const box = cell.getBoundingClientRect();
            return x >= box.left && x <= box.right && y >= box.top && y <= box.bottom;
        }) || null;
    };
    const clearPreview = () => {
        board.querySelectorAll(".cell.is-target, .cell.is-bad").forEach((cell) => {
            cell.classList.remove("is-target", "is-bad");
        });
    };
    const mark = (day, start, length) => {
        clearPreview();
        for (let period = start; period < start + length; period += 1) {
            const cell = board.querySelector('.cell[data-day="' + day + '"][data-period="' + period + '"]');
            if (!cell) continue;
            cell.classList.add(cell.dataset.blocked === "1" ? "is-bad" : "is-target");
        }
    };

    board.addEventListener("keydown", (event) => {
        if (event.key !== "Enter" || !event.target.closest("[data-assign-field]")) return;
        const assign = event.target.closest("[data-assign]");
        if (!assign) return;
        event.preventDefault();
        post(assign.dataset.assign || "");
    });

    board.addEventListener("click", (event) => {
        const control = event.target.closest("[data-action]");
        if (!control || control.closest(".grip") || dragged) {
            dragged = false;
            return;
        }
        if (control.tagName === "BUTTON" && control.name === "action") return;
        event.preventDefault();
        post(control.dataset.action || "");
    });

    board.addEventListener("pointerdown", (event) => {
        if (board.dataset.edit !== "1" || event.button !== 0) return;
        const grip = event.target.closest("[data-grip]");
        const block = event.target.closest("[data-entry]");
        if (!block) return;
        event.preventDefault();
        const origin = {
            id: block.dataset.entry,
            day: Number(block.dataset.day),
            start: Number(block.dataset.start),
            length: Number(block.dataset.length),
        };
        const mode = grip ? grip.dataset.grip : "move";
        const startX = event.clientX;
        const startY = event.clientY;
        let moved = false;
        block.classList.add("dragging");
        const onMove = (ev) => {
            if (Math.abs(ev.clientX - startX) + Math.abs(ev.clientY - startY) > 4) moved = true;
            const cell = cellAt(ev.clientX, ev.clientY);
            if (!cell) return;
            const period = Number(cell.dataset.period);
            const day = Number(cell.dataset.day);
            if (mode === "move") {
                mark(day, period, origin.length);
            } else if (mode === "end" && day === origin.day) {
                mark(origin.day, origin.start, Math.max(1, period - origin.start + 1));
            } else if (mode === "start" && day === origin.day) {
                const end = origin.start + origin.length - 1;
                const next = Math.min(period, end);
                mark(origin.day, next, end - next + 1);
            }
        };
        const onUp = (ev) => {
            window.removeEventListener("pointermove", onMove);
            window.removeEventListener("pointerup", onUp);
            block.classList.remove("dragging");
            clearPreview();
            if (!moved) return;
            dragged = true;
            const cell = cellAt(ev.clientX, ev.clientY);
            if (!cell) return;
            const period = Number(cell.dataset.period);
            const day = Number(cell.dataset.day);
            if (mode === "move") {
                post("move:" + origin.id + ":" + day + ":" + period);
                return;
            }
            if (day !== origin.day) return;
            if (mode === "end") {
                const length = Math.max(1, period - origin.start + 1);
                post("resize:" + origin.id + ":" + origin.day + ":" + origin.start + ":" + length);
                return;
            }
            const end = origin.start + origin.length - 1;
            const next = Math.min(period, end);
            post("resize:" + origin.id + ":" + origin.day + ":" + next + ":" + (end - next + 1));
        };
        window.addEventListener("pointermove", onMove);
        window.addEventListener("pointerup", onUp);
    });
})();
</script>
<?php endif; ?>
<script>
(() => {
    const root = document.querySelector("[data-group-filter]");
    if (!root) return;
    const input = root.querySelector("input");
    const list = root.querySelector("[data-group-list]");
    if (!input || !list) return;
    const items = [...list.querySelectorAll("[data-group-id]")];
    const empty = list.querySelector("[data-group-empty]");
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
