BEGIN;

CREATE SCHEMA IF NOT EXISTS stridebr;
SET search_path TO stridebr, public;

-- =========================================================
-- Schema: Activities engine for StrideBR
-- File: stridebr_activities_schema.sql
-- Purpose: Create modalitites/model templates/fields/records/units/values
-- =========================================================

-- NOTE: This script assumes `usuarios` and `cronogramas` already exist in the database.

-- 1) Grandezas (quantities) and unidades (units)
CREATE TABLE IF NOT EXISTS grandezas (
    idgrandeza       VARCHAR(21) PRIMARY KEY,
    nome             VARCHAR(120) NOT NULL,
    slug             VARCHAR(120) NOT NULL,
    descricao        TEXT,
    ativo            BOOLEAN NOT NULL DEFAULT TRUE,
    data_criacao     TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT ck_grandezas_nome_nao_vazio CHECK (length(trim(nome)) > 0),
    CONSTRAINT ck_grandezas_slug_nao_vazio CHECK (length(trim(slug)) > 0)
);

CREATE TABLE IF NOT EXISTS unidades (
    idunidade         VARCHAR(21) PRIMARY KEY,
    idgrandeza        VARCHAR(21) NOT NULL,
    nome              VARCHAR(120) NOT NULL,
    simbolo           VARCHAR(40) NOT NULL,
    fator_para_base   NUMERIC(30,15) NOT NULL DEFAULT 1,
    ajuste_para_base  NUMERIC(30,15) NOT NULL DEFAULT 0,
    eh_base           BOOLEAN NOT NULL DEFAULT FALSE,
    ativo             BOOLEAN NOT NULL DEFAULT TRUE,
    data_criacao      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT fk_unidades_grandeza
        FOREIGN KEY (idgrandeza)
        REFERENCES grandezas (idgrandeza)
        ON DELETE RESTRICT,
    CONSTRAINT ck_unidades_nome_nao_vazio CHECK (length(trim(nome)) > 0),
    CONSTRAINT ck_unidades_simbolo_nao_vazio CHECK (length(trim(simbolo)) > 0),
    CONSTRAINT ck_unidades_fator_positivo CHECK (fator_para_base > 0),
    CONSTRAINT ck_unidades_base_coerente CHECK (
        NOT eh_base OR (fator_para_base = 1 AND ajuste_para_base = 0)
    )
);

CREATE UNIQUE INDEX IF NOT EXISTS ux_grandezas_slug
    ON grandezas (lower(slug));

CREATE UNIQUE INDEX IF NOT EXISTS ux_unidades_grandeza_simbolo
    ON unidades (idgrandeza, lower(simbolo));

CREATE UNIQUE INDEX IF NOT EXISTS ux_unidades_base_por_grandeza
    ON unidades (idgrandeza)
    WHERE eh_base;

CREATE INDEX IF NOT EXISTS ix_unidades_grandeza_ativo
    ON unidades (idgrandeza, ativo);

-- 2) Modalidades and modelos (templates/versions)
CREATE TABLE IF NOT EXISTS modalidades (
    idmodalidade      VARCHAR(21) PRIMARY KEY,
    idusuario         VARCHAR(21), -- NULL = system/default
    nome              VARCHAR(120) NOT NULL,
    slug              VARCHAR(120) NOT NULL,
    descricao         TEXT,
    ativo             BOOLEAN NOT NULL DEFAULT TRUE,
    data_criacao      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT fk_modalidades_usuario
        FOREIGN KEY (idusuario)
        REFERENCES usuarios (idusuario)
        ON DELETE RESTRICT,
    CONSTRAINT ck_modalidades_nome_nao_vazio CHECK (length(trim(nome)) > 0),
    CONSTRAINT ck_modalidades_slug_nao_vazio CHECK (length(trim(slug)) > 0)
);

CREATE TABLE IF NOT EXISTS modelos_modalidade (
    idmodelo          VARCHAR(21) PRIMARY KEY,
    idmodalidade      VARCHAR(21) NOT NULL,
    idusuario         VARCHAR(21), -- NULL = system template
    nome              VARCHAR(120) NOT NULL,
    slug              VARCHAR(120) NOT NULL,
    descricao         TEXT,
    versao            INTEGER NOT NULL,
    padrao            BOOLEAN NOT NULL DEFAULT FALSE,
    ativo             BOOLEAN NOT NULL DEFAULT TRUE,
    data_criacao      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT fk_modelos_modalidade
        FOREIGN KEY (idmodalidade)
        REFERENCES modalidades (idmodalidade)
        ON DELETE RESTRICT,
    CONSTRAINT fk_modelos_usuario
        FOREIGN KEY (idusuario)
        REFERENCES usuarios (idusuario)
        ON DELETE RESTRICT,
    CONSTRAINT ck_modelos_nome_nao_vazio CHECK (length(trim(nome)) > 0),
    CONSTRAINT ck_modelos_slug_nao_vazio CHECK (length(trim(slug)) > 0),
    CONSTRAINT ck_modelos_versao_positiva CHECK (versao > 0),
    CONSTRAINT uq_modelos_modalidade_modelo UNIQUE (idmodalidade, idmodelo)
);

