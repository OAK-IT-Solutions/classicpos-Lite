SELECT pg_terminate_backend(pg_stat_activity.pid) FROM pg_stat_activity WHERE pg_stat_activity.datname = 'classicpos_testing' AND pid != pg_backend_pid();
DROP DATABASE IF EXISTS classicpos_testing;
CREATE DATABASE classicpos_testing;
