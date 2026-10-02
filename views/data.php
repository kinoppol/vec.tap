<?php
$tabs = [
    'teachers' => ['ครูผู้สอน', 'bi-person-badge'],
    'groups' => ['กลุ่มผู้เรียน', 'bi-people'],
    'plans' => ['แผนการเรียน', 'bi-journal-bookmark'],
    'subjects' => ['รายวิชา', 'bi-journal-text'],
    'rooms' => ['อาคารและห้องเรียน', 'bi-building'],
];
?>
<div class="toolbar plain">
    <div class="tabs">
        <?php foreach ($tabs as $key => $meta): ?>
            <a class="tab <?= $tab === $key ? 'on' : '' ?>" href="<?= e(url('/data?tab=' . $key)) ?>"><i class="bi <?= e($meta[1]) ?>"></i> <?= e($meta[0]) ?></a>
        <?php endforeach; ?>
    </div>
</div>
<?php if ($tab === 'teachers' || $tab === 'rooms' || in_array($tab, ['groups', 'subjects'], true)): ?>
<div class="data-action-bar">
    <?php if ($tab === 'teachers'): ?>
        <a class="btn" href="<?= e(url('/data/skills-export')) ?>"><i class="bi bi-download"></i> ส่งออกทักษะ</a>
        <?php if ($canEdit): ?>
            <form method="post" action="<?= e(url('/data')) ?>" enctype="multipart/form-data" class="inline">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="import_skills">
                <label class="btn file-btn"><i class="bi bi-upload"></i> นำเข้าทักษะ<input type="file" name="file" accept=".csv,.txt,.xlsx" onchange="this.form.submit()"></label>
            </form>
        <?php endif; ?>
    <?php endif; ?>
    <?php if ($tab === 'rooms'): ?>
        <a class="btn" href="<?= e(url('/data/buildings-export')) ?>"><i class="bi bi-download"></i> ส่งออกอาคาร</a>
        <a class="btn" href="<?= e(url('/data/rooms-export')) ?>"><i class="bi bi-download"></i> ส่งออกห้องเรียน</a>
        <?php if ($canEdit): ?>
            <form method="post" action="<?= e(url('/data')) ?>" enctype="multipart/form-data" class="inline">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="import_buildings">
                <label class="btn file-btn"><i class="bi bi-upload"></i> นำเข้าอาคาร<input type="file" name="file" accept=".csv,.txt,.xlsx" onchange="this.form.submit()"></label>
            </form>
            <form method="post" action="<?= e(url('/data')) ?>" enctype="multipart/form-data" class="inline">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="import_rooms">
                <label class="btn file-btn"><i class="bi bi-upload"></i> นำเข้าห้องเรียน<input type="file" name="file" accept=".csv,.txt,.xlsx" onchange="this.form.submit()"></label>
            </form>
        <?php endif; ?>
    <?php endif; ?>
    <?php if (in_array($tab, ['teachers', 'groups', 'subjects'], true)): ?>
        <a class="btn" href="<?= e(url('/data/template?tab=' . $tab)) ?>"><i class="bi bi-file-earmark-arrow-down"></i> ดาวน์โหลดแม่แบบ</a>
        <form method="post" action="<?= e(url('/data')) ?>" enctype="multipart/form-data" class="inline">
            <?= Csrf::field() ?>
            <input type="hidden" name="tab" value="<?= e($tab) ?>">
            <label class="btn btn-primary file-btn"><i class="bi bi-upload"></i> นำเข้า Excel / CSV<input type="file" name="file" accept=".csv,.txt,.xlsx" onchange="this.form.submit()"></label>
        </form>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($tab === 'teachers'): ?>
<?php
$minMode = $hourModes['min'] ?? 'soft';
$maxMode = $hourModes['max'] ?? 'soft';
if ($minMode === 'required' && $maxMode === 'required') {
    $hourHint = 'ช่วงชั่วโมงสอนเป็นข้อบังคับ ลงคาบที่ทำให้ครูเกินชั่วโมงสูงสุดไม่ได้';
} elseif ($minMode === 'soft' && $maxMode === 'soft') {
    $hourHint = 'ช่วงชั่วโมงสอนเป็นข้อแนะนำ ลงคาบเกินช่วงนี้ได้ จนกว่านโยบายที่เกี่ยวข้องจะถูกตั้งเป็นข้อบังคับ';
} else {
    $hourHint = 'ต่ำสุดเป็น' . ($minMode === 'required' ? 'ข้อบังคับ' : 'ข้อแนะนำ')
        . ' · สูงสุดเป็น' . ($maxMode === 'required' ? 'ข้อบังคับ' : 'ข้อแนะนำ');
}
?>
<div class="hint-fold is-collapsed" data-hint-fold>
    <button class="hint-toggle" type="button" data-hint-toggle aria-expanded="false"><i class="bi bi-chevron-down"></i> แสดงคำอธิบาย</button>
    <div data-hint-body>
        <p class="hint"><?= e($hourHint) ?></p>
        <p class="hint">ไฟล์ทักษะมีคอลัมน์ชื่อกับทักษะ หนึ่งแถวต่อหนึ่งทักษะ หรือหลายทักษะในช่องเดียวคั่นด้วย | การนำเข้าแทนที่ทักษะของครูที่มีชื่อในไฟล์</p>
        <?php if ($canAssign): ?><p class="hint">สร้างบัญชีจากชื่อครูได้ในคอลัมน์ครูผู้สอน ชื่อผู้ใช้คือเลขประจำตัวประชาชน 13 หลัก บัญชีนี้จัดตารางได้เฉพาะกลุ่มที่มอบหมายในแท็บกลุ่มผู้เรียน</p><?php endif; ?>
    </div>
