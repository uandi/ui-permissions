CREATE TABLE be_groups (
	title varchar(255) DEFAULT '' NOT NULL,
    subgroup TEXT DEFAULT NULL,
    permission_key varchar(255) DEFAULT '' NOT NULL
);

CREATE TABLE sys_filemounts (
    permission_key varchar(255) DEFAULT '' NOT NULL
);
