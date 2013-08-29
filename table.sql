
CREATE TABLE `jos_accountdata` (
  `bid` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `someval_facebook_valid` tinyint(1) NOT NULL DEFAULT '1',
  `someval_facebook_type` varchar(255) NOT NULL DEFAULT '',
  `someval_facebook_friends_or_likes` int(11) NOT NULL DEFAULT '0',
  `someval_twitter_valid` tinyint(1) NOT NULL DEFAULT '1',
  `someval_twitter_tweets` int(11) NOT NULL DEFAULT '0',
  `someval_twitter_followers` int(11) NOT NULL DEFAULT '0',
  `someval_twitter_following` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`bid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

