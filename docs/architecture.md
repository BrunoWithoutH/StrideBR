# StrideBR Architecture

## Product vision

StrideBR is a flexible platform for planning, organizing and recording physical activities. It began as a running-focused project, but the product is intentionally sport-agnostic. Running is one modality among many.

The platform should work as a structured training notebook: simple enough for quick manual use, but flexible enough to represent very different sports without creating a database table or a hard-coded form for each one.

The current product name is StrideBR. A future naming change to Stride may be evaluated separately and must not drive technical changes until a decision is made.

## Core principles

- Activity and exercise are different concepts.
- An activity is a record of something that happened.
- An exercise is a reusable planning item used to organize workouts.
- A modality describes the sport or physical activity context.
- A modality may have multiple activity models.
- Activity models define which fields are collected and how repeated units are represented.
- Users may eventually create their own modalities, models, exercises, categories and reusable plans.
- The system must not assume that every activity has distance, duration, repetitions, sets or attempts.
- The system must not create sport-specific tables such as `corridas`, `natacao` or `karate`.
- Flexible internals should result in a simple interface. Users should not need to understand database concepts such as models, normalized values or measurement dimensions.
- Historical records must remain understandable after reusable definitions are edited or deactivated.
- Public/community features are future capabilities. Private personal use is the current baseline.
- The web application is the first client, but the domain model must remain suitable for a future API and mobile application.

## Users and privacy

A user owns personal content and may use global StrideBR content.

The data model is prepared for three visibility levels where appropriate:

- `privado`
- `amigos`
- `publico`

Community publishing, friendships and public profiles are future functionality. Their presence in the schema is preparation, not a requirement for the current interface.

Authorization must always be enforced on the server. Resource identifiers received from a browser are never proof of ownership.

## Modalities

A modality represents a sport or activity context, for example:

- Running
- Walking
- Cycling
- Swimming
- Weight training
- Calisthenics
- Karate
- Javelin throw
- Table tennis

StrideBR provides built-in modalities. The architecture also allows personal modalities created by users.

A user may activate multiple modalities. A modality is not itself the form used to record an activity.

## Activity models

A modality may have multiple models. A model describes how an activity of that modality is recorded.

Examples for Running could include:

- Basic run
- Interval session
- 5 km test
- Run with heart-rate fields
- A user-created model

Models are versioned because changing the definition used by historical records must not rewrite the past. If a used model needs a structural change, a new version should be created.

A model defines:

- fields at record level;
- fields at repeated-unit level;
- whether multiple units are supported;
- the generic name of a unit, such as attempt, lap, interval, set, descent or segment.

## Activity records

An activity record represents something the user actually performed.

Examples:

- a run completed on a given date;
- a throwing session;
- a karate practice;
- a cycling route;
- a gym session recorded after it happened.

Activity registration supports two user experiences:

- quick registration using sensible defaults;
- complete registration using a selected activity model and its configurable fields.

A record references the exact modality and model used for it. It may optionally be linked to the planned schedule item that originated it.

## Activity units

`unidades_atividade` is deliberately generic. It represents repeated occurrences inside one activity.

Depending on the model, a unit may mean:

- attempt;
- lap;
- interval;
- set;
- repetition group;
- descent;
- segment;
- throw;
- another model-defined concept.

Examples:

A javelin activity may have six units, each storing a distance and whether the attempt was valid.

An interval run may have six units, each storing distance and duration.

A simple activity may use one unit or only record-level fields when repeated units are unnecessary.

## Dynamic activity fields

`campos_modelo` defines the fields available in a model. A field has a data type, scope, order and optional measurement dimension/unit.

Supported field types currently include:

- text;
- long text;
- integer;
- decimal;
- boolean;
- date;
- time;
- interval/duration;
- selection.

Values are stored in typed relational columns rather than arbitrary JSON. This makes validation, analytics, records, comparisons and future statistics easier.

Measurement values may also have a normalized numeric representation. This allows values such as 5 km and 5000 m to be compared consistently.

## Quantities and units

`grandezas` describes measurement dimensions such as distance, time, mass and energy.

`unidades` describes compatible units and conversion rules to a base unit.

This layer exists so activity models can remain flexible while numerical data stays comparable and useful for calculations and statistics.

## Routes and maps

Routes are an important planned activity feature.

The desired interface supports at least two manual modes:

- freehand drawing;
- points with routing that follows roads/paths.

Future sources may include GPS recording and imported route files.

A route belongs to an activity record and may store its geometry plus calculated distance. Route data is separate from ordinary dynamic fields, although calculated values such as distance may populate activity metrics.