</div>
<div class="card table-wrap" data-datatable data-empty-text="ยังไม่มีครูในสถานศึกษานี้">
    <div class="dt-bar">
        <label class="dt-field">แสดง
            <select data-dt-length aria-label="จำนวนแถวต่อหน้า">
                <option value="10">10</option>
                <option value="25" selected>25</option>
                <option value="50">50</option>
                <option value="100">100</option>
            </select>
        </label>
        <label class="dt-field dt-search">ค้นหา
            <input type="search" data-dt-search placeholder="ชื่อครู แผนก หรือทักษะ" aria-label="ค้นหาครูผู้สอน">
        </label>
    </div>
    <table class="teacher-table">
        <colgroup>
            <col class="col-name">
            <col class="col-dept">
            <col class="col-skill">
            <col class="col-hours">
        </colgroup>
        <thead><tr>
            <th data-dt-sort="text" class="is-sorted">ครูผู้สอน</th>
            <th data-dt-sort="text">แผนก / วุฒิ</th>
            <th data-dt-sort="text">ทักษะการสอน</th>
            <th data-dt-sort="number">ชั่วโมง/สัปดาห์<small>ต่ำสุด–สูงสุด</small></th>
        </tr></thead>
        <tbody>
        <?php foreach ($teachers as $teacher): ?>
            <?php
            $account = $teacherAccounts[(int) $teacher['id']] ?? '';
            $citizenId = (string) ($teacher['rms_people_id'] ?? '');
            $hasCitizenId = preg_match('/^\d{13}$/', $citizenId) === 1;
            $skillText = implode(' ', $teacher['skills']);
            $got = (int) ($teachingHours[(int) $teacher['id']] ?? 0);
            $minHours = (int) ($teacher['min_hours'] ?? 0);
            $maxHours = (int) $teacher['max_hours'];
            $search = mb_strtolower(trim($teacher['name'] . ' ' . $account . ' ' . $citizenId . ' ' . $teacher['dept'] . ' ' . $teacher['degree'] . ' ' . $skillText));
            ?>
            <tr data-dt-row data-search="<?= e($search) ?>">
                <td data-text="<?= e((string) $teacher['name']) ?>">
                    <strong><?= e($teacher['name']) ?></strong>
                    <?php if ($account !== ''): ?>
                        <small class="muted">ชื่อผู้ใช้ <?= e($account) ?></small>
                    <?php elseif ($canAssign): ?>
                        <form method="post" action="<?= e(url('/data')) ?>" class="account-add">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="action" value="create_teacher_user">
                            <input type="hidden" name="teacher_id" value="<?= (int) $teacher['id'] ?>">
                            <?php if ($hasCitizenId): ?>
                                <small class="muted">ชื่อผู้ใช้ <?= e($citizenId) ?></small>
                            <?php else: ?>
                                <input name="citizen_id" required inputmode="numeric" maxlength="13" pattern="\d{13}" placeholder="เลขประจำตัวประชาชน 13 หลัก" autocomplete="off">
                            <?php endif; ?>
                            <input type="password" name="password" required minlength="8" placeholder="รหัสผ่านอย่างน้อย 8 ตัว" autocomplete="new-password">
                            <button class="btn" type="submit">สร้างบัญชี</button>
                        </form>
                    <?php endif; ?>
                </td>
                <td data-text="<?= e(trim($teacher['dept'] . ' ' . $teacher['degree'])) ?>"><?= e($teacher['dept']) ?><small><?= e($teacher['degree']) ?></small></td>
                <td data-text="<?= e($skillText) ?>">
                    <div class="tags">
                        <?php foreach ($teacher['skills'] as $skill): ?>
                            <?php if ($canEdit): ?>
                                <form method="post" action="<?= e(url('/data')) ?>">
                                    <?= Csrf::field() ?>
                                    <input type="hidden" name="action" value="remove_skill">
                                    <input type="hidden" name="teacher_id" value="<?= (int) $teacher['id'] ?>">
                                    <input type="hidden" name="skill" value="<?= e($skill) ?>">
                                    <span><?= e($skill) ?></span>
                                    <button class="tag-x" type="submit" aria-label="ลบทักษะ">×</button>
                                </form>
                            <?php else: ?>
                                <span><?= e($skill) ?></span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($canEdit): ?>
                        <form method="post" action="<?= e(url('/data')) ?>" class="skill-add">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="action" value="add_skill">
                            <input type="hidden" name="teacher_id" value="<?= (int) $teacher['id'] ?>">
                            <input name="skill" maxlength="128" required placeholder="เช่น เขียนโปรแกรม">
                            <button class="btn" type="submit">เพิ่ม</button>
                        </form>
                    <?php elseif ($teacher['skills'] === []): ?>
                        <span class="muted">—</span>
                    <?php endif; ?>
                </td>
                <td data-value="<?= $got ?>" data-text="<?= $minHours ?> <?= $maxHours ?> <?= $got ?>">
                    <?php if ($canEdit): ?>
                        <form method="post" action="<?= e(url('/data')) ?>" class="hour-edit">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="action" value="set_hours">
                            <input type="hidden" name="teacher_id" value="<?= (int) $teacher['id'] ?>">
                            <label>ต่ำสุด<input name="min_hours" type="number" min="0" max="50" step="1" required value="<?= $minHours ?>" inputmode="numeric"></label>
                            <label>สูงสุด<input name="max_hours" type="number" min="0" max="50" step="1" required value="<?= $maxHours ?>" inputmode="numeric"></label>
                            <button class="btn" type="submit">บันทึก</button>
                        </form>
                    <?php else: ?>
                        <strong class="mono"><?= $minHours ?>–<?= $maxHours ?></strong>
                    <?php endif; ?>
                    <small class="muted">ลงแล้ว <?= $got ?> ชม.</small>
                    <?php if ($got > $maxHours): ?><small class="tone-danger">เกินสูงสุด (<?= $maxMode === 'required' ? 'ข้อบังคับ' : 'ข้อแนะนำ' ?>)</small><?php endif; ?>
                    <?php if ($minHours > 0 && $got < $minHours): ?><small class="<?= $minMode === 'required' ? 'tone-danger' : 'tone-muted' ?>">ยังไม่ถึงต่ำสุด (<?= $minMode === 'required' ? 'ข้อบังคับ' : 'ข้อแนะนำ' ?>)</small><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if ($teachers === []): ?><tr><td colspan="4" class="empty">ยังไม่มีครูในสถานศึกษานี้</td></tr><?php endif; ?>
        </tbody>
    </table>
    <p class="empty" data-dt-empty <?= $teachers === [] ? '' : 'hidden' ?>><?= $teachers === [] ? 'ยังไม่มีครูในสถานศึกษานี้' : 'ไม่พบครูที่ตรงกับคำค้น' ?></p>
    <div class="dt-foot">
        <span data-dt-info></span>
        <div class="dt-pages" data-dt-pages></div>
    </div>
</div>
<?php elseif ($tab === 'groups'): ?>
<?php $assignTermId = is_array($term) ? (int) $term['id'] : 0; ?>
<div class="card toolbar">
    <?php if ($terms !== []): ?>
        <form method="get" action="<?= e(url('/data')) ?>" class="term-switch">
            <input type="hidden" name="tab" value="groups">
            <label>ภาคเรียนที่มอบหมาย
                <select name="term" onchange="this.form.submit()" aria-label="ภาคเรียนที่มอบหมายผู้จัดตาราง">
                    <?php foreach ($terms as $item): ?>
                        <option value="<?= (int) $item['id'] ?>" <?= (int) $item['id'] === $assignTermId ? 'selected' : '' ?>><?= e($item['label']) ?><?= (int) $item['is_current'] === 1 ? ' (ปัจจุบัน)' : '' ?> · <?= (int) $item['group_count'] ?> กลุ่ม</option>
                    <?php endforeach; ?>
                </select>
            </label>
        </form>
    <?php endif; ?>
    <?php if ($canEditPlan && $assignTermId > 0): ?>
        <form method="post" action="<?= e(url('/data')) ?>" class="degree-add">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="add_degree_group">
            <input type="hidden" name="term_id" value="<?= $assignTermId ?>">
            <label>กลุ่มปริญญาตรี
                <input name="name" required maxlength="255" placeholder="เช่น บธ.บ. 2/1" aria-label="ชื่อกลุ่มปริญญาตรี">
            </label>
            <label>จำนวนผู้เรียน
                <input name="student_count" type="number" min="0" max="2000" value="0" required aria-label="จำนวนผู้เรียน">
            </label>
            <button class="btn btn-primary" type="submit">เพิ่มกลุ่ม</button>
        </form>
    <?php endif; ?>
