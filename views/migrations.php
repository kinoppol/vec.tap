<section class="card">
    <header class="card-head">
        <strong>รายการปรับปรุงโครงสร้างฐานข้อมูล</strong>
        <form method="post" action="<?= e(url('/migrations')) ?>">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="all">
            <button class="btn btn-primary" type="submit">รันรายการที่ค้างทั้งหมด</button>
        </form>
    </header>
    <div class="table-wrap">
        <table>
            <thead><tr><th>ไฟล์</th><th>รายละเอียด</th><th>สถานะ</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td class="mono"><?= e($item['version']) ?></td>
                    <td>
                        <?= e($item['name']) ?>
                        <?php if ($item['applied'] && !$item['checksum_match']): ?><small class="tone-danger">ไฟล์เปลี่ยนหลังจากรันแล้ว</small><?php endif; ?>
                        <details><summary>ดูคำสั่ง</summary><pre><?= e(implode("\n\n", $item['statements'])) ?></pre></details>
                    </td>
                    <td><?= $item['applied'] ? 'รันแล้ว ' . e(thai_datetime($item['applied_at'])) : 'ยังไม่รัน' ?></td>
                    <td>
                        <?php if (!$item['applied']): ?>
                            <form method="post" action="<?= e(url('/migrations')) ?>">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="action" value="one">
                                <input type="hidden" name="version" value="<?= e($item['version']) ?>">
                                <button class="btn" type="submit">รันรายการนี้</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
