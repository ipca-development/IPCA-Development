-- OMM Canonical Excerpts import
-- Generated files:
--  - omm_excerpts_import.csv
--  - omm_mccf_import.csv (as provided)

CREATE TABLE IF NOT EXISTS omm_excerpts (
  excerpt_id VARCHAR(80) PRIMARY KEY,
  manual_code VARCHAR(16) NOT NULL,
  part_no INT NOT NULL,
  section_num VARCHAR(32) NOT NULL,
  section_title VARCHAR(255) NOT NULL,
  level INT NOT NULL,
  parent_id VARCHAR(80) NULL,
  sort_order INT NOT NULL,
  text LONGTEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_omm_excerpts_section ON omm_excerpts(section_num);
CREATE INDEX idx_omm_excerpts_parent ON omm_excerpts(parent_id);

-- If your MySQL client does not allow LOCAL, remove LOCAL and place the CSV in the server import directory.
LOAD DATA LOCAL INFILE 'omm_excerpts_import.csv'
INTO TABLE omm_excerpts
FIELDS TERMINATED BY ',' ENCLOSED BY '"'
LINES TERMINATED BY '\n'
IGNORE 1 LINES
(excerpt_id, manual_code, part_no, section_num, section_title, level, parent_id, sort_order, text);
