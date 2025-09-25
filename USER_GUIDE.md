# User Guide - Student Enrollment Form Platform

## Table of Contents
1. [Getting Started](#getting-started)
2. [Creating Forms](#creating-forms)
3. [Managing Form Fields](#managing-form-fields)
4. [File Uploads and Image Cropping](#file-uploads-and-image-cropping)
5. [Viewing Submissions](#viewing-submissions)
6. [Exporting Data](#exporting-data)
7. [Security Features](#security-features)
8. [Troubleshooting](#troubleshooting)

## Getting Started

### Accessing the Admin Panel
1. Navigate to `http://yourdomain.com/admin/`
2. Enter your admin credentials
3. You'll be redirected to the main dashboard

### Dashboard Overview
The admin dashboard provides:
- Quick access to form management
- Recent submission statistics
- System health indicators
- Navigation to all platform features

## Creating Forms

### Step 1: Access Form Builder
1. Click "Forms" in the main navigation
2. Click "Create New Form" button
3. The form builder interface will open

### Step 2: Basic Form Settings
1. **Form Title**: Enter a descriptive name for your form
2. **Description**: Add optional description text
3. **Status**: Set form as Active or Inactive
4. **Submission Limit**: Set maximum number of submissions (optional)

### Step 3: Adding Form Fields
The form builder provides a drag-and-drop interface:

#### Available Field Types
- **Text Input**: Single-line text fields
- **Textarea**: Multi-line text input
- **Email**: Email address with validation
- **Phone**: Phone number with formatting
- **Number**: Numeric input with validation
- **Date**: Date picker
- **Dropdown**: Select from predefined options
- **Radio Buttons**: Single selection from options
- **Checkboxes**: Multiple selection options
- **File Upload**: File attachment with validation
- **Image Upload**: Image files with cropping capability
- **Aadhar Number**: Indian Aadhar number with validation

#### Field Configuration
For each field, you can configure:
- **Label**: Display name for the field
- **Required**: Whether the field is mandatory
- **Placeholder**: Helper text shown in empty fields
- **Validation Rules**: Custom validation requirements
- **Help Text**: Additional guidance for users

### Step 4: Form Layout
- Drag fields to reorder them
- Use the preview panel to see how the form will appear
- Adjust field widths and spacing as needed

### Step 5: Save and Publish
1. Click "Save Form" to save your changes
2. Set form status to "Active" to make it available
3. Copy the form URL to share with users

## Managing Form Fields

### Text Fields
- Set minimum/maximum character limits
- Add pattern validation (regex)
- Configure placeholder text

### File Upload Fields
- Set allowed file types (PDF, DOC, JPG, PNG, etc.)
- Configure maximum file size
- Enable virus scanning (if available)

### Image Upload Fields
- Enable image cropping functionality
- Set required dimensions
- Configure compression settings
- Allow multiple image formats

### Aadhar Number Fields
- Automatic format validation
- Checksum verification
- Secure handling of sensitive data

### Dropdown and Selection Fields
- Add/remove options dynamically
- Set default selections
- Enable "Other" option with text input

## File Uploads and Image Cropping

### File Upload Process
1. Users click the upload area or drag files
2. Files are validated against configured rules
3. Upload progress is displayed
4. Successful uploads show file preview

### Image Cropping Feature
1. After image upload, cropping interface appears
2. Users can:
   - Resize the crop area
   - Move the crop selection
   - Rotate the image
   - Apply zoom
3. Preview shows the final cropped result
4. Users confirm or re-crop as needed

### Supported File Types
- **Images**: JPG, PNG, GIF, WebP
- **Documents**: PDF, DOC, DOCX, TXT
- **Spreadsheets**: XLS, XLSX, CSV
- **Archives**: ZIP (with content scanning)

### File Security
- All uploads are scanned for malicious content
- Files are stored outside the web root
- Access is controlled through the application
- Automatic cleanup of temporary files

## Viewing Submissions

### Submissions Dashboard
1. Navigate to "Submissions" from the main menu
2. View list of all form submissions
3. Filter by form, date range, or status
4. Search submissions by content

### Submission Details
Click on any submission to view:
- All submitted form data
- Uploaded files and images
- Submission timestamp and IP address
- Processing status and notes

### Bulk Operations
- Select multiple submissions for bulk actions
- Export selected submissions
- Delete submissions (with confirmation)
- Mark submissions as processed

## Exporting Data

### Export Options
1. **CSV Export**: Spreadsheet-compatible format
2. **Excel Export**: Native Excel format with formatting
3. **PDF Export**: Formatted reports for printing
4. **JSON Export**: Raw data for system integration

### Export Process
1. Go to Submissions page
2. Select desired submissions (or use "Select All")
3. Click "Export" button
4. Choose export format
5. Configure export options:
   - Include file attachments
   - Date range filtering
   - Field selection
6. Download generated file

### Scheduled Exports
- Set up automatic daily/weekly exports
- Email exports to specified addresses
- Configure export retention policies

## Security Features

### User Authentication
- Secure admin login with session management
- Automatic session timeout for security
- Password strength requirements
- Account lockout after failed attempts

### Data Protection
- CSRF protection on all forms
- Input validation and sanitization
- SQL injection prevention
- XSS attack protection

### File Security
- Upload restrictions by file type and size
- Malware scanning for uploaded files
- Secure file storage outside web root
- Access logging for all file operations

### Privacy Compliance
- Secure handling of sensitive data (Aadhar numbers)
- Data encryption for stored files
- Audit trails for data access
- GDPR-compliant data handling

## Troubleshooting

### Common Issues

#### Form Not Loading
**Problem**: Form page shows error or doesn't load
**Solutions**:
1. Check if form is set to "Active" status
2. Verify form URL is correct
3. Check browser JavaScript console for errors
4. Clear browser cache and cookies

#### File Upload Failures
**Problem**: Files won't upload or show errors
**Solutions**:
1. Check file size (must be under configured limit)
2. Verify file type is allowed
3. Ensure stable internet connection
4. Try uploading smaller files first

#### Image Cropping Not Working
**Problem**: Crop interface doesn't appear or function
**Solutions**:
1. Ensure browser supports HTML5 Canvas
2. Check if JavaScript is enabled
3. Try a different browser
4. Verify image file is valid format

#### Submission Data Missing
**Problem**: Form submissions appear incomplete
**Solutions**:
1. Check if all required fields were filled
2. Verify form validation rules
3. Look for JavaScript errors during submission
4. Check server error logs

#### Login Issues
**Problem**: Cannot access admin panel
**Solutions**:
1. Verify username and password
2. Check if account is locked
3. Clear browser cookies
4. Contact system administrator

### Performance Issues

#### Slow Form Loading
- Check internet connection speed
- Verify server resources
- Review form complexity (too many fields)
- Check for large background images

#### Upload Timeouts
- Reduce file sizes before uploading
- Check server upload limits
- Verify network stability
- Try uploading files individually

### Getting Help

#### Error Messages
- Note exact error message text
- Check browser console for additional details
- Review server error logs if accessible
- Document steps that led to the error

#### Contacting Support
When reporting issues, include:
- Browser type and version
- Operating system
- Exact error messages
- Steps to reproduce the problem
- Screenshots if applicable

#### System Requirements
**Minimum Browser Requirements**:
- Chrome 70+, Firefox 65+, Safari 12+, Edge 79+
- JavaScript enabled
- Cookies enabled
- Local storage support

**Recommended Setup**:
- Modern browser with latest updates
- Stable internet connection (1 Mbps minimum)
- Screen resolution 1024x768 or higher
- PDF viewer for export downloads

## Best Practices

### Form Design
- Keep forms concise and focused
- Use clear, descriptive field labels
- Provide helpful placeholder text
- Group related fields together
- Test forms before publishing

### Data Management
- Regularly export and backup submission data
- Review and clean up old submissions
- Monitor storage usage for uploaded files
- Maintain organized file naming conventions

### Security
- Use strong admin passwords
- Log out when finished using the system
- Regularly review access logs
- Keep the system updated
- Monitor for suspicious activity

### User Experience
- Test forms on different devices
- Ensure mobile compatibility
- Provide clear instructions
- Use appropriate field validation
- Offer help text for complex fields