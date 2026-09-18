# Workout import foundation

Status: design only. No file parsers, provider requests, credential capture, import persistence or new schema are implemented in this round.

## Routine versus workout

A routine is a reusable training template: its destination is Biblioteca / workout models. A completed workout is a session with performed sets and results; it must not become a template automatically. A future importer will ask which destination the user intends. Neither flow belongs to the GPS Activity importer.

The planned Biblioteca entry is **Importar**, alongside **Novo treino**, with **Arquivo** first and an optional Hevy connection later.

## One exercise resolution pipeline

All adapters must use the Core resolver (`stridebr_exercise_resolve_catalog`, through `stridebr_exercise_resolve_entry`) and existing cronograma name functions:

1. Technical normalization of whitespace, Unicode and lookup keys; preserve the submitted display name for custom exercises.
2. Personal exact identity first, then canonical catalog identity and configured aliases.
3. Fuzzy results are suggestions requiring an explicit **Usar** or **Manter** decision, including high-confidence fuzzy results.
4. Unrecognized names remain custom; preserve acronyms such as RDL, TRX, T-Bar and EZ.

Manual entry uses the same resolution endpoint and suggestions. Existing **Revisar nomes** remains available for legacy library entries. Do not rewrite historical exercise snapshots; new uses of a selected catalog identity prefer its current name. No provider-specific fuzzy matcher or guessed aliases.

## Planned adapters

- **StrideBR Workout JSON**: a future canonical versioned interchange format for routines and exercise/set prescriptions; validate ownership-independent IDs and limits before preview.
- **Strong CSV**: an independent adapter based on an actual export fixture. [Strong documents CSV export](https://help.strongapp.io/article/235-export-workout-data).
- **Hevy export**: blocked on an actual exported workout file to validate columns, encoding, units and session semantics. Do not infer a Hevy CSV schema from Strong CSV. [Hevy documents workout export and Strong CSV import](https://help.hevyapp.com/hc/en-us/articles/38001424401943-How-to-Import-Strong-App-CSV-Files-and-Export-Your-Data-in-Hevy).
- **Hevy API**: optional independent adapter. The [official API documentation](https://api.hevyapp.com/docs/) requires Hevy Pro and a user-generated API key. Relevant read endpoints are `/v1/routines`, `/v1/workouts`, `/v1/exercise_templates` and `/v1/routine_folders`. Routines map to templates; exercise templates assist resolution; completed workouts require a separate session import decision.

## Preview and deduplication

Before persistence, show routine/exercise counts and recognized, suggested and custom mappings. Unresolved suggestions need review before confirmation. Preview must show the intended destination and retain custom names when explicitly kept.

Plan source/provider + external ID where available and a normalized workout fingerprint for repeat imports. Repeat imports must offer skip/update explicitly; never silently merge into a user's modified routine. Final persistence/schema design belongs to the implementation round after validating real fixtures.

## Credential security and next round

Reuse Core Connections/Integrations encryption, ownership checks and server-side storage. Request an official API key, never a Hevy password. Never put credentials in frontend state, localStorage, URLs or logs. Fetches, pagination, rate limits and revocation must be reviewed against current official documentation before implementation.

Next round: obtain real Strong/Hevy exports, define canonical JSON and preview contracts, confirm destination/session policy, then implement adapters and deduplication with regression fixtures. This document does not enable imports or Hevy synchronization.
