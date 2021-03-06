<?
// The source code packaged with this file is Free Software, Copyright (C) 2005 by
// Ricardo Galli <gallir at uib dot es>.
// It's licensed under the AFFERO GENERAL PUBLIC LICENSE unless stated otherwise.
// You can get copies of the licenses here:
//              http://www.affero.org/oagpl.html
// AFFERO GENERAL PUBLIC LICENSE is also included in the file called "COPYING".

include('config.php');
include(mnminclude.'html1.php');
include(mnminclude.'link.php');
include(mnminclude.'comment.php');
include(mnminclude.'user.php');
include(mnminclude.'geo.php');


$offset=(get_current_page()-1)*$page_size;
$globals['ads'] = true;


if (!empty($globals['base_user_url']) && !empty($_SERVER['PATH_INFO'])) {
	$url_args = preg_split('/\/+/', $_SERVER['PATH_INFO']);
	array_shift($url_args); // The first element is always a "/"
	$_REQUEST['login'] = clean_input_string($url_args[0]);
	$_REQUEST['view'] = $url_args[1];
} else {
	$_REQUEST['login'] = clean_input_string($_REQUEST['login']);
	if (!empty($globals['base_user_url']) && !empty($_REQUEST['login'])) {
		header('Location: ' . get_user_uri($_REQUEST['login'], clean_input_string($_REQUEST['view'])));
		die;
	}
}

$login = $_REQUEST['login'];
if(empty($login)){
	if ($current_user->user_id > 0) {
		header('Location: ' . get_user_uri($current_user->user_login));
		die;
	} else {
		header('Location: '.$globals['base_url']);
		die;
	}
}
$user=new User();
$user->username = $db->escape($login);
if(!$user->read()) {
	not_found();
}

// For editing notes
if ($current_user->user_id == $user->id) {
	array_push($globals['extra_js'], 'jquery-form.pack.js');
}

// Enable user AdSense
// do_user_ad: 0 = noad, > 0: probability n/100
// 100 if the user is the current one
if($globals['external_user_ads'] && !empty($user->adcode)) {
    $globals['user_adcode'] = $user->adcode;
    $globals['user_adchannel'] = $user->adchannel;
	if ($current_user->user_id == $user->id || $current_user->user_level=='god') $globals['do_user_ad']  = 100; 
	else $globals['do_user_ad'] = $user->karma * 2;
}

$view = clean_input_string($_REQUEST['view']);
if(empty($view)) $view = 'profile';

// Load Google GEO
if ($view == 'profile' && $globals['google_maps_api'] && (($globals['latlng']=$user->get_latlng()) || $current_user->user_id == $user->id)) {
	if ($current_user->user_id == $user->id) {
		geo_init('geo_coder_editor_load', $globals['latlng'], 7, 'user');
	} else {
		geo_init('geo_coder_load', $globals['latlng'], 7, 'user');
	}
	$globals['do_geo'] = true;
}

switch ($view) {
	case 'categories':
	case 'history':
	case 'commented':
	case 'shaken':
	case 'friends':
	case 'favorites':
		$globals['noindex'] = true;
}

do_header($login);

do_banner_top();

echo '<div id="container-wide">' . "\n";
echo '<div id="genericform-contents">'."\n";

$url_login = urlencode($login);
switch ($view) {
	case 'history':
		do_user_tabs(2, $login);
		do_history();
		do_pages($rows, $page_size);
		break;
	case 'commented':
		do_user_tabs(3, $login);
		do_commented();
		do_pages($rows, $page_size, false);
		break;
	case 'shaken':
		do_user_tabs(4, $login);
		do_shaken();
		do_pages($rows, $page_size);
		break;
	// Disabls becuase affiliation was also disabled
	/**********
	case 'preferred':
		do_user_tabs(5, $login);
		do_voters_preferred();
		break;
	***********/
	case 'friends':
		do_user_tabs(7, $login);
		do_friends();
		break;
	case 'favorites':
		do_user_tabs(6, $login);
		do_favorites();
		do_pages($rows, $page_size);
		break;
	case 'categories':
		do_user_tabs(8, $login);
		do_categories();
		break;
	case 'profile':
	default:
		do_user_tabs(1, $login);
		do_profile();
		break;
}

echo '</div>'."\n";

do_footer();


