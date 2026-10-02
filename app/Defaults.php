<?php
declare(strict_types=1);

function ai_catalog(): array
{
    return [
        'openrouter' => [
            'name' => 'OpenRouter',
            'icon' => 'bi-shuffle',
            'desc' => 'เลือกใช้ได้หลายโมเดลผ่าน API เดียว',
            'url' => 'https://openrouter.ai/api/v1',
            'models' => ['anthropic/claude-sonnet-4.5', 'google/gemini-2.5-pro', 'openai/gpt-4.1'],
        ],
        'google' => [
            'name' => 'Google AI Studio',
            'icon' => 'bi-google',
            'desc' => 'ใช้โมเดล Gemini ด้วย API Key จาก AI Studio',
            'url' => 'https://generativelanguage.googleapis.com/v1beta',
            'models' => ['gemini-2.5-pro', 'gemini-2.5-flash'],
        ],
        'custom' => [
            'name' => 'Server LLM ของสถานศึกษา',
            'icon' => 'bi-hdd-rack',
            'desc' => 'เซิร์ฟเวอร์ที่รองรับ OpenAI-compatible API เช่น Ollama, vLLM',
            'url' => 'http://llm.college.local:11434/v1',
            'models' => ['llama3.1:70b', 'qwen2.5:32b', 'typhoon2-8b'],
        ],
    ];
}

function default_policies(): array
{
    return [
        ['code' => 'no_morning_gap', 'short' => 'ปวช. ไม่เว้นคาบว่างช่วงเช้า', 'text' => 'ตารางนักเรียนระดับ ปวช. จะต้องไม่มีการเว้นคาบว่างในช่วงเช้า', 'type' => 'required'],
        ['code' => 'lunch_pvc', 'short' => 'ปวช. พักกลางวัน 11–12 น.', 'text' => 'นักเรียน ปวช. จะต้องพักกลางวันเวลา 11–12 น.', 'type' => 'required'],
        ['code' => 'lunch_hvc', 'short' => 'ปวส./ป.ตรี พักกลางวัน 12–13 น.', 'text' => 'นักศึกษาระดับ ปวส. และปริญญาตรีจะต้องพักกลางวันเวลา 12–13 น.', 'type' => 'required'],
        ['code' => 'end_by_17', 'short' => 'ปวช. เลิกเรียนไม่เกิน 17 น.', 'text' => 'นักเรียน ปวช. จะมีคาบเรียนสุดท้ายได้ไม่เกิน 17 น.', 'type' => 'required'],
        ['code' => 'split_even', 'short' => 'แบ่งครึ่งวิชาหลายชั่วโมง', 'text' => 'หากวิชาที่มีหลายชั่วโมงไม่สามารถลงได้หมด ให้พยายามแบ่งครึ่ง เช่น รายวิชา 4 ชั่วโมงควรแบ่งเป็นคาบละ 2 ชั่วโมง รายวิชา 5 ชั่วโมงควรแบ่งเป็นคาบละ 2 และ 3', 'type' => 'recommended'],
        ['code' => 'max_segments', 'short' => 'แบ่งคาบย่อยไม่เกิน 3 ส่วน', 'text' => 'รายวิชาที่มีหลายชั่วโมงสามารถแบ่งเป็นคาบย่อย ๆ ได้ไม่เกิน 3 ส่วน', 'type' => 'required'],
        ['code' => 'travel', 'short' => 'ระยะเดินทางระหว่างคาบ', 'text' => 'คาบติดกันที่ต้องย้ายอาคารหรือวิทยาเขต ไม่ควรใช้เวลาเดินทางเกิน 10 นาที', 'type' => 'recommended'],
        ['code' => 'teacher_hours', 'short' => 'ชั่วโมงสอนตามช่วงของครู', 'text' => 'ชั่วโมงสอนต่อสัปดาห์ของครูแต่ละคนควรอยู่ระหว่างค่าต่ำสุดและค่าสูงสุดที่กำหนดไว้ในข้อมูลครู หากนโยบายนี้เป็นข้อบังคับ จะลงคาบที่ทำให้เกินชั่วโมงสูงสุดไม่ได้ และครูที่ชั่วโมงยังไม่ถึงค่าต่ำสุดจะถือว่ายังไม่ครบตามนโยบาย', 'type' => 'recommended'],
    ];
}

function insert_default_term(PDO $pdo, int $schoolId): int
{
    $statement = $pdo->prepare(
        'INSERT INTO terms (school_id, label, is_current) VALUES (:school_id, :label, 1)'
    );
    $statement->execute([
        'school_id' => $schoolId,
        'label' => 'ภาคเรียนที่ 2/2569',
    ]);
    return (int) $pdo->lastInsertId();
}

function insert_default_policies(PDO $pdo, int $schoolId): void
{
    $statement = $pdo->prepare(
        'INSERT INTO policies (school_id, sort_order, code, short_text, body, policy_type, enabled)
         VALUES (:school_id, :sort_order, :code, :short_text, :body, :policy_type, 1)'
    );
    foreach (default_policies() as $index => $policy) {
        $statement->execute([
            'school_id' => $schoolId,
            'sort_order' => $index + 1,
            'code' => $policy['code'],
            'short_text' => $policy['short'],
            'body' => $policy['text'],
            'policy_type' => $policy['type'],
        ]);
    }
}

function insert_default_ai(PDO $pdo, int $schoolId): void
{
    $provider = ai_catalog()['openrouter'];
    $statement = $pdo->prepare(
        'INSERT INTO ai_settings (
            school_id, provider, base_url, api_key_encrypted, api_key_hint, model,
            allow_act, require_confirm, suggest_contact, log_actions
         ) VALUES (
            :school_id, :provider, :base_url, NULL, NULL, :model, 1, 1, 1, 1
         )'
    );
    $statement->execute([
        'school_id' => $schoolId,
        'provider' => 'openrouter',
        'base_url' => $provider['url'],
        'model' => $provider['models'][0],
    ]);
}

function create_school(PDO $pdo, string $name): int
{
    $statement = $pdo->prepare('INSERT INTO schools (name) VALUES (:name)');
    $statement->execute(['name' => $name]);
    $schoolId = (int) $pdo->lastInsertId();
    insert_default_term($pdo, $schoolId);
    insert_default_policies($pdo, $schoolId);
    insert_default_ai($pdo, $schoolId);
    return $schoolId;
}