</div>
<p class="hint">รายการด้านล่างเป็นกลุ่มของภาคเรียนที่เลือก มอบหมายแล้วมีผลกับกลุ่มเดียวกันในทุกภาคเรียน คนที่ได้รับมอบหมายจะเห็นเฉพาะกลุ่มของตนเองในหน้าจัดตาราง กลุ่มปริญญาตรีไม่มีใน RMS ผู้ดูแลสถานศึกษาเพิ่มได้เองและระบุจำนวนผู้เรียนของกลุ่มนั้น ระดับตรวจจากรหัสกลุ่มหลักที่ 3: เลข 2 คือ ปวช. เลข 3 คือ ปวส. ชื่อย่อที่ขึ้นต้นด้วย ช หรือ ส ตรงกับสองระดับนี้ และ ทล.บ. คือปริญญาตรี ข้อความในวงเล็บเป็นที่มาของผู้เรียน เช่น จบ ปวช. หรือจบ ม.6</p>
<?php if ($canEditPlan && $assignTermId > 0): ?>
<div class="card twin-card">
    <h2>กลุ่มแฝด</h2>
    <p class="hint">แต่ละแถวคือหนึ่งคู่ ซ้ายกับขวาคือกลุ่มที่เรียนเหมือนกัน ตอนจัดตารางคาบที่กลุ่มแฝดลงไว้แล้วจะเป็นสีเหลือง</p>
    <?php
    $twinWarnings = array_values(array_filter($twinSets, static fn (array $set): bool => empty($set['alike'])));
    ?>
    <?php if ($twinWarnings !== []): ?>
        <div class="banner warn"><i class="bi bi-exclamation-triangle-fill"></i> มีการจับคู่ที่แผนการเรียนไม่เหมือนกัน <?= e(implode(' และ ', array_map(static fn (array $set): string => implode(' กับ ', array_map(static fn (array $member): string => (string) $member['name'], $set['groups'])), $twinWarnings))) ?> ควรเลิกจับกลุ่มนั้น</div>
    <?php endif; ?>
    <?php if ($twinSets !== []): ?><h3>จับไว้แล้ว</h3><?php endif; ?>
    <?php foreach ($twinSets as $set): ?>
        <div class="twin-row<?= empty($set['alike']) ? ' is-warn' : '' ?>">
            <div class="twin-sides">
                <?php foreach ($set['groups'] as $index => $member): ?>
                    <?php if ($index > 0): ?><span class="twin-join">คู่กับ</span><?php endif; ?>
                    <div class="twin-side"><small><?= e((string) ($member['level'] ?? '')) ?></small><strong><?= e((string) $member['name']) ?></strong></div>
                <?php endforeach; ?>
            </div>
            <div>
                <?php if (empty($set['alike'])): ?><strong class="tone-danger">จับคู่ไม่เหมือนกัน</strong><?php endif; ?>
                <small class="<?= empty($set['alike']) ? 'tone-danger' : '' ?>"><?= e((string) ($set['detail'] ?? '') !== '' ? (string) $set['detail'] : 'ไม่พบรายวิชาที่ตรงกัน') ?></small>
            </div>
            <form method="post" action="<?= e(url('/data')) ?>" onsubmit="return confirm('เลิกจับกลุ่มแฝดนี้')">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="delete_twin_set">
                <input type="hidden" name="term_id" value="<?= $assignTermId ?>">
                <input type="hidden" name="twin_id" value="<?= (int) $set['id'] ?>">
                <button class="btn" type="submit">เลิกจับกลุ่ม</button>
            </form>
        </div>
    <?php endforeach; ?>
    <?php if ($twinSuggestions['pairs'] !== []): ?><h3>คู่ที่เรียนเหมือนกัน</h3><?php endif; ?>
    <?php foreach ($twinSuggestions['pairs'] as $pair): ?>
        <div class="twin-row">
            <div class="twin-sides">
                <?php foreach ($pair['groups'] as $index => $member): ?>
                    <?php if ($index > 0): ?><span class="twin-join">คู่กับ</span><?php endif; ?>
                    <div class="twin-side"><small><?= e((string) $member['level']) ?></small><strong><?= e((string) $member['name']) ?></strong></div>
                <?php endforeach; ?>
            </div>
            <small><?= e($pair['detail']) ?></small>
            <form method="post" action="<?= e(url('/data')) ?>">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="save_twin_set">
                <input type="hidden" name="term_id" value="<?= $assignTermId ?>">
                <?php foreach ($pair['ids'] as $pairId): ?>
                    <input type="hidden" name="group_ids[]" value="<?= (int) $pairId ?>">
                <?php endforeach; ?>
                <button class="btn btn-primary" type="submit">จับคู่</button>
            </form>
        </div>
    <?php endforeach; ?>
    <?php foreach ($twinSuggestions['notes'] as $note): ?>
        <div class="banner warn"><?= e($note['label']) ?> <?= e($note['detail']) ?></div>
    <?php endforeach; ?>
    <?php if ($twinSets === [] && $twinSuggestions['pairs'] === [] && $twinSuggestions['notes'] === []): ?>
        <p class="empty">ภาคเรียนนี้ยังไม่พบคู่ที่แผนการเรียนเหมือนกัน</p>
    <?php endif; ?>
