<?php
/**
 * Plugin Name: Financial Account Application Portal
 * Description: Secure Account Application portal with Admin Management and AI Email Notifications.
 * Version: 5.5
 * Author: AccountSelectr
 */

// 1. Database Setup
register_activation_hook(__FILE__, 'faap_setup_database');
function faap_setup_database() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $table_apps = $wpdb->prefix . 'faap_submissions';
    $sql_apps = "CREATE TABLE IF NOT EXISTS $table_apps (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type ENUM('personal', 'business') NOT NULL,
        account_type_id VARCHAR(100),
        status VARCHAR(50) DEFAULT 'Pending',
        ip_address VARCHAR(45),
        form_data LONGTEXT,
        submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";

    $table_forms = $wpdb->prefix . 'faap_forms';
    $sql_forms = "CREATE TABLE IF NOT EXISTS $table_forms (
        id INT AUTO_INCREMENT PRIMARY KEY,
        form_type VARCHAR(50) UNIQUE,
        config LONGTEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_apps);
    dbDelta($sql_forms);

    // Ensure IP column exists in old installs
    $exists = $wpdb->get_results("SHOW COLUMNS FROM $table_apps LIKE 'ip_address'");
    if (empty($exists)) {
        $wpdb->query("ALTER TABLE $table_apps ADD ip_address VARCHAR(45) NULL");
    }

    // Set default frontend URL if not set yet.
    if (!get_option('faap_frontend_url')) {
        add_option('faap_frontend_url', 'https://form.prominencebank.com/');
    }
}

// 2. REST API Endpoints
add_action('rest_api_init', function () {
    register_rest_route('faap/v1', '/form-config/(?P<type>[a-zA-Z0-9-]+)', array(
        'methods' => 'GET',
        'callback' => 'faap_get_form_config',
        'permission_callback' => '__return_true',
    ));
    register_rest_route('faap/v1', '/save-form', array(
        'methods' => 'POST',
        'callback' => 'faap_save_form_config',
        'permission_callback' => '__return_true',
    ));
    register_rest_route('faap/v1', '/submit', array(
        'methods' => 'POST',
        'callback' => 'faap_handle_submission',
        'permission_callback' => '__return_true',
    ));
    register_rest_route('faap/v1', '/applications', array(
        'methods' => 'GET',
        'callback' => 'faap_get_applications',
        'permission_callback' => '__return_true',
    ));
    register_rest_route('faap/v1', '/applications/(?P<id>\d+)/export-pdf', array(
        'methods' => 'GET',
        'callback' => 'faap_export_application_pdf',
        'permission_callback' => '__return_true',
    ));
    register_rest_route('faap/v1', '/applications/(?P<id>\d+)/payment-verified', array(
        'methods' => 'POST',
        'callback' => 'faap_verify_payment',
        'permission_callback' => '__return_true',
    ));
});

add_action('admin_menu', 'faap_admin_menu');
function faap_admin_menu() {
    add_menu_page('FAAP Applications', 'FAAP', 'manage_options', 'faap-applications', 'faap_admin_submissions');
    add_submenu_page('faap-applications', 'Manage Forms', 'Manage Forms', 'manage_options', 'faap-forms', 'faap_admin_manage_forms');
}

add_filter('rest_pre_serve_request', function($value) {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Authorization, Content-Type');
    return $value;
});

function faap_get_form_config($data) {
    global $wpdb;
    $type = sanitize_text_field($data['type'] ?? 'personal');
    $table_forms = $wpdb->prefix . 'faap_forms';
    $config = $wpdb->get_var($wpdb->prepare("SELECT config FROM $table_forms WHERE form_type = %s", $type));

    if (!$config) {
        return rest_ensure_response($type === 'business' ? faap_get_default_business_form_steps() : faap_get_default_form_steps());
    }

    $decoded = json_decode($config, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
        return rest_ensure_response($type === 'business' ? faap_get_default_business_form_steps() : faap_get_default_form_steps());
    }

    return rest_ensure_response($decoded);
}

function faap_save_uploaded_file($file, $prefix = 'faap') {
    if (empty($file) || !isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return null;
    }

    $upload_dir = wp_upload_dir();
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = sanitize_file_name($prefix . '-' . uniqid() . '.' . $ext);
    $target_path = trailingslashit($upload_dir['path']) . $filename;

    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        return trailingslashit($upload_dir['url']) . $filename;
    }

    return null;
}

function faap_format_label($key) {
    $label = preg_replace('/([a-z])([A-Z])/', '$1 $2', $key);
    $label = str_replace(['_', '-'], ' ', $label);
    return ucwords($label);
}

function faap_get_letterhead_logo_url() {
    $logo = get_option('faap_letterhead_logo_url');
    if (empty($logo)) {
        // If not set, fall back to a logo shipped with the plugin.
        $logo = plugins_url('Prominence Bank.png', __FILE__);
    }
    return esc_url($logo);
}

function faap_generate_application_id() {
    return 'App-' . mt_rand(100000000, 999999999);
}

function faap_get_banking_policy_html() {
    return '<div style="margin-top:26px;padding:14px;border:1px solid #e5e7eb;border-radius:10px;background:#f9fafb;"><div style="font-size:13px;color:#111827;line-height:1.45;"><div style="margin-bottom:8px;font-weight:700;font-size:14px;">AGREED AND ATTESTED</div><div>By signing and submitting this Personal Bank Account Application, the Applicant(s) acknowledge(s), confirm(s), attest(s), represent(s), warrant(s), and irrevocably agree(s) to the following:</div><div style="margin-top:8px;font-weight:700;">A. Mandatory Submission Requirements (Strict Compliance)</div><div style="margin-top:2px;">The Applicant(s) understand(s), acknowledge(s), and accept(s) that the Bank shall automatically reject, without substantive review, processing, or response, any application submitted without all mandatory items required by the Bank, including, without limitation:<br><ul style="margin:4px 0 4px 18px;color:#111827;"><li>Full Personal Bank Account opening fee</li><li>Valid proof of payment</li><li>All required documentation, disclosures, and supporting materials specified in the application form</li></ul>The Applicant(s) further acknowledge(s) that repeated submission of incomplete, deficient, inaccurate, or non-compliant applications may, at the Bank’s sole and absolute discretion, result in permanent disqualification from reapplying for any banking product or service.</div><div style="margin-top:6px;font-weight:700;">B. Payment Instructions (Opening Fee)</div><div style="margin-top:2px;">The Applicant(s) acknowledge(s), understand(s), and accept(s) that payments made via KTT/TELEX are strictly prohibited and shall not be accepted under any circumstances for payment of the bank account opening fee.<br>Accepted methods of payment for the opening fee are strictly limited to the following:<br><ul style="margin:4px 0 4px 18px;color:#111827;"><li>SWIFT international wire transfer</li><li>Cryptocurrency transfer to the designated wallet address listed in the application form</li></ul>The Applicant(s) further acknowledge(s) that the Application ID must be included in the payment reference field exactly as instructed by the Bank in order to ensure proper and timely allocation of funds. Incomplete, inaccurate, omitted, misdirected, or improperly referenced payments may delay processing and may result in rejection of the application, without liability to the Bank.</div><div style="margin-top:6px;font-weight:700;">C. Account Opening Requirements</div><div style="margin-top:2px;">The Applicant(s) acknowledge(s), understand(s), and accept(s) that:<br><ul style="margin:4px 0 4px 18px;color:#111827;"><li>A minimum balance of USD/EUR 5,000 must be maintained at all times.</li><li>Ongoing adherence to the Bank’s account policies, procedures, operational requirements, and compliance standards is required in order to maintain access to banking services.</li><li>If the account balance falls below the minimum required level, the Bank may restrict services, request corrective funding, apply internal controls, and/or place the account under compliance, risk, or administrative review until the deficiency is remedied.</li></ul></div><div style="margin-top:6px;font-weight:700;">D. Finality of Account Type Selection; No Conversion or Reclassification After Opening</div><div style="margin-top:2px;">The Applicant(s) hereby acknowledge(s), confirm(s), represent(s), warrant(s), and irrevocably agree(s) that the account category selected in this Application is made solely at the Applicant’s own election, responsibility, and risk, and shall be deemed final for purposes of the submitted Application.<br>The Applicant(s) further acknowledge(s) and accept(s) that, once the Application has been submitted, approved by the Bank, and the account has been opened, activated, or established under the selected account category, such account category shall be final and may not thereafter be amended, converted, substituted, re-designated, reclassified, exchanged, or otherwise modified into any other account type, whether in whole or in part.</div><div style="margin-top:6px;font-weight:700;">E. Transaction Profile and Ongoing Due Diligence</div><div style="margin-top:2px;">The Applicant(s) acknowledge(s) and accept(s) that:<br><ul style="margin:4px 0 4px 18px;color:#111827;"><li>Account activity must at all times reasonably align with the information declared in this application.</li><li>Any material deviation, inconsistency, anomaly, or change in activity profile may require additional verification and may be delayed, restricted, reviewed, declined, or otherwise subject to enhanced due diligence.</li><li>The Applicant(s) agree(s) to provide such additional documentation, declarations, evidence, or clarifications as the Bank may request at any time to satisfy AML/KYC and risk requirements.</li></ul></div><div style="margin-top:6px;font-weight:700;">F. Accuracy and Authorization</div><div style="margin-top:2px;">The Applicant(s) hereby affirm(s), represent(s), warrant(s), and undertake(s) that:<br><ul style="margin:4px 0 4px 18px;color:#111827;"><li>All information provided in this application is true, accurate, complete, current, and not misleading in any respect.</li><li>The information is submitted for the purpose of establishing a service relationship with Prominence Bank under the Terms and Conditions disclosed prior to submission and accepted by the Applicant(s) upon signature and/or submission.</li></ul>The Applicant(s) hereby authorize(s) the Bank, without further notice except where required by applicable law or the Bank’s governing framework, to verify details, conduct credit/fraud/identity/sanctions/compliance checks, and request additional information at any time.</div><div style="margin-top:6px;font-weight:700;">G. Account Retention, Record-Keeping, and Banking Relationship (ETMO Framework)</div><div style="margin-top:2px;">The Applicant(s) acknowledge(s) that accounts may be retained or restricted for regulatory, audit, compliance, or operational reasons and that closure is subject to internal policies and applicable legal obligations.</div><div style="margin-top:6px;font-weight:700;">H. Compliance and Regulatory Framework</div><div style="margin-top:2px;">The Applicant(s) acknowledge(s) that the Bank operates under a diplomatic regulatory framework, applies AML/KYC and sanctions controls, and may perform ongoing monitoring and due diligence.</div><div style="margin-top:6px;font-weight:700;">I. Data Processing and Privacy</div><div style="margin-top:2px;">The Applicant(s) agree(s) that personal data is collected and processed for onboarding, compliance, execution, and record retention in accordance with the Bank’s policies. The Applicant(s) can request rights where available under applicable law.</div></div></div>';
}

function faap_get_full_agreed_attested_html() {
    return '
    <div style="margin-bottom:16px;">
        <div style="font-weight:700;color:#111827;font-size:14px;margin-bottom:8px;">AGREED AND ATTESTED</div>
        <div style="background:#fefce8;border:1px solid #fde047;border-radius:6px;padding:12px;margin-bottom:12px;font-size:12px;line-height:1.5;color:#111827;">
            <p style="margin:8px 0;"><strong>A. Mandatory Submission Requirements (Strict Compliance)</strong></p>
            <p style="margin:8px 0;">The Applicant(s) understand(s), acknowledge(s), and accept(s) that the Bank shall automatically reject, without substantive review, processing, or response, any application submitted without all mandatory items required by the Bank, including:</p>
            <ul style="margin:8px 0 8px 18px;padding:0;"><li>Full Personal Bank Account opening fee</li><li>Valid proof of payment</li><li>All required documentation, disclosures, and supporting materials specified in the application form</li></ul>
            <p style="margin:8px 0;"><strong>B. Payment Instructions (Opening Fee)</strong></p>
            <p style="margin:8px 0;">The Applicant(s) acknowledge(s), understand(s), and accept(s) that payments made via KTT/TELEX are strictly prohibited and shall not be accepted under any circumstances for payment of the bank account opening fee. Accepted methods are strictly limited to: SWIFT international wire transfer or Cryptocurrency transfer to the designated wallet address listed in the application form.</p>
            <p style="margin:8px 0;"><strong>C. Account Opening Requirements</strong></p>
            <p style="margin:8px 0;">A minimum balance of USD/EUR 5,000 must be maintained in the account at all times. Ongoing adherence to the Bank\'s account policies, procedures, operational requirements, and compliance standards is required in order to maintain access to banking services.</p>
            <p style="margin:8px 0;"><strong>D. Finality of Account Type Selection; No Conversion or Reclassification After Opening</strong></p>
            <p style="margin:8px 0;">The account category selected in this Application is made solely at the Applicant\'s own election, responsibility, and risk, and shall be deemed final. Once the Application has been submitted, approved by the Bank, and the account has been opened, activated, or established under the selected account category, such account category shall be final and may not thereafter be amended, converted, substituted, re-designated, reclassified, exchanged, or otherwise modified.</p>
            <p style="margin:8px 0;"><strong>E. Transaction Profile and Ongoing Due Diligence</strong></p>
            <p style="margin:8px 0;">Account activity must at all times reasonably align with the information declared in this application. Any material deviation, inconsistency, anomaly, or change in activity profile may require additional verification and may be delayed, restricted, reviewed, declined, or otherwise subject to enhanced due diligence.</p>
            <p style="margin:8px 0;"><strong>F. Accuracy and Authorization</strong></p>
            <p style="margin:8px 0;">All information provided in this application is true, accurate, complete, current, and not misleading. The Applicant(s) hereby authorize(s) the Bank to verify all details, conduct credit, fraud-prevention, identity, sanctions, adverse media, compliance, and risk checks, including AML/KYC screening.</p>
            <p style="margin:8px 0;"><strong>G. Account Retention, Record-Keeping, and Banking Relationship</strong></p>
            <p style="margin:8px 0;">Account status, retention, restriction, suspension, and closure decisions are governed exclusively by the Bank\'s internal Administration, Compliance, Legal, Security, and Risk functions. Accounts are not closed solely upon a client\'s request. The Bank retains the sole and absolute discretion to maintain the account in an administrative, dormant, restricted, or archived status where necessary for record retention, compliance review, and orderly settlement.</p>
            <p style="margin:8px 0;"><strong>H. Compliance and Regulatory Framework</strong></p>
            <p style="margin:8px 0;">The Applicant(s) must comply fully and promptly with all onboarding and ongoing AML/KYC, sanctions, source-of-funds, source-of-wealth, identity verification, and monitoring requirements. The Bank applies internationally aligned compliance and risk standards, including FATF-based AML controls and generally recognized banking risk frameworks.</p>
            <p style="margin:8px 0;"><strong>I. Data Processing and Privacy</strong></p>
            <p style="margin:8px 0;">Personal data and related information provided by the Applicant(s) are required for evaluating, processing, administering, verifying, and managing this application. The Bank is authorized to collect, process, record, verify, analyze, transfer, retain, and store such data to facilitate present and future transactions and to satisfy legal, compliance, operational, audit, fraud-prevention, cybersecurity, and security obligations.</p>
            <p style="margin:8px 0;"><strong>J. Additional Standard Banking Provisions (General)</strong></p>
            <p style="margin:8px 0;">The Bank may, at its sole and absolute discretion, decline, delay, restrict, suspend, refuse, reverse, or not process any application, account service, instruction, transaction, transfer, payment, or product feature. The Bank applies manual review, verification holds, enhanced due diligence, temporary restrictions, reserve requirements, and other internal controls. All Bank fees, service charges, intermediary charges, network fees, custody fees, FX conversion costs, investigation costs, legal costs, and compliance costs may be debited, deducted, offset, withheld, or otherwise collected.</p>
            <p style="margin:8px 0;"><strong>Sections K-Q:</strong> Additional provisions covering instructions and authentication, online banking security, electronic communications, ongoing disclosure duty, prohibited use, set-off and recovery, indemnity, limitation of liability, force majeure, severability, and dispute handling are incorporated herein by reference as binding terms of this Application.</p>
        </div>
    </div>
    ';
}