A future implementation may use a geographic PostgreSQL extension when that becomes useful. The current schema keeps the route concept isolated so that migration remains possible.

## Cronograms

A cronogram is an independent named weekly training plan, for example:

- Gym
- 5 km running plan
- Calisthenics
- Competition preparation

A user may have multiple cronograms and selects which one to view.

The weekly interface follows a calendar model rather than fixed Morning/Afternoon/Night cells. A planned workout stores:

- day of week;
- start time;
- end time;
- whether it ends on the following day;
- title and optional description;
- exercises and prescriptions.

Workouts may cross midnight.

The main desktop view is a week calendar. A day/agenda-oriented representation is appropriate for phones and may coexist with the week view.

## Planned workouts

A calendar card represents a planned workout. Exercises are contained inside that workout.

The conceptual relationship is:

`cronogram -> planned workout -> exercise occurrences`

A workout can contain zero or more exercise occurrences. The same library exercise may appear more than once in one workout.

## Exercise library

Exercises are planning resources, not activity records.

StrideBR has two main exercise sources:

- built-in global exercises;
- personal exercises owned by a user.

Using a global exercise in a workout references the global exercise rather than duplicating it. If a user wants to modify the reusable definition, the global exercise can be copied into the personal library.

Exercises may belong to multiple modalities and multiple categories. Users may create personal categories.

Community publication of exercises is a future feature. The schema may prepare for it without exposing the feature in the current application.

## Exercise occurrence and prescription

A library exercise is the reusable identity of an exercise. An occurrence in a workout stores that workout's prescription.

For example, `Supino reto` may exist once in the library while being prescribed as 4 x 10 in one workout and 5 x 5 in another.

The occurrence stores a name snapshot so old plans remain understandable if the reusable exercise is renamed or removed.

The same exercise may appear multiple times in the same workout.

Important built-in prescription concepts include:

- sets;
- repetitions;
- load;
- rest;
- block;
- cluster;
- notes;
- order.

Block and cluster support are intentional product features, not accidental legacy fields.

A block groups exercises or parts of a workout and can support structures such as supersets. Cluster describes a set split into smaller groups of repetitions with short intra-set rests.

Users may add custom prescription columns, for example RPE, RIR, cadence or another value relevant to their training.

## Historical behavior

Reusable definitions and historical instances are separated deliberately.

Editing a library exercise must not silently change the name or prescription already stored in old plans.

Changing a model used by recorded activities must not reinterpret historical values. Structural model changes require a new version.

Soft deactivation is preferred when content must remain referenced historically.

## Web application and future API

The current implementation is a PHP web application using server-rendered pages and JavaScript enhancements.

Future clients may include a mobile application. Business rules should therefore live in reusable PHP functions/domain services rather than only in page templates where practical.

A future API should expose the same concepts used by the web application rather than creating a separate competing data model.

## Security rules

- Passwords are stored only as password hashes.
- Password hashes are not stored in session state.
- The authenticated user is identified by `IdUsuario` in the server-side session.
- Successful login regenerates the session identifier.
- State-changing browser requests use POST and CSRF protection.
- Every operation on user-owned data verifies ownership server-side.
- Database credentials and other secrets come from environment variables and are never committed.
- PDO prepared statements are used for database input.
- User-controlled output is escaped for HTML.
- Production mode does not expose raw database or PHP exceptions to visitors.

## Current implementation

Implemented or substantially implemented:

- user signup, login and logout;
- profile/settings editing;
- named independent cronograms;
- week and agenda schedule views;
- planned workouts with explicit start/end times and cross-midnight support;
- exercise library with global and personal exercises;
- exercise categories and modality associations;
- exercise occurrences with sets, repetitions, load, block, cluster, rest and custom columns;
- dynamic activity modalities/models/fields;
- typed activity values and repeated activity units;
- basic manual activity creation, editing, listing and deletion;
- basic workout timer/counter tools;
- database preparation for visibility/publication and routes.

Planned or incomplete:

- route drawing and routing UI;
- GPS/imported routes;
- public/community library;
- friendships and public profiles;
- activity statistics and personal records;
- richer model creation/editing UI;
- drag-and-drop schedule editing;
- event/race aggregation;
- API;
- mobile application.

## Development boundaries

Do not implement a feature by bypassing these concepts merely because a short hard-coded solution is easier.

Do not add a sport-specific table or fixed activity form when the model/field engine can represent the requirement.

Do not merge exercise planning with recorded activity history.

Do not treat block or cluster as disposable fields when changing the workout-planning model.

Do not implement social/community behavior as a requirement for basic private use.

Do not remove historical references to simplify deletion behavior.
