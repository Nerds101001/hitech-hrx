# Cliniko — Complete Feature Analysis & Build Blueprint

**Purpose:** exhaustive inventory of everything Cliniko does, so it can be rebuilt in-house without
losing anything staff already rely on.

**Date of analysis:** 4 August 2026
**Sources:** cliniko.com feature pages, help.cliniko.com collection index, and the public
Cliniko API v1 spec (`docs.api.cliniko.com`). The API spec is the most reliable source — it exposes
the real data model, field names, types and enum values.

**Legend used throughout:**
- ✅ = confirmed from Cliniko's own docs/API
- ⚠️ = inferred from behaviour/help-centre titles, verify against your live account before building
- 🇦🇺 = Australia-specific, needs localisation for India
- 🔴 = hard to replicate / highest risk

---

## 0. Read this first — strategy

### 0.1 What you're actually replacing

Cliniko is not one product. It is six products welded together:

| Sub-product | Roughly equivalent to |
|---|---|
| Scheduling engine | Calendly + Google Calendar |
| Clinical records (EMR) | A form builder + document store with medico-legal locking |
| Billing | A small invoicing/AR system with third-party payers |
| Patient communications | Twilio + Mailchimp + a template engine |
| Telehealth | A WebRTC video product |
| Reporting | A BI layer over all of the above |

Rebuilding all six at once is how these projects die. Section 9 has a phasing plan.

### 0.2 The "staff already know it" constraint

Since your team is trained on Cliniko, **UI parity matters more than feature parity.**
Concretely, keep these identical or near-identical:

- Terminology: "patient" (configurable to client), "practitioner", "business" (= location),
  "appointment type", "treatment note", "recall", "wait list", "account credit"
- The calendar as the home screen, with practitioner columns
- The patient file layout: left = demographics/alerts, right = tabbed history
  (Appointments / Treatment notes / Invoices / Files / Communications / Forms)
- Colour-coded appointment types
- "Mark as arrived" / "Did not arrive" on the appointment popover
- Keyboard shortcuts on the calendar

If you deviate on these, retraining cost eats the savings from dropping the subscription.

### 0.3 Pricing you're benchmarking against ✅

Flat per-practitioner-band pricing, all features in every tier:

| Practitioners | USD/month |
|---|---|
| 1 | $45 |
| 2–5 | $95 |
| 6–8 | $145 |
| 9–12 | $195 |
| 13–25 | $295 |
| 26–200 | $395 |

Unlimited admin/reception users, locations, storage, patients. Only paid add-on is SMS at
10¢/message. Annual billing = 1 free month.

**Your real cost to beat** = subscription + SMS credits. Your build cost must be amortised against
that; also weigh hosting, backups, on-call, and the compliance work in Section 8.

---

## 1. Core domain model

Taken from the API's 44 resources ✅. This is your table list, near enough one-to-one.

### 1.1 Entity map

```
Account (tenant)
├── Business (location)          ── many
│   └── DailyAvailability        ── practitioner × business × weekday
├── User                         ── login, has role
│   └── Practitioner             ── clinical identity of a user
│       ├── PractitionerReferenceNumber   (provider numbers) 🇦🇺
│       ├── AppointmentType      ── many-to-many
│       └── AvailabilityBlock / UnavailableBlock
├── Patient
│   ├── PatientPhoneNumber       ── many
│   ├── MedicalAlert             ── many
│   ├── Relationship             ── patient ↔ patient / contact
│   ├── PatientAttachment        ── files
│   ├── PatientCase              ── episode of care / referral / claim
│   ├── Recall                   ── follow-up reminder
│   ├── Communication            ── SMS / email / phone / memo log
│   ├── PatientForm              ── instance of PatientFormTemplate
│   └── CustomField values       ── per-section, per-account defined
├── Appointment
│   ├── IndividualAppointment
│   └── GroupAppointment → Attendee (per patient)
│       └── TreatmentNote        ── from TreatmentNoteTemplate
├── Invoice → InvoiceItem → (BillableItem | Product)
│   ├── Payment ⚠️ (not exposed on public API but exists in product)
│   ├── Tax
│   ├── ConcessionType → ConcessionPrice
│   └── Signature
├── Product → ProductSupplier, StockAdjustment
├── Contact                      ── doctors, insurers, payers
├── ReferralSource → ReferralSourceType
└── Settings / PublicSettings
```

### 1.2 Full resource list from the API ✅

All 44, verbatim — treat as your minimum table/module checklist:

1. Appointment Type
2. Appointment Type Billable Item
3. Appointment Type Product
4. Attendee
5. Availability Block
6. Available Time
7. Billable Item
8. Booking
9. Business
10. Communication
11. Concession Price
12. Concession Type
13. Contact
14. Daily Availability
15. Group Appointment
16. Individual Appointment
17. Invoice
18. Invoice Item
19. Medical Alert
20. Patient
21. Patient Attachment
22. Patient Case
23. Patient Form
24. Patient Form Template
25. Practitioner
26. Practitioner Reference Number
27. Product
28. Product Supplier
29. Public Settings
30. Recall
31. Recall Type
32. Referral Source
33. Referral Source Type
34. Relationship
35. Service
36. Settings
37. Signature
38. Stock Adjustment
39. Tax
40. Treatment Note
41. Treatment Note Template
42. Unavailable Block
43. Unavailable Block Type
44. User

