<?php
/**
 * Search form.
 *
 * @package Nordlys
 */

defined( 'ABSPATH' ) || exit;
?>
<form role="search" method="get" class="nb-search" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label class="screen-reader-text" for="nb-search-field"><?php esc_html_e( 'Suchen', 'nordlys' ); ?></label>
	<input type="search" id="nb-search-field" class="nb-search__field" placeholder="<?php esc_attr_e( 'Suchen …', 'nordlys' ); ?>" value="<?php echo esc_attr( get_search_query() ); ?>" name="s" />
	<button type="submit" class="nb-search__submit"><?php esc_html_e( 'Los', 'nordlys' ); ?></button>
</form>
