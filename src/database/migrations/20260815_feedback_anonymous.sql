BEGIN;

SET search_path TO stridebr, public;

ALTER TABLE feedbacks
    ADD COLUMN IF NOT EXISTS anonimo BOOLEAN NOT NULL DEFAULT FALSE;

INSERT INTO feature_flags (chave, ativo, descricao) VALUES
('feedback.anonymous.enabled', TRUE, 'Permite que testers enviem feedback sem vínculo com conta, IP ou navegador.')
ON CONFLICT (chave) DO NOTHING;

COMMIT;
