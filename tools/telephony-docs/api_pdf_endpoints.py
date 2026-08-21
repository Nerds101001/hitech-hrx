# -*- coding: utf-8 -*-
# Part 2 of the API reference: endpoints. exec()'d by build_api_pdf.py so the
# shared styles and helpers are already in scope.

# ---- 3. endpoints --------------------------------------------------------
section('3. Endpoints')
S.append(table([
    hdr(['Method and path', 'Purpose', 'Success']),
    [p('GET /health', CELLC), 'Verify credentials, clock and source address', '200'],
    [p('POST /events', CELLC), 'Report one call event. The main endpoint.', '202'],
    [p('POST /events/batch', CELLC), 'Drain a backlog after an outage. Max 500 events.', '207'],
    [p('POST /recordings/ready', CELLC), 'Notify that audio is on disk', '202'],
    [p('POST /agent-sessions', CELLC), 'Agent login, state, break, logout', '202'],
    [p('POST /cdr/import', CELLC), 'Bulk CDR replay to close a reconciliation gap', '202'],
], [48 * mm, 88 * mm, 18 * mm]))

# --- GET /health
S.append(p('3.1  GET /health', H2))
S.append(p('Start here. A 200 proves four things at once: the API key is '
           'correct, the shared secret is correct, your clock is within 300 '
           'seconds of ours, and your source address is permitted. Do not begin '
           'sending call data until this passes - every other failure is easier '
           'to diagnose once it does.', BODY))
S.append(p('The request body is empty, so the signable string ends with a dot.', NOTE))
S.append(p('Request', H3))
S.append(code("""
GET /api/telephony/v1/health HTTP/1.1
Host: hrx.hitechgroup.in
Authorization: Bearer hrx_9bf004780caeb3bb25c8f81284a8adcc
X-HRX-Timestamp: 1755691200
X-HRX-Nonce: 4f8c1a2e-6b3d-4e7a-9c15-2d8f0b6a1e93
X-HRX-Signature: sha256=6b2f1c...e9a4
Accept: application/json
"""))
S.append(p('Response 200', H3))
S.append(code("""
{
  "status": "ok",
  "server_time": "2026-08-20T16:04:22+05:30",
  "vendor": "centrovision",
  "api_version": "v1"
}
"""))
S.append(p('Compare server_time against your own clock if you ever receive '
           'stale_timestamp.', NOTE))

# --- POST /events
S.append(PageBreak())
S.append(p('3.2  POST /events', H2))
S.append(p('The main endpoint. Send one event per request, as it happens.', BODY))
S.append(table([
    hdr(['Target', 'Latency']),
    ['Live lifecycle events', 'Within 5 seconds of occurring'],
    ['call.completed', 'Within 30 seconds of hangup'],
    ['recording.ready', 'Within 5 minutes of the audio being written'],
], [60 * mm, 70 * mm]))
S.append(Spacer(1, 4))
S.append(p('call.completed is the important one - it carries the full CDR and '
           'is what every report is built from. The lifecycle events before it '
           'drive live screens. If only one event per call can be delivered, '
           'deliver call.completed; a live agent popup then becomes impossible '
           'but the reporting still works.', BODY))

S.append(p('Required parameters', H3))
S.append(table([
    hdr(['Parameter', 'Type', 'Description']),
    [p('event', CELLC), 'string', 'Event name from the catalogue in section 4'],
    [p('event_id', CELLC), 'uuid',
     'Unique for all time. This is the idempotency key - keep it STABLE across '
     'your retries, or a retry writes a second copy.'],
    [p('occurred_at', CELLC), 'ISO-8601',
     'When the event happened on the gateway, not when it was sent. Must carry '
     'an explicit offset, e.g. 2026-08-19T15:01:44+05:30'],
    [p('call_uid', CELLC), 'string',
     'Your call identifier. Must equal the RID/UID shown on the CentroVision '
     'CDR screen, so both sides can open the same record in a dispute.'],
], [30 * mm, 20 * mm, 104 * mm]))

