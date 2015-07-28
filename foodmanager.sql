--
-- PostgreSQL database dump
--

SET statement_timeout = 0;
SET lock_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET client_min_messages = warning;

--
-- Name: foodmanager; Type: SCHEMA; Schema: -; Owner: webdev
--

CREATE SCHEMA foodmanager;


ALTER SCHEMA foodmanager OWNER TO webdev;

SET search_path = foodmanager, pg_catalog;

SET default_tablespace = '';

SET default_with_oids = false;

--
-- Name: data; Type: TABLE; Schema: foodmanager; Owner: webdev; Tablespace: 
--

CREATE TABLE data (
    id integer NOT NULL,
    text character varying(100)
);


ALTER TABLE data OWNER TO webdev;

SET default_with_oids = true;

--
-- Name: food_days; Type: TABLE; Schema: foodmanager; Owner: webdev; Tablespace: 
--

CREATE TABLE food_days (
    food_day_id integer NOT NULL,
    day date DEFAULT now() NOT NULL,
    food_group_id integer NOT NULL,
    close_stamp timestamp with time zone,
    create_user_id integer
);


ALTER TABLE food_days OWNER TO webdev;

--
-- Name: food_days_food_day_id_seq; Type: SEQUENCE; Schema: foodmanager; Owner: webdev
--

CREATE SEQUENCE food_days_food_day_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE food_days_food_day_id_seq OWNER TO webdev;

--
-- Name: food_days_food_day_id_seq; Type: SEQUENCE OWNED BY; Schema: foodmanager; Owner: webdev
--

ALTER SEQUENCE food_days_food_day_id_seq OWNED BY food_days.food_day_id;


--
-- Name: food_groups; Type: TABLE; Schema: foodmanager; Owner: webdev; Tablespace: 
--

CREATE TABLE food_groups (
    food_group_id integer NOT NULL,
    label character varying NOT NULL,
    descr character varying,
    url character varying
);


ALTER TABLE food_groups OWNER TO webdev;

--
-- Name: food_groups_food_group_id_seq; Type: SEQUENCE; Schema: foodmanager; Owner: webdev
--

CREATE SEQUENCE food_groups_food_group_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE food_groups_food_group_id_seq OWNER TO webdev;

--
-- Name: food_groups_food_group_id_seq; Type: SEQUENCE OWNED BY; Schema: foodmanager; Owner: webdev
--

ALTER SEQUENCE food_groups_food_group_id_seq OWNED BY food_groups.food_group_id;


--
-- Name: foods; Type: TABLE; Schema: foodmanager; Owner: webdev; Tablespace: 
--

CREATE TABLE foods (
    food_day_id integer NOT NULL,
    user_id integer NOT NULL,
    request character varying
);


ALTER TABLE foods OWNER TO webdev;

--
-- Name: users; Type: TABLE; Schema: foodmanager; Owner: webdev; Tablespace: 
--

CREATE TABLE users (
    user_id integer NOT NULL,
    username character varying NOT NULL,
    email character varying,
    notify boolean DEFAULT true NOT NULL,
    deleted boolean DEFAULT false NOT NULL
);


ALTER TABLE users OWNER TO webdev;

--
-- Name: users_user_id_seq; Type: SEQUENCE; Schema: foodmanager; Owner: webdev
--

CREATE SEQUENCE users_user_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE users_user_id_seq OWNER TO webdev;

--
-- Name: users_user_id_seq; Type: SEQUENCE OWNED BY; Schema: foodmanager; Owner: webdev
--

ALTER SEQUENCE users_user_id_seq OWNED BY users.user_id;


--
-- Name: food_day_id; Type: DEFAULT; Schema: foodmanager; Owner: webdev
--

ALTER TABLE ONLY food_days ALTER COLUMN food_day_id SET DEFAULT nextval('food_days_food_day_id_seq'::regclass);


--
-- Name: food_group_id; Type: DEFAULT; Schema: foodmanager; Owner: webdev
--

ALTER TABLE ONLY food_groups ALTER COLUMN food_group_id SET DEFAULT nextval('food_groups_food_group_id_seq'::regclass);