CREATE UNIQUE INDEX IF NOT EXISTS ux_modalidades_slug_padrao
    ON modalidades (lower(slug))
    WHERE idusuario IS NULL;

CREATE UNIQUE INDEX IF NOT EXISTS ux_modalidades_slug_usuario
    ON modalidades (idusuario, lower(slug))
    WHERE idusuario IS NOT NULL;

CREATE UNIQUE INDEX IF NOT EXISTS ux_modelos_slug_padrao
    ON modelos_modalidade (idmodalidade, lower(slug))
    WHERE idusuario IS NULL;

CREATE UNIQUE INDEX IF NOT EXISTS ux_modelos_slug_usuario
    ON modelos_modalidade (idmodalidade, idusuario, lower(slug))
    WHERE idusuario IS NOT NULL;

CREATE UNIQUE INDEX IF NOT EXISTS ux_modelos_versao_padrao
    ON modelos_modalidade (idmodalidade, versao)
    WHERE idusuario IS NULL;

CREATE UNIQUE INDEX IF NOT EXISTS ux_modelos_versao_usuario
    ON modelos_modalidade (idmodalidade, idusuario, versao)
    WHERE idusuario IS NOT NULL;

CREATE UNIQUE INDEX IF NOT EXISTS ux_modelos_padrao_padrao
    ON modelos_modalidade (idmodalidade)
    WHERE padrao AND idusuario IS NULL;

CREATE UNIQUE INDEX IF NOT EXISTS ux_modelos_padrao_usuario
    ON modelos_modalidade (idmodalidade, idusuario)
    WHERE padrao AND idusuario IS NOT NULL;

CREATE INDEX IF NOT EXISTS ix_modelos_modalidade_ativo
    ON modelos_modalidade (idmodalidade, ativo, padrao, versao);

-- 3) Modalidades ativas por usuário (which template is active for user)
CREATE TABLE IF NOT EXISTS modalidades_usuario (
    idusuario         VARCHAR(21) NOT NULL,
    idmodalidade      VARCHAR(21) NOT NULL,
    idmodelo_ativo    VARCHAR(21) NOT NULL,
    ativo             BOOLEAN NOT NULL DEFAULT TRUE,
    data_ativacao     TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    data_desativacao  TIMESTAMPTZ,
    PRIMARY KEY (idusuario, idmodalidade),
    CONSTRAINT fk_modalidades_usuario_usuario
        FOREIGN KEY (idusuario)
        REFERENCES usuarios (idusuario)
        ON DELETE CASCADE,
    CONSTRAINT fk_modalidades_usuario_modalidade
        FOREIGN KEY (idmodalidade)
        REFERENCES modalidades (idmodalidade)
        ON DELETE RESTRICT,
    -- requires unique (idmodalidade,idmodelo) available on modelos_modalidade
    CONSTRAINT fk_modalidades_usuario_modelo_ativo
        FOREIGN KEY (idmodalidade, idmodelo_ativo)
        REFERENCES modelos_modalidade (idmodalidade, idmodelo)
        ON DELETE RESTRICT,
    CONSTRAINT ck_modalidades_usuario_datas CHECK (
        (ativo AND data_desativacao IS NULL)
        OR
        (NOT ativo AND data_desativacao IS NOT NULL)
    )
);

CREATE INDEX IF NOT EXISTS ix_modalidades_usuario_usuario_ativo
    ON modalidades_usuario (idusuario, ativo);

CREATE INDEX IF NOT EXISTS ix_modalidades_usuario_modalidade_ativo
    ON modalidades_usuario (idmodalidade, ativo);

-- 4) Campos do modelo e opções (fields definition)
CREATE TABLE IF NOT EXISTS campos_modelo (
    idcampo           VARCHAR(21) PRIMARY KEY,
    idmodelo          VARCHAR(21) NOT NULL,
    nome              VARCHAR(120) NOT NULL,
    slug              VARCHAR(120) NOT NULL,
    rotulo            VARCHAR(120) NOT NULL,
    tipo_campo        VARCHAR(20) NOT NULL,
    idgrandeza        VARCHAR(21),
    idunidade         VARCHAR(21),
    obrigatorio       BOOLEAN NOT NULL DEFAULT FALSE,
    ordem             INTEGER NOT NULL,
    ativo             BOOLEAN NOT NULL DEFAULT TRUE,
    data_criacao      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT fk_campos_modelo_modelo
        FOREIGN KEY (idmodelo)
        REFERENCES modelos_modalidade (idmodelo)
        ON DELETE RESTRICT,
    CONSTRAINT fk_campos_modelo_grandeza
        FOREIGN KEY (idgrandeza)
        REFERENCES grandezas (idgrandeza)
        ON DELETE RESTRICT,
    CONSTRAINT fk_campos_modelo_unidade
        FOREIGN KEY (idunidade)
        REFERENCES unidades (idunidade)
        ON DELETE RESTRICT,
    CONSTRAINT ck_campos_modelo_nome_nao_vazio CHECK (length(trim(nome)) > 0),
    CONSTRAINT ck_campos_modelo_slug_nao_vazio CHECK (length(trim(slug)) > 0),
    CONSTRAINT ck_campos_modelo_rotulo_nao_vazio CHECK (length(trim(rotulo)) > 0),
    CONSTRAINT ck_campos_modelo_ordem_positiva CHECK (ordem > 0),
    CONSTRAINT ck_campos_modelo_tipo
        CHECK (tipo_campo IN (
            'texto',
            'texto_longo',
            'inteiro',
            'decimal',
            'booleano',
            'data',
            'hora',
            'intervalo',
            'selecao'
        ))
);

