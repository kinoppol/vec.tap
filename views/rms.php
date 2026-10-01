<section class="card stack">
    <h2>URL ของระบบ RMS</h2>
    <p class="hint">แต่ละสถานศึกษาใช้คนละที่อยู่ เส้นทาง <code>/api_connection.php</code> และรหัสเชื่อมต่อถูกกำหนดไว้ในระบบ</p>
    <?php if ($canEditUrl): ?>
        <form method="post" action="<?= e(url('/rms')) ?>" class="stack narrow">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="save_url">
            <label>Base URL<input name="base_url" value="<?= e($baseUrl) ?>" placeholder="http://rms.example.ac.th" required></label>
            <button class="btn btn-primary" type="submit">บันทึก URL ของสถานศึกษานี้</button>
        </form>
    <?php else: ?>
        <p><?= $baseUrl !== '' ? e($baseUrl) : 'ผู้ดูแลสถานศึกษายังไม่ได้ตั้งค่า URL' ?></p>
    <?php endif; ?>
</section>

<section class="card stack">
    <h2>ภาคเรียนปัจจุบัน</h2>
    <p class="hint">การโหลดตารางเรียนใช้ภาคเรียนที่ตั้งเป็นปัจจุบัน และจะไม่ถูกเปลี่ยนเองตอนนำเข้าปฏิทิน</p>
    <?php if ($terms === []): ?>
        <p class="empty">ยังไม่มีภาคเรียน โหลดปฏิทินภาคเรียนก่อน</p>
    <?php else: ?>
        <form method="post" action="<?= e(url('/rms')) ?>" class="row-actions">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="current_term">
            <select name="term_id">
                <?php foreach ($terms as $term): ?>
                    <option value="<?= (int) $term['id'] ?>" <?= (int) $term['is_current'] === 1 ? 'selected' : '' ?>><?= e($term['label']) ?><?= $term['rms_key'] ? ' · ' . e((string) $term['rms_key']) : '' ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn" type="submit">ใช้ภาคเรียนนี้</button>
        </form>
    <?php endif; ?>
</section>

<section class="card stack" id="rms-app" data-csrf="<?= e(Csrf::token()) ?>" data-url="<?= e(url('/rms')) ?>">
    <header class="key-head">
        <div>
            <h2>นำเข้าข้อมูล</h2>
            <p class="hint">อย่าปิดหน้านี้ระหว่างโหลดชุดใหญ่ ข้อมูลถูกผูกกับสถานศึกษาที่เลือกอยู่ และกดซ้ำได้โดยไม่สร้างรายการซ้ำ</p>
        </div>
        <button class="btn btn-primary" type="button" data-rms-all <?= $baseUrl === '' ? 'disabled' : '' ?>>โหลดทั้งหมดตามลำดับ</button>
    </header>
    <div class="progress" data-rms-progress hidden><span></span></div>
    <p class="hint" data-rms-status></p>
    <div class="rms-list">
        <?php
        $jobs = [
            ['people', 'บุคลากร', 'เป็นครูผู้สอนที่ยังปฏิบัติงาน ผู้ที่หายจาก RMS จะถูกปิดใช้งาน ไม่ลบ และไม่เปลี่ยนบทบาทผู้ใช้', (int) ($counts['teachers'] ?? 0) . ' คน'],
            ['terms', 'ปฏิทินภาคเรียน', 'เพิ่มหรืออัปเดตภาคเรียน ไม่เปลี่ยนภาคเรียนปัจจุบันที่ตั้งไว้', (int) ($counts['terms'] ?? 0) . ' ภาคเรียน'],
            ['holidays', 'วันหยุด', 'ต้องมีปฏิทินภาคเรียนตรงกันก่อน วันหยุดที่หาภาคเรียนไม่เจอจะถูกข้าม', (int) ($counts['holidays'] ?? 0) . ' วัน'],
            ['groups', 'กลุ่มเรียน', 'ผูกกับภาคเรียนและครูที่ปรึกษาตามชื่อหรือรหัสบุคลากร', (int) ($counts['groups'] ?? 0) . ' กลุ่ม'],
            ['plans', 'แผนการเรียน', 'จากตาราง std2018_curi_plan สร้างแผนและรายวิชา ท-ป-น แล้วผูกกับกลุ่มเรียนในภาคเรียนเดียวกัน ควรนำเข้ากลุ่มเรียนก่อน', (int) ($counts['plans'] ?? 0) . ' แผน'],
            ['students', 'ผู้เรียน', 'โหลดทีละ 100 รายการ แล้วนับจำนวนผู้เรียนใส่กลุ่ม', (int) ($counts['students'] ?? 0) . ' คน'],
            ['schedules', 'ตารางเรียน', 'ใช้ภาคเรียนปัจจุบัน สร้างรายวิชา ห้อง อาคาร และลงคาบที่อ่านวันกับเวลาได้', (int) ($counts['schedules'] ?? 0) . ' คาบ'],
        ];
        foreach ($jobs as [$key, $title, $detail, $count]): ?>
            <article class="rms-item">
                <div>
                    <strong><?= e($title) ?></strong>
                    <p><?= e($detail) ?></p>
                    <small class="hint" data-rms-result="<?= e($key) ?>">ในระบบตอนนี้ <?= e($count) ?></small>
                </div>
                <button class="btn" type="button" data-rms-one="<?= e($key) ?>" <?= $baseUrl === '' ? 'disabled' : '' ?>>นำเข้า</button>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="card stack">
    <h2>ตรวจสอบข้อมูลที่นำเข้า</h2>
    <div class="tabs">
        <?php foreach (['students' => 'ผู้เรียน', 'groups' => 'กลุ่มเรียน', 'plans' => 'แผนการเรียน', 'holidays' => 'วันหยุด', 'schedules' => 'ตารางเรียน'] as $key => $label): ?>
            <a class="tab <?= $browse['resource'] === $key ? 'on' : '' ?>" href="<?= e(url('/rms?view=' . $key)) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </div>
    <form method="get" action="<?= e(url('/rms')) ?>" class="row-actions">
        <input type="hidden" name="view" value="<?= e($browse['resource']) ?>">
        <input name="q" value="<?= e($query) ?>" placeholder="ค้นหา">
        <button class="btn" type="submit">ค้นหา</button>
    </form>
    <p class="hint"><?= (int) $browse['total'] ?> รายการ</p>
    <div class="table-wrap">
        <table>
            <tbody>
            <?php foreach ($browse['rows'] as $row): ?>
                <tr>
                    <?php foreach ($row as $value): ?><td><?= e($value === null ? '' : (string) $value) ?></td><?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            <?php if ($browse['rows'] === []): ?><tr><td class="empty">ยังไม่มีข้อมูลในมุมนี้</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($browse['pages'] > 1): ?>
        <div class="row-actions">
            <?php for ($i = 1; $i <= $browse['pages']; $i++): ?>
                <a class="btn <?= $i === (int) $browse['page'] ? 'btn-primary' : '' ?>" href="<?= e(url('/rms?view=' . $browse['resource'] . '&page=' . $i . '&q=' . rawurlencode($query))) ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</section>
