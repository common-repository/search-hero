<?php if(!defined('ABSPATH')) die(); ?>
<?php
	$indexingState = \searchHero\searchHero::getIndexingState();
	$percent = 0;
	if($indexingState['count']) $percent = round($indexingState['indexed'] / $indexingState['count'] * 100);
?>

<div class="wrap">
	<p><?php esc_html_e( 'To make your WordPress content searchable, please initiate the indexing process below. Stay on this page until the indexing is complete.', 'search-hero' ); ?></p>

	<div class="indexing-container">
		<!-- Progress Bar -->
		<div style="margin:0 0 10px 0">
			<?php esc_html_e( 'Indexing Progress: ', 'search-hero' ); ?>
			<span class="progress" style="display: inline-block; border: 1px solid #72aee6; background-color: #f0f0f0; padding: 1px; width: 200px; vertical-align: middle;">
				<span class="progress-bar" id="sh_progress" role="progressbar" style="background-color: #72aee6; width: <?php echo $percent;?>%; display: block; text-align: center;"><?php echo $percent;?>%</span>
			</span>
			<span><span id="percent_ratio1"><?php esc_html_e($indexingState['indexed']);?></span> / <span id="percent_ratio2"><?php esc_html_e($indexingState['count']);?></span></span>
		</div>

		<div class="mb-4">
			<?php if(!$indexingState['count']): ?>
			<!-- Start Indexing Button -->
			<button id="start_indexing" class="button button-primary"><?php esc_html_e( 'Start Indexing', 'search-hero' ); ?></button>
			<?php elseif($indexingState['state'] == 'continue'): ?>
			<button id="continue_indexing" class="button button-primary"><?php esc_html_e( 'Continue Indexing', 'search-hero' ); ?></button>
			<?php elseif($indexingState['state'] == 'finished'): ?>
			<button id="start_reindex" class="button button-primary"><?php esc_html_e( 'Rebuild Index', 'search-hero' ); ?></button>
			<?php endif; ?>
		</div>

		<pre id="results"></pre>
	</div>
</div>