function faap_get_comprehensive_footer_terms_html() {
    return '
    <div style="margin-bottom:16px;font-size:11px;line-height:1.4;color:#666;">
        <p style="margin:4px 0;"><strong>Full Terms Agreement:</strong> The applicant confirms having read and understood this entire Application, including all 17 binding provisions under the AGREED AND ATTESTED section, the payment/refund terms, the Bank\'s account retention and record-keeping provisions, the KYC/AML documentation requirements, and the complete Third-Party Onboarding Notice. By submitting this application, the applicant irrevocably agrees to all terms and conditions set forth herein.</p>
    </div>
    ';
}

function faap_get_payment_kyc_html($app_id = 'N/A', $recipient = 'admin') {
    $safe_app_id = esc_html($app_id);
    $is_applicant = ($recipient === 'applicant');
    
    if ($is_applicant) {
        // For applicant emails, show collapsed sections
        return '
    <div style="margin-bottom:16px;">
        <div style="font-weight:700;color:#111827;font-size:14px;margin-bottom:8px;">KYC/AML DOCUMENTATION NOTE</div>
        <div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:12px;margin-bottom:12px;font-size:13px;line-height:1.5;">
            <details style="margin:0;">
                <summary style="cursor:pointer;font-weight:600;margin-bottom:8px;">Click to expand / view terms</summary>
                <p style="margin:8px 0;">Please ensure all documents are clear and valid. PCM may assist with intake and document coordination and transmit the compiled package to Prominence Bank. Prominence Bank may request additional documentation or enhanced due diligence at any time. Incomplete or inconsistent information may delay processing or result in the application being declined.</p>
            </details>
        </div>
    </div>

    <div style="margin-bottom:16px;">
        <div style="font-weight:700;color:#111827;font-size:14px;margin-bottom:8px;">ACCOUNT OPENING FEE — PAYMENT INSTRUCTIONS</div>
        <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;padding:12px;margin-bottom:12px;font-size:13px;line-height:1.6;">
            <details style="margin:0;">
                <summary style="cursor:pointer;font-weight:600;margin-bottom:8px;">Click to expand / view payment details</summary>
                <p style="margin:0 0 8px;"><strong>Applicable to all new account types listed below.</strong></p>
                <p style="margin:8px 0;"><strong>Account Opening Fee (Onboarding &amp; Compliance Processing Fee)</strong></p>
                <p style="margin:8px 0;">Payment of the Account Opening Fee does not guarantee approval or account opening.</p>
                <ul style="margin:8px 0 8px 18px;padding:0;color:#111827;">
                    <li>€25,000 – Euro Account</li>
                    <li>$25,000 – USD Account</li>
                    <li>€25,000 – Custody Account</li>
                    <li>€25,000 – Cryptocurrency Account</li>
                    <li>€50,000 – Numbered Account</li>
                </ul>
                <p style="margin:12px 0 8px;"><strong>REFUND POLICY (NO EXCEPTIONS)</strong></p>
                <p style="margin:8px 0;">If the application is declined and no account is opened, the Account Opening Fee will be refunded in full by PCM (no PCM deductions). Please note that intermediary banks, card processors, or blockchain networks may charge separate fees outside PCM\'s control, which can affect the net amount received by the sender. Refunds are issued to the original sender (same payment route) within ten (10) business days after the application is formally declined in the Bank\'s records.</p>
                <p style="margin:8px 0;">If the application is approved and an account is opened, the Account Opening Fee is deemed fully earned upon account opening and is non-refundable, as it covers completed onboarding, administrative coordination, and compliance processing services.</p>
            </details>
        </div>
    </div>

    <div style="margin-bottom:16px;">
        <div style="font-weight:700;color:#111827;font-size:14px;margin-bottom:8px;">PAYMENT OPTION 1: INTERNATIONAL WIRE (SWIFT)</div>
        <div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:12px;margin-bottom:12px;font-size:12px;line-height:1.5;color:#111827;">
            <details style="margin:0;">
                <summary style="cursor:pointer;font-weight:600;margin-bottom:8px;">Click to expand / view SWIFT details</summary>
                <p style="margin:0 0 12px;"><strong>EURO (€) CURRENCY</strong></p>
                <p style="margin:2px 0;"><strong>Bank Name:</strong> Wise Europe</p>
                <p style="margin:2px 0;"><strong>Bank Address:</strong> Rue du Trône 100, 3rd floor. Brussels. 1050. Belgium</p>
                <p style="margin:2px 0;"><strong>SWIFT Code:</strong> TRWIBEB1XXX</p>
                <p style="margin:2px 0;"><strong>Account Name:</strong> PROMINENCE CLIENT MANAGEMENT</p>
                <p style="margin:2px 0;"><strong>Account Number/IBAN:</strong> BE31905717979455</p>
                <p style="margin:2px 0;"><strong>Account Address:</strong> Rue du Trône 100, 3rd floor. Brussels. 1050. Belgium</p>
                <p style="margin:8px 0 0;"><strong>Payment Reference / Memo (REQUIRED):</strong> Application ID: ' . $safe_app_id . ' | Onboarding and Compliance Processing Fee</p>
                <p style="margin:12px 0 0;"><strong>USD ($) CURRENCY</strong></p>
                <p style="margin:2px 0;"><strong>Bank Name:</strong> Wise US Inc.</p>
                <p style="margin:2px 0;"><strong>Bank Address:</strong> 108 W 13th St, Wilmington, DE, 19801, United States</p>
                <p style="margin:2px 0;"><strong>SWIFT Code:</strong> TRWIUS35XXX</p>
                <p style="margin:2px 0;"><strong>Account Name:</strong> PROMINENCE CLIENT MANAGEMENT</p>
                <p style="margin:2px 0;"><strong>Account Number:</strong> 205414015428310</p>
                <p style="margin:2px 0;"><strong>Account Address:</strong> 108 W 13th St, Wilmington, DE, 19801, United States</p>
                <p style="margin:8px 0;"><strong>Payment Reference / Memo (REQUIRED):</strong> Application ID: ' . $safe_app_id . ' | Onboarding and Compliance Processing Fee</p>
            </details>
        </div>
    </div>

    <div style="margin-bottom:16px;">
        <div style="font-weight:700;color:#111827;font-size:14px;margin-bottom:8px;">PAYMENT OPTION 2: CRYPTOCURRENCY (USDT TRC20)</div>
        <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;padding:12px;margin-bottom:12px;font-size:12px;line-height:1.5;color:#111827;">
            <details style="margin:0;">
                <summary style="cursor:pointer;font-weight:600;margin-bottom:8px;">Click to expand / view crypto details</summary>
                <p style="margin:0 0 8px;"><strong>USDT Wallet Address (TRC20):</strong> <code style="background:#e5e7eb;padding:2px 4px;border-radius:3px;font-family:monospace;">TPYjSzK3BbZRZAVhBoRZcdyzKpQ9NN6S6Y</code></p>
                <p style="margin:8px 0;"><strong>CRYPTOCURRENCY PAYMENT CONTROLS (USDT TRC20)</strong></p>
                <p style="margin:8px 0;">Crypto is accepted solely as a payment method for the Account Opening Fee. PCM does not provide any virtual-asset exchange, brokerage, custody, wallet custody, or transfer service. To validate a crypto payment, you must provide (i) TXID/transaction hash, (ii) amount sent, (iii) sending wallet address, and (iv) timestamp and supporting screenshot (if available). Refunds (if due) are issued only to the originating wallet address after verification.</p>
                <p style="margin:12px 0;padding:8px;background:#fef3c7;border-left:3px solid #f59e0b;"><strong>⚠️ IMPORTANT NOTICE:</strong> The Account Opening Fee must be paid via SWIFT international wire (Option 1), or USDT (Option 2). KTT / Telex are not accepted for this initial payment and will not be used to activate an account.</p>
            </details>
        </div>
    </div>

    <div style="margin-bottom:16px;">
        <div style="font-weight:700;color:#111827;font-size:14px;margin-bottom:8px;">THIRD-PARTY ONBOARDING AND PAYMENT NOTICE</div>
        <div style="background:#fef3c7;border:1px solid #fcd34d;border-radius:6px;padding:12px;margin-bottom:12px;font-size:12px;line-height:1.5;color:#111827;">
            <details style="margin:0;">
                <summary style="cursor:pointer;font-weight:600;margin-bottom:8px;">Click to expand / view terms</summary>
                <p style="margin:8px 0;">This application may be supported by Prominence Client Management / Prominence Account Management ("PCM"), a separate legal entity acting as an independent introducer and providing administrative onboarding coordination only (intake support, document collection coordination, and application-package transmission). PCM is not authorized to bind Prominence Bank or make representations regarding approval. PCM is not a bank and does not provide banking, deposit-taking, securities brokerage, investment advisory, fiduciary, custody, or legal services. Prominence Bank alone determines whether to approve or decline an application and whether an account is opened. Any Account Opening Fee paid to PCM is a service fee for onboarding and compliance-processing support; it is not a deposit with Prominence Bank and does not create or fund a bank account.</p>
                <p style="margin:8px 0;"><strong>SCOPE OF PCM SERVICES:</strong> PCM services are limited to (i) assisting with completion of intake forms, (ii) coordinating collection of required documents, (iii) basic completeness checks (format/legibility), and (iv) transmitting the compiled application package to Prominence Bank. PCM does not provide advice, does not negotiate terms, does not handle client assets for investment or custody purposes, and does not represent that an application will be approved.</p>
            </details>
        </div>
    </div>

    ' . faap_get_full_agreed_attested_html() . '
    ' . faap_get_comprehensive_footer_terms_html() . '
    ';
    } else {
        // For admin emails/PDFs, show expanded sections
        return '
    <div style="margin-bottom:16px;">
        <div style="font-weight:700;color:#111827;font-size:14px;margin-bottom:8px;">KYC/AML DOCUMENTATION NOTE</div>
        <div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:12px;margin-bottom:12px;font-size:13px;line-height:1.5;">
            <p style="margin:0 0 8px;"><strong>Click to expand / view terms</strong></p>
            <p style="margin:8px 0;">Please ensure all documents are clear and valid. PCM may assist with intake and document coordination and transmit the compiled package to Prominence Bank. Prominence Bank may request additional documentation or enhanced due diligence at any time. Incomplete or inconsistent information may delay processing or result in the application being declined.</p>
        </div>
    </div>

    <div style="margin-bottom:16px;">
        <div style="font-weight:700;color:#111827;font-size:14px;margin-bottom:8px;">ACCOUNT OPENING FEE — PAYMENT INSTRUCTIONS</div>
        <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;padding:12px;margin-bottom:12px;font-size:13px;line-height:1.6;">
            <p style="margin:0 0 8px;"><strong>Applicable to all new account types listed below.</strong></p>
            <p style="margin:8px 0;"><strong>Account Opening Fee (Onboarding &amp; Compliance Processing Fee)</strong></p>
            <p style="margin:8px 0;">Payment of the Account Opening Fee does not guarantee approval or account opening.</p>
            <ul style="margin:8px 0 8px 18px;padding:0;color:#111827;">
                <li>€25,000 – Euro Account</li>
                <li>$25,000 – USD Account</li>
                <li>€25,000 – Custody Account</li>
                <li>€25,000 – Cryptocurrency Account</li>
                <li>€50,000 – Numbered Account</li>
            </ul>
            <p style="margin:12px 0 8px;"><strong>REFUND POLICY (NO EXCEPTIONS)</strong></p>
            <p style="margin:8px 0;">If the application is declined and no account is opened, the Account Opening Fee will be refunded in full by PCM (no PCM deductions). Please note that intermediary banks, card processors, or blockchain networks may charge separate fees outside PCM\'s control, which can affect the net amount received by the sender. Refunds are issued to the original sender (same payment route) within ten (10) business days after the application is formally declined in the Bank\'s records.</p>
            <p style="margin:8px 0;">If the application is approved and an account is opened, the Account Opening Fee is deemed fully earned upon account opening and is non-refundable, as it covers completed onboarding, administrative coordination, and compliance processing services.</p>
        </div>
    </div>

    <div style="margin-bottom:16px;">
        <div style="font-weight:700;color:#111827;font-size:14px;margin-bottom:8px;">PAYMENT OPTION 1: INTERNATIONAL WIRE (SWIFT)</div>
        <div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:12px;margin-bottom:12px;font-size:12px;line-height:1.5;color:#111827;">
            <p style="margin:0 0 12px;"><strong>EURO (€) CURRENCY</strong></p>
            <p style="margin:2px 0;"><strong>Bank Name:</strong> Wise Europe</p>
            <p style="margin:2px 0;"><strong>Bank Address:</strong> Rue du Trône 100, 3rd floor. Brussels. 1050. Belgium</p>
            <p style="margin:2px 0;"><strong>SWIFT Code:</strong> TRWIBEB1XXX</p>
            <p style="margin:2px 0;"><strong>Account Name:</strong> PROMINENCE CLIENT MANAGEMENT</p>
            <p style="margin:2px 0;"><strong>Account Number/IBAN:</strong> BE31905717979455</p>
            <p style="margin:2px 0;"><strong>Account Address:</strong> Rue du Trône 100, 3rd floor. Brussels. 1050. Belgium</p>
            <p style="margin:8px 0 0;"><strong>Payment Reference / Memo (REQUIRED):</strong> Application ID: ' . $safe_app_id . ' | Onboarding and Compliance Processing Fee</p>
            <p style="margin:12px 0 0;"><strong>USD ($) CURRENCY</strong></p>
            <p style="margin:2px 0;"><strong>Bank Name:</strong> Wise US Inc.</p>
            <p style="margin:2px 0;"><strong>Bank Address:</strong> 108 W 13th St, Wilmington, DE, 19801, United States</p>
            <p style="margin:2px 0;"><strong>SWIFT Code:</strong> TRWIUS35XXX</p>
            <p style="margin:2px 0;"><strong>Account Name:</strong> PROMINENCE CLIENT MANAGEMENT</p>
            <p style="margin:2px 0;"><strong>Account Number:</strong> 205414015428310</p>
            <p style="margin:2px 0;"><strong>Account Address:</strong> 108 W 13th St, Wilmington, DE, 19801, United States</p>
            <p style="margin:8px 0;"><strong>Payment Reference / Memo (REQUIRED):</strong> Application ID: ' . $safe_app_id . ' | Onboarding and Compliance Processing Fee</p>
        </div>
    </div>

    <div style="margin-bottom:16px;">
        <div style="font-weight:700;color:#111827;font-size:14px;margin-bottom:8px;">PAYMENT OPTION 2: CRYPTOCURRENCY (USDT TRC20)</div>
        <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;padding:12px;margin-bottom:12px;font-size:12px;line-height:1.5;color:#111827;">
            <p style="margin:0 0 8px;"><strong>USDT Wallet Address (TRC20):</strong> <code style="background:#e5e7eb;padding:2px 4px;border-radius:3px;font-family:monospace;">TPYjSzK3BbZRZAVhBoRZcdyzKpQ9NN6S6Y</code></p>
            <p style="margin:8px 0;"><strong>CRYPTOCURRENCY PAYMENT CONTROLS (USDT TRC20)</strong></p>
            <p style="margin:8px 0;">Crypto is accepted solely as a payment method for the Account Opening Fee. PCM does not provide any virtual-asset exchange, brokerage, custody, wallet custody, or transfer service. To validate a crypto payment, you must provide (i) TXID/transaction hash, (ii) amount sent, (iii) sending wallet address, and (iv) timestamp and supporting screenshot (if available). Refunds (if due) are issued only to the originating wallet address after verification.</p>
            <p style="margin:12px 0;padding:8px;background:#fef3c7;border-left:3px solid #f59e0b;"><strong>⚠️ IMPORTANT NOTICE:</strong> The Account Opening Fee must be paid via SWIFT international wire (Option 1), or USDT (Option 2). KTT / Telex are not accepted for this initial payment and will not be used to activate an account.</p>
        </div>
    </div>

    <div style="margin-bottom:16px;">
        <div style="font-weight:700;color:#111827;font-size:14px;margin-bottom:8px;">THIRD-PARTY ONBOARDING AND PAYMENT NOTICE</div>
        <div style="background:#fef3c7;border:1px solid #fcd34d;border-radius:6px;padding:12px;margin-bottom:12px;font-size:12px;line-height:1.5;color:#111827;">
            <p style="margin:0 0 8px;"><strong>Click to expand / view terms</strong></p>
            <p style="margin:8px 0;">This application may be supported by Prominence Client Management / Prominence Account Management ("PCM"), a separate legal entity acting as an independent introducer and providing administrative onboarding coordination only (intake support, document collection coordination, and application-package transmission). PCM is not authorized to bind Prominence Bank or make representations regarding approval. PCM is not a bank and does not provide banking, deposit-taking, securities brokerage, investment advisory, fiduciary, custody, or legal services. Prominence Bank alone determines whether to approve or decline an application and whether an account is opened. Any Account Opening Fee paid to PCM is a service fee for onboarding and compliance-processing support; it is not a deposit with Prominence Bank and does not create or fund a bank account.</p>
            <p style="margin:8px 0;"><strong>SCOPE OF PCM SERVICES:</strong> PCM services are limited to (i) assisting with completion of intake forms, (ii) coordinating collection of required documents, (iii) basic completeness checks (format/legibility), and (iv) transmitting the compiled application package to Prominence Bank. PCM does not provide advice, does not negotiate terms, does not handle client assets for investment or custody purposes, and does not represent that an application will be approved.</p>
        </div>
    </div>

    ' . faap_get_full_agreed_attested_html() . '
    ' . faap_get_comprehensive_footer_terms_html() . '
    ';
    }
}

