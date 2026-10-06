<?php

/*
| Starting behaviour categories for every new school, modelled on the
| Ministry's behaviour and attendance rules (قواعد السلوك والمواظبة):
| violations by degree with points deducted from a behaviour score of 100,
| and distinguished behaviour that earns points back. Points and wording
| are starting values: each school should check them against the rules
| in force and edit them under Behaviour → Categories.
*/

return [
    'starting_score' => 100,

    'categories' => [
        // [name_ar, name_en, kind, degree, points, notify_guardian]
        ['الإخلال بالنظام داخل الفصل', 'Disrupting the class', 'negative', 1, 1, false],
        ['عدم ارتداء الزي المدرسي', 'Not in school uniform', 'negative', 1, 1, false],
        ['النوم داخل الفصل', 'Sleeping in class', 'negative', 1, 1, false],
        ['الخروج من الحصة دون استئذان', 'Leaving class without permission', 'negative', 2, 2, true],
        ['التلفظ بألفاظ غير لائقة', 'Inappropriate language', 'negative', 2, 2, true],
        ['إحضار الجوال دون إذن', 'Bringing a phone without permission', 'negative', 2, 2, false],
        ['التنمر على الزملاء', 'Bullying classmates', 'negative', 3, 3, true],
        ['إتلاف ممتلكات المدرسة', 'Damaging school property', 'negative', 3, 3, true],
        ['الاعتداء بالضرب على زميل', 'Hitting a classmate', 'negative', 4, 10, true],
        ['الإساءة إلى المعلمين أو العاملين', 'Abusing teachers or staff', 'negative', 5, 15, true],
        ['المشاركة في الإذاعة والأنشطة', 'Taking part in assembly and activities', 'positive', null, 2, false],
        ['المبادرة في الأعمال التطوعية', 'Volunteering', 'positive', null, 2, false],
        ['التميز في المسابقات', 'Excelling in competitions', 'positive', null, 3, true],
        ['مساعدة الزملاء', 'Helping classmates', 'positive', null, 1, false],
    ],
];
