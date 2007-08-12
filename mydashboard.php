<?php
/*
Plugin Name: MyDashboard
Plugin URI: http://dev.clearskys.net/Wordpress/MyDashboard
Description: This plugin provides a replacement ajax based Dashboard for WordPress.
Version: 0.2.4
Author: clearskys.net
Author URI: http://blog.clearskys.net
*/
/*  Copyright 2007 clearskys.net Ltd  (email : team@clearskys.net)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License version 2 as published by
    the Free Software Foundation .

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/


// include the default gadgets
include_once('gadgets/default.php');

define('MYDPLUGINPATH', (DIRECTORY_SEPARATOR != '/') ? str_replace(DIRECTORY_SEPARATOR, '/', dirname(__FILE__)) : dirname(__FILE__));

global $CSmydash;

class CSMyDashboard {
	
	var $build = '5';
	var $localqueue = array();
	var $base_uri = '';
	var $mylocation = '';
	
	var $availablegadgets = array();
	var $gadgetinstances = array();
	
	var $pages = array();
	var $columns = array();
	
	function CSMyDashboard() {
		// grab the configuration settings
		$md = get_option("clearskys_dashboard_config");
		$site_uri = get_settings('siteurl');
		
		// Add the installation and uninstallation hooks 
		
		$directories = explode(DIRECTORY_SEPARATOR,MYDPLUGINPATH);
		$mydir = $directories[count($directories)-1];
		$this->mylocation = $mydir . DIRECTORY_SEPARATOR . basename(__FILE__); 
		
		$this->base_uri = $site_uri . '/wp-content/plugins/' . $mydir . '/';
		
		register_activation_hook(__FILE__, array(&$this, 'install')); 
		register_deactivation_hook(__FILE__, array(&$this, 'uninstall'));
		
		add_action('init', array(&$this,'gadgets_init'), 1);
		add_action('init',array(&$this,'enqueue_dependancies'));
		add_action('init',array(&$this,'handle_ajax'));
		
		add_action('admin_menu', array(&$this,"add_admin_pages"));
		add_action('admin_head',array(&$this,'show_header'));
		
		if($md['show_original'] == '0') {
			add_action('init',array(&$this,'redirect_index'));
			add_action('admin_head',array(&$this,'steal_dashboardmenu'));
		}
		
	}
	
	function install() {
		
		$md = get_option("clearskys_dashboard_config");
		if(!isset($md['build']) || $md['build'] < $this->build) {
			// The options aren't set so set up the defaults here
			$md['build'] = $this->build;
			$md['show_original'] = 0;
			
			$md['layout']['columns'] = 3;
			$md['layout']['style'] = 'even';
			
			$md['skin'] = "default";
			
			$md['defaultpage'] = "page-1";
			
		}
		update_option("clearskys_dashboard_config",$md);
	}
	
	function uninstall() {
		
	}
	
	function handle_ajax() {
		// This function receives all ajax calls for the gadgets, and passes them off
		// to the relevant user functions
		if($this->onpage('index.php') && $_GET['call'] == '_ajax' && function_exists('current_user_can') && current_user_can('edit_posts')) {
			
			$mdp = get_option("clearskys_dashboard_pages");
			$this->load_stored_gadgets();
			
			
			switch($_GET['action']) {
				case "updatecontent":
					//echo $this->get_gadget_content($_GET['name']);
					echo $this->show_gadget_box($_GET['name']);
					break;
				case "updatetitle":
					//echo $this->get_gadget_title($_GET['name']);
					break;
				case "sendedit";
					$sent = $_POST['update'];
					if($sent != '') {
						echo $this->show_gadget_box($sent);
					}
					break;
				case "reordercolumn":
					$tcolumn = $_GET['column'];
					if(isset($mdp['page-1'][$tcolumn]) && count($mdp['page-1'][$tcolumn]) > 0) {
						// make sure that the column actually exists and it has
						// gadgets on it then
						// create an array of gadget names in the order we want them
						$gadgets = explode('&',$_GET['gadgets']);
						for($n=0; $n < count($gadgets); $n++) {
							$gadgets[$n] = str_replace($tcolumn . '[]=', '', $gadgets[$n]);
						}
						$newpagelayout = array();
						foreach($gadgets as $gad) {
							foreach($mdp['page-1'][$tcolumn] as $ogadget) {
								if($ogadget['name'] == $gad) {
									array_push($newpagelayout, $ogadget);
									break;
								}		
							}
						}
						// Store the changes back to the database
						$mdp['page-1'][$tcolumn] = $newpagelayout;
						update_option("clearskys_dashboard_pages",$mdp);
						echo "ok";
					} else {
						echo "no column";
					}
					break;
				case "movegadget":
					$tcolumn = $_GET['tocolumn'];
					$fcolumn = $_GET['fromcolumn'];
					if(isset($mdp['page-1'][$fcolumn]) && isset($mdp['page-1'][$tcolumn]) && (count($mdp['page-1'][$fcolumn]) > 0 || count($mdp['page-1'][$tcolumn]) > 0)) {
						// make sure that the columns exist and that there is actually something to move from one
						// column to the other.	
						// Handle the to column first
						$gadgets = explode('&',$_GET['togadgets']);
						for($n=0; $n < count($gadgets); $n++) {
							$gadgets[$n] = str_replace($tcolumn . '[]=', '', $gadgets[$n]);
						}
						$newpagelayout = array();
						if(count($gadgets) > 0 && count($this->gadgetinstances) > 0) {
							foreach($gadgets as $gad) {
								foreach($this->gadgetinstances as $gi) {
									if($gi['name'] == $gad) {
										array_push($newpagelayout, $gi);
									}
								}
							}
							$mdp['page-1'][$tcolumn] = $newpagelayout;
						}
						// Now handle the from column first
						$gadgets = explode('&',$_GET['fromgadgets']);
						for($n=0; $n < count($gadgets); $n++) {
							$gadgets[$n] = str_replace($fcolumn . '[]=', '', $gadgets[$n]);
						}
						$newpagelayout = array();
						if(count($gadgets) > 0 && count($this->gadgetinstances) > 0) {
							foreach($gadgets as $gad) {
								foreach($this->gadgetinstances as $gi) {
									if($gi['name'] == $gad) {
										array_push($newpagelayout, $gi);
									}
								}
							}
							$mdp['page-1'][$fcolumn] = $newpagelayout;
						}
						update_option("clearskys_dashboard_pages",$mdp);
						echo "ok";
					} else {
						echo "nothing to move";
					}
					break;
				case "removegadget":
					$gadget = $_GET['gadget'];
					if($gadget != "" && $this->gadget_active($gadget)) {
						// Check a gadget is passed and that it is active
						// remove the gadget from the gadget instances
						unset($this->gadgetinstances[$gadget]);
						foreach($mdp['page-1'] as $key=>$col) {
							// Go through every column
							// get the keys for the column
							// need to do this as removing the first item in an array
							// causes things to mess up a wee bit otherwise
							$akeys = array_keys($col);
							for($n = 0; $n < count($akeys); $n++) {
								// go through each gadget in the column
								if($col[$akeys[$n]]['name'] == $gadget) {
									// if the gadget is found
									unset($mdp['page-1'][$key][$akeys[$n]]);
								}
							}
						}
						update_option("clearskys_dashboard_pages",$mdp);
						echo "ok";
					} else {
						echo "no gadget to remove";
					}
					break;
				case "addgadget":
					if(isset($mdp['page-1'])) {
						// Get the Left hand column
						$toadd = $_GET['gadget'];
						if($toadd != "") {
							$toadd = str_replace('addtopage_','',$toadd);
						}
						$column =& $this->get_left_column($mdp['page-1']);
						$gadget = $this->create_gadget_instance($toadd);
						if(isset($gadget['name'])) {
							array_push($column, $gadget);
							update_option("clearskys_dashboard_pages",$mdp);
							//echo $gadget['name'];
							echo $this->show_gadget_box($gadget['name']);
						}
					}
					break;
				case "loadlibrary":
					echo $this->show_library();
					break;
			}
			// Ensure that the code stops here, otherwise the Wordpress headers are returned as well
			exit();
		}
		
	}
	
	
	function call_edit_gadget($name) {
		// This function will call the update functionality on the
		// gadgets edit callback ready for the gadget to be re-displayed
		if($this->gadget_active($name)) {
		 	// get the gadget parent (class) details
		 	$gadgetparent = $this->gadgetinstances[$name]['parent'];
		 	if($this->gadget_registered($gadgetparent)) {
		 		// The parent is registered so we can access the relevant functions
		 		$iam = array(	'name' => $name);
		 		$gp = $this->availablegadgets[$gadgetparent];
		 		if(is_callable($gp['editcallback'])) {
				 	$editresults = call_user_func_array($gp['editcallback'],array( 'args' => $iam));
				 } else {
				 	$editresults = "<p>There are no options available</p>";
				 }
		 		
		 		if(is_callable($gp['contentcallback'])) {
				 	$contentresults = call_user_func_array($gp['contentcallback'],array( 'args' => $iam));
				 	if(!is_array($contentresults)) {
				 		$content = $contentresults;
				 		$contentresults = array(	'title' => $gp['title'],
				 									'content' => $content
				 								);				 	
				 		}
				 } else {
				 	$contentresults = array( 	'title' => $gp['title'],
				 								'content' => '<p>There was a problem accessing this gadget</p>');
				 }
		 		// we have the template, now create and fill in the box
		 		
		 		$this->draw_box($name, $editresults, $contentresults);	
		 	} 
		 }
		
		
	}
	
	function reorder_gadgets($column, $gadgets) {
		
	}
	
	function load_stored_gadgets() {
		/*
		 * This function will load the stored gadgets
		 * It is primarily used for ajax calls
		 * 
		 */
		$mdp = get_option("clearskys_dashboard_pages");
		
		if(count($mdp['page-1']) > 0) {
			// columns exist so output them
			foreach($mdp['page-1'] as $key=>$column) {
				if(count($column) > 0) {
					// there are gadgets in this box
					foreach($column as $gadget) {
						// check if the gadget is active
						// and if it isn't active then load it
						if(!$this->gadget_active($gadget['name'])) {
							$this->load_gadget_instance($gadget);
						}
					}
				}
			}
		}
	}
	
	function enqueue_dependancies() {
		
		$md = get_option("clearskys_dashboard_config");
		
		if(($this->onpage('wp-admin/index.php') || $this->onpage('wp-admin/admin.php')) && $_GET['page'] == $this->mylocation) {
			
			if(get_bloginfo('version') >= "2.2") {
			// jquery native for versions from 2.2 and onwards so enqueue the jquery using standard wordpress calls
				wp_enqueue_script('interface');
			} else {
				// jquery not standard on this version, so use bundled plugin libraries
				wp_enqueue_script('jquery', $this->base_uri . 'js/library/jquery-latest.pack.js', array(), '1.1.3');
				wp_enqueue_script('interface', $this->base_uri . 'js/library/interface.js', array('jquery'), '1.1.2');
			}	
			//jquery.history_remote.pack.js
			wp_enqueue_script('mydashplugins', $this->base_uri . 'js/library/jquery.mydash.js', array(), $this->build);
			wp_enqueue_script('mydashboard', $this->base_uri . 'js/mydashboard.js', array(), $this->build);
			
		
			$this->queueCss('mydashboard.css');
			if(isset($md['skin'])) {
				$this->queueCss('skins/' . $md['skin'] . '/skin.css');	
			} else {
				$this->queueCss('skins/default/skin.css');	
			}
		}
		
	}
	
	function show_header() {
		
		if(count($this->localqueue) > 0) {
			foreach($this->localqueue as $key=>$value) {
				echo $value . "\n";
			}
		}
		//print_r($this->availablegadgets);
	}
	
	function add_admin_pages() {
		add_submenu_page('index.php', 'My Dashboard page', 'My Dashboard', 'read', __FILE__, array(&$this,'show_dashboard'));
		add_submenu_page('themes.php', 'My Dashboard settings', 'My Dashboard', 8, __FILE__, array(&$this,'show_dashboard_layout'));
	}
	
	function redirect_index() {
		if($this->onpage('wp-admin/index.php') && $_GET['page'] == '' && $_GET['call'] != '_ajax') {
			// If the call is for the standard index page and isn't an ajax call
			Header('Location: index.php?page=' . $this->mylocation);
		}
	}
	
	function steal_dashboardmenu() {
		global $menu, $submenu;
				
		if(array_key_exists('index.php',$submenu)) {	
			
			// This is all a bit hacky, but we basically perform the following steps
			
			// Check if there is a submenu of index - there should be as we added a sub menu item to it
			if(isset($submenu['index.php'])) {
				$maindash = -1;
				for($n=0; $n < count($submenu['index.php']); $n++) {
					// For each submenu item
					if($submenu['index.php'][$n][0] == "Dashboard") {
						// Oooh found the main Dashboard, so hijack it to be the new Dashboard
						$submenu['index.php'][$n][0] = "My Dashboard";
						$submenu['index.php'][$n][2] = $this->mylocation;
						$maindash = $n;
					}
					if($submenu['index.php'][$n][0] == "My Dashboard" && $maindash != $n) {
						// Found our added submenu (that isn't the one we hijacked earlier)
						// We no longer need it, so remove it.
						unset($submenu['index.php'][$n]);
					}
				}
				
			}
			// Finally after all our messing around, count the number of sub-menus
			// If there is only one, and it is ours, then we don't really need it, so remove it.
			if(count($submenu['index.php']) == 1 && $submenu['index.php'][0][0] == "My Dashboard") {
				unset($submenu['index.php']);
			}
		}

	}
	
	function show_dashboard() {
		$md = get_option("clearskys_dashboard_config");	
		
		// Need to check if the initial page exists, otherwise
		// this is our first time here, so we need to setup some
		// default gadgets.
		
		$mdp = get_option("clearskys_dashboard_pages");
		if(!isset($mdp['page-1'])) {
			// No initial page is built so create a default one
			$mdp[$md['defaultpage']] = $this->build_default_page($md['layout']['columns'], $md['layout']['style']);
			update_option("clearskys_dashboard_pages",$mdp);
		}
		//print_r($mdp);
		$layout = $md['layout'];
		
		switch($layout['columns']) {
			case 1:
				$style = "singleColumn";
				break;
			case 2:
				switch($layout['style']) {
					case 'even':
						$styleleft = "doubleColumn";
						$styleright = "doubleColumn";
						break;
					case 'left':
						$styleleft = "twothirdsColumn";
						$styleright = "onethirdColumn";
						break;
					case 'right':
						$styleleft = "onethirdColumn";
						$styleright = "twothirdsColumn";
						break;
				}
				break;
			case 3:
				$style = "tripleColumn";
				break;
		}
		
		echo '<div id="mydashcontainer">';
		echo $this->topbar();
		
		echo '<div id="mydashlibrary">';
		echo 'Loading library...';
		echo '</div>';
		
		echo '<div id="' . $md['defaultpage'] . '" class="mydashpage">';
		
		// change code here to read the page from the page array
		
		if(count($mdp['page-1']) > 0) {
			// columns exist so output them
			foreach($mdp['page-1'] as $key=>$column) {
				
				echo '<div class="';	
				if(isset($style)) {
					echo $style;
				} else {
					if($key = 'column-1') {
						echo $styleleft;
					} else {
						echo $styleright;
					} 
				}
				echo ' droppable"';
				echo ' id="' . $key . '">';
				if(count($column) > 0) {
					// there are gadgets in this box
					foreach($column as $gadget) {
						// check if the gadget is active
						// and if it isn't active then load it
						if($this->gadget_active($gadget['name']) || $this->load_gadget_instance($gadget)) {
							$this->show_gadget_box($gadget['name']);
						}
					}
				}
				echo '</div>';
			}
			
		}
		
		echo '</div>';	
		echo '</div>';
		//echo "<div class='wrap'>";
		
		//echo "</div>";
	}
	
	function show_library() {
		if(count($this->availablegadgets) > 0) {
			// There are some available gadgets
			foreach($this->availablegadgets as $key => $gadget) {
				echo "<div class='librarygadget'>";
				echo "<div class='libraryimage'>";
				if(isset($gadget['icon'])) {
					echo "<img src='" . $gadget['icon'] . "' />";
				}
				echo "</div>";
				echo "<div class='librarycontent'>";
					echo "<h2>";
					if(isset($gadget['authorlink'])) {
						echo "<a href='" . $gadget['authorlink'] . "'>";
					}
					echo $gadget['fulltitle'];
					if(isset($gadget['authorlink'])) {
						echo "</a>";
					}
					echo "</h2>";
					echo $gadget['description'];
					echo "<br/>";
					if($this->can_add_instance($gadget['id'])) {
						echo "<a href='' class='addtopage' id='addtopage_" . $gadget['id'] . "'>Add to page</a>";
					} else {
						echo "";
					}
					
					echo "</div>";
					
					
					
					
				echo "</div>";
			}
			echo "<div style='clear: both;'></div>";
		}
	}
	
	function topbar() {
		// Will add pages link code here in the fullness of time
		// for the moment, here are the administration links
		$html = "";
		$html .= '<div class="topbar">';
		
		$html .= '<div class="loadingbox"></div>';
		
		//administration links
		$html .= '<div class="adminlinks">';
		$html .= '<a href="" id="addgadgets">Add Gadgets</a>';
		$html .= '</div>';
		$html .= '<div style="clear: both;"></div>';
		$html .='</div>';
		
		return $html;
		
	}
	
	function build_default_page($columns = 3, $layout = 'even') {
		$t = array();
		
		for($n = 1; $n <= $columns; $n++) {
			$t['column-' . $n] = array();
		}
		
		// Get the Right hand column
		$column =& $this->get_right_column($t);
		
		if($this->gadget_registered('mydash_latest_comments')) {
			$gadget = $this->create_gadget_instance('mydash_latest_comments');
			array_push($column, $gadget);
		}
		if($this->gadget_registered('mydash_latest_posts')) {
			$gadget = $this->create_gadget_instance('mydash_latest_posts');
			array_push($column, $gadget);
		}
		if($this->gadget_registered('mydash_blog_statistics')) {
			$gadget = $this->create_gadget_instance('mydash_blog_statistics');
			array_push($column, $gadget);
		}
		if($this->gadget_registered('mydash_additional_items')) {
			$gadget = $this->create_gadget_instance('mydash_additional_items');
			array_push($column, $gadget);
		}
		
		// Get the Left hand column
		$column =& $this->get_left_column($t);
		
		if($this->gadget_registered('mydash_quick_links')) {
			$gadget = $this->create_gadget_instance('mydash_quick_links');
			array_push($column, $gadget);
		}
		
		// Get the Middle column
		$column =& $this->get_middle_column($t);
		
		if($this->gadget_registered('mydash_incoming_links')) {
			$gadget = $this->create_gadget_instance('mydash_incoming_links');
			array_push($column, $gadget);
		}
		if($this->gadget_registered('mydash_dev_rss_feed')) {
			// Wordpress Development blog
			$gadget = $this->create_gadget_instance('mydash_dev_rss_feed');
			array_push($column, $gadget);
		}
		if($this->gadget_registered('mydash_planet_rss_feed')) {
			// Other Wordpress news
			$gadget = $this->create_gadget_instance('mydash_planet_rss_feed');
			array_push($column, $gadget);
		}
		
		return $t;
	}
	
	function get_gadget_content($name) {
		/*
		 * This function will get the content for a gadget.
		 * It is usually called via ajax for updating content, such as feeds
		 */
		 if($this->gadget_active($name)) {
		 	// get the gadget parent (class) details
		 	$gadgetparent = $this->gadgetinstances[$name]['parent'];
		 	if($this->gadget_registered($gadgetparent)) {
		 		// The parent is registered so we can access the relevant functions
		 		$iam = array(	'name' => $name);
		 		$gp = $this->availablegadgets[$gadgetparent];
		 		if(is_callable($gp['contentcallback'])) {
				 	$contentresults = call_user_func_array($gp['contentcallback'],array( 'args' => $iam));
				 	if(!is_array($contentresults)) {
				 		$content = $contentresults;
				 		$contentresults = array(	'title' => $gp['title'],
				 									'content' => $content
				 								);				 	
				 		}
				 } else {
				 	$contentresults = array( 	'title' => $gp['title'],
				 								'content' => 'There was a problem accessing this gadget');
				 }
		 		// we have the template, now create and fill in the box
		 		return $contentresults['content'];	
		 	} else {
		 		return "This gadget isn't registered";
		 	}
		 } else {
	 		return "This gadget isn't active";
	 	}
		 
	}
	
	function show_gadget_box($name) {
		/*
		 * This function will call the gadgets functions in order to display the required function
		 */	
		 // first check that the gadget actually exists
		 if($this->gadget_active($name)) {
		 	// get the gadget parent (class) details
		 	$gadgetparent = $this->gadgetinstances[$name]['parent'];
		 	if($this->gadget_registered($gadgetparent)) {
		 		// The parent is registered so we can access the relevant functions
		 		$iam = array(	'name' => $name);
		 		$gp = $this->availablegadgets[$gadgetparent];
		 		if(is_callable($gp['editcallback'])) {
				 	$editresults = call_user_func_array($gp['editcallback'],array( 'args' => $iam));
				 } else {
				 	$editresults = "<p>There are no options available</p>";
				 }
		 		
		 		if(is_callable($gp['contentcallback'])) {
				 	$contentresults = call_user_func_array($gp['contentcallback'],array( 'args' => $iam));
				 	if(!is_array($contentresults)) {
				 		$content = $contentresults;
				 		$contentresults = array(	'title' => $gp['title'],
				 									'content' => $content
				 								);				 	
				 		}
				 } else {
				 	$contentresults = array( 	'title' => $gp['title'],
				 								'content' => '<p>There was a problem accessing this gadget</p>');
				 }
		 		// we have the template, now create and fill in the box
		 		
		 		$this->draw_box($name, $editresults, $contentresults);	
		 	} 
		 }
	}
	
	function draw_box($name, $editresults = "", $contentresults = array()) {
		echo "";
		echo '<div class="mybox" id="' . $name . '">';
		echo '<h2 class="mytitle">';
		
		// icons
		echo '<a href="" class="delbox" title="Remove this gadget from the dashboard"></a>';
		echo '<a href="" class="minbox" title="Shrink/expand this gadget"></a>';
		echo '<a href="" class="editbox" title="Edit the settings for this gadget"></a>';
		
		echo $contentresults['titleicon'];
		echo '<span class="mytitle_text">' . $contentresults['title'] . '</span>';
		
		echo '</h2>';
		
		echo '<div class="myboxedit">';
		echo '<form action="index.php" method="post" name="' . $name . '_form" class="myboxeditform">';
		echo $editresults;
		echo '</form>';
		echo '</div>';
		
		echo '<div class="myboxinner">';
		
		
		
		echo $contentresults['content'];
		echo '</div>';
		
		echo '<div style="clear: both;"></div>';
		echo '</div>';
		
	}
	
	function load_gadget_instance($gadget) {
		// loads a gadget instance from a page layout
		if(isset($gadget['parent'])  && $this->gadget_registered($gadget['parent'])) {
			// the parent is set and is a registered gadget
			if(isset($gadget['name']) && !$this->gadget_active($gadget['name'])) {
				// The name is set and the gadget isn't already active
				// don't need to really check for allowmultiples as that check should have
				// been done when the instance was first added to the column
				// add it to the active gadgets array
				$this->gadgetinstances[$gadget['name']] = $gadget;
				return true;
			} else {
				return false;
			}
		} else {
			return false;
		}
		
	}
	
	function create_gadget_instance($gadget) {
		if($this->gadget_registered($gadget)) {
			// gagdet is registered so continue
			if(!$this->gadget_active($gadget) || $this->availablegadgets[$gadget][allowmultiple] == true) {
				// gadget isn't already active or is a gadget that allows multiple instances
				// so create an instance
				$instance = array();
				$instance['parent'] = $gadget;
				
				if($this->availablegadgets[$gadget][allowmultiple] == false) {
					// this is a single instance gadget, so use the default name
					$name = $gadget;
				} else {
					// this is a multiple instance gadget, so we need to
					// get the next available gadget name
					$n = 1;
					while(isset($this->gadgetinstances[$gadget . '_' . $n])) {
						$n++;
					}
					$name = $gadget . '_' . $n;
				}
				
				
				$instance['name'] = $name;
				// call the creation function for the gadget
				//createcallback
				if(is_callable($this->availablegadgets[$gadget]['createcallback'])) {
				 	$createresults = call_user_func_array($this->availablegadgets[$gadget]['createcallback'],array( 'args' => $instance));
				}
				
				// add it to the active gadgets array
				$this->gadgetinstances[$name] = $instance;
				// and return it so it can be added to a column
				return $instance;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}
	
	function can_add_instance($gadget) {
		
		if($this->gadget_registered($gadget)) {
			// gagdet is registered so continue
			if(!$this->gadget_active($gadget) || $this->availablegadgets[$gadget][allowmultiple] == true) {
				return true;
			} else {
				return false;
			}
		} else {
			return false;
		}
		
	}
	
	function gadget_registered($name) {
		if(isset($this->availablegadgets[$name])) {
			return true;
		} else {
			return false;
		}
	}
	
	function gadget_active($name) {
		if(isset($this->gadgetinstances[$name])) {
			// single instance gadget exists, return true
			return true;
		} else {
			// gadget might be a multiple instance gadget so find keys starting with $name
			$foundgadget = false;
			$keys = array_keys($this->gadgetinstances);
			if(count($keys) > 0) {
				// check if there are active gadgets
				foreach($keys as $key) {
					if(strpos($key,$name) === 0) {
						// name exists at the beginning of the string 
						$foundgadget = true;
						break;
					}
				}
			}
			
			return $foundgadget;
		}
	}
	
	function column_exists() {
		
	}
	
	function &get_left_column(&$page) {
		// this function will attempt to return the left column, but in a single column layout
		// it will return the single column instead.	
		if(isset($page['column-1'])) {
			return $page['column-1'];
			//return 'column-1';
		}
	}
	
	function &get_middle_column(&$page, $defaultto = 'left') {
		// this function will attempt to return the middle column, but in a two column layout
		// will return the left column instead (can be altered by passing 'right' as a parameter)	
		if(isset($page['column-2'])) {
			return $page['column-2'];
			//return 'column-2';
		} else {
			if($defaultto == 'left') {
				return $this->get_left_column($page);
			} else {
				return $this->get_right_column($page);
			}
		}
	}
	
	function &get_right_column(&$page) {
		// this function will attempt to return the right column, but in a single column layout
		// it will return the single column instead.
		if(isset($page['column-3'])) {
			return $page['column-3'];
			//return 'column-3';
		} else {
			// there isn't a 3rd column, so return the middle column (2) ensuring that
			// it defaults to returning the left column on single column pages so we don't get
			// a recursion problem.
			return $this->get_middle_column($page, 'left');
		}
		
	}
	
	function get_skin_data($skin) {
		// Get skin meta data function - based on the get_theme_data function
		// from WordPress
		
		$skin_data = implode( '', file($skin) );
		$skin_data = str_replace ( '\r', '\n', $skin_data );
		
		 
		preg_match( '|Skin Name:(.*)|i', $skin_data, $skin_name );
		preg_match( '|Skin URI:(.*)|i', $skin_data, $skin_uri );
		preg_match( '|Description:(.*)|i', $skin_data, $description );
		preg_match( '|Author:(.*)|i', $skin_data, $author_name );
		preg_match( '|Author URI:(.*)|i', $skin_data, $author_uri );
		if ( preg_match( '|Version:(.*)|i', $skin_data, $version ) )
			$version = trim( $version[1] );
		else
			$version ='';
	
		$description = wptexturize( trim( $description[1] ) );
	
		$name = $skin_name[1];
		$name = trim( $name );
		$skin = $name;
		$skin_uri = trim( $skin_uri[1] );
	
		if ( '' == $author_uri[1] ) {
			$author = trim( $author_name[1] );
		} else {
			$author = '<a href="' . trim( $author_uri[1] ) . '" title="' . __('Visit author homepage') . '">' . trim( $author_name[1] ) . '</a>';
		}
	
		return array( 'Name' => $name, 'Title' => $skin, 'URI' => $skin_uri, 'Description' => $description, 'Author' => $author, 'Version' => $version);
	}
	
	function get_skins($skin_root) {
		
		// Get all the skins within the Styles/skins directory.
		// each skin should have it's own sub directory within the skins directory
		// and should have a file called skin.css as the master style sheet.
		// based on the get_themes function from WordPress
		$skins = array();
		
		$md = get_option("clearskys_dashboard_config");
		
		//$skin_root = ABSPATH . 'wp-content/plugins/mydashboard/styles/skins/';
		// find all the files in the skins directory
		$skin_dir = @ dir($skin_root);
	
		if(!$skin_dir) 
			return $skins;
			
		while ( ($skin_t = $skin_dir->read()) !== false ) {
			if ( is_dir($skin_root . '/' . $skin_t) && is_readable($skin_root . '/' . $skin_t) ) {
				if ( $skin_t{0} == '.' || $skin_t == '..' || $skin_t == 'CVS'  || $skin_t == '.svn' )
					continue;
					
				$stylish_dir = @ dir($skin_root . '/' . $skin_t);
				$found_stylesheet = false;
				while ( ($skin_file = $stylish_dir->read()) !== false ) {
					if ( $skin_file == 'skin.css' ) {
						$skins[] = $skin_t;
						$found_stylesheet = true;
						break;
					}
				}
				print_r($skins);
			}
		}
		return $skins;
	}
	
	function show_dashboard_layout() {
		// Control the display and updates of the dashboard options panel
		if ( ! current_user_can('manage_options') )
			wp_die(__('You are not allowed to modify dashboard options.'));
		
		$md = get_option("clearskys_dashboard_config");
		$site_uri = get_settings('siteurl');
		
		//print_r($md);
		if($_POST['submitted'] == 'yes') {
			if($_POST['show_original'] != $md['show_original']) {
				$md['show_original'] = $_POST['show_original'];
			}
			if($_POST['skin'] != $md['skin']) {
				$md['skin'] = $_POST['skin'];
			}
			update_option("clearskys_dashboard_config",$md);
			echo '<div id="message" class="updated fade"><p><strong>Settings updated.</strong></p></div>';
		}
		
		echo "<div class='wrap'>";
		echo "<h2>My Dashboard settings</h2>";
		?>
		<form method="post" id="mydashform">	
		
			<p class="submit"><input type="submit" name="Submit" value="Update Settings &raquo;" />
		
			<fieldset class="options">
			<legend>Dashboard Main Settings</legend>
			<p>The main settings below can be used to customise how your Dashboard will look and operate.</p>
			<p></p>
			<table width="100%" cellspacing="2" cellpadding="5" class="editform"> 
			<tr valign="top"> 
			<th width="33%" scope="row" valign='top'>Display standard dashboard as well</th> 
			<td valign='top'>
				<select name="show_original">
					<option value="0" <?php if($md['show_original'] == 0) echo 'selected="selected"'; ?>>No</option>
					<option value="1" <?php if($md['show_original'] == 1) echo 'selected="selected"'; ?>>Yes</option>
				</select>
			<?php //echo htmlspecialchars($cse["enquiry_thankyou"], ENT_QUOTES); ?>
			</td> 
			</tr>
			<tr valign="top"> 
			<th width="33%" scope="row">Select Dashboard Skin</th> 
			<td>
				<select name="skin">
				<?php 
					$skin_root = ABSPATH . 'wp-content/plugins/mydashboard/styles/skins/';
					$skins = $this->get_skins($skin_root);
					if(count($skins) > 0) {
						for($n = 0; $n < count($skins); $n++ ) {
							$skinfile = $skin_root . $skins[$n] . '/skin.css';
							$tskin = $this->get_skin_data($skinfile);
							echo '<option value="' . $skins[$n] . '"';
							if($md['skin'] == $skins[$n]) echo ' selected="selected"';
							echo '>';
							echo $tskin['Name'];
							echo '</option>';
						}
						
					} 
				?>
				</select>
			</td> 
			</tr>
			
			</table>
			</fieldset>
			
			<p class="submit"><input type="submit" name="Submit" value="Update Settings &raquo;" />
			<input type='hidden' name='onform' id='onform' value=''>
			<input type="hidden" name="submitted" value="yes" />
			</form>
		
		<?
		echo "</div>";
	}
	
	function onpage($page) {
		$path = $_SERVER["SCRIPT_FILENAME"];
		
		if((stristr($path,$page) || stristr($path,$page."?")) && (stristr($path,get_option('upload_path')) === False)) {
			return True;
		} else {
			return False;
		}
	}
	
	function queueCss($css) {
		if($css != "") {
			$plugin_css_uri = $this->base_uri . 'styles/';
			$cssstring = "<link rel='stylesheet' type='text/css' href='" . $plugin_css_uri . $css . "' />";
			$this->localqueue[$css] = $cssstring;
		}
	}
	
	function register_gagdet($name, $options = array(), $type= 'standard') {
		/*
		 * Registers a gadget as available to be added to the page
		 * NOTE: This does not add a gadget to the page, the user needs to do that manually.
		 * 
		 * Available Options:
		 * id: the main id for the added box - this may end being different if you allow multiple boxes
		 * title: the title for the box
		 * link: a link for the box title
		 * createcallback: a function of array for class/function that displays and handles the setup of the gadget
		 * editcallback: a function or array for a class/function that displays and handles the edit form
		 * contentcallback: a function or array for a class/function that displays and handles the content
		 * allowmultiple: if true, then the user can add multiple instances of your gadget.
		 * 
		 * The following are for use in the gadget library
		 * 
		 * icon: the url of a graphic file for the icon, should be 32px by 32px (optional)
		 * fulltitle: a full title of the gadget
		 * description: a short description of the gadget
		 * authorlink: a link to the gadget website (optional)
		 * authoremail: an email address (optional)
		 * 
		 * Important, the id you pass in when you register the gadget is not necessarily the id the gadget will
		 * have when it is loaded on a dashboard page. If you have allowed multiple gadgets to be created
		 * then the id will be appended with a unique identifier so your callback handles should be able to handle this.
		 * 
		 * 
		 */
		 
		 // set up some defaults in case they aren't passed through
		 if(!isset($options['allowmultiple'])) {
		 	$options['allowmultiple'] = false;
		 }
		 
		 $this->availablegadgets[$name] = $options;
		 
		 //print_r($this->availablegadgets);
		 
	}
	
	function register_column($name, $page = "page-1") {
		
	}
	
	function register_page($name) {
		
	}
	
	function gadgets_init() {
		
		if(function_exists('mydash_register_defaults')) {
			mydash_register_defaults();
		}
		
		do_action('mydashboard_gadgets_init');
	}
	
	function columns_init() {
		
	}
	
}

function register_mydashboard_gadget($name, $options = array(), $type = 'standard') {
	global $CSmydash;

	$CSmydash->register_gagdet($name, $options, $type);
}

function register_mydashboard_column($name, $page = "page-1") {
	global $CSmydash;

	$CSmydash->register_column($name, $page);
}

function register_mydashboard_page($name, $options = array(), $type = 'standard') {
	global $CSmydash;

	$CSmydash->register_page($name, $options, $type);
}


$CSmydash =& new CSMyDashboard();


?>