CREATE TABLE IF NOT EXISTS campos_modelo_opcoes (
    idopcao           VARCHAR(21) PRIMARY KEY,
    idcampo           VARCHAR(21) NOT NULL,
    rotulo            VARCHAR(120) NOT NULL,
    valor             VARCHAR(120) NOT NULL,
    ordem             INTEGER NOT NULL,
    ativo             BOOLEAN NOT NULL DEFAULT TRUE,
    data_criacao      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT fk_opcoes_campo
        FOREIGN KEY (idcampo)
        REFERENCES campos_modelo (idcampo)
        ON DELETE RESTRICT,
    CONSTRAINT ck_opcoes_rotulo_nao_vazio CHECK (length(trim(rotulo)) > 0),
    CONSTRAINT ck_opcoes_valor_nao_vazio CHECK (length(trim(valor)) > 0),
    CONSTRAINT ck_opcoes_ordem_positiva CHECK (ordem > 0),
    CONSTRAINT uq_opcoes_campo_idopcao UNIQUE (idcampo, idopcao)
);

CREATE UNIQUE INDEX IF NOT EXISTS ux_campos_modelo_slug
    ON campos_modelo (idmodelo, lower(slug));

CREATE UNIQUE INDEX IF NOT EXISTS ux_campos_modelo_ordem
    ON campos_modelo (idmodelo, ordem);

CREATE INDEX IF NOT EXISTS ix_campos_modelo_modelo_ativo_ordem
    ON campos_modelo (idmodelo, ativo, ordem);

CREATE UNIQUE INDEX IF NOT EXISTS ux_opcoes_campo_valor
    ON campos_modelo_opcoes (idcampo, lower(valor));

CREATE UNIQUE INDEX IF NOT EXISTS ux_opcoes_campo_ordem
    ON campos_modelo_opcoes (idcampo, ordem);

CREATE INDEX IF NOT EXISTS ix_opcoes_campo_ativo_ordem
    ON campos_modelo_opcoes (idcampo, ativo, ordem);

-- 5) Registros de atividade, unidades and valores
CREATE TABLE IF NOT EXISTS registros_atividade (
    idregistro        VARCHAR(21) PRIMARY KEY,
    idusuario         VARCHAR(21) NOT NULL,
    idmodalidade      VARCHAR(21) NOT NULL,
    idmodelo          VARCHAR(21) NOT NULL,
    idcronograma      VARCHAR(21),
    titulo            VARCHAR(255),
    observacoes       TEXT,
    data_inicio       TIMESTAMPTZ NOT NULL,
    data_fim          TIMESTAMPTZ,
    status            VARCHAR(50) NOT NULL DEFAULT 'ativo',
    data_criacao      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT fk_registros_usuario
        FOREIGN KEY (idusuario)
        REFERENCES usuarios (idusuario)
        ON DELETE RESTRICT,
    CONSTRAINT fk_registros_modalidade
        FOREIGN KEY (idmodalidade)
        REFERENCES modalidades (idmodalidade)
        ON DELETE RESTRICT,
    -- ensure the model belongs to the modality: composite FK referencing unique pair (idmodalidade,idmodelo)
    CONSTRAINT fk_registros_modelo
        FOREIGN KEY (idmodalidade, idmodelo)
        REFERENCES modelos_modalidade (idmodalidade, idmodelo)
        ON DELETE RESTRICT,
    CONSTRAINT fk_registros_cronograma
        FOREIGN KEY (idcronograma)
        REFERENCES cronogramas (idcronograma)
        ON DELETE SET NULL,
    CONSTRAINT ck_registros_titulo_nao_vazio CHECK (titulo IS NULL OR length(trim(titulo)) > 0),
    CONSTRAINT ck_registros_status_nao_vazio CHECK (length(trim(status)) > 0),
    CONSTRAINT ck_registros_datas CHECK (data_fim IS NULL OR data_fim >= data_inicio)
);