S.append(p('Key optional parameters', H3))
S.append(table([
    hdr(['Parameter', 'Type', 'Description']),
    [p('sequence', CELLC), 'integer',
     'Monotonically increasing within one call_uid. Lets HRX tolerate '
     'out-of-order delivery - a late call.answered will never overwrite a '
     'call.completed that already arrived.'],
    [p('ref_id', CELLC), 'uuid|null',
     'The HRX correlation id, supplied on originate and echoed back on every '
     'event for that call. Null for manual, inbound and IVR calls - that is '
     'expected, not an error.'],
    [p('origin', CELLC), 'enum',
     'api_click_to_call, manual_dial, inbound, ivr, predictive, preview, '
     'progressive, callback, internal'],
    [p('direction', CELLC), 'enum', 'inbound, outbound, internal'],
    [p('mode', CELLC), 'enum', 'auto, manual - the CentroVision dialling mode'],
    [p('agent', CELLC), 'object', 'See section 5. This is how HRX identifies the employee.'],
    [p('status', CELLC), 'enum',
     'answered, no_answer, busy, failed, congestion, cancelled, abandoned, '
     'rejected, not_reachable, switched_off'],
    [p('hangup_by', CELLC), 'enum', 'agent, customer, system, supervisor, timeout'],
    [p('timestamps', CELLC), 'object', 'See section 6'],
    [p('durations', CELLC), 'object', 'See section 6'],
    [p('disposition', CELLC), 'object', 'code, name, sub_code, sub_name, remarks, callback_at'],
    [p('recording', CELLC), 'object', 'available, recording_uid, duration_seconds, format, channels'],
], [30 * mm, 20 * mm, 104 * mm]))

S.append(PageBreak())
S.append(p('Example: the smallest valid event', H3))
S.append(code("""
{
  "event":       "call.completed",
  "event_id":    "9f1b2c3d-4e5f-4a6b-8c7d-0e1f2a3b4c5d",
  "occurred_at": "2026-08-19T15:01:44+05:30",
  "call_uid":    "CV-20260819-000512"
}
"""))

S.append(p('Example: a complete call.completed', H3))
S.append(code("""
{
  "event": "call.completed",
  "event_id": "9f1b2c3d-4e5f-4a6b-8c7d-0e1f2a3b4c5d",
  "occurred_at": "2026-08-19T15:01:44+05:30",
  "sequence": 6,
  "call_uid": "CV-20260819-000512",
  "ref_id": null,
  "origin": "manual_dial",
  "direction": "outbound",
  "mode": "manual",
  "agent": {
    "agent_id": "AG-1042", "agent_code": "mehak",
    "agent_name": "Mehak Bhatia", "extension": "1042",
    "agent_number": "+919812345678", "hrms_employee_id": "EMP-096"
  },
  "caller_number": "+919812345678",
  "callee_number": "+919812309876",
  "did_number": "+911244567890",
  "campaign": { "campaign_id": "CMP-07", "campaign_name": "Customer Care",
                "queue": "CustomerCare", "skill": "Hindi" },
  "gateway":  { "gateway_id": "GW-2", "channel": "12",
                "sim_msisdn": "+919812300001", "operator": "Airtel" },
  "status": "answered",
  "hangup_by": "customer",
  "hangup_cause": "NORMAL_CLEARING",
  "hangup_cause_code": 16,
  "timestamps": {
    "initiated_at":     "2026-08-19T14:58:02+05:30",
    "agent_ring_at":    "2026-08-19T14:58:03+05:30",
    "agent_answer_at":  "2026-08-19T14:58:08+05:30",
    "customer_ring_at": "2026-08-19T14:58:10+05:30",
    "answered_at":      "2026-08-19T14:58:22+05:30",
    "ended_at":         "2026-08-19T15:01:40+05:30",
    "wrapup_start_at":  "2026-08-19T15:01:40+05:30",
    "wrapup_end_at":    "2026-08-19T15:01:44+05:30"
  },
  "durations": {
    "agent_ring_seconds": 5,  "customer_ring_seconds": 12,
    "pdd_seconds": 8,         "queue_wait_seconds": 0,
    "talk_seconds": 198,      "hold_seconds": 0,
    "wrap_seconds": 4,        "total_seconds": 218,
    "billed_seconds": 240
  },
  "disposition": {
    "code": "NI", "name": "Not Interested",
    "sub_code": "NI-PRICE", "sub_name": "Price too high",
    "remarks": "Asked to be called after Diwali"
  },
  "recording": {
    "available": true, "recording_uid": "REC-20260819-000512",
    "duration_seconds": 198, "format": "wav", "channels": "stereo"
  }
}
"""))

