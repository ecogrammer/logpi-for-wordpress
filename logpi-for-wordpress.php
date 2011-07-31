<?php
/*
Plugin Name: Logpi for Wordpress
Description: ミニグログサービスログピの書き込みを表示するプラグイン
Plugin URI: http://ecogrammer.manno.jp/logpi-for-wordpress/
Version: 0.5
Author URI: http://ecogrammer.manno.jp/
Author: Junji Manno
*/

define('MAGPIE_CACHE_ON', 1);
define('MAGPIE_CACHE_AGE', 180);
define('MAGPIE_INPUT_ENCODING', 'UTF-8');
define('MAGPIE_OUTPUT_ENCODING', 'UTF-8');

$logpi_options['widget_fields']['title'] = array('label'=>'Title:', 'type'=>'text', 'default'=>'');
$logpi_options['widget_fields']['username'] = array('label'=>'ユーザー名:', 'type'=>'text', 'default'=>'');
$logpi_options['widget_fields']['num'] = array('label'=>'表示件数:', 'type'=>'text', 'default'=>'5');
$logpi_options['widget_fields']['logpi_title'] = array('label'=>'タイトルタグを表示:', 'type'=>'checkbox', 'default'=>true);
$logpi_options['widget_fields']['update'] = array('label'=>'投稿日時を表示:', 'type'=>'checkbox', 'default'=>true);
$logpi_options['widget_fields']['linked'] = array('label'=>'ログリンク:', 'type'=>'text', 'default'=>'#');
$logpi_options['widget_fields']['hyperlinks'] = array('label'=>'記事内リンク:', 'type'=>'checkbox', 'default'=>true);
$logpi_options['widget_fields']['logpi_users'] = array('label'=>'ユーザーリンク @リプライ:', 'type'=>'checkbox', 'default'=>true);
$logpi_options['widget_fields']['encode_utf8'] = array('label'=>'UTF8 Encode:', 'type'=>'checkbox', 'default'=>false);
$logpi_options['widget_fields']['feed_type'] = array('label'=>'表示ログ:', 'type'=>'text', 'default'=>'you');
$logpi_options['prefix'] = 'logpi';

// logpi ログ表示
function logpi_messages(
    $username = '', 
    $num = 1, 
    $list = false, 
    $logpi_title = true, 
    $update = true, 
    $linked  = '#', 
    $hyperlinks = true, 
    $logpi_users = true, 
    $encode_utf8 = false,
    $feed_type = 'you'
)
{
    global $logpi_options;
    include_once(ABSPATH . WPINC . '/rss.php');

    // feed type
    if (!preg_match('/^(you|reply|follow|fav)/',$feed_type)) {
        $feed_type = 'you';
    }

	$messages = fetch_rss('http://logpi.jp/' . $username . '/feed/' . $feed_type);

	if ($list) echo '<ul class="logpi">';
	
    if ($username == '') {
        if ($list) echo '<li>';
        echo 'RSS not configured';
        if ($list) echo '</li>';
    } else {
        if ( empty($messages->items) ) {
            if ($list) echo '<li>';
            echo 'No public Logpi messages.';
            if ($list) echo '</li>';
        } else {
            $i = 0;
            foreach ( $messages->items as $message ) {

                // タイトルタグ,メッセージ
                if ($logpi_title) {
                    $msg = $message['title']."&nbsp;".$message['description'];
                // メッセージのみ
                } else {
                    $msg = $message['description'];
                }

                if($encode_utf8) $msg = utf8_encode($msg);

                // リンク
                $link = $message['link'];

                if ($list) echo '<li class="logpi-item">'; elseif ($num != 1) echo '<p class="logpi-message">';

                // ハイパーリンク
                if ($hyperlinks) { $msg = logpi_hyperlinks($msg); }
                
                // ログピユーザー
                if ($logpi_users)  { $msg = logpi_users($msg); }
          		
          		// メッセージリンク		
                if ($linked != '' || $linked != false) {
                if($linked == 'all')  { 
                    // Puts a link to the status of each tweet 
                    $msg = '<a href="'.$link.'" class="logpi-link">'.$msg.'</a>';  
                } else {
                    // Puts a link to the status of each tweet
                    $msg = $msg . '<a href="'.$link.'" class="logpi-link">'.$linked.'</a>'; 
                }
            }

            echo $msg;

            if($update) {
                $time = strtotime($message['pubdate']);
                if ( ( abs( time() - $time) ) < 86400 ) {
                    $h_time = sprintf( __('%s ago'), human_time_diff( $time ) );
                } else {
                    $h_time = date(__('Y/m/d'), $time);
                }
                echo sprintf( __('%s', 'logpi-for-wordpress'),' <span class="logpi-timestamp"><abbr title="' . date(__('Y/m/d H:i:s'), $time) . '">' . $h_time . '</abbr></span>' );
            }
            if ($list) echo '</li>'; elseif ($num != 1) echo '</p>';

			$i++;
			if ( $i >= $num ) break;
			}
		}
	}

	if ($list) echo '</ul>';
}

