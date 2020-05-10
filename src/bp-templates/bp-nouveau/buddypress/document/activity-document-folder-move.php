<?php
/**
 * BuddyBoss - Activity Document Folder Move
 *
 * @since BuddyBoss 1.0.0
 * @package BuddyBoss\Core
 */

?>

<div class="bp-media-move-folder" id="bp-media-move-folder" style="display: none;">
	<transition name="modal">
		<div class="modal-mask bb-white bbm-model-wrap">
			<div class="modal-wrapper">
				<div id="boss-media-create-album-popup" class="modal-container has-folderlocationUI">
					<header class="bb-model-header">
						<h4><?php esc_html_e( 'Move ', 'buddyboss' ); ?> <span class="target_name"></span> <?php esc_html_e( ' to ', 'buddyboss' ); ?><span class="target_folder">...</span></h4>
					</header>
					<div class="bb-field-wrap bb-field-wrap-search">
						<input type="text" class="ac_document_search_folder" value="" placeholder="<?php esc_html_e( 'Search Folders', 'buddyboss' ); ?>" />
					</div>
					<div class="bb-field-wrap">
						<?php bp_get_template_part( 'document/location-move' ); ?>
						<?php bp_get_template_part( 'document/document-create-folder' ); ?>
					</div>
					<footer class="bb-model-footer">
						<a href="#" class="bp-document-open-create-popup-folder"> <i class="bb-icon-plus-square"></i> <?php esc_html_e( 'Create new folder', 'buddyboss' ); ?></a>
						<a class="ac-folder-close-button" href="#"><?php esc_html_e( 'Cancel', 'buddyboss' ); ?></a>
						<a class="button bp-folder-move" id="" href="#"><?php esc_html_e( 'Move', 'buddyboss' ); ?></a>
					</footer>
				</div>
			</div>
		</div>
	</transition>
</div>
