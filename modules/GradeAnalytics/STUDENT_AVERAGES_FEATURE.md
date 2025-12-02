# Student Final Average Feature - Implementation Summary

## Overview
This document describes the new functionality added to the Grade Analytics module to calculate and display students' final averages across all their subjects.

## New Features

### 1. Student Averages Ranking Page
A new page that displays students ranked by their final average percentage across all enrolled courses.

**File:** `studentAveragesRanking.php`

**Features:**
- Calculate final average for each student across all their subjects
- Display students ranked from highest to lowest average
- Color-coded averages:
  - Green (≥85%): Excellent performance
  - Blue (≥70%): Good performance
  - Orange (≥55%): Satisfactory performance
  - Red (<55%): Needs improvement
- Interactive bar chart showing top 20 students
- Filterable by:
  - Form Group
  - Year Group
  - Assessment Type
- Shows total number of subjects per student
- CSV export functionality
- Print-friendly version

### 2. Chart Visualization
An interactive bar chart displays the top students by final average:
- Uses Chart.js library for rendering
- Color-coded bars matching the grade thresholds
- Responsive design
- Toggle show/hide functionality
- Shows top 20 students by default
- Tooltips display exact percentages

### 3. Gateway Methods
Added two new methods to `GradeAnalyticsGateway.php`:

#### `selectStudentAverages($gibbonSchoolYearID, $filters = [])`
Calculates and returns students' final averages across all subjects.

**Returns:**
- Student ID, name, form group, year group
- Total number of courses enrolled
- Final average percentage (rounded to 2 decimal places)
- Results ordered by average (DESC)

**Filters:**
- Form Group ID
- Year Group ID
- Assessment Type

**Calculation:**
- Only includes numeric percentage grades
- Averages across all internal assessment entries
- Groups by student
- Excludes null or empty grades

#### `selectStudentSubjectGrades($gibbonPersonID, $gibbonSchoolYearID)`
Retrieves individual subject grades for a specific student (for future expansion).

**Returns:**
- Course name
- Assessment name
- Assessment type
- Grade (original value)
- Numeric grade (converted)

### 4. Integration with Prize Giving Report
Updated `prizeGivingReport.php` to:
- Format grade display with percentage symbol
- Add link to Student Averages Ranking page
- Maintain existing filtering functionality

### 5. Print Version
Created `studentAveragesRanking_print.php` for printer-friendly output:
- Clean table layout
- Summary statistics (total students, highest/lowest/class average)
- Filter information display
- Optimized for printing

## Database Structure

### Tables Used
The feature queries the following Gibbon core tables:
- `gibbonPerson` - Student information
- `gibbonStudentEnrolment` - Enrollment data
- `gibbonFormGroup` - Form group information
- `gibbonYearGroup` - Year group details
- `gibbonCourseClassPerson` - Student-course relationships
- `gibbonCourseClass` - Course class details
- `gibbonCourse` - Course information
- `gibbonInternalAssessmentColumn` - Assessment column definitions
- `gibbonInternalAssessmentEntry` - Assessment grades (attainmentValue)

### Key Assumption
Internal Assessment grades are stored as **numeric percentages** in the `attainmentValue` field.

## Module Manifest Updates
Added new action to `manifest.php`:

```php
$actionRows[] = [
    'name' => 'Student Averages Ranking',
    'precedence' => '2',
    'category' => 'Reports',
    'description' => 'View student rankings based on their final average across all subjects.',
    'URLList' => 'studentAveragesRanking.php',
    'entryURL' => 'studentAveragesRanking.php',
    'entrySidebar' => 'Y',
    'menuShow' => 'Y',
    'defaultPermissionAdmin' => 'Y',
    'defaultPermissionTeacher' => 'Y',
    'defaultPermissionStudent' => 'N',
    'defaultPermissionParent' => 'N',
    'defaultPermissionSupport' => 'N',
    'categoryPermissionStaff' => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent' => 'N',
    'categoryPermissionOther' => 'N'
];
```

## Permissions
- **Admin**: Full access
- **Teacher**: Full access
- **Student**: No access
- **Parent**: No access
- **Support**: No access

## Usage Guide