--
-- Name: user_id; Type: DEFAULT; Schema: foodmanager; Owner: webdev
--

ALTER TABLE ONLY users ALTER COLUMN user_id SET DEFAULT nextval('users_user_id_seq'::regclass);


--
-- Name: food_days_day_key; Type: CONSTRAINT; Schema: foodmanager; Owner: webdev; Tablespace: 
--

ALTER TABLE ONLY food_days
    ADD CONSTRAINT food_days_day_key UNIQUE (day);


--
-- Name: pk3; Type: CONSTRAINT; Schema: foodmanager; Owner: webdev; Tablespace: 
--

ALTER TABLE ONLY data
    ADD CONSTRAINT pk3 PRIMARY KEY (id);


--
-- Name: pk_food_days; Type: CONSTRAINT; Schema: foodmanager; Owner: webdev; Tablespace: 
--

ALTER TABLE ONLY food_days
    ADD CONSTRAINT pk_food_days PRIMARY KEY (food_day_id);


--
-- Name: pk_food_groups; Type: CONSTRAINT; Schema: foodmanager; Owner: webdev; Tablespace: 
--

ALTER TABLE ONLY food_groups
    ADD CONSTRAINT pk_food_groups PRIMARY KEY (food_group_id);


--
-- Name: pk_foods; Type: CONSTRAINT; Schema: foodmanager; Owner: webdev; Tablespace: 
--

ALTER TABLE ONLY foods
    ADD CONSTRAINT pk_foods PRIMARY KEY (food_day_id, user_id);


--
-- Name: pk_users; Type: CONSTRAINT; Schema: foodmanager; Owner: webdev; Tablespace: 
--

ALTER TABLE ONLY users
    ADD CONSTRAINT pk_users PRIMARY KEY (user_id);


--
-- Name: users_username_key; Type: CONSTRAINT; Schema: foodmanager; Owner: webdev; Tablespace: 
--

ALTER TABLE ONLY users
    ADD CONSTRAINT users_username_key UNIQUE (username);


--
-- Name: IX_food_days_to_food_groups; Type: INDEX; Schema: foodmanager; Owner: webdev; Tablespace: 
--

CREATE INDEX "IX_food_days_to_food_groups" ON food_days USING btree (food_group_id);


--
-- Name: IX_foods_to_food_days; Type: INDEX; Schema: foodmanager; Owner: webdev; Tablespace: 
--

CREATE INDEX "IX_foods_to_food_days" ON foods USING btree (food_day_id);


--
-- Name: IX_foods_to_users; Type: INDEX; Schema: foodmanager; Owner: webdev; Tablespace: 
--

CREATE INDEX "IX_foods_to_users" ON foods USING btree (user_id);


--
-- Name: fk_food_days_create_user_id; Type: INDEX; Schema: foodmanager; Owner: webdev; Tablespace: 
--

CREATE INDEX fk_food_days_create_user_id ON food_days USING btree (create_user_id);


--
-- Name: food_days_to_create_users; Type: FK CONSTRAINT; Schema: foodmanager; Owner: webdev
--

ALTER TABLE ONLY food_days
    ADD CONSTRAINT food_days_to_create_users FOREIGN KEY (create_user_id) REFERENCES users(user_id) MATCH FULL ON UPDATE CASCADE ON DELETE SET NULL DEFERRABLE;


--
-- Name: food_days_to_food_groups; Type: FK CONSTRAINT; Schema: foodmanager; Owner: webdev
--

ALTER TABLE ONLY food_days
    ADD CONSTRAINT food_days_to_food_groups FOREIGN KEY (food_group_id) REFERENCES food_groups(food_group_id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: foods_to_food_days; Type: FK CONSTRAINT; Schema: foodmanager; Owner: webdev
--

ALTER TABLE ONLY foods
    ADD CONSTRAINT foods_to_food_days FOREIGN KEY (food_day_id) REFERENCES food_days(food_day_id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: foods_to_users; Type: FK CONSTRAINT; Schema: foodmanager; Owner: webdev
--

ALTER TABLE ONLY foods
    ADD CONSTRAINT foods_to_users FOREIGN KEY (user_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- PostgreSQL database dump complete
--

