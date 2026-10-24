CREATE DATABASE smart_recipe;
-- Connect to smart_recipe, then run the remaining statements in this file.

CREATE TABLE app_users (
  id BIGSERIAL PRIMARY KEY,
  email VARCHAR(254) UNIQUE NOT NULL,
  password_hash TEXT NOT NULL,
  role VARCHAR(20) NOT NULL CHECK (role IN ('customer','staff','admin')),
  active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE products (
  id BIGSERIAL PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE,
  base_quantity NUMERIC(12,3) NOT NULL CHECK (base_quantity > 0),
  base_unit VARCHAR(20) NOT NULL
);

CREATE TABLE product_ingredients (
  id BIGSERIAL PRIMARY KEY,
  product_id BIGINT NOT NULL REFERENCES products(id) ON DELETE CASCADE,
  ingredient_name VARCHAR(120) NOT NULL,
  base_quantity NUMERIC(12,3) NOT NULL CHECK (base_quantity >= 0),
  unit VARCHAR(20) NOT NULL
);

CREATE TABLE secret_recipes (
  id BIGSERIAL PRIMARY KEY,
  product_id BIGINT REFERENCES products(id) ON DELETE SET NULL,
  slug VARCHAR(120) UNIQUE NOT NULL,
  title VARCHAR(160) NOT NULL,
  recipe_text TEXT NOT NULL,
  pin_hash TEXT NOT NULL,
  active BOOLEAN NOT NULL DEFAULT TRUE,
  updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Example product and ingredient data for a 500 ml demonstration batch.
INSERT INTO products (name, base_quantity, base_unit)
VALUES ('Mango Pickle', 500, 'ml');

INSERT INTO product_ingredients (product_id, ingredient_name, base_quantity, unit)
SELECT id, 'Raw mango', 350, 'g' FROM products WHERE name = 'Mango Pickle';
INSERT INTO product_ingredients (product_id, ingredient_name, base_quantity, unit)
SELECT id, 'Sesame oil', 100, 'ml' FROM products WHERE name = 'Mango Pickle';
INSERT INTO product_ingredients (product_id, ingredient_name, base_quantity, unit)
SELECT id, 'Pickle salt', 50, 'g' FROM products WHERE name = 'Mango Pickle';
INSERT INTO product_ingredients (product_id, ingredient_name, base_quantity, unit)
SELECT id, 'Chilli powder', 40, 'g' FROM products WHERE name = 'Mango Pickle';
INSERT INTO product_ingredients (product_id, ingredient_name, base_quantity, unit)
SELECT id, 'Mustard seeds', 15, 'g' FROM products WHERE name = 'Mango Pickle';
INSERT INTO product_ingredients (product_id, ingredient_name, base_quantity, unit)
SELECT id, 'Fenugreek seeds', 5, 'g' FROM products WHERE name = 'Mango Pickle';

-- Create the staff account and secret recipe with PHP password_hash() hashes.
-- Do not insert plaintext passwords/PINs. See README.md for the helper command.
