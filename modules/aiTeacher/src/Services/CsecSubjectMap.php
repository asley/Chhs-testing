<?php

namespace Gibbon\Module\aiTeacher\Services;

class CsecSubjectMap
{
    public function resolve(?string $courseName, ?string $courseNameShort): array
    {
        $haystack = trim(($courseName ?? '').' '.($courseNameShort ?? ''));

        $map = [
            'Information Technology' => ['information technology', 'it'],
            'EDPM' => ['edpm', 'electronic document preparation'],
            'Mathematics' => ['mathematics', 'maths', 'math'],
            'English A' => ['english a', 'english'],
            'Social Studies' => ['social studies'],
            'Caribbean History' => ['caribbean history', 'history'],
            'Biology' => ['biology'],
            'Chemistry' => ['chemistry'],
            'Physics' => ['physics'],
            'Geography' => ['geography'],
            'Principles of Business' => ['principles of business', 'pob'],
            'Principles of Accounts' => ['principles of accounts', 'poa'],
        ];

        foreach ($map as $subject => $needles) {
            foreach ($needles as $needle) {
                if (stripos($haystack, $needle) !== false) {
                    return [
                        'subject' => $subject,
                        'agent' => 'CSEC '.$subject,
                        'verified' => true,
                    ];
                }
            }
        }

        return [
            'subject' => $courseName ?: ($courseNameShort ?: 'Unknown Subject'),
            'agent' => 'Generic CSEC Teacher',
            'verified' => false,
        ];
    }
}
