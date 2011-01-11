DROP TABLE IF EXISTS `user_preferences`;
DROP TABLE IF EXISTS `user_roles`;
DROP TABLE IF EXISTS `user_permissions`;
DROP TABLE IF EXISTS `role_permissions`;
DROP TABLE IF EXISTS `roles`;
DROP TABLE IF EXISTS `user_groups`;
DROP TABLE IF EXISTS `groups`;
DROP TABLE IF EXISTS `users`;

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `username` varchar(30) NOT NULL,
  `password` varchar(32) NOT NULL,
  `email` varchar(150) NOT NULL,
  `verified` tinyint(1) NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `staff` tinyint(1) NOT NULL DEFAULT '0',
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=49039 DEFAULT CHARSET=utf8;

--
-- Table structure for table `groups`
--

CREATE TABLE `groups` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `name` varchar(30) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=218 DEFAULT CHARSET=utf8;

--
-- Table structure for table `user_groups`
--

CREATE TABLE `user_groups` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user` bigint(20) DEFAULT NULL,
  `group` bigint(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user`),
  KEY `group_id` (`group`),
  CONSTRAINT `user_groups_ibfk_1` FOREIGN KEY (`user`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `user_groups_ibfk_2` FOREIGN KEY (`group`) REFERENCES `groups` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6468 DEFAULT CHARSET=utf8;

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` mediumtext NOT NULL,
  `internal` tinyint(1) NOT NULL DEFAULT '0',
  `sort_order` int(11) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8;

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `role` bigint(20) DEFAULT NULL,
  `module` varchar(50) NOT NULL,
  `type` varchar(50) NOT NULL,
  `instance` varchar(50) NOT NULL,
  `action` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `role_id` (`role`),
  KEY `role_id_2` (`role`,`module`,`type`,`instance`),
  KEY `role_id_3` (`role`,`module`,`type`,`action`),
  CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1191 DEFAULT CHARSET=utf8;

--
-- Table structure for table `user_permissions`
--

CREATE TABLE `user_permissions` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user` bigint(20) DEFAULT NULL,
  `module` varchar(50) NOT NULL,
  `type` varchar(50) NOT NULL,
  `instance` varchar(50) NOT NULL,
  `action` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user`),
  KEY `user_id_2` (`user`,`module`,`type`,`instance`),
  KEY `user_id_3` (`user`,`module`,`type`,`action`)
) ENGINE=InnoDB AUTO_INCREMENT=220 DEFAULT CHARSET=utf8;

--
-- Table structure for table `user_roles`
--

CREATE TABLE `user_roles` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user` bigint(20) DEFAULT NULL,
  `role` bigint(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user`),
  KEY `role_id` (`role`),
  CONSTRAINT `user_roles_ibfk_1` FOREIGN KEY (`user`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `user_roles_ibfk_2` FOREIGN KEY (`role`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7328 DEFAULT CHARSET=utf8;

--
-- Table structure for table `user_preferences`
--

CREATE TABLE `user_preferences` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user` bigint(20) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `value` mediumtext,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user`,`name`),
  KEY `user_id_2` (`user`),
  CONSTRAINT `user_preferences_ibfk_1` FOREIGN KEY (`user`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=727 DEFAULT CHARSET=utf8;

--
-- Initial user data
--

INSERT INTO users (`id`, `username`, `password`, `email`, `staff`) VALUES (1, 'admin', '5f4dcc3b5aa765d61d8327deb882cf99', 'nobody@example.com', TRUE);
INSERT INTO roles (`id`, `name`, `description`, `internal`) VALUES (1, 'Administrator', 'Unrestricted access to all areas of the application', TRUE);
INSERT INTO role_permissions(`role`, `module`, `type`, `instance`, `action`) VALUES (1, 'ALL', 'ALL', 'ALL','ALL');
INSERT INTO user_roles (`user`, `role`) VALUES (1, 1);