</div>
<?php endif; ?>
<div class="card table-wrap">
    <table>
        <thead><tr><th>ภาคเรียน</th><th>กลุ่มผู้เรียน</th><th>ระดับ</th><th>ผู้เรียน</th><th>ครูที่ปรึกษา</th><th>ผู้จัดตาราง</th><th>ข้อสังเกตขนาดห้อง</th></tr></thead>
        <tbody>
        <?php foreach ($groups as $group): ?>
            <?php $assigned = $schedulers[(int) $group['id']] ?? []; ?>
            <tr>
                <td><?= e((string) ($group['term_label'] ?? '')) ?></td>
                <td>
                    <strong><?= e($group['name']) ?></strong>
                    <?php if ($canEditPlan): ?>
                        <?php if ((int) ($group['plan_id'] ?? 0) > 0): ?>
                            <a class="plan-link" href="<?= e(url('/data?tab=plans&plan=' . (int) $group['plan_id'])) ?>">แก้แผนการเรียน</a>
                        <?php else: ?>
                            <form method="post" action="<?= e(url('/data')) ?>" class="plan-create">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="action" value="create_group_plan">
                                <input type="hidden" name="group_id" value="<?= (int) $group['id'] ?>">
                                <button class="plan-link" type="submit">สร้างแผนการเรียน</button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if ($canEditPlan && (string) $group['level'] === 'ป.ตรี' && trim((string) ($group['rms_group_code'] ?? '')) === ''): ?>
                        <form method="post" action="<?= e(url('/data')) ?>" class="plan-create" onsubmit="return confirm('ลบกลุ่มปริญญาตรีนี้ และคาบที่ลงไว้ของกลุ่มนี้จะหายไปด้วย')">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="action" value="delete_degree_group">
                            <input type="hidden" name="term_id" value="<?= $assignTermId ?>">
                            <input type="hidden" name="group_id" value="<?= (int) $group['id'] ?>">
                            <button class="plan-link" type="submit">ลบกลุ่ม</button>
                        </form>
                    <?php endif; ?>
                </td>
                <td><?= e($group['level']) ?></td>
                <td class="mono">
                    <?php if ($canEditPlan && (string) $group['level'] === 'ป.ตรี' && trim((string) ($group['rms_group_code'] ?? '')) === ''): ?>
                        <form method="post" action="<?= e(url('/data')) ?>" class="count-edit">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="action" value="set_degree_count">
                            <input type="hidden" name="term_id" value="<?= $assignTermId ?>">
                            <input type="hidden" name="group_id" value="<?= (int) $group['id'] ?>">
                            <input name="student_count" type="number" min="0" max="2000" value="<?= (int) $group['student_count'] ?>" required aria-label="จำนวนผู้เรียนของ <?= e($group['name']) ?>">
                            <button class="btn" type="submit">บันทึก</button>
                        </form>
                    <?php else: ?>
                        <?= (int) $group['student_count'] ?> คน
                    <?php endif; ?>
                </td>
                <td><?= e($group['advisor_name'] ?: '—') ?></td>
                <td>
                    <div class="tags">
                        <?php foreach ($assigned as $scheduler): ?>
                            <?php if ($canAssign): ?>
                                <form method="post" action="<?= e(url('/data')) ?>">
                                    <?= Csrf::field() ?>
                                    <input type="hidden" name="action" value="remove_scheduler">
                                    <input type="hidden" name="term_id" value="<?= $assignTermId ?>">
                                    <input type="hidden" name="group_id" value="<?= (int) $group['id'] ?>">
                                    <input type="hidden" name="teacher_id" value="<?= (int) $scheduler['teacher_id'] ?>">
                                    <span><?= e($scheduler['name']) ?></span>
                                    <button class="tag-x" type="submit" aria-label="ยกเลิกการมอบหมาย">×</button>
                                </form>
                            <?php else: ?>
                                <span><?= e($scheduler['name']) ?></span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($canAssign): ?>
                        <form method="post" action="<?= e(url('/data')) ?>" class="scheduler-add">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="action" value="assign_scheduler">
                            <input type="hidden" name="term_id" value="<?= $assignTermId ?>">
                            <input type="hidden" name="group_id" value="<?= (int) $group['id'] ?>">
                            <select name="teacher_id" required>
                                <option value="">เลือกครู</option>
                                <?php foreach ($teachers as $teacher): ?>
                                    <option value="<?= (int) $teacher['id'] ?>"><?= e($teacher['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn" type="submit">มอบหมาย</button>
                        </form>
                    <?php elseif ($assigned === []): ?>
                        <span class="muted">—</span>
                    <?php endif; ?>
                </td>
                <td class="tone-<?= e((string) $group['note_tone']) ?>"><?= e($group['note'] ?: '—') ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($groups === []): ?><tr><td colspan="7" class="empty">ภาคเรียนนี้ยังไม่มีกลุ่มผู้เรียน</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php elseif ($tab === 'plans'): ?>
<?php if (is_array($planDetail)): ?>
<?php $planTermId = (int) ($planDetail['term_id'] ?? 0); ?>
<p class="hint"><a href="<?= e(url('/data?tab=plans' . ($planTermId > 0 ? '&term=' . $planTermId : ''))) ?>">← แผนการเรียนทั้งหมด</a></p>
<div class="card" data-plan-editor>
    <small class="muted"><?= e((string) ($planDetail['term_label'] ?? '')) ?></small>
    <?php if ($canEditPlan): ?>
        <form method="post" action="<?= e(url('/data')) ?>" class="plan-rename">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="rename_plan">
            <input type="hidden" name="plan_id" value="<?= (int) $planDetail['id'] ?>">
            <input name="name" value="<?= e((string) $planDetail['name']) ?>" required maxlength="255" aria-label="ชื่อแผนการเรียน">
            <button class="btn" type="submit">บันทึกชื่อ</button>
        </form>
    <?php else: ?>
        <h2><?= e((string) $planDetail['name']) ?></h2>
    <?php endif; ?>
    <p class="hint">ใช้กับ <?= count($planDetail['groups']) ?> กลุ่ม: <?= e($planDetail['groups'] !== [] ? implode(', ', array_map(static function (array $group): string {
        $code = trim((string) ($group['rms_group_code'] ?? ''));
        return $code !== '' ? $group['name'] . ' (' . $code . ')' : (string) $group['name'];
    }, $planDetail['groups'])) : '—') ?><?php if (count($planDetail['groups']) > 1): ?> การเพิ่ม ลบ หรือแก้รายวิชามีผลกับทุกกลุ่มที่ใช้แผนนี้<?php endif; ?> รหัสวิชาเดียวกันใช้ค่า ท-ป-น ล่าสุดในทุกแผน การบันทึกแต่ละแถวไม่โหลดหน้าใหม่</p>
    <p class="warn-note" data-plan-note hidden></p>
    <div class="plan-subject plan-subject-head" aria-hidden="true"><span>รหัส</span><span>ชื่อรายวิชา</span><span>ท</span><span>ป</span><span>น</span><span></span></div>
    <?php foreach ($planDetail['subjects'] as $subject): ?>
        <form method="post" action="<?= e(url('/data')) ?>" class="plan-subject">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="save_plan_subject">
            <input type="hidden" name="plan_id" value="<?= (int) $planDetail['id'] ?>">
            <input type="hidden" name="subject_id" value="<?= (int) $subject['id'] ?>">
            <input name="code" value="<?= e((string) $subject['code']) ?>" required maxlength="32" aria-label="รหัสวิชา" <?= $canEditPlan ? '' : 'readonly' ?>>
            <input name="name" value="<?= e((string) $subject['name']) ?>" required maxlength="255" aria-label="ชื่อรายวิชา" <?= $canEditPlan ? '' : 'readonly' ?>>
            <input name="theory" type="number" min="0" max="40" value="<?= (int) $subject['theory'] ?>" required aria-label="ทฤษฎี" data-hours-field <?= $canEditPlan ? '' : 'readonly' ?>>
            <input name="practice" type="number" min="0" max="40" value="<?= (int) $subject['practice'] ?>" required aria-label="ปฏิบัติ" data-hours-field <?= $canEditPlan ? '' : 'readonly' ?>>
            <input name="extra" type="number" min="0" max="40" value="<?= (int) $subject['extra'] ?>" required aria-label="ศึกษาด้วยตนเอง" data-hours-field <?= $canEditPlan ? '' : 'readonly' ?>>
            <?php if ($canEditPlan): ?>
                <span class="plan-actions">
                    <button class="btn" type="submit" data-plan-save>บันทึก</button>
                    <button class="btn btn-danger" type="submit" form="delete-subject-<?= (int) $subject['id'] ?>">ลบ</button>
                </span>
            <?php else: ?>
                <span></span>
            <?php endif; ?>
        </form>
        <?php if ($canEditPlan): ?>
            <form id="delete-subject-<?= (int) $subject['id'] ?>" method="post" action="<?= e(url('/data')) ?>" onsubmit="return confirm('ลบรายวิชานี้จากแผน และนำคาบที่ลงไว้ของวิชานี้ออกจากตาราง')">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="delete_plan_subject">
                <input type="hidden" name="plan_id" value="<?= (int) $planDetail['id'] ?>">
                <input type="hidden" name="subject_id" value="<?= (int) $subject['id'] ?>">
            </form>
        <?php endif; ?>
    <?php endforeach; ?>
    <?php if ($planDetail['subjects'] === []): ?><p class="empty">แผนนี้ยังไม่มีรายวิชา</p><?php endif; ?>
    <?php if ($canEditPlan): ?>
        <form method="post" action="<?= e(url('/data')) ?>" class="plan-subject plan-subject-add">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="add_plan_subject">
            <input type="hidden" name="plan_id" value="<?= (int) $planDetail['id'] ?>">
            <input name="code" required maxlength="32" placeholder="รหัสวิชา" aria-label="รหัสวิชาใหม่">
            <input name="name" required maxlength="255" placeholder="ชื่อรายวิชา" aria-label="ชื่อรายวิชาใหม่">
            <input name="theory" type="number" min="0" max="40" value="0" required aria-label="ทฤษฎี">
            <input name="practice" type="number" min="0" max="40" value="0" required aria-label="ปฏิบัติ">
            <input name="extra" type="number" min="0" max="40" value="0" required aria-label="ศึกษาด้วยตนเอง">
            <button class="btn btn-primary" type="submit">เพิ่มรายวิชา</button>
        </form>
    <?php endif; ?>
</div>
<?php if ($canEditPlan): ?>
<script>
(() => {
    const editor = document.querySelector("[data-plan-editor]");
    if (!editor) return;
    const note = editor.querySelector("[data-plan-note]");
    const showNote = (text) => {
        if (!note) return;
        note.hidden = text === "";
        note.textContent = text;
    };
    const syncButton = (form) => {
        const button = form.querySelector("[data-plan-save]");
        if (!button) return;
        const dirty = [...form.querySelectorAll("[data-hours-field]")].some((field) => field.value !== field.defaultValue);
        button.classList.toggle("is-dirty", dirty);
    };
    const markSaved = (form) => {
        form.querySelectorAll("input").forEach((field) => {
            if (field.type !== "hidden") field.defaultValue = field.value;
        });
        syncButton(form);
    };
    const postForm = async (form) => {
        const response = await fetch(form.getAttribute("action") || "", {
            method: "POST",
            body: new FormData(form),
            headers: { Accept: "application/json" },
        });
        const data = await response.json();
        if (!data.ok) throw new Error(data.message || "บันทึกไม่สำเร็จ");
        return data;
    };
    const field = (name, value, extra = {}) => {
        const input = document.createElement("input");
        input.name = name;
        Object.entries(extra).forEach(([key, item]) => input.setAttribute(key, item));
        input.value = value;
        input.defaultValue = value;
        return input;
    };
    const hidden = (name, value) => field(name, value, { type: "hidden" });
    const addRow = (subjectId, code, name, theory, practice, extra) => {
        const sample = editor.querySelector(".plan-subject-add");
        const planId = sample?.querySelector("[name=plan_id]")?.value || "";
        const csrf = sample?.querySelector("[name=_csrf]")?.value || "";
        const action = sample?.getAttribute("action") || "";
        const form = document.createElement("form");
        form.method = "post";
        form.setAttribute("action", action);
        form.className = "plan-subject";
        form.append(
            hidden("_csrf", csrf),
            hidden("action", "save_plan_subject"),
            hidden("plan_id", planId),
            hidden("subject_id", String(subjectId)),
            field("code", code, { required: "", maxlength: "32", "aria-label": "รหัสวิชา" }),
            field("name", name, { required: "", maxlength: "255", "aria-label": "ชื่อรายวิชา" }),
            field("theory", theory, { type: "number", min: "0", max: "40", required: "", "aria-label": "ทฤษฎี", "data-hours-field": "" }),
            field("practice", practice, { type: "number", min: "0", max: "40", required: "", "aria-label": "ปฏิบัติ", "data-hours-field": "" }),
            field("extra", extra, { type: "number", min: "0", max: "40", required: "", "aria-label": "ศึกษาด้วยตนเอง", "data-hours-field": "" })
        );
        const actions = document.createElement("span");
        actions.className = "plan-actions";
        const save = document.createElement("button");
        save.className = "btn";
        save.type = "submit";
        save.dataset.planSave = "";
        save.textContent = "บันทึก";
        const remove = document.createElement("button");
        remove.className = "btn btn-danger";
        remove.type = "submit";
        remove.textContent = "ลบ";
        remove.setAttribute("form", "delete-subject-" + subjectId);
        actions.append(save, remove);
        form.append(actions);
        const deletion = document.createElement("form");
        deletion.id = "delete-subject-" + subjectId;
        deletion.method = "post";
        deletion.action = action;
        deletion.addEventListener("submit", (event) => {
            if (!confirm("ลบรายวิชานี้จากแผน และนำคาบที่ลงไว้ของวิชานี้ออกจากตาราง")) event.preventDefault();
        });
        deletion.append(
            hidden("_csrf", csrf),
            hidden("action", "delete_plan_subject"),
            hidden("plan_id", planId),
            hidden("subject_id", String(subjectId))
        );
        sample.before(form, deletion);
        editor.querySelector(".empty")?.remove();
    };
    editor.addEventListener("input", (event) => {
        const form = event.target.closest(".plan-subject");
        if (!form || !event.target.matches("[data-hours-field]")) return;
        syncButton(form);
    });
    editor.addEventListener("submit", async (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || event.defaultPrevented) return;
        const action = form.querySelector("[name=action]")?.value || "";
        if (!["save_plan_subject", "add_plan_subject", "delete_plan_subject"].includes(action)) return;
        event.preventDefault();
        if (form.dataset.busy === "1") return;
        form.dataset.busy = "1";
        showNote("");
        try {
            const data = await postForm(form);
            if (action === "delete_plan_subject") {
                const subjectId = form.querySelector("[name=subject_id]")?.value || "";
                editor.querySelector(".plan-subject [name=subject_id][value='" + subjectId + "']")?.closest("form")?.remove();
                form.remove();
            } else if (action === "add_plan_subject") {
                addRow(
                    data.subject_id || 0,
                    form.querySelector("[name=code]")?.value || "",
                    form.querySelector("[name=name]")?.value || "",
                    form.querySelector("[name=theory]")?.value || "0",
                    form.querySelector("[name=practice]")?.value || "0",
                    form.querySelector("[name=extra]")?.value || "0"
                );
                form.querySelector("[name=code]").value = "";
                form.querySelector("[name=name]").value = "";
                ["theory", "practice", "extra"].forEach((name) => {
                    const input = form.querySelector("[name=" + name + "]");
                    input.value = "0";
                    input.defaultValue = "0";
                });
            } else {
                markSaved(form);
            }
        } catch (error) {
            showNote(error.message || "บันทึกไม่สำเร็จ");
        } finally {
            delete form.dataset.busy;
        }
    });
})();
</script>
<?php endif; ?>
<?php else: ?>
<?php $planTermId = is_array($term) ? (int) $term['id'] : 0; ?>
<?php if ($canEditPlan): ?><p class="hint">ค้นหาหรือเรียงคอลัมน์เพื่อเปิดแผนของกลุ่ม แล้วเพิ่ม ลบ หรือแก้รายวิชา แผนที่ใช้ร่วมกันหลายกลุ่มจะแก้พร้อมกัน</p><?php endif; ?>
<div class="card table-wrap" data-datatable data-empty-text="ภาคเรียนนี้ยังไม่มีแผนการเรียน">
    <div class="dt-bar">
        <?php if ($terms !== []): ?>
            <form method="get" action="<?= e(url('/data')) ?>" class="term-switch">
                <input type="hidden" name="tab" value="plans">
                <label>ภาคเรียน
                    <select name="term" onchange="this.form.submit()" aria-label="ภาคเรียนของแผนการเรียน">
                        <?php foreach ($terms as $item): ?>
                            <option value="<?= (int) $item['id'] ?>" <?= (int) $item['id'] === $planTermId ? 'selected' : '' ?>><?= e($item['label']) ?><?= (int) $item['is_current'] === 1 ? ' (ปัจจุบัน)' : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </form>
        <?php endif; ?>
        <label class="dt-field">แสดง
            <select data-dt-length aria-label="จำนวนแถวต่อหน้า">
                <option value="10">10</option>
                <option value="25" selected>25</option>
                <option value="50">50</option>
                <option value="100">100</option>
            </select>
        </label>
        <label class="dt-field dt-search">ค้นหา
            <input type="search" data-dt-search placeholder="ชื่อแผน รหัสกลุ่ม หรือกลุ่ม" aria-label="ค้นหาแผนการเรียน">
        </label>
    </div>
    <table>
        <thead>
            <tr>
                <th data-dt-sort="text" class="is-sorted">แผนการเรียน</th>
                <th data-dt-sort="text">รหัสกลุ่ม</th>
                <th data-dt-sort="number">รายวิชา</th>
                <th data-dt-sort="number">ชม./สัปดาห์</th>
                <th data-dt-sort="number">หน่วยกิต</th>
                <th data-dt-sort="text">ใช้กับ</th>
                <?php if ($canEditPlan): ?><th></th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($plans as $plan): ?>
            <?php
            $planNames = array_map(static fn (array $group): string => (string) $group['name'], $plan['groups']);
            $planCodes = array_values(array_filter(array_map(
                static fn (array $group): string => trim((string) ($group['rms_group_code'] ?? '')),
                $plan['groups']
            ), static fn (string $code): bool => $code !== ''));
            $planGroups = $planNames !== [] ? implode(', ', $planNames) : '—';
            $planCodeText = $planCodes !== [] ? implode(', ', $planCodes) : '—';
            ?>
            <tr data-dt-row data-search="<?= e(mb_strtolower($plan['name'] . ' ' . $planGroups . ' ' . $planCodeText . ' ' . (string) ($plan['term_label'] ?? ''))) ?>">
                <td data-text="<?= e((string) $plan['name']) ?>"><strong><?= e($plan['name']) ?></strong></td>
                <td class="mono" data-text="<?= e($planCodeText) ?>"><?= e($planCodeText) ?></td>
                <td data-value="<?= (int) $plan['subject_count'] ?>"><?= (int) $plan['subject_count'] ?></td>
                <td data-value="<?= (int) $plan['hours_per_week'] ?>"><?= (int) $plan['hours_per_week'] ?></td>
                <td data-value="<?= (int) $plan['credits'] ?>"><?= (int) $plan['credits'] ?></td>
                <td data-text="<?= e($planGroups) ?>"><?= e($planGroups) ?></td>
                <?php if ($canEditPlan): ?><td><a class="btn" href="<?= e(url('/data?tab=plans&plan=' . (int) $plan['id'])) ?>">แก้ไขรายวิชา</a></td><?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p class="empty" data-dt-empty <?= $plans === [] ? '' : 'hidden' ?>><?= $plans === [] ? 'ภาคเรียนนี้ยังไม่มีแผนการเรียน' : 'ไม่พบแผนที่ตรงกับคำค้น' ?></p>
    <div class="dt-foot">
        <span data-dt-info></span>
        <div class="dt-pages" data-dt-pages></div>
    </div>
</div>
<?php endif; ?>
<?php elseif ($tab === 'subjects'): ?>
<div class="card table-wrap">
    <table>
        <thead><tr><th>รหัสวิชา</th><th>ชื่อรายวิชา</th><th>ท</th><th>ป</th><th>น</th><th>ชม./สัปดาห์</th><th>แผน</th></tr></thead>
        <tbody>
        <?php foreach ($subjects as $subject): ?>
            <tr>
                <td class="mono"><?= e($subject['code']) ?></td>
                <td><?= e($subject['name']) ?></td>
                <td><?= (int) $subject['theory'] ?></td>
                <td><?= (int) $subject['practice'] ?></td>
                <td><?= (int) $subject['extra'] ?></td>
                <td><strong><?= (int) $subject['theory'] + (int) $subject['practice'] ?></strong></td>
                <td><?= e($subject['plan_name']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($subjects === []): ?><tr><td colspan="7" class="empty">ยังไม่มีรายวิชา</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php else: ?>
<p class="hint">นำเข้าอาคารจับคู่ด้วยชื่อ และนำเข้าห้องเรียนจับคู่ด้วยรหัสห้อง รายการที่ไม่มีในไฟล์จะไม่ถูกลบ ถ้าเป็นอาคารใหม่ให้นำเข้าอาคารก่อน แล้วจึงนำเข้าห้องที่อ้างชื่ออาคารนั้น ช่องพิกัดที่เว้นว่างจะไม่ลบหมุดเดิม</p>
<div class="split">
    <section class="card">
        <header class="card-head"><strong>อาคารเรียนและพิกัด</strong><span class="hint">ละติจูด, ลองจิจูด</span></header>
        <?php if ($canEdit): ?>
            <form method="post" action="<?= e(url('/data')) ?>" class="data-form" data-building-form>
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="add_building">
                <label>ชื่ออาคาร<input name="name" required maxlength="255" placeholder="เช่น อาคาร 5 เทคโนโลยีสารสนเทศ"></label>
                <label>ชื่อย่อ<input name="short_name" maxlength="64" placeholder="เช่น อาคาร 5"></label>
                <label>วิทยาเขต<input name="campus" maxlength="255" placeholder="เช่น วิทยาเขตหลัก"></label>
                <div class="coord-pair">
                    <label>ละติจูด<input name="lat" required inputmode="decimal" placeholder="16.057431" data-lat></label>
                    <label>ลองจิจูด<input name="lng" required inputmode="decimal" placeholder="103.653679" data-lng></label>
                </div>
                <label>ระยะเดินจากอาคาร 1<input name="dist_label" maxlength="64" placeholder="เช่น 3 นาที"></label>
                <button class="btn btn-primary" type="submit"><i class="bi bi-plus-lg"></i> เพิ่มอาคารเรียน</button>
            </form>
        <?php endif; ?>
        <?php
        $origin = null;
        foreach ($buildings as $candidate) {
            if (is_numeric($candidate['lat']) && is_numeric($candidate['lng']) && $candidate['lat'] !== '' && $candidate['lng'] !== '') {
                $origin = $candidate;
                break;
            }
        }
        foreach ($buildings as $building):
            $placed = is_numeric($building['lat']) && is_numeric($building['lng']) && $building['lat'] !== '' && $building['lng'] !== '';
            $meta = $building['campus'] !== '' ? $building['campus'] : 'ไม่ระบุวิทยาเขต';
            if ($placed) {
                $meta .= ' · ' . $building['lat'] . ', ' . $building['lng'];
                if ($origin && (int) $origin['id'] !== (int) $building['id']) {
                    $km = geo_kilometers((float) $origin['lat'], (float) $origin['lng'], (float) $building['lat'], (float) $building['lng']);
                    $meta .= ' · ' . number_format($km, 2) . ' กม. จาก ' . $origin['short_name'];
                }
            } else {
                $meta .= ' · ยังไม่มีพิกัด';
            }
            ?>
            <div class="building-row">
                <span><strong><?= e($building['name']) ?></strong><small class="mono"><?= e($meta) ?></small></span>
                <?php if ($canEdit && !$placed): ?>
                    <button class="btn" type="button" data-pin="<?= (int) $building['id'] ?>">ปักหมุดบนแผนที่</button>
                <?php else: ?>
                    <em style="background: <?= e($building['badge_bg']) ?>; color: <?= e($building['badge_fg']) ?>"><?= e($building['dist_label'] !== '' ? $building['dist_label'] : ($placed ? 'ปักหมุดแล้ว' : '—')) ?></em>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <?php if ($buildings === []): ?><p class="empty">ยังไม่มีอาคาร</p><?php endif; ?>
    </section>
    <div class="stack-cards">
        <section class="card">
            <header class="card-head"><strong>แผนที่อาคารเรียน</strong></header>
            <?php
            $mapPoints = [];
            foreach ($buildings as $building) {
                if (!is_numeric($building['lat']) || !is_numeric($building['lng']) || $building['lat'] === '' || $building['lng'] === '') {
                    continue;
                }
                $mapPoints[] = [
                    'id' => (int) $building['id'],
                    'name' => (string) $building['short_name'],
                    'title' => (string) $building['name'],
                    'lat' => (float) $building['lat'],
                    'lng' => (float) $building['lng'],
                ];
            }
            ?>
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">
            <div class="campus-map" id="campus-map"
                data-url="<?= e(url('/data')) ?>"
                data-csrf="<?= e(Csrf::token()) ?>"
                data-edit="<?= $canEdit ? '1' : '0' ?>"
                data-points="<?= e(json_encode($mapPoints, JSON_UNESCAPED_UNICODE)) ?>"></div>
            <p class="hint" data-map-hint>คลิกบนแผนที่เพื่อใส่ละติจูดและลองจิจูดของอาคารใหม่<?= $canEdit ? ' · ลากหมุดเพื่อย้ายอาคารที่ปักไว้แล้ว' : '' ?></p>
        </section>
        <section class="card">
            <header class="card-head"><strong>ห้องเรียน</strong></header>
            <?php if ($canEdit): ?>
                <form method="post" action="<?= e(url('/data')) ?>" class="data-form">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="action" value="add_room">
                    <label>รหัสห้อง<input name="code" required maxlength="32" placeholder="เช่น 5-201"></label>
                    <label>อาคาร
                        <select name="building_id">
                            <option value="0">ไม่ระบุอาคาร</option>
                            <?php foreach ($buildings as $building): ?>
                                <option value="<?= (int) $building['id'] ?>"><?= e($building['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>ประเภทห้อง<input name="room_type" maxlength="255" placeholder="เช่น ห้องเรียนทฤษฎี"></label>
                    <label>ความจุ (ที่นั่ง)<input name="capacity" type="number" min="0" max="999" value="0"></label>
                    <button class="btn btn-primary" type="submit"><i class="bi bi-plus-lg"></i> เพิ่มห้องเรียน</button>
                </form>
            <?php endif; ?>
            <?php foreach ($rooms as $room): ?>
                <div class="room-row">
                    <span class="mono"><?= e($room['code']) ?></span>
                    <span><?= e($room['room_type'] !== '' ? $room['room_type'] : 'ห้องเรียน') ?><small><?= e(($room['building_name'] ?? '') !== '' ? $room['building_name'] : 'ไม่ระบุอาคาร') ?></small></span>
                    <em><?= (int) $room['capacity'] ?> ที่</em>
                </div>
            <?php endforeach; ?>
            <?php if ($rooms === []): ?><p class="empty">ยังไม่มีห้องเรียน</p><?php endif; ?>
        </section>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(() => {
    const root = document.getElementById("campus-map");
    if (!root || typeof L === "undefined") return;
    const points = JSON.parse(root.dataset.points || "[]");
    const canEdit = root.dataset.edit === "1";
    const hint = document.querySelector("[data-map-hint]");
    const latInput = document.querySelector("[data-building-form] [data-lat]");
    const lngInput = document.querySelector("[data-building-form] [data-lng]");
    const campus = [16.057431, 103.653679];
    const map = L.map(root, { scrollWheelZoom: true }).setView(campus, 16);
    L.tileLayer("https://{s}.tile.openstreetmap.fr/osmfr/{z}/{x}/{y}.png", {
        maxZoom: 20,
        subdomains: "abc",
        attribution: "&copy; OpenStreetMap France | &copy; OpenStreetMap",
    }).addTo(map);
    const escapeHtml = (value) => String(value).replace(/[&<>"']/g, (char) => ({
        "&": "&amp;", "<": "&lt;", ">": "&gt;", "\"": "&quot;", "'": "&#39;",
    }[char]));
    const iconFor = (label) => {
        const width = Math.min(168, Math.max(56, String(label).length * 14 + 28));
        return L.divIcon({
            className: "campus-pin",
            html: "<span>" + escapeHtml(label) + "</span>",
            iconSize: [width, 28],
            iconAnchor: [width / 2, 14],
        });
    };
    const markers = [];
    points.forEach((point) => {
        const marker = L.marker([point.lat, point.lng], { draggable: canEdit, icon: iconFor(point.name) }).addTo(map);
        marker.bindPopup("<strong>" + escapeHtml(point.title) + "</strong><br>" + point.lat.toFixed(6) + ", " + point.lng.toFixed(6));
        if (canEdit) {
            marker.on("dragend", () => savePoint(point.id, marker.getLatLng()));
        }
        markers.push(marker);
    });
    if (points.length === 1) {
        map.setView([points[0].lat, points[0].lng], 17);
    } else if (points.length > 1) {
        map.fitBounds(L.featureGroup(markers).getBounds().pad(0.4), { maxZoom: 17 });
    }
    let draft = null;
    let armed = 0;
    const placeDraft = (latlng) => {
        if (latInput) latInput.value = latlng.lat.toFixed(6);
        if (lngInput) lngInput.value = latlng.lng.toFixed(6);
        if (draft) draft.setLatLng(latlng);
        else draft = L.marker(latlng, { icon: iconFor("ใหม่") }).addTo(map);
    };
    const savePoint = async (id, latlng) => {
        const body = new FormData();
        body.set("_csrf", root.dataset.csrf || "");
        body.set("action", "set_point");
        body.set("building_id", String(id));
        body.set("lat", latlng.lat.toFixed(6));
        body.set("lng", latlng.lng.toFixed(6));
        const response = await fetch(root.dataset.url || "", {
            method: "POST",
            body,
            headers: { Accept: "application/json" },
        });
        const data = await response.json();
        if (!data.ok) throw new Error(data.message || "บันทึกพิกัดไม่สำเร็จ");
        window.location.reload();
    };
    map.on("click", (event) => {
        if (!canEdit) return;
        if (armed > 0) {
            savePoint(armed, event.latlng).catch((error) => {
                if (hint) hint.textContent = error.message || "บันทึกพิกัดไม่สำเร็จ";
            });
            return;
        }
        placeDraft(event.latlng);
    });
    document.querySelectorAll("[data-pin]").forEach((button) => {
        button.addEventListener("click", () => {
            armed = Number(button.dataset.pin || 0);
            document.querySelectorAll("[data-pin].is-armed").forEach((item) => item.classList.remove("is-armed"));
            button.classList.add("is-armed");
            if (hint) hint.textContent = "คลิกบนแผนที่เพื่อปักหมุดอาคารนี้";
        });
    });
    window.setTimeout(() => map.invalidateSize(), 0);
})();
</script>
<?php endif; ?>
<script>
document.querySelectorAll("[data-datatable]").forEach((root) => {
    const rows = [...root.querySelectorAll("[data-dt-row]")];
    const tbody = root.querySelector("tbody");
    const search = root.querySelector("[data-dt-search]");
    const length = root.querySelector("[data-dt-length]");
    const info = root.querySelector("[data-dt-info]");
    const pages = root.querySelector("[data-dt-pages]");
    const empty = root.querySelector("[data-dt-empty]");
    const heads = [...root.querySelectorAll("th[data-dt-sort]")];
    if (!tbody || !info || !pages || !empty) return;
    let sortKey = 0;
    let sortDir = 1;
    let page = 1;
    const cellValue = (row, index, kind) => {
        const cell = row.children[index];
        if (!cell) return kind === "number" ? 0 : "";
        if (kind === "number") return Number(cell.dataset.value || 0);
        return cell.dataset.text || cell.textContent || "";
    };
    const renderPages = (pageCount) => {
        pages.replaceChildren();
        const add = (label, target, disabled, current) => {
            const button = document.createElement("button");
            button.type = "button";
            button.textContent = label;
            button.disabled = disabled;
            if (current) button.className = "on";
            if (!disabled && !current) button.addEventListener("click", () => { page = target; apply(); });
            pages.appendChild(button);
        };
        add("ก่อนหน้า", page - 1, page <= 1, false);
        const marks = new Set([1, pageCount, page - 1, page, page + 1]);
        let skipped = false;
        for (let i = 1; i <= pageCount; i += 1) {
            if (!marks.has(i)) {
                if (!skipped) add("…", page, true, false);
                skipped = true;
                continue;
            }
            skipped = false;
            add(String(i), i, false, i === page);
        }
        add("ถัดไป", page + 1, page >= pageCount, false);
    };
    const apply = () => {
        const query = (search?.value || "").trim().toLowerCase();
        const kind = heads[sortKey]?.dataset.dtSort || "text";
        const matched = rows.filter((row) => (row.dataset.search || "").includes(query));
        matched.sort((a, b) => {
            const av = cellValue(a, sortKey, kind);
            const bv = cellValue(b, sortKey, kind);
            if (kind === "number") return (av - bv) * sortDir;
            return String(av).localeCompare(String(bv), "th") * sortDir;
        });
        const size = Math.max(1, Number(length?.value) || matched.length || 1);
        const pageCount = Math.max(1, Math.ceil(matched.length / size));
        if (page > pageCount) page = pageCount;
        const start = (page - 1) * size;
        const visible = new Set(matched.slice(start, start + size));
        matched.forEach((row) => tbody.appendChild(row));
        rows.forEach((row) => { row.hidden = !visible.has(row); });
        const end = Math.min(matched.length, start + size);
        info.textContent = matched.length === 0
            ? "ไม่พบรายการ"
            : "แสดง " + (start + 1) + "–" + end + " จาก " + matched.length + " รายการ" + (matched.length !== rows.length ? " (กรองจาก " + rows.length + ")" : "");
        empty.hidden = matched.length !== 0;
        if (rows.length === 0 && root.dataset.emptyText) empty.textContent = root.dataset.emptyText;
        renderPages(matched.length === 0 ? 1 : pageCount);
        pages.hidden = matched.length === 0;
    };
    heads.forEach((head, index) => {
        head.addEventListener("click", () => {
            if (sortKey === index) sortDir *= -1;
            else { sortKey = index; sortDir = 1; }
            heads.forEach((item) => {
                item.classList.toggle("is-sorted", item === head);
                item.classList.toggle("is-desc", item === head && sortDir < 0);
            });
            page = 1;
            apply();
        });
    });
    if (search) search.addEventListener("input", () => { page = 1; apply(); });
    if (length) length.addEventListener("change", () => { page = 1; apply(); });
    apply();
});
document.querySelectorAll("[data-hint-toggle]").forEach((button) => {
    button.addEventListener("click", () => {
        const fold = button.closest("[data-hint-fold]");
        if (!fold) return;
        const collapsed = fold.classList.toggle("is-collapsed");
        button.setAttribute("aria-expanded", collapsed ? "false" : "true");
        button.innerHTML = collapsed
            ? '<i class="bi bi-chevron-down"></i> แสดงคำอธิบาย'
            : '<i class="bi bi-chevron-up"></i> ย่อคำอธิบาย';
    });
});
</script>