function faap_get_email_header_block_html($recipient, $admin_email, $user_email, $brand_name, $brand_email, $app_id, $type_label, $submitted_at) {
    $is_applicant = ($recipient === 'applicant');
    $to_email = $is_applicant ? $user_email : $admin_email;
    $header_title = $is_applicant ? 'Applicant Email Header' : 'Admin Email Header';
    $status_label = $is_applicant ? '✓ YOUR CONFIRMATION' : '🔔 ADMIN NOTIFICATION';
    $status_color = $is_applicant ? '#2e7d32' : '#d32f2f';
    $status_border = $is_applicant ? '#1b5e20' : '#b71c1c';

    return '<div style="background:' . esc_attr($status_color) . ';color:#fff;padding:12px 16px;border-bottom:2px solid ' . esc_attr($status_border) . ';font-weight:700;font-size:14px;letter-spacing:0.045em;">' . esc_html($status_label) . '</div>' .
        '<div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;padding:10px;margin-top:8px;">' .
        '<div style="font-weight:700;font-size:13px;color:#0f172a;">' . esc_html($header_title) . '</div>' .
        '<div style="font-size:12px;color:#334155;margin-top:4px;">From: ' . esc_html($brand_name) . ' &lt;' . esc_html($brand_email) . '&gt;</div>' .
        '<div style="font-size:12px;color:#334155;margin-top:2px;">Subject: New Application #' . esc_html($app_id) . ' - ' . esc_html($type_label) . ' Account</div>' .
        '<div style="font-size:12px;color:#334155;margin-top:2px;">Date: ' . esc_html($submitted_at) . '</div>' .
        '<div style="font-size:12px;color:#334155;margin-top:2px;">To: ' . esc_html($to_email) . '</div>' .
        '</div>';
}

function faap_build_application_html($submission, $recipient = 'admin') {
    $app_id = sanitize_text_field($submission['applicationId'] ?? 'N/A');
    $type_label = ucwords(sanitize_text_field($submission['type'] ?? 'personal'));
    $submitted_at = sanitize_text_field($submission['submittedAt'] ?? date('Y-m-d H:i:s'));

    $data = $submission;
    if (isset($submission['applicationData']) && is_string($submission['applicationData'])) {
        $decoded = json_decode($submission['applicationData'], true);
        if (is_array($decoded)) {
            $data = array_merge($data, $decoded);
        }
    }

    $rows = '';
    $excluded = ['emailSubject', 'emailBody', 'applicationData', 'mainDocumentFile', 'paymentProofFile', 'companyRegFile', 'signatureImage', 'attestation', 'submittedAt', 'status', 'type', 'accountTypeId', 'applicationId'];

    $attestationHtml = '';
    if (!empty($data['attestation']) && is_array($data['attestation'])) {
        $att = $data['attestation'];
        $attestationLines = [];

        if (isset($att['signatureName'])) {
            $attestationLines[] = '<div><strong>Name:</strong> ' . esc_html($att['signatureName']) . '</div>';
        }
        if (isset($att['idNumber'])) {
            $attestationLines[] = '<div><strong>ID Number:</strong> ' . esc_html($att['idNumber']) . '</div>';
        }
        if (isset($att['signatureDate'])) {
            $attestationLines[] = '<div><strong>Date:</strong> ' . esc_html($att['signatureDate']) . '</div>';
        }
        if (isset($att['agreedToTerms'])) {
            $attestationLines[] = '<div><strong>Accepted Terms:</strong> ' . ($att['agreedToTerms'] ? 'Yes' : 'No') . '</div>';
        }

        $attestationSignature = '';

        $signatureSource = '';
        if (!empty($att['signatureImage']) && is_string($att['signatureImage'])) {
            $signatureSource = $att['signatureImage'];
        } elseif (!empty($data['signatureImage']) && is_string($data['signatureImage'])) {
            $signatureSource = $data['signatureImage'];
        }

        if ($signatureSource && preg_match('/^(data:image\/[a-zA-Z]+;base64,[A-Za-z0-9+\/=]+)$/', $signatureSource, $sigMatch)) {
            $attestationSignature = '<div style="margin-top:8px;"><img src="' . esc_url($sigMatch[1]) . '" alt="Signature" style="max-width:320px;max-height:200px;height:auto;border:1px solid #cbd5e1;border-radius:5px;" /></div>';
        }

        $attestationHtml = '<div style="background:#f9fafb;border:1px solid #e2e8f0;border-radius:6px;padding:12px;margin-bottom:16px;"><div style="font-weight:700;margin-bottom:8px;color:#0f172a;">Attestation</div>' . implode('', $attestationLines) . $attestationSignature . '</div>';
    }

    $format_item_html = function ($value) {
        if (is_array($value)) {
            $items = [];
            foreach ($value as $item) {
                if (is_string($item) && preg_match('/(data:image\/[a-zA-Z]+;base64,[A-Za-z0-9+\/=]+)$/', $item, $matches)) {
                    $text = trim(str_replace($matches[1], '', $item), ", \t\n\r");
                    if ($text !== '') {
                        $items[] = '<span>' . esc_html($text) . '</span>';
                    }
                    $items[] = '<img src="' . esc_url($matches[1]) . '" alt="Applicant Signature" style="max-width:260px;height:auto;border:1px solid #cbd5e1;border-radius:5px;margin-top:6px;" />';
                } else {
                    $items[] = '<span>' . esc_html(is_scalar($item) ? (string)$item : json_encode($item)) . '</span>';
                }
            }
            return implode('<br>', $items);
        }

        if (is_string($value) && preg_match('/(data:image\/[a-zA-Z]+;base64,[A-Za-z0-9+\/=]+)$/', $value, $matches)) {
            $text = trim(str_replace($matches[1], '', $value), ", \t\n\r");
            $html = '';
            if ($text !== '') {
                $html .= '<span>' . esc_html($text) . '</span><br>';
            }
            $html .= '<img src="' . esc_url($matches[1]) . '" alt="Applicant Signature" style="max-width:260px;height:auto;border:1px solid #cbd5e1;border-radius:5px;margin-top:6px;" />';
            return $html;
        }

        return esc_html(is_scalar($value) ? (string)$value : json_encode($value));
    };

    foreach ($data as $key => $value) {
        if (in_array($key, $excluded, true)) {
            continue;
        }

        $rows .= '<tr><td style="padding:8px 10px;border:1px solid #e5e7eb;font-weight:600;background:#f9fafb;color:#111827;width:28%;">' . esc_html(faap_format_label($key)) . '</td><td style="padding:8px 10px;border:1px solid #e5e7eb;color:#111827;">' . $format_item_html($value) . '</td></tr>';
    }

    $attachments = [];
    if (!empty($submission['mainDocumentFile'])) $attachments[] = $submission['mainDocumentFile'];
    if (!empty($submission['paymentProofFile'])) $attachments[] = $submission['paymentProofFile'];
    if (!empty($submission['companyRegFile'])) $attachments[] = $submission['companyRegFile'];

    $attachmentItems = '';
    foreach ($attachments as $fileUrl) {
        $attachmentItems .= '<li style="margin-bottom:4px;"><a href="' . esc_url($fileUrl) . '" target="_blank" rel="noopener" style="color:#2563eb;text-decoration:none;">' . esc_html(basename($fileUrl)) . '</a></li>';
    }
    if (empty($attachmentItems)) {
        $attachmentItems = '<li style="color:#6b7280;">No documents uploaded.</li>';
    }

    $user_name = esc_html($data['fullName'] ?? $data['name'] ?? 'Applicant');
    $user_email = esc_html($data['email'] ?? 'N/A');
    $logoUrl = faap_get_letterhead_logo_url();
    $brand_name = esc_html(get_option('faap_brand_name', 'Prominence Bank Corp.'));
    $brand_email = esc_html(get_option('faap_brand_email', 'account@prominencebank.com'));
    $header_title = esc_html(get_option('faap_header_title', 'Prominence Bank Application Submission'));

    $subject_template = $recipient === 'admin' ? get_option('faap_admin_subject_template', 'New Form Entry #{{applicationId}} - {{type}}') : get_option('faap_user_subject_template', 'New Form Entry #{{applicationId}} - {{type}}');
    $subject = str_replace(['{{applicationId}}', '{{type}}'], [esc_html($app_id), esc_html($type_label)], esc_html($subject_template));

    return '<div style="font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:#f3f4f6;padding:18px;">
      <div style="max-width:800px;margin:0 auto;background:#fff;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;">
        <div style="' . ($recipient === 'admin' ? 'background:#d32f2f;color:#fff;padding:12px 16px;border-bottom:2px solid #b71c1c;font-weight:700;font-size:14px;letter-spacing:0.045em;">🔔 ADMIN NOTIFICATION' : 'background:#2e7d32;color:#fff;padding:12px 16px;border-bottom:2px solid #1b5e20;font-weight:700;font-size:14px;letter-spacing:0.045em;">✓ YOUR CONFIRMATION') . '</div>
        <div style="background:#f8f9fa;padding:20px 16px;border-bottom:1px solid #e5e7eb;display:flex;justify-content:space-between;align-items:flex-start;gap:16px;">
          <div style="flex:1;min-width:0;display:flex;flex-direction:column;justify-content:flex-start;">
            <div style="font-size:16px;font-weight:700;color:#111827;margin-bottom:6px;">'. $brand_name .'</div>
            <div style="font-size:13px;color:#374151;margin-bottom:4px;"><strong>Email:</strong> '. $brand_email .'</div>
            <div style="font-size:13px;color:#374151;margin-bottom:4px;"><strong>Address:</strong> P.B. 1257 Bonovo Road, Fomboni, Mwali, KM</div>
            <div style="font-size:13px;color:#374151;"><strong>Phone:</strong> +1 (555) 123-4567</div>
            <div style="font-size:12px;color:#6b7280;margin-top:8px;border-top:1px solid #d1d5db;padding-top:8px;">
              <div><strong>Subject:</strong> ' . esc_html($subject) . '</div>
              <div style="margin-top:4px;"><strong>Application ID:</strong> ' . esc_html($app_id) . ' | ' . esc_html($type_label) . ' Account</div>
            </div>
          </div>
          <div style="text-align:right;flex-shrink:0;display:flex;flex-direction:column;align-items:flex-end;justify-content:flex-start;gap:4px;">
            <img src="' . esc_url($logoUrl) . '" alt="'. $brand_name .'" style="max-height:100px;max-width:120px;object-fit:contain;" />
            <div style="font-size:11px;color:#6b7280;">Official Seal</div>
          </div>
        </div>

        <div style="padding:16px;">
          <h2 style="margin:0 0 8px;color:#111827;font-size:20px;">' . $header_title . '</h2>
          <div style="color:#374151; margin:0 0 12px; font-size:14px;">Application ID: <strong>' . esc_html($app_id) . '</strong></div>
          <div style="margin-bottom:16px;display:flex;gap:12px;flex-wrap:wrap;"><div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;padding:8px;">Type: <strong>' . esc_html($type_label) . '</strong></div><div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;padding:8px;">Submitted: <strong>' . esc_html($submitted_at) . '</strong></div></div>

          <div style="margin-bottom:16px;">
            <div style="font-weight:700;color:#111827;font-size:14px;margin-bottom:8px;">Application Details</div>
            <table style="width:100%;border-collapse:collapse;border:1px solid #e5e7eb;">' . $rows . '</table>
          </div>

          ' . faap_get_payment_kyc_html($app_id, $recipient) . '

          <div style="margin-bottom:16px;"><div style="font-weight:700;color:#111827;font-size:14px;margin-bottom:8px;">Uploaded Documents</div><ul style="margin:0 0 0 18px;padding:0;color:#111827;">' . $attachmentItems . '</ul></div>

          ' . $attestationHtml . '

          <div style="font-size:12px;color:#6b7280;margin-top:10px;">For support, contact <a href="mailto:support@prominencebank.com" style="color:#2563eb;text-decoration:none;">support@prominencebank.com</a>.</div>
        </div>
      </div>
    </div>';
}

