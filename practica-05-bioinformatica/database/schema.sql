-- Práctica 05 · Laboratorio Bioinformático de Cultivo (Tulipán)
-- MySQL 8 · utf8mb4
CREATE DATABASE IF NOT EXISTS practica05_bioinformatica CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE practica05_bioinformatica;

DROP TABLE IF EXISTS comparaciones_ab;
DROP TABLE IF EXISTS decisiones;
DROP TABLE IF EXISTS mediciones;
DROP TABLE IF EXISTS simulaciones;
DROP TABLE IF EXISTS especies;

-- Catálogo de especies: óptimos ambientales y parámetros genéticos simulados (r, K)
CREATE TABLE especies (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre_comun       VARCHAR(80)  NOT NULL,
  nombre_cientifico  VARCHAR(120) NOT NULL UNIQUE,
  familia            VARCHAR(60)  NOT NULL,
  color_hex          CHAR(7)      NOT NULL DEFAULT '#ff4f8b',
  luz_opt            DECIMAL(5,1) NOT NULL COMMENT '% luz solar completa',
  agua_opt           DECIMAL(5,1) NOT NULL COMMENT '% humedad del sustrato',
  temp_opt           DECIMAL(4,1) NOT NULL COMMENT '°C fase de crecimiento',
  nutr_opt           DECIMAL(5,1) NOT NULL COMMENT '% nutrientes (NPK)',
  r_defecto          DECIMAL(5,3) NOT NULL COMMENT 'tasa intrínseca de crecimiento (1/día)',
  k_defecto          DECIMAL(7,2) NOT NULL COMMENT 'capacidad de carga: biomasa máx. (g)',
  n0                 DECIMAL(6,2) NOT NULL COMMENT 'biomasa inicial (g)',
  altura_max         DECIMAL(5,2) NOT NULL COMMENT 'altura máxima del tallo (unidades 3D)',
  frio_req_dias      SMALLINT UNSIGNED NOT NULL COMMENT 'días de frío para liberar dormancia',
  frio_tmin          DECIMAL(4,1) NOT NULL,
  frio_tmax          DECIMAL(4,1) NOT NULL,
  temp_umbral_calor  DECIMAL(4,1) NOT NULL DEFAULT 21.0,
  notas              TEXT NULL,
  creada_en          TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Cada corrida guardada: parámetros usados + resultados agregados (trazabilidad)
CREATE TABLE simulaciones (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  especie_id     INT UNSIGNED NOT NULL,
  nombre         VARCHAR(100) NOT NULL,
  notas          TEXT NULL,
  modelo         ENUM('lineal','exponencial','logistico','gompertz') NOT NULL,
  luz            DECIMAL(5,1) NOT NULL,
  agua           DECIMAL(5,1) NOT NULL,
  temp           DECIMAL(4,1) NOT NULL,
  nutr           DECIMAL(5,1) NOT NULL,
  co2            SMALLINT UNSIGNED NOT NULL DEFAULT 420,
  r              DECIMAL(5,3) NOT NULL,
  k              DECIMAL(7,2) NOT NULL,
  termostato     TINYINT(1) NOT NULL DEFAULT 1,
  escenario      CHAR(1) NOT NULL DEFAULT 'A',
  biomasa_final  DECIMAL(8,3) NOT NULL DEFAULT 0,
  altura_final   DECIMAL(6,3) NOT NULL DEFAULT 0,
  dias_madurez   SMALLINT UNSIGNED NULL COMMENT 'día en que inicia la floración (cosecha de flor)',
  dias_totales   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  estres_medio   DECIMAL(5,2) NOT NULL DEFAULT 0,
  aborto         TINYINT(1) NOT NULL DEFAULT 0,
  creada_en      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_sim_especie FOREIGN KEY (especie_id) REFERENCES especies(id),
  INDEX idx_sim_especie (especie_id),
  INDEX idx_sim_biomasa (biomasa_final)
) ENGINE=InnoDB;

-- Serie temporal: una fila cada N días (no cada frame) por simulación
CREATE TABLE mediciones (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  simulacion_id       INT UNSIGNED NOT NULL,
  dia                 SMALLINT UNSIGNED NOT NULL,
  fase                TINYINT UNSIGNED NOT NULL,
  biomasa             DECIMAL(8,3) NOT NULL,
  altura              DECIMAL(6,3) NOT NULL,
  hojas               TINYINT UNSIGNED NOT NULL DEFAULT 0,
  bulbillos           TINYINT UNSIGNED NOT NULL DEFAULT 0,
  icr                 DECIMAL(8,5) NOT NULL DEFAULT 0 COMMENT 'índice de crecimiento relativo (g/g/día)',
  estres_hidrico      DECIMAL(5,2) NOT NULL,
  estres_termico      DECIMAL(5,2) NOT NULL,
  estres_luminico     DECIMAL(5,2) NOT NULL,
  estres_nutricional  DECIMAL(5,2) NOT NULL,
  estres_global       DECIMAL(5,2) NOT NULL,
  frio_acum           DECIMAL(5,2) NOT NULL DEFAULT 0,
  salud_bulbo         DECIMAL(5,2) NOT NULL DEFAULT 100,
  temp                DECIMAL(4,1) NOT NULL,
  agua                DECIMAL(5,1) NOT NULL,
  luz                 DECIMAL(5,1) NOT NULL,
  nutr                DECIMAL(5,1) NOT NULL,
  CONSTRAINT fk_med_sim FOREIGN KEY (simulacion_id) REFERENCES simulaciones(id) ON DELETE CASCADE,
  UNIQUE KEY uq_med_sim_dia (simulacion_id, dia)
) ENGINE=InnoDB;

-- Recomendaciones emitidas por el motor de decisiones durante la corrida
CREATE TABLE decisiones (
  id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  simulacion_id  INT UNSIGNED NOT NULL,
  dia            SMALLINT UNSIGNED NOT NULL,
  codigo         VARCHAR(30) NOT NULL,
  severidad      ENUM('critica','alta','media','info','ok') NOT NULL,
  titulo         VARCHAR(120) NOT NULL,
  detalle        VARCHAR(255) NULL,
  umbral         VARCHAR(60) NULL,
  valor          DECIMAL(8,2) NULL,
  CONSTRAINT fk_dec_sim FOREIGN KEY (simulacion_id) REFERENCES simulaciones(id) ON DELETE CASCADE,
  INDEX idx_dec_sim (simulacion_id)
) ENGINE=InnoDB;

-- Resultado de comparar dos simulaciones (A/B)
CREATE TABLE comparaciones_ab (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sim_a_id   INT UNSIGNED NOT NULL,
  sim_b_id   INT UNSIGNED NOT NULL,
  ganador    ENUM('A','B','empate') NOT NULL,
  puntaje_a  DECIMAL(6,3) NOT NULL,
  puntaje_b  DECIMAL(6,3) NOT NULL,
  criterio   VARCHAR(160) NOT NULL DEFAULT 'biomasa 50% + rapidez 30% + bajo estrés 20%',
  creada_en  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ab_a FOREIGN KEY (sim_a_id) REFERENCES simulaciones(id) ON DELETE CASCADE,
  CONSTRAINT fk_ab_b FOREIGN KEY (sim_b_id) REFERENCES simulaciones(id) ON DELETE CASCADE
) ENGINE=InnoDB;