CREATE INDEX IF NOT EXISTS ix_registros_usuario_data_inicio
    ON registros_atividade (idusuario, data_inicio DESC);

CREATE INDEX IF NOT EXISTS ix_registros_modalidade_data_inicio
    ON registros_atividade (idmodalidade, data_inicio DESC);

CREATE INDEX IF NOT EXISTS ix_registros_modelo_data_inicio
    ON registros_atividade (idmodelo, data_inicio DESC);

CREATE TABLE IF NOT EXISTS unidades_atividade (
    idunidade_atividade VARCHAR(21) PRIMARY KEY,
    idregistro          VARCHAR(21) NOT NULL,
    ordem               INTEGER NOT NULL,
    observacoes         TEXT,
    data_criacao        TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT fk_unidades_atividade_registro
        FOREIGN KEY (idregistro)
        REFERENCES registros_atividade (idregistro)
        ON DELETE CASCADE,
    CONSTRAINT ck_unidades_atividade_ordem_positiva CHECK (ordem > 0),
    CONSTRAINT uq_unidades_atividade_registro_ordem UNIQUE (idregistro, ordem)
);

CREATE INDEX IF NOT EXISTS ix_unidades_atividade_registro
    ON unidades_atividade (idregistro, ordem);

CREATE TABLE IF NOT EXISTS valores_unidade (
    idvalor             VARCHAR(21) PRIMARY KEY,
    idunidade_atividade VARCHAR(21) NOT NULL,
    idcampo             VARCHAR(21) NOT NULL,
    valor_texto         TEXT,
    valor_inteiro       BIGINT,
    valor_decimal       NUMERIC(30,10),
    valor_booleano      BOOLEAN,
    valor_data          DATE,
    valor_hora          TIME WITHOUT TIME ZONE,
    valor_intervalo     INTERVAL,
    idopcao             VARCHAR(21),
    valor_normalizado   NUMERIC(30,15),
    data_criacao        TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT fk_valores_unidade
        FOREIGN KEY (idunidade_atividade)
        REFERENCES unidades_atividade (idunidade_atividade)
        ON DELETE CASCADE,
    CONSTRAINT fk_valores_campo
        FOREIGN KEY (idcampo)
        REFERENCES campos_modelo (idcampo)
        ON DELETE RESTRICT,
    -- composite FK to ensure idopcao belongs to idcampo; requires unique (idcampo,idopcao) in campos_modelo_opcoes
    CONSTRAINT fk_valores_opcao_campo
        FOREIGN KEY (idcampo, idopcao)
        REFERENCES campos_modelo_opcoes (idcampo, idopcao)
        ON DELETE RESTRICT,
    CONSTRAINT ck_valores_um_unico_tipo CHECK (
        num_nonnulls(
            valor_texto,
            valor_inteiro,
            valor_decimal,
            valor_booleano,
            valor_data,
            valor_hora,
            valor_intervalo,
            idopcao
        ) = 1
    )
);

CREATE INDEX IF NOT EXISTS ix_valores_unidade_unidade
    ON valores_unidade (idunidade_atividade);

CREATE INDEX IF NOT EXISTS ix_valores_unidade_campo
    ON valores_unidade (idcampo);

CREATE INDEX IF NOT EXISTS ix_valores_unidade_campo_normalizado
    ON valores_unidade (idcampo, valor_normalizado);

CREATE INDEX IF NOT EXISTS ix_valores_unidade_campo_opcao
    ON valores_unidade (idcampo, idopcao);

-- 6) Functions & triggers (validation and immutability)
-- (Functions implement the checks we discussed: model->modalidade coherence, field->model coherence,
-- value typing and normalization, and immutability protections.)

-- fn_valida_modalidade_usuario_modelo: ensures model belongs to modality and (if user-specific) to user
CREATE OR REPLACE FUNCTION stridebr.fn_valida_modalidade_usuario_modelo()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
DECLARE
    v_idmodalidade_modelo VARCHAR(21);
    v_idusuario_modelo    VARCHAR(21);
    v_ativo_modelo        BOOLEAN;
BEGIN
    SELECT idmodalidade, idusuario, ativo
    INTO v_idmodalidade_modelo, v_idusuario_modelo, v_ativo_modelo
    FROM modelos_modalidade
    WHERE idmodelo = NEW.idmodelo_ativo;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'Modelo ativo % não existe.', NEW.idmodelo_ativo;
    END IF;

    IF NOT v_ativo_modelo THEN
        RAISE EXCEPTION 'O modelo % está inativo.', NEW.idmodelo_ativo;
    END IF;

    IF v_idmodalidade_modelo <> NEW.idmodalidade THEN
        RAISE EXCEPTION 'O modelo % não pertence à modalidade %.', NEW.idmodelo_ativo, NEW.idmodalidade;
    END IF;

    IF v_idusuario_modelo IS NOT NULL AND v_idusuario_modelo <> NEW.idusuario THEN
        RAISE EXCEPTION 'O modelo % é personalizado de outro usuário.', NEW.idmodelo_ativo;
    END IF;

    RETURN NEW;
