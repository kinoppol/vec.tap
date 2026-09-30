<?php if (!$canEdit): ?>
    <div class="banner warn"><i class="bi bi-lock"></i> การตั้งค่าการเชื่อมต่อ AI เป็นสิทธิ์ของผู้ดูแลระบบสถานศึกษา</div>
<?php endif; ?>
<?php if (!$settings): ?>
    <div class="banner warn">ยังไม่มีสถานศึกษาให้ตั้งค่า</div>
<?php else:
    $providerKey = (string) $settings['provider'];
    $active = $catalog[$providerKey] ?? reset($catalog);
    ?>
<form method="post" action="<?= e(url('/ai')) ?>" class="stack" id="ai-form">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="save">
    <div class="provider-cards">
        <?php foreach ($catalog as $key => $provider): ?>
            <label class="provider-card <?= $providerKey === $key ? 'on' : '' ?>">
                <input type="radio" name="provider" value="<?= e($key) ?>" <?= $providerKey === $key ? 'checked' : '' ?> <?= $canEdit ? '' : 'disabled' ?>>
                <span><i class="bi <?= e($provider['icon']) ?>"></i> <strong><?= e($provider['name']) ?></strong></span>
                <small><?= e($provider['desc']) ?></small>
            </label>
        <?php endforeach; ?>
    </div>
    <div class="split">
        <section class="card stack">
            <strong>การเชื่อมต่อ · <span data-provider-name><?= e($active['name']) ?></span></strong>
            <label>Base URL<input name="base_url" value="<?= e($settings['base_url']) ?>" <?= $canEdit ? '' : 'readonly' ?> data-base-url></label>
            <label>API Key
                <input type="password" name="api_key" value="" placeholder="<?= $settings['api_key_hint'] ? 'บันทึกแล้ว ลงท้าย ' . e((string) $settings['api_key_hint']) : 'ยังไม่ได้บันทึก' ?>" <?= $canEdit ? '' : 'disabled' ?> autocomplete="off">
            </label>
            <label>โมเดล
                <input name="model" list="model-list" value="<?= e($settings['model']) ?>" <?= $canEdit ? '' : 'readonly' ?> data-model>
                <datalist id="model-list">
                    <?php foreach ($active['models'] as $model): ?><option value="<?= e($model) ?>"><?php endforeach; ?>
                </datalist>
            </label>
            <p class="hint">เว้น API Key ว่างหากต้องการใช้คีย์เดิมของสถานศึกษานี้ คีย์ถูกเข้ารหัสและไม่แสดงกลับทั้งค่า</p>
            <?php if ($canEdit): ?><button class="btn btn-primary" type="submit">บันทึกการตั้งค่า</button><?php endif; ?>
        </section>
        <section class="card">
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
                    <input type="checkbox" name="<?= e($key) ?>" <?= (int) $settings[$key] === 1 ? 'checked' : '' ?> <?= $canEdit ? '' : 'disabled' ?>>
                </label>
            <?php endforeach; ?>
        </section>
    </div>
</form>
<?php if ($canEdit): ?>
<form method="post" action="<?= e(url('/ai')) ?>" class="test-row">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="test">
    <button class="btn btn-primary" type="submit">ทดสอบการเชื่อมต่อ</button>
    <span class="hint">ใช้คีย์ที่บันทึกไว้ของสถานศึกษาที่เลือกอยู่เท่านั้น</span>
</form>
<?php endif; ?>
<script>
const aiCatalog = <?= json_encode($catalog, JSON_UNESCAPED_UNICODE) ?>;
document.querySelectorAll('input[name="provider"]').forEach((input) => {
    input.addEventListener('change', () => {
        const item = aiCatalog[input.value];
        if (!item) return;
        const url = document.querySelector('[data-base-url]');
        const model = document.querySelector('[data-model]');
        const name = document.querySelector('[data-provider-name]');
        if (url) url.value = item.url;
        if (model) model.value = item.models[0] || '';
        if (name) name.textContent = item.name;
        const list = document.getElementById('model-list');
        if (list) list.innerHTML = item.models.map((value) => '<option value="' + value.replaceAll('"', '&quot;') + '">').join('');
        document.querySelectorAll('.provider-card').forEach((card) => card.classList.remove('on'));
        input.closest('.provider-card').classList.add('on');
    });
});
</script>
<?php endif; ?>
