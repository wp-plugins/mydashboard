<?php
/*
 * Default gadgets for the mydashboard Wordpress plugin
 * Author: Barry at clearskys.net
 * 
 * 
 */

require_once(ABSPATH . WPINC . '/rss.php');
/*
 * RSS Feed gadets
 */
 function mydash_word_limiter($str, $limit = 100, $end_char = '&#8230;') {
    
    if (trim($str) == '')
        return $str;
    
    preg_match('/^\s*(?:\S+\s*){1,'. (int) $limit .'}/', $str, $matches);

    if (strlen($matches[0]) == strlen($str))
        $end_char = '';

    return rtrim($matches[0]) . $end_char;
}
 
function mydash_create_incoming_links_feed($args = false) {
	// Creates the feed to get the technorati incoming links
	if(!isset($args['name'])) $args['name'] = "mydash_incoming_links";
	$mydash_i = get_option($args['name']);
	if(!isset($mydash_i['feed_uri'])) {
		// default settings not set up so create them
		$mydash_i['link_title'] = 'Incoming Links';
		$mydash_i['feed_uri'] = 'http://feeds.technorati.com/cosmos/rss/?url='. trailingslashit(get_option('home')) .'&partner=wordpress';
		$mydash_i['link_uri'] = 'http://www.technorati.com/search/' . trailingslashit(get_option('home')) . '?partner=wordpress';
		$mydash_i['item_template'] = '<a href="{link}">{title}</a>';
		$mydash_i['numitems'] = 10;
		update_option($args['name'],$mydash_i);
	}
	return true;
}

function mydash_create_dev_rss_feed($args = false) {
	// Creates the feed to get the technorati incoming links
	if(!isset($args['name'])) $args['name'] = "mydash_dev_rss_feed";
	$mydash_feed = get_option($args['name']);
	if(!isset($mydash_feed['feed_uri'])) {
		// default settings not set up so create them
		$mydash_feed['link_title'] = 'WordPress Development Blog';
		$mydash_feed['feed_uri'] = 'http://wordpress.org/development/feed/';
		$mydash_feed['link_uri'] = 'http://wordpress.org/development/';
		$mydash_feed['item_template'] = '<a href="{link}">{title}</a> &#8212; {ago}<br/>{desc}';
		$mydash_feed['numitems'] = 3;
		update_option($args['name'],$mydash_feed);
	}
	return true;
}

function mydash_create_planet_rss_feed($args = false) {
	// Creates the feed to get the technorati incoming links
	if(!isset($args['name'])) $args['name'] = "mydash_planet_rss_feed";
	$mydash_feed = get_option($args['name']);
	if(!isset($mydash_feed['feed_uri'])) {
		// default settings not set up so create them
		$mydash_feed['link_title'] = 'Other WordPress News';
		$mydash_feed['feed_uri'] = 'http://planet.wordpress.org/feed/';
		$mydash_feed['link_uri'] = 'http://planet.wordpress.org/';
		$mydash_feed['item_template'] = '<a href="{link}">{title}</a>';
		$mydash_feed['numitems'] = 20;
		update_option($args['name'],$mydash_feed);
	}
	return true;
}
 
function mydash_create_blank_feed_gadget($args = false) {
	// Creates a blank feed gadget
	$mydash_feed = get_option($args['name']);
	
	// not bothered about overwriting existing settings here
	// as any existing data will be from a previously
	// removed, and thus not wanted instance of the gadget
	$mydash_feed['link_title'] = 'RSS Feed';
	$mydash_feed['feed_uri'] = '';
	$mydash_feed['link_uri'] = '';
	$mydash_feed['item_template'] = '<a href="{link}">{title}</a>';
	$mydash_feed['numitems'] = 5;
	update_option($args['name'],$mydash_feed);
	
	return true;
	
	
}

