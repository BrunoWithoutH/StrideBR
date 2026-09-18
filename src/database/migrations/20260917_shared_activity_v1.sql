SET search_path TO stridebr, public;

CREATE TABLE IF NOT EXISTS activity_participants (
    idactivityparticipant text PRIMARY KEY,
    idregistro text NOT NULL REFERENCES registros_atividade(idregistro) ON DELETE CASCADE,
    idusuario text NOT NULL REFERENCES usuarios(idusuario) ON DELETE CASCADE,
    status text NOT NULL CHECK (status IN ('pending','accepted','declined','removed')),
    invited_by text NOT NULL REFERENCES usuarios(idusuario) ON DELETE RESTRICT,
    invited_at timestamptz NOT NULL DEFAULT NOW(),
    responded_at timestamptz NULL,
    created_at timestamptz NOT NULL DEFAULT NOW(),
    updated_at timestamptz NOT NULL DEFAULT NOW(),
    CONSTRAINT uq_activity_participants_activity_user UNIQUE (idregistro,idusuario)
);

CREATE INDEX IF NOT EXISTS ix_activity_participants_user_status
ON activity_participants (idusuario,status,updated_at DESC);

CREATE INDEX IF NOT EXISTS ix_activity_participants_activity
ON activity_participants (idregistro,status);
