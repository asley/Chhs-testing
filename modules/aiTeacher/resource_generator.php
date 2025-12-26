<?php
// Ensure proper Gibbon environment
require_once __DIR__ . '/../../gibbon.php';

// Page setup
$page->title = __('AI Resource Generator');
$page->breadcrumbs->add(__('AI Teacher'), 'aiTeacher.php');
$page->breadcrumbs->add(__('Resource Generator'));

// Check access
if (!isActionAccessible($guid, $connection2, '/modules/aiTeacher/resource_generator.php')) {
    $page->addMessage(__('You do not have access to this action.'));
    return;
}
?>
<style>
    /* Remove the style that hides the form */
</style>
<!-- Resource Generator (Updated Layout Like Curriculum Support) -->
<form id="assessmentForm" class="w-full bg-white px-6 py-6 rounded shadow-md space-y-6">
    <h2 class="text-2xl font-semibold text-indigo-700">Assessment Generator</h2>

    <!-- Subject -->
    <div>
        <label for="subject" class="block text-sm font-medium text-gray-700">Subject <span class="text-red-600">*</span></label>
        <select name="subject" id="subject" class="form-control w-full mt-1" required>
            <option value="">Please select...</option>
            <option value="Mathematics">Mathematics</option>
            <option value="English A">English A</option>
            <option value="English B">English B (Literature)</option>
            <option value="Information Technology">Information Technology</option>
            <option value="Biology">Biology</option>
            <option value="Chemistry">Chemistry</option>
            <option value="Physics">Physics</option>
            <option value="Social Studies">Social Studies</option>
            <option value="Geography">Geography</option>
            <option value="Spanish">Spanish</option>
            <option value="Caribbean History">Caribbean History</option>
            <option value="Principles of Business">Principles of Business</option>
            <option value="Principles of Accounts">Principles of Accounts</option>
            <option value="EDPM">EDPM</option>
            <option value="Food and Nutrition">Food and Nutrition</option>
            <option value="Data Operations">Data Ops</option>
            <option value="Technical Drawing">Technical Drawing</option>
            <option value="Visual Arts">Visual Arts</option>
            <option value="Clothing and Textile">Clothing and Textile</option>
            <!-- Add more CSEC subjects as needed -->
        </select>
    </div>

    <!-- Topic -->
    <div>
        <label for="topic" class="block text-sm font-medium text-gray-700">Topic <span class="text-red-600">*</span></label>
        <input type="text" id="topic" name="topic" class="form-control w-full mt-1" placeholder="e.g., Input Devices" required />
    </div>

    <!-- Assessment Type -->
    <div>
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

    <!-- Custom Instructions -->
    <div>
        <label for="customInstructions" class="block text-sm font-medium text-gray-700">Custom Instructions (Optional)</label>
        <textarea name="customInstructions" id="customInstructions" rows="4" class="form-control w-full mt-1" placeholder="e.g., Generate 5 questions, display answers in bold, add explanations below each answer."></textarea>
    </div>

    <!-- Submit Button -->
    <div class="text-right">
        <button type="button" id="generateAssessment" class="button" onclick="handleGenerateClick(event)">Generate Assessment</button>
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

    // Override the inline handler
    window.handleGenerateClick = async function(event) {
        event.preventDefault(); // Prevent any default behavior

        console.log("Generate Assessment button clicked!");

        // Validate required fields
        const subject = document.getElementById("subject").value;
        const topic = document.getElementById("topic").value;
        const assessmentType = document.getElementById("assessmentType").value;

        if (!subject || !topic || !assessmentType) {
            alert("Please fill in all required fields (Subject, Topic, and Assessment Type)");
            return;
        }

        // Disable button and show loading
        generateBtn.disabled = true;
        generateBtn.textContent = "Generating...";
        outputDiv.innerHTML = '<div class="loading" style="text-align:center; padding:20px; color:#667eea; font-size:1.1em;">⏳ Generating your assessment... This may take up to 2 minutes.</div>';
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
                const html = marked.parse(result.formatted_assessment || '');
                outputDiv.innerHTML = `
                    <div style="color:#2a7a2a;font-weight:bold;font-size:1.1em;margin-bottom:1em;">✅ ${result.message}</div>
                    <div>${html}</div>
                `;
                outputDiv.scrollIntoView({ behavior: "smooth" });
            } else {
                outputDiv.innerHTML = `<div class="error" style="color:#b00;font-weight:bold;padding:15px;background:#ffe6e6;border-radius:6px;">❌ ${result.message || result.error}</div>`;
            }
        } catch (error) {
            clearTimeout(timeout);
            if (error.name === 'AbortError') {
                outputDiv.innerHTML = `<div class="error" style="color:#b00;font-weight:bold;padding:15px;background:#ffe6e6;border-radius:6px;">⏱️ The AI service is taking too long to respond. Please try again later.</div>`;
            } else {
                console.error("Error generating assessment:", error);
                outputDiv.innerHTML = `<div class="error" style="color:#b00;font-weight:bold;padding:15px;background:#ffe6e6;border-radius:6px;">❌ ${error.message}</div>`;
            }
        } finally {
            generateBtn.disabled = false;
            generateBtn.textContent = "Generate Assessment";
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