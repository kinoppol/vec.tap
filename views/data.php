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
    <div class="spacer"></div>
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
<p class="hint"><?= e($hourHint) ?></p>
<p class="hint">ไฟล์ทักษะมีคอลัมน์ชื่อกับทักษะ หนึ่งแถวต่อหนึ่งทักษะ หรือหลายทักษะในช่องเดียวคั่นด้วย | การนำเข้าแทนที่ทักษะของครูที่มีชื่อในไฟล์</p>
<?php if ($canAssign): ?><p class="hint">สร้างบัญชีจากชื่อครูได้ในคอลัมน์ครูผู้สอน ชื่อผู้ใช้คือเลขประจำตัวประชาชน 13 หลัก บัญชีนี้จัดตารางได้เฉพาะกลุ่มที่มอบหมายในแท็บกลุ่มผู้เรียน</p><?php endif; ?>
<div class="card table-wrap">
    <table class="teacher-table">
        <colgroup>
            <col class="col-name">
            <col class="col-dept">
            <col class="col-skill">
            <col class="col-hours">
        </colgroup>
        <thead><tr><th>ครูผู้สอน</th><th>แผนก / วุฒิ</th><th>ทักษะการสอน</th><th>ชั่วโมง/สัปดาห์<small>ต่ำสุด–สูงสุด</small></th></tr></thead>
        <tbody>
        <?php foreach ($teachers as $teacher): ?>
            <tr>
                <td>
                    <strong><?= e($teacher['name']) ?></strong>
                    <?php
                    $account = $teacherAccounts[(int) $teacher['id']] ?? '';
                    $citizenId = (string) ($teacher['rms_people_id'] ?? '');
                    $hasCitizenId = preg_match('/^\d{13}$/', $citizenId) === 1;
                    ?>
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
                <td><?= e($teacher['dept']) ?><small><?= e($teacher['degree']) ?></small></td>
                <td>
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
                <td>
                    <?php
                    $got = (int) ($teachingHours[(int) $teacher['id']] ?? 0);
                    $minHours = (int) ($teacher['min_hours'] ?? 0);
                    $maxHours = (int) $teacher['max_hours'];
                    ?>
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
</div>
<p class="hint">รายการด้านล่างเป็นกลุ่มของภาคเรียนที่เลือก มอบหมายแล้วมีผลกับกลุ่มเดียวกันในทุกภาคเรียน คนที่ได้รับมอบหมายจะเห็นเฉพาะกลุ่มของตนเองในหน้าจัดตาราง</p>
<div class="card table-wrap">
    <table>
        <thead><tr><th>ภาคเรียน</th><th>กลุ่มผู้เรียน</th><th>ระดับ</th><th>ผู้เรียน</th><th>ครูที่ปรึกษา</th><th>ผู้จัดตาราง</th><th>ข้อสังเกตขนาดห้อง</th></tr></thead>
        <tbody>
        <?php foreach ($groups as $group): ?>
            <?php $assigned = $schedulers[(int) $group['id']] ?? []; ?>
            <tr>
                <td><?= e((string) ($group['term_label'] ?? '')) ?></td>
                <td><strong><?= e($group['name']) ?></strong></td>
                <td><?= e($group['level']) ?></td>
                <td class="mono"><?= (int) $group['student_count'] ?> คน</td>
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
<div class="plan-grid">
    <?php foreach ($plans as $plan): ?>
        <article class="card plan">
            <small><?= e($plan['term_label']) ?></small>
            <strong><?= e($plan['name']) ?></strong>
            <p><b><?= (int) $plan['subject_count'] ?></b> รายวิชา <b><?= (int) $plan['hours_per_week'] ?></b> ชม./สัปดาห์ <b><?= (int) $plan['credits'] ?></b> หน่วยกิต</p>
            <span>ใช้กับ: <?= e($plan['groups'] ? implode(', ', $plan['groups']) : '—') ?></span>
        </article>
    <?php endforeach; ?>
    <?php if ($plans === []): ?><p class="empty">ยังไม่มีแผนการเรียน</p><?php endif; ?>
</div>
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
