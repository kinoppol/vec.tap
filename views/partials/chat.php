<button class="fab" type="button" data-open-chat aria-label="เปิดผู้ช่วย AI">
    <span class="fab-face" aria-hidden="true"></span>
    <i class="bi bi-stars"></i>
</button>
<aside class="chat" data-chat hidden>
    <header>
        <span class="chat-badge"><i class="bi bi-stars"></i></span>
        <span><strong>ผู้ช่วย AI</strong><small>ทำงานในสิทธิ์ของ: <?= e($shell['roleLabel']) ?></small></span>
        <button type="button" data-close-chat aria-label="ปิด"><i class="bi bi-x-lg"></i></button>
    </header>
    <div class="chat-log" data-chat-log>
        <?php foreach ($shell['chat'] as $message): ?>
            <div class="<?= !empty($message['me']) ? 'me' : 'ai' ?>"><?= e($message['text']) ?></div>
        <?php endforeach; ?>
    </div>
    <div class="quick">
        <?php foreach ($shell['prompts'] as $prompt): ?>
            <button type="button" data-prompt="<?= e($prompt) ?>"><?= e($prompt) ?></button>
        <?php endforeach; ?>
    </div>
    <form data-chat-form action="<?= e(url('/chat')) ?>" method="post">
        <?= Csrf::field() ?>
        <textarea name="message" rows="1" placeholder="พิมพ์หรือกดไมค์เพื่อพูด อธิบายยาว ๆ ได้" data-chat-input></textarea>
        <div class="chat-tools">
            <span data-chat-status>0 ตัวอักษร</span>
            <button type="button" data-mic aria-label="พิมพ์ด้วยเสียง"><i class="bi bi-mic-fill"></i></button>
            <button type="submit" aria-label="ส่ง"><i class="bi bi-send-fill"></i></button>
        </div>
    </form>
</aside>
