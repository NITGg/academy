<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * English strings for local_profilefields.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sign-up and profile fields';
$string['managefields'] = 'Sign-up and profile field layout';

// Tabs.
$string['tabregister'] = 'Register page';
$string['tablogin'] = 'Login page';
$string['tabprofile'] = 'Profile page';
$string['tabregister_intro'] = 'Choose which fields a new user fills in when creating an account, in what order, and what each one is called.';
$string['tablogin_intro'] = 'The login page only ever asks for an identifier and a password. These are the few things around it that can be turned on or off.';
$string['tabprofile_intro'] = 'Choose which profile fields a user is allowed to edit themselves. Whether a field appears, is required or must be unique is managed on the sign-up tab (for the register form) and on each field\'s own "Edit profile field" page.';

// Table columns.
$string['colfield'] = 'Field';
$string['colshow'] = 'Show';
$string['colrequired'] = 'Required';
$string['colunique'] = 'Unique';
$string['colcanedit'] = 'User can edit';
$string['colrename'] = 'Label';
$string['renamefield'] = 'Rename';
$string['renameoncore'] = 'Rename on the field page';
$string['fixedbycore'] = 'Fixed by Moodle - not configurable here.';

// Row badges.
$string['badgebuiltin'] = 'Built-in';
$string['badgecustom'] = 'Custom';
$string['badgespecial'] = 'Behaviour';

// Country from phone (task3).
$string['countryfromphone'] = 'Fill Country from the phone field';
$string['countryfromphone_desc'] = 'On the sign-up form, the Country box follows the country chosen in the phone field, so the user picks it once.';

// IP match (task4).
$string['ipmatchheading'] = 'Location check';
$string['ipmatchphone'] = 'Require the sign-up country to match the visitor\'s location';
$string['ipmatchphone_desc'] = 'When on, a new account is only created if the visitor\'s IP address resolves to the same country as the phone number they entered. Users on a VPN or roaming would be blocked, so use with care.';
$string['ipmatchonline'] = 'No setup needed: this uses a free online lookup to find the visitor\'s country. For faster, self-hosted lookups you can install a local GeoIP database in <a href="{$a}">Location &gt; IP address lookup</a>, and it will be used instead.';
$string['ipmatchgeoip'] = 'A local GeoIP database is configured, so it is used for the lookup.';
// AC-4.6.4 fixes this sentence, and adds a rule about what it may not contain:
// "The message does not disclose the country the system detected." Naming the
// detected country would tell someone probing the check exactly which country to
// claim next, which is the circumvention GEO-3 exists to prevent.
$string['ipmismatch'] = 'Registration could not be completed. The country you selected does not match your current location. Please select the country you are registering from, or contact support.';
$string['blockunresolvedip'] = 'Also refuse sign-up when the location cannot be determined';
$string['blockunresolvedip_desc'] = 'Applies while the check above is on. With this on, an address the lookup cannot place in any country is refused instead of let through. Note that a site behind a reverse proxy must have $CFG->getremoteaddrconf set, or every visitor looks like the proxy and nobody can be placed.';
// GEO-5 refuses an address nothing could place "as though the check had failed",
// so it says exactly what a failed check says. Telling the visitor their location
// could not be determined would be more informative and would also be a hint that
// a VPN defeats the check rather than triggering it.
$string['ipunresolved'] = 'Registration could not be completed. The country you selected does not match your current location. Please select the country you are registering from, or contact support.';
$string['ipblocked'] = 'New accounts cannot be created from your current network.';
$string['seereports'] = 'See the refused attempts in Register reports';

// Username.
$string['usernameheading'] = 'Username';
$string['usernamefromemail'] = 'Build the username from the email address (hide the Username box)';
$string['usernamesource'] = 'Username taken from';
$string['usernamesourceemail'] = 'The whole email address';
$string['usernamesourcelocalpart'] = 'The part before the "@"';

