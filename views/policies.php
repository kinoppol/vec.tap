<?php if (!$canEdit): ?>
    <div class="banner warn"><i class="bi bi-lock"></i> การเพิ่ม แก้ไข ลบ หรือเรียงลำดับนโยบายเป็นสิทธิ์ของผู้ดูแลระบบสถานศึกษา บทบาทของคุณดูได้อย่างเดียว</div>
<?php endif; ?>
<?php if ($schoolName !== '' || $maxPeriod > 0): ?>
<form method="post" action="<?= e(url('/policies')) ?>" class="card timetable-max">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="max_period">
    <header class="card-head">
        <strong>ชั่วโมงสูงสุดของตาราง</strong>
        <span class="hint">คาบที่เกินค่านี้เป็นนอกเวลา ลงรายวิชาไม่ได้ ค่าเริ่มต้นคือ 9 คาบ เลิก 17:00 น.</span>
    </header>
    <label>คาบสุดท้ายที่ใช้ได้
        <select name="max_period" onchange="this.form.submit()" <?= $canEdit ? '' : 'disabled' ?> aria-label="ชั่วโมงสูงสุดของตาราง">
            <?php for ($period = 6; $period <= 10; $period++): ?>
                <option value="<?= $period ?>" <?= $period === (int) $maxPeriod ? 'selected' : '' ?>><?= $period ?> คาบ · เลิก <?= e(ScheduleEngine::TIMES[$period]) ?> น.</option>
            <?php endfor; ?>
        </select>
    </label>
</form>
<?php endif; ?>
<section class="card policy-list">
    <header class="card-head">
        <strong>นโยบายของ<?= $schoolName !== '' ? ' ' . e($schoolName) : 'สถานศึกษานี้' ?></strong>
        <span class="hint">ชุดนี้ใช้เฉพาะสถานศึกษาที่เลือกอยู่ · ข้อบังคับต้องปฏิบัติตาม · ข้อแนะนำพยายามปฏิบัติตามลำดับ</span>
    </header>
    <?php foreach ($policies as $index => $policy): ?>
        <form method="post" action="<?= e(url('/policies')) ?>" class="policy-row" style="opacity: <?= (int) $policy['enabled'] === 1 ? '1' : '.5' ?>">
            <?= Csrf::field() ?>
            <input type="hidden" name="id" value="<?= (int) $policy['id'] ?>">
            <span class="order"><?= $index + 1 ?></span>
            <div class="policy-text">
                <p class="policy-read"><?= e($policy['body']) ?></p>
                <?php if ($canEdit): ?>
                    <div class="policy-form">
                        <textarea name="text" rows="3" maxlength="2000"><?= e($policy['body']) ?></textarea>
                        <span class="row-actions">
                            <button class="btn btn-primary" name="action" value="save">บันทึกข้อความ</button>
                            <button class="btn" type="button" data-policy-cancel>ยกเลิก</button>
                        </span>
                    </div>
                <?php endif; ?>
            </div>
            <div class="seg">
                <button name="action" value="required" <?= $canEdit ? '' : 'disabled' ?> class="<?= $policy['policy_type'] === 'required' ? 'on' : '' ?>">ข้อบังคับ</button>
                <button name="action" value="recommended" <?= $canEdit ? '' : 'disabled' ?> class="<?= $policy['policy_type'] === 'recommended' ? 'on' : '' ?>">ข้อแนะนำ</button>
            </div>
            <button class="switch <?= (int) $policy['enabled'] === 1 ? 'on' : '' ?>" name="action" value="toggle" <?= $canEdit ? '' : 'disabled' ?> aria-label="เปิดหรือปิด"></button>
            <span class="icon-buttons">
                <?php if ($canEdit): ?><button type="button" data-policy-edit aria-label="แก้ไข"><i class="bi bi-pencil"></i></button><?php endif; ?>
                <button name="action" value="up" <?= $canEdit ? '' : 'disabled' ?> aria-label="ขึ้น"><i class="bi bi-arrow-up"></i></button>
                <button name="action" value="down" <?= $canEdit ? '' : 'disabled' ?> aria-label="ลง"><i class="bi bi-arrow-down"></i></button>
                <button name="action" value="delete" <?= $canEdit ? '' : 'disabled' ?> aria-label="ลบ"><i class="bi bi-trash3"></i></button>
            </span>
        </form>
    <?php endforeach; ?>
    <form method="post" action="<?= e(url('/policies')) ?>" class="policy-add">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="add">
        <input name="text" placeholder="พิมพ์นโยบายใหม่ เช่น ครูแต่ละคนสอนไม่เกิน 6 ชั่วโมงต่อวัน" <?= $canEdit ? '' : 'disabled' ?>>
        <select name="policy_type" <?= $canEdit ? '' : 'disabled' ?>>
            <option value="required">ข้อบังคับ</option>
            <option value="recommended" selected>ข้อแนะนำ</option>
        </select>
        <button class="btn btn-primary" <?= $canEdit ? '' : 'disabled' ?>><i class="bi bi-plus-lg"></i> เพิ่มนโยบาย</button>
    </form>
</section>
<?php if ($canEdit): ?>
<script>
(() => {
    const list = document.querySelector(".policy-list");
    if (!list) return;
    const close = (row) => {
        row.classList.remove("is-editing");
        const area = row.querySelector("textarea");
        const text = row.querySelector(".policy-read");
        if (area && text) area.value = text.textContent;
    };
    list.addEventListener("click", (event) => {
        const edit = event.target.closest("[data-policy-edit]");
        const cancel = event.target.closest("[data-policy-cancel]");
        const row = event.target.closest(".policy-row");
        if (!row) return;
        if (edit) {
            list.querySelectorAll(".policy-row.is-editing").forEach((open) => {
                if (open !== row) close(open);
            });
            row.classList.add("is-editing");
            row.querySelector("textarea")?.focus();
        }
        if (cancel) close(row);
    });
})();
</script>
<?php endif; ?>
