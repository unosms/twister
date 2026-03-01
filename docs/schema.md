# Proposed Schema

This schema matches the new migration stubs and the UI flows in the admin dashboard.

## Core
- users (existing): auth, roles, ownership, assignments
- roles: name, description
- permissions: name, description
- role_user (pivot): role_id, user_id
- permission_role (pivot): permission_id, role_id

## Devices
- devices: uuid, name, type, model, serial_number, status, ip_address, location, firmware_version, last_seen_at, assigned_user_id, metadata
- device_groups: name, description, created_by
- device_group_device (pivot): device_id, device_group_id
- device_assignments: device_id, user_id, assigned_by, assigned_at, unassigned_at, notes

## Commands
- command_templates: name, description, device_group_id, ui_type, payload_template, requires_confirmation, requires_2fa, log_execution, active, created_by
- command_executions: command_template_id, device_id, executed_by, status, payload, result, error_message, executed_at

## Alerts & Notifications
- alerts: device_id, severity, title, message, status, acknowledged_by, acknowledged_at, resolved_at
- notifications: user_id, alert_id, type, title, body, severity, read_at, archived_at, metadata

## Observability & Audit
- telemetry_logs: device_id, level, message, payload, recorded_at
- audit_logs: actor_id, action, subject_type, subject_id, metadata, ip_address, user_agent, occurred_at