// Terms & privacy.
$string['termsheading'] = 'Terms and privacy policy';
$string['termsnative'] = 'Consent to the terms and privacy policy is handled by Moodle\'s built-in Policies tool, which shows the documents to a new user before the account is created. This plugin does not change that behaviour.';
$string['termson'] = 'Policies are active (site policy handler is set to the Policies tool). New users must accept them during sign-up.';
$string['termsoff'] = 'The Policies tool is installed but not selected as the site policy handler, so new users are not yet asked to accept anything.';
$string['termsmanage'] = 'Manage policy documents';
$string['termssettings'] = 'Site policy settings';
$string['termsnotool'] = 'Moodle\'s Policies tool is not installed, so the checkbox will show plain wording with no document links.';
$string['consentenable'] = 'Show an agreement checkbox on the register form';
$string['consentenable_desc'] = 'When on, a required "I agree to the policies" checkbox is added to the sign-up form itself (with the documents linked from it), instead of Moodle\'s separate acceptance page. The documents are still written and versioned in Moodle\'s Policies tool.';
$string['consentlabel'] = 'I agree to the {$a}.';
$string['consentlabelplain'] = 'I agree to the terms of use and the privacy policy.';
$string['consentrequired'] = 'You must agree to the policies before you can create an account.';
$string['and'] = 'and';
$string['termsdocsfound'] = 'The checkbox will link to these policy documents:';
$string['termsdocsnone'] = 'No policy documents for guests are defined yet. Create them (below) and they will be linked from the checkbox automatically; until then the checkbox shows plain wording.';
$string['termsdoubleask'] = 'Moodle\'s Policies tool is also set to ask on a separate page, so users would be asked twice. While the inline checkbox is on, open <a href="{$a}">Users &gt; Privacy and policies &gt; Policy settings</a> and set the "Site policy handler" to "Default (based on the site policy setting)".';
$string['termspolicysettings'] = 'Policy settings';

// Login tab.
$string['loginselfregister'] = 'Allow new users to create their own account';
$string['loginselfregister_desc'] = 'Shows the "Create new account" button on the login page (email-based self registration).';
$string['loginguest'] = 'Show the "Log in as a guest" button';
$string['loginguest_desc'] = 'Lets visitors browse guest-accessible courses without an account.';
$string['loginremember'] = 'Remember the username';
$string['loginremember_desc'] = 'Ticks and pre-fills the username on the login page on the next visit.';

// Provisioning.
$string['provisionheading'] = 'Recommended fields';
$string['provisionintro'] = 'The academy\'s recommended set is not all present yet ({$a} missing). Create the missing fields in one step; existing fields are left untouched.';
$string['provisionbutton'] = 'Create the recommended fields';
$string['provisiondone'] = '{$a} field(s) created.';
$string['provisionallset'] = 'All recommended fields are present.';
$string['provisionnophone'] = 'The Phone field type plugin (profilefield_phone) is not installed, so the phone field cannot be created. Install it, then run this again.';
$string['academycategory'] = 'Additional details';

// Core headings.
$string['corefieldsheading'] = 'Built-in Moodle fields';
$string['customfieldsheading'] = 'Custom profile fields';
$string['optionalcorefields'] = 'Optional section (ID number, institution, department, phone, address)';

// Provisioned field names (Arabic side supplies the translation).
$string['fieldphone'] = 'Phone';
$string['fieldnationality'] = 'Nationality';
$string['fieldgender'] = 'Gender';
$string['fielddateofbirth'] = 'Date of birth';
$string['fieldjobtitle'] = 'Job title';
$string['fieldcompany'] = 'Company';
$string['fieldindustry'] = 'Industry';
$string['fieldeducation'] = 'Education';
$string['fieldnationalid'] = 'National ID';
$string['fieldpassport'] = 'Passport';

$string['privacy:metadata'] = 'The Sign-up and profile fields plugin only stores which fields a site shows. It stores no personal data.';