function do_profile() {
	global $user, $current_user, $login, $db, $globals;

	if(!empty($user->url)) {
		if ($user->karma < 10) $nofollow = 'rel="nofollow"';
		if (!preg_match('/^http/', $user->url)) $url = 'http://'.$user->url;
		else $url = $user->url;
	}
	$post = new Post;
	if ($post->read_last($user->id)) {   
		echo '<ol class="comments-list" id="last_post">';   
		$post->print_summary();   
		echo "</ol>\n";
	}   

	echo '<fieldset><legend>';
	echo _('información personal');
	if($login===$current_user->user_login) {
		echo ' (<a href="'.$globals['base_url'].'profile.php">'._('modificar').'</a>)';
	} elseif ($current_user->user_level == 'god') {
		echo ' (<a href="'.$globals['base_url'].'profile.php?login='.urlencode($login).'">'._('modificar').'</a>)';
	}
	echo '</legend>';

	// Avatar
	echo '<img class="thumbnail" src="'.get_avatar_url($user->id, $user->avatar, 80).'" width="80" height="80" alt="'.$user->username.'" title="avatar" />';
	// Geo div
	echo '<div style="width:140px; float:left;">';
	if($globals['do_geo']) {
		echo '<div id="map" class="thumbnail" style="width:130px; height:130px; overflow:hidden; float:left"></div>';
		if ($current_user->user_id > 0 && $current_user->user_id != $user->id && $globals['latlng'] && ($my_latlng = geo_latlng('user', $current_user->user_id))) {
			$distance = (int) geo_distance($my_latlng, $globals['latlng']);
			echo '<p style="color: #FF9400; font-size: 90%">'._('estás a')." <strong>$distance kms</strong> "._('de').' '.$user->username.'</p>';
		}
	}
	echo '&nbsp;</div>';


	echo '<div style="float:left;min-width:65%">';
	echo '<dl>';	
	if(!empty($user->username)) {
		echo '<dt>'._('usuario').':</dt><dd>';
		if (!empty($url)) {
			echo '<a href="'.$url.'" '.$nofollow.'>'.$user->username.'</a>';
		} else {
			echo $user->username;
		}
		// Print friend icon
		if ($current_user->user_id > 0 && $current_user->user_id != $user->id) {
			echo '&nbsp;<a id="friend-'.$current_user->user_id.'-'.$user->id.'" href="javascript:get_votes(\'get_friend.php\',\''.$current_user->user_id.'\',\'friend-'.$current_user->user_id.'-'.$user->id.'\',0,\''.$user->id.'\')">'.friend_teaser($current_user->user_id, $user->id).'</a>';
		}
		// Print user detailed info
		if ($login===$current_user->user_login || $current_user->user_level == 'god') {
			echo " (" . _('id'). ": <em>$user->id</em>)";
			echo " (<em>$user->level</em>)";
		}
		if($current_user->user_level=='god') {
			echo " (<em>$user->username_register</em>)";
		}
		echo '</dd>';
	}

	if(!empty($user->names)) {
		echo '<dt>'._('nombre').':</dt><dd>'.$user->names.'</dd>';
	}

	// Show public info is it's a friend or god
	if($current_user->user_id > 0 && !empty($user->public_info) && (
			$current_user->user_id == $user->id
			|| $current_user->user_level=='god' 
			/*|| friend_exists($user->id, $current_user->user_id)*/ )) {  //friends cannot see the IM address (it was public before)
		echo '<dt>'._('IM/email').':</dt><dd> '.$user->public_info.'</dd>';
	}

	if(!empty($url)) {
		echo '<dt>'._('sitio web').':</dt><dd><a href="'.$url.'" '.$nofollow.'>'.$url.'</a></dd>';
	}

	echo '<dt>'._('desde').':</dt><dd>'.get_date_time($user->date).'</dd>';

	if($current_user->user_level=='god') {
		echo '<dt>'._('email').':</dt><dd>'.$user->email. " (<em>$user->email_register</em>)</dd>";
	}

	if ($user->id == $current_user->user_id || $current_user->user_level=='god' ) {
		echo '<dt>'._('clave API').':</dt><dd id="api-key"><a href="javascript:get_votes(\'get_user_api_key.php\',\'\',\'api-key\',0,\''.$user->id.'\')">'._('leer clave API').'</a> ('._('no la divulgues').')</dd>';
		if(!empty($user->adcode)) {
			echo '<dt>'._('Código AdSense').':</dt><dd>'.$user->adcode.'&nbsp;</dd>';
			echo '<dt>'._('Canal AdSense').':</dt><dd>'.$user->adchannel.'&nbsp;</dd>';
		}
	}

	echo '<dt>'._('karma').':</dt><dd>'.$user->karma;
	// Karma details
	if ($user->id == $current_user->user_id || $current_user->user_level=='god' ) {
		echo ' (<a href="javascript:modal_from_ajax(\''.$globals['base_url'].'backend/get_karma_numbers.php?id='.$user->id.'\', \''.
			_('cálculo del karma').
			'\')" title="'._('detalles').'">'._('detalle cálculo').'</a>)';
	}
	echo '</dd>';

	$user->all_stats();
	echo '<dt>'._('noticias enviadas').':</dt><dd>'.$user->total_links.'</dd>';
	if ($user->total_links > 0 && $user->published_links > 0) {
		$percent = intval($user->published_links/$user->total_links*100);
	} else {
		$percent = 0;
	}
	if ($user->total_links > 1) {
		$entropy = intval(($user->blogs() - 1) / ($user->total_links - 1) * 100);
		echo '<dt><em>'._('entropía').'</em>:</dt><dd>'.$entropy.'%</dd>';
	}
	echo '<dt>'._('noticias publicadas').':</dt><dd>'.$user->published_links.' ('.$percent.'%)</dd>';
	echo '<dt>'._('comentarios').':</dt><dd>'.$user->total_comments.'</dd>';
	echo '<dt>'._('notas').':</dt><dd>'.$user->total_posts.'</dd>';
	echo '<dt>'._('número de votos').':</dt><dd>'.$user->total_votes.'</dd>';

	echo '</dl>';

	echo '</div>';
	echo '</fieldset>';

	// Print GEO form
	if($globals['do_geo'] && $current_user->user_id == $user->id) {
		echo '<div class="geoform">';
		geo_coder_print_form('user', $current_user->user_id, $globals['latlng'], _('ubícate en el mapa (si te apetece)'), 'user');
		echo '</div>';
	}

	// Show first numbers of the address if the user has god privileges
	if ($current_user->user_level == 'god' &&
			$user->level != 'god' && $user->level != 'admin' ) { // tops and admins know each other for sure, keep privacy
		$addresses = $db->get_results("select INET_NTOA(vote_ip_int) as ip from votes where vote_type='links' and vote_user_id = $user->id order by vote_date desc limit 30");

		// Try with comments
		if (! $addresses) {
			$addresses = $db->get_results("select comment_ip as ip from comments where comment_user_id = $user->id and comment_date > date_sub(now(), interval 30 day) order by comment_date desc limit 30");
		}

		// Not addresses to show
		if (! $addresses) {
			return;
		}

		$clone_counter = 0;
		echo '<fieldset><legend>'._('últimas direcciones IP').'</legend>';
		echo '<ol>';
		$prev_address = '';
		foreach ($addresses as $dbaddress) {
			$ip_pattern = preg_replace('/\.[0-9]+$/', '', $dbaddress->ip);
			if($ip_pattern != $prev_address) {
				echo '<li>'. $ip_pattern . ': <span id="clone-container-'.$clone_counter.'"><!--<a href="javascript:get_votes(\'ip_clones.php\',\''.$ip_pattern.'\',\'clone-container-'.$clone_counter.'\',0,'.$user->id.')" title="'._('clones').'">&#187;&#187;</a>--></span></li>';
				$clone_counter++;
				$prev_address = $ip_pattern;
				if ($clone_counter >= 30) break;
			}
		}
		echo '</ol>';
		echo '</fieldset>';
	}
}


