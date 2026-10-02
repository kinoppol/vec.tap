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
    <div class="rms-live" data-rms-live hidden>
        <div class="rms-live-head">
            <strong data-rms-name>กำลังเตรียมนำเข้า</strong>
            <span data-rms-percent></span>
        </div>
        <div class="progress" data-rms-progress role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><span></span></div>
        <p class="hint" data-rms-status></p>
    </div>
    <div class="rms-list">
        <?php
        $jobs = [
            ['people', 'บุคลากร', 'เป็นครูผู้สอนที่ยังปฏิบัติงาน ผู้ที่หายจาก RMS จะถูกปิดใช้งาน ไม่ลบ และไม่เปลี่ยนบทบาทผู้ใช้', (int) ($counts['teachers'] ?? 0) . ' คน'],
            ['terms', 'ปฏิทินภาคเรียน', 'เพิ่มหรืออัปเดตภาคเรียน ไม่เปลี่ยนภาคเรียนปัจจุบันที่ตั้งไว้', (int) ($counts['terms'] ?? 0) . ' ภาคเรียน'],
            ['holidays', 'วันหยุด', 'ต้องมีปฏิทินภาคเรียนตรงกันก่อน วันหยุดที่หาภาคเรียนไม่เจอจะถูกข้าม', (int) ($counts['holidays'] ?? 0) . ' วัน'],
            ['groups', 'กลุ่มเรียน', 'ผูกกับภาคเรียนปัจจุบันและครูที่ปรึกษาตามชื่อหรือรหัสบุคลากร ต้องตั้งภาคเรียนปัจจุบันก่อน', (int) ($counts['groups'] ?? 0) . ' กลุ่ม'],
            ['plans', 'แผนการเรียน', 'สร้างแผนในภาคเรียนปัจจุบัน อ่าน ท-ป-น จากรูปแบบหน่วยกิต (ท-ป-น) หรือจากบัญชีรายวิชา เลขหน่วยกิตรวมอย่างเดียวจะไม่ถูกใส่ลงช่องทฤษฎี และไม่เขียนทับชั่วโมงที่แยกไว้แล้ว', (int) ($counts['plans'] ?? 0) . ' แผน'],
            ['majors', 'สาขาวิชา', 'จากตาราง std2018_major', (int) ($counts['majors'] ?? 0) . ' สาขา'],
            ['minors', 'สาขางาน', 'จากตาราง std2018_minor', (int) ($counts['minors'] ?? 0) . ' สาขางาน'],
            ['subjecttypes', 'ประเภทวิชา', 'จากตาราง std2018_subjecttype', (int) ($counts['subjecttypes'] ?? 0) . ' ประเภท'],
            ['curricula', 'หลักสูตร', 'จากตาราง std2018_curriculum ผูกปีหลักสูตรกับประเภทวิชา สาขา และสาขางาน', (int) ($counts['curricula'] ?? 0) . ' หลักสูตร'],
            ['catalog', 'บัญชีรายวิชา', 'จากตาราง subject เก็บรหัส ชื่อ และ ท-ป-น เมื่อ RMS ส่งรูปแบบหน่วยกิต (ท-ป-น) ควรนำเข้าก่อนแผนการเรียน', (int) ($counts['catalog'] ?? 0) . ' วิชา'],
            ['students', 'ผู้เรียน', 'โหลดทีละ 100 รายการ แล้วนับจำนวนผู้เรียนใส่กลุ่ม', (int) ($counts['students'] ?? 0) . ' คน'],
            ['enrollments', 'การลงทะเบียน', 'จากตาราง std2018_studentenroll ว่าผู้เรียนลงตารางใดในภาคเรียนนั้น', (int) ($counts['enrollments'] ?? 0) . ' รายการ'],
            ['timetables', 'ตารางจาก ศธ.02', 'จากตาราง std2018_timetable ลงคาบ ห้อง และอาคาร ควรนำเข้ากลุ่มเรียนและแผนก่อน', (int) ($counts['timetables'] ?? 0) . ' คาบ'],
            ['blocks', 'บล็อกคอร์ส', 'จากตาราง std2018_timetable_blockcourse ใช้ช่วงเวลาของตารางศธ.02 ควรนำเข้าตารางนั้นก่อน', (int) ($counts['blocks'] ?? 0) . ' ช่วง'],
            ['schedules', 'ตารางเรียน RMS', 'จากตาราง studing ของภาคเรียนปัจจุบัน ลงคาบที่ยังว่าง', (int) ($counts['schedules'] ?? 0) . ' คาบ'],
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
        <?php foreach (['students' => 'ผู้เรียน', 'groups' => 'กลุ่มเรียน', 'plans' => 'แผนการเรียน', 'catalog' => 'บัญชีรายวิชา', 'curricula' => 'หลักสูตร', 'timetables' => 'ตารางศธ.02', 'blocks' => 'บล็อกคอร์ส', 'enrollments' => 'ลงทะเบียน', 'holidays' => 'วันหยุด', 'schedules' => 'ตาราง RMS'] as $key => $label): ?>
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
    const live = root.querySelector('[data-rms-live]');
    const nameEl = root.querySelector('[data-rms-name]');
    const percentEl = root.querySelector('[data-rms-percent]');
    const progress = root.querySelector('[data-rms-progress]');
    const bar = progress?.querySelector('span');
    const status = root.querySelector('[data-rms-status]');
    const jobs = [
        { key: 'people', mode: 'once' },
        { key: 'terms', mode: 'once' },
        { key: 'holidays', mode: 'once' },
        { key: 'groups', mode: 'once' },
        { key: 'plans', mode: 'scan', row: 500 },
        { key: 'majors', mode: 'once' },
        { key: 'minors', mode: 'once' },
        { key: 'subjecttypes', mode: 'once' },
        { key: 'curricula', mode: 'once' },
        { key: 'catalog', mode: 'scan', row: 500 },
        { key: 'students', mode: 'count', row: 100 },
        { key: 'enrollments', mode: 'scan', row: 500 },
        { key: 'timetables', mode: 'scan', row: 500 },
        { key: 'blocks', mode: 'scan', row: 500 },
        { key: 'schedules', mode: 'scan', row: 1000 },
    ];
    jobs.forEach((job) => {
        const button = root.querySelector('[data-rms-one="' + job.key + '"]');
        job.title = button?.closest('.rms-item')?.querySelector('strong')?.textContent?.trim() || job.key;
    });
    let busy = false;
    const n = (value) => Number(value || 0).toLocaleString('th-TH');

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
    const paint = (ratio, waiting) => {
        if (!live || !progress || !bar) return;
        live.hidden = false;
        const percent = Math.max(0, Math.min(100, Math.round((Number.isFinite(ratio) ? ratio : 0) * 100)));
        progress.classList.toggle('wait', waiting);
        progress.classList.remove('scan');
        progress.setAttribute('aria-valuenow', String(percent));
        bar.style.width = percent + '%';
        if (percentEl) percentEl.textContent = percent + '%';
    };
    const mark = (key) => {
        root.querySelectorAll('.rms-item').forEach((item) => item.classList.remove('is-running'));
        const item = root.querySelector('[data-rms-one="' + key + '"]')?.closest('.rms-item');
        if (!item) return;
        item.classList.add('is-running');
        item.scrollIntoView({ block: 'nearest' });
    };
    const countOf = async (job) => {
        if (nameEl) nameEl.textContent = 'กำลังนับ ' + job.title;
        show('ขอนับจำนวนจาก RMS ก่อนลงข้อมูล');
        const counted = await post({ action: 'count', dataset: job.key });
        return counted.total || 0;
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

    const ratioOf = (index, inner, span) => span > 1 ? (index + inner) / span : inner;

    const run = async (job, index, span) => {
        const slot = root.querySelector('[data-rms-result="' + job.key + '"]');
        mark(job.key);
        const total = await countOf(job);
        const place = (inner, waiting, detail) => {
            paint(ratioOf(index, inner, span), waiting);
            if (nameEl) nameEl.textContent = 'กำลังนำเข้า ' + job.title;
            const set = span > 1 ? 'ชุดที่ ' + n(index + 1) + ' จาก ' + n(span) : '';
            show([set, detail].filter(Boolean).join(' · '));
        };
        if (job.mode === 'once') {
            place(0, true, total ? n(total) + ' รายการ' : 'กำลังดึงข้อมูล');
            const data = await post({ action: 'sync', dataset: job.key, offset: 0, row: 100 });
            if (slot) slot.textContent = summarize(data);
            place(1, false, summarize(data));
            return;
        }
        let offset = 0;
        let done = 0;
        const tally = { added: 0, updated: 0, skipped: 0, inserted: 0, placed: 0, duplicated: 0, created: 0, deactivated: 0 };
        while (true) {
            const inner = total > 0 ? Math.min(1, done / total) : 0;
            place(inner, total === 0, total ? 'โหลดแล้ว ' + n(done) + ' จาก ' + n(total) + ' รายการ' : 'โหลดแล้ว ' + n(done) + ' รายการ');
            if (slot && done > 0) slot.textContent = total ? 'กำลังนำเข้า ' + n(done) + ' / ' + n(total) : 'กำลังนำเข้า ' + n(done) + ' รายการ';
            const data = await post({ action: 'sync_batch', dataset: job.key, offset, row: job.row });
            Object.keys(tally).forEach((key) => { tally[key] += data[key] || 0; });
            const fetched = data.fetched || 0;
            done += fetched;
            offset += job.row;
            if (fetched < job.row) break;
            if (total > 0 && done >= total) break;
        }
        if (slot) slot.textContent = summarize(tally);
        place(1, false, summarize(tally));
    };

    const buttons = () => root.querySelectorAll('[data-rms-one], [data-rms-all]');
    const guard = async (work) => {
        if (busy) return;
        busy = true;
        buttons().forEach((button) => { button.disabled = true; });
        try {
            await work();
            if (nameEl) nameEl.textContent = 'นำเข้าเสร็จแล้ว';
            paint(1, false);
            show('ครบทุกรายการที่เลือก');
            root.querySelectorAll('.rms-item').forEach((item) => item.classList.remove('is-running'));
        } catch (error) {
            if (progress) progress.classList.remove('wait');
            show(error.message || 'นำเข้าไม่สำเร็จ');
        } finally {
            busy = false;
            buttons().forEach((button) => { button.disabled = false; });
        }
    };

    root.querySelectorAll('[data-rms-one]').forEach((button) => {
        button.addEventListener('click', () => {
            const job = jobs.find((item) => item.key === button.dataset.rmsOne);
            if (job) guard(() => run(job, 0, 1));
        });
    });
    root.querySelector('[data-rms-all]')?.addEventListener('click', () => {
        guard(async () => {
            for (let index = 0; index < jobs.length; index += 1) await run(jobs[index], index, jobs.length);
        });
    });
})();
</script>