// Completing a registration that never went through the sign-up form.
$string['completetitle'] = 'Complete your registration';
$string['completeintro'] = 'We just need a couple of details before you carry on.';
$string['completesave'] = 'Save and continue';
$string['completedone'] = 'Thanks — your registration is complete.';
$string['completiongate'] = 'Hold incomplete accounts';
$string['completiongate_desc'] = 'Send any signed-in user who is missing a required sign-up field to a page that collects it. Catches accounts created outside the sign-up form, such as a Google login.';

// Register reports: the refused-attempt log and the IP deny list.
$string['reportstitle'] = 'Register reports';
$string['tabattempts'] = 'Blocked attempts';
$string['tabblacklist'] = 'IP blacklist';
$string['tabattempts_intro'] = 'Every attempt to create an account that the location rules refused: the country the visitor said they were in, the country their address actually resolved to, why they were turned away, and the address itself.';
$string['tabblacklist_intro'] = 'Addresses listed here cannot create an account, whatever country they claim. Existing accounts are not affected - this gates registration only, not logging in or reading the site.';

// Report columns.
$string['colwhen'] = 'When';
$string['colip'] = 'IP address';
$string['coldeclared'] = 'Declared country';
$string['coldetected'] = 'Detected country';
$string['colreason'] = 'Reason';
$string['colorigin'] = 'Came from';
$string['colactions'] = 'Actions';
$string['colnote'] = 'Note';
$string['coladded'] = 'Added';

// Reasons an attempt was refused.
$string['reasonany'] = 'Any reason';
$string['reasonmismatch'] = 'Country mismatch';
$string['reasonunresolved'] = 'Location unknown';
$string['reasonblocked'] = 'Blacklisted IP';

// Which registration page the attempt came from.
$string['originsignup'] = 'Sign-up page';
$string['origincomplete'] = 'Complete registration';
$string['originapp'] = 'Mobile app';

// Deny-list editor.
$string['blockipaddress'] = 'IP address';
$string['blockipaddress_help'] = 'One entry per address. Accepted formats are the same ones Moodle uses elsewhere:

* a single address - `1.2.3.4` or `2001:db8::1`
* a network in CIDR notation - `1.2.3.0/24`
* the start of an address - `1.2.3.`
* a range in the last group - `1.2.3.4-16`';
$string['blockipnote'] = 'Note (optional)';
$string['blockipadd'] = 'Add to the blacklist';
$string['blockipinvalid'] = 'That is not an address, network, address prefix or range this list can match against.';
$string['blockipduplicate'] = 'That entry is already on the blacklist.';
$string['blockipadded'] = 'Added {$a} to the blacklist.';
$string['blockipremoved'] = 'Removed from the blacklist.';
$string['blockipfromreport'] = 'Added from the blocked-attempts report';
$string['blockthisip'] = 'Blacklist this IP';
$string['alreadyblocked'] = 'Blacklisted';
$string['blocklistempty'] = 'The blacklist is empty. Nothing is being refused on the strength of its address alone.';

// Report furniture.
$string['repeatoffenders'] = 'Addresses that keep trying:';
$string['attemptcount'] = '{$a} attempts';
$string['clearlog'] = 'Clear the log';
$string['clearlogconfirm'] = 'Delete every refused attempt on record? The blacklist itself is not touched.';
$string['logcleared'] = '{$a} logged attempt(s) deleted.';
$string['guardoff'] = 'The location check is switched off, so nothing is being refused and no new rows will appear here. Turn it on under <a href="{$a}">Sign-up and profile field layout &gt; Register page</a>.';
$string['guardonstrict'] = 'The location check is on, and visitors whose location cannot be determined are refused as well. Both kinds of refusal are listed here. Settings are on the <a href="{$a}">Register page</a> tab.';
$string['guardonlenient'] = 'The location check is on, but visitors whose location cannot be determined are let through. Only country mismatches and blacklisted addresses are listed here. Settings are on the <a href="{$a}">Register page</a> tab.';

