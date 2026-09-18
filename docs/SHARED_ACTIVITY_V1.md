# Shared Activity V1

`registros_atividade.idusuario` remains the sole recorder/owner. `activity_participants` only links invited athletes to that event; accepted links expose event facts (date, sport, route, distance, elapsed/moving duration, route laps and canonical elevation) without cloning the activity.

Personal fields remain recorder-only: heart rate, calories, power, cadence, RPE, load, equipment, zones and sensor samples. A missing participant metric is represented as absent, never copied from the recorder.

Statuses are `pending`, `accepted`, `declined`, and `removed`. Owners may invite/cancel; the invitee alone accepts or declines; an accepted participant may leave. Invitations require an accepted friendship and do not make an activity public. The unique activity/user relation makes invite and accept idempotent.

Accepted entries may appear in a participant history as shared event facts. V1 excludes participant-only links from PB/SB and personal physiological statistics. Future versions may associate a participant's own recording/streams with this same event; no automatic matching or merge occurs in V1.