function do_history () {
	global $db, $rows, $user, $offset, $page_size, $globals;

	$link = new Link;
	$rows = $db->get_var("SELECT count(*) FROM links WHERE link_author=$user->id AND link_votes > 0");
	$links = $db->get_col("SELECT link_id FROM links WHERE link_author=$user->id AND link_votes > 0 ORDER BY link_date DESC LIMIT $offset,$page_size");
	if ($links) {
		echo '<div class="bookmarks-export-user-stories">';
		echo '<a href="'.$globals['base_url'].'link_bookmark.php?user_id='.$user->id.'&amp;option=history" title="'._('exportar bookmarks en formato Mozilla').'"><img src="'.$globals['base_url'].'img/common/bookmarks-export-01.png" alt="Mozilla bookmark"/></a>';
		echo '&nbsp;&nbsp;<a href="'.$globals['base_url'].'rss2.php?sent_by='.$user->id.'" title="'._('obtener historial en rss2').'"><img src="'.$globals['base_url'].'img/common/rss-button01.png" alt="rss2"/></a>';
		echo '</div>';
		foreach($links as $link_id) {
			$link->id=$link_id;
			$link->read();
			$link->print_summary('short');
		}
	}
}

function do_favorites () {
	global $db, $rows, $user, $offset, $page_size, $globals;

	$link = new Link;
	$rows = $db->get_var("SELECT count(*) FROM favorites WHERE favorite_user_id=$user->id");
	$links = $db->get_col("SELECT link_id FROM links, favorites WHERE favorite_user_id=$user->id AND favorite_link_id=link_id ORDER BY link_date DESC LIMIT $offset,$page_size");
	if ($links) {
		echo '<div class="bookmarks-export-user-stories">';
		echo '<a href="'.$globals['base_url'].'link_bookmark.php?user_id='.$user->id.'&amp;option=favorites&amp;url=source" title="'._('formato Mozilla bookmarks').'"><img src="'.$globals['base_url'].'img/common/bookmarks-export-01.png" alt="Mozilla bookmark"/></a>';
		echo '&nbsp;&nbsp;<a href="'.$globals['base_url'].'rss2.php?favorites='.$user->id.'" title="'._('obtener favoritos en rss2').'"><img src="'.$globals['base_url'].'img/common/rss-button01.png" alt="rss2"/></a>';
		echo '</div>';
		foreach($links as $link_id) {
			$link->id=$link_id;
			$link->read();
			$link->print_summary('short');
		}
	}
}

