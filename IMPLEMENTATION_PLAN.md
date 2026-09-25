# OfficeOS Implementation Plan

## Scope
- PHP backend with MySQL storage.
- Role based access for `employee`, `manager`, and `admin`.
- Core modules for login, task management, attendance, leave, and leaderboard.
- Plain HTML and simple responsive CSS only.

## Todo
- [x] Define the backend structure and app flow.
- [x] Create the database connection helper.
- [x] Add authentication and session based RBAC.
- [x] Add the MySQL schema and demo seed data.
- [x] Build the dashboard shell with simple responsive CSS.
- [ ] Add edit/delete actions for tasks and leave workflows.
- [ ] Split the dashboard into smaller PHP view files if the UI grows.

## Implementation Order
1. Login and session handling.
2. Dashboard model per role.
3. Attendance check-in and check-out.
4. Leave request creation and review.
5. Task creation and task status updates.
6. Leaderboard query and display.