// Privacy.
$string['privacy:metadata:local_profilefields_log'] = 'A record of registration attempts the location rules refused. No account exists for these rows - they are the record of accounts that were never created - so they cannot be linked back to a user.';
$string['privacy:metadata:local_profilefields_log:ip'] = 'The IP address the refused attempt came from.';
$string['privacy:metadata:local_profilefields_log:declared'] = 'The country the attempt claimed to be in.';
$string['privacy:metadata:local_profilefields_log:detected'] = 'The country the IP address was resolved to.';
$string['privacy:metadata:local_profilefields_log:reason'] = 'Why the attempt was refused.';
$string['privacy:metadata:local_profilefields_log:timecreated'] = 'When the attempt was made.';

// -----------------------------------------------------------------------------
// SRS chapter 4 wording.
//
// The specification fixes the exact sentence shown for each failure, in each
// language, and acceptance is tested against that sentence. They are gathered
// here rather than spread across the classes that raise them so that a change
// agreed with EAAC is a one-line edit in two files.
// -----------------------------------------------------------------------------

// AC-4.1.6 / AC-4.4.1 - password complexity, one message per broken rule.
$string['pwtooshort'] = 'Your password must be at least 8 characters long.';
$string['pwnoupper'] = 'Your password must contain at least one uppercase letter.';
$string['pwnolower'] = 'Your password must contain at least one lowercase letter.';
$string['pwnodigit'] = 'Your password must contain at least one number.';

// AC-4.1.15 - field-level validation.
$string['errfirstnameempty'] = 'Please enter your first name.';
$string['errlastnameempty'] = 'Please enter your last name.';
$string['errnamelength'] = 'This name must be between 2 and 50 characters.';
$string['errnamechars'] = 'Only letters, spaces, hyphens and apostrophes are allowed.';
$string['erremailempty'] = 'Please enter your email address.';
$string['erremailformat'] = 'Please enter a valid email address, for example name@example.com.';
$string['errcountryempty'] = 'Please select your country.';
$string['errphoneempty'] = 'Please enter your phone number.';
$string['errphonedigits'] = 'Please enter digits only.';
$string['errtermsempty'] = 'Please accept the Terms and Conditions to continue.';

// AC-4.1.2 - the email is already registered.
$string['emailexistsloginhint'] = 'An account already exists for this email address. {$a}';
$string['emailexistsloginlink'] = 'Log in';

// AC-4.2 - email address verification (link channel).
$string['verifysent'] = 'An email should have been sent to your address at {$a}';
$string['verifysentdetail'] = 'It contains easy instructions to complete your registration.';
$string['verifyresendtoomany'] = 'Too many requests. Please try again in one hour.';
$string['verifylinkexpired'] = 'This confirmation link is no longer valid. Please request a new one.';
$string['verifyalreadydone'] = 'Your account is already confirmed. Please log in.';
$string['verifyresend'] = 'Resend email';
$string['verifyresendwait'] = 'Resend email ({$a}s)';
$string['verifyresent'] = 'A new confirmation email has been sent.';
$string['verifychangeemail'] = 'Change email address';
$string['verifychangeemailsaved'] = 'Your email address has been updated and a new confirmation email sent.';
$string['verifyemailtaken'] = 'An account already exists for this email address.';

// AC-4.3 - login.
$string['loginbadcredentials'] = 'The email address or password is incorrect.';
$string['loginlockedout'] = 'Too many failed attempts. Your account is locked for 15 minutes.';
$string['loginsuspended'] = 'This account has been suspended. Please contact support.';
$string['loginunverified'] = 'Please confirm your email address to continue. We have sent you a new confirmation email.';