function faap_build_application_pdf_html($submission, $recipient = 'admin') {
    // PDFs show expanded content but maintain appropriate headers for recipient
    // Use 'admin' for expanded/full details, but pass through recipient for correct header styling
    $adminContent = faap_build_application_html($submission, 'admin');
    if ($recipient !== 'admin') {
        // For applicant PDFs, replace admin header with applicant header
        $adminContent = str_replace('🔔 ADMIN NOTIFICATION', '✓ YOUR CONFIRMATION', $adminContent);
        $adminContent = str_replace('background:#d32f2f;color:#fff;padding:12px 16px;border-bottom:2px solid #b71c1c;', 'background:#2e7d32;color:#fff;padding:12px 16px;border-bottom:2px solid #1b5e20;', $adminContent);
    }
    return '<html><head><meta charset="utf-8"><style>body{margin:0;padding:0;background:#f3f4f6;} </style></head><body>' . $adminContent . '</body></html>';
}

function faap_build_simple_email_body($submission) {
    $app_id = sanitize_text_field($submission['applicationId'] ?? 'N/A');
    $logo_url = faap_get_letterhead_logo_url();

    return "Dear Valued Clients,

Thank you for your interest in opening a bank account with us. We have received your application. Once the payment has been verified, we will proceed to process your application accordingly.

Best Regards,

<img src=\"" . esc_url($logo_url) . "\" alt=\"Prominence Bank\" style=\"max-width:200px;height:auto;\" />

Accounts Department
____________________________________________
Prominence Bank Corp.
http://www.prominencebank.com
P.B. 1257 Bonovo Road, Fomboni, Mwali, KM
Email: helpdesk@prominencebank.com
Facebook: https://www.facebook.com/ProminenceBank/
Twitter: https://twitter.com/BankProminence";
}

function faap_build_admin_greeting_email($submission) {
    $app_id = sanitize_text_field($submission['applicationId'] ?? 'N/A');
    $type_label = ucwords(sanitize_text_field($submission['type'] ?? 'personal'));
    $logo_url = faap_get_letterhead_logo_url();
    $applicant_name = sanitize_text_field($submission['fullName'] ?? $submission['name'] ?? $submission['entityName'] ?? 'New Applicant');
    
    $greeting = '<div style="font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:#f3f4f6;padding:20px;">
      <div style="max-width:800px;margin:0 auto;background:#fff;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;">
        <div style="background:#d32f2f;color:#fff;padding:16px;text-align:center;">
          <h2 style="margin:0;font-size:22px;font-weight:700;">🔔 NEW APPLICATION RECEIVED</h2>
        </div>
        <div style="background:#f8f9fa;padding:16px;border-bottom:1px solid #e5e7eb;display:flex;justify-content:space-between;align-items:flex-start;gap:12px;">
          <div style="flex:1;min-width:0;display:flex;flex-direction:column;justify-content:flex-start;">
            <div style="font-size:14px;font-weight:700;color:#111827;margin-bottom:4px;">Prominence Bank Corp.</div>
            <div style="font-size:12px;color:#374151;">Email: account@prominencebank.com</div>
            <div style="font-size:12px;color:#374151;">Address: P.B. 1257 Bonovo Road, Fomboni, Mwali, KM</div>
          </div>
          <div style="text-align:right;flex-shrink:0;display:flex;flex-direction:column;align-items:flex-end;justify-content:flex-start;">
            <img src="' . esc_url($logo_url) . '" alt="Prominence Bank" style="max-height:80px;max-width:100px;object-fit:contain;" />
          </div>
        </div>
        <div style="padding:24px;">
          <p style="font-size:14px;color:#111827;margin:0 0 16px 0;line-height:1.6;">
            <strong>Dear Admin,</strong>
          </p>
          <p style="font-size:14px;color:#111827;margin:0 0 16px 0;line-height:1.6;">
            A new application has been received for <strong>' . esc_html($type_label) . '</strong> account opening.
          </p>
          <div style="background:#f0f9ff;border-left:4px solid #2563eb;padding:12px;margin:16px 0;border-radius:4px;">
            <p style="margin:0;font-size:14px;color:#0c4a6e;"><strong>Application Details:</strong></p>
            <p style="margin:4px 0;font-size:13px;color:#0c4a6e;">Application ID: <strong>' . esc_html($app_id) . '</strong></p>
            <p style="margin:4px 0;font-size:13px;color:#0c4a6e;">Type: <strong>' . esc_html($type_label) . '</strong></p>
            <p style="margin:4px 0;font-size:13px;color:#0c4a6e;">Applicant: <strong>' . esc_html($applicant_name) . '</strong></p>
          </div>
          <p style="font-size:14px;color:#111827;margin:16px 0;line-height:1.6;">
            Please review the attached documents and full application details below. The complete application PDF and all supporting documents are attached to this email.
          </p>
          <p style="font-size:12px;color:#6b7280;margin:16px 0;">' . date('Y-m-d H:i:s') . '</p>
        </div>
        <div style="border-top:1px solid #e5e7eb;padding:16px;background:#f9fafb;text-align:center;">
          <img src="' . esc_url($logo_url) . '" alt="Prominence Bank" style="max-width:150px;height:auto;margin-bottom:8px;" />
          <p style="margin:4px 0;font-size:11px;color:#6b7280;">Prominence Bank Corp.</p>
          <p style="margin:4px 0;font-size:10px;color:#9ca3af;">helpdesk@prominencebank.com</p>
        </div>
      </div>
    </div>';
    
    return $greeting;
}

function faap_build_applicant_greeting_email($submission) {
    $app_id = sanitize_text_field($submission['applicationId'] ?? 'N/A');
    $type_label = ucwords(sanitize_text_field($submission['type'] ?? 'personal'));
    $logo_url = faap_get_letterhead_logo_url();
    $applicant_name = sanitize_text_field($submission['fullName'] ?? $submission['firstName'] ?? $submission['name'] ?? 'Valued Client');
    
    $greeting = '<div style="font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:#f3f4f6;padding:20px;">
      <div style="max-width:800px;margin:0 auto;background:#fff;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;">
        <div style="background:#2e7d32;color:#fff;padding:16px;text-align:center;">
          <h2 style="margin:0;font-size:22px;font-weight:700;">✓ APPLICATION CONFIRMED</h2>
        </div>
        <div style="background:#f8f9fa;padding:16px;border-bottom:1px solid #e5e7eb;display:flex;justify-content:space-between;align-items:flex-start;gap:12px;">
          <div style="flex:1;min-width:0;display:flex;flex-direction:column;justify-content:flex-start;">
            <div style="font-size:14px;font-weight:700;color:#111827;margin-bottom:4px;">Prominence Bank Corp.</div>
            <div style="font-size:12px;color:#374151;">Email: account@prominencebank.com</div>
            <div style="font-size:12px;color:#374151;">Address: P.B. 1257 Bonovo Road, Fomboni, Mwali, KM</div>
          </div>
          <div style="text-align:right;flex-shrink:0;display:flex;flex-direction:column;align-items:flex-end;justify-content:flex-start;">
            <img src="' . esc_url($logo_url) . '" alt="Prominence Bank" style="max-height:80px;max-width:100px;object-fit:contain;" />
          </div>
        </div>
        <div style="padding:24px;">
          <p style="font-size:14px;color:#111827;margin:0 0 16px 0;line-height:1.6;">
            <strong>Dear ' . esc_html($applicant_name) . ',</strong>
          </p>
          <p style="font-size:14px;color:#111827;margin:0 0 16px 0;line-height:1.6;">
            Thank you for choosing Prominence Bank! We have successfully received your application for a <strong>' . esc_html($type_label) . '</strong> account.
          </p>
          <div style="background:#f0fdf4;border-left:4px solid #22c55e;padding:12px;margin:16px 0;border-radius:4px;">
            <p style="margin:4px 0;font-size:13px;color:#166534;"><strong>Your Application ID:</strong> ' . esc_html($app_id) . '</p>
            <p style="margin:4px 0;font-size:13px;color:#166534;"><strong>Submitted:</strong> ' . date('Y-m-d H:i:s') . '</p>
          </div>
          <p style="font-size:14px;color:#111827;margin:16px 0;line-height:1.6;">
            <strong>What\'s Next?</strong>
          </p>
          <ol style="margin:0;padding-left:20px;font-size:14px;color:#111827;line-height:1.8;">
            <li>Our team will review your application and supporting documents</li>
            <li>Once payment is verified, we proceed with compliance checks</li>
            <li>You will receive confirmation updates via email</li>
            <li>Your account will be activated upon final approval</li>
          </ol>
          <p style="font-size:14px;color:#111827;margin:24px 0 8px 0;line-height:1.6;">
            <strong>Your complete application details and all submitted information are attached to this email.</strong>
          </p>
          <p style="font-size:12px;color:#6b7280;margin:16px 0;">For questions or support, contact us at helpdesk@prominencebank.com</p>
        </div>
        <div style="border-top:1px solid #e5e7eb;padding:16px;background:#f9fafb;text-align:center;">
          <img src="' . esc_url($logo_url) . '" alt="Prominence Bank" style="max-width:150px;height:auto;margin-bottom:8px;" />
          <p style="margin:4px 0;font-size:11px;color:#6b7280;">Prominence Bank Corp.</p>
          <p style="margin:4px 0;font-size:10px;color:#9ca3af;">Secure Account Applications | Protected by Advanced Encryption</p>
        </div>
      </div>
    </div>';
    
    return $greeting;
}

function faap_generate_application_pdf($submission, $recipient = 'admin') {
    $upload_dir = wp_upload_dir();
    $pdf_path = trailingslashit($upload_dir['path']) . 'faap-app-' . uniqid() . '.pdf';
    $html_file = trailingslashit($upload_dir['path']) . 'faap-app-' . uniqid() . '.html';

    $html_content = faap_build_application_pdf_html($submission, $recipient);
    file_put_contents($html_file, $html_content);

    $wkhtml = trim(shell_exec('which wkhtmltopdf 2>/dev/null'));
    if ($wkhtml) {
        $escaped = escapeshellarg($wkhtml) . ' --enable-local-file-access ' . escapeshellarg($html_file) . ' ' . escapeshellarg($pdf_path) . ' 2>&1';
        $out = shell_exec($escaped);
        if (file_exists($pdf_path) && filesize($pdf_path) > 0) {
            @unlink($html_file);
            return $pdf_path;
        }
    }

    if (function_exists('proc_open')) {
        $cmd = 'wkhtmltopdf --enable-local-file-access ' . escapeshellarg($html_file) . ' ' . escapeshellarg($pdf_path);
        @exec($cmd, $output, $return);
        if ($return === 0 && file_exists($pdf_path) && filesize($pdf_path) > 0) {
            @unlink($html_file);
            return $pdf_path;
        }
    }

    @unlink($html_file);
    return null;
}

