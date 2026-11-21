-- Runs once, when the db volume is first created.
-- A separate database for the API test suite, so tests never touch the dev catalogue.
CREATE DATABASE catalogue_test;