### Accessing the Feature
1. Log in as an Admin or Teacher
2. Navigate to Grade Analytics module
3. Click on "Student Averages Ranking" in the sidebar or reports menu

### Using Filters
1. Select desired filters (optional):
   - Form Group: Filter by specific form group
   - Year Group: Filter by year group
   - Assessment Type: Filter by assessment type (e.g., "Test", "Quiz", "Exam")
2. Click "Apply Filters"
3. Results will display automatically

### Chart Interaction
- Click "Toggle Chart View" to show/hide the chart
- Hover over bars to see exact percentages
- Chart shows top 20 students by default

### Exporting Data
- **CSV Export**: Click "Export to CSV" button to download data as CSV file
- **Print**: Click the Print icon to open printer-friendly version

## Example Use Cases

### 1. Prize Giving Award Selection
**Scenario**: Need to identify top 10 students for academic excellence awards

**Steps:**
1. Access Student Averages Ranking page
2. Apply filters if needed (e.g., specific year group)
3. View top-ranked students
4. Use chart to visualize performance
5. Export to CSV for records

### 2. Form Group Performance Analysis
**Scenario**: Compare average performance across different form groups

**Steps:**
1. Filter by first form group
2. Note the class average and top performers
3. Change filter to second form group
4. Compare results

### 3. Identifying Students Needing Support
**Scenario**: Find students with below-average performance

**Steps:**
1. Apply relevant filters
2. Scroll to bottom of ranking
3. Identify students with red-coded averages (<55%)
4. Export list for intervention planning

## Technical Notes

### Grade Calculation
The final average is calculated using:
```sql
ROUND(AVG(CAST(me.attainmentValue AS DECIMAL(10,2))), 2)
```

This ensures:
- Numeric conversion of percentage strings
- Proper averaging across all assessments
- 2 decimal place precision

### Data Validation
The query includes validation to ensure:
- Only numeric grades are included (`REGEXP '^[0-9]+(\\.[0-9]+)?$'`)
- No NULL or empty values
- Student must be enrolled in current school year
- Student role = 'Student' (not teacher)

### Performance Considerations
- Query groups by student to calculate averages
- Indexes on foreign keys improve performance
- Filters reduce dataset for faster processing
- Top 20 limit on chart prevents rendering issues

## Future Enhancement Ideas

1. **Individual Student Reports**: Drill-down to see subject-wise breakdown
2. **Historical Trending**: Compare averages across multiple school years
3. **Grade Distribution**: Show how many students fall into each grade band
4. **Subject-wise Averages**: Identify strongest/weakest subjects
5. **Improvement Tracking**: Show average improvement over time
6. **Percentile Rankings**: Show percentile position for each student
7. **Parent Access**: Allow parents to view their child's ranking (privacy-controlled)
8. **Email Reports**: Automated email summaries to form tutors

## Testing Checklist

- [ ] Page loads without errors
- [ ] Filters populate correctly
- [ ] Student data displays accurately
- [ ] Final averages calculate correctly
- [ ] Ranking order is correct (highest to lowest)
- [ ] Chart renders properly
- [ ] Chart toggle works
- [ ] Color coding is accurate
- [ ] CSV export downloads correctly
- [ ] Print version formats properly
- [ ] Permissions enforce correctly
- [ ] Cross-browser compatibility
- [ ] Mobile responsiveness

## Files Modified/Created

### Created
- `studentAveragesRanking.php` - Main ranking page
- `studentAveragesRanking_print.php` - Print version
- `STUDENT_AVERAGES_FEATURE.md` - This documentation

### Modified
- `src/GradeAnalyticsGateway.php` - Added new query methods
- `manifest.php` - Added new action
- `prizeGivingReport.php` - Added link and formatting

## Dependencies
- Chart.js v3.9.1 (loaded from CDN)
- Gibbon Core v30.0.00
- PHP 8.0+
- MySQL 5.7+

## Support
For issues or questions:
1. Check Gibbon error logs
2. Verify database permissions
3. Ensure Internal Assessment data exists
4. Check browser console for JavaScript errors

---

**Version**: 1.0.0
**Date**: 2025-12-01
**Author**: Claude Code
