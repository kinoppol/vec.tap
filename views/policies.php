<?php if (!$canEdit): ?>
    <div class="banner warn"><i class="bi bi-lock"></i> การเพิ่ม แก้ไข ลบ หรือเรียงลำดับนโยบายเป็นสิทธิ์ของผู้ดูแลระบบสถานศึกษา บทบาทของคุณดูได้อย่างเดียว</div>
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
            <?php if ($canEdit): ?>
                <div class="policy-text">
                    <textarea name="text" rows="2" maxlength="2000"><?= e($policy['body']) ?></textarea>
                    <button class="btn" name="action" value="save">บันทึกข้อความ</button>
                </div>
            <?php else: ?>
                <p><?= e($policy['body']) ?></p>
            <?php endif; ?>
            <div class="seg">
                <button name="action" value="required" <?= $canEdit ? '' : 'disabled' ?> class="<?= $policy['policy_type'] === 'required' ? 'on' : '' ?>">ข้อบังคับ</button>
                <button name="action" value="recommended" <?= $canEdit ? '' : 'disabled' ?> class="<?= $policy['policy_type'] === 'recommended' ? 'on' : '' ?>">ข้อแนะนำ</button>
            </div>
            <button class="switch <?= (int) $policy['enabled'] === 1 ? 'on' : '' ?>" name="action" value="toggle" <?= $canEdit ? '' : 'disabled' ?> aria-label="เปิดหรือปิด"></button>
            <span class="icon-buttons">
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
