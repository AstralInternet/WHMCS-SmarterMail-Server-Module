<?php
if (!defined('WHMCS')) { die('Accès direct interdit.'); }
/**
 * SmarterMail WHMCS Module — Language File: English
 *
 * Loaded automatically by _sm_lang() based on the client's language or
 * the WHMCS system default language.
 */

$_lang = [

    // ── General ───────────────────────────────────────────────────────────
    'back_dashboard'       => 'Back to Dashboard',
    'back'                 => 'Back',
    'btn_save'             => 'Save',
    'btn_cancel'           => 'Cancel',
    'btn_add'              => 'Add',
    'btn_generate'         => 'Generate',
    'btn_manage'           => 'Manage',
    'btn_delete'           => 'Delete',
    'btn_edit'             => 'Edit',
    'btn_create'           => 'Create',
    'btn_close'            => 'Close',
    'btn_copy'             => 'Copy',
    'required'             => 'Required',
    'na'                   => '—',
    'unlimited'            => 'unlimited',
    'never'                => 'Never',
    'per_month'            => '/month',
    'zero_unlimited'       => '0 = unlimited',

    // ── Service statuses ──────────────────────────────────────────────────
    'status_active'        => 'Active',
    'status_suspended'     => 'Suspended',
    'status_terminated'    => 'Terminated',
    'status_cancelled'     => 'Cancelled',
    'status_pending'       => 'Pending',

    // ── Billing cycles ────────────────────────────────────────────────────
    'cycle_monthly'        => 'Monthly',
    'cycle_quarterly'      => 'Quarterly',
    'cycle_semi_annually'  => 'Semi-Annually',
    'cycle_annually'       => 'Annually',
    'cycle_biennially'     => 'Biennially',
    'cycle_triennially'    => 'Triennially',
    'cycle_free'           => 'Free Account',
    'cycle_one_time'       => 'One Time',

    // ── Client area dashboard (clientarea.tpl) ────────────────────────────
    'dash_storage_title'   => 'Email Storage Usage',
    'dash_storage_used'    => 'Used',
    'dash_storage_billed'  => 'Billed',
    'dash_storage_tiers'   => 'Tiers',
    'dash_storage_go'      => 'GB',
    'dash_stats_title'     => 'Statistics',
    'dash_service_title'   => 'Service Information',
    'dash_dkim_title'      => 'DKIM Key',
    'dash_dkim_active'     => 'Active',
    'dash_dkim_inactive'   => 'Inactive',
    'dash_dkim_view'       => 'View Record to add',

    'stat_email_accounts'  => 'Email Accounts',
    'stat_aliases'         => 'Aliases',
    'stat_eas'             => 'ActiveSync (EAS)',
    'stat_mapi'            => 'MAPI/Exchange',
    'stat_disk'            => 'Storage Used',

    'svc_status'           => 'Status',
    'svc_reg_date'         => 'Since',
    'svc_amount'           => 'Amount',
    'svc_cycle'            => 'Billing Cycle',
    'svc_due_date'         => 'Next Due Date',
    'svc_payment'          => 'Payment Method',
    'svc_estimated'        => 'Monthly Estimate',

    // Email account list
    'list_title'           => 'Email Accounts',
    'list_add_btn'         => 'Add',
    'list_col_email'       => 'Email Address',
    'list_col_alias'       => 'Aliases',
    'list_col_fwd'         => 'Forwarding',
    'list_col_status'      => 'Mailbox',
    'list_search_ph'       => 'Search address, alias, forwarding…',
    'list_per_page'        => '/ page',
    'list_results'         => 'result',
    'list_results_pl'      => 'results',
    'list_accounts'        => 'account',
    'list_accounts_pl'     => 'accounts',
    'list_no_results'      => 'No accounts match your search.',
    'list_empty'           => 'No email addresses for this domain.',
    'list_create_first'    => 'Create First Address',
    'list_sort_asc'        => 'Sort A→Z',
    'list_sort_desc'       => 'Sort Z→A',

    'mbox_active'          => 'Active',
    'mbox_redirect'        => 'No',
    'mbox_disabled'        => 'Disabled',

    // ── DKIM Modal ────────────────────────────────────────────────────────
    'dkim_modal_title'     => 'DKIM Key',
    'dkim_desc'            => 'Add this DNS TXT record at your registrar to authenticate outgoing emails.',
    'dkim_lbl_host'        => 'Record Name (Host / Name)',
    'dkim_lbl_value'       => 'Record Value (TXT Value)',
    'dkim_info'            => 'Type&nbsp;: <strong>TXT</strong>&nbsp;|&nbsp;TTL&nbsp;: <strong>3600</strong>&nbsp;|&nbsp;DNS propagation can take up to 48&nbsp;h.',

    // ── Manage email address (edituser.tpl) ───────────────────────────────
    'eu_usage_title'       => 'Mailbox Usage',
    'eu_protocols'         => 'Protocols',
    'eu_last_login'        => 'Last Login',
    'eu_used_mb'           => 'MB used',
    'eu_allocated_mb'      => 'MB allocated',

    'eu_alias_title'       => 'Aliases for this Address',
    'eu_alias_none'        => 'No aliases for this address.',
    'eu_alias_ph'          => 'aliasname',
    'eu_alias_err_chars'   => 'Invalid characters. Use letters, numbers, . - _',
    'eu_alias_err_dup'     => 'This alias is already in the list.',

    'eu_fwd_title'         => 'Forwarding',
    'eu_fwd_ph'            => 'destination@example.com',
    'eu_fwd_keep'          => 'Keep original sender and recipients when forwarded',
    'eu_fwd_delete'        => 'Delete after forwarding',
    'eu_fwd_err_invalid'   => 'Invalid email address.',
    'eu_fwd_err_dup'       => 'This address is already in the list.',

    'eu_settings_title'    => 'Settings',
    'eu_display_name'      => 'Display Name',
    'eu_disk_limit'        => 'Storage Limit (MB)',
    'eu_eas_label'         => 'ActiveSync (EAS)',
    'eu_mapi_label'        => 'MAPI/Exchange',
    'eu_per_box_month'     => '/mailbox/month',

    'eu_btn_pwd'           => 'Password',
    'eu_btn_save'          => 'Save',
    'eu_btn_delete'        => 'Delete',

    // ── Password modal ────────────────────────────────────────────────────
    'pwd_modal_title'      => 'Change Password',
    'pwd_new_label'        => 'New Password',
    'pwd_confirm_label'    => 'Confirm',
    'pwd_show_hide'        => 'Show/hide',
    'pwd_generate'         => 'Generate',
    'pwd_change_btn'       => 'Change Password',
    'pwd_crit_min'         => 'Minimum %d characters',
    'pwd_crit_upper'       => 'At least one uppercase letter (A-Z)',
    'pwd_crit_number'      => 'At least one number (0-9)',
    'pwd_crit_special'     => 'At least one special character (!@#$...)',
    'pwd_crit_match'       => 'Passwords match',

    // ── Delete address modal ──────────────────────────────────────────────
    'del_modal_title'      => 'Delete Email Address',
    'del_irreversible'     => 'This action is irreversible.',
    'del_all_deleted'      => 'All emails for %s will be permanently deleted from the server.',
    'del_no_recovery'      => 'There is no way to recover this data after deletion.',
    'del_keep_btn'         => 'Cancel — Keep Mailbox',
    'del_confirm_btn'      => 'Permanently Delete',

    // ── Add email address (adduser.tpl) ───────────────────────────────────
    'add_user_title'       => 'New Email Address',
    'add_user_lbl_email'   => 'Email Address',
    'add_user_lbl_name'    => 'Display Name',
    'add_user_lbl_pwd'     => 'Password',
    'add_user_lbl_size'    => 'Storage Limit (MB)',
    'add_user_lbl_fwd'     => 'Auto-Forward (optional)',
    'add_user_lbl_opts'    => 'Additional Options',
    'add_user_lbl_eas'     => 'Enable ActiveSync (EAS)',
    'add_user_lbl_mapi'    => 'Enable MAPI/Exchange',
    'add_user_btn'         => 'Create Email Address',
    'add_user_help_email'  => 'Letters, numbers, dots, dashes and underscores only.',
    'add_user_help_fwd'    => 'If set, received emails will also be forwarded to this address.',
    'add_user_help_eas'    => 'Sync emails, calendars and contacts with mobile devices.',
    'add_user_help_mapi'   => 'Connect via Outlook (Exchange mode) with full synchronization.',

    // ── Manage aliases (managealiases.tpl) ───────────────────────────────
    'al_manage_title'      => 'Email Aliases',
    'al_add_btn'           => 'Add Alias',
    'al_col_alias'         => 'Alias',
    'al_col_dest'          => 'Destinations',
    'al_col_actions'       => 'Actions',
    'al_btn_edit'          => 'Edit',
    'al_btn_delete'        => 'Delete',
    'al_empty'             => 'No aliases for this domain.',
    'al_empty_desc'        => 'An alias redirects emails to one or more destination addresses.',
    'al_create_first'      => 'Create First Alias',
    'al_confirm_delete'    => 'Delete this alias?',

    // ── Add/Edit alias (addalias.tpl / editalias.tpl) ─────────────────────
    'al_add_title'         => 'New Alias',
    'al_edit_title'        => 'Edit Alias',
    'al_desc'              => 'An alias redirects received emails to one or more destination addresses.',
    'al_lbl_name'          => 'Alias Name',
    'al_lbl_display'       => 'Display Name',
    'al_lbl_targets'       => 'Destination Addresses',
    'al_lbl_options'       => 'Options',
    'al_opt_send'          => 'Allow sending from this alias',
    'al_opt_hide_gal'      => 'Hide from Global Address List (GAL)',
    'al_opt_internal'      => 'Accept internal emails only',
    'al_help_targets'      => 'One address per line. Can include external addresses.',
    'al_btn_create'        => 'Create Alias',
    'al_btn_save'          => 'Save',

    // ── Error ─────────────────────────────────────────────────────────────
    'err_title'            => 'Error',
    'err_default'          => 'An unexpected error occurred.',
    'err_connection'       => 'Unable to connect to the SmarterMail server.',

    // ── Password criteria (additions) ────────────────────────────────────
    'pwd_crit_no_user'     => 'Must not contain the username',
    'pwd_crit_no_domain'   => 'Must not contain the domain name',
    'pwd_not_set'          => 'Not set',
    'pwd_btn_define'       => 'Set Password',
    'pwd_btn_apply'        => 'Apply',
    'btn_show_pwd'         => 'Show',

    // ── Aliases / Forwarding ──────────────────────────────────────────────
    'eu_alias_empty'       => 'No aliases',
    'eu_fwd_empty'         => 'No forwarding',
    'eu_btn_add'           => 'Add',

    // ── Dashboard — stats and DNS ─────────────────────────────────────────
    'stat_combined'        => 'EAS + MAPI combined',
    'stat_total_proto'     => 'Total protocols',
    'dash_dns_title'       => 'DNS Records',
    'dash_spf_ok'          => 'Configured',
    'dash_spf_err'         => 'Not configured',
    'dash_spf_view'        => 'View record',
    'dash_spf_add'         => 'How to configure?',
    'spf_mechanism_label'  => 'Required mechanism',
    'dkim_selector_label'  => 'Selector',
    'list_col_size'        => 'Storage',
    'list_col_proto'       => 'Protocols',

    // ── Domain not ready ──────────────────────────────────────────────────
    'domain_not_ready_title' => 'Email Service Not Yet Activated',
    'domain_not_ready_msg'   => 'The domain <strong>{$domain}</strong> is not yet configured on our mail server.<br>This may happen if your service was recently ordered or if manual configuration is required.',
    'domain_not_ready_cta'   => 'Open a Support Ticket',
    'domain_not_ready_sub'   => 'Our team will activate your service as soon as possible.',

    // ── Protocol info modals ──────────────────────────────────────────────
    'info_eas_title'       => 'ActiveSync',
    'info_eas_desc'        => '<strong>ActiveSync (EAS)</strong> syncs your <strong>emails</strong>, <strong>contacts</strong>, <strong>calendars</strong> and <strong>tasks</strong> in real time to mobile devices. Ideal for iPhone and Android.',
    'info_mapi_title'      => 'MAPI / Exchange',
    'info_mapi_desc'       => '<strong>MAPI (Exchange)</strong> is Microsoft Outlook\'s native protocol. Enables <strong>calendar sharing</strong>, <strong>meeting invitations</strong>, <strong>public folders</strong> and full rule synchronization.',
    'btn_close_modal'      => 'Close',

    // ── JS dynamic strings (injected via Smarty into scripts) ────────────
    'js_price_bundle'      => 'Bundle price EAS+MAPI',
    'js_price_saving'      => 'Save',
    'js_price_included'    => 'Included',
    'js_pwd_defined'       => 'Set',
    'js_alias_empty'       => 'No aliases',
    'js_fwd_empty'         => 'No forwarding',


    // ── DKIM modal ────────────────────────────────────────────────────────────
    'dkim_modal_desc_valid'    => 'DKIM record detected and valid in DNS.',
    'dkim_modal_desc_invalid'  => 'DKIM record not found in DNS. Add the records below.',
    'dkim_modal_desc_add'      => 'Add this TXT record in your domain DNS zone to authenticate outgoing emails.',
    'dkim_lbl_host_short'      => 'Host (Name)',
    'dkim_lbl_value_short'     => 'Value (TXT)',
    'dkim_propagation'         => 'After updating DNS, propagation can take up to 24 h.',

    // ── SPF modal ─────────────────────────────────────────────────────────────
    'spf_modal_title'          => 'SPF Record',
    'spf_valid_desc'           => 'Valid SPF record detected in DNS.',
    'spf_current_label'        => 'Current SPF record',
    'spf_missing_desc'         => 'SPF record missing or incomplete.',
    'spf_add_desc'             => 'Add or update the SPF TXT record for your domain to allow our server to send on your behalf.',
    'spf_txt_label'            => 'TXT record to add to your domain',
    'spf_existing_warn'        => 'An SPF record already exists for this domain. Edit it to add the required mechanism.',
    'spf_propagation'          => 'DNS propagation can take up to 24 h.',

    // ── Common buttons (additions) ────────────────────────────────────────────
    'btn_remove_alias'         => 'Remove alias',
    'btn_remove'               => 'Remove',
    'btn_more_info'            => 'Learn more',
    'btn_generate_pwd'         => 'Generate password',

    // ── Placeholders ─────────────────────────────────────────────────────────
    'eu_alias_ph_ex'           => 'e.g.: info',
    'eu_fwd_ph_ex'             => 'e.g.: other@example.com',
    'add_user_ph_username'     => 'username',

    // ── Error label ───────────────────────────────────────────────────────────
    'err_label'                => 'Error:',

    // ── PHP error messages ────────────────────────────────────────────────────
    //
    // Convention: err_* keys are returned by PHP functions and displayed either
    // in the client area (AJAX responses) or the WHMCS admin panel (module
    // functions). All messages must be clear and actionable.
    //
    // sprintf substitution codes used in some keys:
    //   %s → string (domain, API error message, username, etc.)
    //   %d → integer (character count, HTTP code, record count)

    // ── Connection / server ───────────────────────────────────────────────────
    'err_server_connect'       => 'Unable to connect to SmarterMail server. Please check server credentials.',
    'err_server_unreachable'   => 'SmarterMail server unreachable — please check the hostname and port.',
    'err_sa_token_invalid'     => 'SA token invalid or expired.',
    'err_sa_no_impersonate'    => 'SA account does not have impersonation rights.',
    'err_api_http'             => 'API HTTP error %d.',
    // Suffix appended to connection errors requiring admin intervention.
    // Used alongside a specific error: e.g. err_api_http . err_contact_admin
    'err_contact_admin'        => ' Please contact your administrator or open a support ticket.',
    'err_connection_prefix'    => 'Connection error: %s',

    // ── Domains / provisioning ────────────────────────────────────────────────
    'err_domain_not_ready'     => 'This email service is not yet activated. Please open a support ticket.',
    'err_domain_access'        => 'This domain is not yet configured. Please open a support ticket.',
    'err_domain_not_found'     => 'Unable to access domain — please open a support ticket.',
    // %s = domain name — displayed in admin panel during provisioning
    'err_domain_exists'        => 'Error: Domain "%s" already exists in SmarterMail. Create the service manually or contact your administrator.',
    'err_domain_duplicate'     => 'Error: Domain "%s" is already in use in SmarterMail.',
    // %s = API error message returned by SmarterMail
    'err_create_domain'        => 'Error creating domain: %s',
    'err_config_domain'        => 'Error configuring domain (domain deleted): %s',
    'err_suspend'              => 'Error suspending account: %s',
    'err_unsuspend'            => 'Error unsuspending account: %s',
    'err_terminate'            => 'Error terminating account: %s',
    // %s = domain, %d = HTTP status code returned by SmarterMail
    'err_domain_http_access'   => 'Unable to access domain %s (HTTP %d).',

    // ── Users / email accounts ────────────────────────────────────────────────
    'err_user_required'        => 'User not specified or invalid.',
    'err_user_exists'          => 'An email address with this name already exists.',
    // Username format validation (letters, digits, . - _)
    'err_user_invalid_chars'   => 'Invalid username. Allowed characters: letters, digits, . - _ (must start with a letter or digit).',
    'err_admin_user_missing'   => 'Admin username not configured in WHMCS.',
    'err_create_failed'        => 'Unable to create email address. Please try again or open a support ticket.',
    'err_save_failed'          => 'Unable to save changes. Please try again or open a support ticket.',
    'err_delete_failed'        => 'Unable to delete account. Please try again or open a support ticket.',

    // ── Forwarding ────────────────────────────────────────────────────────────
    'err_fwd_invalid'          => 'The forwarding address is not valid.',

    // ── Passwords ─────────────────────────────────────────────────────────────
    'err_pwd_required'         => 'Password is required.',
    'err_new_pwd_empty'        => 'The new password cannot be empty.',
    'err_pwd_too_short'        => 'Password is too short.',
    // %d = minimum length configured in configoption9
    'err_pwd_min_length'       => 'Password must contain at least %d characters.',
    'err_pwd_no_upper'         => 'Password must contain at least one uppercase letter (A-Z).',
    'err_pwd_no_digit'         => 'Password must contain at least one digit (0-9).',
    'err_pwd_no_special'       => 'Password must contain at least one special character (!@#$%^&*-_=+).',
    // %s = username or domain name that must not appear in the password
    'err_pwd_has_user'         => 'Password must not contain the username (%s).',
    'err_pwd_has_domain'       => 'Password must not contain the domain name (%s).',
    'err_pwd_change_failed'    => 'Unable to change password. Please try again or open a support ticket.',
    // API error when changing admin password (WHMCS admin panel) — %s = API message
    'err_pwd_change_api'       => 'Error changing password: %s',

    // ── Billing / sync (admin module commands) ────────────────────────────────
    // These messages are returned by admin module commands and displayed in
    // WHMCS Admin → Services → Module Commands panel.
    'err_billing_period'       => 'Unable to determine billing period (nextduedate missing?).',
    // %s = PHP exception message
    'err_sync'                 => 'Error during synchronisation: %s',
    'msg_cleanup_none'         => 'Cleanup complete — no obsolete records found (no Cancelled/Fraud/Terminated services with tracking data).',
    // %d[1] = records deleted, %d[2] = services affected
    'msg_cleanup_done'         => 'Cleanup complete — %d record(s) deleted for %d service(s) with inactive status.',

    // ── Server connection test (WHMCS Admin "Test Connection" button) ─────────
    //
    // Returned by smartermail_TestConnection() and shown in
    // Administration → Servers → Test Connection. Targets the technical
    // administrator, not the end client.
    //
    // err_test_connection_failed: SA authentication impossible — bad credentials,
    // unreachable server, wrong port, SSL mismatch, etc.
    'err_test_connection_failed' => 'Connection failed: unable to authenticate with the provided credentials. Please verify the username, password, hostname and port.',
    // Labels for the success summary line.
    // Joined into one string: "Version: X.Y — Edition: Pro — 42 active domain(s)"
    // %s = value read from the SmarterMail API
    'lbl_version'                => 'Version: %s',
    'lbl_edition'                => 'Edition: %s',
    // %d = number of active domains reported by SmarterMail
    'lbl_domains_active'         => '%d active domain(s)',

    // ── DKIM toggle (enable/disable from client area) ─────────────────────────
    // These strings are used by the DKIM enable/disable toggle button in the
    // DNS section of the client dashboard. The toggle lets the client activate
    // or deactivate DKIM signing without leaving the page.
    'dkim_toggle_enable'       => 'Enable DKIM',
    'dkim_toggle_disable'      => 'Disable DKIM',
    'dkim_toggle_confirm_disable' => 'Disable DKIM signing for this domain? Outgoing emails will no longer be DKIM-signed.',
    'dkim_toggle_confirm_enable'  => 'Enable DKIM signing for this domain?',
    'dkim_toggle_enabled_badge'   => 'Signing active',
    'dkim_toggle_disabled_badge'  => 'Signing inactive',
    'dkim_status_active'          => 'Active',
    'dkim_status_disabled'        => 'Disabled',
    'dkim_status_standby'         => 'Pending DNS validation',
    'dkim_status_standby_desc'    => 'The DKIM key has been generated and signing is enabled. Add the DNS record below so SmarterMail can validate it.',
    'err_dkim_toggle_failed'      => 'Unable to change DKIM status. Please try again or open a support ticket.',
    'dkim_toggle_invalid_action'  => 'Invalid DKIM action.',

    // ── DKIM Rollover — Fallback when EnableDkim is unavailable ──────────
    // These strings are used when SmarterMail has never had DKIM installed
    // on the domain and CreateDkimRollover is used as a fallback.
    // They appear in admin logs and optionally in client-area notices.
    'dkim_rollover_pending'        => 'DKIM key generated (pending DNS validation). Add the record below to activate signing.',
    'dkim_rollover_activated'      => 'DKIM key activated via key rollover.',
    'err_dkim_rollover_failed'     => 'Unable to generate a DKIM key. Please try again or contact support.',
    'dkim_rollover_notice'         => 'A new DKIM key has been created. Reload this page to see the DNS record to add.',

    // ── DNS Configuration Guide — Tabs ────────────────────────────────────────
    // These strings power the step-by-step DNS guide shown below the DNS status
    // card. The guide explains how to add DKIM and SPF records depending on which
    // control panel or DNS manager the client is using. The default tab is
    // automatically selected based on the domain's nameservers.
    'dns_guide_card_title'     => 'How to configure your DNS records',
    'dns_guide_tab_cpanel'     => 'cPanel',
    'dns_guide_tab_plesk'      => 'Plesk',
    'dns_guide_tab_client'     => 'Client Area',
    'dns_guide_tab_generic'    => 'Other / Generic',
    'dns_guide_main_title'     => 'DNS Configuration – DKIM & SPF',
    'dns_guide_dkim_section'   => 'DKIM Record',
    'dns_guide_spf_section'    => 'SPF Record',
    'dns_guide_spf_exists'     => 'A record already exists:',
    'dns_guide_spf_missing'    => 'No existing SPF record:',
    'dns_guide_spf_no_dup'     => '⚠️ Do not create a second SPF record — only one is allowed per domain.',
    'dns_guide_spf_help'       => '💡 If in doubt, consult your DNS provider\'s documentation or contact their support.',
    'dns_guide_footer'         => '⚠️ DNS changes can take up to <strong>24–48 hours</strong> to propagate.',
    'dns_guide_type_txt'       => 'TXT',
    'dns_guide_field_type'     => 'Type',
    'dns_guide_field_name'     => 'Name',
    'dns_guide_field_record'   => 'Record',
    'dns_guide_field_value'    => 'Value',
    'dns_guide_field_host'     => 'Host / Name',
    'dns_guide_field_content'  => 'Content',
    'dns_guide_field_ttl'      => 'TTL',
    'dns_guide_field_ttl_default' => 'Leave the default value',
    'dns_guide_save'           => 'Save',
    'dns_guide_ok_save'        => 'Click <strong>"Save"</strong>.',
    'dns_guide_with_dot'       => '(with trailing dot)',
    'dns_guide_leave_empty'    => 'leave empty (represents the domain root)',
    'dns_guide_or_leave_empty' => 'or leave <strong>empty</strong> (depending on your provider)',
    'dns_guide_spf_add_before' => 'Add the value below <strong>before</strong> the <code>~all</code> or <code>-all</code> at the end of the line.',
    'dns_guide_spf_full_rec'   => 'Use this complete record as the TXT value:',
    'dns_guide_copy_below'     => 'Copy the value below:',
    'dns_guide_copy_host'      => 'Copy this host name:',
    'dns_guide_copy_value'     => 'Copy this TXT value:',
    'dns_guide_copy_spf_mech'  => 'Add this mechanism before <code>~all</code>:',

    // ── DNS Guide — cPanel specific steps ─────────────────────────────────────
    'dns_cpanel_step1'   => 'Open your <strong>cPanel</strong> and go to <strong>"DNS Zone Editor"</strong>.',
    'dns_cpanel_step2'   => 'Click <strong>"Manage"</strong> next to your domain <strong>{$domain}</strong>.',
    'dns_cpanel_dkim3'   => 'Click <strong>"Add Record"</strong>.',
    'dns_cpanel_dkim4'   => 'In <strong>Type</strong>, select <strong>TXT</strong>.',
    'dns_cpanel_dkim5'   => 'In <strong>Name</strong>, paste:',
    'dns_cpanel_dkim6'   => 'In <strong>Record</strong>, paste:',
    'dns_cpanel_dkim7'   => 'Click <strong>"Save"</strong>.',
    'dns_cpanel_spf8'    => 'In the list, look for a <strong>TXT</strong> record whose value starts with <code>v=spf1</code>.',
    'dns_cpanel_spf_e1'  => 'Click <strong>"Edit"</strong> on that line.',
    'dns_cpanel_spf_e3'  => 'Click <strong>"Save"</strong>.',
    'dns_cpanel_spf_n1'  => 'Click <strong>"Add Record"</strong>.',
    'dns_cpanel_spf_n2'  => 'In <strong>Type</strong>, select <strong>TXT</strong>.',
    'dns_cpanel_spf_n3'  => 'In <strong>Name</strong>, paste: <code>{$domain}.</code> <em>(with the trailing dot)</em>.',
    'dns_cpanel_spf_n4'  => 'In <strong>Record</strong>, paste:',
    'dns_cpanel_spf_n5'  => 'Click <strong>"Save"</strong>.',

    // ── DNS Guide — Plesk specific steps ──────────────────────────────────────
    'dns_plesk_step1'    => 'Log in to your <strong>Plesk</strong> panel and go to <strong>"Websites & Domains"</strong>.',
    'dns_plesk_step2'    => 'Select domain <strong>{$domain}</strong>.',
    'dns_plesk_step3'    => 'Click <strong>"DNS Settings"</strong>.',
    'dns_plesk_dkim4'    => 'Click <strong>"Add Record"</strong>.',
    'dns_plesk_dkim5'    => 'In <strong>Type</strong>, select <strong>TXT</strong>.',
    'dns_plesk_dkim6'    => 'In <strong>Domain Name</strong>, paste:',
    'dns_plesk_dkim7'    => 'In <strong>TXT Value</strong>, paste:',
    'dns_plesk_dkim8'    => 'Click <strong>"OK"</strong>, then <strong>"Update"</strong>.',
    'dns_plesk_spf9'     => 'In the list, look for a <strong>TXT</strong> record whose value starts with <code>v=spf1</code>.',
    'dns_plesk_spf_e1'   => 'Click the corresponding line to <strong>edit</strong> it.',
    'dns_plesk_spf_e3'   => 'Click <strong>"OK"</strong>, then <strong>"Update"</strong>.',
    'dns_plesk_spf_n1'   => 'Click <strong>"Add Record"</strong>.',
    'dns_plesk_spf_n2'   => 'In <strong>Type</strong>, select <strong>TXT</strong>.',
    'dns_plesk_spf_n3'   => 'In <strong>Domain Name</strong>, leave the field <strong>empty</strong> <em>(represents the domain root)</em>.',
    'dns_plesk_spf_n4'   => 'In <strong>TXT Value</strong>, paste:',
    'dns_plesk_spf_n5'   => 'Click <strong>"OK"</strong>, then <strong>"Update"</strong>.',

    // ── DNS Guide — Client Area (Astral Internet) specific steps ──────────────
    'dns_client_step1'   => 'In the <strong>"My Domains"</strong> dropdown menu, select <strong>"My Domain Names"</strong>.',
    'dns_client_step2'   => 'Click <strong>"…"</strong> at the end of the <strong>{$domain}</strong> row and select <strong>"Manage Domain"</strong>.',
    'dns_client_step3'   => 'In the left menu, click <strong>"DNS Zone Management"</strong>.',
    'dns_client_dkim4'   => 'Click <strong>"+ Add record"</strong>.',
    'dns_client_dkim5'   => 'In <strong>Type</strong>, select <strong>TXT</strong>.',
    'dns_client_dkim6'   => 'In <strong>Name</strong>, paste:',
    'dns_client_dkim7'   => 'In <strong>Content</strong>, paste:',
    'dns_client_dkim8'   => 'Click <strong>"Save"</strong>.',
    'dns_client_spf9'    => 'In the list, look for a <strong>TXT</strong> record whose value starts with <code>v=spf1</code>.',
    'dns_client_spf_e1'  => 'Click directly on the <strong>text field</strong> of the SPF record to edit it.',
    'dns_client_spf_e3'  => 'Click outside the field, then click <strong>"Save Changes"</strong> at the bottom of the page.',
    'dns_client_spf_n1'  => 'Click <strong>"+ Add record"</strong>.',
    'dns_client_spf_n2'  => 'In <strong>Type</strong>, select <strong>TXT</strong>.',
    'dns_client_spf_n3'  => 'In <strong>Name</strong>, paste: <code>{$domain}.</code> <em>(with the trailing dot)</em>.',
    'dns_client_spf_n4'  => 'In <strong>Content</strong>, paste:',
    'dns_client_spf_n5'  => 'Click <strong>"Save"</strong>.',

    // ── DNS Guide — Generic / Other provider steps ────────────────────────────
    'dns_generic_step1'  => 'Log in to the management panel of your <strong>DNS provider</strong> <em>(e.g. GoDaddy, Cloudflare, Google Domains, Namecheap, etc.)</em>.',
    'dns_generic_step2'  => 'Go to the <strong>DNS zone management</strong> section for your domain <strong>{$domain}</strong>.',
    'dns_generic_dkim3'  => 'Add a new <strong>TXT</strong> record with the following values:',
    'dns_generic_dkim_host'  => '<strong>Name / Host / Host:</strong>',
    'dns_generic_dkim_val'   => '<strong>Value / Content / Value:</strong>',
    'dns_generic_dkim_ttl'   => '<strong>TTL:</strong> Leave the default value.',
    'dns_generic_dkim4'  => 'Save the record.',
    'dns_generic_spf5'   => 'In the list, look for a <strong>TXT</strong> record whose value starts with <code>v=spf1</code>.',
    'dns_generic_spf_e1' => 'Edit that existing record.',
    'dns_generic_spf_e3' => 'Save the record.',
    'dns_generic_spf_n1' => 'Add a new <strong>TXT</strong> record with the following values:',
    'dns_generic_spf_n_name' => '<strong>Name / Host / Host:</strong> <code>{$domain}.</code> or leave <strong>empty</strong> <em>(depending on your provider)</em>.',
    'dns_generic_spf_n_val'  => '<strong>Value / Content / Value:</strong>',
    'dns_generic_spf_n_ttl'  => '<strong>TTL:</strong> Leave the default value.',
    'dns_generic_spf_n4' => 'Save the record.',
    // ── EAS/MAPI usage-based billing — Client area ───────────────────────────
    // Notice displayed below EAS/MAPI checkboxes in add/edit address pages.
    // {days} is replaced dynamically with the configoption16 value.
    'proto_billing_notice'         => 'If EAS or MAPI is active, it will be billed for the current month. If deactivated within {days} day(s), no charge applies.',

    // Billing detail popup (i button next to the amount)
    'proto_billing_detail_title'   => 'Billing Detail',
    'proto_billing_period'         => 'Period: ',
    'proto_billing_storage'        => 'Storage',
    'proto_billing_active_from'    => 'Active from ',
    'proto_billing_active_to'      => ' to ',
    'proto_billing_total'          => 'Estimated total',
    'proto_billing_live_note'      => 'Detail will be available after the first EAS or MAPI activation with usage tracking enabled.',
    'proto_billing_threshold_note' => 'EAS and MAPI → If deactivated after {days} day(s) → it will be billed with the deactivation date on the next invoice.',
    // ── Protocol states in billing popup ─────────────────────────────────────
    'proto_status_grace'           => 'Grace period in progress',
    'proto_billing_deleted_on'     => 'Deactivated on ',

    // ── Invoice line descriptions (hooks.php — InvoiceCreation hook) ─────────
    //
    // These strings appear directly on customer invoices and must be clear
    // and professional. They are generated by the InvoiceCreation hook and
    // stored as plain text in tblinvoiceitems.description.
    //
    // Placeholders:
    //   inv_usage_label   : %1$s = usage in GB (formatted), %2$d = tier count,
    //                       %3$d = GB per tier, %4$s = unit price
    //   inv_disabled_on   : %s = date formatted as d-M-y (e.g. 15-Jan-26)
    //   inv_entry_prefix  : bullet prefix before each email address line
    //   inv_combined_hdr  : header for combined EAS + MAPI invoice lines
    //   inv_eas_hdr       : header for ActiveSync (EAS)-only invoice lines
    //   inv_mapi_hdr      : header for MAPI/Exchange-only invoice lines
    'inv_usage_label'  => '%1$s GB used · %2$d Tier(s) of %3$d GB × $%4$s',
    'inv_disabled_on'  => '(Disabled on %s)',
    'inv_entry_prefix' => '- ',
    'inv_combined_hdr' => 'EAS + MAPI/Exchange:',
    'inv_eas_hdr'      => 'ActiveSync (EAS):',
    'inv_mapi_hdr'     => 'MAPI/Exchange:',


    // ── Standalone Redirects (aliases without a mailbox) ─────────────────────
    //
    // These strings support addredirect.tpl, editredirect.tpl and the account
    // list in clientarea.tpl.
    //
    // CONVENTION:
    //   add_redirect_*  → creation form
    //   edit_redirect_* → edit / delete form
    //   list_*          → dashboard (account list)
    //   err_redirect_*  → PHP error messages

    // ── List buttons ──────────────────────────────────────────────────────────
    'list_add_email_btn'    => '+ Email',           // Replaces old '+ Add'
    'list_add_redirect_btn' => '+ Redirect',        // New button

    // ── Type column in account list ───────────────────────────────────────────
    'list_col_type'      => 'Type',
    'list_type_mailbox'  => 'Email mailbox',        // fa-inbox icon (blue)
    'list_type_redirect' => 'Standalone redirect',  // fa-share icon (green)

    // ── Add redirect form ─────────────────────────────────────────────────────
    'add_redirect_title'         => 'New Redirect',
    // Sub-title in the green header
    'add_redirect_subtitle'      => 'A redirect automatically forwards incoming email to another address, without creating a mailbox.',
    // Source address section
    'add_redirect_source_title'  => 'Source Address',
    'add_redirect_source_label'  => 'Redirect Name',
    'add_redirect_source_ph'     => 'e.g. bob',
    'add_redirect_source_help'   => 'Letters, numbers, dots, hyphens and underscores only.',
    // Info banner
    'add_redirect_info'          => 'Email sent to this address will be automatically forwarded to the destinations below. No mailbox will be created.',
    // Destinations section
    'add_redirect_dest_title'    => 'Destination Addresses',
    'add_redirect_dest_empty'    => 'No destination — at least one is required.',
    'add_redirect_dest_add'      => 'Add a destination',
    'add_redirect_btn_create'    => 'Create Redirect',
    // Add destination modal
    'add_redirect_modal_title'   => 'Add a destination',
    'add_redirect_modal_label'   => 'Destination address',
    'add_redirect_modal_ph'      => 'e.g. bob@gmail.com',

    // ── Edit redirect form ────────────────────────────────────────────────────
    'edit_redirect_title'        => 'Edit Redirect',
    'edit_redirect_subtitle'     => 'Update the addresses this redirect forwards email to.',
    // Note that source address cannot be renamed
    'edit_redirect_source_hint'  => 'The source address cannot be changed. To rename it, delete this redirect and create a new one.',
    'edit_redirect_btn_delete'   => 'Delete Redirect',
    // Delete confirmation modal
    'edit_redirect_del_title'    => 'Delete Redirect',
    'edit_redirect_del_confirm'  => 'Permanently delete the following redirect?',
    'edit_redirect_del_irreversible' => 'This action cannot be undone.',
    'edit_redirect_del_confirm_btn'  => 'Delete Permanently',

    // ── Error messages ────────────────────────────────────────────────────────
    'err_redirect_invalid_name'       => 'Invalid redirect name. Letters, numbers, . - _ only (must start with a letter or digit).',
    'err_redirect_target_required'    => 'At least one destination address is required.',
    // %s = invalid address (htmlspecialchars applied in PHP)
    'err_redirect_invalid_target'     => 'Invalid destination address: %s',
    // JS version (no %s — displayed by JS before form submission)
    'err_redirect_invalid_target_js'  => 'Invalid email address.',
    'err_redirect_dup_target'         => 'This address is already in the list.',
    'err_redirect_conflicts_mailbox'  => 'This name is already used by an existing email mailbox.',
    'err_redirect_alias_exists'       => 'A redirect or alias with this name already exists.',

];