function mydash_display_feed_gadget($args = false) {
	
	$mydash_feed = get_option($args['name']);
	if(empty($mydash_feed['feed_uri'])) {
		$mytitle = "RSS Feed";
		$mytitleicon = "";
		$mycontent = 'Select a feed to load...';
	} else {
		if($_GET['call'] == '_ajax' && ($_GET['action'] == 'updatecontent' || $_GET['action'] == "sendedit")) {
			// the code to grab the feed goes here
			$mycontent = "";
			$rss = @fetch_rss($mydash_feed['feed_uri']);
			if ( isset($rss->items) && 0 != count($rss->items) ) { 
				// a feed exists - woo hoo
				// check if we still have an old feed title
				// and if so update it here quickly
				if(empty($mydash_feed['link_title']) || $mydash_feed['link_title'] != $rss->channel['title']) {
					$mydash_feed['link_title'] = $rss->channel['title'];
					$mydash_feed['link_uri'] = $rss->channel['link'];
					update_option($args['name'],$mydash_feed);
				}
				
				if(!isset($mydash_feed['numitems'])) $mydash_feed['numitems'] = 5;
				$rss->items = array_slice($rss->items, 0, $mydash_feed['numitems']);
				$mycontent .= '<ul class="rssfeed">';
				if(!isset($mydash_feed['item_template'])) $mydash_feed['item_template'] = '<a href="{link}">{title}</a>';
				foreach ($rss->items as $item ) {
					$link = $mydash_feed['item_template'];
					$link = str_replace('{link}',wp_filter_kses($item['link']), $link);
					$link = str_replace('{title}',wptexturize(wp_specialchars($item['title'])), $link);
					$link = str_replace('{ago}',sprintf(__('%s ago'), human_time_diff(strtotime($item['pubdate'], time() ) ) ), $link);
					$link = str_replace('{desc}',mydash_word_limiter($item['description'],50), $link);
					//$link = str_replace('{author}',mydash_word_limiter($item['description'],50), $link);
					$mycontent .= '<li>' . $link . '</li>';
				}
				$mycontent .= '</ul>';
			}
		} else {
			
			$html = '<img src="404.gif" style="position:absolute;width:0px;height:0px" onerror="(function(){';
			$html .= 'function init(){';
			$html .= "myDash(function() {myDash('#" . $args['name'] . "').myDashGetContent();});";
			$html .= '}';
			$html .= 'var _interval = setInterval(function(){';
			$html .= 'clearInterval(_interval);';
			$html .= 'init();';
			$html .= '}, 10);';
			$html .= '})()" />';
			
			$mycontent = $html . 'Loading Feed...';
		}
		$mytitleicon = '<a href="' . $mydash_feed['feed_uri'] . '" class="feedlinkicon">' . '</a>';
		$mytitle = '<a href="' . $mydash_feed['link_uri'] . '">' . $mydash_feed['link_title'] . '</a>';
	}
	
	return array('title' => $mytitle, 'titleicon' => $mytitleicon,'content' => $mycontent);
}

function mydash_edit_lockedfeed_gadget($args = false) {
	$mydash_feed = get_option($args['name']);
	
	if(!empty($mydash_feed) && $_POST['update'] == $args['name']) {
		// An update has been submited so change the settings for the gadget here
		if($_POST['numitems'] != $mydash_feed['numitems']) {
			$mydash_feed['numitems'] = $_POST['numitems'];
			update_option($args['name'],$mydash_feed);
		}
		
	}
	
	if(!isset($mydash_feed['numitems'])) {
		$mydash_feed['numitems'] = 3;
	}
	 
	$ed = '<p>To modify this gadget, set the new options below and click on the <strong>Update settings</strong> button.</p>';
	$ed .= '<label for="numtiems">Show</label>';
	
	$ed .= '<select name="numitems">';
	for($n = 1; $n <= 99; $n++) {
		$ed .= '<option value="' . $n . '"';
		if($mydash_feed['numitems'] == $n) {
			$ed .= ' selected="selected"';
		}
		$ed .= '>' . $n . ' item(s)</option>';
	}
	$ed .= '</select>';
	
	$ed .= '<input type="submit" name="submit" value="Update Settings &raquo;" />';
	$ed .= '<input type="hidden" name="update" value="' . $args['name'] . '" style="display: none" />';
	
	return $ed;
}