function faap_handle_submission($request) {
    global $wpdb;
    $table_apps = $wpdb->prefix . 'faap_submissions';

    $params = $request->get_json_params();
    if (empty($params) && !empty($_POST)) {
        $params = $_POST;
    }
    if (!is_array($params)) {
        $params = [];
    }

    if (isset($params['applicationData']) && is_string($params['applicationData'])) {
        $decoded = json_decode(stripslashes($params['applicationData']), true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $params = array_merge($params, $decoded);
        }
    }

    $params['type'] = in_array($params['type'] ?? 'personal', ['personal', 'business'], true) ? $params['type'] : 'personal';
    $params['accountTypeId'] = sanitize_text_field($params['accountTypeId'] ?? '');
    $params['applicationId'] = sanitize_text_field($params['applicationId'] ?? faap_generate_application_id());
    $params['status'] = 'Pending';

    try {
        if (!empty($_FILES['mainDocumentFile'])) {
            $saved = faap_save_uploaded_file($_FILES['mainDocumentFile'], 'main_document');
            if ($saved) {
                $params['mainDocumentFile'] = $saved;
            }
        }
        if (!empty($_FILES['paymentProofFile'])) {
            $saved = faap_save_uploaded_file($_FILES['paymentProofFile'], 'payment_proof');
            if ($saved) {
                $params['paymentProofFile'] = $saved;
            }
        }
        if (!empty($_FILES['companyRegFile'])) {
            $saved = faap_save_uploaded_file($_FILES['companyRegFile'], 'company_reg');
            if ($saved) {
                $params['companyRegFile'] = $saved;
            }
        }

        $form_data_json = wp_json_encode($params);
        $inserted = $wpdb->insert($table_apps, [
            'type' => $params['type'],
            'account_type_id' => $params['accountTypeId'],
            'status' => 'Pending',
            'ip_address' => sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? 'Unknown'),
            'form_data' => $form_data_json,
        ]);

        if (!$inserted) {
            return new WP_Error('db_err', 'Failed to save application.');
        }

        $application_id = sanitize_text_field($params['applicationId']);
        $user_email = sanitize_email($params['email'] ?? $params['signatoryEmail'] ?? '');
        $admin_email = sanitize_email(get_option('admin_email'));
        $type_label = ucwords(sanitize_text_field($params['type'] ?? 'personal'));

        $admin_template = get_option('faap_admin_subject_template', 'New Form Entry #{{applicationId}} - {{type}}');
        $user_template = get_option('faap_user_subject_template', 'New Form Entry #{{applicationId}} - {{type}}');
        $admin_subject = str_replace(
            ['{{applicationId}}', '{{type}}'], 
            [esc_html($application_id), esc_html($type_label)], 
            esc_html($admin_template)
        );
        $user_subject = str_replace(
            ['{{applicationId}}', '{{type}}'], 
            [esc_html($application_id), esc_html($type_label)], 
            esc_html($user_template)
        );

        $full_body = faap_build_application_html($params);
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        // Build email bodies with greetings only (full form data in PDF attachment)
        $user_greeting = faap_build_applicant_greeting_email($params);
        $admin_greeting = faap_build_admin_greeting_email($params);
        
        // Email body contains only greeting - full form data is in PDF attachment
        $user_body = $user_greeting;
        $admin_body = $admin_greeting;
        $attachments = [];
        if (!empty($params['mainDocumentFile'])) $attachments[] = $params['mainDocumentFile'];
        if (!empty($params['paymentProofFile'])) $attachments[] = $params['paymentProofFile'];
        if (!empty($params['companyRegFile'])) $attachments[] = $params['companyRegFile'];

        // Generate separate PDFs for admin and client with appropriate headers
        $admin_pdf_attachment = faap_generate_application_pdf($params, 'admin');
        $client_pdf_attachment = faap_generate_application_pdf($params, 'applicant');

        if (!empty($user_email)) {
            $client_attachments = $attachments;
            if ($client_pdf_attachment && file_exists($client_pdf_attachment)) {
                $client_attachments[] = $client_pdf_attachment;
            }
            wp_mail($user_email, $user_subject, $user_body, $headers, $client_attachments);
        }

        $admin_attachments = $attachments;
        if ($admin_pdf_attachment && file_exists($admin_pdf_attachment)) {
            $admin_attachments[] = $admin_pdf_attachment;
        }
        wp_mail($admin_email, $admin_subject, $admin_body, $headers, $admin_attachments);

        $applicant_name = $params['fullName'] ?? $params['companyName'] ?? $params['signatoryName'] ?? $params['name'] ?? '';
        return rest_ensure_response([
            'success' => true,
            'id' => $wpdb->insert_id,
            'applicationId' => $application_id,
            'applicantName' => $applicant_name,
            'status' => $params['status'],
        ]);
    } catch (Exception $e) {
        return new WP_Error('submission_error', 'Application submission error: ' . $e->getMessage(), ['status' => 500]);
    }
}

function faap_get_applications() {
    global $wpdb;
    $table_apps = $wpdb->prefix . 'faap_submissions';
    
    $applications = $wpdb->get_results("SELECT * FROM $table_apps ORDER BY submitted_at DESC", ARRAY_A);
    
    // Format the data for the admin dashboard
    $formatted_apps = array_map(function($app) {
        $form_data = json_decode($app['form_data'], true);
        return [
            'id' => $app['id'],
            'type' => $app['type'],
            'accountTypeId' => $app['account_type_id'],
            'status' => $app['status'],
            'submittedAt' => $app['submitted_at'],
            'ipAddress' => $app['ip_address'] ?? 'N/A',
            'applicationId' => $form_data['applicationId'] ?? 'N/A',
            'applicantName' => $form_data['fullName'] ?? $form_data['companyName'] ?? $form_data['signatoryName'] ?? 'N/A',
            'formData' => $form_data
        ];
    }, $applications);
    
    return $formatted_apps;
}

function faap_export_application_pdf($request) {
    global $wpdb;
    $id = intval($request->get_param('id'));
    $table_apps = $wpdb->prefix . 'faap_submissions';
    $app = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_apps WHERE id = %d", $id), ARRAY_A);

    if (!$app) {
        return new WP_Error('not_found', 'Application not found.', ['status' => 404]);
    }

    $form_data = json_decode($app['form_data'], true);
    if (!is_array($form_data)) {
        return new WP_Error('invalid_data', 'Stored application data is invalid.', ['status' => 400]);
    }

    $pdf_path = faap_generate_application_pdf($form_data);
    if (!$pdf_path || !file_exists($pdf_path)) {
        return new WP_Error('pdf_error', 'Unable to generate PDF from application data.', ['status' => 500]);
    }

    $upload_dir = wp_upload_dir();
    $basedir = trailingslashit($upload_dir['basedir']);
    $baseurl = trailingslashit($upload_dir['baseurl']);
    if (strpos($pdf_path, $basedir) === 0) {
        $relative = ltrim(str_replace($basedir, '', $pdf_path), '/\\');
        $pdf_url = $baseurl . str_replace('\\', '/', $relative);
    } else {
        $pdf_url = $pdf_path;
    }

    return rest_ensure_response(['success' => true, 'pdfUrl' => $pdf_url]);
}

function faap_save_form_config($request) {
    $type = sanitize_text_field($request->get_param('type'));
    $config = $request->get_param('config');
    if (!is_string($config)) {
        return new WP_Error('invalid', 'Invalid config');
    }
    $decoded = json_decode($config, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return new WP_Error('invalid', 'Invalid JSON');
    }
    global $wpdb;
    $table = $wpdb->prefix . 'faap_forms';
    $wpdb->replace($table, [
        'form_type' => $type,
        'config' => $config,
    ]);
    return ['success' => true];
}

function faap_get_default_form_steps() {
    return [
        [
            'id' => 'step-1',
            'order' => 1,
            'title' => 'Account Type (Personal Account)',
            'description' => 'Select the account type.',
            'fields' => [
                ['id' => 'f1', 'label' => 'Account Type', 'name' => 'accountType', 'type' => 'select', 'width' => 'full', 'required' => true, 'options' => ['Savings Account', 'Custody Account', 'Numbered Account', 'Cryptocurrency Account']],
            ],
        ],
        [
            'id' => 'step-2',
            'order' => 2,
            'title' => 'Identity (Personal Details)',
            'description' => 'Personal identification information.',
            'fields' => [
                ['id' => 'f2', 'label' => 'First Name', 'name' => 'firstName', 'type' => 'text', 'width' => 'half', 'required' => true],
                ['id' => 'f3', 'label' => 'Last Name', 'name' => 'lastName', 'type' => 'text', 'width' => 'half', 'required' => true],
                ['id' => 'f4', 'label' => 'Middle Name', 'name' => 'middleName', 'type' => 'text', 'width' => 'full', 'required' => false],
                ['id' => 'f5', 'label' => 'Date of Birth (dd-mm-yyyy)', 'name' => 'dob', 'type' => 'date', 'width' => 'half', 'required' => true, 'minAge' => 18, 'validation' => ['notFutureDates' => true]],
                ['id' => 'f6', 'label' => 'Place of Birth', 'name' => 'pob', 'type' => 'text', 'width' => 'half', 'required' => true],
                ['id' => 'f7', 'label' => 'Nationality', 'name' => 'nationality', 'type' => 'text', 'width' => 'half', 'required' => true],
                ['id' => 'f8', 'label' => 'Passport / ID Number', 'name' => 'passportNo', 'type' => 'text', 'width' => 'half', 'required' => true],
                ['id' => 'f9', 'label' => 'Passport Issue Date', 'name' => 'passportIssue', 'type' => 'date', 'width' => 'half', 'required' => true, 'validation' => ['notFutureDates' => true, 'compareField' => ['fieldName' => 'passportExpiry', 'operator' => 'before']]],
                ['id' => 'f10', 'label' => 'Passport Expiry Date', 'name' => 'passportExpiry', 'type' => 'date', 'width' => 'half', 'required' => true, 'validation' => ['notExpiredDates' => true]],
                ['id' => 'f11', 'label' => 'Country of Issue', 'name' => 'passportCountry', 'type' => 'text', 'width' => 'half', 'required' => true],
                ['id' => 'f12', 'label' => 'Telephone / Fax Number', 'name' => 'phone', 'type' => 'text', 'width' => 'half', 'required' => true, 'numericOnly' => true],
            ],
        ],
        [
            'id' => 'step-3',
            'order' => 3,
            'title' => 'Contact Information',
            'description' => 'Contact details.',
            'fields' => [
                ['id' => 'f13', 'label' => 'Home Address', 'name' => 'homeAddress', 'type' => 'text', 'width' => 'full', 'required' => true],
                ['id' => 'f14', 'label' => 'Address Line 2', 'name' => 'addressLine2', 'type' => 'text', 'width' => 'full', 'required' => false],
                ['id' => 'f15', 'label' => 'City / State / Zip Code', 'name' => 'cityStateZip', 'type' => 'text', 'width' => 'full', 'required' => true],
                ['id' => 'f16', 'label' => 'Country', 'name' => 'country', 'type' => 'text', 'width' => 'full', 'required' => true],
                ['id' => 'f17', 'label' => 'Email Address', 'name' => 'email', 'type' => 'email', 'width' => 'half', 'required' => true],
                ['id' => 'f18', 'label' => 'Confirm Email', 'name' => 'emailConfirm', 'type' => 'email', 'width' => 'half', 'required' => true, 'validation' => ['matchField' => 'email']],
                ['id' => 'f19', 'label' => 'Mobile Number', 'name' => 'mobile', 'type' => 'text', 'width' => 'half', 'required' => true, 'numericOnly' => true],
            ],
        ],
        [
            'id' => 'step-4',
            'order' => 4,
            'title' => 'Activity (Expected Transfer Activity)',
            'description' => 'Expected transfer activities.',
            'fields' => [
                ['id' => 'f20', 'label' => 'Main countries to send transfers', 'name' => 'sendCountries', 'type' => 'textarea', 'width' => 'full', 'required' => true],
                ['id' => 'f21', 'label' => 'Main countries to receive transfers', 'name' => 'receiveCountries', 'type' => 'textarea', 'width' => 'full', 'required' => true],
                ['id' => 'f22', 'label' => 'Estimated outgoing transfers per month', 'name' => 'outgoingCount', 'type' => 'number', 'width' => 'half', 'required' => true],
                ['id' => 'f23', 'label' => 'Estimated incoming transfers per month', 'name' => 'incomingCount', 'type' => 'number', 'width' => 'half', 'required' => true],
                ['id' => 'f24', 'label' => 'Average transfer value', 'name' => 'avgValue', 'type' => 'text', 'width' => 'half', 'required' => true, 'numericOnly' => true],
                ['id' => 'f25', 'label' => 'Maximum transfer value', 'name' => 'maxValue', 'type' => 'text', 'width' => 'half', 'required' => true, 'numericOnly' => true],
                ['id' => 'f26', 'label' => 'Initial funding currency', 'name' => 'fundingCurrency', 'type' => 'select', 'width' => 'half', 'required' => true, 'options' => ['EUR', 'USD']],
            ],
        ],
        [
            'id' => 'step-5',
            'order' => 5,
            'title' => 'Wealth (Source of Funds)',
            'description' => 'Source of funds information.',
            'fields' => [
                ['id' => 'f27', 'label' => 'Value of Initial Funding', 'name' => 'fundingValue', 'type' => 'text', 'width' => 'full', 'required' => true, 'numericOnly' => true],
                ['id' => 'f28', 'label' => 'Originating Bank Name', 'name' => 'originatingBankName', 'type' => 'text', 'width' => 'full', 'required' => true],
                ['id' => 'f29', 'label' => 'Bank Address', 'name' => 'originatingBankAddress', 'type' => 'textarea', 'width' => 'full', 'required' => true],
                ['id' => 'f30', 'label' => 'Account Name & Number', 'name' => 'originatingAccount', 'type' => 'text', 'width' => 'full', 'required' => true],
                ['id' => 'f31', 'label' => 'Signatory Name', 'name' => 'originatingSignatory', 'type' => 'text', 'width' => 'full', 'required' => true],
                ['id' => 'f32', 'label' => 'Description of how funds were generated', 'name' => 'fundsDescription', 'type' => 'textarea', 'width' => 'full', 'required' => true],
            ],
        ],
        [
            'id' => 'step-6',
            'order' => 6,
            'title' => 'Banking Details',
            'description' => 'Account banking details.',
            'fields' => [
                ['id' => 'f33', 'label' => 'Account Currency', 'name' => 'accCurrency', 'type' => 'select', 'width' => 'half', 'required' => true, 'options' => ['EUR', 'USD']],
                ['id' => 'f34', 'label' => 'Optional account name (for your reference)', 'name' => 'optionalAccountName', 'type' => 'text', 'width' => 'half', 'required' => false],
            ],
        ],
        [
            'id' => 'step-7',
            'order' => 7,
            'title' => 'Fee Bank / Recommending Bank',
            'description' => 'Recommending bank details.',
            'fields' => [
                ['id' => 'f35', 'label' => 'Bank Name', 'name' => 'feeBankName', 'type' => 'text', 'width' => 'full', 'required' => true],
                ['id' => 'f36', 'label' => 'Bank Address', 'name' => 'feeBankAddress', 'type' => 'textarea', 'width' => 'full', 'required' => true],
                ['id' => 'f37', 'label' => 'SWIFT Code', 'name' => 'feeSwiftCode', 'type' => 'text', 'width' => 'half', 'required' => true],
                ['id' => 'f38', 'label' => 'Account Holder Name', 'name' => 'feeAccountHolder', 'type' => 'text', 'width' => 'half', 'required' => true],
                ['id' => 'f39', 'label' => 'Account Number', 'name' => 'feeAccountNumber', 'type' => 'text', 'width' => 'half', 'required' => true, 'numericOnly' => true],
                ['id' => 'f40', 'label' => 'Account Signatory', 'name' => 'feeAccountSignatory', 'type' => 'text', 'width' => 'half', 'required' => true],
                ['id' => 'f41', 'label' => 'Origin of Deposit Funds', 'name' => 'depositOrigin', 'type' => 'textarea', 'width' => 'full', 'required' => true],
            ],
        ],
        [
            'id' => 'step-8',
            'order' => 8,
            'title' => 'Account Opening Fee & Payment Instructions',
            'description' => 'Fee and payment details.',
            'fields' => [
                ['id' => 'f42', 'label' => 'Payment Method', 'name' => 'paymentMethod', 'type' => 'select', 'width' => 'full', 'required' => true, 'options' => ['SWIFT International Wire', 'Cryptocurrency (USDT TRC20)']],
                ['id' => 'f43', 'label' => 'Passport Photo', 'name' => 'passportPhoto', 'type' => 'file', 'width' => 'full', 'required' => true],
                ['id' => 'f44', 'label' => 'Payment Proof / Transfer Receipt', 'name' => 'paymentProof', 'type' => 'file', 'width' => 'full', 'required' => true],
                ['id' => 'f45', 'label' => 'Full Name / Signature', 'name' => 'fullNameSignature', 'type' => 'text', 'width' => 'full', 'required' => true],
                ['id' => 'f46', 'label' => 'Passport or ID Number', 'name' => 'signatureIdNumber', 'type' => 'text', 'width' => 'half', 'required' => true],
                ['id' => 'f47', 'label' => 'Signature Date', 'name' => 'signatureDate', 'type' => 'date', 'width' => 'half', 'required' => true],
            ],
        ],
    ];
}

