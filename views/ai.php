<?php if (!$canEdit): ?>
    <div class="banner warn"><i class="bi bi-lock"></i> การเพิ่ม API Key และการเปิดใช้โมเดลเป็นสิทธิ์ของผู้ดูแลระบบสถานศึกษา</div>
<?php endif; ?>
<?php if (!$settings): ?>
    <div class="banner warn">ยังไม่มีสถานศึกษาให้ตั้งค่า</div>
<?php else: ?>
<?php if (count($enabledModels) > 1): ?>
<form method="post" action="<?= e(url('/ai')) ?>" class="card stack narrow">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="pick">
    <label>โมเดลที่ทำงาน
        <select name="model_id">
            <?php foreach ($enabledModels as $model): ?>
                <option value="<?= (int) $model['id'] ?>" <?= (int) $model['id'] === (int) $workingModelId ? 'selected' : '' ?>><?= e($model['model_name']) ?> · <?= e($model['label']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <p class="hint">เปิดใช้ไว้หลายโมเดล จึงเลือกได้ว่าจะให้โมเดลใดตอบในแชทของบัญชีนี้</p>
    <button class="btn btn-primary" type="submit">ใช้โมเดลนี้</button>
</form>
<?php elseif (count($enabledModels) === 1): ?>
    <div class="banner ok">โมเดลที่ทำงานอยู่คือ <?= e($enabledModels[0]['model_name']) ?> จากชุด <?= e($enabledModels[0]['label']) ?></div>
<?php else: ?>
    <div class="banner warn">ยังไม่มีโมเดลที่เปิดใช้งาน แชทจะตอบภายในระบบจนกว่าจะเลือกโมเดลจากชุด API Key</div>
<?php endif; ?>

<?php if ($canEdit): ?>
<section class="card stack">
    <h2>เพิ่ม API Key</h2>
    <p class="hint">บันทึกได้หลายชุด ระบบจะทดสอบการเชื่อมต่อและดึงรายการโมเดลก่อนเก็บคีย์ หากเชื่อมต่อไม่สำเร็จจะไม่บันทึก</p>
    <form method="post" action="<?= e(url('/ai')) ?>" class="stack" id="ai-form">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="add">
        <div class="provider-cards">
            <?php foreach ($catalog as $key => $provider): ?>
                <label class="provider-card <?= $key === 'openrouter' ? 'on' : '' ?>">
                    <input type="radio" name="provider" value="<?= e($key) ?>" <?= $key === 'openrouter' ? 'checked' : '' ?>>
                    <span><i class="bi <?= e($provider['icon']) ?>"></i> <strong><?= e($provider['name']) ?></strong></span>
                    <small><?= e($provider['desc']) ?></small>
                </label>
            <?php endforeach; ?>
        </div>
        <div class="split">
            <label>ชื่อชุดคีย์<input name="label" placeholder="เช่น OpenRouter ของวิทยาลัย" data-key-label></label>
            <label>Base URL<input name="base_url" value="<?= e($catalog['openrouter']['url']) ?>" data-base-url required></label>
        </div>
        <label>API Key<input type="password" name="api_key" required autocomplete="off"></label>
        <button class="btn btn-primary" type="submit">ทดสอบ ดึงโมเดล และบันทึก</button>
    </form>
</section>
<?php endif; ?>

<?php if ($canEdit): ?>
<?php foreach ($credentials as $credential): ?>
<section class="card stack key-card" id="key-<?= (int) $credential['id'] ?>">
    <header class="key-head">
        <div>
            <strong><?= e($credential['label']) ?></strong>
            <p class="hint"><?= e($catalog[$credential['provider']]['name'] ?? $credential['provider']) ?> · ลงท้าย <?= e((string) ($credential['api_key_hint'] ?: '—')) ?>
                <?php if ($credential['last_test_status'] === 'ok'): ?> · ทดสอบผ่าน<?php elseif ($credential['last_test_status'] === 'fail'): ?> · ทดสอบไม่ผ่าน<?php endif; ?>
            </p>
        </div>
        <form method="post" action="<?= e(url('/ai')) ?>">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="credential_id" value="<?= (int) $credential['id'] ?>">
            <button class="btn btn-danger" type="submit">ลบชุดนี้</button>
        </form>
    </header>
    <form method="post" action="<?= e(url('/ai')) ?>" class="stack">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="models">
        <input type="hidden" name="credential_id" value="<?= (int) $credential['id'] ?>">
        <label>ค้นหาโมเดล<input type="search" data-model-filter placeholder="พิมพ์ชื่อโมเดล"></label>
        <?php if ($credential['models'] === []): ?>
            <p class="empty">ยังไม่มีรายการโมเดล กดดึงรายการอีกครั้ง</p>
        <?php else: ?>
            <div class="model-list">
                <?php foreach ($credential['models'] as $model): ?>
                    <label data-model-row>
                        <input type="checkbox" name="enabled[]" value="<?= (int) $model['id'] ?>" <?= (int) $model['enabled'] === 1 ? 'checked' : '' ?>>
                        <span><?= e($model['model_name']) ?></span>
                        <input type="radio" name="default_model" value="<?= (int) $model['id'] ?>" <?= (int) $model['id'] === (int) ($settings['working_model_id'] ?? 0) ? 'checked' : '' ?>>
                        <em>ค่าเริ่มต้น</em>
                    </label>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <div class="row-actions">
            <button class="btn btn-primary" type="submit">บันทึกโมเดลที่เปิดใช้</button>
        </div>
    </form>
    <form method="post" action="<?= e(url('/ai')) ?>">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="refresh">
        <input type="hidden" name="credential_id" value="<?= (int) $credential['id'] ?>">
        <button class="btn" type="submit">ทดสอบและดึงรายการโมเดลอีกครั้ง</button>
    </form>
</section>
<?php endforeach; ?>
<?php if ($credentials === []): ?><p class="empty">ยังไม่มีชุด API Key</p><?php endif; ?>
<?php endif; ?>

<?php if ($canEdit): ?>
<form method="post" action="<?= e(url('/ai')) ?>" class="card stack">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="permissions">
    <strong>ขอบเขตการทำงานของผู้ช่วย AI</strong>
    <?php
    $perms = [
        'allow_act' => ['ให้ AI ดำเนินการแทนผู้ใช้', 'ทำได้เฉพาะงานที่อยู่ในสิทธิ์ของบทบาทผู้ใช้'],
        'require_confirm' => ['ยืนยันก่อนบันทึกการเปลี่ยนแปลง', 'AI แสดงสิ่งที่จะเปลี่ยนและรอผู้ใช้พิมพ์ยืนยัน'],
        'suggest_contact' => ['แนะนำผู้รับผิดชอบเมื่องานเกินสิทธิ์', 'ระบุบทบาทและชื่อผู้ที่ต้องติดต่อ'],
        'log_actions' => ['บันทึกประวัติการทำงานของ AI', 'ตรวจสอบย้อนหลังได้ว่า AI ทำอะไรแทนใคร'],
    ];
    foreach ($perms as $key => $meta): ?>
        <label class="perm">
            <span><strong><?= e($meta[0]) ?></strong><small><?= e($meta[1]) ?></small></span>
            <input type="checkbox" name="<?= e($key) ?>" <?= (int) $settings[$key] === 1 ? 'checked' : '' ?>>
        </label>
    <?php endforeach; ?>
    <button class="btn btn-primary" type="submit">บันทึกขอบเขต</button>
</form>
<script>
const aiCatalog = <?= json_encode($catalog, JSON_UNESCAPED_UNICODE) ?>;
document.querySelectorAll('#ai-form input[name="provider"]').forEach((input) => {
    input.addEventListener('change', () => {
        const item = aiCatalog[input.value];
        if (!item) return;
        const url = document.querySelector('[data-base-url]');
        const label = document.querySelector('[data-key-label]');
        if (url) url.value = item.url;
        if (label && label.value === '') label.placeholder = item.name;
        document.querySelectorAll('#ai-form .provider-card').forEach((card) => card.classList.remove('on'));
        input.closest('.provider-card').classList.add('on');
    });
});
document.querySelectorAll('[data-model-filter]').forEach((input) => {
    input.addEventListener('input', () => {
        const word = input.value.trim().toLowerCase();
        input.closest('form').querySelectorAll('[data-model-row]').forEach((row) => {
            const name = row.querySelector('span')?.textContent.toLowerCase() || '';
            row.hidden = word !== '' && !name.includes(word);
        });
    });
});
</script>
<?php endif; ?>
<?php endif; ?>
