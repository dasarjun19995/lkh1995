# 📊 Prominence Bank - Admin Dashboard & Application Management Guide

## Table of Contents
1. [Admin Access](#admin-access)
2. [Applications Dashboard](#applications-dashboard)
3. [Reviewing Applications](#reviewing-applications)
4. [Managing Application Status](#managing-application-status)
5. [Payment Verification](#payment-verification)
6. [Document Management](#document-management)
7. [Email & Communication](#email--communication)
8. [Reporting & Exports](#reporting--exports)
9. [System Settings](#system-settings)
10. [Common Tasks](#common-tasks)

---

## Admin Access

### Login to WordPress Admin

1. **URL**: `https://prominencebank.com/wp-admin`
2. **Enter Credentials**:
   - Username: Your WordPress admin username
   - Password: Your secure password
3. **Two-Factor Authentication** (if enabled):
   - Enter code from authenticator app
4. **Click Login**

### First-Time Setup

1. Change default password to secure one
2. Update admin profile:
   - Real name
   - Email address (for notifications)
   - Time zone
3. Enable two-factor authentication
4. Review plugin settings

### Navigation Menu (Left Sidebar)

```
WordPress Admin
├─ Dashboard (Overview)
├─ Posts
├─ Pages
├─ Media
├─ → FAAP Plugin (Custom)
│  ├─ Applications
│  ├─ Settings
│  └─ Reports
├─ Comments
├─ Users
├─ Tools
└─ Settings
```

---

## Applications Dashboard

### Accessing Applications

1. Click **FAAP Plugin** (or **Applications**) in left menu
2. View **Applications List**

### Dashboard Overview

```
┌─────────────────────────────────────────────┐
│  FAAP Applications Dashboard                │
│                                             │
│  📊 Summary Statistics:                     │
│  ├─ Total Applications: 342                 │
│  ├─ Pending Review: 45                      │
│  ├─ Approved: 287                           │
│  ├─ Declined: 10                            │
│  └─ Average Review Time: 18 hours           │
│                                             │
│  🔄 Recent Activity:                        │
│  ├─ New Applications (Today): 8             │
│  ├─ Approved (Today): 12                    │
│  └─ Declined (Today): 1                     │
│                                             │
│  ⚠️  Pending Tasks:                          │
│  ├─ Waiting Payment Verification: 23       │
│  ├─ Waiting Document Review: 15            │
│  └─ Awaiting Final Approval: 7             │
└─────────────────────────────────────────────┘
```

### Applications List Table

| Column | Information | Actions |
|--------|-------------|---------|
| **ID** | Application ID (App-XXXXXX) | Click to open full details |
| **Applicant Name** | First + Last Name | - |
| **Type** | Personal / Business | Filter by type |
| **Status** | Pending / Approved / Declined | Change status |
| **Submission Date** | When form submitted | Sort by date |
| **Payment** | ✓ Verified / ✗ Pending | Verify payment |
| **Documents** | Number of uploaded files | View attachments |
| **Actions** | View / Edit / Approve / Decline | Quick actions |

### Filtering & Searching

```
Filter Options:
└─ Status Filter
   ├─ All
   ├─ Pending Review
   ├─ Approved
   └─ Declined

└─ Date Range Filter
   ├─ Last 24 hours
   ├─ Last 7 days
   ├─ Last 30 days
   └─ Custom date range

└─ Account Type Filter
   ├─ All
   ├─ Personal Account
   └─ Business Account

└─ Payment Status Filter
   ├─ All
   ├─ Payment Verified
   └─ Payment Pending

Search:
└─ Search by:
   ├─ Application ID
   ├─ Applicant Name
   ├─ Email Address
   └─ Phone Number
```

---

## Reviewing Applications

### Opening an Application

1. Click on **Application ID** or **Applicant Name** in list
2. Full application details page loads
3. See all form data, documents, and signature

### Application Details Page

```
┌────────────────────────────────────────────────────┐
│ Application: App-1234567890                       │
│ Status: [PENDING REVIEW] [v Change Status]        │
├────────────────────────────────────────────────────┤
│                                                    │
│ 📋 APPLICANT INFORMATION                          │
│ ├─ Full Name: John Doe                            │
│ ├─ Date of Birth: May 20, 1990                    │
│ ├─ Email: john@example.com                        │
│ ├─ Phone: +1-234-567-8900                         │
│ ├─ Address: 123 Main St, New York, NY 10001      │
│ └─ Country: United States                         │
│                                                    │
│ 🛂 IDENTITY INFORMATION                           │
│ ├─ Passport No.: AB123456                         │
│ ├─ Issue Date: Jan 1, 2020                        │
│ ├─ Expiry Date: Jan 1, 2030                       │
│ ├─ Nationality: American                          │
│ └─ Place of Birth: Los Angeles, USA              │
│                                                    │
│ 💰 FINANCIAL INFORMATION                          │
│ ├─ Initial Funding: €100,000                      │
│ ├─ Account Type: Savings Account                  │
│ ├─ Currency: EUR                                  │
│ └─ Source: Employment income                      │
│                                                    │
│ 📧 CONTACT DETAILS                                │
│ ├─ Email: john@example.com (Verified ✓)         │
│ ├─ Phone: +1-234-567-8900                        │
│ ├─ Fax: +1-234-567-8901                          │
│ └─ Mobile: +1-234-567-8902                       │
│                                                    │
│ 🏦 FUNDING BANK DETAILS                           │
│ ├─ Bank Name: Chase Bank                          │
│ ├─ Account Number: ****1234                       │
│ ├─ Routing Number: 021000021                      │
│ └─ Account Holder: John Doe                       │
│                                                    │
│ 📎 DOCUMENTS & ATTACHMENTS                        │
│ ├─ Payment Proof: payment_2026-04-15.jpg         │
│ ├─ Signature: signature_abc123.png               │
│ ├─ Company Registration: N/A (Personal)          │
│ └─ [View All] [Download All]                     │
│                                                    │
│ ✍️  SIGNATURE                                     │
│ ├─ [Show Signature Image]                         │
│ ├─ Date Signed: April 15, 2026                   │
│ └─ Signature Verified: ✓                          │
│                                                    │
│ 📋 TERMS & CONDITIONS                             │
│ ├─ Agreed: ✓ Yes                                 │
│ ├─ All 17 sections acknowledged                  │
│ └─ [Expand Terms]                                │
│                                                    │
│ 💳 PAYMENT STATUS                                 │
│ ├─ Amount Due: €25,000                            │
│ ├─ Status: [⏳ PENDING] [✅ MARK AS VERIFIED]     │
│ ├─ Payment Method Expected: SWIFT Transfer       │
│ └─ Notes: [Enter verification notes]             │
│                                                    │
│ 📝 ADMIN NOTES                                    │
│ ├─ Previous Notes:                                │
│ │  └─ "KYC check in progress" - Apr 15, 14:30    │
│ ├─ Add New Note:                                  │
│ │  [Text area for internal notes]                 │
│ │  [Save Note]                                    │
│ └─ Created by: Admin User / Date: Apr 15, 2026   │
│                                                    │
│ ⚙️  QUICK ACTIONS                                 │
│ ├─ [👁️  View Full PDF]                            │
│ ├─ [📧 Send Email to Applicant]                   │
│ ├─ [✅ Approve Application]                       │
│ ├─ [❌ Decline Application]                       │
│ └─ [🔄 Reset Status]                              │
│                                                    │
└────────────────────────────────────────────────────┘
```

### Expanding Detailed Sections

Click "Expand" on any section to see full details:

**Payment & KYC Section**:
```
├─ Account Opening Fee: €25,000
├─ Payment Instructions:
│  ├─ Method: SWIFT International Wire Transfer
│  ├─ Beneficiary: Prominence Bank AG
│  ├─ SWIFT Code: PROMCH22
│  ├─ IBAN: CH93 0076 2011 6238 5295 7
│  └─ Reference: [Application ID]
├─ KYC Documents Required:
│  ├─ Passport/ID (Provided ✓)
│  ├─ Proof of Address (Provided ✓)
│  ├─ Financial Documentation (Provided ✓)
│  └─ Source of Funds Verification (Provided ✓)
└─ Risk Assessment: LOW
```

**Terms & Conditions Section**:
```
├─ Section A: Mandatory Submission Requirements ✓
├─ Section B: Payment Instructions ✓
├─ Section C: Account Opening Requirements ✓
├─ Section D: Account Type Finality ✓
├─ ... (All 17 sections displayed)
└─ [Download Full T&C PDF]
```

---

## Managing Application Status

### Status Workflow

```
         ┌──────────────────┐
         │  APPLICATION     │
         │  SUBMITTED       │
         └────────┬─────────┘
                  │
                  ▼
         ┌──────────────────┐
         │  PENDING REVIEW  │ ◄─── Default status after submission
         │ (KYC Screening)  │
         └────────┬─────────┘
                  │
        ┌─────────┴─────────┐
        │                   │
        ▼                   ▼
   ┌─────────────┐    ┌────────────┐
   │  APPROVED   │    │  DECLINED  │
   │(Account     │    │(Rejected)  │
   │ Created)    │    │            │
   └─────────────┘    └────────────┘
```

### Changing Status

1. **Open application** details page
2. Click **"[Change Status]"** button at top
3. Select new status from dropdown:
   - ⏳ Pending Review
   - ✅ Approved
   - ❌ Declined

4. **For Approval**:
   - Check all KYC documents verified
   - Confirm payment received
   - Review compliance notes
   - Click **"✅ Approve Application"**
   - Email confirmation auto-sent to applicant
   - Account creation workflow initiated

5. **For Decline**:
   - Click **"❌ Decline Application"**
   - Select reason for decline:
     - Incomplete documentation
     - Invalid identification
     - High risk profile
     - Payment not received
     - Failed AML screening
     - Other (specify)
   - Add declining remarks (required)
   - Email to applicant with reason
   - Refund process initiated

### Status Email Templates

**Approval Email** (auto-sent):
```
Subject: Your Application Approved - Account Setup Begins

Dear John Doe,

Great news! Your application (App-1234567890) has been APPROVED.

Your account opening fee of €25,000 has been verified and processed.

Next Steps:
1. Your account will be created within 2-5 business days
2. You will receive login credentials via secured email
3. Your account will be activated within 5-10 business days
4. You can begin transactions once activated

Questions? Contact: support@prominencebank.com

Best regards,
Prominence Bank Team
```

**Decline Email** (auto-sent):
```
Subject: Application Status Update - App-1234567890

Dear John Doe,

Thank you for your application (App-1234567890).

Unfortunately, we are unable to proceed at this time due to:
→ Incomplete documentation - Please provide additional proof of address

You may reapply with corrected documents. Our team will provide guidance
on required documentation corrections.

Contact: support@prominencebank.com for assistance

Best regards,
Prominence Bank Team
```

---

## Payment Verification

### Payment Status Tracker

1. **Open application** details
2. Scroll to **"💳 PAYMENT STATUS"** section
3. View payment information:
   - Amount required
   - Current status (Verified / Pending)
   - Expected payment method

### Verifying Payment

**Step 1: Check Payment Proof**
- Click **"View Payment Proof"** to see uploaded document
- Verify it shows:
  - Amount: €25,000 (or account opening fee)
  - Transaction date
  - Wire reference
  - Bank/method confirmation

**Step 2: Confirm Receipt**
- Check your bank account for deposited funds
- Cross-reference transaction with application details

**Step 3: Mark as Verified**
1. Click **"✅ MARK AS VERIFIED"** button
2. Add verification notes (optional):
   - "Wire received from Chase Bank"
   - "Reference: PROMCH22"
   - "Amount verified: €25,000"
3. Click **"Save Verification"**
4. Status updates to ✓ Payment Verified

### Payment Not Received

If payment not received:

1. **Send payment reminder email**:
   - Click **"📧 Send Email"**
   - Select "Payment Reminder" template
   - Include payment instructions
   - Set deadline (e.g., 5 days)

2. **Set internal deadline**:
   - Click **"⏰ Set Deadline"**
   - Select date (e.g., +5 days)
   - Email reminder will auto-send

3. **Take action after deadline**:
   - If still not paid: Decline application
   - Request refund processing
   - Close application

---

## Document Management

### Viewing Attached Documents

1. **Open application**
2. Scroll to **"📎 DOCUMENTS & ATTACHMENTS"**
3. See list of uploaded files:
   - Payment Proof
   - Signature Image
   - Company Registration (Business)
   - Other supporting documents

### Document Actions

**View Document**:
- Click document name or thumbnail
- Opens in new tab/window
- Zoom and pan available

**Download Document**:
- Right-click document
- Select "Download"
- Saved to computer

**Download All Documents**:
- Click **"[Download All]"** button
- Creates ZIP file with all attachments
- Automatically downloads

**View Signature**:
```
Signature Preview:
┌─────────────────────┐
│                     │
│    John Doe         │  ← Digital signature
│                     │
│  Date: Apr 15, 2026 │
└─────────────────────┘
```

### Document Verification

For each document, verify:

**Payment Proof**:
- ✓ Clear, readable image
- ✓ Shows amount (€25,000)
- ✓ Shows date
- ✓ Shows bank/method
- ✓ Shows applicant name (if available)

**Signature**:
- ✓ Legible signature
- ✓ Signed with applicant name
- ✓ Dated (matches submission date)
- ✓ Full signature visible

**ID/Passport**:
- ✓ Photo matches applicant
- ✓ Passport number matches form
- ✓ Not expired
- ✓ Full number and expiry visible

**Company Registration** (Business only):
- ✓ Official document
- ✓ Company name matches application
- ✓ Registration number visible
- ✓ Date of incorporation visible

---

## Email & Communication

### Sending Emails to Applicants

1. **Open application**
2. Click **"📧 Send Email to Applicant"**
3. Choose email template:

```
Email Templates:
├─ Status Update
│  └─ Notify applicant of current review status
├─ Request Additional Documents
│  └─ Ask for missing or unclear documents
├─ Payment Reminder
│  └─ Request payment if not received
├─ Need More Information
│  └─ Request clarification on form fields
├─ Approval Notification
│  └─ Inform of approval (usually auto)
├─ Decline Notification
│  └─ Explain decline reason (usually auto)
└─ Custom Email
   └─ Write your own message
```

### Compose Custom Email

1. Select **"Custom Email"** option
2. **Subject** field appears:
   - Auto-populated with Application ID
   - Edit as needed
3. **Message** field (rich text editor):
   - Type or paste message
   - Format: Bold, Italic, Links, Lists
   - Can include:
     - {{APPLICANT_NAME}}
     - {{APPLICATION_ID}}
     - {{ACCOUNT_TYPE}}
     - {{SUBMISSION_DATE}}
4. **Preview** option
5. Click **"Send Email"**

### Email Notifications (Auto-Sent)

System automatically sends emails on:
- ✓ Application submitted (Confirmation)
- ✓ Application approved (Status update)
- ✓ Application declined (Reason provided)
- ✓ Document upload required (Reminder)
- ✓ Payment deadline approaching (Reminder)

**Check email history** for each application:
- View all sent emails
- See delivery status
- Read-receipts if enabled
- Resend previous emails

---

## Reporting & Exports

### Dashboard Reports

**Access Reports**:
1. Click **FAAP Plugin** → **Reports**

**Available Reports**:

1. **Applications Summary**:
   - Total applications (all time)
   - Breakdown by status
   - Breakdown by type (Personal/Business)
   - Monthly trend graph
   - Average processing time

2. **Performance Metrics**:
   - Applications by day/week/month
   - Average review time
   - Approval rate %
   - Decline rate %
   - Payment verification rate %

3. **Geographic Distribution**:
   - Applications by country
   - Applications by state/province
   - Map visualization
   - Top countries

4. **Financial Summary**:
   - Total fees collected (€)
   - Average account opening fee
   - Revenue by account type
   - Revenue trend

5. **Compliance Dashboard**:
   - KYC completion rate
   - Document verification status
   - High-risk applications
   - Pending compliance reviews

### Exporting Data

**Export Current View**:
1. From applications list, click **"📥 Export"**
2. Choose format:
   - CSV (spreadsheet)
   - JSON (data format)
   - PDF (report)
3. Select columns:
   - Application ID
   - Applicant Name
   - Email
   - Type
   - Status
   - Submission Date
   - Payment Status
   - Country
4. Choose date range
5. Click **"Export Now"**
6. File downloads automatically

**Export Single Application**:
1. Click **"📥 Export"** on application
2. Formats:
   - **PDF**: Complete application with signature
   - **JSON**: Raw application data
   - **ZIP**: All documents + PDF

### Report Automation

**Schedule automatic reports**:
1. Settings → Report Scheduling
2. Choose frequency:
   - Daily
   - Weekly (choose day)
   - Monthly (choose date)
3. Select report type
4. Choose recipients (email addresses)
5. Enable/Disable
6. Save

**Generated reports** sent automatically to specified email addresses.

---

## System Settings

### Plugin Configuration

1. Click **FAAP Plugin** → **Settings**
2. Configure options:

**General Settings**:
```
└─ Application Settings
   ├─ Enable New Applications: [✓] Yes
   ├─ Require Email Confirmation: [✓] Yes
   ├─ Require Payment Before Approval: [✓] Yes
   ├─ Account Review Time: [48] hours
   └─ [Save Settings]

└─ Account Type Settings
   ├─ Personal Account Fee: €25,000
   ├─ Business Account Fee: €50,000
   ├─ Numbered Account Fee: €50,000
   ├─ Cryptocurrency Account Fee: €75,000
   └─ [Save Settings]

└─ Payment Configuration
   ├─ Payment Methods:
   │  ├─ [✓] SWIFT Transfer
   │  ├─ [✓] Cryptocurrency (USDT TRC20)
   │  └─ [ ] Bank Card (Disabled)
   ├─ Payment Currency: EUR / USD
   └─ [Save Settings]

└─ Email Configuration
   ├─ From Email: admin@prominencebank.com
   ├─ From Name: Prominence Bank
   ├─ SMTP Server: [smtp.server.com]
   ├─ SMTP Port: [587]
   ├─ SMTP Username: [username]
   ├─ SMTP Password: [••••••]
   ├─ Enable TLS: [✓] Yes
   └─ [Test Email]

└─ Notification Recipients
   ├─ Admin Email: admin@prominencebank.com
   ├─ Secondary Email: support@prominencebank.com
   ├─ Approval Notifier: admin@prominencebank.com
   └─ [Save Settings]
```

**Advanced Settings**:
```
└─ PDF Generation
   ├─ wkhtmltopdf Path: [/usr/bin/wkhtmltopdf]
   ├─ Temp Directory: [/tmp]
   ├─ Archive PDFs: [✓] Yes
   └─ [Save Settings]

└─ Database
   ├─ Archive Old Applications: [After] 1 [year]
   ├─ Backup Frequency: [Weekly]
   └─ [Save Settings]

└─ AI Integration (Genkit)
   ├─ Enable AI Summaries: [✓] Yes
   ├─ Genkit API Key: [sk-••••••••••]
   ├─ API Endpoint: [https://api.genkit.google.com]
   └─ [Save Settings]

└─ Security
   ├─ Require 2FA for Admin: [✓] Yes
   ├─ Failed Login Attempts: [5]
   ├─ Lockout Duration: [30] minutes
   ├─ IP Whitelist: [List IPs]
   └─ [Save Settings]
```

### Email Templates

Customize email templates sent to applicants:

1. Click **Settings** → **Email Templates**
2. Templates available:
   - Confirmation (after submission)
   - Status Update
   - Document Request
   - Payment Reminder
   - Approval Notice
   - Decline Notice

3. **Edit Template**:
   - Subject line
   - Body content
   - Available variables:
     - {{APPLICANT_NAME}}
     - {{APPLICATION_ID}}
     - {{ACCOUNT_TYPE}}
     - {{SUBMISSION_DATE}}
     - {{NEXT_STEPS}}

4. **Preview** before saving
5. **Reset to Default** if needed
6. Click **Save**

---

## Common Tasks

### Task 1: Reviewing a New Application

**Checklist**:
- [ ] Open application details
- [ ] Review all form data accuracy
- [ ] Check documents are uploaded
- [ ] Verify signature present and legible
- [ ] Check payment proof image quality
- [ ] Verify applicant agreed to terms
- [ ] Run KYC/AML screening (if available)
- [ ] Add internal notes with review findings
- [ ] Approve or request more information
- [ ] Send status email to applicant

**Time**: 10-15 minutes per application

### Task 2: Verifying Payment Received

**Steps**:
1. Click on application
2. Go to Payment Status section
3. Review payment proof document
4. Check your bank account for receipt
5. Match amount and reference
6. Click "Mark as Verified"
7. Add verification notes
8. Save

**Note**: Payment must be verified before final approval

### Task 3: Requesting Additional Documents

**Steps**:
1. Open application
2. Note what documents are needed
3. Click **"📧 Send Email"**
4. Select "Request Additional Documents"
5. List specific documents needed:
   - "Please provide original bank statement"
   - "Color photo of passport required"
6. Set deadline (e.g., "Please respond by April 22")
7. Send email
8. Add note to application: "Awaiting bank statement - requested Apr 15"

**Follow-up**: Check email for applicant response (usually within 24-48 hours)

### Task 4: Approving an Application

**Pre-Approval Checklist**:
- [ ] All required documents received
- [ ] Payment verified
- [ ] KYC screening passed
- [ ] No red flags or concerns
- [ ] Signature and terms accepted

**Approval Process**:
1. Open application
2. Review all checks complete
3. Add approval notes: "All documents verified, KYC passed, ready for account creation"
4. Click **"✅ Approve Application"**
5. Confirmation dialog appears
6. Click **"Confirm Approval"**
7. Status changes to ✅ Approved
8. Approval email sent automatically
9. Account creation workflow starts
10. Application date recorded

**Account Creation Timeline**:
- 0-2 hours: Approval recorded
- 2-5 days: Account created in system
- 5-10 days: Account activated
- Credentials emailed to applicant

### Task 5: Declining an Application

**Decline Process**:
1. Open application
2. Review reason for decline
3. Click **"❌ Decline Application"**
4. Select decline reason:
   - Incomplete documentation
   - Invalid identification
   - High risk profile
   - Payment not received
   - Failed AML screening
   - Fraud suspected
   - Other
5. Add decline explanation:
   - "Passport expired - requires valid ID"
   - "Bank statements more than 6 months old"
   - "Failed AML screening - account blocked"
6. Click **"Confirm Decline"**
7. Status changes to ❌ Declined
8. Decline email sent automatically
9. Refund process initiated (if payment received)
10. Application date recorded

**Post-Decline**:
- Refund issued within 2-5 business days
- Applicant can reapply with corrected documents
- Add note: "Reapplication can address [specific issues]"

### Task 6: Exporting Monthly Report

**Steps**:
1. Click **Reports** section
2. Select "Applications Summary"
3. Choose date range: "Month to Date"
4. View dashboard metrics:
   - Total new applications
   - Approval rate
   - Processing time average
   - Payment verification rate
5. Click **"📥 Export"**
6. Choose format: PDF
7. Report downloads with charts and metrics
8. Email or attach to monthly stakeholder report

---

## Dashboard Analytics

### Key Performance Indicators (KPIs)

Monitor these metrics regularly:

```
📊 APPLICATION METRICS
├─ New Applications (Today): ___
├─ Approval Rate (%): ___
├─ Average Review Time (hrs): ___
├─ Payment Verification Rate (%): ___
└─ Pending Review Count: ___

💰 FINANCIAL METRICS
├─ Total Revenue (This Month): €___
├─ Average Account Fee: €___
├─ Payment Received Rate (%): ___
└─ Refund Processing: ___ applications

⏱️ PERFORMANCE METRICS
├─ Fastest Review Time: ___ hours
├─ Slowest Review Time: ___ hours
├─ Email Response Rate: ___%
└─ Document Correction Rate: ___%
```

### Weekly Administration Checklist

**Every Monday**:
- [ ] Review pending applications (count)
- [ ] Check for payment reminders needed
- [ ] Send awaiting-documents reminders
- [ ] Generate weekly report
- [ ] Check system logs for errors
- [ ] Verify backups completed

**Every Friday**:
- [ ] Approve/decline all completed reviews
- [ ] Update stakeholders on progress
- [ ] Prepare weekend summary
- [ ] Plan next week's priorities

---

## Troubleshooting

### Common Issues

**Issue: Payment shown as pending but received**
- Solution: Manually verify and mark payment as verified in application

**Issue: Email not sending to applicants**
- Solution: Check email settings, verify SMTP credentials, test connection

**Issue: PDF not generating**
- Solution: Check wkhtmltopdf installation, verify temp directory permissions

**Issue: Cannot change application status**
- Solution: Check user permissions, may require admin role

**Issue: Documents not displaying**
- Solution: Check file permissions, verify image format, try re-uploading

---

## Support & Help

**Need Help?**
- Review this guide section by section
- Check WordPress help menus
- Contact technical support with application ID
- Escalate to senior admin if needed

**Report Issues**:
- Document the issue (steps to reproduce)
- Include affected Application ID
- Screenshot of error
- Email to: admin-support@prominencebank.com

---

*Admin Guide Version 5.5*  
*Last Updated: April 2026*  
*Next Review: July 2026*
