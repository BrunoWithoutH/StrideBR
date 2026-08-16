BEGIN;

SET search_path TO stridebr, public;

CREATE OR REPLACE FUNCTION fn_valida_registro_atividade()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
DECLARE
    v_modelo RECORD;
    v_modalidade RECORD;
    v_cronograma_usuario VARCHAR(21);
    v_treino RECORD;
    v_cronograma_alterado BOOLEAN;
    v_treino_alterado BOOLEAN;
BEGIN
    IF TG_OP = 'UPDATE'
       AND (
            NEW.idusuario <> OLD.idusuario
            OR NEW.idmodalidade <> OLD.idmodalidade
            OR NEW.idmodelo <> OLD.idmodelo
       ) THEN
        RAISE EXCEPTION 'Usuário, modalidade e modelo de um registro histórico não podem ser alterados.';
    END IF;

    SELECT idmodalidade, idusuario, ativo
      INTO v_modelo
      FROM stridebr.modelos_modalidade
     WHERE idmodelo = NEW.idmodelo;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'Modelo inválido.';
    END IF;

    IF TG_OP = 'INSERT' AND NOT v_modelo.ativo THEN
        RAISE EXCEPTION 'Modelo inativo.';
    END IF;

    IF v_modelo.idmodalidade <> NEW.idmodalidade THEN
        RAISE EXCEPTION 'O modelo não pertence à modalidade.';
    END IF;

    IF v_modelo.idusuario IS NOT NULL
       AND v_modelo.idusuario <> NEW.idusuario THEN
        RAISE EXCEPTION 'O modelo pertence a outro usuário.';
    END IF;

    SELECT idusuario, ativo
      INTO v_modalidade
      FROM stridebr.modalidades
     WHERE idmodalidade = NEW.idmodalidade;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'Modalidade inválida.';
    END IF;

    IF TG_OP = 'INSERT' AND NOT v_modalidade.ativo THEN
        RAISE EXCEPTION 'Modalidade inativa.';
    END IF;

    IF v_modalidade.idusuario IS NOT NULL
       AND v_modalidade.idusuario <> NEW.idusuario THEN
        RAISE EXCEPTION 'A modalidade pertence a outro usuário.';
    END IF;

    IF TG_OP = 'INSERT' THEN
        v_cronograma_alterado := TRUE;
        v_treino_alterado := TRUE;
    ELSE
        v_cronograma_alterado := NEW.idcronograma IS DISTINCT FROM OLD.idcronograma;
        v_treino_alterado := NEW.idtreino_cronograma IS DISTINCT FROM OLD.idtreino_cronograma;
    END IF;

    IF v_cronograma_alterado
       AND NEW.idcronograma IS NOT NULL THEN
        SELECT idusuario
          INTO v_cronograma_usuario
          FROM stridebr.cronogramas
         WHERE idcronograma = NEW.idcronograma;

        IF NOT FOUND
           OR v_cronograma_usuario <> NEW.idusuario THEN
            RAISE EXCEPTION 'Cronograma inválido para este usuário.';
        END IF;
    END IF;

    IF NEW.idtreino_cronograma IS NOT NULL
       AND (
            v_treino_alterado
            OR (v_cronograma_alterado AND NEW.idcronograma IS NOT NULL)
       ) THEN
        SELECT t.idcronograma, c.idusuario
          INTO v_treino
          FROM stridebr.treinos_cronograma t
          JOIN stridebr.cronogramas c
            ON c.idcronograma = t.idcronograma
         WHERE t.idtreino = NEW.idtreino_cronograma;

        IF NOT FOUND
           OR v_treino.idusuario <> NEW.idusuario THEN
            RAISE EXCEPTION 'Treino de cronograma inválido para este usuário.';
        END IF;

        IF NEW.idcronograma IS NOT NULL
           AND v_treino.idcronograma <> NEW.idcronograma THEN
            RAISE EXCEPTION 'O treino não pertence ao cronograma informado.';
        END IF;
    END IF;

    RETURN NEW;
END;
$$;

COMMIT;
