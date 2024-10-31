<?php if(!defined('ABSPATH')) die(); ?>
<!-- Our admin page content should all be inside .wrap -->
<div class="wrap">
	<!-- Print the page title -->
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
	<!-- Here are our tabs -->
	<nav class="nav-tab-wrapper">
	<?php foreach($this->menu as $parent_slug => $menus) : ?>
		<?php foreach($menus['submenu'] as $menu) : ?>
		<?php $menuSlug = $this->getSettingName($menu['slug']); ?>
		<a href="?page=<?php echo esc_html($menuSlug);?>" class="nav-tab <?php echo ($menuSlug == $menu['slug'] ? 'nav-tab-active' : '');?>"><?php echo esc_html($menu['name']);?></a>
		<?php endforeach; ?>
	<?php endforeach; ?>
	</nav>