function do_shaken () {
	global $db, $rows, $user, $offset, $page_size, $globals;

	if ($globals['bot']) return;

	$link = new Link;
	$rows = $db->get_var("SELECT count(*) FROM links, votes WHERE vote_type='links' and vote_user_id=$user->id AND vote_link_id=link_id and vote_value > 0");
	$links = $db->get_col("SELECT link_id FROM links, votes WHERE vote_type='links' and vote_user_id=$user->id AND vote_link_id=link_id  and vote_value > 0 ORDER BY link_date DESC LIMIT $offset,$page_size");
	if ($links) {
		echo '<div class="bookmarks-export-user-stories">';
		echo '<a href="'.$globals['base_url'].'link_bookmark.php?user_id='.$user->id.'&amp;option=shaken" title="'._('exportar bookmarks en formato Mozilla').'"><img src="'.$globals['base_url'].'img/common/bookmarks-export-01.png" alt="Mozilla bookmark"/></a>';
		echo '&nbsp;&nbsp;<a href="'.$globals['base_url'].'rss2.php?voted_by='.$user->id.'" title="'._('noticias votadas en rss2').'"><img src="'.$globals['base_url'].'img/common/rss-button01.png" alt="rss2"/></a>';
		echo '</div>';
		foreach($links as $link_id) {
			$link->id=$link_id;
			$link->read();
			$link->print_summary('short');
		}
		echo '<br/><span class="credits-strip-text"><strong>'._('Nota').'</strong>: ' . _('sólo se visualizan los votos de los últimos meses') . '</span><br />';
	}
}


function do_commented () {
	global $db, $rows, $user, $offset, $page_size, $globals, $current_user;

	if ($globals['bot']) return;

	$link = new Link;
	$comment = new Comment;
	$rows = $db->get_var("SELECT count(*) FROM comments WHERE comment_user_id=$user->id");
	$comments = $db->get_results("SELECT comment_id, link_id, comment_type FROM comments, links WHERE comment_user_id=$user->id and link_id=comment_link_id ORDER BY comment_date desc LIMIT $offset,$page_size");
	if ($comments) {
		echo '<div class="bookmarks-export-user-stories">';
		echo '<a href="'.$globals['base_url'].'link_bookmark.php?user_id='.$user->id.'&amp;option=commented" title="'._('exportar bookmarks en formato Mozilla').'" class="bookmarks-export-user-commented"><img src="'.$globals['base_url'].'img/common/bookmarks-export-01.png" alt="Mozilla bookmark"/></a>';
		echo '&nbsp;&nbsp;<a href="'.$globals['base_url'].'comments_rss2.php?user_id='.$user->id.'" title="'._('obtener comentarios en rss2').'"><img src="'.$globals['base_url'].'img/common/rss-button01.png" alt="rss2"/></a>';
		echo '</div>';
		foreach ($comments as $dbcomment) {
			if ($dbcomment->comment_type == 'admin' && $current_user->user_level != 'god' && $current_user->user_level != 'admin') continue;
			$link->id=$dbcomment->link_id;
			$comment->id = $dbcomment->comment_id;
			if ($last_link != $link->id) {
				$link->read();
				echo '<h4>';
				echo '<a href="'.$link->get_permalink().'">'. $link->title. '</a>';
				echo ' ['.$link->comments.']';
				echo '</h4>';
				$last_link = $link->id;
			}
			$comment->read();
			echo '<ol class="comments-list">';
			$comment->print_summary($link, 2000, false);
			echo "</ol>\n";
		}
	}
}

