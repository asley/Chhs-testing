<?php

namespace Gibbon\Module\aiTeacher\Services;

class LessonContentGenerator
{
    private $provider;
    private $subjectMap;

    public function __construct(AITeacherProvider $provider, CsecSubjectMap $subjectMap)
    {
        $this->provider = $provider;
        $this->subjectMap = $subjectMap;
    }

    public function generate(array $context, string $outputType, string $customInstructions = ''): array
    {
        $subject = $this->subjectMap->resolve(
            $context['course']['name'] ?? '',
            $context['course']['nameShort'] ?? ''
        );
        $prompt = $this->buildPrompt($context, $subject, $outputType, $customInstructions);
        $raw = $this->provider->generate($prompt);
        $draft = $this->decodeJsonResponse($raw);

        return [
            'success' => true,
            'lesson' => [
                'name' => $draft['lesson']['name'] ?? '',
                'summary' => $draft['lesson']['summary'] ?? '',
                'description' => $draft['lesson']['description'] ?? $this->htmlFromText($raw),
                'teachersNotes' => $this->resolveTeacherNotes($draft, $subject, $context),
            ],
            'homework' => [
                'enabled' => (bool) ($draft['homework']['enabled'] ?? true),
                'details' => $draft['homework']['details'] ?? '',
                'timeCap' => $draft['homework']['timeCap'] ?? null,
            ],
            'meta' => [
                'provider' => $this->provider->getProviderName(),
                'subject' => $subject['subject'],
                'subjectAgent' => $subject['agent'],
                'syllabusVerified' => $subject['verified'] && !empty($context['unit']),
                'promptHash' => hash('sha256', $prompt),
            ],
        ];
    }

    private function buildPrompt(array $context, array $subject, string $outputType, string $customInstructions): string
    {
        $blocks = array_slice($context['blocks'] ?? [], 0, 12);
        $blockText = '';
        foreach ($blocks as $block) {
            $blockText .= "- {$block['title']}: ".trim(strip_tags($block['contents'] ?? ''))."\n";
        }

        $outcomeText = '';
        foreach ($context['outcomes'] ?? [] as $outcome) {
            $outcomeText .= "- {$outcome['nameShort']} {$outcome['name']}: ".trim(strip_tags($outcome['content'] ?? ''))."\n";
        }

        $unit = $context['unit'] ?? [];
        $lesson = $context['lesson'] ?? [];

        return "You are {$subject['agent']}, a CSEC teacher generating classroom-ready Planner draft content.\n"
            ."Return strict JSON only. Do not wrap it in Markdown.\n"
            ."JSON shape: {\"lesson\":{\"name\":\"\",\"summary\":\"\",\"description\":\"\",\"teachersNotes\":\"\"},\"homework\":{\"enabled\":true,\"details\":\"\",\"timeCap\":30}}\n"
            ."Use simple HTML in description, teachersNotes, and homework.details. Do not include script, iframe, or external media.\n"
            ."The lesson.description field should contain only two sections: Learning Objectives and Lesson Content. Do not create a full lesson plan with starter, timing, activities, plenary, differentiation, resources, or teacher/student action sections unless the teacher specifically asks for them.\n"
            ."Learning Objectives should be 3-5 clear, measurable objectives aligned to the unit and CSEC level.\n"
            ."Lesson Content should be concise teaching content the teacher can use in class: key concepts, explanations, examples, important vocabulary, and CSEC-style application points. Aim for 250-450 words unless the teacher asks for more.\n"
            ."The lesson.teachersNotes field is required. Include a short teacher-only note with: preparation reminders, likely misconceptions, quick questioning prompts, and answer guidance for homework or checks. Keep it concise, but do not leave it empty.\n"
            ."The homework.details field should include clear student instructions, numbered tasks, expected output, and marking/collection guidance without revealing answers.\n"
            ."Output type requested: {$outputType}\n"
            ."Subject: {$subject['subject']}\n"
            ."Course: ".($context['course']['name'] ?? '')." (".($context['course']['nameShort'] ?? '').")\n"
            ."Class: ".($context['class']['nameShort'] ?? '')."\n"
            ."Lesson date/time: ".($lesson['date'] ?? '')." ".($lesson['timeStart'] ?? '')."-".($lesson['timeEnd'] ?? '')."\n"
            ."Existing lesson name: ".($lesson['name'] ?? '')."\n"
            ."Existing summary: ".($lesson['summary'] ?? '')."\n"
            ."Unit: ".($unit['name'] ?? 'No unit selected')."\n"
            ."Unit description: ".trim(strip_tags($unit['description'] ?? ''))."\n"
            ."Unit details: ".trim(strip_tags($unit['details'] ?? ''))."\n"
            ."Unit tags: ".($unit['tags'] ?? '')."\n"
            ."Unit blocks/objectives:\n{$blockText}\n"
            ."Planner outcomes:\n{$outcomeText}\n"
            ."Custom teacher instructions: {$customInstructions}\n"
            ."Rules: draft content for teacher review; keep homework student-facing; put answer keys or marking guidance in teachersNotes only. If output type is Homework Only, keep lesson.description brief but still update teachersNotes with how the homework connects to the lesson. Always return non-empty lesson.teachersNotes.";
    }

    private function decodeJsonResponse(string $raw): array
    {
        $raw = trim($raw);
        $raw = preg_replace('/^```json\s*/i', '', $raw);
        $raw = preg_replace('/^```\s*/', '', $raw);
        $raw = preg_replace('/\s*```$/', '', $raw);

        $data = json_decode($raw, true);
        if (is_array($data)) {
            return $data;
        }

        if (preg_match('/\{.*\}/s', $raw, $matches)) {
            $data = json_decode($matches[0], true);
            if (is_array($data)) {
                return $data;
            }
        }

        return [
            'lesson' => [
                'description' => $this->htmlFromText($raw),
                'teachersNotes' => '',
            ],
            'homework' => [
                'enabled' => false,
                'details' => '',
                'timeCap' => null,
            ],
        ];
    }

    private function htmlFromText(string $text): string
    {
        return '<p>'.nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8')).'</p>';
    }

    private function resolveTeacherNotes(array $draft, array $subject, array $context): string
    {
        $teachersNotes = trim((string) ($draft['lesson']['teachersNotes'] ?? ''));
        if ($teachersNotes !== '') {
            return $teachersNotes;
        }

        $unitName = $context['unit']['name'] ?? 'the selected unit';
        $subjectName = $subject['subject'] ?? 'the subject';

        return '<p><strong>Preparation:</strong> Review the key terms and examples for '.htmlspecialchars($unitName, ENT_QUOTES, 'UTF-8').' before class.</p>'
            .'<p><strong>Misconceptions:</strong> Check that students can explain the difference between similar concepts in '.htmlspecialchars($subjectName, ENT_QUOTES, 'UTF-8').' and can apply them to CSEC-style scenarios.</p>'
            .'<p><strong>Questioning:</strong> Ask students to justify their answers using syllabus vocabulary and real examples.</p>';
    }
}
