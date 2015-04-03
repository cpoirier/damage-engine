-- Damage Engine Copyright 2012-2015 Massive Damage, Inc.
--
-- Licensed under the Apache License, Version 2.0 (the "License"); you may not use this file except 
-- in compliance with the License. You may obtain a copy of the License at
--
--     http://www.apache.org/licenses/LICENSE-2.0
--
-- Unless required by applicable law or agreed to in writing, software distributed under the License 
-- is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express 
-- or implied. See the License for the specific language governing permissions and limitations under 
-- the License.



-- Create syntax for TABLE 'ServerAlertLog'
CREATE TABLE `ServerAlertLog` (
  `alert_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(15) unsigned NOT NULL,
  `ws` varchar(50) CHARACTER SET ascii DEFAULT NULL,
  `level` varchar(8) CHARACTER SET ascii NOT NULL DEFAULT 'unknown',
  `message` text CHARACTER SET ascii NOT NULL,
  `client_version` varchar(10) CHARACTER SET ascii DEFAULT NULL,
  `timestamp` datetime NOT NULL,
  `ws_id` int(11) DEFAULT NULL,
  `signature` varchar(100) CHARACTER SET ascii DEFAULT NULL,
  `mailed` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`alert_id`),
  KEY `user_id` (`user_id`),
  KEY `signature_lookup` (`signature`,`level`,`mailed`,`timestamp`)
) ENGINE=MyISAM AUTO_INCREMENT=95 DEFAULT CHARSET=utf8;

-- Create syntax for TABLE 'ServerCacheControlData'
CREATE TABLE `ServerCacheControlData` (
  `aspect` varchar(60) NOT NULL DEFAULT '',
  `cutoff` datetime NOT NULL,
  `description` text,
  `extra` text,
  PRIMARY KEY (`aspect`),
  KEY `lookup` (`aspect`,`cutoff`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Create syntax for TABLE 'ServerExecutionLog'
CREATE TABLE `ServerExecutionLog` (
  `ws_id` int(11) NOT NULL AUTO_INCREMENT,
  `timestamp` datetime NOT NULL,
  `index_s` int(10) unsigned NOT NULL,
  `index_u` int(11) unsigned NOT NULL,
  `app` char(8) CHARACTER SET ascii DEFAULT NULL,
  `service` char(30) CHARACTER SET ascii DEFAULT NULL,
  `user_id` int(11) unsigned DEFAULT NULL,
  `client_ip` char(40) CHARACTER SET ascii DEFAULT NULL,
  `client_version` char(10) CHARACTER SET ascii DEFAULT NULL,
  `client_platform` char(7) CHARACTER SET ascii DEFAULT NULL,
  `server_ip` char(15) CHARACTER SET ascii DEFAULT NULL,
  `response_time_ms` int(11) unsigned DEFAULT NULL,
  `response_size` int(11) unsigned DEFAULT NULL,
  `db_reads` int(11) unsigned DEFAULT NULL,
  `db_writes` int(11) unsigned DEFAULT NULL,
  `db_time_ms` int(11) unsigned DEFAULT NULL,
  `cache_attempts` int(11) unsigned DEFAULT NULL,
  `cache_hits` int(11) unsigned DEFAULT NULL,
  `cache_writes` int(11) unsigned DEFAULT NULL,
  `cache_wait_time_ms` int(10) unsigned DEFAULT NULL,
  `cache_claim_time_ms` int(11) unsigned DEFAULT NULL,
  `peak_memory_usage` int(10) unsigned DEFAULT NULL,
  `tag` char(5) CHARACTER SET ascii DEFAULT NULL,
  PRIMARY KEY (`ws_id`),
  KEY `timestamp` (`timestamp`),
  KEY `service` (`service`,`user_id`,`index_s`)
) ENGINE=MyISAM AUTO_INCREMENT=113533769 DEFAULT CHARSET=utf8;

-- Create syntax for TABLE 'ServerStubDict'
CREATE TABLE `ServerStubDict` (
  `request_uri` varchar(500) NOT NULL DEFAULT '',
  `rule_priority` int(11) NOT NULL DEFAULT '30',
  `parameters_nvp` text,
  `response_text` text NOT NULL,
  `response_type` varchar(200) NOT NULL DEFAULT 'application/json',
  `response_code` varchar(200) NOT NULL DEFAULT '200 Found',
  KEY `service_name` (`request_uri`(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8;





-- Create syntax for TABLE 'DebugCacheClaimLog'
CREATE TABLE `DebugCacheClaimLog` (
  `claim_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `ws_id` int(11) NOT NULL,
  `ws` varchar(50) NOT NULL DEFAULT '',
  `user_id` int(11) NOT NULL,
  `key` varchar(100) NOT NULL DEFAULT '',
  `timestamp` datetime NOT NULL,
  `completed_u` decimal(17,6) NOT NULL,
  `acquired` tinyint(4) NOT NULL,
  `wait_time_ms` int(11) NOT NULL,
  `initial_blocker_id` int(11) DEFAULT NULL,
  `blocker_summary` text,
  `blocker_log` text,
  `released` datetime DEFAULT NULL,
  `released_u` decimal(17,6) DEFAULT NULL,
  `release_track` text,
  PRIMARY KEY (`claim_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- Create syntax for TABLE 'DebugExecutionReportLog'
CREATE TABLE `DebugExecutionReportLog` (
  `ws_id` int(11) unsigned NOT NULL,
  `report` text NOT NULL,
  PRIMARY KEY (`ws_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- Create syntax for TABLE 'DebugExecutionTimingLog'
CREATE TABLE `DebugExecutionTimingLog` (
  `ws_id` int(11) unsigned NOT NULL,
  `index` int(11) NOT NULL,
  `elapsed_ms` int(11) NOT NULL,
  `label` varchar(150) NOT NULL DEFAULT '',
  `notes` text,
  PRIMARY KEY (`ws_id`,`index`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- Create syntax for TABLE 'DebugResponseLog'
CREATE TABLE `DebugResponseLog` (
  `script_id` int(11) unsigned NOT NULL,
  `script_name` varchar(50) NOT NULL DEFAULT '',
  `response_text` text NOT NULL,
  `request_json` text NOT NULL,
  PRIMARY KEY (`script_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;





-- Create syntax for TABLE 'GameEventLog'
CREATE TABLE `GameEventLog` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `subject` varchar(40) CHARACTER SET ascii DEFAULT NULL,
  `verb` varchar(40) CHARACTER SET ascii DEFAULT NULL,
  `details` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `timestamp` (`timestamp`),
  KEY `verb` (`verb`,`timestamp`),
  KEY `subject` (`subject`,`timestamp`),
  KEY `subject_2` (`subject`,`verb`,`timestamp`)
) ENGINE=InnoDB AUTO_INCREMENT=90 DEFAULT CHARSET=utf8;

-- Create syntax for TABLE 'GameParameterDict'
CREATE TABLE `GameParameterDict` (
  `param_id` varchar(50) CHARACTER SET ascii NOT NULL DEFAULT '',
  `value` text NOT NULL,
  `comments` varchar(50) CHARACTER SET ascii NOT NULL DEFAULT '',
  PRIMARY KEY (`param_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Create syntax for TABLE 'GameStringDict'
CREATE TABLE `GameStringDict` (
  `name` varchar(60) CHARACTER SET ascii NOT NULL,
  `language` varchar(5) CHARACTER SET ascii NOT NULL,
  `text` text NOT NULL,
  `interests_client` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`name`,`language`),
  KEY `gamestrings_ibfk_1` (`language`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;






-- Create syntax for TABLE 'UserPermissionChangeLog'
CREATE TABLE `UserPermissionChangeLog` (
  `user_id` int(11) unsigned NOT NULL,
  `timestamp` datetime NOT NULL,
  `op` varchar(6) CHARACTER SET ascii NOT NULL,
  `permission` varchar(60) CHARACTER SET ascii NOT NULL DEFAULT '',
  `before` varchar(120) CHARACTER SET ascii NOT NULL DEFAULT '',
  `after` varchar(120) CHARACTER SET ascii NOT NULL DEFAULT '',
  `changed_by` int(10) unsigned DEFAULT NULL,
  KEY `user_id` (`user_id`,`timestamp`),
  KEY `changed_by` (`changed_by`,`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Create syntax for TABLE 'UserPermissionData'
CREATE TABLE `UserPermissionData` (
  `user_id` int(11) unsigned NOT NULL,
  `permission` varchar(60) CHARACTER SET ascii NOT NULL DEFAULT '',
  `value` tinyint(1) NOT NULL,
  `until` datetime DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`,`permission`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;



