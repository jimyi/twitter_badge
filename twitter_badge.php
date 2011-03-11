<?php
	/*
		Twitter Badge
		Version 1.2
		June 25, 2009
		http://www.jimyi.com/code/twitter_badge/
		open source, no restrictions
	*/

	/** configuration **/

	$config = array(
		'username'     => 'jimyi',  // twitter username
		'update_count' => 5,  // number of updates to show; should be 20 or less
		'update_time'  => 5,  // update every # minutes
		'hide_replies' => TRUE,  // hide your @replies from the badge
		'filename'     => 'twitter_cache.txt',  // file to use as cache, must be writable by PHP
	);

	/** end configuration - nothing else needs to be modified **/

	$tb = new Twitter_Badge($config);
	$tb->display_tweets();
	
	class Twitter_Badge {

		private $url = 'http://twitter.com/statuses/user_timeline.rss?screen_name=';

		private $tweets = array();
		private $last_update;

		public function __construct($config_array) {
			// load the config values into the object
			foreach ($config_array as $key => $value) {
				$this->$key = $value;
			}

			$this->load_tweets();
		}

		public function display_tweets() {
			// print the tweets as list items
			foreach ($this->tweets as $update) {
				$status = htmlentities($update['status']);
				// parse URLs into links
				$status = ereg_replace("[[:alpha:]]+://[^ ]+","<a href=\"\\0\">\\0</a>", $status);
				// parse any @users into links
				$status = ereg_replace("@([a-zA-Z0-9_]+)","<a href=\"http://twitter.com/\\1\">\\0</a>", $status);

				$item  = '<li>' . $status . '&nbsp;';
				$item .= '<a href="' . htmlentities($update['link']) . '">';
				$item .= $this->calculate_age($update['created']) . "</a></li>\n";
				echo $item;
			}
		}

		/** begin private functions **/

		private function load_tweets() {
			$this->load_file_cache();

			// retrieve tweets from Twitter if the last update is too old
			// the return of fetch_tweets is checked in case there was an error
			// loading the feed (API limit, twitter down, etc)
			if ($this->is_cache_old() && $this->fetch_tweets() != 0) {
				$this->save_file_cache();
			}
		}

		private function fetch_tweets() {
			$doc = new DOMDocument('1.0', 'UTF-8');
			$fetch = @$doc->load($this->url . $this->username); // fetch the user's RSS feed

			$count = 0;

			if ($fetch) {
				$this->tweets = array();
				foreach ($doc->getElementsByTagName('item') as $node) {
					if ($count == $this->update_count) break;

					$status = $node->getElementsByTagName('title')->item(0)->nodeValue;
					// each status starts with "username: " - filter that out
					$status = substr($status, strpos($status, ':') + 2);
					// filter out replies
					if ($status[0] == '@' && $this->hide_replies) continue;

					$link = $node->getElementsByTagName('link')->item(0)->nodeValue;

					$update = array (
						'status' => $status,
						'link' => $link,
						'created' => $node->getElementsByTagName('pubDate')->item(0)->nodeValue
					);
					array_push($this->tweets, $update);
					$count++;
				}
			}
			return $count;
		}

		private function is_cache_old() {
			$time_since = time() - $this->last_update;
			return $time_since >= $this->update_time * 60;
		}

		private function load_file_cache() {
			$data = @file_get_contents($this->filename);
			if (!$data) {
				// the cache file might not exist yet
				return;
			}
			$this->tweets = unserialize($data);
			$this->last_update = $this->tweets[$this->update_count];
			unset($this->tweets[$this->update_count]);
		}

		private function save_file_cache() {
			// make the last item our update time
			$this->tweets[$this->update_count] = time();
			file_put_contents($this->filename, serialize($this->tweets));
			unset($this->tweets[$this->update_count]);
		}

		private function calculate_age($date) {
			$age = time() - strtotime($date);

			// twitter style string for age
			if ($age < 60) return "less than a minute ago";
			elseif ($age < 120) return "about 1 minute ago";
			elseif ($age < 3600) return "about ".floor($age/60)." minutes ago";
			elseif ($age < 3600 * 24) return "about ".floor($age/3600)." hours ago";
			elseif ($age < 3600 * 24 * 2) return "1 day ago";
			else return floor($age/3600/24)." days ago";
			// we don't do X months ago, even if days is > 30
			// besides, if one your most recent tweets is over a month ago, maybe you should tweet more :)
		}

	}
