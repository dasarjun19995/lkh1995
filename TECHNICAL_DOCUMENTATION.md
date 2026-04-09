# 🔧 Form System Architecture & Technical Documentation

## Table of Contents
1. [System Overview](#system-overview)
2. [Technology Stack](#technology-stack)
3. [Form Architecture](#form-architecture)
4. [Data Flow](#data-flow)
5. [Validation System](#validation-system)
6. [PDF Generation](#pdf-generation)
7. [Email Delivery](#email-delivery)
8. [Database Schema](#database-schema)
9. [API Endpoints](#api-endpoints)
10. [Security](#security)
11. [Troubleshooting & Logs](#troubleshooting--logs)

---

## System Overview

### What is the Form?

The Prominence Bank Account Application Form is a **hybrid web application** consisting of:

```
┌─────────────────────────────────────────────────┐
│         Prominence Bank Application Portal       │
│                                                 │
│  ┌──────────────────────────────────────────┐  │
│  │  Next.js Frontend (React)                │  │
│  │  - Interactive form UI                   │  │
│  │  - Real-time validation                  │  │
│  │  - Digital signature canvas              │  │
│  │  - File uploads                          │  │
│  │  - Progress tracking                     │  │
│  └──────────────────────────────────────────┘  │
│                    ↕ HTTP/REST API             │
│  ┌──────────────────────────────────────────┐  │
│  │  WordPress Backend (PHP)                 │  │
│  │  - Data storage                          │  │
│  │  - PDF generation                        │  │
│  │  - Email delivery                        │  │
│  │  - Admin dashboard                       │  │
│  │  - Payment verification                  │  │
│  └──────────────────────────────────────────┘  │
│                    ↕ Database API              │
│  ┌──────────────────────────────────────────┐  │
│  │  MySQL Database                          │  │
│  │  - Application records                   │  │
│  │  - User data                             │  │
│  │  - Payment info                          │  │
│  │  - Document references                   │  │
│  └──────────────────────────────────────────┘  │
│                                                 │
│  External Services:                            │
│  ├─ Google Genkit AI (summaries)              │
│  ├─ wkhtmltopdf (PDF rendering)               │
│  ├─ Email servers (SMTP/wp_mail)              │
│  └─ File storage (WordPress uploads)          │
└─────────────────────────────────────────────────┘
```

### Key Features

✅ **Two Application Types**: Personal and Business with different field sets  
✅ **9-Step Multi-Step Form**: Progressive disclosure of fields  
✅ **Real-Time Validation**: Client-side and server-side validation  
✅ **Digital Signatures**: Canvas-based signature capture  
✅ **File Uploads**: Support for documents and images  
✅ **PDF Generation**: Complete application compiled to PDF  
✅ **Email Notifications**: Applicant and admin confirmation emails  
✅ **Secure Storage**: Encrypted data at rest and in transit  
✅ **Admin Dashboard**: WordPress backend for application management  
✅ **AI Summaries**: Genkit AI generates executive summary of application  

---

## Technology Stack

### Frontend

```
Next.js 15 (React Framework)
├─ TypeScript (Type safety)
├─ Tailwind CSS (Styling)
├─ React Context API (State management)
├─ Form validation utilities
├─ Digital signature canvas
└─ File upload handling
```

**Location**: `/src/app/` and `/src/components/`

### Backend

```
WordPress PHP
├─ Custom plugin: wordpress-plugin-bridge.php
├─ Database: WordPress MySQL tables
├─ REST API: wp-json endpoints
├─ PDF generation: wkhtmltopdf binary
├─ Email: wp_mail() function
└─ File storage: WordPress uploads directory
```

**Location**: `wordpress-plugin-bridge.php` and WordPress installation

### External Services

| Service | Purpose | Location |
|---------|---------|----------|
| **Google Genkit AI** | Generate application summaries | API endpoint configured in .env |
| **wkhtmltopdf** | Convert HTML to PDF | Installed on WordPress server |
| **SMTP/Email Server** | Send confirmation emails | Configured in WordPress settings |
| **File Storage** | Store uploaded documents | /wp-content/uploads/ directory |

---

## Form Architecture

### Frontend Structure

```
src/
├─ app/
│  ├─ page.tsx                    # Landing page with account type selector
│  ├─ layout.tsx                   # Main layout wrapper
│  ├─ globals.css                  # Global styles
│  ├─ admin/
│  │  └─ page.tsx                 # Admin dashboard (WordPress)
│  ├─ details/
│  │  └─ page.tsx                 # Application details view
│  ├─ actions/
│  │  └─ application-actions.ts   # Server actions for form submission
│  └─ lib/
│     ├─ form-steps.ts            # Form field definitions
│     ├─ form-context.tsx         # React Context for form state
│     ├─ account-types.ts         # Account type definitions
│     └─ db.ts                    # Database utilities
├─ components/
│  ├─ step-renderer.tsx           # Dynamic field renderer
│  ├─ account-card.tsx            # Account type selection
│  ├─ submission-receipt.tsx       # Success receipt display
│  └─ ui/                         # shadcn/ui components
└─ hooks/
   ├─ use-mobile.tsx              # Responsive layout hook
   └─ use-toast.ts                # Toast notification hook
```

### Form Definition (form-steps.ts)

Form structure defined as TypeScript objects:

```typescript
type FormStep = {
  id: string                    // "p1", "p2", "b3", etc.
  title: string                 // "Account Type", "Identity", etc.
  subtitle?: string
  fields: FormField[]
}

type FormField = {
  id: string                    // "firstName", "phone", etc.
  type: 'text' | 'email' | 'date' | 'number' | 'textarea'
  label: string
  required: boolean
  placeholder?: string
  numericOnly?: boolean         // For phone/fax fields
  validation?: {
    notFutureDates?: boolean    // Passport issue dates
    notExpiredDates?: boolean   // Passport expiry dates
    matchField?: string         // Email confirmation matching
  }
}

// Personal Form: 9 steps (p1-p9)
const PERSONAL_STEPS: FormStep[] = [
  { id: 'p1', title: 'Account Type', fields: [...] },
  { id: 'p2', title: 'Identity', fields: [...] },
  // ... p3 through p9
]

// Business Form: 9 steps (b1-b9)
const BUSINESS_STEPS: FormStep[] = [
  { id: 'b1', title: 'Account Type', fields: [...] },
  { id: 'b2', title: 'Company Details', fields: [...] },
  // ... b3 through b9
]
```

### Form State Management (form-context.tsx)

```typescript
interface FormContextType {
  formType: 'personal' | 'business'           // User's selection
  currentStep: number                         // Current step (0-8)
  formData: Record<string, any>              // All entered data
  errors: Record<string, string>             // Validation errors
  isLoading: boolean                         // Submission in progress
  
  // Methods
  setFormType: (type: 'personal' | 'business') => void
  setCurrentStep: (step: number) => void
  updateField: (fieldId: string, value: any) => void
  validateStep: (stepNumber: number) => boolean
  submitForm: () => Promise<{ success: boolean; id: string }>
}

// Used in layout:
<FormProvider>
  <FormContent />
</FormProvider>
```

---

## Data Flow

### User Journey

```
1. LANDING PAGE
   └─ User selects "Personal" or "Business"
   
2. STEP RENDERER (9 TIMES)
   ├─ Fields loaded from form-steps.ts
   ├─ User enters data
   ├─ onChange validation runs
   ├─ Errors displayed if validation fails
   └─ User clicks "NEXT" when ready
   
3. FORM SUBMISSION (Step 9)
   └─ Click "SUBMIT NOW ✓"
   
4. DATA TRANSMISSION
   ├─ All form data collected
   ├─ Signature image processed
   ├─ Files uploaded
   └─ POST to /wp-json/faap/v1/submit
   
5. BACKEND PROCESSING
   ├─ Data decrypted
   ├─ Validations re-checked
   ├─ Database INSERT
   ├─ Application ID generated
   ├─ PDF generated
   ├─ Email notifications sent
   └─ Genkit AI summary created
   
6. RESPONSE & RECEIPT
   ├─ Success response sent to frontend
   └─ Receipt page displayed
   
7. EMAIL DELIVERY
   ├─ Applicant receives confirmation + PDF
   └─ Admin receives notification + PDF
```

### Form Data Structure

When submitted, form data looks like:

```typescript
{
  // Meta
  applicationId: "App-1234567890",
  applicationDate: "2026-04-15T14:30:00Z",
  formType: "personal",
  
  // Personal Form Fields
  firstName: "John",
  lastName: "Doe",
  dateOfBirth: "1990-05-20",
  passportNumber: "AB123456",
  passportIssueDate: "2020-01-01",
  passportExpiryDate: "2030-01-01",
  email: "john@example.com",
  emailConfirm: "john@example.com",
  phone: "+1-234-567-8900",
  address: "123 Main St",
  city: "New York",
  country: "USA",
  
  // ... all other fields for all 9 steps
  
  // Files & Signature
  paymentProof: {
    filename: "payment_proof_2026-04-15.jpg",
    path: "/wp-content/uploads/faap/payment_proof_abc123.jpg"
  },
  signature: {
    canvas: "data:image/png;base64,iVBORw0KGg...",
    savedPath: "/wp-content/uploads/faap/signature_def456.jpg"
  },
  companyRegistration: {  // Business only
    filename: "company_reg.jpg",
    path: "/wp-content/uploads/faap/company_reg_ghi789.jpg"
  }
}
```

---

## Validation System

### Client-Side Validation (Frontend)

Runs in real-time as user types using `getFieldError()` function:

```typescript
function getFieldError(field: FormField, value: any, allData: any): string | null {
  // Check if required
  if (field.required && !value) {
    return `${field.label} is required`;
  }
  
  // Email validation
  if (field.type === 'email' && value) {
    if (!isValidEmail(value)) {
      return `Please enter a valid email address`;
    }
  }
  
  // Email confirmation match
  if (field.validation?.matchField && value) {
    if (value !== allData[field.validation.matchField]) {
      return `Email addresses do not match`;
    }
  }
  
  // Date validation
  if (field.type === 'date' && value) {
    const dateError = getDateValidationError(field, value);
    if (dateError) return dateError;
  }
  
  // Numeric fields
  if (field.numericOnly && value) {
    if (!/^[0-9+\-().\s]*$/.test(value)) {
      return `Only numbers allowed`;
    }
  }
  
  return null;
}
```

### Date Validation Logic

```typescript
function getDateValidationError(field: FormField, value: string): string | null {
  if (!field.validation) return null;
  
  const selectedDate = new Date(value);
  const today = new Date();
  today.setHours(0, 0, 0, 0);  // Normalize to midnight
  
  // For passport issue date - cannot be in future
  if (field.validation.notFutureDates) {
    if (selectedDate > today) {
      return `${field.label} cannot be in the future`;
    }
  }
  
  // For passport expiry date - cannot be in past (expired)
  if (field.validation.notExpiredDates) {
    if (selectedDate < today) {
      return `${field.label} has expired`;
    }
  }
  
  return null;
}
```

### Email Validation Regex

```typescript
const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

// Checks for:
// ✓ At least one character before @
// ✓ One @ symbol
// ✓ At least one character between @ and .
// ✓ One . (dot) for domain extension
// ✓ At least one character after .
```

### Server-Side Validation (Backend)

After form submission, PHP re-validates all data:

```php
// WordPress plugin validation
function faap_validate_submission($data) {
    $errors = [];
    
    // Required fields
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            $errors[] = "$field is required";
        }
    }
    
    // Email validation
    if (!is_email($data['email'])) {
        $errors[] = "Invalid email format";
    }
    
    if ($data['email'] !== $data['emailConfirm']) {
        $errors[] = "Emails do not match";
    }
    
    // Phone validation
    if (!preg_match('/^[0-9+\-().\s]+$/', $data['phone'])) {
        $errors[] = "Invalid phone number format";
    }
    
    // Date validation
    $issue_date = strtotime($data['passportIssueDate']);
    $expiry_date = strtotime($data['passportExpiryDate']);
    $today = strtotime('today');
    
    if ($issue_date > $today) {
        $errors[] = "Passport issue date cannot be in future";
    }
    
    if ($expiry_date < $today) {
        $errors[] = "Passport has expired";
    }
    
    return $errors;
}
```

---

## PDF Generation

### Process Flow

```
1. TRIGGER: Form submitted
   └─ Calls faap_export_application_pdf()

2. HTML GENERATION
   ├─ Uses faap_build_application_pdf_html()
   ├─ Includes all form data
   ├─ Embeds signature image
   ├─ Adds payment details
   ├─ Includes full terms (17 sections)
   └─ Formats for print/PDF

3. IMAGE PROCESSING
   ├─ Signature: Convert base64 → file
   ├─ Payment Proof: Reference from uploads
   ├─ Company Docs: Reference from uploads
   └─ All files use file:// URLs instead of data URIs

4. CONTRACT TO BINARY
   ├─ wkhtmltopdf reads HTML
   ├─ Renders to PDF format
   ├─ Optimizes images
   ├─ Applies margins and page breaks
   └─ Outputs binary PDF file

5. STORAGE & DELIVERY
   ├─ Save to WordPress uploads
   ├─ Attach to email
   ├─ Return to frontend (success)
   └─ Store path in database
```

### PDF Content Sections

```
┌────────────────────────────────────────────────┐
│  Page 1: Header & Application Info            │
│  ├─ Bank Logo                                 │
│  ├─ Application ID                            │
│  ├─ Submission Date/Time                      │
│  └─ Account Type                              │
├────────────────────────────────────────────────┤
│  Page 2-3: Personal/Company Details           │
│  ├─ Full name/Company name                    │
│  ├─ Address information                       │
│  ├─ Contact details                           │
│  ├─ Identification (Passport/Registration)    │
│  └─ Business structure (if applicable)        │
├────────────────────────────────────────────────┤
│  Page 4-5: Payment & KYC Information          │
│  ├─ Account opening fee amount                │
│  ├─ Payment method required                   │
│  ├─ Document checklist                        │
│  └─ Source of funds verification              │
├────────────────────────────────────────────────┤
│  Page 6: Signature & Attestation              │
│  ├─ Digital Signature Image                   │
│  ├─ Signature date                            │
│  ├─ "Agreed and Attested" statement           │
│  ├─ Full 17-section Terms & Conditions        │
│  │  A. Mandatory submission requirements      │
│  │  B. Payment instructions                   │
│  │  C. Account opening requirements           │
│  │  D. Account type finality                  │
│  │  E. Transaction profiles                   │
│  │  F. Accuracy and authorization             │
│  │  G. Account retention (ETMO)               │
│  │  H. Compliance framework                   │
│  │  I. Data processing & privacy              │
│  │  J-Q. Additional banking provisions        │
│  └─ (≈15,000 characters total)                │
└────────────────────────────────────────────────┘
```

### Signature Image Handling

```php
// Backend signature processing
function faap_get_data_image_src($data_uri) {
    // Input: data:image/png;base64,iVBORw0KGgoAAAANS...
    
    // Step 1: Extract base64 from data URI
    $pattern = '/^data:image\/(\w+);base64,(.*)$/';
    preg_match($pattern, $data_uri, $matches);
    
    $image_type = $matches[1];      // png or jpg
    $image_data = $matches[2];      // base64 string
    
    // Step 2: Decode base64
    $decoded = base64_decode($image_data, true);
    if ($decoded === false) {
        return $data_uri;  // Fallback to original
    }
    
    // Step 3: Save to file
    $uploads_dir = wp_upload_dir();
    $unique_name = uniqid('signature_') . '.' . $image_type;
    $file_path = $uploads_dir['path'] . '/' . $unique_name;
    
    if (file_put_contents($file_path, $decoded)) {
        // Step 4: Return file URL (not data URI)
        return $uploads_dir['url'] . '/' . $unique_name;
    }
    
    return $data_uri;  // Fallback if save fails
}
```

**Why This Matters**:
- `wkhtmltopdf` has issues rendering base64 data URIs
- File-based URLs are rendered reliably in PDFs
- Improves signature visibility from 0% to 100%

---

## Email Delivery

### Email Types

#### 1. Applicant Confirmation Email

```
To: applicant@example.com
Subject: Application Received - ID: App-1234567890

Body:
- Thank you message
- Unique Application ID
- What to expect (48-hour review)
- Status update timeline
- Contact information
- PDF attachment with complete application
```

#### 2. Admin Notification Email

```
To: admin@prominencebank.com
Subject: New Application: [Applicant Name] - ID: App-1234567890

Body:
- New application submitted
- Applicant information
- Account type selected
- Submission timestamp
- PDF attachment
- Document names
- Link to admin dashboard (WordPress)
```

### Email Function

```php
function faap_send_application_emails($application_data, $pdf_file) {
    // Prepare attachments
    $attachments = array($pdf_file);
    
    if (!empty($application_data['payment_proof'])) {
        $attachments[] = $application_data['payment_proof']['path'];
    }
    
    // Email 1: Applicant
    $applicant_email = $application_data['email'];
    $applicant_subject = "Application Received - ID: " . 
                        $application_data['applicationId'];
    $applicant_message = faap_get_applicant_email_html($application_data);
    
    wp_mail(
        $applicant_email,
        $applicant_subject,
        $applicant_message,
        array('Content-Type: text/html; charset=UTF-8'),
        $attachments
    );
    
    // Email 2: Admin
    $admin_email = get_option('admin_email');
    $admin_subject = "New Application: " . 
                    $application_data['firstName'] . " " .
                    $application_data['lastName'];
    $admin_message = faap_get_admin_email_html($application_data);
    
    wp_mail(
        $admin_email,
        $admin_subject,
        $admin_message,
        array('Content-Type: text/html; charset=UTF-8'),
        $attachments
    );
}
```

---

## Database Schema

### Main Application Table

```sql
CREATE TABLE wp_faap_applications (
    -- Meta
    id INT PRIMARY KEY AUTO_INCREMENT,
    application_id VARCHAR(50) UNIQUE,  -- "App-1234567890"
    form_type ENUM('personal', 'business'),
    submission_date DATETIME,
    status ENUM('pending', 'approved', 'declined'),
    
    -- Applicant/Company Info
    first_name VARCHAR(255),
    last_name VARCHAR(255),
    company_name VARCHAR(255),
    email VARCHAR(255),
    phone VARCHAR(50),
    
    -- Address
    address VARCHAR(500),
    city VARCHAR(100),
    state VARCHAR(100),
    country VARCHAR(100),
    zip_code VARCHAR(20),
    
    -- Identity
    passport_number VARCHAR(50),
    passport_issue_date DATE,
    passport_expiry_date DATE,
    nationality VARCHAR(100),
    
    -- Financial
    initial_funding VARCHAR(100),
    funding_currency VARCHAR(10),
    account_type VARCHAR(100),
    
    -- Documents
    payment_proof_path VARCHAR(500),
    signature_path VARCHAR(500),
    company_registration_path VARCHAR(500),
    
    -- Processing
    compliance_checked TINYINT(1),
    payment_verified TINYINT(1),
    pdf_path VARCHAR(500),
    genkit_summary TEXT,
    admin_notes LONGTEXT,
    
    -- Admin
    reviewed_by VARCHAR(255),
    review_date DATETIME,
    reviewed_by_user_id INT,
    
    -- Timestamps
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX (application_id),
    INDEX (email),
    INDEX (form_type),
    INDEX (status),
    INDEX (submission_date)
);
```

### Application Data (JSON Storage)

```sql
-- Additional table for complete form data (JSON)
CREATE TABLE wp_faap_application_data (
    id INT PRIMARY KEY AUTO_INCREMENT,
    application_id VARCHAR(50) UNIQUE,
    form_data LONGTEXT,  -- JSON containing all field values
    FOREIGN KEY (application_id) 
        REFERENCES wp_faap_applications(application_id)
);

-- Example JSON structure:
{
  "p1_accountType": "Savings Account",
  "p2_firstName": "John",
  "p2_lastName": "Doe",
  "p2_dateOfBirth": "1990-05-20",
  "p3_email": "john@example.com",
  "p4_expectedTransfers": "Yes",
  "... all 9 steps fields ...",
  "p9_signatureDate": "2026-04-15",
  "p9_agreed": true
}
```

---

## API Endpoints

### Submit Application

**Endpoint**: `POST /wp-json/faap/v1/submit`

**Request Body**:
```json
{
  "formType": "personal",
  "formData": {
    "firstName": "John",
    "lastName": "Doe",
    "... all form fields ...",
    "signature": "data:image/png;base64,iVBORw0KGg..."
  },
  "files": {
    "paymentProof": {
      "blob": "File binary data",
      "filename": "payment_2026-04-15.jpg"
    }
  }
}
```

**Response Success (200)**:
```json
{
  "success": true,
  "message": "Application submitted successfully",
  "applicationId": "App-1234567890",
  "pdfUrl": "https://prominencebank.com/pdfs/App-1234567890.pdf"
}
```

**Response Error (400)**:
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "email": "Email addresses do not match",
    "phone": "Invalid phone number"
  }
}
```

### Get Application Details

**Endpoint**: `GET /wp-json/faap/v1/application/{applicationId}`

**Response (200)**:
```json
{
  "success": true,
  "application": {
    "applicationId": "App-1234567890",
    "formType": "personal",
    "submissionDate": "2026-04-15T14:30:00Z",
    "status": "pending",
    "firstName": "John",
    "lastName": "Doe",
    "email": "john@example.com",
    "... all form fields ...",
    "pdfPath": "/uploads/faap/App-1234567890.pdf"
  }
}
```

### List Applications (Admin)

**Endpoint**: `GET /wp-json/faap/v1/applications?status=pending`

**Query Parameters**:
- `status`: pending | approved | declined
- `formType`: personal | business
- `page`: 1, 2, 3...
- `limit`: 10, 20, 50...

**Response (200)**:
```json
{
  "success": true,
  "applications": [
    {
      "applicationId": "App-1234567890",
      "applicantName": "John Doe",
      "submissionDate": "2026-04-15T14:30:00Z",
      "status": "pending",
      "formType": "personal"
    }
  ],
  "total": 150,
  "page": 1,
  "pages": 8
}
```

### Update Application Status (Admin)

**Endpoint**: `PATCH /wp-json/faap/v1/application/{applicationId}`

**Request Body**:
```json
{
  "status": "approved",
  "adminNotes": "KYC verification passed",
  "paymentVerified": true
}
```

**Response (200)**:
```json
{
  "success": true,
  "message": "Application updated successfully"
}
```

---

## Security

### Data Encryption

**In Transit**:
- All connections use HTTPS/TLS 1.3
- Data encrypted during transmission
- Certificate from Let's Encrypt or commercial CA

**At Rest**:
- Application data stored in encrypted database table
- Sensitive fields (passport, email) optionally encrypted with AES-256
- File uploads stored outside web root

### Authentication & Authorization

```php
// WordPress nonce verification on all endpoints
function faap_verify_request() {
    if (!wp_verify_nonce($_REQUEST['_nonce'], 'faap_form_action')) {
        wp_send_json_error('Security check failed', 403);
    }
}

// Admin-only endpoints protected
function faap_require_admin() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions', 403);
    }
}
```

### Input Sanitization

```php
// Sanitize all user inputs
$form_data = array(
    'firstName' => sanitize_text_field($_POST['firstName']),
    'email' => sanitize_email($_POST['email']),
    'address' => sanitize_textarea_field($_POST['address']),
    'phone' => sanitize_text_field($_POST['phone'])
);
```

### XSS Protection

- All form data escaped when displayed
- React auto-escapes by default
- Additional `wp_kses()` for admin display

### CSRF Protection

- WordPress nonces on all forms
- SameSite cookie flag enabled
- CORS headers properly configured

### File Upload Security

```php
// Validate uploaded files
function faap_validate_file_upload($file) {
    // Check file type
    $allowed_types = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');
    if (!in_array($file['type'], $allowed_types)) {
        return new WP_Error('invalid_file_type', 'Invalid file type');
    }
    
    // Check file size (max 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        return new WP_Error('file_too_large', 'File exceeds maximum size');
    }
    
    // Verify it's actually an image
    $image_info = getimagesize($file['tmp_name']);
    if ($image_info === false) {
        return new WP_Error('invalid_image', 'File is not a valid image');
    }
    
    return true;
}
```

---

## Troubleshooting & Logs

### Common Issues

#### 1. Signature Not Showing in PDF

**Problem**: Blank white box where signature should appear

**Diagnosis**:
- Check if signature_path file exists in uploads directory
- Verify base64 decoding didn't fail
- Check wkhtmltopdf logs for rendering errors

**Solution**:
```php
// Debugging: Check signature conversion
$signature_path = faap_get_data_image_src($data_uri);
error_log("Signature saved to: " . $signature_path);
error_log("File exists: " . (file_exists($signature_path) ? 'YES' : 'NO'));
```

#### 2. Email Not Sending

**Problem**: Application submitted but no confirmation email received

**Diagnosis**:
- Check WordPress email configuration
- Verify SMTP server is configured
- Check admin email setting

**Solution**:
```php
// Test email function
$result = wp_mail(
    'test@example.com',
    'Test Email',
    'This is a test',
    array('Content-Type: text/html; charset=UTF-8')
);

if (!$result) {
    error_log("Email failed to send");
} else {
    error_log("Email sent successfully");
}
```

#### 3. PDF Generation Fails

**Problem**: Error during wkhtmltopdf PDF creation

**Diagnosis**:
- Check if wkhtmltopdf binary is installed
- Verify file permissions on uploads directory
- Check server disk space

**Solution**:
```bash
# Check if wkhtmltopdf is installed
which wkhtmltopdf

# Test wkhtmltopdf
wkhtmltopdf /path/to/input.html /path/to/output.pdf

# Check permissions
ls -la /wp-content/uploads/faap/
chmod 755 /wp-content/uploads/faap/
```

#### 4. Validation Not Working

**Problem**: Invalid data accepted by form

**Diagnosis**:
- Check if step-renderer.tsx validation functions are loaded
- Verify form-steps.ts has validation metadata
- Check browser console for JavaScript errors

**Solution**:
```typescript
// Debug validation
console.log('Field validation rules:', field.validation);
console.log('Validation result:', getFieldError(field, value, formData));
```

### Log Files

**Frontend Logs**:
- Browser Console (F12)
- Error messages appear in red toast notifications
- File: /workspaces/lkh1995/next.log (if enabled)

**Backend Logs**:
- WordPress: `/wp-content/debug.log` (if WP_DEBUG enabled)
- PHP: `/var/log/php-errors.log` (server-dependent)
- Application-specific: Custom logs in `faap_process_application()`

### Debug Mode

**Enable Debugging**:
```php
// Add to wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);

// Add to wordpress-plugin-bridge.php
define('FAAP_DEBUG', true);
```

**Log Application Processing**:
```php
function faap_log($message) {
    if (defined('FAAP_DEBUG') && FAAP_DEBUG) {
        error_log('[FAAP] ' . $message);
    }
}
```

---

## Performance Optimization

### Frontend Optimization

- Code splitting for form steps
- Lazy loading of unused components
- Image optimization for uploads
- CSS minification with Tailwind
- React Context batching updates

### Backend Optimization

- Database indexes on frequently queried fields
- PDF generation cached for 24 hours
- Email sending queued asynchronously
- Genkit AI summaries cached

### Infrastructure

- Gzip compression enabled
- Browser caching configured
- CDN for static assets (if deployed)
- Database connection pooling

---

## Deployment Notes

### Environment Variables (.env)

```
# Frontend
NEXT_PUBLIC_API_URL=https://prominencebank.com/wp-json/faap/v1
NEXT_PUBLIC_SITE_NAME=Prominence Bank

# Backend
WORDPRESS_HOME=https://prominencebank.com
WORDPRESS_SITEURL=https://prominencebank.com

# External Services
GENKIT_API_KEY=sk-...
GENKIT_API_URL=https://api.genkit.google.com

# Security
JWT_SECRET=your-jwt-secret-key
ENCRYPTION_KEY=your-encryption-key
```

### Installation Steps

1. **Frontend**:
   ```bash
   npm install
   npm run build
   npm start
   ```

2. **Backend (WordPress)**:
   - Upload wordpress-plugin-bridge.php to /wp-content/plugins/
   - Activate plugin in WordPress dashboard
   - Configure API keys and settings
   - Create database tables

3. **Verification**:
   - Test form submission
   - Verify PDF generation
   - Check email delivery
   - Validate admin dashboard

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 5.5 | Apr 2026 | Signature rendering fix, complete attestation terms |
| 5.4 | Mar 2026 | Email validation, numeric field filtering |
| 5.3 | Feb 2026 | Date validation improvements |
| 5.2 | Jan 2026 | Multi-step form structure |
| 5.1 | Dec 2025 | Initial release |

---

## Support & Escalation

**Level 1: Self-Help**
- Check this documentation
- Verify environment variables
- Check browser console for errors

**Level 2: Developer Support**
- Email: dev-support@prominencebank.com
- Include Application ID and error logs
- Screenshots of issue

**Level 3: Escalation**
- Senior developer review
- Database investigation
- System administrator involvement

---

*Last Updated: April 2026*  
*Next Review: July 2026*
