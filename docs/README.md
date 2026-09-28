Mini ERP CodeIgniter 4 Authorization Middleware v1.0

Implemented:
- AuthorizationFilter
- Permission helper
- Controller/API integration pattern

Usage:

Route:
filter = authz:invoice.approve

Controller:
can_permission('invoice.edit')

Flow:

Controller
 |
AuthorizationFilter
 |
AuthorizationService
 |
DecisionEngine
 |
Allow / Deny
