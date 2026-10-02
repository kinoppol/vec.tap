<?php
declare(strict_types=1);

return [
    'name' => 'กำหนดระดับจากรหัสกลุ่มหลักที่ 3 และชื่อย่อ เลข 2 หรือ ช คือ ปวช. เลข 3 หรือ ส คือ ปวส. ทล.บ. คือปริญญาตรี',
    'statements' => [
        "UPDATE student_groups SET level = 'ป.ตรี' WHERE name LIKE 'ทล.บ%'",
        "UPDATE student_groups SET level = 'ปวช.' WHERE CHAR_LENGTH(rms_group_code) >= 3 AND SUBSTRING(rms_group_code, 3, 1) = '2'",
        "UPDATE student_groups SET level = 'ปวส.' WHERE CHAR_LENGTH(rms_group_code) >= 3 AND SUBSTRING(rms_group_code, 3, 1) = '3'",
        "UPDATE student_groups SET level = 'ปวช.' WHERE (rms_group_code IS NULL OR CHAR_LENGTH(rms_group_code) < 3 OR SUBSTRING(rms_group_code, 3, 1) NOT IN ('2', '3')) AND name LIKE 'ช%'",
        "UPDATE student_groups SET level = 'ปวส.' WHERE (rms_group_code IS NULL OR CHAR_LENGTH(rms_group_code) < 3 OR SUBSTRING(rms_group_code, 3, 1) NOT IN ('2', '3')) AND name LIKE 'ส%'",
    ],
];
