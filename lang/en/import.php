<?php

return [
    'unreadable' => 'The file could not be read. Make sure it is an Excel .xlsx file.',
    'too_many_rows' => 'The file has more than :max rows. Split it by stage and upload in parts.',
    'already_exists' => 'National ID already registered; skipped.',
    'failed' => 'This row could not be imported.',
    'name_required' => 'Student name is required (at least first and family name).',
    'invalid_national_id' => 'Invalid student national ID.',
    'invalid_guardian_id' => 'Invalid guardian national ID.',
    'invalid_gender' => 'Gender must be male or female.',
    'invalid_date' => 'Date of birth not understood (e.g. 2019-05-14 or 1440/09/09).',
    'invalid_phone' => 'Invalid guardian mobile number.',
    'unknown_grade' => 'Grade “:grade” does not exist in this school.',
    'new_section' => 'Section “:section” will be created.',
    'guardian_of' => 'Guardian of :name',
    'no_columns' => 'The file’s columns were not recognized. The first row must hold the headers (student name, gender, grade…).',
];
