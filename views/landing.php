<!DOCTYPE html>
<html lang="th">
<head>
<?php require app_root() . '/views/partials/head.php'; ?>
<title>ระบบจัดตารางเรียนอาชีวศึกษา</title>
</head>
<body class="landing">
<nav class="landing-nav">
    <div class="landing-inner nav-row">
        <a class="brand-light" href="<?= e(url('/')) ?>">
            <span class="logo-mark">สอศ.</span>
            <span><strong>ระบบจัดตารางเรียนอาชีวศึกษา</strong><small>VEC SMART TIMETABLE</small></span>
        </a>
        <div class="spacer"></div>
        <div class="nav-links">
            <a href="#features">ฟังก์ชัน</a>
            <a href="#steps">ขั้นตอนการทำงาน</a>
            <a href="#providers">ผู้ให้บริการ AI</a>
        </div>
        <a class="btn btn-primary" href="<?= e(url(Auth::check() ? '/dashboard' : '/login')) ?>">เข้าสู่ระบบ</a>
    </div>
</nav>
<header class="hero">
    <div class="landing-inner hero-grid">
        <div>
            <span class="eyebrow"><i class="bi bi-stars"></i> ผู้ช่วย AI สำหรับงานวิชาการ</span>
            <h1>จัดตารางเรียนตารางสอน<br><span>ตามนโยบายของสถานศึกษา</span><br>ด้วยผู้ช่วย AI</h1>
            <p>นำเข้าข้อมูลครู กลุ่มผู้เรียน แผนการเรียน รายวิชา อาคารและห้องเรียน แล้วให้ AI ลงตารางตามนโยบายที่เรียงลำดับความสำคัญไว้ ส่วนที่จัดไม่ได้ ระบบจะบอกสาเหตุและทางแก้ รองรับหลายสถานศึกษาในระบบเดียว</p>
            <div class="hero-actions">
                <a class="btn btn-primary btn-lg" href="<?= e(url(Auth::check() ? '/dashboard' : '/login')) ?>">เริ่มใช้งาน <i class="bi bi-arrow-right"></i></a>
                <a class="btn btn-lg" href="<?= e(url(Auth::check() ? '/schedule' : '/login')) ?>">ดูตัวอย่างการจัดตาราง</a>
            </div>
        </div>
        <div class="hero-card">
            <div class="hero-card-head"><strong>ปวช.1/1 เทคโนโลยีสารสนเทศ</strong><span>· 32 คน</span><em>ลงครบ 26/26 ชม.</em></div>
            <div class="mini-grid" aria-hidden="true">
                <span>จ.</span><span>อ.</span><span>พ.</span><span>พฤ.</span><span>ศ.</span>
                <b class="span3">วิทยาศาสตร์</b><b>กราฟิก</b><b class="outline">ไทย</b><b class="span2">อังกฤษ</b><b class="outline">โปรแกรม</b><b class="span3">คอมพิวเตอร์ฯ</b><b class="outline">ลูกเสือ</b><b class="span2">ระบบปฏิบัติการ</b><b>คณิต</b><b class="span2">ระบบปฏิบัติการ</b><b class="span3">กราฟิก</b>
            </div>
            <div class="hero-note"><i class="bi bi-stars"></i><span>ย้าย “การเขียนโปรแกรมเบื้องต้น” ที่ลงด้วยมือจากวันจันทร์เช้าไปวันอังคารบ่าย ทำให้ลงวิชาวิทยาศาสตร์ได้ครบ และไม่ขัดนโยบายข้อบังคับทุกข้อ</span></div>
        </div>
    </div>
</header>
<section id="features" class="section">
    <div class="landing-inner">
        <span class="kicker">ฟังก์ชันของระบบ</span>
        <h2>ครอบคลุมงานจัดตารางตั้งแต่ข้อมูลตั้งต้นจนถึงตารางที่ใช้งานจริง</h2>
        <div class="feature-grid">
            <?php foreach ($features as $feature): ?>
                <article>
                    <i class="bi <?= e($feature['icon']) ?>"></i>
                    <strong><?= e($feature['title']) ?></strong>
                    <p><?= e($feature['text']) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<section id="steps" class="steps">
    <div class="landing-inner">
        <h2>ขั้นตอนการทำงาน</h2>
        <div class="step-grid">
            <?php foreach ($steps as $step): ?>
                <article>
                    <span><?= e($step['no']) ?></span>
                    <strong><?= e($step['title']) ?></strong>
                    <p><?= e($step['text']) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<section id="providers" class="section">
    <div class="landing-inner provider-grid">
        <div>
            <span class="kicker">เชื่อมต่อ AI ได้หลายแหล่ง</span>
            <h2>เลือกผู้ให้บริการ LLM ที่สถานศึกษาใช้อยู่</h2>
            <p>ผู้ดูแลระบบกำหนด API Key และโมเดลได้เองในแต่ละสถานศึกษา ผู้ช่วย AI ทำงานแทนผู้ใช้ได้เฉพาะในขอบเขตสิทธิ์ของบทบาท ส่วนที่เกินสิทธิ์จะแนะนำว่าต้องติดต่อผู้ใดดำเนินการ</p>
        </div>
        <div class="provider-list">
            <?php foreach ($providers as $provider): ?>
                <article>
                    <i class="bi <?= e($provider['icon']) ?>"></i>
                    <span><strong><?= e($provider['name']) ?></strong><small><?= e($provider['desc']) ?></small></span>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<footer class="landing-foot">
    <div class="landing-inner foot-row">
        <span class="logo-mark lg">สอศ.</span>
        <span><strong>พร้อมจัดตารางภาคเรียนถัดไป</strong><small>ระบบจัดตารางเรียนอาชีวศึกษา · สำนักงานคณะกรรมการการอาชีวศึกษา</small></span>
        <a class="btn btn-primary" href="<?= e(url(Auth::check() ? '/dashboard' : '/login')) ?>">เข้าสู่ระบบ</a>
    </div>
</footer>
</body>
</html>