S.append(p('Response 202 - accepted', H3))
S.append(code("""
{
  "status": "accepted",
  "event_id": "9f1b2c3d-4e5f-4a6b-8c7d-0e1f2a3b4c5d",
  "received_at": "2026-08-19T15:01:45+05:30"
}
"""))
S.append(p('202 means STORED, not processed. Normalisation happens afterwards. '
           'Do not retry a 202.', NOTE))

S.append(p('Response 200 - duplicate', H3))
S.append(code("""
{
  "status": "duplicate",
  "event_id": "9f1b2c3d-4e5f-4a6b-8c7d-0e1f2a3b4c5d",
  "received_at": "2026-08-19T15:01:47+05:30"
}
"""))
S.append(p('This event_id was already delivered and nothing was written twice. '
           'This is the expected, successful answer to a retry - treat it as '
           'success, not as an error.', NOTE))

# --- the manual dial, called out on its own
S.append(PageBreak())
S.append(p('3.3  Example: a manual handset dial (UAT-05)', H2))
S.append(p('The agent dialled on the handset. HRX never placed the call, so '
           'there is no ref_id, and the agent block may carry only an extension '
           '- often all a handset dial knows. Everything else is identical to '
           'an API-originated call.', BODY))
S.append(code("""
{
  "event": "call.completed",
  "event_id": "1a2b3c4d-5e6f-4071-8213-495a6b7c8d9e",
  "occurred_at": "2026-08-19T16:22:10+05:30",
  "sequence": 4,
  "call_uid": "CV-20260819-000640",
  "ref_id": null,
  "origin": "manual_dial",
  "direction": "outbound",
  "mode": "manual",
  "agent": { "extension": "1042" },
  "caller_number": "+919812345678",
  "callee_number": "+919811122233",
  "status": "no_answer",
  "hangup_by": "timeout",
  "timestamps": {
    "initiated_at":     "2026-08-19T16:21:40+05:30",
    "customer_ring_at": "2026-08-19T16:21:44+05:30",
    "ended_at":         "2026-08-19T16:22:10+05:30"
  },
  "durations": { "customer_ring_seconds": 26, "talk_seconds": 0,
                 "total_seconds": 30 }
}
"""))
S.append(callout(
    'Why this case decides the project',
    'Integrations of this kind usually fail because only the API-originated '
    'path is instrumented, while a large share of calls are dialled manually. '
    'The reports are then wrong in week one and confidence does not recover. '
    'If the CentroVision platform cannot raise webhooks for manual, inbound and '
    'IVR calls, please say so at kickoff rather than at acceptance testing.',
    WARN))

# --- batch
S.append(PageBreak())
S.append(p('3.4  POST /events/batch', H2))
S.append(p('Backlog drain only, never live traffic. If HRX was unreachable and '
           'events were buffered, replay them here rather than one at a time. '
           'At most 500 events per request; each is processed independently, so '
           'one malformed row never rejects the batch.', BODY))
S.append(p('Request', H3))
S.append(code("""
{
  "events": [
    { "event": "call.completed", "event_id": "...", "occurred_at": "...",
      "call_uid": "CV-20260819-000512", "...": "..." },
    { "event": "call.completed", "event_id": "...", "occurred_at": "...",
      "call_uid": "CV-20260819-000513", "...": "..." }
  ]
}
"""))
S.append(p('Response 207 - per item', H3))
S.append(code("""
{
  "accepted": 498,
  "rejected": 2,
  "results": [
    { "event_id": "9f1b...", "status": "accepted",  "error": null },
    { "event_id": "2c4d...", "status": "duplicate", "error": null },
    { "event_id": null,      "status": "rejected",
      "error": "event_id and call_uid are required" }
  ]
}
"""))
S.append(p('Always inspect results. A 207 does not mean everything was '
           'accepted.', NOTE))

# --- recordings
S.append(p('3.5  POST /recordings/ready', H2))
S.append(p('Sent once the recording is written and fetchable. Send only a '
           'recording_uid - never a playable URL. When a permitted HRX user '
           'presses play, HRX requests a short-lived signed URL from '
           'CentroVision and streams the bytes itself, so audio never leaves '
           'the gateway unauthenticated and every playback is permission '
           'checked and logged. A URL in a webhook is a URL in a log file.', BODY))