// AC-4.4 - password reset.
$string['resetsamepassword'] = 'Please choose a password you have not used before.';
$string['resetuseprovider'] = 'This account signs in with {$a}. Please use that button on the login screen.';
$string['resetdonesubject'] = 'Your password has been changed';
$string['resetdonebody'] = 'Hi {$a->firstname},

The password for your account on {$a->sitename} has just been changed, and every device that was signed in has been signed out.

If this was not you, please contact support immediately.';

// AC-4.5 - profile and account settings.
$string['countryofrecord'] = 'Country of record';
$string['countryofrecordhelp'] = 'This determines the prices you are shown. Only an administrator can change it.';
$string['requestchange'] = 'Request a change';
$string['requestchangeintro'] = 'Tell us what should change and why. An administrator will review your request.';
$string['requestchangesent'] = 'Your request has been sent. An administrator will review it shortly.';
$string['requestchangepending'] = 'You already have a change request awaiting review.';
$string['lockedmessages'] = 'Transactional and security messages cannot be turned off.';
$string['deleteaccount'] = 'Delete my account';
$string['deleteaccountwarning'] = 'Deleting your account removes your access to every course you have purchased and to the certificates you have earned. This cannot be undone.';
$string['deleteaccountconfirm'] = 'Enter your password to confirm';
$string['deleteaccountdone'] = 'Your account has been deleted.';
$string['deleteaccountwrongpassword'] = 'That password is not correct.';

// AC-4.6 - geographic consistency.
$string['ipservicedown'] = 'Registration is temporarily unavailable. Please try again shortly.';
$string['ipallowlist'] = 'Allowed addresses';
$string['ipallowlistintro'] = 'Addresses listed here skip the location check entirely - for the academy\'s own offices and for testing. One entry per row: a single address, a CIDR block, a partial address or a range.';
$string['ipallowlistempty'] = 'No address is exempt from the location check.';
$string['ipallowlistadd'] = 'Exempt an address';
$string['alreadyallowed'] = 'Already exempt';
$string['reasonservicedown'] = 'Geolocation service unavailable';
$string['servicedownalert'] = 'The geolocation service could not be reached, so registration is being refused. Browsing and pricing have fallen back to the default price. Check the network path to the lookup services, or configure a local GeoIP2 database under Location settings.';

// AC-4.3.5 - remember me.
$string['rememberme'] = 'Remember me';
$string['remembermedesc'] = 'Keeps you signed in on this device for 30 days.';
$string['remembermeenabled'] = 'Offer "Remember me" on the login screen';
$string['remembermeenabled_desc'] = 'Adds a "Remember me" checkbox to the login screen. A learner who ticks it stays signed in on that device for the period below, even after the ordinary session has expired. The token is single-use and is replaced on every visit, and it is destroyed on logout, on a password change and when the account is suspended.';
$string['remembermedays'] = 'Remember me for';
$string['remembermedays_desc'] = 'How long a "Remember me" token stays valid. The specification asks for 30 days.';
$string['remembermestolen'] = 'Sign-in on {$a} was refused';
$string['remembermestolenbody'] = 'Hi {$a->firstname},

Someone tried to sign in to your account on {$a->sitename} using an out-of-date "Remember me" token. As a precaution we have signed out every device and you will need to sign in again.

If this was not you, please change your password.';

// Security settings mirrored from core.
$string['securityheading'] = 'Sign-in security';
$string['securityintro'] = 'These settings belong to Moodle itself; they are repeated here so that everything governing the sign-in screen sits in one place. Saving on this page writes straight to the core setting.';
$string['lockoutthreshold'] = 'Failed attempts before lock-out';
$string['lockoutthreshold_desc'] = 'The specification asks for 5. Zero switches lock-out off entirely.';
$string['lockoutduration'] = 'Lock-out lasts';
$string['lockoutduration_desc'] = 'The specification asks for 15 minutes. Moodle emails the account holder an unlock link as soon as the lock is applied.';
$string['sessiontimeoutlabel'] = 'Sign out after inactivity';
$string['sessiontimeout_desc'] = 'The specification asks for 24 hours. This is a site-wide setting and affects every user.';
$string['gatebuttons'] = 'Disable submit buttons until the form is valid';
$string['gatebuttons_desc'] = 'Applies to this academy\'s own screens only - sign-up, login, profile, password reset and checkout. Moodle\'s administrative forms are deliberately left alone, because their fields are often conditional and a gated button would strand the administrator with no explanation.';