<script>
(() => {
    const root = document.getElementById('rms-app');
    if (!root) return;
    const csrf = root.dataset.csrf || '';
    const endpoint = root.dataset.url || '';
    const progress = root.querySelector('[data-rms-progress]');
    const bar = progress?.querySelector('span');
    const status = root.querySelector('[data-rms-status]');
    const jobs = [
        { key: 'people', mode: 'once' },
        { key: 'terms', mode: 'once' },
        { key: 'holidays', mode: 'once' },
        { key: 'groups', mode: 'once' },
        { key: 'plans', mode: 'scan', row: 500 },
        { key: 'students', mode: 'count', row: 100 },
        { key: 'schedules', mode: 'scan', row: 1000 },
    ];
    let busy = false;

    const post = async (fields) => {
        const body = new FormData();
        body.set('_csrf', csrf);
        Object.entries(fields).forEach(([key, value]) => body.set(key, String(value)));
        const response = await fetch(endpoint, { method: 'POST', body, headers: { Accept: 'application/json' } });
        const data = await response.json();
        if (!data.ok) throw new Error(data.message || 'นำเข้าไม่สำเร็จ');
        return data.data || {};
    };

    const show = (text) => { if (status) status.textContent = text; };
    const paint = (done, total, scan) => {
        if (!progress || !bar) return;
        progress.hidden = false;
        progress.classList.toggle('scan', scan);
        if (!scan) bar.style.width = (total > 0 ? Math.min(100, Math.round(done / total * 100)) : 0) + '%';
    };

    const summarize = (data) => {
        const bits = [];
        if (data.created) bits.push('เพิ่ม ' + data.created);
        if (data.added) bits.push('เพิ่ม ' + data.added);
        if (data.updated) bits.push('อัปเดต ' + data.updated);
        if (data.inserted) bits.push('บันทึก ' + data.inserted);
        if (data.placed) bits.push('ลงตาราง ' + data.placed);
        if (data.deactivated) bits.push('ปิดใช้งาน ' + data.deactivated);
        if (data.duplicated) bits.push('ซ้ำ ' + data.duplicated);
        if (data.skipped) bits.push('ข้าม ' + data.skipped);
        return bits.join(' · ') || 'ไม่มีรายการใหม่';
    };

    const run = async (job) => {
        const slot = root.querySelector('[data-rms-result="' + job.key + '"]');
        if (job.mode === 'once') {
            paint(0, 0, true);
            show('กำลังนำเข้า ' + job.key);
            const data = await post({ action: 'sync', dataset: job.key, offset: 0, row: 100 });
            if (slot) slot.textContent = summarize(data);
            paint(1, 1, false);
            return;
        }
        let offset = 0;
        let done = 0;
        let total = 0;
        const tally = { added: 0, updated: 0, skipped: 0, inserted: 0, placed: 0, duplicated: 0 };
        if (job.mode === 'count') {
            const counted = await post({ action: 'count', dataset: job.key });
            total = counted.total || 0;
        }
        while (true) {
            paint(done, total, job.mode !== 'count');
            show('โหลดแล้ว ' + done + (total ? ' / ' + total : '') + ' รายการ');
            const data = await post({ action: 'sync_batch', dataset: job.key, offset, row: job.row });
            Object.keys(tally).forEach((key) => { tally[key] += data[key] || 0; });
            const fetched = data.fetched || 0;
            done += fetched;
            offset += job.row;
            if (fetched < job.row) break;
            if (job.mode === 'count' && total > 0 && done >= total) break;
        }
        if (slot) slot.textContent = summarize(tally);
        paint(done, total || done, false);
    };

    const guard = async (work) => {
        if (busy) return;
        busy = true;
        try {
            await work();
            show('นำเข้าเสร็จแล้ว');
        } catch (error) {
            show(error.message || 'นำเข้าไม่สำเร็จ');
        } finally {
            busy = false;
        }
    };

    root.querySelectorAll('[data-rms-one]').forEach((button) => {
        button.addEventListener('click', () => {
            const job = jobs.find((item) => item.key === button.dataset.rmsOne);
            if (job) guard(() => run(job));
        });
    });
    root.querySelector('[data-rms-all]')?.addEventListener('click', () => {
        guard(async () => {
            for (const job of jobs) await run(job);
        });
    });
})();
</script>
