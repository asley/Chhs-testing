# aiTeacher Module - Implementation Roadmap

**Project**: AI Teacher Assistance Module Enhancement
**Version**: 2.0.0
**Created**: 2025-12-21
**Duration**: 6-12 Months
**Status**: Planning Phase

---

## Executive Summary

This roadmap outlines the strategic enhancement of the aiTeacher module from v1.0.0 to v2.0.0, transforming it from a teacher-focused productivity tool into a comprehensive AI-powered educational ecosystem that engages students, parents, and teachers alike.

**Key Goals:**
- Increase student engagement through personalized AI tutoring
- Provide parents with transparent, actionable insights
- Enable predictive analytics for early intervention
- Foster collaborative learning through AI-moderated study groups
- Ensure ethical AI use with robust safety and moderation features

**Success Metrics:**
- 80% student adoption rate within 6 months
- 30% reduction in students scoring below threshold
- 90% parent satisfaction with AI-generated reports
- 50% reduction in teacher time spent on administrative tasks

---

## Project Phases Overview

| Phase | Duration | Focus Area | Key Deliverables |
|-------|----------|------------|------------------|
| **Phase 1** | Months 1-2 | Quick Wins & Foundation | Student AI Tutor, Stream Integration, Question Bank, Mobile UI |
| **Phase 2** | Months 3-4 | Analytics & Communication | Parent Portal, Learning Profiles, Predictive Dashboard, Moderation |
| **Phase 3** | Months 5-6 | Advanced Features | Gamification, Study Groups, Multi-modal AI, Peer Tutoring |
| **Phase 4** | Ongoing | Optimization & Scaling | Performance tuning, Cost optimization, Feature refinement |

---

# Phase 1: Quick Wins & Foundation (Months 1-2)

**Objective**: Deliver immediate value with core features that have high impact and low complexity.

## 1.1 Student AI Tutor Chat

### Overview
Create a private, personalized AI tutor for each student that provides 24/7 homework help, concept explanations, and study guidance.

