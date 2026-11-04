<?php

/*
 * Grade boundaries every new school starts with (a percentage >= min earns
 * the grade). Copied into the school's own grade_scales rows the first time
 * results are opened, after which the school can edit them freely.
 */
return [
    'defaults' => [
        ['grade' => 'A', 'min_percent' => 75, 'remark' => 'Excellent'],
        ['grade' => 'B', 'min_percent' => 65, 'remark' => 'Very Good'],
        ['grade' => 'C', 'min_percent' => 45, 'remark' => 'Good'],
        ['grade' => 'D', 'min_percent' => 30, 'remark' => 'Satisfactory'],
        ['grade' => 'F', 'min_percent' => 0, 'remark' => 'Fail'],
    ],
];
