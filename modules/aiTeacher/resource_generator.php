<?php
// Ensure proper Gibbon environment
require_once __DIR__ . '/../../gibbon.php';
require_once __DIR__ . '/moduleFunctions.php';

// Page setup
$page->title = __('AI Resource Generator');
$page->breadcrumbs->add(__('AI Teacher'), 'aiTeacher.php');
$page->breadcrumbs->add(__('Resource Generator'));

// Check access
if (!isActionAccessible($guid, $connection2, '/modules/aiTeacher/resource_generator.php')) {
    $page->addMessage(__('You do not have access to this action.'));
    return;
}

$gibbonPlannerEntryID = $_GET['gibbonPlannerEntryID'] ?? '';
$lessonContext = null;
if (!empty($gibbonPlannerEntryID)) {
    $canViewAllPlannerLessons = isActionAccessible(
        $guid,
        $connection2,
        '/modules/Planner/planner_view_full.php',
        'Lesson Planner_viewEditAllClasses'
    );

    try {
        $lessonContext = getAITeacherLessonContext($pdo, $gibbonPlannerEntryID, $session->get('gibbonPersonID'), $canViewAllPlannerLessons);
    } catch (Throwable $e) {
        error_log('[AI Resource Generator] Lesson context load failed: ' . $e->getMessage());
        $lessonContext = null;
    }

    if (empty($lessonContext)) {
        $page->addMessage(__('The selected lesson could not be loaded, or you do not have access to it.'));
        $gibbonPlannerEntryID = '';
    }
}

$prefillSubject = $lessonContext['subject'] ?? '';
$prefillTopic = $lessonContext['lessonName'] ?? '';
?>
<style>
    /* Remove the style that hides the form */
