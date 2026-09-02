# LAFIM backend

Laravel REST API for the pre-applied LAFIM MySQL MVP schema. It deliberately contains no schema migrations.

Set normal Laravel database variables and `FRONTEND_URL` (a comma-separated allow-list is supported) in the deployment environment. Do not run `migrate`; the container only checks database connectivity before Apache starts.

Opening standings import: apply `LAFIM_Estructura_Base_Posiciones.sql` manually to production, then insert one `standings_baselines` row per tournament, category, and club. Each row must contain cumulative standings through its `snapshot_round_number` (default `3`); `goals_for` and `goals_against` default to `0` when unavailable. Public standings start from these values and only add confirmed or validated results from later rounds, so imported rounds are not counted twice. The SQL file creates only the table and constraints, never club or category standings data.

Canonical public endpoints: `GET /api/matches/{upcoming|today|completed}`, `GET /api/standings/{category}` (the active tournament), `GET /api/standings/{tournament}/{category}`, `GET /api/clubs`, `GET /api/clubs/{slug}`, `GET /api/players`, and `GET /api/news`. The frontend-compatible Spanish aliases are `/api/partidos/{proximos|hoy|jugados}`, `/api/clubes`, `/api/jugadores`, `/api/noticias`, and `/api/posiciones/{categoria}`; aliases return the Spanish field names expected by the current Next client.

Authentication: `POST /api/auth/login`, then send `Authorization: Bearer <token>` to `GET /api/auth/me` and `POST /api/auth/logout`. Tokens are encrypted Laravel-native bearer tokens, retained in the configured cache for 12 hours; this is used instead of Sanctum because the production schema has no Sanctum token table. The default cache is file-based because that table is absent; set `CACHE_STORE` to a shared cache such as Redis for multi-container deployments.

Admins use `/api/admin/{clubs|categories|tournaments|rounds|fixtures|matches|players|news}` for CRUD. Assigned `CLUB_ADMIN` users submit results for their club at `POST /api/matches/{match}/results/submissions` and the opposing club can confirm an existing submission at `POST /api/matches/{match}/results/confirm`. `SUPER_ADMIN` can confirm using a submission or direct scores, and can validate results. Match status is `FINISHED`; result states include `PENDING_CONFIRMATION`, `CONFIRMED`, `IN_CONFLICT`, and `VALIDATED`.

Super Admin user management is available only to authenticated `SUPER_ADMIN` users: `GET /api/admin/users?per_page=20`, `POST /api/admin/users`, `GET /api/admin/users/{id}`, `PUT|PATCH /api/admin/users/{id}`, and `DELETE /api/admin/users/{id}`. List and detail responses include `roles` and `clubs`, never a password. Create requires `name`, `email`, `password`, `password_confirmation`, and `role_ids`; update accepts those fields optionally, plus `is_active`, `role_ids`, and `club_ids`. Passwords must be at least 12 characters and include uppercase, lowercase, numeric, and symbol characters. `role_ids` may contain only the existing `SUPER_ADMIN` and `CLUB_ADMIN` role IDs. `club_ids` are existing club IDs; a user assigned `CLUB_ADMIN` must have at least one. Role and club assignments are replaced when their respective arrays are supplied. The API rejects deleting, deactivating, or removing the `SUPER_ADMIN` role from the last active Super Admin, and records create, update, and delete snapshots in `audit_logs` without password data.

Example create payload:

```json
{
  "name": "Club administrator",
  "email": "admin@example.test",
  "password": "SecurePassword!2026",
  "password_confirmation": "SecurePassword!2026",
  "is_active": true,
  "role_ids": [2],
  "club_ids": [4, 7]
}
```
