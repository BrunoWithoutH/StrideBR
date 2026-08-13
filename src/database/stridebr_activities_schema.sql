BEGIN;

SET search_path TO stridebr, public;

CREATE TABLE grandezas (
    idgrandeza VARCHAR(21) PRIMARY KEY,
    nome VARCHAR(120) NOT NULL CHECK (length(trim(nome)) > 0),
    slug VARCHAR(120) NOT NULL CHECK (length(trim(slug)) > 0),
    descricao TEXT,
    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    data_criacao TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE UNIQUE INDEX ux_grandezas_slug ON grandezas (lower(slug));

CREATE TABLE unidades (
    idunidade VARCHAR(21) PRIMARY KEY,
    idgrandeza VARCHAR(21) NOT NULL REFERENCES grandezas(idgrandeza) ON DELETE RESTRICT,
    nome VARCHAR(120) NOT NULL CHECK (length(trim(nome)) > 0),
    simbolo VARCHAR(40) NOT NULL CHECK (length(trim(simbolo)) > 0),
    fator_para_base NUMERIC(30,15) NOT NULL DEFAULT 1 CHECK (fator_para_base > 0),
    ajuste_para_base NUMERIC(30,15) NOT NULL DEFAULT 0,
    eh_base BOOLEAN NOT NULL DEFAULT FALSE,
    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    data_criacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT ck_unidade_base CHECK (NOT eh_base OR (fator_para_base = 1 AND ajuste_para_base = 0))
);

CREATE UNIQUE INDEX ux_unidades_grandeza_simbolo ON unidades (idgrandeza, lower(simbolo));
CREATE UNIQUE INDEX ux_unidade_base_grandeza ON unidades (idgrandeza) WHERE eh_base;

CREATE TABLE modalidades (
    idmodalidade VARCHAR(21) PRIMARY KEY,
    idusuario VARCHAR(21) REFERENCES usuarios(idusuario) ON DELETE CASCADE,
    nome VARCHAR(120) NOT NULL CHECK (length(trim(nome)) > 0),
    slug VARCHAR(120) NOT NULL CHECK (length(trim(slug)) > 0),
    descricao TEXT,
    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    visibilidade VARCHAR(10) NOT NULL DEFAULT 'privado' CHECK (visibilidade IN ('privado', 'amigos', 'publico')),
    status_publicacao VARCHAR(20) NOT NULL DEFAULT 'privado' CHECK (status_publicacao IN ('privado', 'pendente', 'publicado')),
    data_criacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    data_atualizacao TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE UNIQUE INDEX ux_modalidade_sistema_slug ON modalidades (lower(slug)) WHERE idusuario IS NULL;
CREATE UNIQUE INDEX ux_modalidade_usuario_slug ON modalidades (idusuario, lower(slug)) WHERE idusuario IS NOT NULL;

CREATE TABLE modelos_modalidade (
    idmodelo VARCHAR(21) PRIMARY KEY,
    idmodalidade VARCHAR(21) NOT NULL REFERENCES modalidades(idmodalidade) ON DELETE RESTRICT,
    idusuario VARCHAR(21) REFERENCES usuarios(idusuario) ON DELETE CASCADE,
    idmodelo_anterior VARCHAR(21) REFERENCES modelos_modalidade(idmodelo) ON DELETE RESTRICT,
    nome VARCHAR(120) NOT NULL CHECK (length(trim(nome)) > 0),
    slug VARCHAR(120) NOT NULL CHECK (length(trim(slug)) > 0),
    descricao TEXT,
    tipo_unidade_padrao VARCHAR(30) NOT NULL DEFAULT 'unidade' CHECK (length(trim(tipo_unidade_padrao)) > 0),
    rotulo_unidade VARCHAR(60) NOT NULL DEFAULT 'Unidade' CHECK (length(trim(rotulo_unidade)) > 0),
    permite_multiplas_unidades BOOLEAN NOT NULL DEFAULT FALSE,
    versao INTEGER NOT NULL DEFAULT 1 CHECK (versao > 0),
    padrao BOOLEAN NOT NULL DEFAULT FALSE,
    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    visibilidade VARCHAR(10) NOT NULL DEFAULT 'privado' CHECK (visibilidade IN ('privado', 'amigos', 'publico')),
    status_publicacao VARCHAR(20) NOT NULL DEFAULT 'privado' CHECK (status_publicacao IN ('privado', 'pendente', 'publicado')),
    data_criacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT uq_modelo_modalidade_id UNIQUE (idmodalidade, idmodelo)
);

CREATE UNIQUE INDEX ux_modelo_sistema_versao ON modelos_modalidade (idmodalidade, lower(slug), versao) WHERE idusuario IS NULL;
CREATE UNIQUE INDEX ux_modelo_usuario_versao ON modelos_modalidade (idmodalidade, idusuario, lower(slug), versao) WHERE idusuario IS NOT NULL;
CREATE UNIQUE INDEX ux_modelo_padrao_sistema ON modelos_modalidade (idmodalidade) WHERE padrao AND idusuario IS NULL AND ativo;
CREATE UNIQUE INDEX ux_modelo_padrao_usuario ON modelos_modalidade (idmodalidade, idusuario) WHERE padrao AND idusuario IS NOT NULL AND ativo;

CREATE TABLE modalidades_usuario (
    idusuario VARCHAR(21) NOT NULL REFERENCES usuarios(idusuario) ON DELETE CASCADE,
    idmodalidade VARCHAR(21) NOT NULL REFERENCES modalidades(idmodalidade) ON DELETE RESTRICT,
    idmodelo_ativo VARCHAR(21),
    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    data_ativacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    data_desativacao TIMESTAMPTZ,
    PRIMARY KEY (idusuario, idmodalidade),
    FOREIGN KEY (idmodalidade, idmodelo_ativo) REFERENCES modelos_modalidade(idmodalidade, idmodelo) ON DELETE RESTRICT,
    CONSTRAINT ck_modalidade_usuario_datas CHECK ((ativo AND data_desativacao IS NULL) OR (NOT ativo AND data_desativacao IS NOT NULL))
);

CREATE TABLE campos_modelo (
    idcampo VARCHAR(21) PRIMARY KEY,
    idmodelo VARCHAR(21) NOT NULL REFERENCES modelos_modalidade(idmodelo) ON DELETE RESTRICT,
    nome VARCHAR(120) NOT NULL CHECK (length(trim(nome)) > 0),
    slug VARCHAR(120) NOT NULL CHECK (length(trim(slug)) > 0),
    rotulo VARCHAR(120) NOT NULL CHECK (length(trim(rotulo)) > 0),
    tipo_campo VARCHAR(20) NOT NULL CHECK (tipo_campo IN ('texto', 'texto_longo', 'inteiro', 'decimal', 'booleano', 'data', 'hora', 'intervalo', 'selecao')),
    escopo VARCHAR(10) NOT NULL DEFAULT 'unidade' CHECK (escopo IN ('registro', 'unidade')),
    idgrandeza VARCHAR(21) REFERENCES grandezas(idgrandeza) ON DELETE RESTRICT,
    idunidade VARCHAR(21) REFERENCES unidades(idunidade) ON DELETE RESTRICT,
    obrigatorio BOOLEAN NOT NULL DEFAULT FALSE,
    ordem INTEGER NOT NULL CHECK (ordem > 0),
    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    data_criacao TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE UNIQUE INDEX ux_campos_modelo_slug ON campos_modelo (idmodelo, lower(slug));
CREATE UNIQUE INDEX ux_campos_modelo_ordem ON campos_modelo (idmodelo, ordem);

CREATE TABLE campos_modelo_opcoes (
    idopcao VARCHAR(21) PRIMARY KEY,
    idcampo VARCHAR(21) NOT NULL REFERENCES campos_modelo(idcampo) ON DELETE RESTRICT,
    rotulo VARCHAR(120) NOT NULL CHECK (length(trim(rotulo)) > 0),
    valor VARCHAR(120) NOT NULL CHECK (length(trim(valor)) > 0),
    ordem INTEGER NOT NULL CHECK (ordem > 0),
    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    data_criacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (idcampo, idopcao)
);

CREATE UNIQUE INDEX ux_opcao_campo_valor ON campos_modelo_opcoes (idcampo, lower(valor));
CREATE UNIQUE INDEX ux_opcao_campo_ordem ON campos_modelo_opcoes (idcampo, ordem);

CREATE TABLE exercicios_modalidades (
    idexercicio VARCHAR(21) NOT NULL REFERENCES exercicios(idexercicio) ON DELETE CASCADE,
    idmodalidade VARCHAR(21) NOT NULL REFERENCES modalidades(idmodalidade) ON DELETE CASCADE,
    PRIMARY KEY (idexercicio, idmodalidade)
);

CREATE TABLE registros_atividade (
    idregistro VARCHAR(21) PRIMARY KEY,
    idusuario VARCHAR(21) NOT NULL REFERENCES usuarios(idusuario) ON DELETE CASCADE,
    idmodalidade VARCHAR(21) NOT NULL REFERENCES modalidades(idmodalidade) ON DELETE RESTRICT,
    idmodelo VARCHAR(21) NOT NULL,
    idcronograma VARCHAR(21) REFERENCES cronogramas(idcronograma) ON DELETE SET NULL,
    idtreino_cronograma VARCHAR(21) REFERENCES treinos_cronograma(idtreino) ON DELETE SET NULL,
    titulo VARCHAR(255),
    observacoes TEXT,
    data_inicio TIMESTAMPTZ NOT NULL,
    data_fim TIMESTAMPTZ,
    status VARCHAR(20) NOT NULL DEFAULT 'concluido' CHECK (status IN ('rascunho', 'ativo', 'concluido', 'cancelado')),
    visibilidade VARCHAR(10) NOT NULL DEFAULT 'privado' CHECK (visibilidade IN ('privado', 'amigos', 'publico')),
    origem VARCHAR(20) NOT NULL DEFAULT 'manual' CHECK (origem IN ('manual', 'gps', 'importacao', 'api')),
    data_criacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    data_atualizacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    FOREIGN KEY (idmodalidade, idmodelo) REFERENCES modelos_modalidade(idmodalidade, idmodelo) ON DELETE RESTRICT,
    CONSTRAINT ck_registro_datas CHECK (data_fim IS NULL OR data_fim >= data_inicio),
    CONSTRAINT ck_registro_titulo CHECK (titulo IS NULL OR length(trim(titulo)) > 0)
);

CREATE INDEX ix_registros_usuario_data ON registros_atividade (idusuario, data_inicio DESC);
CREATE INDEX ix_registros_modalidade_data ON registros_atividade (idmodalidade, data_inicio DESC);

CREATE TABLE unidades_atividade (
    idunidade_atividade VARCHAR(21) PRIMARY KEY,
    idregistro VARCHAR(21) NOT NULL REFERENCES registros_atividade(idregistro) ON DELETE CASCADE,
    ordem INTEGER NOT NULL CHECK (ordem > 0),
    tipo_unidade VARCHAR(30) NOT NULL DEFAULT 'unidade' CHECK (length(trim(tipo_unidade)) > 0),
    rotulo VARCHAR(120),
    observacoes TEXT,
    data_criacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (idregistro, ordem)
);

CREATE TABLE valores_atividade (
    idvalor VARCHAR(21) PRIMARY KEY,
    idregistro VARCHAR(21) NOT NULL REFERENCES registros_atividade(idregistro) ON DELETE CASCADE,
    idunidade_atividade VARCHAR(21) REFERENCES unidades_atividade(idunidade_atividade) ON DELETE CASCADE,
    idcampo VARCHAR(21) NOT NULL REFERENCES campos_modelo(idcampo) ON DELETE RESTRICT,
    valor_texto TEXT,
    valor_inteiro BIGINT,
    valor_decimal NUMERIC(30,10),
    valor_booleano BOOLEAN,
    valor_data DATE,
    valor_hora TIME,
    valor_intervalo INTERVAL,
    idopcao VARCHAR(21),
    valor_normalizado NUMERIC(30,15),
    data_criacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    FOREIGN KEY (idcampo, idopcao) REFERENCES campos_modelo_opcoes(idcampo, idopcao) ON DELETE RESTRICT,
    CONSTRAINT ck_valores_atividade_tipo CHECK (num_nonnulls(valor_texto, valor_inteiro, valor_decimal, valor_booleano, valor_data, valor_hora, valor_intervalo, idopcao) = 1)
);

CREATE UNIQUE INDEX ux_valor_registro_campo ON valores_atividade (idregistro, idcampo) WHERE idunidade_atividade IS NULL;
CREATE UNIQUE INDEX ux_valor_unidade_campo ON valores_atividade (idunidade_atividade, idcampo) WHERE idunidade_atividade IS NOT NULL;
CREATE INDEX ix_valores_campo_normalizado ON valores_atividade (idcampo, valor_normalizado);

CREATE TABLE rotas_atividade (
    idrota VARCHAR(21) PRIMARY KEY,
    idregistro VARCHAR(21) NOT NULL UNIQUE REFERENCES registros_atividade(idregistro) ON DELETE CASCADE,
    modo VARCHAR(20) NOT NULL CHECK (modo IN ('desenho_livre', 'seguir_ruas', 'gps', 'importada')),
    coordenadas JSONB NOT NULL,
    distancia_metros NUMERIC(14,3) CHECK (distancia_metros IS NULL OR distancia_metros >= 0),
    data_criacao TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE OR REPLACE FUNCTION fn_valida_modalidade_usuario_modelo()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
DECLARE
    v_modelo RECORD;
    v_modalidade RECORD;
BEGIN
    SELECT idusuario, ativo INTO v_modalidade FROM modalidades WHERE idmodalidade = NEW.idmodalidade;
    IF NOT FOUND OR (NEW.ativo AND NOT v_modalidade.ativo) THEN
        RAISE EXCEPTION 'Modalidade inválida ou inativa.';
    END IF;
    IF v_modalidade.idusuario IS NOT NULL AND v_modalidade.idusuario <> NEW.idusuario THEN
        RAISE EXCEPTION 'A modalidade pertence a outro usuário.';
    END IF;

    IF NEW.idmodelo_ativo IS NULL THEN
        RETURN NEW;
    END IF;

    SELECT idmodalidade, idusuario, ativo INTO v_modelo FROM modelos_modalidade WHERE idmodelo = NEW.idmodelo_ativo;
    IF NOT FOUND OR (NEW.ativo AND NOT v_modelo.ativo) THEN
        RAISE EXCEPTION 'Modelo ativo inválido.';
    END IF;
    IF v_modelo.idmodalidade <> NEW.idmodalidade THEN
        RAISE EXCEPTION 'O modelo não pertence à modalidade.';
    END IF;
    IF v_modelo.idusuario IS NOT NULL AND v_modelo.idusuario <> NEW.idusuario THEN
        RAISE EXCEPTION 'O modelo pertence a outro usuário.';
    END IF;
    RETURN NEW;
END;
$$;

CREATE OR REPLACE FUNCTION fn_valida_registro_atividade()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
DECLARE
    v_modelo RECORD;
    v_modalidade RECORD;
    v_cronograma_usuario VARCHAR(21);
    v_treino RECORD;
BEGIN
    IF TG_OP = 'UPDATE' AND (NEW.idusuario <> OLD.idusuario OR NEW.idmodalidade <> OLD.idmodalidade OR NEW.idmodelo <> OLD.idmodelo) THEN
        RAISE EXCEPTION 'Usuário, modalidade e modelo de um registro histórico não podem ser alterados.';
    END IF;

    SELECT idmodalidade, idusuario, ativo INTO v_modelo FROM modelos_modalidade WHERE idmodelo = NEW.idmodelo;
    IF NOT FOUND THEN
        RAISE EXCEPTION 'Modelo inválido.';
    END IF;
    IF TG_OP = 'INSERT' AND NOT v_modelo.ativo THEN
        RAISE EXCEPTION 'Modelo inativo.';
    END IF;
    IF v_modelo.idmodalidade <> NEW.idmodalidade THEN
        RAISE EXCEPTION 'O modelo não pertence à modalidade.';
    END IF;
    IF v_modelo.idusuario IS NOT NULL AND v_modelo.idusuario <> NEW.idusuario THEN
        RAISE EXCEPTION 'O modelo pertence a outro usuário.';
    END IF;

    SELECT idusuario, ativo INTO v_modalidade FROM modalidades WHERE idmodalidade = NEW.idmodalidade;
    IF NOT FOUND THEN
        RAISE EXCEPTION 'Modalidade inválida.';
    END IF;
    IF TG_OP = 'INSERT' AND NOT v_modalidade.ativo THEN
        RAISE EXCEPTION 'Modalidade inativa.';
    END IF;
    IF v_modalidade.idusuario IS NOT NULL AND v_modalidade.idusuario <> NEW.idusuario THEN
        RAISE EXCEPTION 'A modalidade pertence a outro usuário.';
    END IF;

    IF NEW.idcronograma IS NOT NULL THEN
        SELECT idusuario INTO v_cronograma_usuario FROM cronogramas WHERE idcronograma = NEW.idcronograma;
        IF NOT FOUND OR v_cronograma_usuario <> NEW.idusuario THEN
            RAISE EXCEPTION 'Cronograma inválido para este usuário.';
        END IF;
    END IF;

    IF NEW.idtreino_cronograma IS NOT NULL THEN
        SELECT t.idcronograma, c.idusuario INTO v_treino
        FROM treinos_cronograma t
        JOIN cronogramas c ON c.idcronograma = t.idcronograma
        WHERE t.idtreino = NEW.idtreino_cronograma;
        IF NOT FOUND OR v_treino.idusuario <> NEW.idusuario THEN
            RAISE EXCEPTION 'Treino de cronograma inválido para este usuário.';
        END IF;
        IF NEW.idcronograma IS NOT NULL AND v_treino.idcronograma <> NEW.idcronograma THEN
            RAISE EXCEPTION 'O treino não pertence ao cronograma informado.';
        END IF;
    END IF;

    RETURN NEW;
END;
$$;

CREATE OR REPLACE FUNCTION fn_valida_campo_modelo()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
DECLARE
    v_grandeza_unidade VARCHAR(21);
BEGIN
    IF NEW.tipo_campo IN ('texto', 'texto_longo', 'booleano', 'data', 'hora', 'selecao') AND (NEW.idgrandeza IS NOT NULL OR NEW.idunidade IS NOT NULL) THEN
        RAISE EXCEPTION 'Este tipo de campo não aceita grandeza ou unidade.';
    END IF;
    IF NEW.idunidade IS NOT NULL AND NEW.idgrandeza IS NULL THEN
        RAISE EXCEPTION 'Uma unidade exige uma grandeza.';
    END IF;
    IF NEW.tipo_campo = 'intervalo' AND NEW.idunidade IS NOT NULL THEN
        RAISE EXCEPTION 'Campos de duração usam intervalo de tempo e não aceitam uma unidade específica.';
    END IF;
    IF NEW.idunidade IS NOT NULL THEN
        SELECT idgrandeza INTO v_grandeza_unidade FROM unidades WHERE idunidade = NEW.idunidade AND ativo;
        IF NOT FOUND OR v_grandeza_unidade <> NEW.idgrandeza THEN
            RAISE EXCEPTION 'A unidade não pertence à grandeza informada.';
        END IF;
    END IF;
    RETURN NEW;
END;
$$;

CREATE OR REPLACE FUNCTION fn_valida_opcao_modelo()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
DECLARE
    v_tipo VARCHAR(20);
BEGIN
    SELECT tipo_campo INTO v_tipo FROM campos_modelo WHERE idcampo = NEW.idcampo;
    IF v_tipo IS DISTINCT FROM 'selecao' THEN
        RAISE EXCEPTION 'Opções só podem ser usadas em campos de seleção.';
    END IF;
    RETURN NEW;
END;
$$;

CREATE OR REPLACE FUNCTION fn_valida_valor_atividade()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
DECLARE
    v_campo RECORD;
    v_modelo VARCHAR(21);
    v_registro_unidade VARCHAR(21);
    v_fator NUMERIC(30,15);
    v_ajuste NUMERIC(30,15);
BEGIN
    SELECT cm.tipo_campo, cm.escopo, cm.idgrandeza, cm.idunidade, cm.idmodelo
    INTO v_campo
    FROM campos_modelo cm
    WHERE cm.idcampo = NEW.idcampo;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'Campo inválido.';
    END IF;

    SELECT idmodelo INTO v_modelo FROM registros_atividade WHERE idregistro = NEW.idregistro;
    IF NOT FOUND OR v_modelo <> v_campo.idmodelo THEN
        RAISE EXCEPTION 'O campo não pertence ao modelo deste registro.';
    END IF;

    IF v_campo.escopo = 'registro' AND NEW.idunidade_atividade IS NOT NULL THEN
        RAISE EXCEPTION 'Campo de registro não pode ser ligado a uma unidade.';
    END IF;
    IF v_campo.escopo = 'unidade' AND NEW.idunidade_atividade IS NULL THEN
        RAISE EXCEPTION 'Campo de unidade exige uma unidade de atividade.';
    END IF;

    IF NEW.idunidade_atividade IS NOT NULL THEN
        SELECT idregistro INTO v_registro_unidade FROM unidades_atividade WHERE idunidade_atividade = NEW.idunidade_atividade;
        IF NOT FOUND OR v_registro_unidade <> NEW.idregistro THEN
            RAISE EXCEPTION 'A unidade não pertence ao registro.';
        END IF;
    END IF;

    CASE v_campo.tipo_campo
        WHEN 'texto', 'texto_longo' THEN
            IF NEW.valor_texto IS NULL THEN RAISE EXCEPTION 'Valor de texto obrigatório.'; END IF;
        WHEN 'inteiro' THEN
            IF NEW.valor_inteiro IS NULL THEN RAISE EXCEPTION 'Valor inteiro obrigatório.'; END IF;
        WHEN 'decimal' THEN
            IF NEW.valor_decimal IS NULL THEN RAISE EXCEPTION 'Valor decimal obrigatório.'; END IF;
        WHEN 'booleano' THEN
            IF NEW.valor_booleano IS NULL THEN RAISE EXCEPTION 'Valor booleano obrigatório.'; END IF;
        WHEN 'data' THEN
            IF NEW.valor_data IS NULL THEN RAISE EXCEPTION 'Valor de data obrigatório.'; END IF;
        WHEN 'hora' THEN
            IF NEW.valor_hora IS NULL THEN RAISE EXCEPTION 'Valor de hora obrigatório.'; END IF;
        WHEN 'intervalo' THEN
            IF NEW.valor_intervalo IS NULL THEN RAISE EXCEPTION 'Valor de duração obrigatório.'; END IF;
        WHEN 'selecao' THEN
            IF NEW.idopcao IS NULL THEN RAISE EXCEPTION 'Opção obrigatória.'; END IF;
    END CASE;

    NEW.valor_normalizado := NULL;
    IF v_campo.idgrandeza IS NOT NULL AND v_campo.tipo_campo IN ('inteiro', 'decimal', 'intervalo') THEN
        IF v_campo.idunidade IS NULL THEN
            SELECT fator_para_base, ajuste_para_base INTO v_fator, v_ajuste FROM unidades WHERE idgrandeza = v_campo.idgrandeza AND eh_base AND ativo;
        ELSE
            SELECT fator_para_base, ajuste_para_base INTO v_fator, v_ajuste FROM unidades WHERE idunidade = v_campo.idunidade AND ativo;
        END IF;
        IF NOT FOUND THEN
            RAISE EXCEPTION 'Não foi possível normalizar a unidade.';
        END IF;
        IF v_campo.tipo_campo = 'inteiro' THEN
            NEW.valor_normalizado := NEW.valor_inteiro * v_fator + v_ajuste;
        ELSIF v_campo.tipo_campo = 'decimal' THEN
            NEW.valor_normalizado := NEW.valor_decimal * v_fator + v_ajuste;
        ELSE
            NEW.valor_normalizado := EXTRACT(EPOCH FROM NEW.valor_intervalo) * v_fator + v_ajuste;
        END IF;
    END IF;

    RETURN NEW;
END;
$$;

CREATE OR REPLACE FUNCTION fn_protege_modelo_historico()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
    IF EXISTS (SELECT 1 FROM registros_atividade WHERE idmodelo = OLD.idmodelo) THEN
        IF TG_OP = 'DELETE' THEN
            RAISE EXCEPTION 'Modelo usado historicamente não pode ser removido.';
        END IF;
        IF NEW.idmodalidade IS DISTINCT FROM OLD.idmodalidade
           OR NEW.idusuario IS DISTINCT FROM OLD.idusuario
           OR NEW.idmodelo_anterior IS DISTINCT FROM OLD.idmodelo_anterior
           OR NEW.nome IS DISTINCT FROM OLD.nome
           OR NEW.slug IS DISTINCT FROM OLD.slug
           OR NEW.descricao IS DISTINCT FROM OLD.descricao
           OR NEW.tipo_unidade_padrao IS DISTINCT FROM OLD.tipo_unidade_padrao
           OR NEW.rotulo_unidade IS DISTINCT FROM OLD.rotulo_unidade
           OR NEW.permite_multiplas_unidades IS DISTINCT FROM OLD.permite_multiplas_unidades
           OR NEW.versao IS DISTINCT FROM OLD.versao THEN
            RAISE EXCEPTION 'Crie uma nova versão para alterar um modelo usado historicamente.';
        END IF;
    END IF;
    IF TG_OP = 'DELETE' THEN
        RETURN OLD;
    END IF;
    RETURN NEW;
END;
$$;

CREATE OR REPLACE FUNCTION fn_protege_campo_historico()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
DECLARE
    v_modelo VARCHAR(21);
BEGIN
    IF TG_OP = 'INSERT' THEN
        v_modelo := NEW.idmodelo;
    ELSE
        v_modelo := OLD.idmodelo;
    END IF;

    IF EXISTS (SELECT 1 FROM registros_atividade WHERE idmodelo = v_modelo) THEN
        IF TG_OP IN ('INSERT', 'DELETE') THEN
            RAISE EXCEPTION 'Crie uma nova versão do modelo para alterar seus campos.';
        END IF;
        IF NEW.idmodelo IS DISTINCT FROM OLD.idmodelo
           OR NEW.nome IS DISTINCT FROM OLD.nome
           OR NEW.slug IS DISTINCT FROM OLD.slug
           OR NEW.rotulo IS DISTINCT FROM OLD.rotulo
           OR NEW.tipo_campo IS DISTINCT FROM OLD.tipo_campo
           OR NEW.escopo IS DISTINCT FROM OLD.escopo
           OR NEW.idgrandeza IS DISTINCT FROM OLD.idgrandeza
           OR NEW.idunidade IS DISTINCT FROM OLD.idunidade
           OR NEW.obrigatorio IS DISTINCT FROM OLD.obrigatorio
           OR NEW.ordem IS DISTINCT FROM OLD.ordem
           OR NEW.ativo IS DISTINCT FROM OLD.ativo THEN
            RAISE EXCEPTION 'Crie uma nova versão do modelo para alterar seus campos.';
        END IF;
    END IF;
    IF TG_OP = 'DELETE' THEN
        RETURN OLD;
    END IF;
    RETURN NEW;
END;
$$;

CREATE OR REPLACE FUNCTION fn_protege_opcao_historica()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
DECLARE
    v_campo VARCHAR(21);
    v_modelo VARCHAR(21);
BEGIN
    IF TG_OP = 'INSERT' THEN
        v_campo := NEW.idcampo;
    ELSE
        v_campo := OLD.idcampo;
    END IF;

    SELECT idmodelo INTO v_modelo FROM campos_modelo WHERE idcampo = v_campo;
    IF EXISTS (SELECT 1 FROM registros_atividade WHERE idmodelo = v_modelo) THEN
        IF TG_OP IN ('INSERT', 'DELETE') THEN
            RAISE EXCEPTION 'Crie uma nova versão do modelo para alterar suas opções.';
        END IF;
        IF NEW.idcampo IS DISTINCT FROM OLD.idcampo
           OR NEW.rotulo IS DISTINCT FROM OLD.rotulo
           OR NEW.valor IS DISTINCT FROM OLD.valor
           OR NEW.ordem IS DISTINCT FROM OLD.ordem
           OR NEW.ativo IS DISTINCT FROM OLD.ativo THEN
            RAISE EXCEPTION 'Crie uma nova versão do modelo para alterar suas opções.';
        END IF;
    END IF;
    IF TG_OP = 'DELETE' THEN
        RETURN OLD;
    END IF;
    RETURN NEW;
END;
$$;

CREATE TRIGGER tg_modalidades_usuario_validacao BEFORE INSERT OR UPDATE ON modalidades_usuario FOR EACH ROW EXECUTE FUNCTION fn_valida_modalidade_usuario_modelo();
CREATE TRIGGER tg_registros_atividade_validacao BEFORE INSERT OR UPDATE ON registros_atividade FOR EACH ROW EXECUTE FUNCTION fn_valida_registro_atividade();
CREATE TRIGGER tg_campos_modelo_validacao BEFORE INSERT OR UPDATE ON campos_modelo FOR EACH ROW EXECUTE FUNCTION fn_valida_campo_modelo();
CREATE TRIGGER tg_opcoes_modelo_validacao BEFORE INSERT OR UPDATE ON campos_modelo_opcoes FOR EACH ROW EXECUTE FUNCTION fn_valida_opcao_modelo();
CREATE TRIGGER tg_valores_atividade_validacao BEFORE INSERT OR UPDATE ON valores_atividade FOR EACH ROW EXECUTE FUNCTION fn_valida_valor_atividade();
CREATE TRIGGER tg_modelos_historico BEFORE UPDATE OR DELETE ON modelos_modalidade FOR EACH ROW EXECUTE FUNCTION fn_protege_modelo_historico();
CREATE TRIGGER tg_campos_historico BEFORE INSERT OR UPDATE OR DELETE ON campos_modelo FOR EACH ROW EXECUTE FUNCTION fn_protege_campo_historico();
CREATE TRIGGER tg_opcoes_historico BEFORE INSERT OR UPDATE OR DELETE ON campos_modelo_opcoes FOR EACH ROW EXECUTE FUNCTION fn_protege_opcao_historica();

COMMIT;