function mydash_edit_feed_gadget($args = false) {
	
	$mydash_feed = get_option($args['name']);
	
	if(!empty($mydash_feed) && $_POST['update'] == $args['name']) {
		// An update has been submited so change the settings for the gadget here
		if($_POST['numitems'] != $mydash_feed['numitems']) {
			$mydash_feed['numitems'] = $_POST['numitems'];
			
		}
		if($_POST['feed_uri'] != $mydash_feed['feed_uri']) {
			$mydash_feed['feed_uri'] = $_POST['feed_uri'];
		}
		update_option($args['name'],$mydash_feed);
	}
	
	if(!isset($mydash_feed['numitems'])) {
		$mydash_feed['numitems'] = 5;
	}
	 
	$ed = '<p>To modify this gadget, set the new options below and click on the <strong>Update settings</strong> button.</p>';
	$ed .= '<label for="feed_uri">Feed url (the RSS url, NOT the website url)</label>';
	$ed .= '<input type="text" name="feed_uri" value="' . $mydash_feed['feed_uri'] . '" style="width: 90%" />';
	$ed .= '<label for="numtiems">Show</label>';
	
	$ed .= '<select name="numitems">';
	for($n = 1; $n <= 99; $n++) {
		$ed .= '<option value="' . $n . '"';
		if($mydash_feed['numitems'] == $n) {
			$ed .= ' selected="selected"';
		}
		$ed .= '>' . $n . ' item(s)</option>';
	}
	$ed .= '</select>';
	
	$ed .= '<input type="submit" name="submit" value="Update Settings &raquo;" />';
	$ed .= '<input type="hidden" name="update" value="' . $args['name'] . '" style="display: none" />';
	
	return $ed;
}

/*
 *  Useful links gadget
 * 
 */
function mydash_quick_links_gadget($args = false) {
	// Do nothing
}

function mydash_display_quick_links($args = false) {
	global $wpdb;
	
	$mytitle = "Quick Links";
	
	$mycontent = '<p>' . __('Use these links to get started:') . '</p>';
	$mycontent .= '<ul>';
	if ( current_user_can('edit_posts') ) :
	$mycontent .= '<li><a href="post-new.php">' . __('Write a post') . '</a></li>';
	endif;
	$mycontent .= '<li><a href="profile.php">' . __('Update your profile or change your password') . '</a></li>';
	if ( current_user_can('manage_links') ) :
	$mycontent .= '<li><a href="link-add.php">' . __('Add a link to your blogroll') . '</a></li>';
	endif;
	if ( current_user_can('switch_themes') ) :
	$mycontent .= '<li><a href="themes.php">' . __('Change your site&#8217;s look or theme') . '</a></li>';
	endif;
	$mycontent .= '</ul>';
	$mycontent .= '<p>' . __("Need help with WordPress? Please see our <a href='http://codex.wordpress.org/'>documentation</a> or visit the <a href='http://wordpress.org/support/'>support forums</a>.") . '</p>';
	
	return array('title' => $mytitle, 'content' => $mycontent);
}

function mydash_edit_quick_links($args = false) {
	return "No editable options.";
}

/*
 *  Latest comments gadget
 * 
 */
function mydash_create_latest_comments_gadget($args = false) {
	// Do nothing
}

function mydash_display_latest_comments($args = false) {
	global $wpdb;
	
	
	$comments = $wpdb->get_results("SELECT comment_author, comment_author_url, comment_ID, comment_post_ID FROM $wpdb->comments WHERE comment_approved = '1' ORDER BY comment_date_gmt DESC LIMIT 5");
	$numcomments = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->comments WHERE comment_approved = '0'");
	
	$mytitle = __('Comments') . ' <a href="edit-comments.php" title="' . __('More comments...') . '">&raquo;</a>';
	
	if ( $comments || $numcomments ) :
	ob_start();
	?>
	<?php if ( $numcomments ) : ?>
	<p><strong><a href="moderation.php"><?php echo sprintf(__('Comments in moderation (%s)'), number_format($numcomments) ); ?> &raquo;</a></strong></p>
	<?php endif; ?>
	
	<ul>
	<?php
	if ( $comments ) {
	foreach ($comments as $comment) {
		if(empty( $comment->comment_author_url ) || 'http://' == $comment->comment_author_url) {
			$commentlink = $comment->comment_author;
		} else {
			$commentlink = "<a href='" . $comment->comment_author_url . "'>" . $comment->comment_author . "</a>";
		}
		echo '<li>' . sprintf(__('%1$s on %2$s'), $commentlink, '<a href="'. get_permalink($comment->comment_post_ID) . '#comment-' . $comment->comment_ID . '">' . apply_filters('the_title', get_the_title($comment->comment_post_ID)) . '</a>');
		edit_comment_link(__("Edit"), ' <small>(', ')</small>');
		echo '</li>';
	}
	}
	?>
	</ul>
	<?php endif;
	$mycontent = ob_get_contents();
	ob_end_clean();	
	
	return array('title' => $mytitle, 'content' => $mycontent);
}