/************
function do_voters_preferred() {
	global $db, $user;

	echo '<fieldset style="width: 45%; display: block; float: left;"><legend>';
	echo _('autores preferidos');
	echo '</legend>';
	$prefered_id = $user->id;
	$prefered_type = 'friends';
	echo '<div id="friends-container">'. "\n";
	require('backend/get_prefered_bars.php');
	echo '</div>'. "\n";
	echo '</fieldset>'. "\n";


	echo '<fieldset style="width: 45%; display: block; float: right;"><legend>';
	echo _('votado por');
	echo '</legend>';
	$prefered_id = $user->id;
	$prefered_type = 'voters';
	echo '<div id="voters-container">'. "\n";
	require('backend/get_prefered_bars.php');
	echo '</div>'. "\n";
	echo '</fieldset>'. "\n";

	echo '<br clear="all" />';

	// Show first numbers of the addresss if the user has god privileges
	if ($current_user->user_level == 'god' &&
			$user->level != 'god' && $user->level != 'admin' ) { // tops and admins know each other for sure, keep privacy
		$addresses = $db->get_results("select distinct INET_NTOA(vote_ip_int) as ip from votes where vote_type='links' and vote_user_id = $user->id and vote_date > date_sub(now(), interval 60 day) order by vote_date desc limit 20");

		// Try with comments
		if (! $addresses) {
			$addresses = $db->get_results("select distinct comment_ip as ip from comments where comment_user_id = $user->id and comment_date > date_sub(now(), interval 60 day) order by comment_date desc limit 20");
		}

		// Not addresses to show
		if (! $addresses) {
			return;
		}

		$clone_counter = 0;
		echo '<fieldset><legend>'._('últimas direcciones IP').'</legend>';
		echo '<ol>';
		foreach ($addresses as $dbaddress) {
			$ip_pattern = preg_replace('/\.[0-9]+$/', '', $dbaddress->ip);
			echo '<li>'. $ip_pattern . ': <span id="clone-container-'.$clone_counter.'"><!--<a href="javascript:get_votes(\'ip_clones.php\',\''.$ip_pattern.'\',\'clone-container-'.$clone_counter.'\',0,'.$user->id.')" title="'._('clones').'">&#187;&#187;</a>--></span></li>';
			$clone_counter++;
		}
		echo '</ol>';
		echo '</fieldset>';
	}


}
***************/

function do_friends() {
	global $db, $user, $globals;

	echo '<div class="bookmarks-export-user-stories">';
	echo '<a href="'.$globals['base_url'].'rss2.php?friends_of='.$user->id.'" title="'._('noticias de amigos en rss2').'"><img src="'.$globals['base_url'].'img/common/rss-button01.png" alt="rss2"/></a>';
	echo '</div>';

	echo '<fieldset style="width: 45%; display: block; float: left;"><legend>';
	echo _('amigos');
	echo '</legend>';
	$prefered_id = $user->id;
	$prefered_type = 'from';
	echo '<div id="from-container">'. "\n";
	require('backend/get_friends_bars.php');
	echo '</div>'. "\n";
	echo '</fieldset>'. "\n";


	echo '<fieldset style="width: 45%; display: block; float: right;"><legend>';
	echo _('elegido por');
	echo '</legend>';
	$prefered_id = $user->id;
	$prefered_type = 'to';
	echo '<div id="to-container">'. "\n";
	require('backend/get_friends_bars.php');
	echo '</div>'. "\n";
	echo '</fieldset>'. "\n";

	echo '<br clear="all" />';
}

