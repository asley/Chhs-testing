-- =====================================================
-- Fix Faith Skipping's 4 F&N Marksheet 1 Entry
-- Issue: Wrong teacher (Shaneka Smith instead of Taylor)
-- =====================================================

-- STEP 1: Find Faith Skipping's gibbonPersonID
SELECT
    gibbonPersonID,
    preferredName,
    surname,
    CONCAT(preferredName, ' ', surname) as fullName
FROM gibbonPerson
WHERE preferredName = 'Faith' AND surname = 'Skipping';

-- Expected result: gibbonPersonID for Faith Skipping


-- STEP 2: Find the Shaneka Smith teacher record
SELECT
    gibbonPersonID,
    preferredName,
    surname,
    CONCAT(preferredName, ' ', surname) as fullName
FROM gibbonPerson
WHERE preferredName = 'Shaneka' AND surname = 'Smith';

-- Expected result: gibbonPersonID for Shaneka Smith


-- STEP 3: Find Damona Taylor's gibbonPersonID
SELECT
    gibbonPersonID,
    preferredName,
    surname,
    title,
    CONCAT(title, ' ', preferredName, ' ', surname) as fullName
FROM gibbonPerson
WHERE surname = 'Taylor' AND preferredName LIKE '%DAMONA%';

-- Expected result: gibbonPersonID for Ms DAMONA TAYLOR


-- STEP 4: Find the 4 F&N course
SELECT
    c.gibbonCourseID,
    c.name as courseName,
    c.nameShort,
    cc.gibbonCourseClassID,
    cc.nameShort as className
FROM gibbonCourse c
JOIN gibbonCourseClass cc ON c.gibbonCourseID = cc.gibbonCourseID
WHERE c.nameShort LIKE '%F%N%' OR c.name LIKE '%Food%Nutrition%'
ORDER BY c.name;


-- STEP 5: Display the INCORRECT internal assessment entry (Shaneka Smith)
-- Replace the gibbonPersonIDs below with actual values from steps 1-4
SELECT
    iae.gibbonInternalAssessmentEntryID,
    iae.gibbonInternalAssessmentColumnID,
    p_student.preferredName as studentFirstName,
    p_student.surname as studentLastName,
    c.nameShort as course,
    cc.nameShort as class,
    iac.name as assessmentName,
    iac.description as assessmentDescription,
    iae.attainmentValue,
    iae.attainmentDescriptor,
    iae.effortValue,
    iae.comment,
    p_teacher.preferredName as teacherFirstName,
    p_teacher.surname as teacherLastName,
    CONCAT(p_teacher.preferredName, ' ', p_teacher.surname) as teacherFullName
FROM gibbonInternalAssessmentEntry iae
JOIN gibbonInternalAssessmentColumn iac
    ON iae.gibbonInternalAssessmentColumnID = iac.gibbonInternalAssessmentColumnID
JOIN gibbonCourseClass cc
    ON iac.gibbonCourseClassID = cc.gibbonCourseClassID
JOIN gibbonCourse c
    ON cc.gibbonCourseID = c.gibbonCourseID
JOIN gibbonPerson p_student
    ON iae.gibbonPersonIDStudent = p_student.gibbonPersonID
LEFT JOIN gibbonCourseClassPerson ccp
    ON cc.gibbonCourseClassID = ccp.gibbonCourseClassID
    AND ccp.role = 'Teacher'
LEFT JOIN gibbonPerson p_teacher
    ON ccp.gibbonPersonID = p_teacher.gibbonPersonID
WHERE p_student.preferredName = 'Faith'
    AND p_student.surname = 'Skipping'
    AND c.nameShort LIKE '%F%N%'
    AND iac.name LIKE '%Marksheet%1%'
    AND p_teacher.surname = 'Smith'
ORDER BY iac.name;


-- STEP 6: More specific query - Find ALL internal assessments for Faith in 4 F&N
SELECT
    iae.gibbonInternalAssessmentEntryID,
    iae.gibbonInternalAssessmentColumnID,
    p_student.preferredName as studentFirstName,
    p_student.surname as studentLastName,
    c.nameShort as course,
    cc.nameShort as class,
    iac.name as assessmentName,
    iae.attainmentValue,
    iae.comment,
    iae.gibbonPersonIDStudent,
    iac.gibbonCourseClassID
FROM gibbonInternalAssessmentEntry iae
JOIN gibbonInternalAssessmentColumn iac
    ON iae.gibbonInternalAssessmentColumnID = iac.gibbonInternalAssessmentColumnID
JOIN gibbonCourseClass cc
    ON iac.gibbonCourseClassID = cc.gibbonCourseClassID
JOIN gibbonCourse c
    ON cc.gibbonCourseID = c.gibbonCourseID
JOIN gibbonPerson p_student
    ON iae.gibbonPersonIDStudent = p_student.gibbonPersonID
WHERE p_student.preferredName = 'Faith'
    AND p_student.surname = 'Skipping'
    AND c.nameShort LIKE '%F%N%'
ORDER BY iac.name;


-- =====================================================
-- DELETION QUERIES (RUN ONLY AFTER VERIFYING ABOVE)
-- =====================================================

-- STEP 7: DELETE the incorrect entry
-- IMPORTANT: First run the SELECT above to get the exact gibbonInternalAssessmentEntryID
-- Then use it in the DELETE statement below

-- Option A: Delete by specific entry ID (SAFEST - use this)
-- DELETE FROM gibbonInternalAssessmentEntry
-- WHERE gibbonInternalAssessmentEntryID = 'XXXX';  -- Replace XXXX with actual ID from STEP 5


-- Option B: Delete by matching criteria (use only if you're 100% sure)
-- DELETE iae
-- FROM gibbonInternalAssessmentEntry iae
-- JOIN gibbonInternalAssessmentColumn iac
--     ON iae.gibbonInternalAssessmentColumnID = iac.gibbonInternalAssessmentColumnID
-- JOIN gibbonCourseClass cc
--     ON iac.gibbonCourseClassID = cc.gibbonCourseClassID
-- JOIN gibbonCourse c
--     ON cc.gibbonCourseID = c.gibbonCourseID
-- JOIN gibbonPerson p_student
--     ON iae.gibbonPersonIDStudent = p_student.gibbonPersonID
-- WHERE p_student.preferredName = 'Faith'
--     AND p_student.surname = 'Skipping'
--     AND c.nameShort LIKE '%F%N%'
--     AND iac.name LIKE '%Marksheet%1%';


-- STEP 8: Verify deletion
-- Re-run STEP 6 to confirm the incorrect entry is gone
SELECT
    iae.gibbonInternalAssessmentEntryID,
    iae.gibbonInternalAssessmentColumnID,
    p_student.preferredName as studentFirstName,
    p_student.surname as studentLastName,
    c.nameShort as course,
    cc.nameShort as class,
    iac.name as assessmentName,
    iae.attainmentValue,
    iae.comment
FROM gibbonInternalAssessmentEntry iae
JOIN gibbonInternalAssessmentColumn iac
    ON iae.gibbonInternalAssessmentColumnID = iac.gibbonInternalAssessmentColumnID
JOIN gibbonCourseClass cc
    ON iac.gibbonCourseClassID = cc.gibbonCourseClassID
JOIN gibbonCourse c
    ON cc.gibbonCourseID = c.gibbonCourseID
JOIN gibbonPerson p_student
    ON iae.gibbonPersonIDStudent = p_student.gibbonPersonID
WHERE p_student.preferredName = 'Faith'
    AND p_student.surname = 'Skipping'
    AND c.nameShort LIKE '%F%N%'
ORDER BY iac.name;
