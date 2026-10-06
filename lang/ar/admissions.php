<?php

return [
    'try_again' => 'تعذر إرسال الطلب، يرجى مراجعة البيانات والمحاولة مرة أخرى.',
    'daily_limit' => 'وصل عدد الطلبات اليوم لهذا الرقم أو للمدرسة إلى الحد المسموح؛ حاول غدًا أو تواصل مع المدرسة.',
    'subject' => ':school: طلب القبول :reference',
    'age_outside' => 'تاريخ الميلاد خارج الفئة العمرية المقبولة لهذا الصف هذا العام.',
    'duplicate_application' => 'يوجد طلب قائم لهذا الطالب بنفس رقم الهوية هذا العام.',
    'window_closed' => 'التقديم على هذا الصف غير متاح حاليًا.',
    'bad_phone' => 'أدخل رقم جوال سعودي صحيح، مثل 05XXXXXXXX.',
    'bad_transition' => 'لا يمكن نقل الطلب إلى هذه الحالة من حالته الحالية.',
    'no_seats' => 'لا توجد مقاعد شاغرة في هذا الصف؛ أدرج الطلب في قائمة الانتظار.',
    'assessment_time_required' => 'حدد موعد المقابلة أو التقييم.',
    'not_accepted' => 'لا يمكن التسجيل قبل أن تؤكد الأسرة قبول العرض.',
    'already_a_student' => 'يوجد طالب مسجل بنفس رقم الهوية.',
    'submitted' => 'تم استلام طلبكم. احتفظوا برابط هذه الصفحة لمتابعة الطلب.',
    'document_uploaded' => 'تم رفع المستند.',
    'document_already_accepted' => 'تم اعتماد هذا المستند ولا يمكن استبداله.',
    'offer_accepted' => 'شكرًا لكم، تم تأكيد قبول العرض.',
    'withdrawn' => 'تم سحب الطلب.',
    'by_family' => 'بواسطة الأسرة',
    'status_changed' => 'تم تحديث حالة الطلب.',
    'enrolled' => 'تم تسجيل الطالب.',
    'window_saved' => 'تم حفظ فترة القبول.',
    'window_deleted' => 'تم حذف فترة القبول.',
    'window_has_applications' => 'لا يمكن حذف فترة لها طلبات.',

    // Messages to the family. Names are used without gendered words so
    // the same text reads correctly for boys and girls.
    'message_submitted' => ':school: استلمنا طلب القبول رقم :reference للطالب/ـة :student. تابعوا الطلب وارفعوا المستندات عبر الرابط: :link',
    'message_assessment_scheduled' => ':school: موعد المقابلة لطلب :reference يوم :date الساعة :time. التفاصيل: :link',
    'message_offered' => ':school: يسعدنا إبلاغكم بالقبول المبدئي لـ :student في :grade (طلب :reference). أكدوا القبول عبر الرابط: :link',
    'message_waitlisted' => ':school: أُدرج طلب :reference في قائمة الانتظار لـ :grade، وسنبلغكم عند توفر مقعد.',
    'message_rejected' => ':school: نعتذر عن عدم إمكانية قبول طلب :reference هذا العام. شكرًا لاهتمامكم.',
    'message_enrolled' => ':school: تم تسجيل :student في :grade. أهلًا وسهلًا بكم.',

    'status' => [
        'submitted' => 'مقدَّم',
        'under_review' => 'قيد المراجعة',
        'assessment_scheduled' => 'مقابلة محددة',
        'assessed' => 'تمت المقابلة',
        'offered' => 'قبول مبدئي',
        'waitlisted' => 'قائمة الانتظار',
        'accepted' => 'قبلت الأسرة',
        'enrolled' => 'مسجَّل',
        'rejected' => 'مرفوض',
        'withdrawn' => 'مسحوب',
    ],
];
