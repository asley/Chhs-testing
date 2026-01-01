// Interactive Charts for Grade Analytics Dashboard
// Handles modal display and student list fetching

function closeStudentModal() {
    document.getElementById("studentModal").style.display = "none";
}

function showStudentsByGrade(grade) {
    console.log("showStudentsByGrade called with grade:", grade);
    const modal = document.getElementById("studentModal");
    const modalTitle = document.getElementById("modalTitle");
    const studentList = document.getElementById("studentList");

    if (!modal || !modalTitle || !studentList) {
        console.error("Modal elements not found!");
        return;
    }

    console.log("Modal elements found");

    // Get current filter values
    const courseID = document.getElementById("courseID")?.value || "";
    const classID = document.getElementById("classID")?.value || "";
    const formGroupID = document.getElementById("formGroupID")?.value || "";
    const yearGroup = document.getElementById("yearGroup")?.value || "";
    const assessmentType = document.getElementById("assessmentType")?.value || "";
    const teacherID = document.getElementById("teacherID")?.value || "";

    console.log("Filters:", {courseID, classID, formGroupID, yearGroup, assessmentType, teacherID});

    // Show modal with loading state
    modalTitle.textContent = "Grade " + grade + " Students";
    studentList.innerHTML = "<p style='text-align: center; padding: 2rem;'><i class='fas fa-spinner fa-spin'></i> Loading students...</p>";
    modal.style.display = "block";

    // Build query string
    const params = new URLSearchParams({
        grade: grade,
        courseID: courseID,
        classID: classID,
        formGroupID: formGroupID,
        yearGroup: yearGroup,
        assessmentType: assessmentType,
        teacherID: teacherID
    });

    // Get base URL from a data attribute or global variable
    const baseURL = document.body.getAttribute('data-absolute-url') || window.location.origin;
    const url = baseURL + "/modules/GradeAnalytics/ajax/fetchStudentsByGrade.php?" + params.toString();
    console.log("Fetching from URL:", url);

    fetch(url)
        .then(response => {
            console.log("Response status:", response.status);
            return response.text();
        })
        .then(text => {
            console.log("Raw response:", text);
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error("JSON parse error:", e);
                throw new Error("Invalid JSON response: " + text.substring(0, 100));
            }
        })
        .then(data => {
            console.log("Parsed data:", data);
            if (data.success) {
                if (data.data.length === 0) {
                    studentList.innerHTML = "<p style='text-align: center; padding: 2rem; color: #718096;'>No students found for grade " + grade + "</p>";
                } else {
                    let html = "<table style='width: 100%; border-collapse: collapse;'>";
                    html += "<thead><tr style='background-color: #f7fafc; border-bottom: 2px solid #e2e8f0;'>";
                    html += "<th style='padding: 0.75rem; text-align: left; font-weight: 600;'>Student</th>";
                    html += "<th style='padding: 0.75rem; text-align: left; font-weight: 600;'>Form Group</th>";
                    html += "<th style='padding: 0.75rem; text-align: left; font-weight: 600;'>Year Group</th>";
                    html += "<th style='padding: 0.75rem; text-align: center; font-weight: 600;'>Grade</th>";
                    html += "</tr></thead><tbody>";

                    data.data.forEach((student, index) => {
                        const rowStyle = index % 2 === 0 ? "background-color: #ffffff;" : "background-color: #f9fafb;";
                        html += "<tr style='" + rowStyle + " border-bottom: 1px solid #e2e8f0;'>";
                        html += "<td style='padding: 0.75rem;'><a href='" + student.profileLink + "' style='color: #4e73df; text-decoration: none; font-weight: 500;' target='_blank'>" + student.name + "</a></td>";
                        html += "<td style='padding: 0.75rem;'>" + student.formGroup + "</td>";
                        html += "<td style='padding: 0.75rem;'>" + student.yearGroup + "</td>";
                        html += "<td style='padding: 0.75rem; text-align: center; font-weight: bold;'>" + student.grade + "%</td>";
                        html += "</tr>";
                    });

                    html += "</tbody></table>";
                    html += "<p style='margin-top: 1rem; text-align: center; color: #718096; font-size: 0.875rem;'>Total: " + data.count + " student(s)</p>";
                    studentList.innerHTML = html;
                }
            } else {
                studentList.innerHTML = "<p style='text-align: center; padding: 2rem; color: #e53e3e;'>Error: " + data.message + "</p>";
            }
        })
        .catch(error => {
            console.error("Fetch error:", error);
            studentList.innerHTML = "<p style='text-align: center; padding: 2rem; color: #e53e3e;'>Error loading students: " + error.message + "</p>";
        });
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById("studentModal");
    if (event.target === modal) {
        closeStudentModal();
    }
}