// リンク検知
function logpi_hyperlinks($text)
{
    // match protocol://address/path/file.extension?some=variable&another=asf%
    $text = preg_replace('/\b([a-zA-Z]+:\/\/[\w_.\-]+\.[a-zA-Z]{2,6}[\/\w\-~.?=&%#+$*!]*)\b/i',"<a href=\"$1\" class=\"logpi-link\">$1</a>", $text);
    // match www.something.domain/path/file.extension?some=variable&another=asf%
    $text = preg_replace('/\b(?<!:\/\/)(www\.[\w_.\-]+\.[a-zA-Z]{2,6}[\/\w\-~.?=&%#+$*!]*)\b/i',"<a href=\"http://$1\" class=\"logpi-link\">$1</a>", $text);    
    // match name@address
    $text = preg_replace("/\b([a-zA-Z][a-zA-Z0-9\_\.\-]*[a-zA-Z]*\@[a-zA-Z][a-zA-Z0-9\_\.\-]*[a-zA-Z]{2,6})\b/i","<a href=\"mailto://$1\" class=\"logpi-link\">$1</a>", $text);
    // match #trendingtopics.
    $text = preg_replace('/([\.|\,|\:|\¡|\¿|\>|\{|\(]?)#{1}(\w*)([\.|\,|\:|\!|\?|\>|\}|\)]?)\s/i', "$1<a href=\"http://twitter.com/#search?q=$2\" class=\"logpi-link\">#$2</a>$3 ", $text);

    return $text;
}

// logpi ユーザー返信ログ
function logpi_users($text)
{
    $text = preg_replace('/([\.|\,|\:|\¡|\¿|\>|\{|\(]?)@{1}(\w*)([\.|\,|\:|\!|\?|\>|\}|\)]?)\s/i', "$1<a href=\"http://logpi.jp/$2\" class=\"logpi-user\">@$2</a>$3 ", $text);

    return $text;
}     