function faap_get_default_business_form_steps() {
    return [
        [
            'id' => 'step-1',
            'order' => 1,
            'title' => 'Account Type (Business Account)',
            'description' => 'Select the account type.',
            'fields' => [
                ['id' => 'bf1', 'label' => 'Account Type', 'name' => 'accountType', 'type' => 'select', 'width' => 'full', 'required' => true, 'options' => ['Savings Account', 'Custody Account', 'Numbered Account', 'Cryptocurrency Account']],
            ],
        ],
        [
            'id' => 'b2',
            'order' => 2,
            'title' => 'Company Details',
            'description' => 'Your company details.',
            'fields' => [
                ['id' => 'bf2', 'label' => 'Company name', 'name' => 'entityName', 'type' => 'text', 'width' => 'full', 'required' => true],
                ['id' => 'bf3', 'label' => 'Registered address', 'name' => 'address', 'type' => 'text', 'width' => 'full', 'required' => true],
                ['id' => 'bf4', 'label' => 'Registered address line 2', 'name' => 'address2', 'type' => 'text', 'width' => 'full'],
                ['id' => 'bf5', 'label' => 'City', 'name' => 'city', 'type' => 'text', 'width' => 'half'],
                ['id' => 'bf6', 'label' => 'State', 'name' => 'state', 'type' => 'text', 'width' => 'half'],
                ['id' => 'bf7', 'label' => 'Zip code', 'name' => 'zip', 'type' => 'text', 'width' => 'half'],
                ['id' => 'bf8', 'label' => 'Country', 'name' => 'country', 'type' => 'text', 'width' => 'half', 'required' => true],
                ['id' => 'bf9', 'label' => 'Telephone No.', 'name' => 'phone', 'type' => 'text', 'width' => 'half', 'required' => true, 'numericOnly' => true],
                ['id' => 'bf10', 'label' => 'Company registration No.', 'name' => 'regNumber', 'type' => 'text', 'width' => 'half', 'numericOnly' => true],
                ['id' => 'bf11', 'label' => 'Date of incorporation', 'name' => 'incorporationDate', 'type' => 'date', 'width' => 'half', 'required' => true, 'validation' => ['notFutureDates' => true]],
                ['id' => 'bf12', 'label' => 'Tax ID/VAT Number', 'name' => 'taxId', 'type' => 'text', 'width' => 'half', 'numericOnly' => true],
                ['id' => 'bf13', 'label' => 'Company Website', 'name' => 'website', 'type' => 'text', 'width' => 'half'],
                ['id' => 'bf14', 'label' => 'Company Email', 'name' => 'email', 'type' => 'email', 'width' => 'half', 'required' => true],
                ['id' => 'bf15', 'label' => 'Brief Description of Primary Company Activity', 'name' => 'activityDescription', 'type' => 'textarea', 'width' => 'full', 'required' => true],
            ],
        ],
        [
            'id' => 'b3',
            'order' => 3,
            'title' => 'Activity Profile',
            'description' => 'Expected transaction profile.',
            'fields' => [
                ['id' => 'bf16', 'label' => 'Main countries (To)', 'name' => 'countriesTo', 'type' => 'text', 'width' => 'half', 'required' => true],
                ['id' => 'bf17', 'label' => 'Main countries (From)', 'name' => 'countriesFrom', 'type' => 'text', 'width' => 'half', 'required' => true],
                ['id' => 'bf18', 'label' => 'Estimated Outgoing Transfers / Month', 'name' => 'outgoingCount', 'type' => 'number', 'width' => 'half'],
                ['id' => 'bf19', 'label' => 'Estimated Incoming Transfers / Month', 'name' => 'incomingCount', 'type' => 'number', 'width' => 'half'],
                ['id' => 'bf20', 'label' => 'Average Value for each Transfer', 'name' => 'avgValue', 'type' => 'text', 'width' => 'half', 'numericOnly' => true],
                ['id' => 'bf21', 'label' => 'Maximum Value for each Transfer', 'name' => 'maxValue', 'type' => 'text', 'width' => 'half', 'numericOnly' => true],
            ],
        ],
        [
            'id' => 'b4',
            'order' => 4,
            'title' => 'Authorized Signatory',
            'description' => 'Details of the authorized signatory.',
            'fields' => [
                ['id' => 'bf22', 'label' => 'First Name', 'name' => 'signatoryFirstName', 'type' => 'text', 'width' => 'half', 'required' => true],
                ['id' => 'bf23', 'label' => 'Middle Name', 'name' => 'signatoryMiddleName', 'type' => 'text', 'width' => 'half'],
                ['id' => 'bf24', 'label' => 'Last Name', 'name' => 'signatoryLastName', 'type' => 'text', 'width' => 'half', 'required' => true],
                ['id' => 'bf25', 'label' => 'Address', 'name' => 'signatoryAddress', 'type' => 'text', 'width' => 'full', 'required' => true],
                ['id' => 'bf26', 'label' => 'Address Line 2', 'name' => 'signatoryAddress2', 'type' => 'text', 'width' => 'full'],
                ['id' => 'bf27', 'label' => 'City', 'name' => 'signatoryCity', 'type' => 'text', 'width' => 'half'],
                ['id' => 'bf28', 'label' => 'State', 'name' => 'signatoryState', 'type' => 'text', 'width' => 'half'],
                ['id' => 'bf29', 'label' => 'Zip Code', 'name' => 'signatoryZip', 'type' => 'text', 'width' => 'half'],
                ['id' => 'bf30', 'label' => 'Country', 'name' => 'signatoryCountry', 'type' => 'text', 'width' => 'half', 'required' => true],
                ['id' => 'bf31', 'label' => 'Nationality', 'name' => 'signatoryNationality', 'type' => 'text', 'width' => 'half', 'required' => true],
                ['id' => 'bf32', 'label' => 'Passport/ID No.', 'name' => 'signatoryPassport', 'type' => 'text', 'width' => 'half', 'required' => true],
                ['id' => 'bf33', 'label' => 'Passport Issue Date', 'name' => 'signatoryPassportIssue', 'type' => 'date', 'width' => 'half', 'required' => true, 'validation' => ['notFutureDates' => true, 'compareField' => ['fieldName' => 'signatoryPassportExpiry', 'operator' => 'before']]],
                ['id' => 'bf34', 'label' => 'Passport Expiration Date', 'name' => 'signatoryPassportExpiry', 'type' => 'date', 'width' => 'half', 'required' => true, 'validation' => ['notExpiredDates' => true]],
                ['id' => 'bf35', 'label' => 'Signatory Email', 'name' => 'signatoryEmail', 'type' => 'email', 'width' => 'half', 'required' => true],
                ['id' => 'bf36', 'label' => 'Confirm Signatory Email', 'name' => 'signatoryEmailConfirm', 'type' => 'email', 'width' => 'half', 'required' => true, 'validation' => ['matchField' => 'signatoryEmail']],
                ['id' => 'bf37', 'label' => 'Mobile No.', 'name' => 'signatoryPhone', 'type' => 'text', 'width' => 'half', 'required' => true, 'numericOnly' => true],
                ['id' => 'bf38', 'label' => 'Fax No.', 'name' => 'signatoryFax', 'type' => 'text', 'width' => 'half', 'numericOnly' => true],
            ],
        ],
        [
            'id' => 'b5',
            'order' => 5,
            'title' => 'Directors',
            'description' => 'Company director information.',
            'fields' => [
                ['id' => 'bf39', 'label' => 'Director First Name', 'name' => 'directorFirstName', 'type' => 'text', 'width' => 'half', 'required' => true],
                ['id' => 'bf40', 'label' => 'Director Middle Name', 'name' => 'directorMiddleName', 'type' => 'text', 'width' => 'half'],
                ['id' => 'bf41', 'label' => 'Director Last Name', 'name' => 'directorLastName', 'type' => 'text', 'width' => 'half', 'required' => true],
                ['id' => 'bf42', 'label' => 'Director Nationality', 'name' => 'directorNationality', 'type' => 'text', 'width' => 'half', 'required' => true],
                ['id' => 'bf43', 'label' => 'Passport/ID No.', 'name' => 'directorPassport', 'type' => 'text', 'width' => 'half', 'required' => true],
                ['id' => 'bf44', 'label' => 'Passport Issue Date', 'name' => 'directorPassportIssue', 'type' => 'date', 'width' => 'half', 'required' => true, 'validation' => ['notFutureDates' => true, 'compareField' => ['fieldName' => 'directorPassportExpiry', 'operator' => 'before']]],
                ['id' => 'bf45', 'label' => 'Passport Expiration Date', 'name' => 'directorPassportExpiry', 'type' => 'date', 'width' => 'half', 'required' => true, 'validation' => ['notExpiredDates' => true]],
                ['id' => 'bf46', 'label' => 'Director Address', 'name' => 'directorAddress', 'type' => 'text', 'width' => 'full'],
                ['id' => 'bf47', 'label' => 'Director Email', 'name' => 'directorEmail', 'type' => 'email', 'width' => 'half', 'required' => true],
                ['id' => 'bf48', 'label' => 'Director Phone', 'name' => 'directorPhone', 'type' => 'text', 'width' => 'half', 'required' => true, 'numericOnly' => true],
            ],
        ],
        [
            'id' => 'b6',
            'order' => 6,
            'title' => 'Beneficiaries',
            'description' => 'Ultimate beneficiary information.',
            'fields' => [
                ['id' => 'bf49', 'label' => 'UBO Share (%)', 'name' => 'beneficiaryShare', 'type' => 'number', 'width' => 'half', 'required' => true],
                ['id' => 'bf50', 'label' => 'UBO First Name', 'name' => 'beneficiaryFirstName', 'type' => 'text', 'width' => 'half', 'required' => true],
                ['id' => 'bf51', 'label' => 'UBO Middle Name', 'name' => 'beneficiaryMiddleName', 'type' => 'text', 'width' => 'half'],
                ['id' => 'bf52', 'label' => 'UBO Last Name', 'name' => 'beneficiaryLastName', 'type' => 'text', 'width' => 'half', 'required' => true],
                ['id' => 'bf53', 'label' => 'UBO Nationality', 'name' => 'beneficiaryNationality', 'type' => 'text', 'width' => 'half', 'required' => true],
                ['id' => 'bf54', 'label' => 'UBO Passport No.', 'name' => 'beneficiaryPassport', 'type' => 'text', 'width' => 'half', 'required' => true],
                ['id' => 'bf55', 'label' => 'Passport Issue Date', 'name' => 'beneficiaryPassportIssue', 'type' => 'date', 'width' => 'half', 'required' => true, 'validation' => ['notFutureDates' => true, 'compareField' => ['fieldName' => 'beneficiaryPassportExpiry', 'operator' => 'before']]],
                ['id' => 'bf56', 'label' => 'Passport Expiration Date', 'name' => 'beneficiaryPassportExpiry', 'type' => 'date', 'width' => 'half', 'required' => true, 'validation' => ['notExpiredDates' => true]],
                ['id' => 'bf57', 'label' => 'UBO Address', 'name' => 'beneficiaryAddress', 'type' => 'text', 'width' => 'full'],
                ['id' => 'bf58', 'label' => 'Phone No.', 'name' => 'beneficiaryPhone', 'type' => 'text', 'width' => 'half', 'required' => true, 'numericOnly' => true],
                ['id' => 'bf59', 'label' => 'Email Address', 'name' => 'beneficiaryEmail', 'type' => 'email', 'width' => 'half', 'required' => true],
            ],
        ],
        [
            'id' => 'b7',
            'order' => 7,
            'title' => 'Funding & Account Details',
            'description' => 'Initial corporate funding and account preferences.',
            'fields' => [
                ['id' => 'bf60', 'label' => 'Originating Bank Address', 'name' => 'fundingBankAddr', 'type' => 'text', 'width' => 'full'],
                ['id' => 'bf61', 'label' => 'Value of Initial Funding', 'name' => 'fundingValue', 'type' => 'text', 'width' => 'half', 'numericOnly' => true],
                ['id' => 'bf62', 'label' => 'Funding Currency', 'name' => 'fundingCurrency', 'type' => 'text', 'width' => 'half'],
                ['id' => 'bf63', 'label' => 'Originating Bank Name', 'name' => 'fundingBank', 'type' => 'text', 'width' => 'half'],
                ['id' => 'bf64', 'label' => 'Account Name (at originating bank)', 'name' => 'fundingAccName', 'type' => 'text', 'width' => 'half'],
                ['id' => 'bf65', 'label' => 'Account Number', 'name' => 'fundingAccNo', 'type' => 'text', 'width' => 'half', 'numericOnly' => true],
                ['id' => 'bf66', 'label' => 'Signatory', 'name' => 'fundingSignatory', 'type' => 'text', 'width' => 'half'],
                ['id' => 'bf67', 'label' => 'How were funds generated?', 'name' => 'wealthSource', 'type' => 'textarea', 'width' => 'full', 'required' => true],
                ['id' => 'bf68', 'label' => 'Account Currency (Primary Account)', 'name' => 'accCurrency', 'type' => 'select', 'width' => 'half', 'required' => true, 'options' => ['EUR', 'USD']],
                ['id' => 'bf69', 'label' => 'Account Name Reference (Optional)', 'name' => 'accRef', 'type' => 'text', 'width' => 'half'],
                ['id' => 'bf70', 'label' => 'Recommended By (Referral)', 'name' => 'referral', 'type' => 'text', 'width' => 'half'],
            ],
        ],
        [
            'id' => 'b8',
            'order' => 8,
            'title' => 'Payment',
            'description' => 'Upload required documents and payment proof.',
            'fields' => [
                ['id' => 'bf71', 'label' => 'Payment Proof', 'name' => 'paymentProof', 'type' => 'file', 'width' => 'full', 'required' => true],
                ['id' => 'bf72', 'label' => 'Company Registration Certificate', 'name' => 'companyRegFile', 'type' => 'file', 'width' => 'full', 'required' => true],
            ],
        ],
        [
            'id' => 'b9',
            'order' => 9,
            'title' => 'Review & Attestation',
            'description' => 'Review and confirm your application.',
            'fields' => [
                ['id' => 'bf73', 'label' => 'I Agree', 'name' => 'agree', 'type' => 'radio', 'width' => 'full', 'required' => true, 'options' => ['I Agree']],
                ['id' => 'bf74', 'label' => 'Full name', 'name' => 'fullName', 'type' => 'text', 'width' => 'half', 'required' => true],
                ['id' => 'bf75', 'label' => 'Date', 'name' => 'date', 'type' => 'date', 'width' => 'half', 'required' => true],
                ['id' => 'bf76', 'label' => 'Signature', 'name' => 'signature', 'type' => 'text', 'width' => 'full', 'required' => true],
            ],
        ],
    ];
}

