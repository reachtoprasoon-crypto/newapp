CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actor_name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `action` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `details` text COLLATE utf8_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
CREATE TABLE `aptitude_marks` (
  `sid` int(11) NOT NULL,
  `marks` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
CREATE TABLE `attendance` (
  `attid` int(11) NOT NULL,
  `sid` int(11) NOT NULL,
  `termid` int(11) NOT NULL,
  `report` int(2) NOT NULL DEFAULT '1',
  `attendance` int(11) NOT NULL,
  `totalattendance` int(11) NOT NULL,
  `comid` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
CREATE TABLE `attendance_holidays` (
  `id` int(11) NOT NULL,
  `date` date NOT NULL,
  `sclass` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `description` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
CREATE TABLE `attendance_settings` (
  `sclass` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `is_active` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
CREATE TABLE `backuphtwt` (
  `hwid` int(11) NOT NULL DEFAULT '0',
  `sid` int(6) NOT NULL,
  `ht` int(4) NOT NULL,
  `wt` int(4) NOT NULL,
  `termid` int(5) NOT NULL,
  `report` int(5) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
CREATE TABLE `classes` (
  `clid` int(11) NOT NULL,
  `sclass` varchar(4) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
CREATE TABLE `comments` (
  `comid` int(3) NOT NULL,
  `comment` tinytext
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
CREATE TABLE `communications` (
  `commid` int(11) NOT NULL,
  `tid` int(11) NOT NULL,
  `sclass` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `content` text COLLATE utf8_unicode_ci,
  `attachment_name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `attachment_file` mediumtext COLLATE utf8_unicode_ci,
  `comm_type` enum('Notice','Homework','Worksheet','Other') COLLATE utf8_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
CREATE TABLE `controls` (
  `conid` int(11) NOT NULL,
  `control` varchar(30) COLLATE utf8_unicode_ci NOT NULL,
  `cval` int(11) NOT NULL,
  `allowed` tinyint(1) NOT NULL DEFAULT '1',
  `ctype` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `cdata` mediumtext COLLATE utf8_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
CREATE TABLE `daily_attendance` (
  `id` int(11) NOT NULL,
  `sid` int(11) NOT NULL,
  `date` date NOT NULL,
  `status` varchar(10) COLLATE utf8_unicode_ci DEFAULT 'Present',
  `marked_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
CREATE TABLE `datafortq` (
  `schno` int(7) NOT NULL,
  `sname` varchar(150) COLLATE utf8_unicode_ci NOT NULL,
  `sclass` varchar(5) COLLATE utf8_unicode_ci NOT NULL,
  `dob` varchar(15) COLLATE utf8_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
CREATE TABLE `data_collection_forms` (
  `id` int(11) NOT NULL,
  `tid` int(11) NOT NULL,
  `title` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `description` text COLLATE utf8_unicode_ci,
  `fields_json` longtext COLLATE utf8_unicode_ci NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
CREATE TABLE `data_collection_responses` (
  `id` int(11) NOT NULL,
  `form_id` int(11) NOT NULL,
  `sid` int(11) NOT NULL,
  `response_json` longtext COLLATE utf8_unicode_ci NOT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
CREATE TABLE `exams` (
  `exid` int(11) NOT NULL,
  `examname` varchar(30) NOT NULL,
  `examshort` varchar(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
CREATE TABLE `finalhic` (
  `fhicid` int(11) NOT NULL,
  `sclass` varchar(5) NOT NULL,
  `subid` int(5) NOT NULL,
  `hic` int(5) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
CREATE TABLE `finalhictotal` (
  `fhtid` int(11) NOT NULL,
  `sclass` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `thic` decimal(7,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
CREATE TABLE `finaltotal` (
  `sid` int(6) NOT NULL,
  `finaltot` int(5) NOT NULL,
  `rank` int(3) NOT NULL,
  `per` decimal(5,2) NOT NULL,
  `total_marks` decimal(7,2) NOT NULL,
  `percentage` decimal(5,2) NOT NULL DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
CREATE TABLE `finlhictotal` (
  `id` int(11) NOT NULL,
  `sclass` varchar(5) NOT NULL,
  `hictot` int(5) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
CREATE TABLE `grades` (
  `gid` int(11) NOT NULL,
  `sid` int(11) NOT NULL,
  `subid` int(11) NOT NULL,
  `termid` int(11) NOT NULL,
  `report` int(2) NOT NULL DEFAULT '1',
  `grade` varchar(5) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
CREATE TABLE `hic` (
  `hicid` int(11) NOT NULL,
  `sclass` varchar(5) NOT NULL,
  `subid` int(11) NOT NULL,
  `termid` int(11) NOT NULL,
  `report` int(2) NOT NULL DEFAULT '1',
  `hic` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
CREATE TABLE `house` (
  `hid` int(11) NOT NULL DEFAULT '0',
  `house` varchar(30) NOT NULL,
  `colour` varchar(15) NOT NULL,
  `contrast` varchar(15) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
CREATE TABLE `htwt` (
  `hwid` int(11) NOT NULL,
  `sid` int(6) NOT NULL,
  `ht` int(4) NOT NULL,
  `wt` int(4) NOT NULL,
  `termid` int(5) NOT NULL,
  `report` int(5) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
CREATE TABLE `lesson_plans` (
  `lpid` int(11) NOT NULL,
  `tid` int(11) NOT NULL,
  `sclass` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `subid` int(11) NOT NULL,
  `plan_date` date NOT NULL,
  `topic` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `objectives` text COLLATE utf8_unicode_ci,
  `content` text COLLATE utf8_unicode_ci NOT NULL,
  `image` mediumtext COLLATE utf8_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
CREATE TABLE `marks` (
  `mid` int(11) NOT NULL,
  `sid` int(11) NOT NULL,
  `marks` int(11) NOT NULL,
  `termschid` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
CREATE TABLE `newstu` (
  `sid` int(10) DEFAULT NULL,
  `schno` int(5) DEFAULT NULL,
  `roll` int(2) DEFAULT NULL,
  `sname` varchar(30) DEFAULT NULL,
  `pname` varchar(30) DEFAULT NULL,
  `mname` varchar(29) DEFAULT NULL,
  `gender` varchar(1) DEFAULT NULL,
  `dob` varchar(10) DEFAULT NULL,
  `sclass` varchar(3) DEFAULT NULL,
  `branch` varchar(20) DEFAULT NULL,
  `hid` int(1) DEFAULT NULL,
  `ht` varchar(3) DEFAULT NULL,
  `wt` varchar(3) DEFAULT NULL,
  `phone` varchar(10) DEFAULT NULL,
  `photo` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
CREATE TABLE `promotion` (
  `pid` int(11) NOT NULL,
  `sid` int(11) NOT NULL,
  `promotion` varchar(50) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'PROMOTION GRANTED'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
CREATE TABLE `questions` (
  `qid` int(11) NOT NULL,
  `qpid` int(11) NOT NULL,
  `question_text` text COLLATE utf8_unicode_ci NOT NULL,
  `question_image` mediumtext COLLATE utf8_unicode_ci,
  `option_a` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `option_a_image` mediumtext COLLATE utf8_unicode_ci,
  `option_b` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `option_b_image` mediumtext COLLATE utf8_unicode_ci,
  `option_c` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `option_c_image` mediumtext COLLATE utf8_unicode_ci,
  `option_d` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `option_d_image` mediumtext COLLATE utf8_unicode_ci,
  `correct_option` enum('A','B','C','D') COLLATE utf8_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
CREATE TABLE `question_papers` (
  `qpid` int(11) NOT NULL,
  `tid` int(11) NOT NULL,
  `sclass` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `subid` int(11) NOT NULL,
  `title` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
CREATE TABLE `reopen` (
  `id` int(11) NOT NULL,
  `reopen` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
CREATE TABLE `reports` (
  `crid` int(11) NOT NULL,
  `currreport` varchar(10) COLLATE utf8_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
CREATE TABLE `sttu` (
  `sid` int(4) NOT NULL,
  `schno` varchar(7) DEFAULT NULL,
  `sname` varchar(33) DEFAULT NULL,
  `pname` varchar(30) DEFAULT NULL,
  `sclass` varchar(3) DEFAULT NULL,
  `branch` varchar(9) DEFAULT NULL,
  `hid` int(1) DEFAULT NULL,
  `roll` int(2) DEFAULT NULL,
  `dob` varchar(15) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
CREATE TABLE `students` (
  `sid` int(4) NOT NULL,
  `schno` int(5) DEFAULT NULL,
  `roll` int(2) DEFAULT NULL,
  `sname` varchar(30) DEFAULT NULL,
  `pname` varchar(30) DEFAULT NULL,
  `mname` varchar(29) DEFAULT NULL,
  `dob` varchar(10) DEFAULT NULL,
  `sclass` varchar(3) DEFAULT NULL,
  `branch` varchar(20) DEFAULT NULL,
  `hid` int(1) DEFAULT NULL,
  `ht` int(5) DEFAULT NULL,
  `wt` int(3) DEFAULT NULL,
  `photo` mediumtext,
  `phone` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
CREATE TABLE `students_old` (
  `sid` int(11) NOT NULL,
  `schno` int(7) NOT NULL,
  `roll` int(3) NOT NULL,
  `sname` varchar(150) COLLATE utf8_unicode_ci NOT NULL,
  `pname` varchar(150) COLLATE utf8_unicode_ci NOT NULL,
  `mname` varchar(150) COLLATE utf8_unicode_ci NOT NULL,
  `dob` varchar(15) COLLATE utf8_unicode_ci NOT NULL,
  `sclass` varchar(5) COLLATE utf8_unicode_ci NOT NULL,
  `branch` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  `hid` int(2) NOT NULL,
  `ht` int(3) NOT NULL,
  `wt` int(3) NOT NULL,
  `photo` mediumtext COLLATE utf8_unicode_ci,
  `phone` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
CREATE TABLE `student_notes` (
  `note_id` int(11) NOT NULL,
  `sid` int(11) NOT NULL,
  `tid` int(11) NOT NULL,
  `note_content` text COLLATE utf8_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
CREATE TABLE `subjective_papers` (
  `spid` int(11) NOT NULL,
  `tid` int(11) NOT NULL,
  `sclass` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `subid` int(11) NOT NULL,
  `title` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `instruction` text COLLATE utf8_unicode_ci,
  `max_marks` int(11) NOT NULL,
  `time_duration` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `elements_json` longtext COLLATE utf8_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
CREATE TABLE `subjects` (
  `subid` int(11) NOT NULL,
  `subname` varchar(50) NOT NULL,
  `subshort` varchar(10) NOT NULL,
  `subtype` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
CREATE TABLE `subjectteacher` (
  `stid` int(11) NOT NULL,
  `tid` int(11) NOT NULL,
  `subid` int(11) NOT NULL,
  `sclass` varchar(4) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
CREATE TABLE `subschedule` (
  `subschid` int(4) NOT NULL,
  `sclass` varchar(5) DEFAULT NULL,
  `subid` int(4) DEFAULT NULL,
  `schorder` int(4) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
CREATE TABLE `teacherhash` (
  `tid` int(11) NOT NULL DEFAULT '0',
  `tname` varchar(100) CHARACTER SET latin1 NOT NULL,
  `tuser` varchar(50) CHARACTER SET latin1 NOT NULL,
  `tpass` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `gender` char(1) CHARACTER SET latin1 NOT NULL DEFAULT 'F',
  `sclass` varchar(4) CHARACTER SET latin1 NOT NULL,
  `ttype` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
CREATE TABLE `teachers` (
  `tid` int(11) NOT NULL,
  `tname` varchar(100) NOT NULL,
  `tuser` varchar(50) NOT NULL,
  `tpass` varchar(20) NOT NULL DEFAULT 'password',
  `gender` char(1) NOT NULL DEFAULT 'F',
  `sclass` varchar(4) NOT NULL,
  `ttype` int(11) NOT NULL DEFAULT '0',
  `dob` date DEFAULT NULL,
  `phone` varchar(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
CREATE TABLE `termhic` (
  `thicid` int(11) NOT NULL,
  `sclass` varchar(10) NOT NULL,
  `termid` int(11) NOT NULL,
  `report` int(2) NOT NULL DEFAULT '1',
  `thic` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
CREATE TABLE `terms` (
  `termid` int(11) NOT NULL,
  `termname` varchar(50) NOT NULL,
  `termshort` varchar(5) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
CREATE TABLE `termschedule` (
  `termschid` int(11) NOT NULL,
  `sclass` varchar(5) NOT NULL,
  `termid` int(11) NOT NULL,
  `report` int(2) NOT NULL DEFAULT '1',
  `maxm` int(11) NOT NULL,
  `exid` int(11) NOT NULL,
  `subid` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
CREATE TABLE `timetable_constraints` (
  `id` int(11) NOT NULL,
  `ctype` enum('Parallel','Combined','Continuous') COLLATE utf8_unicode_ci NOT NULL,
  `sclass` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `subids_json` text COLLATE utf8_unicode_ci NOT NULL,
  `label` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `data_json` longtext COLLATE utf8_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
CREATE TABLE `timetable_load` (
  `id` int(11) NOT NULL,
  `sclass` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `subid` int(11) DEFAULT NULL,
  `periods_per_week` int(11) NOT NULL,
  `is_extra` tinyint(1) DEFAULT '0',
  `extra_name` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
CREATE TABLE `timetable_settings` (
  `group_id` int(11) NOT NULL,
  `group_name` varchar(250) COLLATE utf8_unicode_ci DEFAULT NULL,
  `working_days` int(11) NOT NULL DEFAULT '6',
  `periods_per_day` int(11) NOT NULL DEFAULT '8',
  `saturday_periods` int(11) NOT NULL DEFAULT '4'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
CREATE TABLE `timetable_slots` (
  `id` int(11) NOT NULL,
  `sclass` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `d_idx` int(11) NOT NULL,
  `p_idx` int(11) NOT NULL,
  `label` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `tid` int(11) DEFAULT NULL,
  `subid` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
CREATE TABLE `timetable_structure` (
  `id` int(11) NOT NULL,
  `sclass` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `slot_idx` int(11) NOT NULL,
  `label` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `start_time` varchar(10) COLLATE utf8_unicode_ci DEFAULT NULL,
  `end_time` varchar(10) COLLATE utf8_unicode_ci DEFAULT NULL,
  `duration` int(11) DEFAULT '0',
  `is_break` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
CREATE TABLE `timetable_substitutions` (
  `id` int(11) NOT NULL,
  `sub_date` date NOT NULL,
  `tid_absent` int(11) NOT NULL,
  `tid_substitute` int(11) NOT NULL,
  `p_idx` int(11) NOT NULL,
  `sclass` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `label` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
CREATE TABLE `workload` (
  `ttid` int(11) NOT NULL,
  `tid` int(11) NOT NULL,
  `subid` int(11) NOT NULL,
  `sclass` varchar(10) COLLATE utf8_unicode_ci NOT NULL,
  `nop` int(2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