</style>
<!-- Resource Generator (Updated Layout Like Curriculum Support) -->
<form id="assessmentForm" class="w-full bg-white px-6 py-6 rounded shadow-md space-y-6">
    <h2 class="text-2xl font-semibold text-indigo-700">Assessment Generator</h2>
    <input type="hidden" name="gibbonPlannerEntryID" id="gibbonPlannerEntryID" value="<?php echo htmlPrep($gibbonPlannerEntryID) ?>" />

    <?php if (!empty($lessonContext)) { ?>
        <div style="padding:12px 14px; border:1px solid #bfdbfe; background:#eff6ff; color:#1e3a8a; border-radius:4px;">
            <?php echo __('Linked lesson') ?>:
            <strong><?php echo htmlPrep($lessonContext['lessonName']) ?></strong>
            <?php if (!empty($lessonContext['course'])) { ?>
                <span style="font-size:90%;">(<?php echo htmlPrep($lessonContext['course']) ?>)</span>
            <?php } ?>
        </div>
    <?php } ?>

    <!-- Output Format -->
    <div>
        <label for="mode" class="block text-sm font-medium text-gray-700">Output Format <span class="text-red-600">*</span></label>
        <select name="mode" id="mode" class="form-control w-full mt-1" required>
            <option value="assessment">Readable Assessment</option>
            <option value="tcexam_csv" <?php echo !empty($lessonContext) ? 'selected' : '' ?>>TCExam Question CSV</option>
        </select>
    </div>

    <!-- Subject -->
    <div>
        <label for="subject" class="block text-sm font-medium text-gray-700">Subject <span class="text-red-600">*</span></label>
        <select name="subject" id="subject" class="form-control w-full mt-1" required>
            <option value="">Please select...</option>
            <?php
            $subjects = [
                'Mathematics' => 'Mathematics',
                'English A' => 'English A',
                'English B' => 'English B (Literature)',
                'Information Technology' => 'Information Technology',
                'Biology' => 'Biology',
                'Chemistry' => 'Chemistry',
                'Physics' => 'Physics',
                'Social Studies' => 'Social Studies',
                'Geography' => 'Geography',
                'Spanish' => 'Spanish',
                'Caribbean History' => 'Caribbean History',
                'Principles of Business' => 'Principles of Business',
                'Principles of Accounts' => 'Principles of Accounts',
                'EDPM' => 'EDPM',
                'Food and Nutrition' => 'Food and Nutrition',
                'Data Operations' => 'Data Ops',
                'Technical Drawing' => 'Technical Drawing',
                'Visual Arts' => 'Visual Arts',
                'Clothing and Textile' => 'Clothing and Textile',
            ];
            if (!empty($prefillSubject) && !isset($subjects[$prefillSubject])) {
                $subjects = [$prefillSubject => $prefillSubject] + $subjects;
            }
            foreach ($subjects as $value => $label) {
                $selected = $value === $prefillSubject ? ' selected' : '';
                echo '<option value="'.htmlPrep($value).'"'.$selected.'>'.htmlPrep($label).'</option>';
            }
            ?>
            <!-- Add more CSEC subjects as needed -->
        </select>
    </div>

    <!-- Topic -->
    <div>
        <label for="topic" class="block text-sm font-medium text-gray-700">Topic <span class="text-red-600">*</span></label>
        <input type="text" id="topic" name="topic" class="form-control w-full mt-1" placeholder="e.g., Input Devices" value="<?php echo htmlPrep($prefillTopic) ?>" required />
    </div>

    <!-- Assessment Type -->
    <div id="assessmentTypeWrap">
        <label for="assessmentType" class="block text-sm font-medium text-gray-700">Assessment Type <span class="text-red-600">*</span></label>
        <select name="assessmentType" id="assessmentType" class="form-control w-full mt-1" required>
            <option value="">Please select...</option>
            <option value="Multiple Choice Quiz">Multiple Choice Quiz</option>
            <option value="True/False Questions">True/False Questions</option>
            <option value="Fill in the Blanks">Fill in the Blanks</option>
            <option value="Matching Items">Matching Items</option>
            <option value="Case Study">Case Study</option>
            <option value="Short Answer Questions">Short Answer Questions</option>
            <option value="Diagram Labeling">Diagram Labeling</option>
            <!-- Add more types if applicable -->
        </select>
    </div>

    <!-- Question Count -->
    <div id="questionCountWrap">
        <label for="questionType" class="block text-sm font-medium text-gray-700">Question Type</label>
        <select name="questionType" id="questionType" class="form-control w-full mt-1">
            <option value="multiple_choice_single">Multiple Choice - Single Answer</option>
            <option value="multiple_choice_multiple">Multiple Choice - Multiple Answers</option>
            <option value="true_false">True / False</option>
        </select>

        <label for="questionCount" class="block text-sm font-medium text-gray-700">Question Count</label>
        <input type="number" id="questionCount" name="questionCount" class="form-control w-full mt-1" min="1" max="50" value="10" />
    </div>

    <!-- Custom Instructions -->
    <div>
        <label for="customInstructions" class="block text-sm font-medium text-gray-700">Custom Instructions (Optional)</label>
        <textarea name="customInstructions" id="customInstructions" rows="4" class="form-control w-full mt-1" placeholder="e.g., Generate 5 questions, display answers in bold, add explanations below each answer."></textarea>
    </div>

    <!-- Submit Button -->
    <div class="text-right">
        <button type="button" id="generateAssessment" class="button" onclick="handleGenerateClick(event)">Generate</button>
        <div id="readyIndicator" style="display:none; color:#10b981; font-size:0.85em; margin-top:8px;">✓ Ready to generate</div>
    </div>
</form>
<div id="assessmentOutput" style="padding:20px; margin-top:20px; background:#fff; border:1px solid #ddd; border-radius:4px; display:none;"></div>
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<script>
console.log("=== Resource Generator Script Loading ===");