function faap_verify_payment($request) {
    global $wpdb;
    $app_id = $request->get_param('id');
    $table_apps = $wpdb->prefix . 'faap_submissions';
    
    // Update status to verified
    $result = $wpdb->update($table_apps, ['status' => 'Payment Verified'], ['id' => $app_id]);
    
    if ($result) {
        // Get application data for email
        $app = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_apps WHERE id = %d", $app_id), ARRAY_A);
        $form_data = json_decode($app['form_data'], true);
        $application_id = $form_data['applicationId'] ?? 'N/A';
        $user_email = $form_data['email'] ?? $form_data['signatoryEmail'] ?? '';
        
        // Send notification emails
        if (!empty($user_email)) {
            $headers = array('Content-Type: text/html; charset=UTF-8');
            $admin_email = get_option('admin_email');
            
            // Email to user
            $user_subject = "Payment Verified - Application ID: " . $application_id;
            $user_body = '<div style="font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;max-width:700px;margin:0 auto;padding:18px;background:#f9fafb;">
                <div style="background:#0a192f;color:#fff;padding:16px;border-radius:10px 10px 0 0;">
                  <div style="font-weight:800;font-size:18px;">Payment Verified</div>
                  <div style="margin-top:4px;font-size:12px;color:#d1d5db;">Prominence Bank Application</div>
                </div>
                <div style="background:#fff;border:1px solid #e5e7eb;padding:16px;border-radius:0 0 10px 10px;">
                  <p style="margin:0;color:#111827;">Dear Customer,</p>
                  <p style="margin:10px 0 0;color:#374151;">Your payment has been verified for Application ID: <strong>' . esc_html($application_id) . '</strong>.</p>
                  <p style="margin:10px 0 0;color:#374151;">Your account application is now being processed by our team. We will notify you when the next step is complete.</p>
                  <p style="margin:12px 0 0;color:#6b7280;">Thank you,<br>Prominence Bank Team</p>
                </div>
              </div>';
            wp_mail($user_email, $user_subject, $user_body, $headers);

            // Email to admin
            $admin_subject = "PAYMENT VERIFIED | " . $application_id;
            $admin_body = '<div style="font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;max-width:700px;margin:0 auto;padding:18px;background:#f9fafb;">
                <div style="background:#0a192f;color:#fff;padding:16px;border-radius:10px 10px 0 0;">
                  <div style="font-weight:800;font-size:18px;">Payment Verified</div>
                  <div style="margin-top:4px;font-size:12px;color:#d1d5db;">Prominence Bank Admin Alert</div>
                </div>
                <div style="background:#fff;border:1px solid #e5e7eb;padding:16px;border-radius:0 0 10px 10px;">
                  <p style="margin:0;color:#111827;">Payment has been verified for Application ID: <strong>' . esc_html($application_id) . '</strong>.</p>
                  <p style="margin:10px 0 0;color:#374151;">Please continue to process the application in the admin portal.</p>
                </div>
              </div>';
            wp_mail($admin_email, $admin_subject, $admin_body, $headers);
        }
        
        return ['success' => true, 'message' => 'Payment verified successfully'];
    }
    
    return new WP_Error('update_err', 'Failed to verify payment');
}

// 3. Admin Menu
add_action('admin_menu', function() {
    add_menu_page('Financial Portal', 'Financial Portal', 'manage_options', 'faap-admin', 'faap_admin_submissions', 'dashicons-bank', 30);
    add_submenu_page('faap-admin', 'Submissions', 'Submissions', 'manage_options', 'faap-admin', 'faap_admin_submissions');
    add_submenu_page('faap-admin', 'Manage Forms', 'Manage Forms', 'manage_options', 'faap-manage-forms', 'faap_admin_manage_forms');
});

add_action('wp_ajax_faap_export_pdf', 'faap_ajax_export_pdf');
function faap_ajax_export_pdf() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized', '', ['response' => 403]);
    }

    global $wpdb;
    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) {
        wp_die('Invalid application ID', '', ['response' => 400]);
    }

    $table_apps = $wpdb->prefix . 'faap_submissions';
    $app = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_apps WHERE id = %d", $id), ARRAY_A);
    if (!$app) {
        wp_die('Application not found', '', ['response' => 404]);
    }

    $form_data = json_decode($app['form_data'], true);
    if (!is_array($form_data)) {
        wp_die('Invalid application data', '', ['response' => 500]);
    }

    $pdf_path = faap_generate_application_pdf($form_data);
    if (!$pdf_path || !file_exists($pdf_path)) {
        wp_die('Could not generate PDF', '', ['response' => 500]);
    }

    $upload_dir = wp_upload_dir();
    $basedir = trailingslashit($upload_dir['basedir']);
    $baseurl = trailingslashit($upload_dir['baseurl']);
    if (strpos($pdf_path, $basedir) === 0) {
        $relative = ltrim(str_replace($basedir, '', $pdf_path), '/\\');
        $pdf_url = $baseurl . str_replace('\\', '/', $relative);
    } else {
        $pdf_url = $pdf_path;
    }

    wp_redirect($pdf_url);
    exit;
}

