# Shield Group Extension Design

**Status:** Official implementation extension

## 1. Purpose

CodeIgniter Shield remains the identity, authentication, session and group
membership authority. Mini ERP adds database-backed group metadata and group
permission assignments so the administration UI does not depend on PHP config
files.

## 2. Authority split

| Concern | Authority |
|---|---|
| User identity and authentication | Shield `users` and identity tables |
| User-to-group membership | Shield `auth_groups_users` |
| Direct user permission additions | Shield `auth_permissions_users` |
| Group catalog | Mini ERP `groups` |
| Permission catalog | Mini ERP `resource_actions` |
| Group permission matrix | Mini ERP `group_permissions` |
| Contextual restriction | Mini ERP `policies` and `policy_actions` |

The ERP authorization engine does not use Shield's static `AuthGroups::$matrix`
as its runtime permission source and does not call Shield `User::can()` as the
final ERP decision.

## 3. Group identity bridge

`groups.code` is the canonical Shield group alias. The value is stored in
`auth_groups_users.group`. A Policy participant with
`participant_type = ROLE` stores the matching `groups.id` in
`participant_ref_id`.

The bridge is application-validated because Shield's membership table stores a
string alias and the polymorphic Policy reference has no foreign key.

## 4. Profile resolution

The effective ERP profile is the union of:

1. `group_permissions` for the user's active Shield groups;
2. direct permissions in `auth_permissions_users` that match the
   `resource.action` catalog.

Direct user permissions are additions only. They do not create a Policy
restriction and cannot bypass `ABSOLUTE_DENY`.

## 5. Legacy tables

The former Mini ERP `roles` and `role_permissions` tables are legacy and are
not read by the runtime authorization engine. Migration `000021` copies any
existing data into `groups` and `group_permissions` without deleting the legacy
tables or rewriting migration history.