function mydash_edit_latest_comments($args = false) {
	return "No editable options.";
}

/*
 * Posts
 */
 function mydash_create_posts($args = false) {
 	// do nothing
 }
 
 function mydash_edit_posts($args = false) {
 	return "No editable options.";
 }
 
function mydash_display_posts($args = false) {
	global $wpdb, $post;
	
	$today = current_time('mysql', 1);
	
	$mytitle = __('Posts') . ' <a href="edit.php" title="' . __('More posts...') . '">&raquo;</a>';

	if ( $recentposts = $wpdb->get_results("SELECT ID, post_title FROM $wpdb->posts WHERE post_type = 'post' AND " . get_private_posts_cap_sql('post') . " AND post_date_gmt < '$today' ORDER BY post_date DESC LIMIT 5") ) :
	ob_start();
	?>
	<ul>
	<?php
	foreach ($recentposts as $post) {
		if ($post->post_title == '')
			$post->post_title = sprintf(__('Post #%s'), $post->ID);
		echo "<li><a href='post.php?action=edit&amp;post=$post->ID'>";
		the_title();
		echo '</a></li>';
	}
	?>
	</ul>
	<?php endif;
	$mycontent = ob_get_contents();
	ob_end_clean();	
	
	return array('title' => $mytitle, 'content' => $mycontent);
}



/*
 * blog statistics gadget
 */

function mydash_create_blog_statistics($args = false) {
	// do nothing
}

function mydash_edit_blog_statistics($args = false) {
	return "No editable options.";
}

function mydash_display_blog_statistics($args = false) {
	global $wpdb;
	
	$mytitle = __('Blog Stats');
	
	$numposts = (int) $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->posts WHERE post_type = 'post' AND post_status = 'publish'");
	$numcomms = (int) $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->comments WHERE comment_approved = '1'");
	$numcats  = (int) $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->categories");
	
	$post_str = sprintf(__ngettext('%1$s <a href="%2$s" title="Posts">post</a>', '%1$s <a href="%2$s" title="Posts">posts</a>', $numposts), number_format($numposts), 'edit.php');
	$comm_str = sprintf(__ngettext('%1$s <a href="%2$s" title="Comments">comment</a>', '%1$s <a href="%2$s" title="Comments">comments</a>', $numcomms), number_format($numcomms), 'edit-comments.php');
	$cat_str  = sprintf(__ngettext('%1$s <a href="%2$s" title="Categories">category</a>', '%1$s <a href="%2$s" title="Categories">categories</a>', $numcats), number_format($numcats), 'categories.php');
	
	$mycontent = '<p>' . sprintf(__('There are currently %1$s and %2$s, contained within %3$s.'), $post_str, $comm_str, $cat_str) . '</p>';

	return array('title' => $mytitle, 'content' => $mycontent);
}

/*
 * Askimet statistics gadget
 */
 
 function mydash_create_akismet_gadget($args=false) {
 	// do nothing
 }
 
 function mydash_display_akismet_gadget($args = false) {
 	
 	$akismet_api = get_option('wordpress_api_key');
 	$mytitle = "Spam";
 	if(empty($akismet_api)) {
 		// askimet not installed.
 		$mycontent = '<p><a href="http://akismet.com/">Akismet</a> is not setup for this blog - please visit <a href="http://akismet.com/">here</a> for more details</p>';
 	} else {
 		$count = get_option('akismet_spam_count');
 		global $submenu;
		if ( isset( $submenu['edit-comments.php'] ) )
			$link = 'edit-comments.php';
		else
			$link = 'edit.php';
		$mycontent = '<p>'.sprintf(__('<a href="%1$s">Akismet</a> has protected your site from <a href="%2$s">%3$s spam comments</a>.'), 'http://akismet.com/', "$link?page=akismet-admin", number_format($count) ).'</p>';
 	}
 	
 	return array('title' => $mytitle, 'content' => $mycontent);
}
 
 function mydash_edit_akismet_gadget($args = false) {
 	return "No editable options.";
 }

/*
 * Scheduled entries gadget
 */
 
function mydash_create_scheduled_gadget($args = false) {
	// Do nothing
}
 
function mydash_edit_scheduled_gadget($args = false) {
	return "No editable options.";
}

