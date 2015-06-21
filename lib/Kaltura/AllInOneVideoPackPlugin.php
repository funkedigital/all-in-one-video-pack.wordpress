<?php

class Kaltura_AllInOneVideoPackPlugin {
	public function init() {

        // show notice on admin pages except for kaltura_options
		if ( ! KalturaHelpers::getOption( 'kaltura_partner_id' ) &&	! isset( $_POST['submit'] ) &&	(!isset( $_GET['page'] ) || 'kaltura_options' !== $_GET['page'])
		) {
			add_action( 'admin_notices', array($this, 'adminWarning' ) );

			return;
		}

		// filters
		add_filter( 'media_buttons_context', array($this, 'mediaButtonsContextFilter' ) );
		add_filter( 'media_upload_tabs', array($this, 'mediaUploadTabsFilter' ) );
		add_filter( 'mce_external_plugins', array($this, 'mceExternalPluginsFilter' ) );
		add_filter( 'tiny_mce_version', array($this, 'tinyMceVersionFilter' ) );

		// actions
		add_action( 'admin_menu', array($this, 'adminMenuAction' ) );
		add_action( 'wp_enqueue_scripts', array($this, 'enqueueScripts' ) );
		add_action( 'admin_enqueue_scripts', array($this, 'adminEnqueueScripts' ) );

		// media upload actions
		add_action( 'media_upload_kaltura_upload', array($this, 'mediaUploadAction' ) );
		add_action( 'media_upload_kaltura_browse', array($this, 'mediaBrowseAction' ) );
		add_action( 'admin_print_scripts-media-upload-popup', array($this, 'mediaUploadPrintScriptsAction' ) );

		add_action( 'save_post', array($this, 'savePost' ) );
		add_action( 'wp_ajax_kaltura_ajax', array($this, 'executeLibraryController' ) );

		add_shortcode( 'kaltura-widget', array($this, 'shortcodeHandler' ) );
	}

	public function adminWarning() {
        $kalturaOptionsPageUrl = admin_url('options-general.php?page=kaltura_options');
		echo '<div class="updated fade">
		    <p>
		        <strong>' . esc_html__( 'To complete the All in One Video Pack installation, ') . '<a href="' . esc_url($kalturaOptionsPageUrl) . '">' . esc_html__('you must get a Partner ID.') . '</a></strong>
		    </p>
		</div>';
	}

	public function mceExternalPluginsFilter( $content ) {
		$pluginUrl          = KalturaHelpers::getPluginUrl();
		$content['kaltura'] = esc_url_raw($pluginUrl . '/tinymce/kaltura_tinymce.js?v' . KALTURA_PLUGIN_VERSION );

		return $content;
	}

	public function tinyMceVersionFilter( $content ) {
		return $content . '_k' . KALTURA_PLUGIN_VERSION;
	}

	public function adminMenuAction() {
		add_options_page( 'All in One Video', 'All in One Video', 'manage_options', 'kaltura_options', array($this, 'executeAdminController' ) );
		add_media_page( 'All in One Video', 'All in One Video', 'edit_posts', 'kaltura_library', array($this, 'executeLibraryController' ) );
	}

	public function enqueueScripts() {
		wp_enqueue_style( 'kaltura', KalturaHelpers::cssUrl( 'css/kaltura.css' ), array(), KALTURA_PLUGIN_VERSION );
		wp_enqueue_script( 'kaltura', KalturaHelpers::jsUrl( 'js/kaltura.js' ), array('jquery'), KALTURA_PLUGIN_VERSION, false );
	}

	public function adminEnqueueScripts() {
		wp_register_script( 'kaltura-admin', KalturaHelpers::jsUrl( 'js/kaltura-admin.js' ), array(), KALTURA_PLUGIN_VERSION, false );
		wp_register_script( 'kaltura-player-selector', KalturaHelpers::jsUrl( 'js/kaltura-player-selector.js' ), array(), KALTURA_PLUGIN_VERSION, true );
		wp_register_script( 'kaltura-entry-status-checker', KalturaHelpers::jsUrl( 'js/kaltura-entry-status-checker.js' ), array(), KALTURA_PLUGIN_VERSION, true );
		wp_register_script( 'kaltura-editable-name', KalturaHelpers::jsUrl( 'js/kaltura-editable-name.js' ), array(), KALTURA_PLUGIN_VERSION, true );
		wp_register_script( 'kaltura-jquery-validate', KalturaHelpers::jsUrl( 'js/jquery.validate.min.js' ), array(), KALTURA_PLUGIN_VERSION, true );

		wp_enqueue_script( 'kaltura', KalturaHelpers::jsUrl( 'js/kaltura.js' ), array(), KALTURA_PLUGIN_VERSION, false );
		wp_enqueue_style( 'kaltura-admin', KalturaHelpers::cssUrl( 'css/admin.css' ), array(), KALTURA_PLUGIN_VERSION );

		wp_enqueue_style( 'kaltura' );
	}