END;
$$;

-- fn_valida_registro_atividade: ensure model exists and belongs to modality; allow historical records even if modality not active
CREATE OR REPLACE FUNCTION stridebr.fn_valida_registro_atividade()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
DECLARE
    v_idusuario_modelo VARCHAR(21);
BEGIN
    IF TG_OP = 'UPDATE' THEN
        IF NEW.idusuario <> OLD.idusuario
           OR NEW.idmodalidade <> OLD.idmodalidade
           OR NEW.idmodelo <> OLD.idmodelo THEN
            RAISE EXCEPTION 'O vínculo do registro com usuário/modalidade/modelo não pode ser alterado.';
        END IF;
    END IF;

    SELECT idusuario
    INTO v_idusuario_modelo
    FROM modelos_modalidade
    WHERE idmodelo = NEW.idmodelo;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'Modelo % não existe.', NEW.idmodelo;
    END IF;

    IF v_idusuario_modelo IS NOT NULL AND v_idusuario_modelo <> NEW.idusuario THEN
        RAISE EXCEPTION 'O modelo % é personalizado de outro usuário.', NEW.idmodelo;
    END IF;

    RETURN NEW;
END;
$$;

-- fn_valida_campos_modelo: ensure types/coherence with grandeza/unidade
CREATE OR REPLACE FUNCTION stridebr.fn_valida_campos_modelo()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
DECLARE
    v_qtd_unidades_base INTEGER;
BEGIN
    IF NEW.tipo_campo IN ('texto', 'texto_longo', 'booleano', 'data', 'hora', 'selecao') THEN
        IF NEW.idgrandeza IS NOT NULL OR NEW.idunidade IS NOT NULL THEN
            RAISE EXCEPTION 'Campo do tipo % não pode usar grandeza nem unidade.', NEW.tipo_campo;
        END IF;
    ELSE
        IF NEW.idunidade IS NOT NULL AND NEW.idgrandeza IS NULL THEN
            RAISE EXCEPTION 'Se a unidade for informada, a grandeza também deve ser informada.';
        END IF;

        IF NEW.idgrandeza IS NOT NULL THEN
            SELECT COUNT(*)
            INTO v_qtd_unidades_base
            FROM unidades
            WHERE idgrandeza = NEW.idgrandeza
              AND eh_base = TRUE
              AND ativo = TRUE;

            IF v_qtd_unidades_base = 0 THEN
                RAISE EXCEPTION 'A grandeza % precisa ter uma unidade base ativa.', NEW.idgrandeza;
            END IF;

            IF NEW.idunidade IS NOT NULL THEN
                IF NOT EXISTS (
                    SELECT 1
                    FROM unidades
                    WHERE idunidade = NEW.idunidade
                      AND idgrandeza = NEW.idgrandeza
                      AND ativo = TRUE
                ) THEN
                    RAISE EXCEPTION 'A unidade % não pertence à grandeza % ou está inativa.', NEW.idunidade, NEW.idgrandeza;
                END IF;
            END IF;
        END IF;
    END IF;

    RETURN NEW;
END;
$$;

-- fn_valida_opcao_modelo: ensure option only for selection fields
CREATE OR REPLACE FUNCTION stridebr.fn_valida_opcao_modelo()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
DECLARE
    v_tipo_campo VARCHAR(20);
BEGIN
    SELECT tipo_campo
    INTO v_tipo_campo
    FROM campos_modelo
    WHERE idcampo = NEW.idcampo
      AND ativo = TRUE;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'Campo % não existe ou está inativo.', NEW.idcampo;
    END IF;

    IF v_tipo_campo <> 'selecao' THEN
        RAISE EXCEPTION 'Opções só podem existir para campos do tipo selecao.';
    END IF;

    RETURN NEW;
END;
$$;

-- fn_valida_valor_unidade: ensure value matches field type, field belongs to record's model, compute normalized value when applicable
CREATE OR REPLACE FUNCTION stridebr.fn_valida_valor_unidade()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
DECLARE
    v_tipo_campo      VARCHAR(20);
    v_idgrandeza      VARCHAR(21);
    v_idunidade       VARCHAR(21);
    v_modelo_campo    VARCHAR(21);
    v_modelo_registro VARCHAR(21);
    v_factor          NUMERIC(30,15) := 1;
    v_ajuste          NUMERIC(30,15) := 0;
