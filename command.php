<?php

# Check Exist WP-CLI
if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

/**
 * Wordpress Code Reference.
 *
 * ## OPTIONS
 *
 * [<search>]
 * : Search Keyword.
 *
 * [--function=<name>]
 * : Search Function name in WordPress Code Reference.
 *
 * [--class=<name>]
 * : Search Class name in WordPress Code Reference.
 *
 * [--hook=<name>]
 * : Search Hook name in WordPress Code Reference.
 *
 * [--method=<name>]
 * : Search Method name in WordPress Code Reference.
 *
 * [--clear]
 * : clear WordPress Reference Cache.
 *
 * [--browser]
 * : Show WordPress Code Reference in browser.
 *
 * ## EXAMPLES
 *
 *      # Search between WordPress function or class and show document.
 *      $ wp reference absint
 *
 *      # Search in WordPress Code.
 *      $ wp reference get_userdata
 *
 *      # Search only in WordPress functions.
 *      $ wp reference --function=wp_insert_user
 *
 *      # Search only in WordPress class.
 *      $ wp reference --class=wp_user
 *
 *      # Search only in WordPress hook.
 *      $ wp reference --hook=admin_footer
 *
 *      # search only in WordPress method.
 *      $ wp reference --method=get_row
 *
 *      # Remove All WordPress Reference Cache.
 *      $ wp reference --clear
 *      Success: Cache cleared.
 *
 *      # Show code reference in web browser
 *      $ wp reference --browser
 *
 * @when before_wp_load
 */
\WP_CLI::add_command( 'reference', function ( $args, $assoc_args ) {

		# init Reference Class
		$reference = new Reference_Command();

		# Clear Cache
		if ( isset( $assoc_args['clear'] ) ) {
			$reference->run_clear_cache();

			# Show in browser
		} elseif ( isset( $assoc_args['browser'] ) ) {
			$reference->run_browser();

			# Search
		} else {

			//Prepare Word
			$word = '';
			if ( isset( $args[0] ) ) {
				$word = $args[0];
			}

			//Show Loading
			Reference_Command::pl_wait_start();

			//Custom Search
			$list          = array( 'function', 'class', 'hook', 'method' );
			$custom_search = false;
			foreach ( $list as $action ) {
				if ( isset( $assoc_args[ $action ] ) ) {
					$word = $assoc_args[ $action ];
					$reference->run_search( $word, array( 'source' => true, 'allowed_filter' => $action ) );
					$custom_search = true;
					break;
				}
			}

			//Common Search
			if ( ! $custom_search ) {
				$reference->run_search( $word, array( 'source' => true ) );
			}
		}

	} );
