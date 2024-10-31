<?php
namespace searchHero;

class admin_helper {
	static protected $instance = null;
	protected $options = array();

	public static function getInstance($options = array() ){
		if(self::$instance == null){
			self::$instance = new self($options);
		}

		return self::$instance;
	}

	public function getSettingName($value){
		return $this->options['rootName'] . '-' . $value;
	}

	protected function __construct($options = array() ) {
		$this->options = $options;
		$this->menu = $this->options['menu'];

		add_action('plugins_loaded', array($this, 'load_textdomain'));
		add_action('admin_menu', array($this, 'add_menu_page'));
		add_filter('plugin_action_links_' . plugin_basename($this->options['plugin_file']), array($this, 'add_action_links'));
		add_action('admin_init', array($this, 'admin_init'));

		add_filter('plugin_row_meta', array($this, 'settings_plugin_row_meta'), 10, 2);
		add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));

		register_activation_hook($this->options['plugin_file'], array($this, 'settings_activation_redirect'));
		register_deactivation_hook($this->options['plugin_file'], array($this, 'settings_deactivation_redirect'));
	}

	protected function getMTime($filename){
		return filemtime(plugin_dir_path($this->options['plugin_file']) . $filename);
	}

	public function enqueue_scripts() {
		wp_enqueue_script($this->options['rootName'], plugins_url('js/search-hero.js', $this->options['plugin_file']), array('jquery'), 
			$this->getMTime('js/search-hero.js') , true);

	}

	public function settings_plugin_row_meta($links, $file) {
		if (plugin_basename($this->options['plugin_file']) === $file) {
			$view_details_link = '<a href="' . admin_url('plugin-install.php?tab=plugin-information&amp;plugin=' . urlencode($file) . '&amp;TB_iframe=true&amp;width=772&amp;height=930') . '" class="thickbox" aria-label="' . esc_attr__('View Details', 'search-hero') . '">' . esc_html__('View Details', 'search-hero') . '</a>';
			$links[] = $view_details_link;
		}

		return $links;
	}

	public function load_textdomain() {
		load_plugin_textdomain('search-hero', false, dirname(plugin_basename($this->options['plugin_file'])) . '/languages');
	}

	public function settings_activation_redirect() {
		add_option($this->getSettingName('activated'), true);

	}

	public function settings_deactivation_redirect() {

	}

	public function add_menu_page() {
		foreach($this->menu as $menu){
			add_menu_page(
				$menu['name'],
				$menu['name'],
				'manage_options',    
				$this->getSettingName($menu['slug']),
				array($this, 'render_settings_page'),
				$menu['icon'],
				99,
			);

			foreach($menu['submenu'] as $submenu){
				add_submenu_page(
					$this->getSettingName($menu['slug']),
					$submenu['name'],
					$submenu['name'],
					'manage_options',      
					$this->getSettingName($submenu['slug']),
					array($this, 'render_settings_page'),
				);
			}

		}
	}

	public function add_action_links($links) {
		$settings_link = '<a href="' . admin_url('options-general.php?page=' . esc_html($this->getSettingName('settings'))) . '">' . __('Settings', 'search-hero') . '</a>';

		array_unshift($links, $settings_link, $pro_link);
		return $links;
	}

	public function admin_init() {

	
		if (get_option($this->getSettingName('activated'))) {
			delete_option($this->getSettingName('activated'));
			wp_redirect(admin_url('options-general.php?page=' . $this->getSettingName('settings')));
			exit();
		}
	}

	public function render_settings_page() {
		$slug = '';
		$viewFile = '';
		foreach($this->menu as $topMenu){
			if(!$slug) $slug = $this->getSettingName($topMenu['slug']);
			$page = (isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '');
			if($page == $topMenu['slug']) $viewFile = $topMenu['view'];
			foreach($topMenu['submenu'] as $menu){
				$subMenuSlug = $this->getSettingName($menu['slug']);
				if($page == $subMenuSlug){
					$slug = $subMenuSlug;
					$viewFile = $menu['view'];
				}
			}
		}

		$current_user = wp_get_current_user();

		include(plugin_dir_path( __FILE__ ) . 'views/header.php');
		include(plugin_dir_path( __FILE__ ) . $viewFile);
		include(plugin_dir_path( __FILE__ ) . 'views/footer.php');
	}
}