function do_user_tabs($option, $user) {
	global $globals, $current_user;

	$active = array();
	$active[$option] = 'class="tabsub-this"';

	echo '<ul class="tabsub">'."\n";
	echo '<li><a '.$active[1].' href="'.get_user_uri($user).'">'._('perfil'). '</a></li>';
	echo '<li><a '.$active[8].' href="'.get_user_uri($user, 'categories').'">'._('personalización'). '</a></li>';
	echo '<li><a '.$active[7].' href="'.get_user_uri($user, 'friends').'">&nbsp;<img src="'.$globals['base_url'].'img/common/icon_heart_bi.gif" alt="amigos e ignorados" width="16" height="16" title="'._('amigos e ignorados').'"/>&nbsp;</a></li>';
	echo '<li><a '.$active[2].' href="'.get_user_uri($user, 'history').'">'._('enviadas'). '</a></li>';
	if (! $globals['bot']) {
		echo '<li><a '.$active[6].' href="'.get_user_uri($user, 'favorites').'">&nbsp;'.FAV_YES. '&nbsp;</a></li>';
		echo '<li><a '.$active[3].' href="'.get_user_uri($user, 'commented').'">'._('comentarios'). '</a></li>';
		echo '<li><a '.$active[4].' href="'.get_user_uri($user, 'shaken').'">'._('votadas'). '</a></li>';
		//echo '<li><a '.$active[5].' href="'.get_user_uri($user, 'preferred').'">'._('autores preferidos'). '</a></li>';
	}
	echo '<li><a href="'.post_get_base_url($user).'">'._('notas'). '</a></li>';
	echo '</ul>';
}

function do_categories() {
	global $current_user, $db, $user;

	if (is_array($_POST['categories'])) {
		$db->query("delete from prefs where pref_user_id = $current_user->user_id and pref_key = 'category'");
		$total = (int) $db->get_var("SELECT count(*) FROM categories WHERE category_parent != 0");
		if (count($_POST['categories']) < $total) {
			for ($i=0; $i<count($_POST['categories']); $i++){ 
				$cat = intval($_POST['categories'][$i]); 
				$db->query("insert into prefs (pref_user_id, pref_key, pref_value) values ($current_user->user_id, 'category', $cat)");
			}
		}
	}
	echo '<div id="genericform">';
	print_categories_checkboxes($user);
	echo '</div>';
}

function print_categories_checkboxes($user) {
    global $db, $current_user;

	if ($user->id != $current_user->user_id) $disabled = 'disabled="true"';
	else $disabled = false;

	$selected_set = $db->get_col("SELECT pref_value FROM prefs WHERE pref_user_id = $user->id and pref_key = 'category' ");
	if ($selected_set) {
		foreach ($selected_set as $cat) {
			$selected["$cat"] = true;
		}
	} else {
		$empty = true;
	}
	echo '<form action="" method="POST">';
	echo '<fieldset style="clear: both;">';
	echo '<legend>'._('categorías personalizadas').'</legend>'."\n";
	$metas = $db->get_results("SELECT category_id, category_name FROM categories WHERE category_parent = 0 ORDER BY category_name ASC");
	foreach ($metas as $meta) {
		echo '<dl class="categorylist" id="meta-'.$meta->category_id.'"><dt>';
		echo '<input '.$disabled.' name="meta_category[]" type="checkbox" value="'.$meta->category_id.'"';
		if ($empty) echo ' checked="true" ';
		echo 'onchange="select_meta(this, '.$meta->category_id.')" ';
		echo '/>';
		echo $meta->category_name.'</dt>'."\n";
		$categories = $db->get_results("SELECT category_id, category_name FROM categories WHERE category_parent = $meta->category_id ORDER BY category_name ASC");
		foreach ($categories as $category) {
			echo '<dd><input '.$disabled.' name="categories[]" type="checkbox" ';
			if ($empty || $selected[$category->category_id]) echo ' checked="true" ';
			echo 'value="'.$category->category_id.'"/>'._($category->category_name).'</dd>'."\n";
		}
		echo '</dl>'."\n";
	}
	echo '<br style="clear: both;"/>' . "\n";
	echo '</fieldset>';
	if (!$disabled) {
		echo '<input class="genericsubmit" type="submit" value="'._('grabar').'"/>';
	}
	echo '</form>';
?>
<script type="text/javascript">
function select_meta(input, meta) {
	if (input.checked) new_value = true;
	else new_value = false;
	meta_id = '#meta-'+meta;
	$(meta_id+' input').attr({checked: new_value});
	return false;
}
</script>
<?
}
?>
