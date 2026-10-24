SMART RECIPE HUB — HOME UI + FORMULA GUIDE + POSTGRESQL TABLES

FILES
- index.php       Home page: removes Recipes from navbar; saved recipe gallery appears before the animated food cover.
- calculator.php  Main calculator page and server-side ingredient-scaling formula.
- api/recipes.php REST API endpoint that can return recipe data and independently calculate scaled quantities.
- admin.php       Admin recipe create/edit/view/delete flow.
- db.php          PostgreSQL connection and additive schema initialization.
- schema.sql      PostgreSQL schema reference, columns, and indexes.

HOME PAGE CHANGES
- Removed the separate Recipes link from the home-page navbar. The saved recipe gallery remains accessible from the home-page Explore button/cards.
- "Explore your saved recipes" is displayed before the animated food-inspired cover.
- The food cover is a CSS image-pan/shimmer animation, not an actual video file.
- Fixed mb_substr() so the home page uses mb_substr() only if the PHP mbstring extension exists; otherwise it falls back to substr().
- Recipe cards use image_url from the recipes table when provided, otherwise use a food-photo fallback.

WHERE IS THE CALCULATION FORMULA?
1) calculator.php — main browser form submits to PHP. The scaling formula is:
       scaling_factor = requested quantity converted to base unit / recipe base_yield converted to base unit
       scaled ingredient quantity = saved ingredient quantity * scaling_factor
   For weight, kg is converted to g by multiplying by 1000. For volume, litre is converted to ml by multiplying by 1000.
   Example: base recipe yield = 500 g; requested quantity = 1000 g; factor = 1000/500 = 2. A saved ingredient quantity of 25 g becomes 50 g.
2) api/recipes.php — REST endpoint also implements the same calculation and returns scaling_factor plus scaled ingredient quantities. The API is not required for calculator.php's current server-rendered form; it is available for API consumers or a future JavaScript calculator.
3) No Gemini API is used for the calculation. PHP reads the saved recipe from PostgreSQL and calculates the ratio.

POSTGRESQL TABLES
- admin_users: administrator username and password/PIN hashes.
- recipes: recipe name, JSON ingredient list, base yield/unit, preparation method, translations, descriptions, and image URL.
- ingredients_master: reusable ingredient dropdown names in English/Tamil/Thanglish.
- units_master: reusable unit dropdown and unit-type metadata.
The ingredients field in recipes currently stores an array of ingredient objects as JSON text. This matches the current admin/calculator code. Do not manually change it to a different table format without updating PHP code too.

SETUP
1. Back up your existing project folder and PostgreSQL database before replacing files.
2. Copy the included files into your project, keeping api/recipes.php inside the api folder.
3. Preserve/configure your own db.php credentials privately (DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS). Do not share passwords.
4. In pgAdmin, select the smart_recipe database and run schema.sql if you want the schema documented/created explicitly. The db.php file also runs additive CREATE/ALTER statements on page load.
5. Run from the project folder: php -S 127.0.0.1:8000
6. Open http://127.0.0.1:8000/index.php

NOTES
- The ZIP was checked for PHP syntax in the changed index.php. This environment cannot connect to your local PostgreSQL instance, so please back up and test locally.
- Recipe card images and the CSS food-cover image use external image URLs and require internet access.


Calculation fix: when saving a recipe, set Original recipe total yield to the actual total quantity represented by the ingredient list (e.g. 100 g), not an arbitrary default of 1. The calculator computes requested total yield / saved base yield and multiplies every ingredient numeric quantity by that factor. Ingredient units (g, tsp, tbsp, cup, etc.) remain unchanged; no unsupported tsp-to-gram conversion is performed. Existing recipes are not deleted.