S.append(code("""
{
  "event": "recording.ready",
  "event_id": "5e6f7081-92a3-44b5-c6d7-8e9f0a1b2c34",
  "occurred_at": "2026-08-19T15:02:10+05:30",
  "call_uid": "CV-20260819-000512",
  "recording_uid": "REC-20260819-000512",
  "recording_type": "call",
  "duration_seconds": 198,
  "format": "wav",
  "channels": "stereo",
  "size_bytes": 3168000,
  "retention_until": "2027-08-19"
}
"""))
S.append(table([
    hdr(['Parameter', 'Notes']),
    [p('recording_uid', CELLC), 'Required. The handle HRX later exchanges for a signed URL.'],
    [p('recording_type', CELLC),
     'call, transfer_leg, conference or voicemail. Only "call" flips the play '
     'button on the parent call; a transfer-leg or conference recording belongs '
     'to its own leg.'],
    [p('channels', CELLC),
     'Stereo strongly preferred - agent and customer on separate channels makes '
     'talk-over analysis possible later. Mono is accepted.'],
    [p('retention_until', CELLC),
     'If retention is finite, send it. HRX then stops offering playback after '
     'that date rather than showing a broken player.'],
], [34 * mm, 120 * mm]))

# --- agent sessions
S.append(PageBreak())
S.append(p('3.6  POST /agent-sessions', H2))
S.append(p('This is what produces total login time and active time on the HRX '
           'productivity report. Send the CentroVision-computed totals on '
           'agent.logout: HRX stores them verbatim rather than deriving them '
           'from the state stream, specifically so that the HRX report and the '
           'CentroVision User Session report cannot drift apart and turn every '
           'discrepancy into a support ticket.', BODY))
S.append(code("""
{
  "event": "agent.logout",
  "event_id": "6f708192-a3b4-45c6-d7e8-9f0a1b2c3d45",
  "occurred_at": "2026-08-19T19:02:11+05:30",
  "session_id": "SES-20260819-1042-01",
  "agent": { "agent_id": "AG-1042", "extension": "1042",
             "hrms_employee_id": "EMP-096" },
  "login_at":  "2026-08-19T09:58:40+05:30",
  "logout_at": "2026-08-19T19:02:11+05:30",
  "mode": "manual",
  "totals": {
    "login_seconds": 32611, "ready_seconds": 14980,
    "talk_seconds": 9120,   "hold_seconds": 340,
    "wrap_seconds": 2210,   "idle_seconds": 3105,
    "break_seconds": 2856,  "occupancy_percent": 41.2,
    "calls_handled": 63,    "calls_missed": 4
  },
  "breaks": [
    { "reason": "Lunch", "from": "2026-08-19T13:30:00+05:30",
      "to": "2026-08-19T14:05:12+05:30", "seconds": 2112 }
  ],
  "device": { "type": "gsm_handset", "ip": "192.168.2.87" }
}
"""))
S.append(table([
    hdr(['Event', 'Purpose']),
    [p('agent.login', CELLC), 'Session opens. session_id is required.'],
    [p('agent.state', CELLC),
     'Live state change: ready, oncall, hangup, wrapup, idle, break, offline, '
     'dialing, paused. Drives the wallboard.'],
    [p('agent.break.start', CELLC), 'Break begins. Include a reason - it maps to the Offtime report.'],
    [p('agent.break.end', CELLC), 'Break ends.'],
    [p('agent.logout', CELLC), 'Session closes. Send the totals block here.'],
], [36 * mm, 118 * mm]))

# --- cdr import
S.append(p('3.7  POST /cdr/import', H2))
S.append(p('Used to close a gap that the nightly reconciliation found - after a '
           'long outage, or when a webhook was never delivered. Records need no '
           'event_id: HRX derives a stable one from call_uid, so replaying the '
           'same CDR row twice collapses to a single event rather than '
           'duplicating it. Only call_uid is strictly required, but send the '
           'full CDR shape; a partial record produces a partial call row.', BODY))
S.append(code("""
{
  "records": [
    { "call_uid": "CV-20260819-000512", "origin": "manual_dial",
      "direction": "outbound", "status": "answered",
      "agent": { "extension": "1042" },
      "timestamps": { "initiated_at": "2026-08-19T14:58:02+05:30",
                      "ended_at":     "2026-08-19T15:01:40+05:30" },
      "durations":  { "talk_seconds": 198, "total_seconds": 218 } }
  ]
}
"""))
S.append(code("""
{ "inserted": 14, "updated": 3, "rejected": 0 }
"""))
S.append(p('Prefer push. Use this to repair, not as a substitute.', NOTE))