// Verification limits.
$string['verifyheading'] = 'Email confirmation';
$string['verifyintro'] = 'How long a confirmation link stays usable, and how often a new one may be asked for.';
$string['linkttl'] = 'Confirmation link expires after';
$string['linkttl_desc'] = 'The specification asks for 24 hours. Requesting a new link always invalidates every link issued before it.';
$string['resendcooldown'] = 'Wait between resends';
$string['resendcooldown_desc'] = 'The specification asks for 60 seconds, shown to the learner as a live countdown.';
$string['resendmax'] = 'Maximum resends per hour';
$string['resendmax_desc'] = 'The specification asks for 5. The sixth request in one hour is refused.';

// Privacy - the tables added for chapter 4.
$string['privacy:metadata:local_profilefields_remember'] = 'The "Remember me" tokens that keep a learner signed in on a device they have chosen to trust.';
$string['privacy:metadata:local_profilefields_remember:userid'] = 'The account the token signs in.';
$string['privacy:metadata:local_profilefields_remember:lastip'] = 'The address the token was last used from.';
$string['privacy:metadata:local_profilefields_remember:useragent'] = 'A hash of the browser the token was issued to, so a token presented by a different browser is refused.';
$string['privacy:metadata:local_profilefields_remember:expires'] = 'When the token stops working.';
$string['privacy:metadata:local_profilefields_remember:timecreated'] = 'When the token was issued.';
$string['privacy:metadata:local_profilefields_request'] = 'Requests to change a profile field the learner may not edit themselves, and the administrator decision on each.';
$string['privacy:metadata:local_profilefields_request:userid'] = 'The learner who asked for the change.';
$string['privacy:metadata:local_profilefields_request:field'] = 'Which field the request is about.';
$string['privacy:metadata:local_profilefields_request:oldvalue'] = 'The value held before the request.';
$string['privacy:metadata:local_profilefields_request:newvalue'] = 'The value the learner asked for.';
$string['privacy:metadata:local_profilefields_request:reason'] = 'The reason the learner gave.';
$string['privacy:metadata:local_profilefields_request:decidedby'] = 'The administrator who approved or refused the request.';
$string['privacy:metadata:local_profilefields_request:decisionnote'] = 'The reason the administrator gave for their decision.';
$string['privacy:metadata:local_profilefields_request:timecreated'] = 'When the request was made.';

$string['taskpurgetokens'] = 'Purge expired remember-me tokens';

// Shown when the server refuses a resend that arrived inside the cooldown -
// normally prevented by the disabled button, so this is the no-JavaScript path.
$string['verifyresendtoosoon'] = 'Please wait {$a} seconds before requesting another email.';

// AC-4.5.7 - account deletion.
$string['deleteaccountword'] = 'DELETE';
$string['deleteaccounttype'] = 'Type {$a} to confirm';
$string['deleteaccountwrongword'] = 'Please type {$a} exactly to confirm.';
$string['deleteaccountrefused'] = 'This account cannot be deleted from here. Please contact support.';
$string['deleteaccountdonesubject'] = 'Your account has been deleted';
$string['deleteaccountdonebody'] = 'Hi {$a->firstname},

Your account on {$a->sitename} has been deleted, as you asked. Your access to the courses you had and to your certificates has ended, and this cannot be undone.

Certificates you had already earned remain verifiable by anyone holding their code.

If you did not ask for this, please contact support immediately.';