### Database Changes
```sql
-- Create student conversation tracking table
CREATE TABLE `aiTeacherStudentConversations` (
  `conversationID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `gibbonPersonID` int(10) unsigned NOT NULL,
  `gibbonCourseID` int(8) unsigned zerofill DEFAULT NULL,
  `gibbonSchoolYearID` int(3) unsigned zerofill NOT NULL,
  `sessionID` varchar(50) NOT NULL, -- Group related messages
  `message` text NOT NULL,
  `sender` enum('student','ai','teacher') NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `flagged` tinyint(1) DEFAULT 0,
  `flagReason` varchar(255) DEFAULT NULL,
  `context` text, -- JSON: conversation history for AI context
  `rating` enum('helpful','not_helpful') DEFAULT NULL,
  `teacherReviewed` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`conversationID`),
  KEY `idx_student` (`gibbonPersonID`),
  KEY `idx_session` (`sessionID`),
  KEY `idx_flagged` (`flagged`),
  CONSTRAINT `aiTeacherStudentConversations_ibfk_1`
    FOREIGN KEY (`gibbonPersonID`) REFERENCES `gibbonPerson` (`gibbonPersonID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Create conversation sessions table
CREATE TABLE `aiTeacherChatSessions` (
  `sessionID` varchar(50) NOT NULL,
  `gibbonPersonID` int(10) unsigned NOT NULL,
  `startTime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `lastActivity` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `topic` varchar(255) DEFAULT NULL,
  `subject` varchar(100) DEFAULT NULL,
  `messageCount` int(5) DEFAULT 0,
  `resolved` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`sessionID`),
  KEY `idx_student` (`gibbonPersonID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

### File Structure
```
modules/aiTeacher/
├── student_ai_tutor.php          (NEW - Main chat interface)
├── student_ai_tutor_ajax.php     (NEW - Handle chat requests)
├── student_chat_history.php      (NEW - View past conversations)
├── js/
│   └── student_tutor.js          (NEW - Chat UI interactions)
└── css/
    └── student_tutor.css         (NEW - Chat styling)
```

### Implementation Tasks

#### Week 1: Backend Development
- [ ] Create database migration script in `CHANGEDB.php`
- [ ] Add `student_ai_tutor.php` page with Gibbon page structure
- [ ] Create `student_ai_tutor_ajax.php` for AJAX message handling
- [ ] Implement conversation context management (store last 10 messages)
- [ ] Add session ID generation logic (UUID v4)
- [ ] Create `getStudentChatContext()` function in `moduleFunctions.php`
- [ ] Add automatic flagging logic for inappropriate content
- [ ] Test database CRUD operations

#### Week 2: Frontend Development
- [ ] Build chat UI with Tailwind CSS (speech bubble design)
- [ ] Add JavaScript for real-time message sending
- [ ] Implement typing indicator animation
- [ ] Add "Rate this response" buttons (thumbs up/down)
- [ ] Create "Flag for Teacher" button with reason dropdown
- [ ] Add conversation history sidebar
- [ ] Implement auto-scroll to latest message
- [ ] Add mobile-responsive design

#### Week 3: AI Integration & Testing
- [ ] Update DeepSeek API wrapper to handle conversation context
- [ ] Add system prompts for AI tutor personality:
  - "You are a patient, encouraging CSEC tutor"
  - "Guide students to answers, don't give direct answers"
  - "Detect when student is frustrated and offer empathy"
- [ ] Implement content safety checks (detect cheating attempts)
- [ ] Add rate limiting (max 50 messages per student per day)
- [ ] Test with 5-10 real students
- [ ] Gather feedback and iterate

#### Week 4: Teacher Monitoring Tools
- [ ] Create teacher dashboard to view flagged conversations
- [ ] Add notification system for flagged content
- [ ] Build conversation analytics (avg messages per session, topics)
- [ ] Add permission action to manifest.php
- [ ] Write user documentation
- [ ] Deploy to production

### Success Criteria
- [ ] Students can send messages and receive AI responses within 5 seconds
- [ ] Conversation context is maintained across 10+ messages
- [ ] Inappropriate content is flagged automatically with 90%+ accuracy
- [ ] At least 50% of students use the feature weekly
- [ ] Teacher satisfaction score: 4/5 or higher

---

## 1.2 Stream Module Integration

### Overview
Connect aiTeacher with the existing Stream module to enable social sharing of AI-generated content and automated educational posts.

### Database Changes
```sql
-- Link AI resources to Stream posts
CREATE TABLE `aiTeacherStreamLinks` (
  `linkID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `streamPostID` int(10) unsigned zerofill NOT NULL,
  `aiResourceType` enum('lesson_plan','quiz','worksheet','study_guide') NOT NULL,
  `aiResourceID` int(10) unsigned NOT NULL,
  `sharedBy` int(10) unsigned NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`linkID`),
  KEY `idx_stream_post` (`streamPostID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Store scheduled AI posts
CREATE TABLE `aiTeacherScheduledPosts` (
  `postID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `postType` enum('tip','fact','motivational','reminder') NOT NULL,
  `content` text NOT NULL,
  `streamCategoryID` int(3) unsigned zerofill NOT NULL,
  `scheduledDate` date NOT NULL,
  `posted` tinyint(1) DEFAULT 0,
  `streamPostID` int(10) unsigned zerofill DEFAULT NULL,
  PRIMARY KEY (`postID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

### Implementation Tasks

#### Week 1: Core Integration
- [ ] Create `postToStream()` function in `moduleFunctions.php`
- [ ] Add "Share to Stream" button on resource generator pages
- [ ] Implement sharing logic (create post, attach resource link)
- [ ] Add Stream category selector for sharing
- [ ] Test posting from aiTeacher to Stream

#### Week 2: Automated Posts
- [ ] Create collection of 100+ educational facts for CSEC subjects
- [ ] Build `generateDailyTip()` AI function
- [ ] Create cron job script: `cli/aiTeacher_daily_posts.php`
- [ ] Add admin settings for post frequency
- [ ] Implement post scheduler logic
- [ ] Test automated posting

#### Week 3: Analytics & Engagement
- [ ] Add view counter for shared AI resources
- [ ] Create "Popular AI Resources" widget for Stream
- [ ] Track which resources get most engagement
- [ ] Add "Generate Similar" button on popular posts
- [ ] Display engagement metrics on aiTeacher dashboard

#### Week 4: Polish & Deploy
- [ ] Add moderation for auto-generated posts
- [ ] Create admin interface to review scheduled posts
- [ ] Add ability to pause/resume auto-posting
- [ ] Write documentation for teachers
- [ ] Deploy and monitor

### Success Criteria
- [ ] AI-generated resources can be shared to Stream with 1 click
- [ ] Automated daily posts run without errors
- [ ] At least 20% of Stream posts are AI-generated content
- [ ] Student engagement with AI posts: 50%+ view rate

---

## 1.3 Question Bank with AI Tagging

### Overview
Build a searchable repository of all AI-generated questions with intelligent tagging, reusability, and effectiveness tracking.

### Database Changes
```sql
CREATE TABLE `aiTeacherQuestionBank` (
  `questionID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `questionText` text NOT NULL,
  `questionType` enum('multiple_choice','short_answer','essay','structured','true_false') NOT NULL,
  `subject` varchar(100) NOT NULL,
  `topic` varchar(255) DEFAULT NULL,
  `subtopic` varchar(255) DEFAULT NULL,
  `difficulty` enum('easy','medium','hard') DEFAULT 'medium',
  `bloomsLevel` enum('remember','understand','apply','analyze','evaluate','create') DEFAULT 'understand',
  `csecSyllabusRef` varchar(100) DEFAULT NULL, -- e.g., "CSEC Biology Section 2.3"
  `correctAnswer` text,
  `explanation` text,
  `markingScheme` text, -- JSON: points allocation
  `timesUsed` int(10) DEFAULT 0,
  `totalAttempts` int(10) DEFAULT 0,
  `correctAttempts` int(10) DEFAULT 0,
  `avgScore` decimal(5,2) DEFAULT NULL, -- For essay/structured questions
  `gibbonPersonIDCreator` int(10) unsigned NOT NULL,
  `dateCreated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `lastModified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `approved` tinyint(1) DEFAULT 0, -- Requires teacher review
  `tags` text, -- JSON: ["photosynthesis", "respiration", "biology"]
  `notes` text, -- Teacher notes about the question
  PRIMARY KEY (`questionID`),
  KEY `idx_subject` (`subject`),
  KEY `idx_difficulty` (`difficulty`),
  KEY `idx_blooms` (`bloomsLevel`),
  FULLTEXT KEY `ft_question` (`questionText`,`topic`,`tags`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `aiTeacherQuestionOptions` (
  `optionID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `questionID` int(10) unsigned NOT NULL,
  `optionLetter` char(1) NOT NULL, -- A, B, C, D
  `optionText` text NOT NULL,
  `isCorrect` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`optionID`),
  KEY `idx_question` (`questionID`),
  CONSTRAINT `aiTeacherQuestionOptions_ibfk_1`
    FOREIGN KEY (`questionID`) REFERENCES `aiTeacherQuestionBank` (`questionID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `aiTeacherQuestionUsage` (
  `usageID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `questionID` int(10) unsigned NOT NULL,
  `gibbonPersonID` int(10) unsigned NOT NULL, -- Teacher who used it
  `assessmentType` varchar(100) DEFAULT NULL, -- "Quiz", "Test", "Homework"
  `dateUsed` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `studentCount` int(5) DEFAULT 0,
  `feedback` text, -- Teacher feedback on question quality
  PRIMARY KEY (`usageID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

### File Structure
```
modules/aiTeacher/
├── question_bank.php              (NEW - Browse/search questions)
├── question_bank_view.php         (NEW - View single question)
├── question_bank_edit.php         (NEW - Edit existing question)
├── question_bank_export.php       (NEW - Export to PDF/Word)
├── question_bank_analytics.php    (NEW - Question effectiveness stats)
└── src/
    └── QuestionBankGateway.php    (NEW - Database operations)
```

### Implementation Tasks

#### Week 1: Database & Core Functions
- [ ] Create database tables and indexes
- [ ] Build `QuestionBankGateway.php` class
- [ ] Implement CRUD operations (create, read, update, delete)
- [ ] Add search functionality (full-text search)
- [ ] Create filtering logic (subject, difficulty, Bloom's level)
- [ ] Test database operations

#### Week 2: Question Browser Interface
- [ ] Create `question_bank.php` with data table
- [ ] Add search bar with autocomplete
- [ ] Implement advanced filters (multi-select)
- [ ] Add pagination (50 questions per page)
- [ ] Create "Preview" modal for quick view
- [ ] Add "Add to Assessment" button
- [ ] Build responsive grid view option

#### Week 3: Auto-Tagging & AI Enhancement
- [ ] Modify `resource_generator.php` to save questions to bank
- [ ] Add AI auto-tagging logic:
  - Extract topics from question text
  - Classify Bloom's taxonomy level
  - Suggest difficulty based on question structure
  - Map to CSEC syllabus sections
- [ ] Implement batch import from CSV
- [ ] Add duplicate detection algorithm
- [ ] Create approval workflow for teachers

#### Week 4: Analytics & Export
- [ ] Build question effectiveness dashboard
- [ ] Show: usage count, success rate, avg time to answer
- [ ] Create "Top Performing Questions" report
- [ ] Implement PDF export (formatted assessment)
- [ ] Add Word export with editable formatting
- [ ] Create print-friendly view
- [ ] Deploy and train teachers

### Success Criteria
- [ ] 500+ questions in bank within first month
- [ ] Search returns results in <2 seconds
- [ ] 90% accuracy on AI auto-tagging
- [ ] 75% of teachers use question bank weekly
- [ ] Average 4/5 star rating on question quality

---

## 1.4 Mobile-Responsive UI Overhaul

### Overview
Redesign all aiTeacher pages with mobile-first approach using Tailwind CSS for seamless experience on phones and tablets.

### Implementation Tasks

#### Week 1: Audit & Planning
- [ ] Audit all existing aiTeacher pages for mobile issues
- [ ] Create responsive design mockups (mobile, tablet, desktop)
- [ ] Define breakpoints: mobile (<640px), tablet (640-1024px), desktop (>1024px)
- [ ] Document component patterns (buttons, forms, cards)
- [ ] Set up Tailwind config for aiTeacher theme

#### Week 2: Core Component Refactoring
- [ ] Refactor `index.php` dashboard with responsive grid
- [ ] Update `curriculum_support.php` forms for mobile
- [ ] Redesign `assessment_analysis.php` tables (scroll, card view)
- [ ] Make `resource_generator.php` touch-friendly
- [ ] Rebuild `chatbot.php` for mobile chat UI
- [ ] Test on iPhone, Android, iPad

#### Week 3: Progressive Web App (PWA) Setup
- [ ] Create `manifest.json` for PWA
- [ ] Add service worker for offline caching
- [ ] Implement "Add to Home Screen" prompt
- [ ] Test offline functionality
- [ ] Add push notification support (for alerts)

#### Week 4: Performance & Accessibility
- [ ] Optimize images (lazy loading, WebP format)
- [ ] Minify CSS and JavaScript
- [ ] Add ARIA labels for screen readers
- [ ] Test with keyboard navigation
- [ ] Run Lighthouse audit (target: 90+ score)
- [ ] Deploy and gather user feedback

### Success Criteria
- [ ] All pages load in <3 seconds on 4G mobile
- [ ] Lighthouse score: 90+ (Performance, Accessibility, Best Practices)
- [ ] 50%+ of users access via mobile within 2 months
- [ ] Zero critical mobile usability issues

---

# Phase 2: Analytics & Communication (Months 3-4)

**Objective**: Build data-driven insights and strengthen parent-teacher-student communication.

## 2.1 Parent Portal & AI Reports

### Overview
Provide parents with weekly AI-generated progress reports, personalized recommendations, and a Q&A interface for understanding their child's performance.

### Database Changes
```sql
CREATE TABLE `aiTeacherParentReports` (
  `reportID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `gibbonPersonID` int(10) unsigned NOT NULL, -- Student ID
  `gibbonSchoolYearID` int(3) unsigned zerofill NOT NULL,
  `reportWeek` int(2) NOT NULL, -- Week number (1-52)
  `reportDate` date NOT NULL,
  `summary` text NOT NULL, -- AI-generated summary
  `strengths` text, -- JSON array
  `areasForImprovement` text, -- JSON array
  `recommendations` text, -- What parents can do at home
  `nextSteps` text, -- What school will do
  `avgGrade` decimal(5,2),
  `attendanceRate` decimal(5,2),
  `behaviorScore` int(3),
  `sentToParent` tinyint(1) DEFAULT 0,
  `sentDate` timestamp NULL,
  `parentViewed` tinyint(1) DEFAULT 0,
  `viewedDate` timestamp NULL,
  `parentFeedback` text,
  PRIMARY KEY (`reportID`),
  KEY `idx_student` (`gibbonPersonID`),
  KEY `idx_week` (`reportWeek`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `aiTeacherParentQuestions` (
  `questionID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `gibbonPersonIDParent` int(10) unsigned NOT NULL,
  `gibbonPersonIDStudent` int(10) unsigned NOT NULL,
  `question` text NOT NULL,
  `aiResponse` text,
  `requiresTeacherReview` tinyint(1) DEFAULT 0,
  `reviewedByTeacher` tinyint(1) DEFAULT 0,
  `teacherResponse` text,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `helpful` tinyint(1) DEFAULT NULL, -- Parent rating
  PRIMARY KEY (`questionID`),
  KEY `idx_parent` (`gibbonPersonIDParent`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

### File Structure
```
modules/aiTeacher/
├── parent_dashboard.php           (NEW - Parent view of child's progress)
├── parent_weekly_report.php       (NEW - Detailed weekly report)
├── parent_ask_ai.php              (NEW - Q&A about child's performance)
├── parent_resources.php           (NEW - Home support materials)
├── cli/
│   └── generate_parent_reports.php (NEW - Cron job for weekly reports)
└── email_templates/
    └── parent_weekly_report.html  (NEW - Email template)
```

### Implementation Tasks

#### Week 1: Report Generation Engine
- [ ] Build AI prompt for weekly summary generation
- [ ] Create `generateParentReport()` function
- [ ] Implement data aggregation (grades, attendance, behavior)
- [ ] Add trend analysis (improving, declining, stable)
- [ ] Generate personalized home support recommendations
- [ ] Test report quality with 10 sample students

#### Week 2: Parent Portal Interface
- [ ] Create parent-specific permission checks
- [ ] Build `parent_dashboard.php` overview page
- [ ] Design weekly report display with charts
- [ ] Add historical report archive (last 12 weeks)
- [ ] Implement comparison charts (student vs class avg)
- [ ] Add "Download PDF" functionality

#### Week 3: AI Q&A for Parents
- [ ] Create `parent_ask_ai.php` interface
- [ ] Implement privacy filters (parents only see their child's data)
- [ ] Add AI prompt: "Answer parent questions about student performance"
- [ ] Flag sensitive questions for teacher review
- [ ] Send email notifications to teachers for flagged questions
- [ ] Test with parent focus group

#### Week 4: Email Integration & Automation
- [ ] Create HTML email template
- [ ] Build cron job: `cli/generate_parent_reports.php`
- [ ] Schedule: Run every Friday at 6 PM
- [ ] Integrate with Gibbon Messenger for email delivery
- [ ] Add opt-in/opt-out for parents
- [ ] Add "View Online" link in email
- [ ] Deploy and monitor delivery rates

### Success Criteria
- [ ] 95%+ email delivery success rate
- [ ] 70%+ parent open rate within 48 hours
- [ ] 80%+ parent satisfaction ("helpful" rating)
- [ ] 50%+ parents use AI Q&A feature monthly
- [ ] Teacher review time: <30 min/week

---

## 2.2 Learning Style Detection & Adaptive Profiles

### Overview
Use AI to analyze student performance patterns and detect learning preferences, then automatically generate personalized study materials.

### Database Changes
```sql
CREATE TABLE `aiTeacherLearningProfiles` (
  `profileID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `gibbonPersonID` int(10) unsigned NOT NULL,
  `gibbonSchoolYearID` int(3) unsigned zerofill NOT NULL,
  `learningStyle` enum('visual','auditory','kinesthetic','reading_writing','mixed') DEFAULT 'mixed',
  `confidenceScore` decimal(5,2) DEFAULT NULL, -- How confident AI is about learning style
  `preferredDifficulty` enum('basic','intermediate','advanced') DEFAULT 'intermediate',
  `pacePreference` enum('fast','moderate','slow') DEFAULT 'moderate',
  `strengths` text, -- JSON: ["algebra", "geometry"]
  `weaknesses` text, -- JSON: ["trigonometry"]
  `studyHabits` text, -- JSON: {avg_session_length: 45, preferred_time: "evening"}
  `lastUpdated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `assessmentCount` int(5) DEFAULT 0, -- Number of assessments analyzed
  PRIMARY KEY (`profileID`),
  UNIQUE KEY `student_year` (`gibbonPersonID`, `gibbonSchoolYearID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `aiTeacherLearningStyleAssessment` (
  `assessmentID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `gibbonPersonID` int(10) unsigned NOT NULL,
  `questionID` int(5) NOT NULL,
  `answer` varchar(255) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`assessmentID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `aiTeacherAdaptiveContent` (
  `contentID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `profileID` int(10) unsigned NOT NULL,
  `contentType` enum('video','diagram','text','audio','interactive') NOT NULL,
  `subject` varchar(100) NOT NULL,
  `topic` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `effectiveness` decimal(5,2) DEFAULT NULL, -- Student improvement after using
  `dateGenerated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `timesAccessed` int(5) DEFAULT 0,
  PRIMARY KEY (`contentID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

### File Structure
```
modules/aiTeacher/
├── learning_style_quiz.php        (NEW - Initial assessment for students)
├── my_learning_profile.php        (NEW - Student view of their profile)
├── adaptive_content_viewer.php    (NEW - Personalized study materials)
├── teacher_learning_insights.php  (NEW - Class learning style overview)
└── src/
    ├── LearningStyleAnalyzer.php  (NEW - AI analysis logic)
    └── AdaptiveContentGenerator.php (NEW - Generate personalized materials)
```

### Implementation Tasks

#### Week 1: Learning Style Assessment
- [ ] Research VARK learning style questionnaire
- [ ] Create 20-question assessment quiz
- [ ] Build `learning_style_quiz.php` interface
- [ ] Implement scoring algorithm
- [ ] Store results in `aiTeacherLearningStyleAssessment`
- [ ] Generate initial learning profile
- [ ] Test with 20 students

#### Week 2: Performance Pattern Analysis
- [ ] Build `LearningStyleAnalyzer.php` class
- [ ] Analyze assessment data:
  - Visual learners: high scores on diagram-based questions
  - Auditory: prefer verbal explanations
  - Kinesthetic: better on hands-on labs
- [ ] Track time spent on different content types
- [ ] Update learning profile based on behavior
- [ ] Add confidence scoring (need 10+ data points)

#### Week 3: Adaptive Content Generation
- [ ] Create AI prompts for each learning style:
  - Visual: "Generate a labeled diagram explaining..."
  - Auditory: "Write a conversational explanation..."
  - Kinesthetic: "Create a hands-on activity to demonstrate..."
- [ ] Build `AdaptiveContentGenerator.php`
- [ ] Integrate with resource generator
- [ ] Test content quality with teachers
- [ ] Add content library for common topics

#### Week 4: Student & Teacher Dashboards
- [ ] Build `my_learning_profile.php` for students
- [ ] Show learning style with explanation
- [ ] Display recommended study strategies
- [ ] Create `teacher_learning_insights.php`
- [ ] Show class breakdown of learning styles
- [ ] Suggest differentiation strategies
- [ ] Deploy and train teachers

### Success Criteria
- [ ] 90%+ students complete learning style quiz
- [ ] Learning style detection accuracy: 75%+ (validated by teachers)
- [ ] Students using personalized content show 15%+ grade improvement
- [ ] Teachers report easier differentiation with AI insights

---

## 2.3 Predictive Analytics Dashboard

### Overview
Use machine learning to predict student performance trends and identify at-risk students 2-3 weeks before assessments.

### Database Changes
```sql
CREATE TABLE `aiTeacherPredictions` (
  `predictionID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `gibbonPersonID` int(10) unsigned NOT NULL,
  `gibbonCourseID` int(8) unsigned zerofill NOT NULL,
  `gibbonSchoolYearID` int(3) unsigned zerofill NOT NULL,
  `predictionDate` date NOT NULL,
  `predictionType` enum('grade','attendance','behavior','dropout_risk') NOT NULL,
  `predictedValue` decimal(5,2), -- Predicted grade percentage
  `actualValue` decimal(5,2) DEFAULT NULL, -- Filled in after assessment
  `confidence` decimal(5,2), -- AI confidence in prediction (0-100%)
  `riskLevel` enum('low','medium','high','critical') DEFAULT 'low',
  `contributingFactors` text, -- JSON: ["low quiz scores", "declining attendance"]
  `recommendedActions` text, -- JSON array of intervention suggestions
  `actionTaken` text, -- Teacher notes on what intervention was used
  `predictionAccuracy` decimal(5,2) DEFAULT NULL, -- |predicted - actual|
  PRIMARY KEY (`predictionID`),
  KEY `idx_student` (`gibbonPersonID`),
  KEY `idx_risk` (`riskLevel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `aiTeacherPredictionModels` (
  `modelID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `modelName` varchar(100) NOT NULL,
  `subject` varchar(100),
  `modelType` enum('linear_regression','decision_tree','neural_network') DEFAULT 'linear_regression',
  `trainingDataCount` int(10) DEFAULT 0,
  `accuracy` decimal(5,2), -- Validated accuracy on test set
  `features` text, -- JSON: ["avg_quiz_score", "attendance_rate", "homework_completion"]
  `modelParameters` text, -- JSON: serialized model weights
  `lastTrainedDate` timestamp NULL,
  `active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`modelID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `aiTeacherInterventionTracking` (
  `interventionID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `predictionID` int(10) unsigned NOT NULL,
  `gibbonPersonID` int(10) unsigned NOT NULL,
  `interventionType` enum('tutoring','parent_contact','study_group','modified_work','counseling') NOT NULL,
  `description` text NOT NULL,
  `startDate` date NOT NULL,
  `endDate` date DEFAULT NULL,
  `status` enum('planned','active','completed','cancelled') DEFAULT 'planned',
  `effectiveness` enum('very_effective','effective','neutral','ineffective') DEFAULT NULL,
  `notes` text,
  `gibbonPersonIDResponsible` int(10) unsigned NOT NULL, -- Teacher responsible
  PRIMARY KEY (`interventionID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

### File Structure
```
modules/aiTeacher/
├── predictive_dashboard.php       (NEW - Main analytics dashboard)
├── at_risk_students.php           (NEW - List of students needing intervention)
├── prediction_details.php         (NEW - Detailed prediction for one student)
├── intervention_planner.php       (NEW - Plan and track interventions)
├── model_performance.php          (NEW - Admin: model accuracy stats)
├── cli/
│   ├── train_prediction_models.php (NEW - Re-train models monthly)
│   └── generate_daily_predictions.php (NEW - Run predictions daily)
└── src/
    ├── PredictionEngine.php       (NEW - Core ML logic)
    └── InterventionRecommender.php (NEW - Suggest interventions)
```

### Implementation Tasks

#### Week 1: Data Collection & Feature Engineering
- [ ] Identify predictive features:
  - Historical grades (last 5 assessments)
  - Attendance rate (last 4 weeks)
  - Homework completion rate
  - Quiz performance trends
  - Participation scores
  - Time since last parent contact
- [ ] Build data extraction queries
- [ ] Create training dataset (2+ years of historical data)
- [ ] Normalize and clean data (handle missing values)
- [ ] Split into training (80%) and test (20%) sets

#### Week 2: Model Development
- [ ] Research PHP ML libraries (PHP-ML, Rubix ML)
- [ ] Implement linear regression model (baseline)
- [ ] Test model accuracy on historical data
- [ ] Build `PredictionEngine.php` class
- [ ] Create model serialization/deserialization
- [ ] Add prediction confidence scoring
- [ ] Validate predictions against actual outcomes

#### Week 3: Dashboard & Visualization
- [ ] Create `predictive_dashboard.php`
- [ ] Add risk level overview (# of students in each tier)
- [ ] Build charts:
  - Grade trends over time (Chart.js)
  - Risk distribution by class
  - Intervention effectiveness rates
- [ ] Create `at_risk_students.php` filterable table
- [ ] Add "Generate Report" button (PDF export)
- [ ] Implement email alerts for critical risk students

#### Week 4: Intervention System
- [ ] Build `InterventionRecommender.php`
- [ ] Use AI to suggest interventions based on risk factors:
  - Low homework completion → Study buddy assignment
  - Declining quiz scores → Topic-specific tutoring
  - Attendance issues → Parent contact + counselor referral
- [ ] Create `intervention_planner.php` interface
- [ ] Add intervention tracking workflow
- [ ] Schedule cron jobs for daily predictions
- [ ] Train teachers on using predictive insights
- [ ] Deploy and monitor

### Success Criteria
- [ ] Prediction accuracy: 70%+ within ±10% margin
- [ ] 90%+ of at-risk students identified before failing
- [ ] 30% reduction in students scoring below threshold
- [ ] Teachers spend 50% less time identifying struggling students
- [ ] Intervention effectiveness: 60%+ success rate

---

## 2.4 Content Moderation & Safety System

### Overview
Implement AI-powered content filtering, inappropriate language detection, and automated flagging to ensure safe, ethical use of AI tools.

### Database Changes
```sql
CREATE TABLE `aiTeacherModerationFlags` (
  `flagID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `gibbonPersonID` int(10) unsigned NOT NULL,
  `contentType` enum('chat','question','resource','post') NOT NULL,
  `contentID` int(10) unsigned NOT NULL,
  `contentPreview` text, -- First 500 chars of flagged content
  `flagReason` varchar(255),
  `flagType` enum('profanity','violence','cheating','self_harm','bullying','spam','inappropriate') NOT NULL,
  `severity` enum('low','medium','high','critical') DEFAULT 'medium',
  `autoFlagged` tinyint(1) DEFAULT 1, -- Auto vs manual flag
  `reviewed` tinyint(1) DEFAULT 0,
  `reviewedBy` int(10) unsigned DEFAULT NULL,
  `reviewDate` timestamp NULL,
  `action` enum('dismissed','warning','content_removed','user_suspended','escalated') DEFAULT NULL,
  `actionNotes` text,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`flagID`),
  KEY `idx_reviewed` (`reviewed`),
  KEY `idx_severity` (`severity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `aiTeacherModerationRules` (
  `ruleID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ruleName` varchar(255) NOT NULL,
  `ruleType` enum('keyword','pattern','ai_classifier') NOT NULL,
  `rulePattern` text, -- Regex or keyword list
  `flagType` enum('profanity','violence','cheating','self_harm','bullying','spam','inappropriate') NOT NULL,
  `severity` enum('low','medium','high','critical') DEFAULT 'medium',
  `active` tinyint(1) DEFAULT 1,
  `dateCreated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ruleID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `aiTeacherUserWarnings` (
  `warningID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `gibbonPersonID` int(10) unsigned NOT NULL,
  `flagID` int(10) unsigned NOT NULL,
  `warningType` enum('automated','manual') NOT NULL,
  `warningMessage` text NOT NULL,
  `acknowledgedBy` int(10) unsigned DEFAULT NULL,
  `acknowledgedDate` timestamp NULL,
  `issuedDate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`warningID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

### File Structure
```
modules/aiTeacher/
├── moderation_dashboard.php       (NEW - Admin/counselor review panel)
├── moderation_settings.php        (NEW - Configure rules)
├── flagged_content.php            (NEW - Review flagged items)
├── user_warnings.php              (NEW - Warning history)
└── src/
    ├── ContentModerator.php       (NEW - Core moderation logic)
    └── SafetyClassifier.php       (NEW - AI-based content classification)
```

### Implementation Tasks

#### Week 1: Keyword & Pattern Detection
- [ ] Research common inappropriate terms (age-appropriate list)
- [ ] Create profanity dictionary (200+ words)
- [ ] Build cheating detection patterns:
  - "give me the answer"
  - "what's the answer to question 5"
  - "can you do my homework"
- [ ] Add self-harm keyword detection:
  - "want to hurt myself"
  - "suicidal thoughts"
  - Critical priority → immediate counselor alert
- [ ] Create `ContentModerator.php` class
- [ ] Implement `checkContent()` method
- [ ] Test with 1000+ sample inputs

#### Week 2: AI-Based Classification
- [ ] Build `SafetyClassifier.php` using AI API
- [ ] Create AI prompt: "Classify this content as safe/unsafe"
- [ ] Categories: academic, social, inappropriate, concerning
- [ ] Add context awareness (chemistry question about "explosion" is OK)
- [ ] Implement confidence thresholding (flag only >70% confidence)
- [ ] Test false positive rate (target: <5%)

#### Week 3: Moderation Dashboard
- [ ] Create `moderation_dashboard.php` overview
- [ ] Show counts: pending review, resolved, escalated
- [ ] Build `flagged_content.php` review interface
- [ ] Add quick action buttons:
  - Dismiss (not a concern)
  - Warn Student
  - Remove Content
  - Escalate to Admin/Counselor
- [ ] Implement email notifications for critical flags
- [ ] Add bulk actions for efficiency

#### Week 4: User Communication & Policy
- [ ] Create "Acceptable Use Policy" page
- [ ] Add warning system for students
- [ ] Build acknowledgment workflow (student must click "I understand")
- [ ] Create parent notification for serious violations
- [ ] Add usage statistics for admin
- [ ] Write moderation guidelines for staff
- [ ] Train counselors and admins
- [ ] Deploy with monitoring

### Success Criteria
- [ ] 95%+ detection rate for profanity and inappropriate content
- [ ] <5% false positive rate (incorrectly flagged content)
- [ ] Critical flags reviewed within 1 hour
- [ ] Non-critical flags reviewed within 24 hours
- [ ] Zero incidents of harmful content reaching students

---

# Phase 3: Advanced Features (Months 5-6)

**Objective**: Deliver cutting-edge features that differentiate aiTeacher from competitors and maximize student success.

## 3.1 Gamification & Achievement System

### Overview
Integrate with Gibbon's Badges module to reward students for engaging with AI learning tools, improving performance, and helping peers.

### Database Changes
```sql
CREATE TABLE `aiTeacherAchievements` (
  `achievementID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `achievementName` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `badgeID` int(10) unsigned DEFAULT NULL, -- Link to Gibbon Badges module
  `iconPath` varchar(255),
  `category` enum('engagement','improvement','mastery','social','challenge') NOT NULL,
  `xpValue` int(5) DEFAULT 0, -- Experience points awarded
  `criteria` text, -- JSON: {type: "ai_chat_count", threshold: 10}
  `difficulty` enum('bronze','silver','gold','platinum') DEFAULT 'bronze',
  `active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`achievementID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `aiTeacherStudentAchievements` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `gibbonPersonID` int(10) unsigned NOT NULL,
  `achievementID` int(10) unsigned NOT NULL,
  `earnedDate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `progress` decimal(5,2) DEFAULT NULL, -- For multi-step achievements
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_achievement` (`gibbonPersonID`, `achievementID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `aiTeacherXP` (
  `xpID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `gibbonPersonID` int(10) unsigned NOT NULL,
  `gibbonSchoolYearID` int(3) unsigned zerofill NOT NULL,
  `totalXP` int(10) DEFAULT 0,
  `level` int(3) DEFAULT 1, -- Calculated from totalXP
  `rank` int(5) DEFAULT NULL, -- Class ranking
  `lastUpdated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`xpID`),
  UNIQUE KEY `student_year` (`gibbonPersonID`, `gibbonSchoolYearID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `aiTeacherDailyChallenges` (
  `challengeID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `challengeDate` date NOT NULL,
  `subject` varchar(100) NOT NULL,
  `topic` varchar(255) NOT NULL,
  `challengeType` enum('quiz','problem','creative','research') NOT NULL,
  `content` text NOT NULL,
  `correctAnswer` text,
  `xpReward` int(5) DEFAULT 10,
  `difficultyLevel` enum('easy','medium','hard') DEFAULT 'medium',
  `participantCount` int(10) DEFAULT 0,
  PRIMARY KEY (`challengeID`),
  KEY `idx_date` (`challengeDate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `aiTeacherChallengeSubmissions` (
  `submissionID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `challengeID` int(10) unsigned NOT NULL,
  `gibbonPersonID` int(10) unsigned NOT NULL,
  `answer` text NOT NULL,
  `submittedDate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `isCorrect` tinyint(1) DEFAULT NULL,
  `xpAwarded` int(5) DEFAULT 0,
  `feedback` text, -- AI-generated feedback
  PRIMARY KEY (`submissionID`),
  UNIQUE KEY `student_challenge` (`challengeID`, `gibbonPersonID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

### File Structure
```
modules/aiTeacher/
├── achievements.php               (NEW - Browse achievements)
├── my_achievements.php            (NEW - Student achievement profile)
├── leaderboard.php                (NEW - XP leaderboard by class/year)
├── daily_challenge.php            (NEW - Today's challenge)
├── challenge_history.php          (NEW - Past challenges)
├── cli/
│   └── generate_daily_challenge.php (NEW - Cron job)
└── src/
    ├── GamificationEngine.php     (NEW - XP and achievement logic)
    └── BadgeIntegration.php       (NEW - Connect to Badges module)
```

### Implementation Tasks

#### Week 1: Achievement System Foundation
- [ ] Define 20+ achievements:
  - **AI Learner** (Bronze): Ask AI tutor 10 questions
  - **Curious Mind** (Silver): Ask 50 questions
  - **Chat Master** (Gold): Ask 200 questions
  - **Improvement Star** (Silver): Raise grade by 10%
  - **Streak Keeper** (Bronze): Use AI 7 days in a row
  - **Resource Creator** (Bronze): Generate 5 study guides
  - **Perfect Score** (Gold): Get 100% on AI-generated quiz
  - **Helper** (Silver): Peer tutor 3 students
  - **Challenge Champion** (Platinum): Complete 30 daily challenges
- [ ] Create icons for each achievement
- [ ] Build `GamificationEngine.php`
- [ ] Implement achievement tracking logic
- [ ] Add XP calculation system (level up every 100 XP)

#### Week 2: Integration with Badges Module
- [ ] Research Gibbon Badges database schema
- [ ] Create `BadgeIntegration.php` class
- [ ] Auto-create badges in Badges module for each achievement
- [ ] Sync achievement earning → badge granting
- [ ] Test with sample student accounts
- [ ] Add achievement notification popup

#### Week 3: Daily Challenges
- [ ] Build AI challenge generator
- [ ] Create diverse challenge types:
  - **Quiz**: 5 multiple choice questions
  - **Problem**: Real-world application problem
  - **Creative**: "Design an experiment for..."
  - **Research**: "Find 3 examples of..."
- [ ] Create `daily_challenge.php` interface
- [ ] Implement submission and auto-grading
- [ ] Award XP for participation and correctness
- [ ] Schedule cron job for daily generation

#### Week 4: Leaderboard & Social Features
- [ ] Build `leaderboard.php` with filters:
  - Class leaderboard
  - Year group leaderboard
  - Subject-specific leaderboard
  - All-time vs monthly
- [ ] Add privacy settings (opt-in for public leaderboard)
- [ ] Create "Challenge a Friend" feature
- [ ] Add achievement sharing to Stream
- [ ] Implement XP decay (inactive students lose XP slowly)
- [ ] Deploy and promote to students

### Success Criteria
- [ ] 70%+ student participation in gamification
- [ ] Average 15 XP earned per student per week
- [ ] 50%+ daily challenge completion rate
- [ ] 20% increase in AI tutor usage after gamification launch
- [ ] Student survey: 80%+ find achievements motivating

---

## 3.2 Collaborative AI Study Groups

### Overview
Enable students to form AI-moderated study groups where they can collaborate, share resources, and get group-level performance insights.

### Database Changes
```sql
CREATE TABLE `aiTeacherStudyGroups` (
  `groupID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `groupName` varchar(255) NOT NULL,
  `description` text,
  `gibbonCourseID` int(8) unsigned zerofill NOT NULL,
  `gibbonSchoolYearID` int(3) unsigned zerofill NOT NULL,
  `createdBy` int(10) unsigned NOT NULL,
  `maxMembers` int(3) DEFAULT 8,
  `currentMembers` int(3) DEFAULT 1,
  `groupType` enum('open','invite_only','teacher_created') DEFAULT 'open',
  `dateCreated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `active` tinyint(1) DEFAULT 1,
  `groupGoal` text, -- e.g., "Master calculus by end of term"
  `meetingSchedule` varchar(255), -- e.g., "Wednesdays 3-4 PM"
  PRIMARY KEY (`groupID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `aiTeacherStudyGroupMembers` (
  `memberID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `groupID` int(10) unsigned NOT NULL,
  `gibbonPersonID` int(10) unsigned NOT NULL,
  `role` enum('leader','co_leader','member') DEFAULT 'member',
  `joinedDate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `contributionScore` int(5) DEFAULT 0, -- Based on activity
  `status` enum('active','inactive','left') DEFAULT 'active',
  PRIMARY KEY (`memberID`),
  UNIQUE KEY `group_student` (`groupID`, `gibbonPersonID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `aiTeacherGroupMessages` (
  `messageID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `groupID` int(10) unsigned NOT NULL,
  `gibbonPersonID` int(10) unsigned NOT NULL,
  `messageType` enum('text','ai_response','resource_share','question') NOT NULL,
  `message` text NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `flagged` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`messageID`),
  KEY `idx_group` (`groupID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `aiTeacherGroupResources` (
  `resourceID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `groupID` int(10) unsigned NOT NULL,
  `sharedBy` int(10) unsigned NOT NULL,
  `resourceType` enum('study_guide','quiz','notes','video_link','file') NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `filePath` varchar(255),
  `externalLink` varchar(500),
  `likes` int(5) DEFAULT 0,
  `dateShared` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`resourceID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `aiTeacherGroupAnalytics` (
  `analyticsID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `groupID` int(10) unsigned NOT NULL,
  `reportDate` date NOT NULL,
  `avgGroupGrade` decimal(5,2),
  `groupImprovement` decimal(5,2), -- % change from previous week
  `activityScore` int(5), -- Messages, resources shared
  `aiSuggestions` text, -- AI-generated study tips
  PRIMARY KEY (`analyticsID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

### File Structure
```
modules/aiTeacher/
├── study_groups.php               (NEW - Browse and join groups)
├── study_group_create.php         (NEW - Create new group)
├── study_group_view.php           (NEW - Group chat and resources)
├── study_group_analytics.php      (NEW - Group performance dashboard)
├── study_group_manage.php         (NEW - Leader management tools)
└── src/
    └── StudyGroupModerator.php    (NEW - AI group moderation)
```

### Implementation Tasks

#### Week 1: Group Creation & Management
- [ ] Build `study_group_create.php` form
- [ ] Implement group creation logic (auto-add creator as leader)
- [ ] Create `study_groups.php` browse page with filters
- [ ] Add join/leave group functionality
- [ ] Build invitation system for invite-only groups
- [ ] Add group capacity limits
- [ ] Create group settings page

#### Week 2: Group Chat & AI Moderation
- [ ] Build real-time group chat (AJAX polling or WebSockets)
- [ ] Implement AI moderator bot:
  - Greets new members
  - Suggests study topics based on upcoming assessments
  - Answers group questions
  - Detects off-topic conversations and redirects
- [ ] Add "@AI" mention to ask group AI questions
- [ ] Implement message flagging for inappropriate content
- [ ] Test with pilot groups

#### Week 3: Resource Sharing & Collaboration
- [ ] Add "Share Resource" button in group view
- [ ] Support file uploads (PDF notes, images)
- [ ] Add external link sharing (YouTube videos, articles)
- [ ] Implement collaborative quiz feature:
  - AI generates quiz for group
  - Members take quiz together
  - Leaderboard within group
- [ ] Add resource rating (like/helpful buttons)
- [ ] Create group resource library

#### Week 4: Group Analytics & Insights
- [ ] Build `study_group_analytics.php` dashboard
- [ ] Show group performance trends (avg grades over time)
- [ ] Compare group avg vs class avg
- [ ] Identify most/least active members
- [ ] AI generates weekly group report:
  - "Your group improved 12% this week"
  - "Focus on topic X next week"
  - "Member Y is struggling with Z"
- [ ] Add "Group Goal" progress tracker
- [ ] Deploy and promote

### Success Criteria
- [ ] 40%+ of students join at least one study group
- [ ] Groups with active AI use show 20%+ better grades than solo students
- [ ] Average 50 messages per group per week
- [ ] 80% of groups remain active for full term
- [ ] Student feedback: 4.5/5 on usefulness

---

## 3.3 Multi-Modal AI (Vision, Audio, Video)

### Overview
Expand AI capabilities beyond text to analyze images (student work, diagrams), transcribe audio lectures, and generate visual learning materials.

### Database Changes
```sql
CREATE TABLE `aiTeacherMediaAnalysis` (
  `analysisID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `gibbonPersonID` int(10) unsigned NOT NULL,
  `mediaType` enum('image','audio','video','pdf') NOT NULL,
  `filePath` varchar(255) NOT NULL,
  `fileSize` int(10) unsigned NOT NULL,
  `uploadDate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `analysisType` enum('handwriting_check','diagram_explanation','lecture_transcription','pdf_summary') NOT NULL,
  `analysisResult` text, -- AI output
  `confidence` decimal(5,2), -- AI confidence
  `processingTime` int(5), -- seconds
  `cost` decimal(10,4), -- API cost in USD
  PRIMARY KEY (`analysisID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `aiTeacherGeneratedMedia` (
  `mediaID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `gibbonPersonID` int(10) unsigned NOT NULL,
  `mediaType` enum('diagram','infographic','flashcard','video_summary') NOT NULL,
  `subject` varchar(100) NOT NULL,
  `topic` varchar(255) NOT NULL,
  `prompt` text, -- User's original request
  `filePath` varchar(255) NOT NULL,
  `dateGenerated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `downloads` int(5) DEFAULT 0,
  PRIMARY KEY (`mediaID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

### File Structure
```
modules/aiTeacher/
├── analyze_image.php              (NEW - Upload & analyze images)
├── transcribe_audio.php           (NEW - Lecture transcription)
├── generate_diagram.php           (NEW - AI diagram generator)
├── create_flashcards.php          (NEW - Visual flashcard creator)
└── src/
    ├── VisionAPI.php              (NEW - OpenAI Vision/Google Vision)
    ├── AudioProcessor.php         (NEW - Whisper API integration)
    └── DiagramGenerator.php       (NEW - Image generation)
```

### Implementation Tasks

#### Week 1: Image Analysis
- [ ] Integrate OpenAI Vision API or Google Vision API
- [ ] Create `analyze_image.php` upload interface
- [ ] Implement image analysis features:
  - **Handwriting Check**: Analyze student handwritten work
  - **Diagram Explanation**: Explain uploaded diagrams
  - **Math Problem Solver**: Solve handwritten math problems
  - **Lab Report Analysis**: Check lab diagrams for accuracy
- [ ] Add image preprocessing (resize, enhance contrast)
- [ ] Test with sample images

#### Week 2: Audio Transcription
- [ ] Integrate Whisper API (OpenAI) or Google Speech-to-Text
- [ ] Build `transcribe_audio.php` interface
- [ ] Support formats: MP3, WAV, M4A
- [ ] Add speaker diarization (identify different speakers)
- [ ] Generate timestamped transcripts
- [ ] Add keyword extraction and summarization
- [ ] Test with recorded lectures

#### Week 3: Visual Content Generation
- [ ] Integrate DALL-E or Stable Diffusion API
- [ ] Create `generate_diagram.php`
- [ ] Generate educational visuals:
  - Biology diagrams (cell structure, photosynthesis)
  - Physics diagrams (force diagrams, circuits)
  - Chemistry diagrams (molecular structures)
  - Math graphs and charts
- [ ] Add style controls (realistic, cartoon, schematic)
- [ ] Implement quality filters (reject low-quality outputs)

#### Week 4: Flashcard Creator
- [ ] Build `create_flashcards.php` AI generator
- [ ] Auto-generate flashcards from text input
- [ ] Add image support for visual flashcards
- [ ] Create printable PDF format (Anki-compatible)
- [ ] Add spaced repetition algorithm
- [ ] Build flashcard quiz mode
- [ ] Deploy all features

### Success Criteria
- [ ] Image analysis accuracy: 85%+ on handwriting recognition
- [ ] Audio transcription accuracy: 90%+ on clear recordings
- [ ] Generated diagrams rated 4/5+ for accuracy
- [ ] 30% of students use at least one multi-modal feature monthly
- [ ] Processing time: <30 seconds for most requests

---

## 3.4 AI-Powered Peer Tutoring Matching

### Overview
Use AI to intelligently match struggling students with peer tutors based on academic strengths, learning styles, schedules, and personality compatibility.

### Database Changes
```sql
CREATE TABLE `aiTeacherPeerTutors` (
  `tutorID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `gibbonPersonID` int(10) unsigned NOT NULL,
  `gibbonSchoolYearID` int(3) unsigned zerofill NOT NULL,
  `subjects` text, -- JSON: ["Mathematics", "Biology"]
  `availability` text, -- JSON: {monday: ["3-4 PM", "4-5 PM"], tuesday: []}
  `minGrade` decimal(5,2), -- Minimum grade in subject to tutor (e.g., 85%)
  `maxStudents` int(3) DEFAULT 3, -- Max concurrent tutees
  `currentStudents` int(3) DEFAULT 0,
  `teachingStyle` enum('patient','fast_paced','visual','verbal') DEFAULT 'patient',
  `totalSessions` int(5) DEFAULT 0,
  `avgRating` decimal(3,2), -- Student ratings (1-5)
  `active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`tutorID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `aiTeacherPeerTutoring` (
  `sessionID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tutorID` int(10) unsigned NOT NULL,
  `studentID` int(10) unsigned NOT NULL,
  `subject` varchar(100) NOT NULL,
  `topic` varchar(255),
  `matchScore` decimal(5,2), -- AI compatibility score (0-100)
  `matchReasoning` text, -- Why AI matched them
  `sessionDate` datetime NOT NULL,
  `duration` int(5), -- minutes
  `sessionType` enum('in_person','online','hybrid') DEFAULT 'in_person',
  `location` varchar(255),
  `status` enum('scheduled','completed','cancelled','no_show') DEFAULT 'scheduled',
  `effectiveness` enum('very_helpful','helpful','neutral','not_helpful') DEFAULT NULL,
  `studentRating` int(1), -- 1-5 stars
  `tutorRating` int(1), -- 1-5 stars
  `notes` text,
  `aiSuggestions` text, -- Post-session improvement suggestions
  PRIMARY KEY (`sessionID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `aiTeacherTutoringGoals` (
  `goalID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `sessionID` int(10) unsigned NOT NULL,
  `goal` text NOT NULL,
  `achieved` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`goalID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

### File Structure
```
modules/aiTeacher/
├── become_peer_tutor.php          (NEW - Tutor registration)
├── find_tutor.php                 (NEW - Request tutoring)
├── my_tutoring_sessions.php       (NEW - View upcoming sessions)
├── tutor_dashboard.php            (NEW - Tutor view of their students)
├── tutoring_analytics.php         (NEW - Admin view of program effectiveness)
└── src/
    └── PeerMatchingEngine.php     (NEW - AI matching algorithm)
```

### Implementation Tasks

#### Week 1: Tutor Registration & Profiles
- [ ] Create `become_peer_tutor.php` application form
- [ ] Add eligibility criteria:
  - Grade ≥85% in subject
  - Teacher recommendation
  - Good attendance record
- [ ] Build availability calendar selector
- [ ] Add teaching style questionnaire
- [ ] Implement teacher approval workflow
- [ ] Create tutor profile pages

#### Week 2: Matching Algorithm
- [ ] Build `PeerMatchingEngine.php`
- [ ] Implement matching criteria:
  - **Academic**: Tutor strong in student's weak areas
  - **Schedule**: Overlapping availability
  - **Learning Style**: Compatible teaching/learning styles
  - **Personality**: Similar interests (from Gibbon profiles)
  - **Proximity**: Same year group or house
- [ ] Calculate compatibility score (weighted algorithm)
- [ ] Test matching with sample data

#### Week 3: Session Management
- [ ] Create `find_tutor.php` request interface
- [ ] Show top 3 AI-recommended tutors with match reasoning
- [ ] Add session scheduling calendar
- [ ] Implement notification system:
  - Email tutor when matched
  - Reminder 1 day before session
  - Follow-up after session for feedback
- [ ] Build session check-in system (confirm attendance)

#### Week 4: Effectiveness Tracking
- [ ] Add post-session feedback form
- [ ] Track student grade improvement after tutoring
- [ ] Build `tutoring_analytics.php` dashboard:
  - Total sessions conducted
  - Avg effectiveness rating
  - Grade improvement stats
  - Top tutors leaderboard
- [ ] Award badges to effective tutors
- [ ] Generate AI insights: "Students tutored by X improve 18% on avg"
- [ ] Deploy and promote program

### Success Criteria
- [ ] 15%+ of high-achieving students sign up as tutors
- [ ] 80% matching success rate (tutor-student compatibility)
- [ ] Students receiving tutoring improve by 12%+ on average
- [ ] 4/5 average session rating
- [ ] 70% of sessions marked "helpful" or "very helpful"

---

# Phase 4: Optimization & Scaling (Ongoing)

**Objective**: Ensure long-term sustainability, cost efficiency, and continuous improvement of aiTeacher.

## 4.1 Performance Optimization

### Tasks
- [ ] **Database Optimization**
  - Add indexes on frequently queried columns
  - Implement query caching for dashboard widgets
  - Archive old data (>2 years) to separate tables
  - Optimize slow queries (target: <500ms)

- [ ] **API Cost Reduction**
  - Cache frequently asked questions and answers
  - Implement request deduplication
  - Use cheaper AI models for simple queries
  - Monitor daily API spending (set budget alerts)

- [ ] **Frontend Performance**
  - Lazy load images and charts
  - Minify JavaScript and CSS
  - Implement CDN for static assets
  - Add service worker for offline caching

- [ ] **Server Load Management**
  - Implement rate limiting (max 100 requests/user/hour)
  - Add queue system for heavy AI tasks
  - Scale horizontally if needed (load balancer)

### Success Metrics
- [ ] Page load time: <3 seconds (desktop), <5 seconds (mobile)
- [ ] API response time: <5 seconds for 95% of requests
- [ ] Daily API cost: <$20 for 500 students
- [ ] Server CPU usage: <70% during peak hours

---

## 4.2 Security Hardening

### Tasks
- [ ] **Data Protection**
  - Encrypt sensitive data at rest (AI API keys, student data)
  - Implement HTTPS only (force SSL)
  - Add CSRF tokens to all forms
  - Sanitize all user inputs (prevent XSS, SQL injection)

- [ ] **Access Control**
  - Review all permissions (principle of least privilege)
  - Add two-factor authentication for admin accounts
  - Implement session timeout (30 min inactivity)
  - Log all sensitive actions (audit trail)

- [ ] **API Security**
  - Rotate API keys quarterly
  - Use separate keys for dev/staging/production
  - Implement IP whitelisting for admin functions
  - Add honeypot endpoints to detect attacks

- [ ] **Compliance**
  - GDPR compliance audit (right to deletion, data portability)
  - FERPA compliance for student data (USA schools)
  - Add privacy policy and terms of service
  - Implement parent consent for AI usage (students <13)

### Success Metrics
- [ ] Zero security breaches or data leaks
- [ ] 100% of admin accounts use 2FA
- [ ] Security audit score: A+ (SSL Labs)
- [ ] Privacy policy reviewed by legal counsel

---

## 4.3 Continuous Improvement

### Tasks
- [ ] **Feedback Loops**
  - Add "Rate this feature" on every page
  - Conduct quarterly user surveys (students, teachers, parents)
  - Create suggestion box for new features
  - Hold monthly focus groups

- [ ] **A/B Testing**
  - Test different AI prompts for quality
  - Compare UI variations (button placement, colors)
  - Measure impact of gamification on engagement
  - Optimize email open rates (subject lines, send times)

- [ ] **Model Retraining**
  - Retrain prediction models quarterly with new data
  - Update learning style classifier annually
  - Improve moderation rules based on false positives
  - Fine-tune AI responses based on teacher feedback

- [ ] **Feature Analytics**
  - Track feature adoption rates
  - Measure time-to-value (how long until students benefit)
  - Identify underutilized features (consider deprecation)
  - Monitor user retention (7-day, 30-day, 90-day)

### Success Metrics
- [ ] 80%+ user satisfaction score
- [ ] Feature adoption: 60%+ of users try new features within 1 month
- [ ] Retention rate: 70%+ monthly active users
- [ ] Continuous improvement: 5+ feature enhancements per quarter

---

## 4.4 Documentation & Training

### Tasks
- [ ] **User Documentation**
  - Create video tutorials for all features (5-10 min each)
  - Write step-by-step guides with screenshots
  - Build searchable knowledge base (FAQ)
  - Add in-app tooltips and walkthroughs

- [ ] **Teacher Training**
  - Conduct monthly professional development sessions
  - Create "AI in the Classroom" best practices guide
  - Develop teacher certification program (AI Teaching Badge)
  - Record webinars for asynchronous learning

- [ ] **Student Onboarding**
  - Create "Getting Started" wizard for new students
  - Add interactive tutorial on first login
  - Develop peer ambassador program (students train students)
  - Create quick reference cards (printable)

- [ ] **Developer Documentation**
  - Document all API endpoints
  - Write code comments (PHPDoc standards)
  - Create architecture diagrams
  - Maintain changelog (semantic versioning)

### Success Metrics
- [ ] 90%+ of users complete onboarding tutorial
- [ ] Support ticket reduction: 50% after documentation launch
- [ ] Teacher confidence: 80%+ feel prepared to use AI tools
- [ ] Developer onboarding: New dev productive within 1 week

---

# Risk Management

## Technical Risks

| Risk | Likelihood | Impact | Mitigation Strategy |
|------|------------|--------|---------------------|
| AI API service outage | Medium | High | Implement fallback providers (OpenAI → Anthropic) |
| High API costs exceed budget | High | Medium | Set hard spending limits, cache aggressively |
| Poor AI response quality | Medium | High | Human review system, continuous prompt tuning |
| Database performance issues | Low | High | Regular optimization, scaling plan ready |
| Security breach | Low | Critical | Security audits, penetration testing, insurance |

## Adoption Risks

| Risk | Likelihood | Impact | Mitigation Strategy |
|------|------------|--------|---------------------|
| Low teacher buy-in | Medium | High | Extensive training, show quick wins, teacher champions |
| Student privacy concerns | Medium | Medium | Transparent policies, parent consent, opt-out option |
| Lack of student engagement | Low | High | Gamification, peer pressure (positive), teacher encouragement |
| Resistance to AI in education | Medium | Medium | Education campaign, share success stories, pilot program |

## Operational Risks

| Risk | Likelihood | Impact | Mitigation Strategy |
|------|------------|--------|---------------------|
| Key developer leaves project | Medium | High | Documentation, knowledge transfer, backup developer |
| Insufficient server resources | Low | Medium | Cloud hosting with auto-scaling, monitor usage |
| Regulatory changes (AI in schools) | Low | High | Stay informed, legal counsel, flexible architecture |
| Integration issues with Gibbon updates | High | Medium | Test on staging, subscribe to Gibbon updates, maintain compatibility layer |

---

# Success Metrics & KPIs

## Student Outcomes
- **Academic Performance**: 20% reduction in students scoring below threshold
- **Engagement**: 70% weekly active users
- **Learning Efficiency**: 15% faster topic mastery (measured by quiz progression)
- **Confidence**: 80% of students report feeling more confident in subjects

## Teacher Productivity
- **Time Savings**: 50% reduction in lesson planning time
- **Intervention Efficiency**: 40% less time identifying struggling students
- **Resource Creation**: 70% faster assessment creation
- **Job Satisfaction**: 85% of teachers would recommend aiTeacher

## Parent Engagement
- **Report Views**: 75% of parents view weekly reports
- **Communication**: 60% increase in parent-teacher contact
- **Satisfaction**: 80% of parents feel well-informed about child's progress
- **Home Support**: 50% of parents use AI-suggested home strategies

## System Performance
- **Uptime**: 99.5%+ availability
- **Response Time**: 95% of requests <5 seconds
- **Cost Efficiency**: <$1 per student per month in API costs
- **Error Rate**: <1% of AI responses flagged as incorrect

## Adoption Metrics
- **Student Adoption**: 80% of students use at least 1 feature weekly
- **Teacher Adoption**: 90% of teachers use at least 3 features monthly
- **Feature Utilization**: 60%+ adoption of major features within 3 months
- **Retention**: 75% monthly active users remain active after 6 months

---

# Budget Estimate

## Development Costs (One-Time)

| Phase | Developer Hours | Cost (@$50/hr) |
|-------|----------------|----------------|
| Phase 1 (Months 1-2) | 320 hours | $16,000 |
| Phase 2 (Months 3-4) | 320 hours | $16,000 |
| Phase 3 (Months 5-6) | 320 hours | $16,000 |
| **Total Development** | **960 hours** | **$48,000** |

## Ongoing Costs (Monthly)

| Item | Cost |
|------|------|
| AI API usage (500 students) | $300 - $500 |
| Cloud hosting (scaled) | $100 - $200 |
| Storage (images, audio, video) | $50 - $100 |
| Email delivery (parent reports) | $20 - $50 |
| Security & backups | $50 - $100 |
| **Total Monthly** | **$520 - $950** |

## Annual Ongoing Costs
**$6,240 - $11,400 per year** (for 500 students)

**Cost per Student per Year**: $12.50 - $22.80

---

# Timeline Summary

```
Month 1-2: Phase 1 - Quick Wins
├─ Week 1-4: Student AI Tutor
├─ Week 1-4: Stream Integration
├─ Week 1-4: Question Bank
└─ Week 1-4: Mobile UI

Month 3-4: Phase 2 - Analytics & Communication
├─ Week 1-4: Parent Portal
├─ Week 1-4: Learning Profiles
├─ Week 1-4: Predictive Dashboard
└─ Week 1-4: Content Moderation

Month 5-6: Phase 3 - Advanced Features
├─ Week 1-4: Gamification
├─ Week 1-4: Study Groups
├─ Week 1-4: Multi-Modal AI
└─ Week 1-4: Peer Tutoring

Month 7+: Phase 4 - Optimization
├─ Performance tuning
├─ Security hardening
├─ Documentation
└─ Continuous improvement
```

---

# Next Steps

1. **Review & Prioritize**: Review this roadmap with stakeholders and prioritize features based on school needs
2. **Allocate Resources**: Assign developers, budget approval, timeline confirmation
3. **Set Up Project Management**: Create tasks in project tracker (Jira, Trello, Asana)
4. **Kick-Off Meeting**: Align team on goals, success metrics, communication plan
5. **Phase 1 Sprint Planning**: Break down Week 1 tasks into daily actionable items
6. **Begin Development**: Start with Student AI Tutor feature

---

**Document Version**: 1.0
**Last Updated**: 2025-12-21
**Next Review Date**: 2026-01-21
**Document Owner**: Asley Smith

🤖 Generated with [Claude Code](https://claude.com/claude-code)