// logpiウィジット設定情報
function widget_logpi_init()
{
    if ( !function_exists('register_sidebar_widget') ){
        return;
    }

	// widget_logpi からオプションの取得
    $check_options = get_option('widget_logpi');

    if ($check_options['number']=='') {
        $check_options['number'] = 1;
        // widget_logpi からオプションを更新
        update_option('widget_logpi', $check_options);
    }

    // logpiウィジット設定
	function widget_logpi($args, $number = 1)
	{
		global $logpi_options;

		extract($args);

		include_once(ABSPATH . WPINC . '/rss.php');
		$options = get_option('widget_logpi');

		$item = $options[$number];
        //var_dump ($item);

		foreach($logpi_options['widget_fields'] as $key => $field) {
			if (! isset($item[$key])) {
				$item[$key] = $field['default'];
			}
		}

		$messages = fetch_rss('http://logpi.jp/'.$item['username'].'/feed/');
        //var_dump ($messages);

        echo $before_widget . $before_title . 
            '<a href="http://logpi.jp/' . $item['username'] . '" class="logpi_title_link">'. $item['title'] . '</a>' . 
            $after_title;

        logpi_messages(
            $item['username'], 
            $item['num'], 
            true,        
            $item['logpi_title'], 
            $item['update'], 
            $item['linked'], 
            $item['hyperlinks'], 
            $item['logpi_users'], 
            $item['encode_utf8'],
            $item['feed_type']
        );

        echo $after_widget;
				
	}

    // logpi ウィジットコントロール画面
	function widget_logpi_control($number)
	{
		global $logpi_options;

		$options = get_option('widget_logpi');
		
        // option setting update
		if ( isset($_POST['logpi-submit']) ) {

			foreach($logpi_options['widget_fields'] as $key => $field) {
				$options[$number][$key] = $field['default'];
				$field_name = sprintf('%s_%s_%s', $logpi_options['prefix'], $key, $number);

				if ($field['type'] == 'text') {
					$options[$number][$key] = strip_tags(stripslashes($_POST[$field_name]));
				} elseif ($field['type'] == 'checkbox') {
					$options[$number][$key] = isset($_POST[$field_name]);
				}
			}

			update_option('widget_logpi', $options);
		}

		foreach($logpi_options['widget_fields'] as $key => $field) {
			
			$field_name = sprintf('%s_%s_%s', $logpi_options['prefix'], $key, $number);
			$field_checked = '';

            // text
			if ($field['type'] == 'text') {
				$field_value = htmlspecialchars($options[$number][$key], ENT_QUOTES);
            // checkbox
			} elseif ($field['type'] == 'checkbox') {
				$field_value = 1;
				if (! empty($options[$number][$key])) {
					$field_checked = 'checked="checked"';
				}
			}
			
			printf('
			     <p style="text-align:right;" class="logpi_field"><label for="%s">%s  
			         <input id="%s" name="%s" type="%s" value="%s" class="%s" %s /></label></p>',
			     $field_name, 
			     __($field['label']), 
			     $field_name, 
			     $field_name, 
			     $field['type'], 
			     $field_value, 
			     $field['type'], 
			     $field_checked
            );
		}

		echo '<input type="hidden" id="logpi-submit" name="logpi-submit" value="1" />';

	}

    // logpi ウィジットセットアップ
	function widget_logpi_setup()
	{
		$options = $newoptions = get_option('widget_logpi');

		if ( isset($_POST['logpi-number-submit']) ) {
			$number = (int) $_POST['logpi-number'];
			$newoptions['number'] = $number;
		}
		if ( $options != $newoptions ) {
			update_option('widget_logpi', $newoptions);
			widget_logpi_register();
		}
	}

    // logpi ウィジット登録数設定
	function widget_logpi_page()
	{
		$options = $newoptions = get_option('widget_logpi');
    	?>
		<div class="wrap">
			<form method="POST">
				<h2><?php _e('ログピウィジット'); ?></h2>
				<p style="line-height: 30px;"><?php _e('どのくらいログピウィジットを登録しますか？'); ?>
				<select id="logpi-number" name="logpi-number" value="<?php echo $options['number']; ?>">
	               <?php for ( $i = 1; $i < 10; ++$i ) echo "<option value='$i' ".($options['number']==$i ? "selected='selected'" : '').">$i</option>"; ?>
				</select>
				<span class="submit"><input type="submit" name="logpi-number-submit" id="logpi-number-submit" value="<?php echo attribute_escape(__('Save')); ?>" /></span></p>
			</form>
		</div>
        <?php
	}

    // logpi ウィジット登録
	function widget_logpi_register()
	{
		$options = get_option('widget_logpi');
		$dims = array('width' => 300, 'height' => 300);
		$class = array('classname' => 'widget_logpi');

		for ($i = 1; $i <= 9; $i++) {
			$name = sprintf(__('Logpi #%d'), $i);
			$id = "logpi-$i"; // Never never never translate an id
			wp_register_sidebar_widget($id, $name, $i <= $options['number'] ? 'widget_logpi' : /* 未登録 */ '', $class, $i);
			wp_register_widget_control($id, $name, $i <= $options['number'] ? 'widget_logpi_control' : /* 未登録 */ '', $dims, $i);
		}
		
		add_action('sidebar_admin_setup', 'widget_logpi_setup');
		add_action('sidebar_admin_page', 'widget_logpi_page');

	}

	widget_logpi_register();

}


// widget_logpi_init をウィジットに登録
add_action('widgets_init', 'widget_logpi_init');

?>