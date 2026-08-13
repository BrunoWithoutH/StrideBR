# StrideBR Copilot Instructions

Read `docs/architecture.md` before proposing or making architectural changes.

StrideBR is a generic platform for planning and recording physical activities. It is not a running-only application.

Use the existing stack:

- PHP without a framework;
- PostgreSQL;
- PDO prepared statements;
- server-rendered HTML with JavaScript/CSS enhancements;
- Composer only for justified PHP dependencies.

Keep `public/` as the web document root. Application code, database configuration and shared logic belong outside the public web root when possible.

Never hard-code credentials, tokens or private infrastructure data. Use environment variables for secrets.

Authentication uses the server-side `IdUsuario` session value. Do not put password hashes in session state. Regenerate sessions after successful login. State-changing requests must use POST and CSRF protection.

Never trust a user-owned resource ID from a request by itself. Every read, update, copy or delete involving private data must verify ownership on the server.

Escape user-controlled HTML output. Keep raw exceptions and database errors out of production responses.

Activity and exercise are different concepts:

- an activity records something that happened;
- an exercise is a reusable planning resource;
- a planned workout contains exercise occurrences/prescriptions;
- an activity model defines how an activity is recorded.

Do not create sport-specific activity tables. Use modalities, activity models, dynamic fields, repeated activity units and typed values.

Do not assume all activities have distance, duration, attempts, sets or repetitions.

Preserve activity history. Used model definitions must not be structurally rewritten; create a new model version when the structure changes.

Exercise-library definitions and workout exercise occurrences must stay separate. Preserve snapshot/history behavior. The same exercise may appear more than once in a workout.

Block and cluster are intentional first-class workout-planning capabilities. Do not remove them while refactoring. Custom prescription fields must remain possible.

Cronograms are independent named weekly plans. Planned workouts use explicit start/end times and may cross midnight. Do not reintroduce fixed Morning/Afternoon/Night storage.

Keep future API/mobile use possible by placing reusable business logic outside page templates when practical, but do not introduce a framework or an unnecessary API layer before it is needed.

Community publishing, friendships and public profiles are future features. Keep compatible fields where already designed, but do not make them required for private use.

Routes/maps are planned. The desired future route editor supports freehand drawing and follow-roads routing, with later GPS/import support.

Prefer focused changes over broad rewrites. Preserve working behavior unless the task explicitly requires changing it.

Do not add explanatory comments to code. Prefer clear names and small functions instead. Preserve existing user-written comments when editing surrounding code unless they become incorrect.
