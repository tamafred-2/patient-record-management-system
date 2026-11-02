# patient-record-management

Opening the application root (`/`) redirects to `/login` instead of the default welcome page. Signed-in users continue to their dashboard through the existing login middleware.

Shared forms use [validation alert modals](docs/features/validation-feedback.md) for failed submissions, with field-level feedback and keyboard dismissal. Login uses simple inline alerts. Forbidden browser page visits return authorized users to their role-filtered dashboard with a warning; see [staff access](docs/features/staff-access.md). Dashboard summary cards appear directly below the heading, before follow-up visits.

Implemented patient registration, validation and duplicate handling are documented in the [patient registry guide](docs/features/patient-registry.md).

Account creation, locked primary roles and permitted additional access are documented in the [staff access guide](docs/features/staff-access.md).

Doctors can prepare editable, printable certificates using the two supplied RHU Calasiao references, with a live landscape preview showing both logos and the Calasiao header. New copies automatically use the signed-in doctor's name and, for examination certificates, the visit's saved assessment measurements, hiding missing values. The preview fits its content, and Prepare for print / PDF opens printable output with browser Save as PDF support. The saved-history list is removed; existing certificate snapshots are retained. See the [medical certificate guide](docs/features/medical-certificates.md).

Visits can be marked **Emergency** or **Priority** (PWD, senior citizen, pregnant, other) and are listed first in every queue; see the [priority lane](docs/features/visits.md#priority-lane-october-25-2026).

Patients aged 60 or older on the visit date default to **Priority / Senior citizen** and can only be saved as Priority or Emergency. Printed stubs show the saved care priority and reason; age alone never selects Emergency.

Staff receive [notifications](docs/features/notifications.md) for the next step in each visit (new visits, emergencies, ready-for-consultation, lab orders and results, issued prescriptions). The bell updates every 30 seconds. Doctors can schedule follow-up return visits that appear on the dashboard when due.

New patient and visit registrations also notify other eligible Admin and Information Staff accounts, with details and links limited by their existing permissions. The person creating the record is excluded; historical records are not backfilled.

Generic [printouts](docs/features/printables.md) are available for issued prescriptions, referral or higher-facility advice, and external laboratory requests. They will be replaced when the RHU's official forms are supplied.

Each patient has a **Documents & printouts** page (patient summary, stub, visit summary, certificates, prescription, referrals, lab slips); see [print center](docs/features/printables.md#print-center-and-patient-summary-october-25-2026). Midwives record vital signs for their own visits, use structured prenatal fields and have a monthly report; see [midwife role](docs/features/midwife-care.md#midwife-role-clarification-october-25-2026-feature-9).

Doctor visits appear after vital signs are recorded. Nurses provide assessment summaries, and doctors can request reassessment through the [nurse assessment workflow](docs/features/initial-assessment.md).

Doctors order one or more laboratory tests per order. Doctors can also turn on standing laboratory ordering for nurses from the **Nurse authorization** page, choosing selected tests or any test. It applies to every patient that nurse assessed until a doctor turns it off. Nurses are notified through a Notifications bell. Each order has a printable lab request slip naming the ordering nurse and the authorizing doctor. See [authorized follow-up tests](docs/features/authorized-followups.md#multi-test-orders-october-25-2026).

Paginated lists now use [scroll queues](docs/features/scroll-queues.md): desktop pages use 90% of the space beside the sidebar; tables fit the available screen height, with 10 records per request, sticky headers and bounded browser rendering.

[Table search and filters](docs/features/scroll-queues.md#table-search-and-filters-october-25-2026) cover User Accounts, Nurse authorization, Services and patient visit history alongside existing clinical queue filters. Smaller read-only tables support independent search by column; printing and exports retain their server-selected contents.

Historical date filters disable future dates and invalid ranges, with matching server validation. See [date-filter rules](docs/features/scroll-queues.md#date-filter-validation-october-25-2026).

Standing nurse laboratory authorization has no expiry or visit-count quota and remains active until a doctor turns it off, subject to existing permissions. The nurse dashboard authorization banner and assessment-page reassessment/authorized-visit count links are hidden; notification links and per-visit actions remain available.

The [MedTech laboratory view](docs/features/laboratory.md) shows direct Laboratory visits and encounters with saved laboratory work. Consultation visits appear only after a laboratory order/record exists; unrelated visits are hidden and their direct laboratory URLs are blocked.

[Report downloads](docs/features/service-reports.md) enforce date-range and register-size limits, with an oversized-preview warning and a summary-only alternative.

Report and visit-summary PDFs also use [export safeguards](docs/features/service-reports.md#export-safeguards): one active renderer, per-user attempt limits, and content-size/PHP-memory checks with clear rejection messages.

SQLite is the selected database for both development and deployment. See [deployment and verified backups](docs/deployment.md); clients access one application host rather than sharing the SQLite file. Daily Windows backup setup and normal update commands are in [local development](docs/local-development.md).

User-approved correction workflows preserve history: Information Staff and doctors with completion access can [reopen a completed visit](docs/features/visit-completion.md) with a reason; pharmacists can [void an incorrect release](docs/features/pharmacy-dispensing.md) and enter corrected quantities. Admin can retire linked accounts or delete unused accounts through [Manage access](docs/features/staff-access.md).

MedTech can [print completed laboratory results](docs/features/laboratory.md#print-saved-results-october-25-2026) from saved values using a generic A4 report. The existing laboratory privacy boundary remains unchanged.

Pharmacists can record a substitute brand or generic actually given and mark remaining quantities as bought outside or declined; see [pharmacy outcomes](docs/features/pharmacy-dispensing.md#substitutes-buying-outside-and-declined-medicines-october-25-2026).

Saved medical certificates have separate **Print** (browser print dialog) and **Download PDF** (server-generated A4 landscape file) actions; see [medical certificates](docs/features/medical-certificates.md#separate-print-and-download-pdf-october-25-2026).

A UI consistency pass (October 25, 2026) moved buttons, back links, multi-line fields, error summaries and the lab request slip onto shared components. Visible differences: button sizes and back-link text are uniform ("Back to …"), and textareas gain the same focus outline and inline field errors as text inputs. Developers should follow [shared UI and query conventions](docs/ui-conventions.md).

Use `php artisan deployment:check` for local runtime, SQLite integrity, storage, migration and asset checks; deployment hosts also use `--production`. A [Windows update script](docs/local-development.md#repeatable-windows-update) stops on failure and preserves maintenance mode after a failed database update. Temporary SQLite contention returns a safe 503 retry response without replaying a write. See [staff acceptance and remaining official inputs](docs/acceptance-and-requirements.md).

New visits automatically use today in Asia/Manila. The visit-date input is hidden and backdated/future registration is rejected; see [visit registration](docs/features/visits.md).
