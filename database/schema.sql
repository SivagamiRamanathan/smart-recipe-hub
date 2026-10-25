-- Smart Recipe Hub PostgreSQL schema reference / safe additive setup.
-- Run this while connected to the smart_recipe database.
-- Existing recipes are not deleted. db.php also runs equivalent CREATE/ALTER statements.

CREATE TABLE IF NOT EXISTS admin_users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    pin_hash TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS recipes (
    id SERIAL PRIMARY KEY,
    recipe_name VARCHAR(200) NOT NULL,
    ingredients TEXT NOT NULL DEFAULT '', -- JSON array: [{name, quantity, unit, name_ta, name_th}, ...]
    preparation_method TEXT NOT NULL DEFAULT '',
    base_yield NUMERIC(12,3) NOT NULL DEFAULT 1,
    base_unit VARCHAR(20) NOT NULL DEFAULT 'g',
    image_url TEXT NOT NULL DEFAULT '',
    recipe_name_ta VARCHAR(200) NOT NULL DEFAULT '',
    recipe_name_th VARCHAR(200) NOT NULL DEFAULT '',
    description_en TEXT NOT NULL DEFAULT '',
    description_ta TEXT NOT NULL DEFAULT '',
    description_th TEXT NOT NULL DEFAULT '',
    method_en TEXT NOT NULL DEFAULT '',
    method_ta TEXT NOT NULL DEFAULT '',
    method_th TEXT NOT NULL DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE recipes ADD COLUMN IF NOT EXISTS base_yield NUMERIC(12,3) NOT NULL DEFAULT 1;
ALTER TABLE recipes ADD COLUMN IF NOT EXISTS base_unit VARCHAR(20) NOT NULL DEFAULT 'g';
ALTER TABLE recipes ADD COLUMN IF NOT EXISTS image_url TEXT NOT NULL DEFAULT '';
ALTER TABLE recipes ADD COLUMN IF NOT EXISTS recipe_name_ta VARCHAR(200) NOT NULL DEFAULT '';
ALTER TABLE recipes ADD COLUMN IF NOT EXISTS recipe_name_th VARCHAR(200) NOT NULL DEFAULT '';
ALTER TABLE recipes ADD COLUMN IF NOT EXISTS description_en TEXT NOT NULL DEFAULT '';
ALTER TABLE recipes ADD COLUMN IF NOT EXISTS description_ta TEXT NOT NULL DEFAULT '';
ALTER TABLE recipes ADD COLUMN IF NOT EXISTS description_th TEXT NOT NULL DEFAULT '';
ALTER TABLE recipes ADD COLUMN IF NOT EXISTS method_en TEXT NOT NULL DEFAULT '';
ALTER TABLE recipes ADD COLUMN IF NOT EXISTS method_ta TEXT NOT NULL DEFAULT '';
ALTER TABLE recipes ADD COLUMN IF NOT EXISTS method_th TEXT NOT NULL DEFAULT '';

CREATE TABLE IF NOT EXISTS ingredients_master (
    id SERIAL PRIMARY KEY,
    name_en VARCHAR(150) NOT NULL,
    name_ta VARCHAR(150) NOT NULL DEFAULT '',
    name_th VARCHAR(150) NOT NULL DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE UNIQUE INDEX IF NOT EXISTS ingredients_master_en_unique ON ingredients_master (LOWER(name_en));

CREATE TABLE IF NOT EXISTS units_master (
    id SERIAL PRIMARY KEY,
    unit_name VARCHAR(80) NOT NULL,
    unit_symbol VARCHAR(20) NOT NULL,
    unit_type VARCHAR(20) NOT NULL CHECK (unit_type IN ('weight','volume','count','other')),
    factor_to_base NUMERIC(14,6),
    base_unit VARCHAR(20) NOT NULL DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE UNIQUE INDEX IF NOT EXISTS units_master_symbol_unique ON units_master (LOWER(unit_symbol));

CREATE INDEX IF NOT EXISTS recipes_name_idx ON recipes (LOWER(recipe_name));
CREATE INDEX IF NOT EXISTS recipes_created_idx ON recipes (created_at DESC);
