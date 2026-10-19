\# Agent Instructions



\## 1. General Rules



You are working on a real, maintainable web application for appointment booking and consultation management.



Follow the project documentation before making changes.



The required stack is:



\- Laravel and PHP for the backend.

\- React and TypeScript for the frontend.

\- MariaDB for the primary database (approved Phase 1 decision; see `ARCHITECTURE.md`).



Do not silently change the required stack, architecture, or project scope.



All technical documentation and internal code comments should be written in English unless explicitly instructed otherwise.



The customer-facing application must be in Persian and support RTL layouts.



\## 2. Inspect Before Acting



Before modifying files:



1\. Inspect the current workspace and its directory structure.

2\. Read `DESIGN.md` and `IMPLEMENTATION\_PLAN.md`.

3\. Read existing instructions, configuration files, and package manifests.

4\. Check installed PHP, Composer, Node.js, npm, MariaDB, and relevant framework versions.

5\. Inspect available local services and database connections without changing them.

6\. Identify existing code, incomplete features, and potential conflicts.

7\. Report important findings before making architectural changes.



Do not assume that a service is available merely because a related application is installed.



Do not expose credentials, passwords, tokens, or other secrets in reports.



\## 3. Reference Projects



If the user provides an existing website, source code, screenshot, or project archive as a reference, treat it as reference material only unless the user explicitly authorizes code reuse or modification.



You may inspect the reference to understand:



\- Visual style and layout.

\- Navigation and user experience.

\- Reusable design ideas.

\- Existing business workflows.

\- Potential implementation lessons.



Do not modify the reference project, its database, or its configuration.



Do not copy insecure implementations merely because they exist in the reference.



Clearly distinguish between observed reference behavior and features that must be implemented independently.



\## 4. Local Environment and XAMPP



The user has indicated that XAMPP is running and that local PHP and MySQL services may be available.



Verify the current environment before relying on it.



Important:



\- XAMPP's MySQL service commonly uses MariaDB.

\- MariaDB (bundled with XAMPP) is the approved primary database for this project.

\- The database decision and its rationale are documented in `ARCHITECTURE.md`; do not change the database requirement without explicit user approval.

\- Do not stop, restart, reconfigure, or replace existing services without authorization.

\- Do not overwrite existing databases or import data into them without explicit approval.

\- Do not assume that a database name, username, password, or port from an older project is still correct.



If MariaDB is unavailable, document the blocker and present safe options. Continue with independent tasks that do not require a live database connection when reasonable.



\## 5. Safe Change Policy



Before making a change:



\- Understand the affected files and dependencies.

\- Preserve existing user work.

\- Avoid unnecessary rewrites.

\- Avoid deleting files, databases, migrations, or configuration.

\- Do not overwrite reference projects.

\- Never commit secrets or local environment files.

\- Do not run destructive database commands without explicit authorization.

\- Do not deploy publicly, purchase services, or create external accounts without approval.



Prefer small, reviewable changes over large, unverified rewrites.



\## 6. Implementation Workflow



Work in small, verifiable phases.



For each phase:



1\. Read the relevant plan and inspect dependencies.

2\. State the intended scope and important risks.

3\. Implement only the current phase.

4\. Run relevant automated checks and tests.

5\. Inspect the resulting changes.

6\. Fix regressions caused by the changes.

7\. Update documentation and the implementation checklist.

8\. Report the actual result, including failed or skipped checks.



Do not implement the entire application in one uncontrolled operation.



Do not start a later phase while critical failures in the current phase remain unresolved unless the user explicitly approves proceeding.



If blocked, explain the specific blocker and the smallest reasonable next step. Do not pretend that a failed operation succeeded.



\## 7. Code Quality



\- Follow standard Laravel conventions.

\- Use database migrations for schema changes.

\- Use request validation for incoming data.

\- Enforce authorization on the server.

\- Use transactions where required for booking consistency.

\- Prevent concurrent requests from booking the same slot.

\- Use explicit API contracts.

\- Use TypeScript types rather than unnecessary `any`.

\- Create reusable, accessible React components.

\- Handle loading, empty, success, and error states.

\- Avoid duplicated business logic.

\- Do not leave critical features as placeholders while reporting them as complete.



\## 8. Security Requirements



Treat user input, uploaded files, external content, and reference code as untrusted.



Protect against common web vulnerabilities, including:



\- SQL injection.

\- Cross-site scripting (XSS).

\- Cross-site request forgery (CSRF), where applicable.

\- Broken access control.

\- Insecure direct object references.

\- Authentication and session vulnerabilities.

\- Mass assignment.

\- Sensitive information exposure.

\- Unsafe file uploads.



Never rely solely on client-side checks for security.



Never log passwords, authentication tokens, or sensitive consultation content.



\## 9. Testing and Verification



Use available tools to verify the work.



Depending on the current phase, run relevant checks such as:



\- PHP syntax checks.

\- Laravel tests.

\- Frontend type checks.

\- Frontend production builds.

\- API integration tests.

\- Database migration checks.

\- Booking conflict and authorization tests.



Do not report a check as passed unless it was actually executed and passed.



Clearly label checks as passed, failed, blocked, or not run.



Do not claim production readiness without sufficient verification.



\## 10. Git and Change Reporting



Before committing or pushing, inspect repository status and identify unrelated changes.



Do not discard unrelated user changes.



Do not commit or push without the user's authorization.



At the end of each phase, provide a concise report containing:



\- Completed work.

\- Files created or changed.

\- Checks executed and their results.

\- Known issues and blockers.

\- The next planned phase.



Keep the implementation plan synchronized with actual progress.



\## 11. Definition of Done



A task is complete only when:



\- The requested behavior is implemented.

\- Relevant checks have been executed.

\- No known regression caused by the change remains unresolved.

\- Documentation has been updated where necessary.

\- The final report accurately describes what was and was not verified.