function mydash_display_scheduled_gadget($args = false) {
	global $wpdb;
	
	$mytitle = __('Scheduled Entries:');
	
	if ( $scheduled = $wpdb->get_results("SELECT ID, post_title, post_date_gmt FROM $wpdb->posts WHERE post_type = 'post' AND post_status = 'future' ORDER BY post_date ASC") ) {
		$mycontent = '<ul>';
		foreach ($scheduled as $post) {
		if ($post->post_title == '')
			$post->post_title = sprintf(__('Post #%s'), $post->ID);
			$mycontent .= "<li>" . sprintf(__('%1$s in %2$s'), "<a href='post.php?action=edit&amp;post=$post->ID' title='" . __('Edit this post') . "'>$post->post_title</a>", human_time_diff( current_time('timestamp', 1), strtotime($post->post_date_gmt. ' GMT') ))  . "</li>";
		}
		$mycontent .= '</ul>';
	} else {
		$mycontent = 'You do not have any scheduled entries.';
	}
	return array('title' => $mytitle, 'content' => $mycontent);
}

/*
 * Fake box gadgets
 */

function mydash_create_fake_gadget($args = false) {
	// This is where you would setup defaults
	// and variable storage for your gadget
	
	// In this case I will do nothing
}

function mydash_display_fake_gadget($args = false) {
	
	$mytitle = "<a href=''>Fake box</a>";
	$mycontent = "This is a fake gadget box. So there!!!";	

	return array('title' => $mytitle, 'content' => $mycontent);
}

function mydash_edit_fake_gadget($args = false) {
	return "No editable options.";
}


/*
 * Register the default gadgets
 */

