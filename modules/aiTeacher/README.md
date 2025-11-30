# AI Teacher Assistance Module for Gibbon

A powerful AI-powered teaching assistant module for Gibbon School Management System, designed to help CSEC teachers with curriculum planning, assessment analysis, and resource generation.

## Features

### 1. Curriculum Support
- Generate detailed lesson plans and schemes of work for all CSEC subjects
- Create topic breakdowns with learning outcomes and assessment criteria
- Access activity suggestions and resource lists aligned with CXC standards

### 2. Assessment & Evaluation
- Analyze student performance data from Gibbon's Internal Assessment Module
- Identify students scoring below the configured threshold (default: 60%)
- Generate personalized intervention strategies based on performance patterns
- Suggest modifications to teaching methods and remediation plans

### 3. Teacher Productivity Tools
- Generate structured questions, multiple choice, and extended responses
- Create rubrics for School-Based Assessments (SBAs)
- Generate summary reports and performance heatmaps
- Support bulk generation of quizzes and worksheet materials

### 4. Knowledge Ingestion
- Upload lesson notes, teaching guides, past papers, and mark schemes
- AI learns from uploaded content to improve future outputs
- Context-aware responses based on school-specific materials

## Installation

1. Download the module files and place them in your Gibbon installation's `modules` directory
2. Create a new directory named `aiTeacher` in the `modules` folder
3. Copy all module files into the `aiTeacher` directory
4. Log in to Gibbon as an administrator
5. Go to Admin > Modules and install the "AI Teacher Assistance" module
6. Configure the module settings:
   - DeepSeek API Key
   - File upload storage path
   - Student performance threshold

## Requirements

- Gibbon v23.0.0 or higher
- PHP 7.4 or higher
- MySQL 5.7 or higher
- DeepSeek API access

## Configuration

### API Integration
1. Obtain a DeepSeek API key from [DeepSeek's website](https://deepseek.com)
2. Enter the API key in the module settings
3. Test the connection using the "Test Connection" button

### File Uploads
1. Set the upload path in the module settings
2. Ensure the directory is writable by the web server
3. Configure maximum file size limits in your PHP settings

### Performance Threshold
1. Set the default threshold for student performance alerts
2. This can be adjusted per subject if needed

## Usage

### Generating Lesson Plans
1. Navigate to AI Teacher Assistance > Curriculum Support
2. Select the subject and grade level
3. Enter the topic
4. Click "Generate Lesson Plan"
5. Review and modify the generated plan as needed

### Analyzing Student Performance
1. Go to AI Teacher Assistance > Assessment Analysis
2. Select a student and subject
3. View the performance analysis and intervention strategies
4. Export or print the report

### Creating Assessments
1. Access AI Teacher Assistance > Resource Generator
2. Choose the assessment type
3. Enter subject and topic details
4. Generate and customize the assessment

### Uploading Resources
1. Go to AI Teacher Assistance > Upload Resource
2. Select the subject
3. Choose a file to upload
4. Add a description
5. Submit the resource

## Security

- All API keys are stored securely in the database
- File uploads are validated and sanitized
- Access is restricted to authorized users only
- All actions are logged for audit purposes

## Support

For support and feature requests, please:
1. Check the [Gibbon Forums](https://gibbonedu.org/forums)
2. Submit issues on the [GitHub repository](https://github.com/your-repo/aiTeacher)
3. Contact the module maintainer

## License

This module is released under the GNU General Public License v3.0. See the LICENSE file for details.

## Credits

- Developed by [Your Name/Organization]
- Powered by DeepSeek AI
- Built for Gibbon School Management System 