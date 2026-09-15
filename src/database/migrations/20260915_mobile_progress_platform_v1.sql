BEGIN;
SET search_path TO stridebr, public;

CREATE INDEX IF NOT EXISTS ix_registros_atividade_progress_user_sport_date
ON registros_atividade (idusuario, idmodalidade, data_inicio DESC)
WHERE excluido_em IS NULL AND status = 'concluido';

CREATE INDEX IF NOT EXISTS ix_series_exercicio_atividade_progress_exercise_record
ON series_exercicio_atividade (idexercicio, idregistro, concluida)
WHERE idexercicio IS NOT NULL;

CREATE INDEX IF NOT EXISTS ix_treinos_agendados_progress_athlete_date_status
ON treinos_agendados (idatleta, data_treino, status);

COMMIT;