BEGIN
    IF TG_OP = 'UPDATE' THEN
        IF NEW.idunidade_atividade <> OLD.idunidade_atividade
           OR NEW.idcampo <> OLD.idcampo THEN
            RAISE EXCEPTION 'O vínculo do valor com unidade de atividade e campo não pode ser alterado.';
        END IF;
    END IF;

    SELECT cm.tipo_campo, cm.idgrandeza, cm.idunidade, cm.idmodelo
    INTO v_tipo_campo, v_idgrandeza, v_idunidade, v_modelo_campo
    FROM campos_modelo cm
    WHERE cm.idcampo = NEW.idcampo
      AND cm.ativo = TRUE;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'Campo % não existe ou está inativo.', NEW.idcampo;
    END IF;

    SELECT ra.idmodelo
    INTO v_modelo_registro
    FROM unidades_atividade ua
    JOIN registros_atividade ra ON ra.idregistro = ua.idregistro
    WHERE ua.idunidade_atividade = NEW.idunidade_atividade;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'Unidade de atividade % não existe.', NEW.idunidade_atividade;
    END IF;

    IF v_modelo_campo <> v_modelo_registro THEN
        RAISE EXCEPTION 'O campo % não pertence ao modelo do registro.', NEW.idcampo;
    END IF;

    CASE v_tipo_campo
        WHEN 'texto', 'texto_longo' THEN
            IF NEW.valor_texto IS NULL THEN
                RAISE EXCEPTION 'Campo % exige valor_texto.', NEW.idcampo;
            END IF;
            NEW.valor_normalizado := NULL;

        WHEN 'inteiro' THEN
            IF NEW.valor_inteiro IS NULL THEN
                RAISE EXCEPTION 'Campo % exige valor_inteiro.', NEW.idcampo;
            END IF;

            IF v_idgrandeza IS NULL THEN
                NEW.valor_normalizado := NULL;
            ELSE
                IF v_idunidade IS NULL THEN
                    SELECT fator_para_base, ajuste_para_base
                    INTO v_factor, v_ajuste
                    FROM unidades
                    WHERE idgrandeza = v_idgrandeza
                      AND eh_base = TRUE
                      AND ativo = TRUE;
                ELSE
                    SELECT fator_para_base, ajuste_para_base
                    INTO v_factor, v_ajuste
                    FROM unidades
                    WHERE idunidade = v_idunidade
                      AND ativo = TRUE;
                END IF;

                IF NOT FOUND THEN
                    RAISE EXCEPTION 'Não foi possível resolver a unidade do campo %.', NEW.idcampo;
                END IF;

                NEW.valor_normalizado := (NEW.valor_inteiro::NUMERIC(30,15) * v_factor) + v_ajuste;
            END IF;

        WHEN 'decimal' THEN
            IF NEW.valor_decimal IS NULL THEN
                RAISE EXCEPTION 'Campo % exige valor_decimal.', NEW.idcampo;
            END IF;

            IF v_idgrandeza IS NULL THEN
                NEW.valor_normalizado := NULL;
            ELSE
                IF v_idunidade IS NULL THEN
                    SELECT fator_para_base, ajuste_para_base
                    INTO v_factor, v_ajuste
                    FROM unidades
                    WHERE idgrandeza = v_idgrandeza
                      AND eh_base = TRUE
                      AND ativo = TRUE;
                ELSE
                    SELECT fator_para_base, ajuste_para_base
                    INTO v_factor, v_ajuste
                    FROM unidades
                    WHERE idunidade = v_idunidade
                      AND ativo = TRUE;
                END IF;

                IF NOT FOUND THEN
                    RAISE EXCEPTION 'Não foi possível resolver a unidade do campo %.', NEW.idcampo;
                END IF;

                NEW.valor_normalizado := (NEW.valor_decimal::NUMERIC(30,15) * v_factor) + v_ajuste;
            END IF;

        WHEN 'booleano' THEN
            IF NEW.valor_booleano IS NULL THEN
                RAISE EXCEPTION 'Campo % exige valor_booleano.', NEW.idcampo;
            END IF;
            NEW.valor_normalizado := NULL;

        WHEN 'data' THEN
            IF NEW.valor_data IS NULL THEN
                RAISE EXCEPTION 'Campo % exige valor_data.', NEW.idcampo;
            END IF;
            NEW.valor_normalizado := NULL;

        WHEN 'hora' THEN
            IF NEW.valor_hora IS NULL THEN
                RAISE EXCEPTION 'Campo % exige valor_hora.', NEW.idcampo;
            END IF;
            NEW.valor_normalizado := NULL;

        WHEN 'intervalo' THEN
            IF NEW.valor_intervalo IS NULL THEN
                RAISE EXCEPTION 'Campo % exige valor_intervalo.', NEW.idcampo;
            END IF;

            IF v_idgrandeza IS NULL THEN
                NEW.valor_normalizado := NULL;
            ELSE
                IF v_idunidade IS NULL THEN
                    SELECT fator_para_base, ajuste_para_base
                    INTO v_factor, v_ajuste
                    FROM unidades
                    WHERE idgrandeza = v_idgrandeza
                      AND eh_base = TRUE
                      AND ativo = TRUE;
                ELSE
                    SELECT fator_para_base, ajuste_para_base
                    INTO v_factor, v_ajuste
                    FROM unidades
                    WHERE idunidade = v_idunidade
                      AND ativo = TRUE;
                END IF;

                IF NOT FOUND THEN
                    RAISE EXCEPTION 'Não foi possível resolver a unidade do campo %.', NEW.idcampo;
                END IF;

                NEW.valor_normalizado := (EXTRACT(EPOCH FROM NEW.valor_intervalo)::NUMERIC(30,15) * v_factor) + v_ajuste;
            END IF;

        WHEN 'selecao' THEN
            IF NEW.idopcao IS NULL THEN
                RAISE EXCEPTION 'Campo % exige idopcao.', NEW.idcampo;
            END IF;
            NEW.valor_normalizado := NULL;

        ELSE
            RAISE EXCEPTION 'Tipo de campo % não suportado.', v_tipo_campo;
    END CASE;

    RETURN NEW;
