-- pg_trgm powers the fuzzy patient search on the busiest screen.
CREATE EXTENSION IF NOT EXISTS pg_trgm;

-- unaccent normalises Arabic/Persian character variants (ي vs ی, ك vs ک)
-- so a search for "کریمی" also matches names typed with Arabic letters.
CREATE EXTENSION IF NOT EXISTS unaccent;