	function executeLibraryController() {
		if ( ! isset( $_GET['kaction'] ) ) {
			$_GET['kaction'] = 'library';
		}
		$controller = new Kaltura_LibraryController();
		$controller->execute();
	}

	function executeAdminController() {
		$controller = new Kaltura_AdminController();
		$controller->execute();
	}

	public function mediaButtonsContextFilter( $content ) {
		global $post_ID, $temp_ID;

		$uploading_iframe_ID       = (int) ( 0 == $post_ID ? $temp_ID : $post_ID );
		$media_upload_iframe_src   = admin_url("media-upload.php?post_id=$uploading_iframe_ID");
		$kaltura_iframe_src        = apply_filters( 'kaltura_iframe_src', "$media_upload_iframe_src&amp;tab=kaltura_upload" );
		$kaltura_title             = esc_attr__( 'Add Kaltura Media' );
		$kaltura_button_src        = KalturaHelpers::getPluginUrl() . '/images/kaltura_button.png';
        $kaltura_iframe_src_final  = $kaltura_iframe_src . "&amp;TB_iframe=true&amp;height=500&amp;width=840";

        $content .= '<a
		    href="' . esc_url($kaltura_iframe_src_final) . '"
		    class="thickbox"
		    title="' . esc_attr__($kaltura_title) . '">
		        <img src="' . esc_url($kaltura_button_src) . '"
		        alt="' . esc_attr__($kaltura_title) . '" />
		    </a>';


		return $content;
	}

	public function mediaUploadTabsFilter( $content ) {
		// hide other tabs when user clicks on our tab
		if (in_array(KalturaHelpers::getRequestParam('tab'), array('kaltura_upload', 'kaltura_browse')))
			$content = array();

		$content['kaltura_upload'] = esc_html__( 'Add Media' );
		$content['kaltura_browse'] = esc_html__( 'Browse Existing Media' );

		return $content;
	}

	public function mediaUploadTabsFilterOnlyKaltura() {
		$content = array();

		return $this->mediaUploadTabsFilter( $content );
	}

	public function mediaUploadAction() {
		if ( ! isset( $_GET['kaction'] ) ) {
			$_GET['kaction'] = 'upload';
		}

		$controller = new Kaltura_LibraryController();

		wp_iframe( array( $controller, 'execute' ) );
	}

	public function mediaBrowseAction() {
		if ( ! isset( $_GET['kaction'] ) ) {
			$_GET['kaction'] = 'browse';
		}

		$controller = new Kaltura_LibraryController();

		wp_iframe( array( $controller, 'execute' ) );
	}

	public function mediaUploadPrintScriptsAction() {
		wp_enqueue_script( 'kaltura_upload_popup', KalturaHelpers::jsUrl( 'js/upload-popup.js' ), array(), KALTURA_PLUGIN_VERSION, true );
	}

	public function shortcodeHandler( $attrs ) {
		if ( ! isset( $attrs['entryid'] ) ) {
			return '';
		}

		$attrs = KalturaSanitizer::shortCodeAttributes($attrs);
		$viewRenderer = new Kaltura_ViewRenderer();
		ob_start();
		$viewRenderer->renderView( 'embed-code.php', array('attrs' => $attrs) );
		$embedCode = ob_get_clean();

		return $embedCode;
	}

	public function savePost( $postId ) {
		if ( ! KalturaHelpers::getOption( 'kaltura_save_permalink' ) ) {
			return;
		}

		// ignore revisions
		if ( wp_is_post_revision( $postId ) ) {
			return;
		}

		try {
			$kmodel = KalturaModel::getInstance();
			$kmodel->updateEntryPermalink( $postId );
		} catch ( Exception $ex ) {
			trigger_error( 'An error occurred while updating entry\'s permalink - ' . $ex->getMessage() . ' - ' . $ex->getTraceAsString(),
				E_USER_NOTICE );
		}
	}
}