END;
$$;

-- Immutability triggers: prevent changes to models/fields/options/grandezas/unidades once used historically
CREATE OR REPLACE FUNCTION stridebr.fn_bloqueia_modelo_usado()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM registros_atividade WHERE idmodelo = COALESCE(OLD.idmodelo, NEW.idmodelo)
    ) THEN
        RAISE EXCEPTION 'Modelo % já foi usado por atividades históricas e não pode ser alterado nem removido.', COALESCE(OLD.idmodelo, NEW.idmodelo);
    END IF;

    RETURN COALESCE(NEW, OLD);
END;
$$;

CREATE OR REPLACE FUNCTION stridebr.fn_bloqueia_campo_usado()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
DECLARE
    v_idmodelo VARCHAR(21);
BEGIN
    IF TG_OP = 'DELETE' THEN
        v_idmodelo := OLD.idmodelo;
    ELSE
        v_idmodelo := NEW.idmodelo;
    END IF;

    IF v_idmodelo IS NULL THEN
        RAISE EXCEPTION 'Campo % não encontrado.', COALESCE(OLD.idcampo, NEW.idcampo);
    END IF;

    IF EXISTS (
        SELECT 1
        FROM registros_atividade ra
        WHERE ra.idmodelo = v_idmodelo
    ) THEN
        RAISE EXCEPTION 'Campo % pertence a um modelo já usado historicamente e não pode ser alterado nem removido.', COALESCE(OLD.idcampo, NEW.idcampo);
    END IF;

    IF EXISTS (
        SELECT 1
        FROM valores_unidade vu
        WHERE vu.idcampo = COALESCE(OLD.idcampo, NEW.idcampo)
    ) THEN
        RAISE EXCEPTION 'Campo % já foi usado por valores históricos e não pode ser alterado nem removido.', COALESCE(OLD.idcampo, NEW.idcampo);
    END IF;

    RETURN COALESCE(NEW, OLD);
END;
$$;

CREATE OR REPLACE FUNCTION stridebr.fn_bloqueia_opcao_usada()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
DECLARE
    v_idmodelo VARCHAR(21);
    v_idcampo  VARCHAR(21);
BEGIN
    IF TG_OP = 'DELETE' THEN
        v_idcampo := OLD.idcampo;
    ELSE
        v_idcampo := NEW.idcampo;
    END IF;

    SELECT cm.idmodelo
    INTO v_idmodelo
    FROM campos_modelo cm
    WHERE cm.idcampo = v_idcampo;

    IF v_idmodelo IS NULL THEN
        RAISE EXCEPTION 'Opção % não encontrada.', COALESCE(OLD.idopcao, NEW.idopcao);
    END IF;

    IF EXISTS (
        SELECT 1
        FROM registros_atividade ra
        WHERE ra.idmodelo = v_idmodelo
    ) THEN
        RAISE EXCEPTION 'Opção % pertence a um modelo já usado historicamente e não pode ser alterada nem removida.', COALESCE(OLD.idopcao, NEW.idopcao);
    END IF;

    IF EXISTS (
        SELECT 1 FROM valores_unidade WHERE idopcao = COALESCE(OLD.idopcao, NEW.idopcao)
    ) THEN
        RAISE EXCEPTION 'Opção % já foi usada historicamente e não pode ser alterada nem removida.', COALESCE(OLD.idopcao, NEW.idopcao);
    END IF;

    RETURN COALESCE(NEW, OLD);
END;
$$;

CREATE OR REPLACE FUNCTION stridebr.fn_bloqueia_grandeza_usada()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM registros_atividade ra
        JOIN campos_modelo cm ON cm.idmodelo = ra.idmodelo
        WHERE cm.idgrandeza = COALESCE(OLD.idgrandeza, NEW.idgrandeza)
    ) THEN
        RAISE EXCEPTION 'Grandeza % já participou de valores históricos e não pode ser alterada nem removida.', COALESCE(OLD.idgrandeza, NEW.idgrandeza);
    END IF;

    RETURN COALESCE(NEW, OLD);