function faap_admin_submissions() {
    global $wpdb;
    $table_apps = $wpdb->prefix . 'faap_submissions';
    $rows = $wpdb->get_results("SELECT * FROM $table_apps ORDER BY submitted_at DESC");
    ?>
    <div class="wrap faap-admin">
        <div class="faap-header">
            <h1 style="font-family: 'Alegreya', serif; color: #0a192f; margin: 0;">Application Submissions</h1>
            <p style="color: #666; margin: 5px 0 0;">Manage and review submitted applications</p>
        </div>
        <div class="faap-content">
            <table class="wp-list-table widefat fixed striped faap-table">
                <thead>
                    <tr>
                        <th>Application ID</th>
                        <th>Applicant Name</th>
                        <th>Client IP</th>
                        <th>Type</th>
                        <th>Account Type</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th style="width:190px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($rows): foreach($rows as $row): 
                        $form_data = json_decode($row->form_data, true);
                        $app_id = $form_data['applicationId'] ?? 'N/A';
                        $app_name = $form_data['fullName'] ?? $form_data['companyName'] ?? $form_data['signatoryName'] ?? 'N/A';
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html($app_id); ?></strong></td>
                        <td><?php echo esc_html($app_name); ?></td>
                        <td><?php echo esc_html($row->ip_address ?? 'N/A'); ?></td>
                        <td><span class="faap-badge faap-type"><?php echo strtoupper($row->type); ?></span></td>
                        <td><?php echo esc_html($row->account_type_id); ?></td>
                        <td><span class="faap-status"><?php echo esc_html($row->status); ?></span></td>
                        <td><?php echo esc_html($row->submitted_at); ?></td>
                        <td>
                            <button class="button button-small faap-view-details" type="button" data-details="<?php echo esc_attr(wp_json_encode($form_data)); ?>">View Details</button>
                            <a class="button button-small" href="<?php echo esc_url(admin_url('admin-ajax.php?action=faap_export_pdf&id=' . intval($row->id))); ?>" target="_blank">Export PDF</a>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="7" class="faap-empty">No applications received yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <script>
                document.querySelectorAll('.faap-view-details').forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        var details = btn.getAttribute('data-details');
                        if (!details) return;
                        var parsed;
                        try { parsed = JSON.parse(details); } catch (e) { alert('Invalid application data.'); return; }
                        var lines = [];
                        Object.entries(parsed).forEach(function([k,v]) {
                            if (typeof v === 'object') return;
                            lines.push(k + ': ' + String(v));
                        });
                        if (lines.length === 0) lines.push('No saved details available.');
                        alert(lines.join('\n'));
                    });
                });
            </script>
        </div>
    </div>
    <style>
    .faap-admin { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
    .faap-header { background: linear-gradient(135deg, #0a192f 0%, #1e3a5f 100%); color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
    .faap-content { background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 20px; }
    .faap-table th { background: #f8f9fa; font-weight: 600; color: #0a192f; }
    .faap-badge { background: #0a192f; color: white; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; }
    .faap-status { color: #c29d45; font-weight: bold; }
    .faap-btn { background: #0a192f; color: white; border: none; border-radius: 4px; }
    .faap-btn:hover { background: #1e3a5f; }
    .faap-empty { text-align: center; color: #666; font-style: italic; }
    </style>
    <?php
}

function faap_admin_manage_forms() {
    global $wpdb;
    $table_forms = $wpdb->prefix . 'faap_forms';
    $message = '';
    $message_class = '';

    if (isset($_POST['save_form'])) {
        $config = trim($_POST['form_config']);
        $decoded = json_decode($config, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $wpdb->replace($table_forms, ['form_type' => $_POST['form_type'], 'config' => $config]);
            $message = 'Form configuration updated successfully.';
            $message_class = 'updated';
        } else {
            $message = 'Invalid JSON. Please fix and save again.';
            $message_class = 'error';
        }

        // Save header and email settings
        if (isset($_POST['faap_brand_name'])) {
            update_option('faap_brand_name', sanitize_text_field($_POST['faap_brand_name']));
        }
        if (isset($_POST['faap_brand_email'])) {
            update_option('faap_brand_email', sanitize_email($_POST['faap_brand_email']));
        }
        if (isset($_POST['faap_letterhead_logo_url'])) {
            update_option('faap_letterhead_logo_url', esc_url_raw($_POST['faap_letterhead_logo_url']));
        }
        if (isset($_POST['faap_header_title'])) {
            update_option('faap_header_title', sanitize_text_field($_POST['faap_header_title']));
        }
        if (isset($_POST['faap_admin_subject_template'])) {
            update_option('faap_admin_subject_template', sanitize_text_field($_POST['faap_admin_subject_template']));
        }
        if (isset($_POST['faap_user_subject_template'])) {
            update_option('faap_user_subject_template', sanitize_text_field($_POST['faap_user_subject_template']));
        }
    }

    $personal = $wpdb->get_var($wpdb->prepare("SELECT config FROM $table_forms WHERE form_type = %s", 'personal'));
    $business = $wpdb->get_var($wpdb->prepare("SELECT config FROM $table_forms WHERE form_type = %s", 'business'));

    // Ensure valid JSON for the editor defaults.
    $personalData = json_decode($personal, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($personalData) || count($personalData) === 0) {
        $personalData = faap_get_default_form_steps();
    }
    $businessData = json_decode($business, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($businessData) || count($businessData) === 0) {
        $businessData = faap_get_default_business_form_steps();
    }

    $personalJson = json_encode($personalData, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    $businessJson = json_encode($businessData, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    ?>
    <div class="wrap">
        <h1>Manage Form Steps (Visual Editor)</h1>
        <?php if ($message): ?>
            <div class="<?php echo esc_attr($message_class); ?>"><p><?php echo esc_html($message); ?></p></div>
        <?php endif; ?>
        <p>Use this visual editor to add/remove steps and fields. Click Save to persist changes.</p>

        <?php
            $current_brand_name = esc_attr(get_option('faap_brand_name', 'Prominence Bank Corp.'));
            $current_brand_email = esc_attr(get_option('faap_brand_email', 'account@prominencebank.com'));
            $current_logo_url = esc_url(get_option('faap_letterhead_logo_url', plugins_url('Prominence Bank.png', __FILE__)));
            $current_header_title = esc_attr(get_option('faap_header_title', 'Prominence Bank Application Submission'));
            $current_admin_subject = esc_attr(get_option('faap_admin_subject_template', 'New Form Entry #{{applicationId}} - {{type}}'));
            $current_user_subject = esc_attr(get_option('faap_user_subject_template', 'New Form Entry #{{applicationId}} - {{type}}'));
        ?>

        <div style="margin-bottom:20px;border:1px solid #ddd;padding:14px;border-radius:8px;background:#fcfcfc;">
          <h2 style="margin-top:0;">Header / Email template settings</h2>
          <p style="margin:0 0 10px;color:#555;font-size:14px;">Configure header text, logo URL and email subject templates for outgoing messages and PDF output.</p>
          <form method="post" style="display:grid;grid-template-columns:1fr;gap:10px;">
            <div><label style="font-weight:600;">Brand Name</label><input type="text" name="faap_brand_name" value="<?php echo $current_brand_name; ?>" style="width:100%;padding:8px;margin-top:4px;" /></div>
            <div><label style="font-weight:600;">Brand Email</label><input type="email" name="faap_brand_email" value="<?php echo $current_brand_email; ?>" style="width:100%;padding:8px;margin-top:4px;" /></div>
            <div><label style="font-weight:600;">Letterhead Logo URL</label><input type="text" name="faap_letterhead_logo_url" value="<?php echo $current_logo_url; ?>" style="width:100%;padding:8px;margin-top:4px;" /></div>
            <div><label style="font-weight:600;">Header Title (PDF/Card)</label><input type="text" name="faap_header_title" value="<?php echo $current_header_title; ?>" style="width:100%;padding:8px;margin-top:4px;" /></div>
            <div><label style="font-weight:600;">Admin Email Subject Template</label><input type="text" name="faap_admin_subject_template" value="<?php echo $current_admin_subject; ?>" style="width:100%;padding:8px;margin-top:4px;" /></div>
            <div><label style="font-weight:600;">User Email Subject Template</label><input type="text" name="faap_user_subject_template" value="<?php echo $current_user_subject; ?>" style="width:100%;padding:8px;margin-top:4px;" /></div>
            <div><button type="submit" name="save_form" class="button button-primary">Save Header Settings</button></div>
          </form>
        </div>

        <div style="display:flex;gap:20px;flex-wrap:wrap;">
            <div style="flex:1;min-width:320px;border:1px solid #ccc;padding:12px;border-radius:8px;background:#fff;">
                <h2>Personal Steps</h2>
                <div id="personal-steps" style="margin-bottom:12px;"></div>
                <button id="add-personal-step" class="button button-secondary">+ Add Step</button>
                <form method="post" id="personal-save-form" style="margin-top:12px;">
                    <input type="hidden" name="form_type" value="personal">
                    <input type="hidden" name="form_config" id="personal_form_config">
                    <button type="submit" name="save_form" class="button button-primary">Save Personal</button>
                </form>
            </div>

            <div style="flex:1;min-width:320px;border:1px solid #ccc;padding:12px;border-radius:8px;background:#fff;">
                <h2>Business Steps</h2>
                <div id="business-steps" style="margin-bottom:12px;"></div>
                <button id="add-business-step" class="button button-secondary">+ Add Step</button>
                <form method="post" id="business-save-form" style="margin-top:12px;">
                    <input type="hidden" name="form_type" value="business">
                    <input type="hidden" name="form_config" id="business_form_config">
                    <button type="submit" name="save_form" class="button button-primary">Save Business</button>
                </form>
            </div>
        </div>

        <div style="margin-top:22px;">
            <h3>Raw JSON (for backup)</h3>
            <p style="font-size:12px;color:#555;">The editor stores valid JSON. You can copy this for backup or manual edit.</p>
            <div style="display:flex;gap:20px;flex-wrap:wrap;">
                <textarea id="personal-raw" style="width:100%;min-height:160px;" readonly></textarea>
                <textarea id="business-raw" style="width:100%;min-height:160px;" readonly></textarea>
            </div>
        </div>

        <div style="margin-top:22px;border:1px solid #ccc;padding:12px;border-radius:8px;background:#fff;">
            <h3>How to Use the Form on Your Site</h3>
            <p>Use the shortcode <code>[financial_form]</code> to embed the application form on any page or post.</p>
            <p><strong>Basic Usage:</strong> Add <code>[financial_form]</code> to your page content.</p>
            <p><strong>Custom URL:</strong> If you need to point to a different frontend URL, use <code>[financial_form url="https://your-custom-url.com"]</code>.</p>
            <p>The form will load in an iframe with a height of 1200px. Adjust the height in the shortcode function if needed.</p>
            <p><strong>Note:</strong> Ensure your frontend URL is set correctly in the plugin settings (default: https://form.prominencebank.com/).</p>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
      const personalData = <?php echo json_encode(json_decode($personalJson, true) ?: [], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT); ?>;
      const businessData = <?php echo json_encode(json_decode($businessJson, true) ?: [], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT); ?>;

      function createFieldHtml(stepIndex, fieldIndex, field, baseId) {
        return `
          <div class="faap-field" style="border:1px dashed #d5d5d5; padding:8px; margin-bottom:6px; border-radius:6px; background:#f8f8f8;">
            <div style="display:flex;gap:8px; align-items:center; margin-bottom:4px;">
              <small style="font-weight:bold;">Field ${fieldIndex + 1}</small>
              <button type="button" data-remove-field="${stepIndex}:${fieldIndex}" class="button button-link" style="font-size:11px;">Remove</button>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px; margin-bottom:4px;">
              <input type="text" placeholder="label" data-field-label="${stepIndex}:${fieldIndex}" value="${field.label || ''}" style="width:100%;" />
              <input type="text" placeholder="name" data-field-name="${stepIndex}:${fieldIndex}" value="${field.name || ''}" style="width:100%;" />
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px; margin-bottom:4px;">
              <select data-field-type="${stepIndex}:${fieldIndex}" style="width:100%;">
                <option value="text" ${field.type === 'text' ? 'selected' : ''}>text</option>
                <option value="number" ${field.type === 'number' ? 'selected' : ''}>number</option>
                <option value="date" ${field.type === 'date' ? 'selected' : ''}>date</option>
                <option value="select" ${field.type === 'select' ? 'selected' : ''}>select</option>
                <option value="radio" ${field.type === 'radio' ? 'selected' : ''}>radio</option>
                <option value="textarea" ${field.type === 'textarea' ? 'selected' : ''}>textarea</option>
                <option value="email" ${field.type === 'email' ? 'selected' : ''}>email</option>
                <option value="file" ${field.type === 'file' ? 'selected' : ''}>file</option>
              </select>
              <select data-field-width="${stepIndex}:${fieldIndex}" style="width:100%;">
                <option value="full" ${field.width === 'full' ? 'selected' : ''}>full</option>
                <option value="half" ${field.width === 'half' ? 'selected' : ''}>half</option>
              </select>
            </div>
            <div style="display:flex;gap:8px;">
              <label style="font-size:11px;">required <input type="checkbox" data-field-required="${stepIndex}:${fieldIndex}" ${field.required ? 'checked' : ''} /></label>
            </div>
          </div>
        `;
      }

      function renderEditor(data, containerId) {
        const container = document.getElementById(containerId);
        container.innerHTML = '';

      data.forEach((step, stepIndex) => {
        const stepDiv = document.createElement('div');
        stepDiv.style.border = '1px solid #d2d2d2';
        stepDiv.style.padding = '10px';
        stepDiv.style.marginBottom = '10px';
        stepDiv.style.borderRadius = '8px';
        stepDiv.style.background = '#fefefe';

        const stepHeader = document.createElement('div');
        stepHeader.style.display = 'flex';
        stepHeader.style.justifyContent = 'space-between';
        stepHeader.style.alignItems = 'center';
        stepHeader.style.marginBottom = '8px';

        const stepTitle = document.createElement('strong');
        stepTitle.textContent = `Step ${stepIndex + 1}`;

        const stepControls = document.createElement('div');
        stepControls.style.display = 'flex';
        stepControls.style.gap = '6px';
        stepControls.style.alignItems = 'center';

        const moveUp = document.createElement('button');
        moveUp.type = 'button';
        moveUp.textContent = '↑';
        moveUp.className = 'button button-link';
        moveUp.title = 'Move step up';
        moveUp.disabled = stepIndex === 0;
        moveUp.onclick = () => {
          if (stepIndex === 0) return;
          const prev = data[stepIndex - 1];
          const current = data[stepIndex];
          [data[stepIndex - 1], data[stepIndex]] = [current, prev];
          const tempOrder = prev.order;
          prev.order = current.order;
          current.order = tempOrder;
          renderAll();
        };

        const moveDown = document.createElement('button');
        moveDown.type = 'button';
        moveDown.textContent = '↓';
        moveDown.className = 'button button-link';
        moveDown.title = 'Move step down';
        moveDown.disabled = stepIndex === data.length - 1;
        moveDown.onclick = () => {
          if (stepIndex === data.length - 1) return;
          const next = data[stepIndex + 1];
          const current = data[stepIndex];
          [data[stepIndex + 1], data[stepIndex]] = [current, next];
          const tempOrder = next.order;
          next.order = current.order;
          current.order = tempOrder;
          renderAll();
        };

        const removeStep = document.createElement('button');
        removeStep.type = 'button';
        removeStep.textContent = 'Remove Step';
        removeStep.className = 'button button-link';
        removeStep.onclick = () => {
          data.splice(stepIndex, 1);
          renderAll();
        };

        stepControls.appendChild(moveUp);
        stepControls.appendChild(moveDown);
        stepControls.appendChild(removeStep);

        stepHeader.appendChild(stepTitle);
        stepHeader.appendChild(stepControls);

        const stepFields = document.createElement('div');
        stepFields.style.display = 'grid';
        stepFields.style.gridTemplateColumns = '1fr 1fr';
        stepFields.style.gap = '8px';
        stepFields.style.marginBottom = '8px';

        const idInput = document.createElement('input');
        idInput.type = 'text';
        idInput.value = step.id || `step-${stepIndex + 1}`;
        idInput.placeholder = 'id';
        idInput.onchange = (e) => {
          step.id = e.target.value;
          updateRaw();
        };

        const titleInput = document.createElement('input');
        titleInput.type = 'text';
        titleInput.value = step.title || '';
        titleInput.placeholder = 'title';
        titleInput.onchange = (e) => {
          step.title = e.target.value;
          updateRaw();
        };

        const orderInput = document.createElement('input');
        orderInput.type = 'number';
        orderInput.value = step.order || stepIndex + 1;
        orderInput.placeholder = 'order';
        orderInput.onchange = (e) => {
          step.order = Number(e.target.value);
          updateRaw();
        };

        const descInput = document.createElement('input');
        descInput.type = 'text';
        descInput.value = step.description || '';
        descInput.placeholder = 'description';
        descInput.onchange = (e) => {
          step.description = e.target.value;
          updateRaw();
        };

        stepFields.appendChild(idInput);
        stepFields.appendChild(titleInput);
        stepFields.appendChild(orderInput);
        stepFields.appendChild(descInput);

        const fieldsDiv = document.createElement('div');
        fieldsDiv.style.marginBottom = '8px';
        fieldsDiv.innerHTML = '<strong>Fields</strong>';

        (step.fields || []).forEach((field, fieldIndex) => {
          const fieldHtml = document.createElement('div');
          fieldHtml.innerHTML = createFieldHtml(stepIndex, fieldIndex, field, containerId);
          fieldsDiv.appendChild(fieldHtml);
        });

        const addFieldBtn = document.createElement('button');
        addFieldBtn.type = 'button';
        addFieldBtn.className = 'button button-secondary';
        addFieldBtn.textContent = '+ Add Field';
        addFieldBtn.onclick = () => {
          step.fields = step.fields || [];
          step.fields.push({ id: `f-${Date.now()}`, label: 'New field', name: 'newField', type: 'text', width: 'full', required: false });
          renderAll();
        };

        stepDiv.appendChild(stepHeader);
        stepDiv.appendChild(stepFields);
        stepDiv.appendChild(fieldsDiv);
        stepDiv.appendChild(addFieldBtn);

        container.appendChild(stepDiv);
      });

      Array.from(container.querySelectorAll('input[data-field-label],input[data-field-name],select[data-field-type],select[data-field-width],input[data-field-required]')).forEach((input) => {
        input.onchange = () => {
          const [stepIndex, fieldIndex] = input.dataset.fieldLabel?.split(':') || input.dataset.fieldName?.split(':') || input.dataset.fieldType?.split(':') || input.dataset.fieldWidth?.split(':') || input.dataset.fieldRequired?.split(':');
          const step = data[Number(stepIndex)];
          const field = step?.fields?.[Number(fieldIndex)];
          if (!field) return;

          if (input.dataset.fieldLabel) field.label = input.value;
          if (input.dataset.fieldName) field.name = input.value;
          if (input.dataset.fieldType) field.type = input.value;
          if (input.dataset.fieldWidth) field.width = input.value;
          if (input.dataset.fieldRequired) field.required = input.checked;
          updateRaw();
        };
      });

      Array.from(container.querySelectorAll('[data-remove-field]')).forEach((button) => {
        button.addEventListener('click', () => {
          const [stepIndex, fieldIndex] = button.dataset.removeField.split(':').map(Number);
          data[stepIndex].fields.splice(fieldIndex, 1);
          renderAll();
        });
      });

      updateRaw();
    }

    function sortSteps(steps) {
      return steps.slice().sort((a, b) => (Number(a.order) || 0) - (Number(b.order) || 0));
    }

    function renderAll() {
      personalData.sort((a, b) => (Number(a.order) || 0) - (Number(b.order) || 0));
      businessData.sort((a, b) => (Number(a.order) || 0) - (Number(b.order) || 0));
      renderEditor(personalData, 'personal-steps');
      renderEditor(businessData, 'business-steps');
      updateRaw();
    }

    function updateRaw() {
      const personalRaw = document.getElementById('personal-raw');
      const businessRaw = document.getElementById('business-raw');
      const personalConfig = document.getElementById('personal_form_config');
      const businessConfig = document.getElementById('business_form_config');
      if (personalRaw) personalRaw.value = JSON.stringify(personalData, null, 2);
      if (businessRaw) businessRaw.value = JSON.stringify(businessData, null, 2);
      if (personalConfig) personalConfig.value = JSON.stringify(personalData, null, 2);
      if (businessConfig) businessConfig.value = JSON.stringify(businessData, null, 2);
    }

    document.getElementById('add-personal-step').addEventListener('click', () => {
      personalData.push({ id: `step-${personalData.length + 1}`, order: personalData.length + 1, title: 'New Step', description: '', fields: [] });
      renderAll();
    });

    document.getElementById('add-business-step').addEventListener('click', () => {
      businessData.push({ id: `step-${businessData.length + 1}`, order: businessData.length + 1, title: 'New Step', description: '', fields: [] });
      renderAll();
    });

    document.getElementById('personal-save-form').addEventListener('submit', () => {
      document.getElementById('personal_form_config').value = JSON.stringify(personalData, null, 2);
    });
    document.getElementById('business-save-form').addEventListener('submit', () => {
      document.getElementById('business_form_config').value = JSON.stringify(businessData, null, 2);
    });

    renderAll();
    });
    </script>
    <?php
}

add_shortcode('financial_form', function($atts) {
    $defaultUrl = 'https://form.prominencebank.com/';
    // Accept custom URL via shortcode [financial_form url="..."] for testing.
    $url = isset($atts['url']) ? esc_url_raw($atts['url']) : get_option('faap_frontend_url', $defaultUrl);
    if (empty($url)) {
        $url = $defaultUrl;
    }
    return "<div class='faap-container' style='background:#f4f7f9; padding:10px;'>
        <iframe src='" . esc_url($url) . "' style='width:100%; height:1200px; border:none; box-shadow: 0 10px 30px rgba(0,0,0,0.1);' allow='payment'></iframe>
    </div>";
});