function mydash_register_defaults() {
	// Register the standard gadgets
	
	$site_uri = get_settings('siteurl');
	$base_uri = $site_uri . '/wp-content/plugins/mydashboard/';
	
	// Comments gadget
	$commentsoptions = array(	'id' => 'mydash_latest_comments',
								'title' => 'Comments',
								'createcallback' => 'mydash_create_latest_comments_gadget',
								'editcallback' => 'mydash_edit_latest_comments',
								'contentcallback' => 'mydash_display_latest_comments',
								'allowmultiple' => false,
								'fulltitle' => 'Wordpress latest comments',
								'description' => 'Standard latest comments dashboard gadget',
								'authorlink' => 'http://www.clearskys.net'
								);
	register_mydashboard_gadget('mydash_latest_comments', $commentsoptions);
	
	
	// Latest posts gadget
	$postsoptions = array(	'id' => 'mydash_latest_posts',
								'title' => 'Posts',
								'createcallback' => 'mydash_create_posts_gadget',
								'editcallback' => 'mydash_edit_posts',
								'contentcallback' => 'mydash_display_posts',
								'allowmultiple' => false,
								'fulltitle' => 'Wordpress latest posts',
								'description' => 'Standard latest posts dashboard gadget',
								'authorlink' => 'http://www.clearskys.net'
								);
	register_mydashboard_gadget('mydash_latest_posts', $postsoptions);
	
	// Blog statistics gadget
	$statsoptions = array(	'id' => 'mydash_blog_statistics',
								'title' => 'Blog Stats',
								'createcallback' => 'mydash_create_blog_statistics',
								'editcallback' => 'mydash_edit_blog_statistics',
								'contentcallback' => 'mydash_display_blog_statistics',
								'allowmultiple' => false,
								'fulltitle' => 'Wordpress blog statistics',
								'description' => 'Standard blog statistics dashboard gadget',
								'authorlink' => 'http://www.clearskys.net'
								);
	register_mydashboard_gadget('mydash_blog_statistics', $statsoptions);
	
	// Incoming links RSS feed gadget
	$statsoptions = array(	'id' => 'mydash_incoming_links',
								'title' => 'RSS Feed',
								'createcallback' => 'mydash_create_incoming_links_feed',
								'editcallback' => 'mydash_edit_lockedfeed_gadget',
								'contentcallback' => 'mydash_display_feed_gadget',
								'allowmultiple' => false,
								'fulltitle' => 'Wordpress Incoming links Feed',
								'description' => 'Standard blog incoming links dashboard gadget',
								'icon' => $base_uri . 'images/technorati_32x32.png',
								'authorlink' => 'http://www.clearskys.net'
								);
	register_mydashboard_gadget('mydash_incoming_links', $statsoptions);
	
	// WordPress development blog RSS feed gadget
	$devoptions = array(	'id' => 'mydash_dev_rss_feed',
								'title' => 'RSS Feed',
								'createcallback' => 'mydash_create_dev_rss_feed',
								'editcallback' => 'mydash_edit_lockedfeed_gadget',
								'contentcallback' => 'mydash_display_feed_gadget',
								'allowmultiple' => false,
								'fulltitle' => 'WordPress development RSS Feed',
								'description' => 'Standard development blog dashboard gadget',
								'icon' => $base_uri . 'images/feeds_32x32.png',
								'authorlink' => 'http://www.clearskys.net'
								);
	register_mydashboard_gadget('mydash_dev_rss_feed', $devoptions);
	
	// WordPress planet blog RSS feed gadget
	$devoptions = array(	'id' => 'mydash_planet_rss_feed',
								'title' => 'RSS Feed',
								'createcallback' => 'mydash_create_planet_rss_feed',
								'editcallback' => 'mydash_edit_lockedfeed_gadget',
								'contentcallback' => 'mydash_display_feed_gadget',
								'allowmultiple' => false,
								'fulltitle' => 'Other WordPress news RSS Feed',
								'description' => 'Standard WordPress news blog dashboard gadget',
								'icon' => $base_uri . 'images/feeds_32x32.png',
								'authorlink' => 'http://www.clearskys.net'
								);
	register_mydashboard_gadget('mydash_planet_rss_feed', $devoptions);
	
	// Quick links gadget
	$quickoptions = array(	'id' => 'mydash_quick_links',
								'title' => 'Quick Links',
								'createcallback' => 'mydash_create_quick_links',
								'editcallback' => 'mydash_edit_quick_links',
								'contentcallback' => 'mydash_display_quick_links',
								'allowmultiple' => false,
								'fulltitle' => 'Quick Links',
								'description' => 'Standard WordPress quick links dashboard gadget',
								'authorlink' => 'http://www.clearskys.net'
								);
	register_mydashboard_gadget('mydash_quick_links', $quickoptions);
	
	// Akismet stats Gadget
	$akismetoptions = array(	'id' => 'mydash_akismet',
							'title' => 'Spam',
							'createcallback' => 'mydash_create_akismet_gadget',
							'editcallback' => 'mydash_edit_akismet_gadget',
							'contentcallback' => 'mydash_display_akismet_gadget',
							'allowmultiple' => false,
							'fulltitle' => 'Akismet spam statistics',
							'description' => 'Display the number of spam messages caught by Akismet',
							'authorlink' => 'http://www.akismet.com'
							);
	register_mydashboard_gadget('mydash_akismet', $akismetoptions);
	
	// Scheduled entires gadget
	$schedsoptions = array(	'id' => 'mydash_scheduled_entries',
								'title' => 'Scheduled entries',
								'createcallback' => 'mydash_create_scheduled_gadget',
								'editcallback' => 'mydash_edit_scheduled_gadget',
								'contentcallback' => 'mydash_display_scheduled_gadget',
								'allowmultiple' => false,
								'fulltitle' => 'Wordpress scheduled posts',
								'description' => 'Scheduled posts list gadget',
								'authorlink' => 'http://www.clearskys.net'
								);
	register_mydashboard_gadget('mydash_scheduled_entries', $schedsoptions);
	
	// Generic RSS Feed gadget
	$rssoptions = array(	'id' => 'mydash_rss_feed',
							'title' => 'RSS Feed',
							'createcallback' => 'mydash_create_blank_feed_gadget',
							'editcallback' => 'mydash_edit_feed_gadget',
							'contentcallback' => 'mydash_display_feed_gadget',
							'allowmultiple' => true,
							'fulltitle' => 'RSS Feed',
							'description' => 'A generic RSS Feed reader for custom feeds.',
							'authorlink' => 'http://www.clearskys.net',
							'icon' => $base_uri . 'images/feeds_32x32.png'
							);
	register_mydashboard_gadget('mydash_rss_feed', $rssoptions);
	
	// Fake gadget - for testing purposes
	$fakeoptions = array(	'id' => 'mydash_fake',
							'title' => 'Fake box',
							'createcallback' => 'mydash_create_fake_gadget',
							'editcallback' => 'mydash_edit_fake_gadget',
							'contentcallback' => 'mydash_display_fake_gadget',
							'allowmultiple' => true,
							'fulltitle' => 'Fake Box',
							'description' => 'For testing purposes'
							);
	//register_mydashboard_gadget('mydash_fake', $fakeoptions);
	
}

?>