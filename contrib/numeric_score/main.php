<?php
/**
 * Name: Image Scores (Numeric)
 * Author: Shish <webmaster@shishnet.org>
 * License: GPLv2
 * Description: Allow users to score images
 */

class NumericScoreSetEvent extends Event {
	var $image_id, $user, $score;

	public function NumericScoreSetEvent($image_id, $user, $score) {
		$this->image_id = $image_id;
		$this->user = $user;
		$this->score = $score;
	}
}

class NumericScore extends Extension {
	var $theme;

	public function receive_event($event) {
		if(is_null($this->theme)) $this->theme = get_theme_object("numeric_score", "NumericScoreTheme");

		if(is_a($event, 'InitExtEvent')) {
			global $config;
			if($config->get_int("ext_numeric_score_version", 0) < 1) {
				$this->install();
			}
		}
		
		if(is_a($event, 'DisplayingImageEvent')) {
			global $user;
			if(!$user->is_anonymous()) {
				$html = $this->theme->get_voter_html($event->image);
				$event->page->add_block(new Block("Image Score", $html, "left", 20));
			}
		}
		
		if(is_a($event, 'PageRequestEvent') && ($event->page_name == "numeric_score_vote")) {
			if(!$event->user->is_anonymous()) {
				$image_id = int_escape($_POST['image_id']);
				$char = $_POST['vote'];
				$score = 0;
				if($char == "up") $score = 1;
				else if($char == "down") $score = -1;
				if($score != 0) send_event(new NumericScoreSetEvent($image_id, $event->user, $score));
				$event->page->set_mode("redirect");
				$event->page->set_redirect(make_link("post/view/$image_id"));
			}
		}
		
		if(is_a($event, 'ImageInfoSetEvent')) {
			global $user;
			$char = $_POST['numeric_score'];
			$score = 0;
			if($char == "u") $score = 1;
			else if($char == "d") $score = -1;
			if($score != 0) send_event(new NumericScoreSetEvent($event->image_id, $user, $score));
		}
		
		if(is_a($event, 'NumericScoreSetEvent')) {
			$this->add_vote($event->image_id, $event->user->id, $event->score);
		}

		if(is_a($event, 'ImageDeletionEvent')) {
			global $database;
			$database->execute("DELETE FROM numeric_score_votes WHERE image_id=?", array($event->image->id));
		}

		if(is_a($event, 'ParseLinkTemplateEvent')) {
			$event->replace('$score', $event->image->numeric_score);
		}

		if(is_a($event, 'SearchTermParseEvent')) {
			$matches = array();
			if(preg_match("/score(<|<=|=|>=|>)(\d+)/", $event->term, $matches)) {
				$cmp = $matches[1];
				$score = $matches[2];
				$event->set_querylet(new Querylet("numeric_score $cmp $score"));
			}
		}
	}

	private function install() {
		global $database;
		global $config;

		if($config->get_int("ext_numeric_score_version") < 1) {
			$database->Execute("ALTER TABLE images ADD COLUMN numeric_score INTEGER NOT NULL DEFAULT 0");
			$database->Execute("CREATE INDEX images__numeric_score ON images(numeric_score)");
			$database->Execute("
				CREATE TABLE numeric_score_votes (
					image_id INTEGER NOT NULL,
					user_id INTEGER NOT NULL,
					score INTEGER NOT NULL,
					UNIQUE(image_id, user_id),
					INDEX(image_id)
				)
			");
			$config->set_int("ext_numeric_score_version", 1);
		}
	}

	private function add_vote($image_id, $user_id, $score) {
		global $database;
		$database->Execute(
			"DELETE FROM numeric_score_votes WHERE image_id=? AND user_id=?",
			array($image_id, $user_id));
		$database->Execute(
			"INSERT INTO numeric_score_votes(image_id, user_id, score) VALUES(?, ?, ?)",
			array($image_id, $user_id, $score));
		$database->Execute(
			"UPDATE images SET numeric_score=(SELECT SUM(score) FROM numeric_score_votes WHERE image_id=?) WHERE id=?",
			array($image_id, $image_id));
	}
}
add_event_listener(new NumericScore());
?>