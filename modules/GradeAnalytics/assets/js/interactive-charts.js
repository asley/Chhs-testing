// Interactive Charts for Grade Analytics Dashboard
// Handles modal display and student list fetching

function ensureStudentModal() {
    const modal = document.getElementById("studentModal");
    if (!modal) {
        return null;
    }

    if (modal.parentNode !== document.body) {
        document.body.appendChild(modal);
    }

    modal.style.position = "fixed";
    modal.style.inset = "0";
    modal.style.zIndex = "99999";
    modal.style.width = "100vw";
    modal.style.height = "100vh";
    modal.style.backgroundColor = "rgba(15, 23, 42, 0.58)";
    modal.style.display = "none";
    modal.style.alignItems = "center";
    modal.style.justifyContent = "center";
    modal.style.padding = "24px";

    const content = modal.querySelector(".ga-modal-content");
    if (content) {
        content.style.width = "100%";
        content.style.maxWidth = "1040px";
        content.style.maxHeight = "min(760px, calc(100vh - 56px))";
        content.style.margin = "0";
        content.style.borderRadius = "16px";
        content.style.overflow = "hidden";
        content.style.boxShadow = "0 28px 80px rgba(15, 23, 42, 0.30)";
        content.style.backgroundColor = "#ffffff";
        content.style.display = "flex";
        content.style.flexDirection = "column";
    }

    const header = modal.querySelector(".ga-modal-header");
    if (header) {
        header.style.padding = "20px 24px";
        header.style.display = "flex";
        header.style.justifyContent = "space-between";
        header.style.alignItems = "center";
        header.style.background = "linear-gradient(90deg, #4e73df 0%, #3559cf 55%, #224abe 100%)";
        header.style.color = "#ffffff";
    }

    const title = modal.querySelector(".ga-modal-title");
    if (title) {
        title.style.margin = "0";
        title.style.fontSize = "1.05rem";
        title.style.fontWeight = "800";
        title.style.textTransform = "uppercase";
        title.style.letterSpacing = "0.02em";
    }

    const close = modal.querySelector(".ga-modal-close");
    if (close) {
        close.style.border = "0";
        close.style.background = "transparent";
        close.style.color = "#ffffff";
        close.style.fontSize = "2rem";
        close.style.lineHeight = "1";
        close.style.width = "36px";
        close.style.height = "36px";
        close.style.cursor = "pointer";
        close.style.borderRadius = "999px";
    }

    const body = modal.querySelector(".ga-modal-body");
    if (body) {
        body.style.padding = "24px";
        body.style.overflowY = "auto";
        body.style.flex = "1 1 auto";
        body.style.backgroundColor = "#ffffff";
    }

    const footer = modal.querySelector(".ga-modal-footer");
    if (footer) {
        footer.style.padding = "16px 24px 20px";
        footer.style.textAlign = "right";
        footer.style.backgroundColor = "#ffffff";
        footer.style.borderTop = "1px solid #e5e7eb";
    }

    const closeButton = modal.querySelector(".ga-btn-secondary");
    if (closeButton) {
        closeButton.style.minWidth = "84px";
        closeButton.style.padding = "10px 18px";
        closeButton.style.border = "0";
        closeButton.style.borderRadius = "8px";
        closeButton.style.backgroundColor = "#6b7280";
        closeButton.style.color = "#ffffff";
        closeButton.style.fontWeight = "600";
        closeButton.style.cursor = "pointer";
    }

    return modal;
}

function closeStudentModal() {
    const modal = document.getElementById("studentModal");
    if (modal) {
        modal.style.display = "none";
    }
}

function showStudentsByGrade(grade) {
    console.log("showStudentsByGrade called with grade:", grade);
    const modal = ensureStudentModal();
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
    const assessmentName = document.getElementById("assessmentName")?.value || "";
    const teacherID = document.getElementById("teacherID")?.value || "";

    console.log("Filters:", {courseID, classID, formGroupID, yearGroup, assessmentType, assessmentName, teacherID});

    // Show modal with loading state
    modalTitle.textContent = "Grade " + grade + " Students";
    studentList.innerHTML = "<p style='text-align: center; padding: 2rem;'><i class='fas fa-spinner fa-spin'></i> Loading students...</p>";
    modal.style.display = "flex";

    // Build query string
    const params = new URLSearchParams({
        grade: grade,
        courseID: courseID,
        classID: classID,
        formGroupID: formGroupID,
        yearGroup: yearGroup,
        assessmentType: assessmentType,
        assessmentName: assessmentName,
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
                    let html = "<div style='width:100%; overflow:auto;'>";
                    html += "<table class='ga-student-table' style='width:100%; border-collapse:collapse; border:1px solid #d7dce5; border-radius:10px; overflow:hidden; background:#fff;'>";
                    html += "<thead><tr>";
                    html += "<th style='padding:14px 16px; text-align:left; font-weight:700; font-size:14px; color:#5f6b7a; background:#f8fafc; border-bottom:1px solid #dbe2ea;'>Student</th>";
                    html += "<th style='padding:14px 16px; text-align:left; font-weight:700; font-size:14px; color:#5f6b7a; background:#f8fafc; border-bottom:1px solid #dbe2ea;'>Form Group</th>";
                    html += "<th style='padding:14px 16px; text-align:left; font-weight:700; font-size:14px; color:#5f6b7a; background:#f8fafc; border-bottom:1px solid #dbe2ea;'>Year Group</th>";
                    html += "<th style='padding:14px 16px; text-align:right; font-weight:700; font-size:14px; color:#5f6b7a; background:#f8fafc; border-bottom:1px solid #dbe2ea;'>Grade</th>";
                    html += "</tr></thead><tbody>";

                    data.data.forEach((student, index) => {
                        const rowBg = index % 2 === 0 ? "#ffffff" : "#fbfdff";
                        html += "<tr style='background:" + rowBg + ";'>";
                        html += "<td style='padding:14px 16px; border-bottom:1px solid #e8edf3;'><a href='" + student.profileLink + "' target='_blank' style='color:#3867e8; text-decoration:none; font-weight:600;'>" + student.name + "</a></td>";
                        html += "<td style='padding:14px 16px; border-bottom:1px solid #e8edf3; color:#5f6b7a;'>" + student.formGroup + "</td>";
                        html += "<td style='padding:14px 16px; border-bottom:1px solid #e8edf3; color:#5f6b7a;'>" + student.yearGroup + "</td>";
                        html += "<td style='padding:14px 16px; border-bottom:1px solid #e8edf3; text-align:right; font-weight:700; color:#4b5563;'>" + student.grade + "%</td>";
                        html += "</tr>";
                    });

                    html += "</tbody></table>";
                    html += "</div>";
                    html += "<p style='margin-top: 16px; text-align: right; color: #64748b; font-size: 0.95rem;'>Total: " + data.count + " student(s)</p>";
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

document.addEventListener("DOMContentLoaded", function() {
    ensureStudentModal();
});

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById("studentModal");
    if (event.target === modal) {
        closeStudentModal();
    }
}
