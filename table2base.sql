CREATE TABLE IF NOT EXISTS table2base (
  page_id varchar(50) NULL DEFAULT NULL,
  tag_text varchar(5000) NULL DEFAULT NULL,
  time timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  name varchar(100) NULL
)