// Immediate fallback handler via onclick attribute
function handleGenerateClick(event) {
    console.log("Inline onclick handler fired!");
    event.preventDefault();
    if (window.resourceGeneratorReady) {
        console.log("Using main handler");
    } else {
        console.warn("Main handler not ready, please wait...");
        alert("Resource generator is still loading. Please wait a moment and try again.");
    }
}
</script>
<script>
// Wait for both DOM and marked library to be ready
function initResourceGenerator() {
    console.log("Resource Generator: Initializing...");

    const generateBtn = document.getElementById("generateAssessment");
    const outputDiv = document.getElementById("assessmentOutput");
    const form = document.getElementById("assessmentForm");
    const modeSelect = document.getElementById("mode");
    const assessmentType = document.getElementById("assessmentType");
    const assessmentTypeWrap = document.getElementById("assessmentTypeWrap");
    const questionCountWrap = document.getElementById("questionCountWrap");

    if (!generateBtn) {
        console.error("Generate Assessment button not found!");
        return;
    }

    if (!form) {
        console.error("Assessment form not found!");
        return;
    }

    if (typeof marked === 'undefined') {
        console.error("marked library not loaded yet, retrying...");
        setTimeout(initResourceGenerator, 100);
        return;
    }

    console.log("Resource Generator: All dependencies loaded, attaching event listener...");

    function updateModeDisplay() {
        const isTCExam = modeSelect && modeSelect.value === "tcexam_csv";
        if (assessmentTypeWrap) assessmentTypeWrap.style.display = isTCExam ? "none" : "block";
        if (questionCountWrap) questionCountWrap.style.display = isTCExam ? "block" : "none";
        if (assessmentType) assessmentType.required = !isTCExam;
        generateBtn.textContent = isTCExam ? "Generate TCExam CSV" : "Generate Assessment";
    }

    function downloadCsv(filename, csv) {
        const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");
        link.href = url;
        link.download = filename || "tcexam-questions.csv";
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    }

    function downloadText(filename, content) {
        const blob = new Blob([content || ""], { type: "text/markdown;charset=utf-8;" });
        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");
        link.href = url;
        link.download = filename || "assessment.md";
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    }

    function buildDownloadFilename(prefix, extension) {
        const topic = document.getElementById("topic").value || "assessment";
        const slug = topic
            .toLowerCase()
            .replace(/[^a-z0-9_-]+/g, "-")
            .replace(/^-+|-+$/g, "") || "assessment";

        return `${prefix}-${slug}.${extension}`;
    }

    function escapeHtml(value) {
        return String(value || "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    if (modeSelect) {
        modeSelect.addEventListener("change", updateModeDisplay);
    }
    updateModeDisplay();

    // Override the inline handler
    window.handleGenerateClick = async function(event) {
        event.preventDefault(); // Prevent any default behavior

        console.log("Generate Assessment button clicked!");

        // Validate required fields
        const subject = document.getElementById("subject").value;
        const topic = document.getElementById("topic").value;
        const mode = document.getElementById("mode").value;
        const assessmentTypeValue = document.getElementById("assessmentType").value;

        if (!subject || !topic || (mode !== "tcexam_csv" && !assessmentTypeValue)) {
            alert(mode === "tcexam_csv"
                ? "Please fill in Subject and Topic."
                : "Please fill in all required fields (Subject, Topic, and Assessment Type)");
            return;
        }

        // Disable button and show loading
        generateBtn.disabled = true;
        generateBtn.textContent = "Generating...";
        outputDiv.innerHTML = '<div class="loading" style="text-align:center; padding:20px; color:#667eea; font-size:1.1em;">Generating... This may take up to 2 minutes.</div>';
        outputDiv.style.display = "block";

        const controller = new AbortController();
        const timeout = setTimeout(() => {
            controller.abort();
        }, 120000); // 120 seconds

        try {
            const response = await fetch("modules/aiTeacher/resource_generator_ajax.php", {
                method: "POST",
                body: new FormData(form),
                signal: controller.signal
            });

            clearTimeout(timeout);

            if (!response.ok) {
                throw new Error("Server returned error: " + response.status);
            }

            const result = await response.json();

            if (result.success) {
                if (mode === "tcexam_csv") {
                    const rows = Array.isArray(result.questions) ? result.questions : [];
                    const previewRows = rows.slice(0, 10).map((question, index) => `
                        <tr>
                            <td style="padding:6px;border-bottom:1px solid #e5e7eb;">${index + 1}</td>
                            <td style="padding:6px;border-bottom:1px solid #e5e7eb;">${escapeHtml(question.question_type)}</td>
                            <td style="padding:6px;border-bottom:1px solid #e5e7eb;">${escapeHtml(question.question_text)}</td>
                        </tr>
                    `).join("");

                    outputDiv.innerHTML = `
                        <div style="color:#2a7a2a;font-weight:bold;font-size:1.1em;margin-bottom:1em;">${result.message}</div>
                        <button type="button" id="downloadTCExamCsv" class="button" style="margin-bottom:14px;">Download TCExam CSV</button>
                        <table style="width:100%;border-collapse:collapse;background:#fff;">
                            <thead>
                                <tr>
                                    <th style="text-align:left;padding:6px;border-bottom:1px solid #d1d5db;">#</th>
                                    <th style="text-align:left;padding:6px;border-bottom:1px solid #d1d5db;">Type</th>
                                    <th style="text-align:left;padding:6px;border-bottom:1px solid #d1d5db;">Question</th>
                                </tr>
                            </thead>
                            <tbody>${previewRows}</tbody>
                        </table>
                    `;

                    document.getElementById("downloadTCExamCsv").addEventListener("click", () => {
                        downloadCsv(result.filename, result.csv || "");
                    });
                    downloadCsv(result.filename, result.csv || "");
                } else {
                    const assessmentMarkdown = result.formatted_assessment || '';
                    const html = marked.parse(assessmentMarkdown);
                    outputDiv.innerHTML = `
                        <div style="color:#2a7a2a;font-weight:bold;font-size:1.1em;margin-bottom:1em;">${result.message}</div>
                        <button type="button" id="downloadReadableAssessment" class="button" style="margin-bottom:14px;">Download Assessment</button>
                        <div>${html}</div>
                    `;
                    document.getElementById("downloadReadableAssessment").addEventListener("click", () => {
                        downloadText(buildDownloadFilename("assessment", "md"), assessmentMarkdown);
                    });
                }
                outputDiv.scrollIntoView({ behavior: "smooth" });
            } else {
                outputDiv.innerHTML = `<div class="error" style="color:#b00;font-weight:bold;padding:15px;background:#ffe6e6;border-radius:6px;">${result.message || result.error}</div>`;
            }
        } catch (error) {
            clearTimeout(timeout);
            if (error.name === 'AbortError') {
                outputDiv.innerHTML = `<div class="error" style="color:#b00;font-weight:bold;padding:15px;background:#ffe6e6;border-radius:6px;">The AI service is taking too long to respond. Please try again later.</div>`;
            } else {
                console.error("Error generating assessment:", error);
                outputDiv.innerHTML = `<div class="error" style="color:#b00;font-weight:bold;padding:15px;background:#ffe6e6;border-radius:6px;">${error.message}</div>`;
            }
        } finally {
            generateBtn.disabled = false;
            updateModeDisplay();
            outputDiv.style.display = "block";
        }
    };

    // Set ready flag and show indicator
    window.resourceGeneratorReady = true;
    const readyIndicator = document.getElementById("readyIndicator");
    if (readyIndicator) {
        readyIndicator.style.display = "block";
    }
    console.log("Resource Generator: Ready!");
}

// Initialize when DOM is ready
console.log("Document readyState:", document.readyState);
if (document.readyState === 'loading') {
    console.log("Waiting for DOMContentLoaded...");
    document.addEventListener('DOMContentLoaded', initResourceGenerator);
} else {
    // DOM already loaded
    console.log("DOM already ready, initializing now...");
    initResourceGenerator();
}
</script>