END;
$$;

CREATE OR REPLACE FUNCTION stridebr.fn_bloqueia_unidade_usada()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM registros_atividade ra
        JOIN campos_modelo cm ON cm.idmodelo = ra.idmodelo
        WHERE cm.idunidade = COALESCE(OLD.idunidade, NEW.idunidade)
    ) THEN
        RAISE EXCEPTION 'Unidade % já participou de valores históricos e não pode ser alterada nem removida.', COALESCE(OLD.idunidade, NEW.idunidade);
    END IF;

    RETURN COALESCE(NEW, OLD);
END;
$$;

CREATE OR REPLACE FUNCTION stridebr.fn_bloqueia_unidade_atividade_movida()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
    IF NEW.idregistro <> OLD.idregistro THEN
        IF EXISTS (
            SELECT 1
            FROM valores_unidade
            WHERE idunidade_atividade = OLD.idunidade_atividade
        ) THEN
            RAISE EXCEPTION 'A unidade de atividade % não pode ser movida para outro registro porque já possui valores.', OLD.idunidade_atividade;
        END IF;
    END IF;

    RETURN NEW;
END;
$$;

-- Attach triggers
DROP TRIGGER IF EXISTS tg_modalidades_usuario_validacao ON modalidades_usuario;
CREATE TRIGGER tg_modalidades_usuario_validacao
BEFORE INSERT OR UPDATE ON modalidades_usuario
FOR EACH ROW
EXECUTE FUNCTION stridebr.fn_valida_modalidade_usuario_modelo();

DROP TRIGGER IF EXISTS tg_registros_atividade_validacao ON registros_atividade;
CREATE TRIGGER tg_registros_atividade_validacao
BEFORE INSERT OR UPDATE ON registros_atividade
FOR EACH ROW
EXECUTE FUNCTION stridebr.fn_valida_registro_atividade();

DROP TRIGGER IF EXISTS tg_campos_modelo_validacao ON campos_modelo;
CREATE TRIGGER tg_campos_modelo_validacao
BEFORE INSERT OR UPDATE ON campos_modelo
FOR EACH ROW
EXECUTE FUNCTION stridebr.fn_valida_campos_modelo();

DROP TRIGGER IF EXISTS tg_opcoes_modelo_validacao ON campos_modelo_opcoes;
CREATE TRIGGER tg_opcoes_modelo_validacao
BEFORE INSERT OR UPDATE ON campos_modelo_opcoes
FOR EACH ROW
EXECUTE FUNCTION stridebr.fn_valida_opcao_modelo();

DROP TRIGGER IF EXISTS tg_valores_unidade_validacao ON valores_unidade;
CREATE TRIGGER tg_valores_unidade_validacao
BEFORE INSERT OR UPDATE ON valores_unidade
FOR EACH ROW
EXECUTE FUNCTION stridebr.fn_valida_valor_unidade();

DROP TRIGGER IF EXISTS tg_modelos_immutability ON modelos_modalidade;
CREATE TRIGGER tg_modelos_immutability
BEFORE UPDATE OR DELETE ON modelos_modalidade
FOR EACH ROW
EXECUTE FUNCTION stridebr.fn_bloqueia_modelo_usado();

DROP TRIGGER IF EXISTS tg_campos_immutability ON campos_modelo;
CREATE TRIGGER tg_campos_immutability
BEFORE INSERT OR UPDATE OR DELETE ON campos_modelo
FOR EACH ROW
EXECUTE FUNCTION stridebr.fn_bloqueia_campo_usado();

DROP TRIGGER IF EXISTS tg_opcoes_immutability ON campos_modelo_opcoes;
CREATE TRIGGER tg_opcoes_immutability
BEFORE INSERT OR UPDATE OR DELETE ON campos_modelo_opcoes
FOR EACH ROW
EXECUTE FUNCTION stridebr.fn_bloqueia_opcao_usada();

DROP TRIGGER IF EXISTS tg_grandezas_immutability ON grandezas;
CREATE TRIGGER tg_grandezas_immutability
BEFORE UPDATE OR DELETE ON grandezas
FOR EACH ROW
EXECUTE FUNCTION stridebr.fn_bloqueia_grandeza_usada();

DROP TRIGGER IF EXISTS tg_unidades_immutability ON unidades;
CREATE TRIGGER tg_unidades_immutability
BEFORE UPDATE OR DELETE ON unidades
FOR EACH ROW
EXECUTE FUNCTION stridebr.fn_bloqueia_unidade_usada();

DROP TRIGGER IF EXISTS tg_unidades_atividade_update_guard ON unidades_atividade;
CREATE TRIGGER tg_unidades_atividade_update_guard
BEFORE UPDATE ON unidades_atividade
FOR EACH ROW
EXECUTE FUNCTION stridebr.fn_bloqueia_unidade_atividade_movida();

COMMIT;