---

## 2. Field-level schemas (the parts Cliniko publishes)

### 2.1 Patient ✅

| Group | Fields |
|---|---|
| Identity | `id`, `first_name`, `last_name`, `preferred_first_name`, `title`, `label` (computed display name) |
| Contact | `email`, `patient_phone_numbers[]` (`number`, `normalized_number`, `phone_type`), `address_1/2/3`, `city`, `state`, `post_code`, `country_code` (ISO 3166-1), `country` *(deprecated)* |
| Demographics | `date_of_birth`, `sex`, `gender` *(deprecated)*, `gender_identity`, `pronouns{nominative, accusative, reflexive, pronominal_possessive, predicative_possessive}`, `occupation` |
| Health IDs | `medicare`, `medicare_reference_number`, `dva_card_number` 🇦🇺 |
| Clinical | `medical_alerts`, `appointment_notes` (shows on every booking), `notes` |
| Emergency/referral | `emergency_contact`, `referring_doctor` → Contact, `referral_source` |
| Consent | `accepted_privacy_policy`, `accepted_email_marketing`, `accepted_sms_marketing` |
| Comms prefs | `receives_confirmation_emails`, `receives_cancellation_emails`, `reminders_communication_channels[]` (1=SMS, 2=Email), `follow_ups_communication_channels[]`, plus `_description` string versions |
| Billing | `concession_type`, `invoice_default_to`, `invoice_email`, `invoice_extra_information` |
| Lifecycle | `created_at`, `updated_at`, `archived_at`, `merged_at`, `merged_with_patient`, `deleted_at` *(deprecated)* |
| Misc | `old_reference_id` (migration key — **build this, you'll need it**), `custom_fields{}` |

Note the design decisions worth copying:
- **Archive, never delete.** Every entity has `archived_at`. Deletion is a separate, rarer operation.
- **Patient merge is first-class** (`merged_with_patient`, `merged_at`), not a manual cleanup script.
- **Pronouns are a structured object**, not a free string.
- `old_reference_id` exists purely to make migration-in from another system reversible.

### 2.2 Individual Appointment ✅

| Group | Fields |
|---|---|
| Links | `appointment_type`, `business`, `patient`, `patient_case`, `practitioner` |
| Scheduling | `starts_at`, `ends_at` (defaults from appointment type duration if null) |
| Recurrence | `repeat_rule{number_of_repeats, repeat_type, repeating_interval}`, `repeated_from`, `repeats` |
| Cancellation | `cancelled_at`, `cancellation_reason` (int), `cancellation_reason_description` — enum: *Feeling Better, Condition Worse, Sick, COVID-19 related, Away, Work, Other* — `cancellation_note` |
| Attendance | `patient_arrived` (bool), `did_not_arrive` (bool) |
| Telehealth | `telehealth_url` |
| Reminders | `email_reminder_sent`, `sms_reminder_sent` |
| Online booking audit | `booking_ip_address` (IPv4), `online_booking_policy_accepted` |
| Denormalised/status | `patient_name`, `has_patient_appointment_notes`, `invoice_status` (int), `treatment_note_status` (int), `conflicts` |
| Lifecycle | `created_at`, `updated_at`, `archived_at`, `deleted_at` |

The `invoice_status` / `treatment_note_status` denormalised columns are what drive the calendar's
"$" and "note" badges without N+1 queries. Copy that.

### 2.3 Group Appointment + Attendee ✅

Group appointment: as above plus `max_attendees` (1–100, defaults from appointment type),
`patient_ids[]`, `attendees` collection.

**Key modelling point:** for a group appointment, the *attendee* (not the appointment) is the thing
that carries the per-patient state — arrival, cancellation, treatment note, invoice. Individual
appointments also have an attendee record under the hood. Model it as
`Appointment 1─n Attendee`, always, even for 1:1. This saves a painful refactor later.

### 2.4 Appointment Type ✅

- `name`, `description`, `category`, `color` (hex)
- `duration_in_minutes` (0–1440), `max_attendees` (0–100)
- `show_in_online_bookings`, `online_bookings_lead_time_hours`
- `online_payments_enabled`, `online_payments_mode` (`optional` | `required` | `deposit_required`),
  `deposit_price`, `add_deposit_to_account_credit`
- `appointment_confirmation_template_ids[]`, `appointment_reminder_template_ids[]`,
  `appointment_follow_up_template_ids[]`
- `telehealth_enabled`
- `treatment_note_template` (auto-opens the right note template)
- `appointment_type_billable_items` (auto-populates the invoice)
- `appointment_type_products`
- `practitioners` (which practitioners offer it)

This one object is the hub. Getting it right gives you: colour-coded calendar, correct default
durations, auto-invoicing, auto-note-template, per-service online booking control, and deposits.

### 2.5 Treatment Note ✅

- `id`, `title`, `draft` (bool), `author_name`
- `finalized_at`, `pinned_at`, `archived_at`, `deleted_at`, `created_at`, `updated_at`
- Links: `patient`, `practitioner`, `attendee`, `booking`, `treatment_note_template`
- `content.sections[]` → `{name, description, questions[]}` → `{name, type, answer}`

Allowed HTML in paragraph answers (sanitiser whitelist): `p, div, br, ul, ol, li, blockquote,
h1, h2, b, i, u, a`. Angle brackets must be encoded.

`finalized_at` is the medico-legal linchpin: once set, the note is locked and further edits must be
recorded as amendments, not overwrites. **Do not skip this** — it is the single most important
compliance behaviour in the clinical module.

### 2.6 Treatment Note Template ✅

`id`, `name`, `content{sections[]}`, `print_settings{}` (6 properties), timestamps, `archived_at`.

Question types are **not** enumerated in the public API (only `"text"` is shown). From the product
UI ⚠️ the set is approximately: text, paragraph (rich), checkbox, checkbox group, radio/select,
date, number, table/grid, body chart, signature, heading/instruction. **Action: dump one of your
real templates via the API from your live account to get the exact `type` strings before building
the form renderer.**

### 2.7 Patient Form Template ✅

`id`, `name`, `content{sections[] → questions[]}`, `email_to_patient_on_completion`,
`restricted_to_practitioner`, timestamps, `archived_at`.

Question object: `name`, `required`, `type`, `custom_field_token` (UUID),
`patient_field_connection` (e.g. `custom_field`).

**The important trick:** form answers can map straight onto patient fields via
`patient_field_connection` + `custom_field_token`. A patient fills an intake form and their address,
DOB and custom fields populate automatically. Build this mapping layer from day one — retrofitting
it is miserable.

### 2.8 Invoice ✅

- `number`, `issue_date`, `notes`
- `status` (10=Open, 20=Paid, 30=Closed, 40=Open credit) + `status_description`
- `calculation_method` (1=Legacy, 2=Tax exclusive, 3=Tax inclusive) + description
- `total_amount`, `net_amount`, `tax_amount`, `discounted_amount`
- `invoice_to`, `patient_extra_information`
- `online_payment_url`
- Links: `appointment`, `patient`, `practitioner`, `business`, `attendee`, `invoice_items`,
  `original_invoice`, `reversed_invoices`, `signature`
- `closed_at`, `archived_at`, `deleted_at`, timestamps

**Credit notes are modelled as reversal invoices** (`original_invoice` / `reversed_invoices`), not
as edits. Invoices are never mutated after issue. This is the correct accounting design — copy it.

### 2.9 Patient Case ✅

An episode of care / claim envelope. Fields:
`name`, `patient`, `contact`, `attendee_ids[]`, `issue_date`, `expiry_date`, `closed_at`,
`archived_at`, `referral` (bool), `referral_type` (`dva` | `medicare` 🇦🇺), `max_sessions` (1–200),
`max_invoiceable_amount`, `notes`, `include_cancelled_attendees`, `include_dna_attendees`,
plus links to `attendees`, `bookings`, `invoices`, `patient_attachments`.

This is how Cliniko enforces "insurer approved 6 sessions up to ₹X". Very useful for Indian TPA /
corporate panel / CGHS-style arrangements. Localise `referral_type`.

### 2.10 Communication ✅

`type` (`SMS` | `Email` | `Phone call` | `Other`) with `type_code` (1/2/3/4),
`direction_code` (1=Sent, 2=Received) + `direction_description`,
`category` (e.g. `Memo`, code 12), `confidential` (bool), `content`, `from`, `to`,
`patient`, timestamps, `archived_at`.

Subtypes: `MemoCommunication`, `EmailCommunication`, `SmsCommunication`.

Everything — automated and manual, inbound and outbound — lands in one timeline on the patient
file. Single-table-inheritance style. Do the same.

### 2.11 Recall ✅

`patient`, `recall_at` (date), `recall_type`, `notes`, `recalled` (bool), `recalled_at`,
`recalled_by_user`, timestamps, `archived_at`.

Simple but high-value: it drives the "patients due for follow-up" worklist and retention revenue.

### 2.12 User & roles ✅

`email`, `first_name`, `last_name`, `title`, `active`, `user_active`, `phone_numbers[]`,
`time_zone` (e.g. "Melbourne"), `time_zone_identifier` (IANA, e.g. "Australia/Melbourne").

**`role` enum — exactly six values:**
`administrator`, `bookkeeper`, `power receptionist`, `practitioner`, `receptionist`, `scheduler`

See Section 6 for the permission matrix.

### 2.13 Practitioner ✅

`active`, `first_name`, `last_name`, `title`, `designation`, `display_name`, `description` (bio for
the booking page), `label`, `show_in_online_bookings`, links to `appointment_types`,
`default_appointment_type`, `appointments`, `invoices`, `practitioner_reference_numbers`, `user`.

Practitioner is separate from User — a practitioner can exist without a login (e.g. a locum whose
schedule you manage). Keep them separate.

### 2.14 Settings ✅ — the account configuration surface

This is the single best guide to what's configurable. Reproduced by section:

**Account:** `name`, `first_name`, `last_name`, `email_from`, `country`, `country_code`,
`currency_symbol`, `subdomain`, `time_zone_support`, `has_time_travelers`,
`compliance.has_hipaa_compliance_conflicts`, `compliance.hipaa_compliance_conflict_messages[]`

**Calendar:** `start_hour`, `end_hour`, `timeslot_size_in_minutes`, `timeslot_height_in_pixels`,
`show_current_time_indicator`, `multiple_appointments_gap`

**Documents & printing:** `logo_url`, `logo_height`

**Integrations:** `mail_chimp.enabled`, `xero.enabled`, `medipass.enabled`

**Online bookings:** `enabled`, `allow_online_bookings_time_zone_choice`,
`allow_bookings_days_in_advance`, `min_hours_advance_required_to_book`,
`booking_reservation_minutes`, `max_appointments_per_day_segment`,
`min_hours_notice_for_patient_cancellation`, `daily_bookings_limit`, `require_patient_address`,
`show_prices`, `show_appointment_duration`, `notify_practitioner_by_email`,
`notify_practitioner_by_sms`, `notice_enabled`, `notice_title`, `notice_text`, `disabled_title`,
`disabled_text`, `policy`, `privacy_policy_url`, `calendar_info`, `logo_url`,
`practitioner_order[]`, `google_analytics.tracking_id`, `google_tag_manager.container_id`,
`adroll.advertiser_id`, `adroll.pixel_id`

**Online payments:** `activated`

**Patient custom fields:** `sections[]` → `{name, token, archived, fields[]}`

**Patient privacy:** `requires_hipaa_compliance`, `baa_requested`,
`anonymise_appointments_on_deletion`, `anonymise_invoices_and_payments_on_deletion`,
`anonymise_booking_notifications`, `prevent_sending_financial_data_by_email`,
`browser_title_name_format`, `ical_patient_name_option`

**Reminders:** `default_reminder_type`, `default_reminders_communication_channels[]` + description

**SMS:** `alphanumeric_source_number_required`, `default_alphanumeric_source_number`,
`max_message_length`, `number_of_usable_characters_per_message`, `replies_supported`

**Terminology:** `patient` (the label — lets clinics say "client"), `titles[]`

**Wait list:** `default_wait_list_expiry_period`

Two settings deserve highlighting because they're easy to miss and clinically important:
- `browser_title_name_format` — controls how much of a patient's name shows in the **browser tab**,
  so a patient sitting beside the screen can't read another patient's name.
- `anonymise_*_on_deletion` — right-to-erasure without destroying financial records.

---

## 3. Feature inventory — module by module

Every item below is a build checklist line.

### 3.1 Calendar & scheduling ✅

- [ ] Digital calendar, iCal/Google-Calendar-like interaction model
- [ ] Flexible views: day / week / team (all practitioners side by side)
- [ ] Per-practitioner columns; configurable column order
- [ ] Multi-location — toggle between business schedules
- [ ] Configurable day start/end hour, timeslot size, slot pixel height
- [ ] Current-time indicator line
- [ ] Custom appointment types, colour-coded
- [ ] Per-practitioner default appointment type
- [ ] Drag to move, drag edge to resize ⚠️
- [ ] Reschedule appointments
- [ ] Repeat/recurring appointments — frequency + number of occurrences
- [ ] Group / class bookings with attendee registration
- [ ] Client search with autocomplete when booking
- [ ] Register a new patient inline during booking (no page change)
- [ ] Appointment notes (per booking) + patient-level `appointment_notes` that surface every time
- [ ] Mark **arrived** / **did not arrive**
- [ ] Arrival & no-show notifications to practitioner
- [ ] Cancellation with structured reason + free-text note
- [ ] Appointment change history — who changed what, when 🔴 (audit trail; see 8.3)
- [ ] Conflict detection endpoint (double-booking / outside availability)
- [ ] Privacy mode — hide patient names on screen
- [ ] Keyboard shortcuts
- [ ] Mobile/tablet calendar
- [ ] Wait list, with default expiry period, to fill cancellations
- [ ] Rebook next visit directly from the appointment screen
- [ ] Time zone support — patients book in local time; staff can view multiple zones
- [ ] Print/export the day sheet ⚠️

### 3.2 Availability model ✅

- [ ] **Daily availability** — recurring weekly hours per practitioner per business
- [ ] **Availability block** — one-off extra availability
- [ ] **Unavailable block** — leave, breaks, blocked time; typed (`UnavailableBlockType`)
- [ ] **Available time** — computed free slots, derived as:
      daily availability + availability blocks − unavailable blocks − existing bookings,
      then filtered by online-booking rules
- [ ] Practitioner breaks
- [ ] Rules applied when computing bookable slots:
      max appointments per day segment (morning/afternoon/evening);
      min advance notice (now → 4 weeks);
      max booking horizon (1 day → 2 years);
      business / appointment type / practitioner must each be online-booking enabled

### 3.3 Online bookings (patient-facing) ✅

- [ ] Public booking site on your subdomain + embeddable widget
- [ ] Flow: location → practitioner → appointment type → slot → details → confirm
- [ ] 24/7, mobile-first
- [ ] New-patient registration during booking; optional required address
- [ ] Show/hide prices; show/hide appointment duration
- [ ] Configurable booking policy text, custom notice banner (title + body)
- [ ] Custom "bookings disabled" title + message
- [ ] Privacy policy URL, policy acceptance captured (`online_booking_policy_accepted`)
- [ ] Booking IP address recorded
- [ ] Slot reservation window while the patient completes the form (`booking_reservation_minutes`)
- [ ] Daily bookings limit
- [ ] Patient time zone choice
- [ ] Notify practitioner by email and/or SMS on new booking
- [ ] Online **cancellation** by patient, with min notice hours, staff notified
- [ ] Online payments at booking: optional / required / deposit — deposit amount, option to push
      deposit to account credit
- [ ] Analytics/marketing pixels: Google Analytics, Google Tag Manager, AdRoll
- [ ] Custom logo on booking page
- [ ] Practitioner display order and bio/description shown

### 3.4 Patient records ✅

- [ ] Full demographic record (all fields in §2.1)
- [ ] Multiple phone numbers with types
- [ ] Custom fields, grouped into named sections, per account
- [ ] Medical alerts — at-a-glance banner on the patient file
- [ ] Patient relationships (family, guardian, next of kin, employer)
- [ ] Contacts directory — doctors, insurers, payers, third parties
- [ ] Referring doctor + referral source tracking (with source types)
- [ ] File attachments, drag-and-drop upload, unlimited storage
- [ ] Patient cases — episodes of care with session caps and spend caps
- [ ] Account balance / credit visible on the patient file
- [ ] Communications timeline (SMS, email, phone, memos; in and out)
- [ ] Archive patient (reversible) vs delete patient (with anonymisation options)
- [ ] **Merge duplicate patients**
- [ ] Patient quick-search across the whole account
- [ ] Configurable terminology: "patient" vs "client"

### 3.5 Clinical documentation ✅

- [ ] Treatment notes attached to a specific appointment/attendee
- [ ] Fully customisable note templates: sections → questions → typed answers
- [ ] Multiple templates for multi-disciplinary clinics
- [ ] Template auto-selected by appointment type
- [ ] **Body charts** — annotate an anatomical diagram 🔴 (canvas drawing + stored strokes)
- [ ] Draft notes (`draft: true`) — save incomplete, finish later
- [ ] Flag incomplete notes; worklist of unfinished notes
- [ ] Finalise/lock notes with `finalized_at`; amendments tracked separately 🔴
- [ ] Copy a previous note and edit (reduces repetitive typing)
- [ ] Pinned notes
- [ ] Full-text search across notes
- [ ] Restrict note visibility between practitioners
- [ ] Export note to PDF
- [ ] Email note PDF with a custom message to patient / insurer / GP
- [ ] Rich text with a strict HTML sanitiser whitelist
- [ ] Letters + letter templates with auto-populated placeholders (mail merge)
- [ ] Signature capture (`Signature` entity) for consent/invoices

### 3.6 Patient forms ✅

- [ ] Custom intake forms, questionnaires, surveys
- [ ] Sections + typed questions, required flags
- [ ] Send by email/SMS link; patient completes without a login
- [ ] Answers land on the patient file automatically
- [ ] **Answers map to patient fields/custom fields** via `patient_field_connection`
- [ ] Email the patient a copy on completion
- [ ] Restrict a form to a specific practitioner
- [ ] Attach forms to appointment types so they're sent before the visit ⚠️

### 3.7 Communications ✅

- [ ] SMS to patients, with credit balance and auto top-up
- [ ] Bulk SMS to filtered patient groups
- [ ] Inbound SMS replies captured against the patient
- [ ] Alphanumeric sender ID, max message length, GSM/unicode character counting
- [ ] Email to patients
- [ ] Per-clinic reply-to email addresses
- [ ] Appointment **confirmations** with calendar attachment (.ics)
- [ ] Appointment **reminders** — SMS and/or email, per-patient channel preference
- [ ] Automated **follow-up** messages after an appointment
- [ ] Templates for confirmation / reminder / follow-up, assignable per appointment type
- [ ] Placeholders/merge fields in templates
- [ ] Marketing consent flags respected (`accepted_email_marketing`, `accepted_sms_marketing`)
- [ ] Communication log with sent/received direction and delivery tracking
- [ ] Confidential flag on individual communications
- [ ] Manual phone-call logging
- [ ] Recalls + recall types, recall worklist, mark-as-recalled with user attribution
- [ ] Internal **staff message board**
- [ ] Anonymised booking notifications (privacy setting)

### 3.8 Invoicing & payments ✅

- [ ] Create invoice from the appointment (one click)
- [ ] Auto-populate line items from appointment type billable items/products
- [ ] Billable items (services) and products (stock) as separate line item sources
- [ ] Invoice numbering
- [ ] Tax-inclusive / tax-exclusive / legacy calculation modes; tax rates
- [ ] Discounts and price overrides at invoicing time
- [ ] Concession types + concession prices (per-patient pricing tiers)
- [ ] Invoice to patient, third party, or split billing
- [ ] Business details on invoice: logo, banking details, ABN/tax number 🇦🇺 (→ GSTIN for India)
- [ ] Email invoices, with delivery tracking
- [ ] Online payment link per invoice (`online_payment_url`)
- [ ] Record payments; **one payment across multiple invoices**
- [ ] Custom payment types (cash, card, UPI, bank transfer, insurer…)
- [ ] Prepayments / deposits
- [ ] Account credit, and refunds (cash refund or convert to credit)
- [ ] Credit notes as reversal invoices, never edits
- [ ] Invoice statuses: Open / Paid / Closed / Open credit
- [ ] Patient account statements
- [ ] Invoice and payment change logs
- [ ] Outstanding balance per patient
- [ ] Expenses recording
- [ ] Practitioner reference/provider numbers on invoices 🇦🇺

### 3.9 Products & inventory ✅

- [ ] Product catalogue with barcodes and item codes
- [ ] Product suppliers
- [ ] Stock levels, stock adjustments
- [ ] Sell products on invoices; stock decrements
- [ ] Stock level report
- [ ] Expenses tied to purchases

### 3.10 Telehealth ✅ 🔴

- [ ] Per-appointment video link generated automatically (`telehealth_url`)
- [ ] Enabled per appointment type (`telehealth_enabled`)
- [ ] Browser-based, no app install for the patient
- [ ] Works for group appointments too
- [ ] Link delivered in confirmation/reminder messages
- [ ] ⚠️ Verify against your account: screen sharing, in-call chat, waiting room, multi-party,
      recording policy

**Build advice:** do not write WebRTC signalling/TURN yourself. Use a managed provider (Daily.co,
Twilio Video, 100ms, Agora, Whereby Embedded, LiveKit Cloud) and store the room URL on the
appointment. This is a 1-week integration versus a 6-month product.

### 3.11 Reporting ✅

Individual reports Cliniko ships, all of which you should reproduce:

- [ ] Appointments — daily / weekly / monthly schedule, breakdown per practitioner
- [ ] Cancellations & no-shows, including notice period given
- [ ] Birthdays (upcoming)
- [ ] Recalls due
- [ ] Clients without upcoming appointments (with last-seen date)
- [ ] Client spend
- [ ] Payments by period and payment method
- [ ] Practice revenue by period and practitioner
- [ ] Practitioner revenue and services offered
- [ ] Practitioner performance / productivity / utilisation
- [ ] Outstanding invoices
- [ ] Expenses
- [ ] Referral source effectiveness
- [ ] Practice growth — new clients, appointment popularity trends
- [ ] Stock levels
- [ ] **CSV/spreadsheet export on every report** (this is what stops feature requests)

### 3.12 Multi-location & business management ✅

- [ ] Multiple businesses/locations
- [ ] Per-location availability, practitioners, appointment types, and email address
- [ ] Unlimited users
- [ ] Role-based security
- [ ] Full data export ("your data is your data")
- [ ] Patient list export
- [ ] Data import / migration tooling
- [ ] Message board
- [ ] Contact list
- [ ] Referral source management
- [ ] Public REST API + API keys

---

## 4. Automation & scheduled jobs

Not documented as a feature list, but implied throughout — these are your cron/queue jobs:

| Job | Trigger | Notes |
|---|---|---|
| Send appointment reminders | X hours before appointment | Per-patient channel prefs; set `sms_reminder_sent` / `email_reminder_sent` idempotently |
| Send confirmations | On booking | Attach .ics |
| Send follow-ups | X hours/days after appointment | Per appointment type template |
| Send patient forms | Before appointment ⚠️ | |
| Expire wait list entries | Daily | `default_wait_list_expiry_period` |
| Release unpaid slot reservations | Every minute | `booking_reservation_minutes` |
| Recall due notifications | Daily | |
| SMS credit auto top-up | On low balance | |
| Nightly backups | Daily | |
| Xero sync | Real-time/webhook | |

**Guard every outbound-message job with an idempotency flag.** Double-sending reminders is the
most common and most embarrassing failure in this category of product.

---

## 5. Integrations ✅ / ⚠️

Confirmed from Settings:
- **Xero** — invoices, payments, refunds sync (`xero.enabled`)
- **Mailchimp** — marketing lists (`mail_chimp.enabled`)
- **Medipass** — health fund claiming 🇦🇺 (`medipass.enabled`)
- **Stripe** — online payments (PCI DSS Level 1, referenced in security docs)
- **Google Analytics / Google Tag Manager / AdRoll** — booking page tracking

⚠️ Also in their ecosystem (verify): Tyro/HICAPS terminals 🇦🇺, Physitrack/exercise prescription,
Zapier, Medicare Online claiming 🇦🇺.

**For an India build, the equivalents are:**

| Cliniko | India equivalent |
|---|---|
| Xero | Tally *(you already have a Tally MCP connection — that's a real advantage)*, Zoho Books |
| Stripe | Razorpay, PayU, Cashfree; UPI collect + QR |
| Medicare/DVA/Medipass | ABDM/ABHA health IDs, TPA portals, CGHS/ESIC panels |
| ABN | GSTIN; GST-compliant invoice format with HSN/SAC codes |
| Twilio SMS | MSG91, Gupshup, Kaleyra — **plus DLT registration for templates & sender IDs** |

🔴 **DLT is a real blocker in India:** every SMS template and sender ID must be pre-registered with
a telecom DLT platform before you can send. Budget 2–4 weeks for this and design your template
system so template IDs are stored per message type.

---

## 6. Roles & permissions ✅

Six roles. Approximate capability matrix (⚠️ verify each cell against your live account — this is
inferred from role names and product behaviour):

| Capability | Admin | Practitioner | Power reception | Reception | Scheduler | Bookkeeper |
|---|:--:|:--:|:--:|:--:|:--:|:--:|
| View calendar | ✔ | ✔ (own) | ✔ | ✔ | ✔ | — |
| Book / edit appointments | ✔ | ✔ | ✔ | ✔ | ✔ | — |
| View patient demographics | ✔ | ✔ | ✔ | ✔ | limited | limited |
| View treatment notes | ✔ | own/permitted | — | — | — | — |
| Write treatment notes | — | ✔ | — | — | — | — |
| Create invoices | ✔ | ✔ | ✔ | ✔ | — | ✔ |
| Record payments / refunds | ✔ | ✔ | ✔ | ✔ | — | ✔ |
| Financial reports | ✔ | own | ✔ | limited | — | ✔ |
| Clinical reports | ✔ | own | — | — | — | — |
| Manage users & settings | ✔ | — | — | — | — | — |
| Export data | ✔ | — | — | — | — | ⚠️ |
| Create API keys | opt-in per user | opt-in | opt-in | opt-in | opt-in | opt-in |

Additional access controls:
- Per-practitioner note visibility restriction (independent of role)
- `restricted_to_practitioner` on patient form templates
- `confidential` flag on individual communications
- API keys inherit the creating user's permissions exactly

**Recommendation:** implement roles as *named bundles of granular permissions*, not hardcoded
role checks. You will be asked for a seventh role within six months.

---

## 7. API design (worth copying wholesale) ✅

- REST + JSON, versioned path `/v1/`
- **HTTP Basic auth**, API key as username, blank password:
  `Basic base64(api_key + ":")`
- **Shard encoded in the key itself** — suffix `-au2` → base URL `https://api.au2.cliniko.com/v1/`
- **Mandatory `User-Agent`** in the form `AppName (contact@email)`; requests without it get blocked
- `Accept: application/json`
- **Rate limit 200 req/min per user**; 429 + `X-RateLimit-Reset` (UNIX timestamp)
- Pagination: `page`, `per_page` (default 50, max 100), response has `total_entries` and
  `links{self, next, previous}` — consumers told to follow links, not build URLs
- Filtering: `q[]=field:OPERATOR value`, repeatable
  - Dates (`YYYY-MM-DD`): `=`, `>=`, `>`, `<=`, `<`, `?` (not null), `!?` (null), `*` wildcard
  - DateTimes (UTC ISO 8601): same, plus `*` to **include cancelled/archived records** otherwise hidden
  - Numeric: `=`, `!=`, `>=`, `>`, `<=`, `<`, `?`, `!?`, and IN-lists `practitioner_id:=1,2,3`
  - String: `=`, `!=`, `~` (contains), `~~` (wildcard with `%`), `?`, `!?`
  - Boolean: `=`, `!=`, `?`, `!?`
  - Array: `~` (contains)
- Sorting: `sort=field`, `order=asc|desc`, or inline `sort=starts_at,created_at:desc`;
  default `created_at asc`
- API key management: opt-in per user ("Allow yourself to create and use API keys"), re-auth with
  password/passkey to view, named keys, shown once, revoke by archiving

⚠️ Webhooks are listed as a resource in some references but are not documented in the developer
portal introduction — verify availability before promising event-driven integrations.

**Design notes to steal:** the archived-by-default + `*` operator to reveal them is elegant; so is
putting the shard in the key so clients never guess the wrong region.

---

## 8. Non-functional requirements

### 8.1 Security ✅

- HTTPS everywhere, 2048-bit certs
- AES-256 at rest
- Passkeys (WebAuthn — biometric/PIN)
- TOTP two-factor authentication
- Role-based access control
- 24/7 monitoring
- Bug bounty programme
- Clustered infrastructure with automated failover
- PCI DSS Level 1 for payments (via Stripe — **never touch card data yourself**)

### 8.2 Data protection ✅

- Daily backups, redundant across locations
- Real-time streaming replication to replica databases
- 90-day retention after cancellation, then destruction
- Full data export on demand
- Regional data residency: Australia, UK, Canada, Ireland (EU)

### 8.3 Compliance ✅ 🔴

- HIPAA with BAA available; the product actively detects and warns about *HIPAA compliance
  conflicts* in your settings (`compliance.hipaa_compliance_conflict_messages[]`) — a genuinely
  clever feature worth copying
- GDPR + UK GDPR, DPA, SCCs, appointed DPO
- PIPEDA (Canada)
- Australian Privacy Principles
- Anonymisation-on-deletion for appointments, invoices/payments and booking notifications
- `prevent_sending_financial_data_by_email`

**For India:** you need the DPDP Act 2023 (consent, purpose limitation, breach notification,
Data Principal rights), plus clinical-record retention rules (typically 3 years for outpatient
records under the Indian Medical Council regulations ⚠️ — get legal advice, don't take this line
as authoritative), and ABDM standards if you want health-ID interoperability.

### 8.4 Audit trail 🔴

Cliniko records change history on appointments, invoices and payments, and note finalisation
timestamps. For a clinical system, build a **generic append-only audit log** from day one covering
at minimum: who viewed a patient record, who changed what field from what to what, note
finalisation and amendments, and all exports. Retrofitting audit logging into a live clinical
system is one of the worst jobs in software.

You already have `audits` + an archive pattern in the HRX codebase — reuse that approach.

### 8.5 Multi-tenancy

Cliniko is subdomain-per-account (`yourclinic.cliniko.com`). If you're building for a single
clinic group, you may not need tenancy at all — and **you probably shouldn't add it**. A
`business_id` scope for multi-location is enough. (Relevant precedent: the HRX codebase carries
tenancy scaffolding that is being unwound; don't repeat that.)

---

## 9. Suggested build phases

Ordered so that each phase is independently usable and staff can migrate gradually.

### Phase 1 — Scheduling core (the thing you can't fake)
Businesses, users/roles, practitioners, patients (+ phones, alerts, custom fields), appointment
types, daily availability / availability blocks / unavailable blocks, individual appointments with
arrival & cancellation, calendar UI (day/week/team, colour-coded, keyboard shortcuts), conflict
detection, basic patient search and file view, audit log foundation.

*Success test: reception can run a full day off it.*

### Phase 2 — Clinical records
Treatment note templates (section/question builder + renderer), notes with draft/finalise/lock/
amend, pinning, search, per-practitioner visibility, file attachments, PDF export, letters +
templates, medical alerts surfacing.

*Success test: a practitioner completes a full patient episode without opening Cliniko.*

### Phase 3 — Billing
Billable items, products, taxes, concession types/prices, invoices + items, statuses, payments
(incl. one payment → many invoices), account credit, refunds, reversal invoices, patient
statements, GST-compliant invoice PDF, expenses, financial reports, Tally/Zoho export.

### Phase 4 — Patient communications
Template engine with merge fields, SMS gateway + DLT templates, email, confirmations with .ics,
reminders, follow-ups, communication log, inbound SMS, bulk SMS with consent filtering, recalls,
patient forms with field mapping.

### Phase 5 — Online bookings
Public booking site, available-time computation with all the rule filters, slot reservation,
new-patient capture, policy acceptance, patient self-cancellation, online payment/deposit,
notifications, analytics pixels.

### Phase 6 — Reporting & the rest
Full report suite with CSV export, group appointments + attendees, patient cases, wait list,
stock/inventory, message board, telehealth (managed provider), public API, data import/export.

### Cross-cutting from day one
Audit log · archive-don't-delete on every table · `old_reference_id` on every migrated entity ·
idempotent outbound messaging · timezone correctness · soft-delete + anonymisation.

---

## 10. Migration off Cliniko

- Their API allows a **full export of everything** — patients, appointments, notes, invoices,
  attachments — under your own API key. ✅
- Rate limit 200/min means a large account takes hours, not minutes. Write a resumable exporter
  that checkpoints by `updated_at`.
- Use `*` on datetime filters to also pull **archived and cancelled** records — you want those.
- Store the Cliniko ID in `old_reference_id` on every record so you can re-sync and reconcile.
- Run both systems in parallel for at least one full billing cycle, with Cliniko as the source of
  truth, before cutting over.
- Export attachments separately and verify byte counts.
- **Do the export before you cancel** — 90-day retention is not a backup strategy.

---

## 11. Honest risk assessment

| Area | Difficulty | Note |
|---|---|---|
| Calendar + availability engine | High | Recurring rules × timezones × multi-location × conflicts is where most of the bugs live |
| Note template builder/renderer | High | A generic typed-form engine; body charts are a separate canvas project |
| Telehealth | Low *if bought*, very high if built | Buy it |
| Online booking slot computation | Medium-High | Race conditions on concurrent bookings; needs reservation locking |
| Billing + credit notes | Medium | Correctness matters more than features; get the accounting model right first |
| SMS in India | Medium | DLT registration is a schedule risk, not a code risk |
| Reporting | Medium | Volume of work, low individual difficulty |
| Compliance & audit | Medium | Cheap now, ruinous later |
| Reaching Cliniko's polish | High | 15 years of edge cases |

**The three things most likely to sink the project:**
1. Starting with the shiny parts (telehealth, dashboards) instead of the calendar.
2. Skipping the audit log and note-finalisation locking "for now."
3. Underestimating availability computation — budget it as its own multi-week component.

---

## 12. Open questions to resolve from your live account

Pull these from your own Cliniko before building. All are answerable via the API with your key:

1. Exact `type` strings for treatment-note and patient-form question types
   → `GET /treatment_note_templates`, `GET /patient_form_templates`
2. Body chart storage format (image + overlay? vector strokes?)
3. Whether webhooks are actually available on your plan
4. Full payment-type list and whether payments are API-exposed
5. `print_settings` structure on note templates
6. `Attendee`, `BillableItem`, `Service`, `Signature`, `RecallType`, `UnavailableBlockType`,
   `ConcessionPrice`, `Relationship` field lists (not published in the docs I could read)
7. Your actual usage: which of the 16 reports does your team open in a month? Build those first.
8. Row counts per table — sizes your migration and your indexing strategy

---

## Source links

- [Cliniko features overview](https://www.cliniko.com/features/)
- [Appointments](https://www.cliniko.com/features/appointments/) ·
  [Health records](https://www.cliniko.com/features/health-records/) ·
  [Transactions](https://www.cliniko.com/features/transactions/) ·
  [Reporting](https://www.cliniko.com/features/reporting/) ·
  [Business management](https://www.cliniko.com/features/business-management/)
- [Pricing](https://www.cliniko.com/pricing/)
- [Security](https://www.cliniko.com/security/)
- [API developer portal](https://docs.api.cliniko.com/developer-portal/)
- [API reference (OpenAPI)](https://docs.api.cliniko.com/openapi)
- [Help centre](https://help.cliniko.com/en/)
- [Generate an API key](https://help.cliniko.com/en/articles/1023957-generate-a-cliniko-api-key)
