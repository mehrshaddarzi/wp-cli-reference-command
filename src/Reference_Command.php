<?php

class Reference_Command {
	/**
	 * Developer Wordpress Url
	 *
	 * @var string
	 */
	private static $developer_wordpress = 'developer.wordpress.org';

	/**
	 * Code reference home page
	 *
	 * @var string
	 */
	private static $reference_home_page = 'https://[url]/reference/';

	/**
	 * Default Search Link Wordpress Reference
	 *
	 * @var string
	 */
	private $wp_reference_search_link = 'https://[url]/?s=[search_word]&paged=[paged]';

	/**
	 * Pagination Global $_GET in wordpress Reference
	 *
	 * @var string
	 */
	public $pagination_global = 'paged';

	/**
	 * Filter global $_GET in wordpress Reference
	 *
	 * @var string
	 */
	private $filter_request = 'post_type';

	/**
	 * Filter List Search in Wordpress Reference
	 *
	 * @var array
	 */
	private $filter_list = array(
		'function',
		'hook',
		'class',
		'method'
	);

	/**
	 * Default Disable Filter in Fetch data
	 *
	 * @var array
	 */
	private $default_disable_filter = array( 'method', 'hook' );

	/**
	 * Filter Prefix in Url
	 *
	 * @var string
	 */
	private $filter_prefix = 'wp-parser-';

	/**
	 * Max Page Render in any request
	 *
	 * @var int
	 */
	private $max_page_request = 20;

	/**
	 * Default Dir name of Cache Reference Request
	 *
	 * @var string
	 */
	private $dir = 'reference';

	/**
	 * Full Path of Cache Dir
	 *
	 * @var string
	 */
	private $path;

	/**
	 * Last search file name
	 *
	 * @var string
	 */
	private $last_search_file = 'last.json';

	/**
	 * Last search file path
	 *
	 * @var string
	 */
	private $last_search_file_path;

	/**
	 * Number Of Max Cache Reference
	 *
	 * @var int
	 */
	private $max_cache_reference = 100;

	/**
	 * Check Exist Pagination in Search
	 *
	 * @var boolean
	 */
	private $is_pagination = false;

	/**
	 * Pagination Dom item Key
	 *
	 * @var int
	 */
	private $pagination_key = 0;

	/**
	 * Error List
	 *
	 * @var array
	 */
	public $log = array(
		'remove_cache'         => "Cache cleared.",
		'enter_id'             => "Please type the number list and press enter key: ",
		'enter_search_keyword' => "Please enter search keyword. e.g : 'wp reference absint'.",
		'not_found_id'         => "Your search ID is not found.",
		'empty_search_history' => "Your search history is empty.",
		'not_found'            => "Search not found in WordPress Reference.",
		'confirm_remove_cache' => "Are you sure you want to drop the cache ?",
		'dom'                  => "Could not access to Wordpress Reference Page. Please try again.",
		'connect'              => "Error connecting with WordPress Reference. Please check your internet connection and try again.",
		'DOMDocument'          => "Please install/enable the DOMDocument PHP Class.\nRead More http://php.net/manual/en/dom.setup.php",
	);

	/**
	 * Reference constructor.
	 */
	public function __construct() {
		/*
		 * Check Active Dom Document in php Server
		 */
		if ( ! class_exists( 'DOMDocument' ) ) {
			WP_CLI::error( $this->log['DOMDocument'] );
		}
		/*
		 * Set Default Variable
		 */
		$this->path                  = self::path_join( self::get_cache_dir(), $this->dir );
		$this->last_search_file_path = self::path_join( $this->path, $this->last_search_file );
	}

	/**
	 * Remove Reference Cache.
	 */
	public function run_clear_cache() {
		//Confirm Cli
		WP_CLI::confirm( $this->log['confirm_remove_cache'] );

		//Remove Folder
		if ( self::folder_exist( $this->path ) ) {
			self::remove_dir( $this->path, false );
		}

		WP_CLI::success( $this->log['remove_cache'] );
	}

	/**
	 * Show Reference in browser
	 *
	 * @throws \WP_CLI\ExitException
	 */
	public function run_browser() {

		//Check Link ID
		$json = $this->exist_link_ID( 1, false );

		//prepare url
		if ( $json != false and is_array( $json ) and count( $json ) == 1 ) {
			$url = $json[1];
			$this->remove_last_search_file();
		} else {
			$url = str_replace( "[url]", self::$developer_wordpress, self::$reference_home_page );
		}

		//Run Browser
		self::Browser( $url );
	}

	/**
	 * Run Search Command in Wordpress Code Reference
	 *
	 * @param $search
	 * @param array $args
	 * @throws \WP_CLI\ExitException
	 */
	public function run_search( $search, $args = array() ) {

		//Set Default Data
		$defaults = array(
			'allowed_filter' => false,
			'source'         => false
		);
		$arg      = self::parse_args( $args, $defaults );

		//Sanitize Search Word
		$search = $this->sanitize_search_word( $search );

		//Create Cache Folder
		$this->create_cache_dir();

		//if exist last search file remove it.
		$this->remove_last_search_file();

		//Generate Search Link
		$url = $this->generate_search_link( $search, $arg['allowed_filter'], $paged = 1 );

		//Check Local Cache
		$LocalCache = self::check_cache_url( $url );
		if ( $LocalCache != false ) {

			//Get Last content
			$content = $LocalCache;

			//Generate Last Link
			$last_link_data = $this->generate_last_link_file_data( $content );
		} else {

			//Request Data From API
			$response = $this->fetch_data( $url );
			if ( $response['status'] == "error" ) {
				self::pl_wait_end();
				WP_CLI::error( $this->log['connect'] );
				exit;
			}

			/*
			 * if status == redirect, This is a Single Page
			 */
			if ( $response['status'] == "redirect" ) {

				$single_page_data = $this->get_single_page_data( $response['url'] );
				$content          = $single_page_data['content'];
				$last_link_data   = $single_page_data['last_link_data'];

			} else {

				/*
				 * Check is 404 Not Found
				 */
				$dom = new DOMDocument;
				if ( @$dom->loadHTML( $response['content'] ) === false ) {
					self::pl_wait_end();
					WP_CLI::error( $this->log['dom'] );
				}
				if ( $this->is_404_page( $dom ) === true ) {
					self::pl_wait_end();
					WP_CLI::error( $this->log['not_found'] );
					exit;
				}

				/*
				 * Get Search List
				 */
				//Check exist Pagination
				$this->is_exist_pagination( $dom );

				//Get First Page Content
				$content       = $this->get_search_list( $dom );
				$number_result = count( $content );

				//If Page Pagination Exist
				if ( $this->is_pagination === true ) {
					//Create Loop For Pagination
					for ( $paginate = 2; $paginate <= $this->max_page_request; $paginate ++ ) {

						//Create Page Link
						$paged_url = $this->generate_search_link( $search, $arg['allowed_filter'], $paginate );

						//Fetch Data
						$result = $this->fetch_data( $paged_url );
						if ( $result['status'] == "data" ) {

							//Get Dom Data
							$dom = new DOMDocument;
							@$dom->loadHTML( $result['content'] );

							//Fetch this Page Data
							$this_page_content = $this->get_search_list( $dom );

							//Push To All Item
							foreach ( $this_page_content as $key => $value ) {
								$number_result ++;
								$content[ $number_result ] = $value;
							}

							//Check if Last Page in Pagination
							if ( $this->check_is_last_search_page( $dom ) === true ) {
								break;
							}
						}
					}
				}

				//Create Last Link Cache
				$last_link_data = $this->generate_last_link_file_data( $content );
			}

			//Create Cache file
			if ( isset( $content ) and ! empty( $content ) ) {
				$this->create_cache_data( $url, $content );
			}
		}

		//Action end Process
		$this->end_command_process( $content, $last_link_data, $arg );
	}

	/**
	 * Get Single Page Command
	 *
	 * @param $url
	 * @throws \WP_CLI\ExitException
	 */
	private function single_page_command( $url ) {

		//Create Cache Folder
		$this->create_cache_dir();

		//Check Local Cache
		$LocalCache = self::check_cache_url( $url );
		if ( $LocalCache != false ) {

			//Get Last content
			$content = $LocalCache;

			//Generate Last Link
			$last_link_data = $this->generate_last_link_file_data( $content );
		} else {

			//Request Data From API
			$response = $this->fetch_data( $url );
			if ( $response['status'] == "error" ) {
				self::pl_wait_end();
				WP_CLI::error( $this->log['connect'] );
				exit;
			}

			/*
			 * This is a Single Page
			 */
			$single_page_data = $this->get_single_page_data( $url );
			$content          = $single_page_data['content'];
			$last_link_data   = $single_page_data['last_link_data'];

			//Create Cache file
			if ( isset( $content ) and ! empty( $content ) ) {
				$this->create_cache_data( $url, $content );
			}
		}

		//Action end Process
		$this->end_command_process( $content, $last_link_data );
	}

	/**
	 * Sanitize Search Word
	 *
	 * @param $search
	 * @return string
	 * @throws \WP_CLI\ExitException
	 */
	private function sanitize_search_word( $search ) {

		//Sanitize Search Content
		$word = preg_replace( '/[^a-zA-Z0-9]/', '', $search );
		if ( trim( $word ) == "" ) {

			self::pl_wait_end();
			WP_CLI::error( $this->log['enter_search_keyword'] );
			exit;
		} else {
			return $search;
		}
	}

	/**
	 * Generate Last Link File Data
	 *
	 * @param $content
	 * @return array
	 */
	private function generate_last_link_file_data( $content ) {

		if ( isset( $content['structure'] ) ) {

			//this is a alone page Method
			$last_link_data = array( 1 => $content['page_url'] );
		} else {
			//this is search list
			$last_link_data = array();
			foreach ( $content as $k => $val ) {
				$last_link_data[ $k ] = $val['link'];
			}
		}

		return $last_link_data;
	}

	/**
	 * Action in end reference command Process
	 *
	 * @param $content
	 * @param $last_link_data
	 * @param array $options
	 * @throws \WP_CLI\ExitException
	 */
	private function end_command_process( $content, $last_link_data, $options = array() ) {

		//Start Clean Cache system
		$this->remove_dynamic_cache_file();

		//Create Last Link Data
		if ( isset( $last_link_data ) ) {
			self::create_json_file( $this->last_search_file_path, $last_link_data, false );
		}

		//Show Result
		self::pl_wait_end();
		if ( isset( $content ) and ! empty( $content ) ) {
			$this->show_result_command( $content, $options );
		} else {
			WP_CLI::error( $this->log['dom'] );
		}

	}

	/**
	 * Show Result in Command Line
	 *
	 * @param $content
	 * @param array $options
	 * @throws \WP_CLI\ExitException
	 */
	private function show_result_command( $content, $options = array() ) {

		//Check is Search list or Post
		if ( isset( $content['structure'] ) ) {
			//this is Single Post
			$title_color = "G";
			self::br();

			//Structure
			if ( isset( $content['structure'] ) ) {
				WP_CLI::line( self::color( "# Structure", $title_color ) );
				self::br();

				// Sanitize Structure
				$exp  = explode( " ", $content['structure'] . ";" );
				$word = '';
				for ( $i = 0; $i < count( $exp ); $i ++ ) {
					if ( $i == 0 ) {
						$explode = explode( "( ", $exp[0] );
						$word    .= self::color( $explode[0] . "( ", "Y" ) . self::color( $explode[1], "P" );
					} else {
						if ( stristr( $exp[ $i ], "," ) ) {
							$explode = explode( ", ", $exp[ $i ] );
							$word    .= self::color( $explode[0] . ", ", "Y" ) . self::color( $explode[1], "P" );
						} else {
							$word .= self::color( $exp[ $i ], "Y" );
						}
					}
					$word .= " ";
				}
				WP_CLI::line( "  " . $word . "" );
				self::br();

				//Show Summary
				if ( isset( $content['summary'] ) and ! empty( $content['summary'] ) ) {
					WP_CLI::line( "Summary   : " . $content['summary'] );
				}

				//Show Type
				if ( isset( $content['type'] ) and ! empty( $content['type'] ) ) {
					WP_CLI::line( "Reference : " . $content['type'] );
				}

				//show Url
				if ( isset( $content['page_url'] ) and ! empty( $content['page_url'] ) ) {
					WP_CLI::line( "Url       : " . $content['page_url'] );
				}

				//Source File
				if ( isset( $content['source'] ) and ! empty( $content['source']['file'] ) ) {
					WP_CLI::line( "Source    : " . trim( $content['source']['file'] ) . ( isset( $content['source']['line'] ) ? ":" . trim( $content['source']['line'] ) : '' ) );
				}

			}

			//Description
			if ( isset( $content['description'] ) and ! empty( $content['description'] ) ) {
				self::br();
				WP_CLI::line( self::color( "# Description", $title_color ) );
				self::br();
				WP_CLI::log( " " . $content['description'] );
			}

			//Show Parameter
			if ( isset( $content['parameters'] ) and ! empty( $content['parameters'] ) ) {
				self::br();
				WP_CLI::line( self::color( "# Parameters", $title_color ) );
				self::br();

				foreach ( $content['parameters'] as $k_param => $v_param ) {
					//Show Param name
					WP_CLI::line( " " . self::color( $k_param, "Y" ) );

					//Show Param Option
					WP_CLI::line( "  " . self::color( "(" . $v_param['type'] . ")", "P" ) . " " . self::color( "{" . $v_param['required'] . "}", "B" ) );

					//Show Desc
					WP_CLI::line( "    " . $v_param['description'] );

					//Check Default Params
					if ( isset( $v_param['param'] ) and ! empty( $v_param['param'] ) ) {
						self::br();
						foreach ( $v_param['param'] as $_param_k => $_param_v ) {
							WP_CLI::line( "       " . $_param_k );
							WP_CLI::line( "         " . "(" . $_param_v['type'] . ")" );
							WP_CLI::line( "            " . "(" . $_param_v['desc'] . ")" );
							self::br();
						}
					}

					self::br();
				}

				//Show Return
				if ( isset( $content['return'] ) and ! empty( $content['return'] ) ) {
					self::br();
					WP_CLI::line( self::color( "# Return", $title_color ) );
					self::br();
					WP_CLI::line( " (" . trim( $content['return']['type'] ) . ") " . trim( $content['return']['desc'] ) );
				}

				//Show Change Log
				if ( isset( $content['changelog']['tbody'] ) and ! empty( $content['changelog']['tbody'] ) ) {
					self::br();
					WP_CLI::line( self::color( "# Changelog", $title_color ) );
					self::br();
					self::create_table( $content['changelog']['tbody'] );
				}

			}

		} else {

			//Search List Page
			self::br();
			foreach ( $content as $_s_key => $_s_value ) {
				WP_CLI::line( "{$_s_key}. " . self::color( $_s_value['title'], "Y" ) . " " . self::color( "[" . ucfirst( $_s_value['type'] ) . "]", "B" ) );
				WP_CLI::line( self::color( "     Source: " . $_s_value['file'] . ":" . $_s_value['line'], "P" ) );
				WP_CLI::line( "      " . $_s_value['desc'] );
				self::br();
			}
			self::br();

			// Get ID
			self::create_table();
			while ( true ) {
				echo $this->log['enter_id'];
				$ID = fread( STDIN, 80 );
				if ( is_numeric( trim( $ID ) ) ) {
					break;
				}
			}
			if ( isset( $ID ) ) {
				$ID   = (int) $ID;
				$json = $this->read_last_link_file();
				if ( array_key_exists( $ID, $json ) ) {

					//Show Document
					self::pl_wait_start();
					$this->single_page_command( $json[ $ID ] );
				}
			}

		}

	}

	/**
	 * Get Single Page Data
	 *
	 * @param $url
	 * @return array
	 * @throws \WP_CLI\ExitException
	 */
	private function get_single_page_data( $url ) {

		//Request again For Get Data
		$result = $this->fetch_data( $url );

		//Check Error in connecting
		if ( $result['status'] == "error" ) {
			WP_CLI::error( $this->log['connect'] );
		} else {

			//Start Dom Document
			$dom = new DOMDocument;
			if ( @$dom->loadHTML( $result['content'] ) === false ) {
				WP_CLI::error( $this->log['dom'] );
			}

			//Get Page Data
			$content = $this->get_page_document( $dom, $url );
			return array( 'content' => $content, 'last_link_data' => $this->generate_last_link_file_data( $content ) );
		}
	}

	/**
	 * Generate Search Link
	 *
	 * @param $search
	 * @param bool $allowed_filter
	 * @param int $paged
	 * @return mixed|string
	 */
	private function generate_search_link( $search, $allowed_filter = false, $paged = 1 ) {

		//Check String $allowed_filter
		if ( $allowed_filter != false and is_string( $allowed_filter ) ) {
			$allowed_filter = array( $allowed_filter );
		}

		//Create Search Link
		$link = str_ireplace( "[search_word]", trim( $search ), $this->wp_reference_search_link );
		$link = str_ireplace( "[paged]", $paged, $link );
		$link = str_ireplace( "[url]", self::$developer_wordpress, $link );

		//Check Allowed filter
		if ( $allowed_filter === false ) {

			//Add Default All filter List
			$allowed_filter = $this->filter_list;

			//Remove Default Disable Filter
			$allowed_filter = array_diff( $allowed_filter, $this->default_disable_filter );
		}

		//Add Query Parameter To Link
		foreach ( $allowed_filter as $GET ) {
			$link .= '&' . $this->filter_request . '[]=' . $this->filter_prefix . $GET;
		}

		return $link;
	}

	/**
	 * Check Link ID exist
	 *
	 * @param $ID
	 * @param bool $log
	 * @return bool
	 * @throws \WP_CLI\ExitException
	 */
	private function exist_link_ID( $ID, $log = true ) {

		//Read Last Link file
		$json = $this->read_last_link_file( $log );

		//Check exist ID
		if ( $json != false and is_array( $json ) and array_key_exists( $ID, $json ) ) {
			return $json;
		} else {

			if ( $log ) {
				WP_CLI::error( $this->log['not_found_id'] );
			} else {
				return false;
			}
		}
	}

	/**
	 * Read Last Link File
	 *
	 * @param bool $log
	 * @return array|bool
	 * @throws \WP_CLI\ExitException
	 */
	private function read_last_link_file( $log = true ) {

		//Check Exist Last Link File
		if ( ! file_exists( $this->last_search_file_path ) ) {
			if ( $log ) {
				WP_CLI::error( $this->log['empty_search_history'] );
			} else {
				return false;
			}
		}

		//Read File
		$json = self::read_json_file( $this->last_search_file_path );
		if ( $json === false ) {
			self::remove_file( $this->last_search_file_path );
			if ( $log ) {
				WP_CLI::error( $this->log['empty_search_history'] );
			} else {
				return false;
			}
		}

		return $json;
	}

	/**
	 * Check Page is contain Pagination
	 *
	 * @param $html_dom
	 */
	private function is_exist_pagination( $html_dom ) {

		$nav = $html_dom->getElementsByTagName( 'nav' );
		$i   = 0;
		foreach ( $nav as $element ) {
			$classy = $element->getAttribute( "class" );
			if ( stristr( $classy, "pagination" ) !== false ) {
				$this->is_pagination  = true;
				$this->pagination_key = $i;
			}
			$i ++;
		}
	}

	/**
	 * Check is Last Search Page
	 * @param $html_dom
	 * @return bool
	 */
	private function check_is_last_search_page( $html_dom ) {

		//If Page is not Pagination
		if ( $this->is_pagination === false ) {
			return false;
		}

		//Check Last Page Key
		$is_last_page   = true;
		$nav            = $html_dom->getElementsByTagName( 'nav' );
		$pagination_dom = $nav->item( $this->pagination_key )->getElementsByTagName( 'a' );
		foreach ( $pagination_dom as $html ) {
			$inner_text = $html->nodeValue;
			if ( stristr( $inner_text, "next" ) !== false ) {
				$is_last_page = false;
			}
		}

		return $is_last_page;
	}

	/**
	 * Check 404 Page Not Found
	 *
	 * @param $html_dom
	 * @return bool
	 */
	private function is_404_page( $html_dom ) {

		$is_not_found = false;
		$section      = $html_dom->getElementsByTagName( 'section' );
		foreach ( $section as $element ) {
			$classy = $element->getAttribute( "class" );
			if ( stristr( $classy, "not-found" ) !== false ) {
				$is_not_found = true;
			}
		}

		return $is_not_found;
	}

	/**
	 * Convert Link To file Name
	 *
	 * @param $link
	 * @return mixed|string
	 */
	private static function convert_link_to_file( $link ) {
		//Trim Last slash
		$link = rtrim( $link, "/" );

		//Remove Site Url
		$link = str_ireplace( array( self::$developer_wordpress ), "reference", $link );

		//Remove Protocol
		$link = str_ireplace( array( "https://", "http://" ), "", $link );

		//Convert Slash to line
		$link = str_ireplace( "/", "---", $link );

		//Convert Search Parameter
		$link = str_ireplace( "?", "+++", $link );

		return $link;
	}

	/**
	 * Create Cache Folder
	 */
	private function create_cache_dir() {
		if ( ! self::folder_exist( $this->path ) ) {
			self::create_dir( $this->dir, self::get_cache_dir() );
		}
	}

	/**
	 * Remove Last Search File
	 */
	private function remove_last_search_file() {
		if ( file_exists( $this->last_search_file_path ) ) {
			self::remove_file( $this->last_search_file_path );
		}
	}

	/**
	 * Check Cache Url data
	 *
	 * @param $url
	 * @return bool|array
	 */
	private function check_cache_url( $url ) {

		//convert Url to File name
		$file_name = self::convert_link_to_file( $url );

		//Search in files
		$file_path = self::path_join( $this->path, $file_name . ".json" );
		if ( file_exists( $file_path ) ) {
			return self::read_json_file( $file_path );
		}

		return false;
	}

	/**
	 * Create Cache Data From Url
	 *
	 * @param $url
	 * @param $data
	 * @return bool
	 */
	private function create_cache_data( $url, $data ) {

		//convert Url to File name
		$file_name = self::convert_link_to_file( $url );

		//Create Cache File data
		$file_path = self::path_join( $this->path, $file_name . ".json" );
		if ( self::create_json_file( $file_path, $data, false ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Remove Dynamic Cache File according to Max history file
	 */
	private function remove_dynamic_cache_file() {

		//Get list of file according to Date Create
		$list_of_cache_file = self::sort_dir_by_date( $this->path, "ASC" );

		//Remove Last Link From list
		$list_of_cache_file = array_diff( $list_of_cache_file, array( $this->last_search_file ) );

		//Get number File in _Cache Folder
		$number_file = count( $list_of_cache_file );

		//Check Max Number Cache
		if ( $number_file > $this->max_cache_reference ) {
			$_to_remove = $number_file - $this->max_cache_reference;
			for ( $i = 0; $i < $_to_remove; $i ++ ) {
				self::remove_file( self::path_join( $this->path, $list_of_cache_file[ $i ] ) );
			}
		}
	}

	/**
	 * Get List Of Search Page
	 *
	 * @param $dom
	 * @return array
	 */
	private function get_search_list( $dom ) {

		//Create Empty List Obj
		$result = array();

		//Check if Exist dom
		$main = $dom->getElementsByTagName( 'main' );
		if ( isset( $dom ) and $main->length > 0 ) {
			$articles = $main->item( 0 )->getElementsByTagName( "article" );
			$i        = 1;
			foreach ( $articles as $a ) {
				$item = array();

				//Get H1
				$h1 = $a->getElementsByTagName( "h1" )->item( 0 );

				//Get Title of Article
				$item['title'] = $h1->nodeValue;

				//Get link Of Page
				$item['link'] = $h1->getElementsByTagName( "a" )->item( 0 )->getAttribute( 'href' );

				//Get type [List of type : function - action - filter - class - method ]
				$description  = $a->getElementsByTagName( "div" )->item( 0 )->getElementsByTagName( "p" )->item( 0 );
				$type         = $description->getElementsByTagName( "b" )->item( 0 )->nodeValue;
				$item['type'] = trim( strtolower( str_ireplace( array( ":", " Hook" ), "", $type ) ) );

				//Get Description
				$desc         = $description->nodeValue;
				$exp          = explode( ":", $desc );
				$item['desc'] = trim( $exp[1] );

				//Source File
				$source       = $a->getElementsByTagName( "div" )->item( 1 )->getElementsByTagName( "p" )->item( 0 )->nodeValue;
				$source       = str_ireplace( "Source: ", "", $source );
				$exp          = explode( ":", $source );
				$item['file'] = $exp[0];
				$item['line'] = $exp[1];

				//Push To List
				$result[ $i ] = $item;
				$i ++;
			}
		}

		return $result;
	}

	/**
	 * Get Page Document
	 *
	 * @param $dom
	 * @param $url
	 * @return array|bool
	 */
	private function get_page_document( $dom, $url ) {

		$main = $dom->getElementsByTagName( 'main' );
		if ( isset( $dom ) and $main->length > 0 ) {
			$articles = $main->item( 0 )->getElementsByTagName( "article" )->item( 0 );
			$document = array();

			//Save Url
			$document['page_url'] = $url;

			//Type of this method
			$document['type'] = self::get_type_of_code( $url );

			//Get structure
			$document['structure'] = str_ireplace( "&nbsp;", " ", self::get_tag_content( $articles->getElementsByTagName( "h1" )->item( 0 ) ) );

			//Get Summary
			$document['summary'] = self::get_tag_content( $articles->getElementsByTagName( "section" )->item( 0 )->getElementsByTagName( "p" )->item( 0 ) );

			//Check Content
			$content_dom = self::get_tags_with_class( $articles->getElementsByTagName( "div" ), "content-toc" );
			if ( ! is_null( $content_dom ) ) {

				//Get Description
				$desc_dom = self::get_tags_with_class( $content_dom->getElementsByTagName( "section" ), "description" );
				if ( ! is_null( $desc_dom ) ) {
					$document['description'] = self::get_tag_html( $dom->saveXML( $desc_dom ) );
				}

				//Get Source File
				$source_dom = self::get_tags_with_class( $content_dom->getElementsByTagName( "section" ), "source-content" );
				if ( ! is_null( $source_dom ) ) {
					if ( self::exist_tag( $source_dom->getElementsByTagName( "p" ) ) ) {
						$document['source'] = array();

						//Get Source file
						$p_ID = 0;
						if ( trim( strtolower( $source_dom->getElementsByTagName( "p" )->item( 0 )->getAttribute( "class" ) ) ) == "toc-jump" ) {
							$p_ID = 1;
						}
						$document['source']['file'] = self::remove_whitespace_word( str_ireplace( "file: ", "", self::get_tag_content( $source_dom->getElementsByTagName( "p" )->item( $p_ID ) ) ) );

						//Get First Line
						$gutter = self::get_tags_with_class( $source_dom->getElementsByTagName( "div" ), "source-code-container" );
						if ( ! is_null( $gutter ) ) {

							//Get PRE information
							$pre                        = $gutter->getElementsByTagName( "pre" )->item( 0 )->getAttribute( "class" );
							$exp_pre                    = explode( "first-line:", $pre );
							$document['source']['line'] = trim( $exp_pre[1] );
						}
					}
				}

				//Get Parameters
				$param_dom = self::get_tags_with_class( $content_dom->getElementsByTagName( "section" ), "parameters" );
				if ( ! is_null( $param_dom ) ) {
					$dl_list = $param_dom->getElementsByTagName( "dl" );
					if ( self::exist_tag( $dl_list ) ) {
						$dt         = $dl_list->item( 0 )->getElementsByTagName( "dt" );
						$dd         = $dl_list->item( 0 )->getElementsByTagName( "dd" );
						$param_key  = array();
						$param_desc = array();
						foreach ( $dt as $dt_list ) {
							$param_key[] = self::get_tag_content( $dt_list );
						}
						foreach ( $dd as $dd_list ) {
							$param_array = array( 'type' => 'string', 'required' => 'Optional', 'description' => '', 'param' => array() );
							$get_p       = $dd_list->getElementsByTagName( "p" )->item( 0 );

							//Check Type
							$type_dom = self::get_tags_with_class( $get_p->getElementsByTagName( "span" ), "type" );
							if ( ! is_null( $type_dom ) ) {
								$param_array['type'] = self::remove_parentheses( self::get_tag_content( $type_dom ) );
							}

							//Check Required
							$require_dom = self::get_tags_with_class( $get_p->getElementsByTagName( "span" ), "required" );
							if ( ! is_null( $require_dom ) ) {
								$param_array['required'] = self::remove_parentheses( self::get_tag_content( $require_dom ) );
							}

							//Check Desc every item
							$param_desc_dom = self::get_tags_with_class( $get_p->getElementsByTagName( "span" ), "description" );
							if ( ! is_null( $param_desc_dom ) ) {
								$param_array['description'] = self::remove_whitespace_word( self::get_tag_content( $param_desc_dom ) );
							}

							//Check arg params
							if ( self::exist_tag( $dd_list->getElementsByTagName( "ul" ) ) ) {
								$internal_param_dom = $dd_list->getElementsByTagName( "ul" )->item( 0 )->getElementsByTagName( "li" );
								if ( isset( $internal_param_dom ) ) {
									$internal_param = array();
									foreach ( $internal_param_dom as $li ) {
										$p_name                    = trim( self::get_tag_content( $li->getElementsByTagName( "b" )->item( 0 ) ), "'" );
										$p_type                    = self::remove_parentheses( self::get_tag_content( $li->getElementsByTagName( "i" )->item( 0 ) ) );
										$exp_desc                  = explode( "(" . $p_type . ")", self::get_tag_content( $li ) );
										$internal_param[ $p_name ] = array( 'type' => $p_type, 'desc' => trim( $exp_desc[1] ) );
									}

									//Set to Global
									$param_array['param'] = $internal_param;

									//Fixed Description
									$get_first_param            = key( $param_array['param'] );
									$exp_description            = explode( "'" . $get_first_param . "'", $param_array['description'] );
									$param_array['description'] = trim( $exp_description[0] );
								}
							}

							//Push To array
							$param_desc[] = $param_array;
						}

					}

					//Set Parameter to Global
					if ( isset( $param_key ) and isset( $param_desc ) and count( $param_key ) > 0 ) {
						$document['parameters'] = array();
						for ( $i = 0; $i < count( $param_key ); $i ++ ) {
							$document['parameters'][ $param_key[ $i ] ] = $param_desc[ $i ];
						}
					}
				}

				//Get Return
				$return_dom = self::get_tags_with_class( $content_dom->getElementsByTagName( "section" ), "return" );
				if ( ! is_null( $return_dom ) ) {
					if ( self::exist_tag( $return_dom->getElementsByTagName( "p" ) ) ) {
						$document['return']         = array();
						$return_type                = self::get_tag_content( $return_dom->getElementsByTagName( "p" )->item( 1 )->getElementsByTagName( "span" )->item( 0 ) );
						$document['return']['type'] = self::remove_parentheses( $return_type );
						$exp_desc                   = explode( $return_type, self::get_tag_content( $return_dom->getElementsByTagName( "p" )->item( 1 ) ) );
						$document['return']['desc'] = self::remove_whitespace_word( $exp_desc[1] );
					}
				}

				//Get Changelog
				$changelog_dom = self::get_tags_with_class( $content_dom->getElementsByTagName( "section" ), "changelog" );
				if ( ! is_null( $changelog_dom ) ) {
					if ( self::exist_tag( $changelog_dom->getElementsByTagName( "table" ) ) ) {
						$document['changelog'] = self::get_table_content( $changelog_dom->getElementsByTagName( "table" )->item( 0 ) );
					}
				}

				//Return Complete Array
				return $document;
			}

		}

		return false;
	}

	/**
	 * Fetch Data From Url
	 *
	 * @param $url
	 * @return array|boolean
	 */
	private function fetch_data( $url ) {

		# Request Header
		$headers = array( 'Accept' => 'application/json' );

		# Response
		$response = WP_CLI\Utils\http_request( 'GET', $url, null, $headers, array( 'timeout' => 600 ) );
		if ( 200 === $response->status_code ) {
			if ( $response->redirects ) {
				return array( 'status' => 'redirect', 'url' => $response->url );
			} else {
				return array( 'status' => 'data', 'content' => $response->body );
			}
		}
	}

	/**
	 * Get Tags With class name
	 *
	 * @param $dom
	 * @param $class
	 * @return bool
	 */
	private static function get_tags_with_class( $dom, $class ) {
		$i = 0;
		foreach ( $dom as $element ) {
			$classy = $element->getAttribute( "class" );
			if ( stristr( $classy, $class ) !== false ) {
				return $element;
			}
			$i ++;
		}

		return null;
	}

	/**
	 * Check Exist Tag
	 *
	 * @param $tag | $dom->getElementsByTagName( 'main' )
	 * @return bool
	 */
	private static function exist_tag( $tag ) {
		if ( isset( $tag ) and $tag->length > 0 ) {
			return true;
		}

		return false;
	}

	/**
	 * Show Tags Content
	 *
	 * @param $tag
	 * @param bool $strip
	 * @return string
	 */
	private static function get_tag_content( $tag, $strip = false ) {
		$content = @ $tag->textContent;
		if ( $strip ) {
			return strip_tags( $content );
		}

		return $content;
	}

	/**
	 * Get Html Content From Custom Dom
	 *
	 * @param $xml
	 * @return string
	 */
	private static function get_tag_html( $xml ) {

		//Remove Tags With inner Content
		$tags = array( "h2", "i" );
		$text = preg_replace( '#<(' . implode( '|', $tags ) . ')(?:[^>]+)?>.*?</\1>#s', '', $xml );

		//Stripe Certain Tag
		$tags = array( "code", "a", "section" );
		foreach ( $tags as $tag ) {
			$text = self::strip_single_tag( $text, $tag );
		}

		//Add line Break
		$text = preg_replace( "/<\/p>/", '</p>\n\n', $text );
		$text = preg_replace( "/<\/li>/", '</li>\n', $text );

		//Stripe All Tag
		$text = trim( self::remove_whitespace_word( strip_tags( $text ) ) );

		//Trim Right \n\n
		$text = rtrim( $text, '\n\n' );

		return $text;
	}

	/**
	 * Remove parentheses from Content
	 * @param $content
	 * @return string
	 */
	private static function remove_parentheses( $content ) {
		return str_ireplace( array( "(", ")" ), "", $content );
	}

	/**
	 * Remove White Space From Word
	 * @param $content
	 * @return null|string|string[]
	 */
	private static function remove_whitespace_word( $content ) {
		return preg_replace( '/\s\s+/', ' ', $content );
	}

	/**
	 * Get Table Content
	 * @param $tables
	 * @return array
	 */
	private static function get_table_content( $tables ) {

		//Create Empty object
		$tbl = array();

		//First Get th of table
		$th = $tables->getElementsByTagName( 'thead' )->item( 0 )->getElementsByTagName( 'tr' )->item( 0 )->getElementsByTagName( 'th' );
		foreach ( $th as $title ) {
			$tbl['thead'][] = self::get_tag_content( $title );
		}

		//Get table Content
		$tbody = $tables->getElementsByTagName( 'tbody' )->item( 0 )->getElementsByTagName( 'tr' );
		foreach ( $tbody as $row ) {
			$cols        = $row->getElementsByTagName( 'td' );
			$row_content = array();
			for ( $i = 0; $i < count( $tbl['thead'] ); $i ++ ) {
				$row_content[ $tbl['thead'][ $i ] ] = self::remove_whitespace_word( self::get_tag_content( $cols->item( $i ) ) );
			}
			$tbl['tbody'][] = $row_content;
		}

		return $tbl;
	}

	/**
	 * Check Type Of Code
	 *
	 * @param $link
	 * @return string
	 * function - action - filter - class - method
	 */
	private static function get_type_of_code( $link ) {

		//Check is Hook
		if ( stristr( $link, "/hooks/" ) != false ) {
			return 'Hooks';
		}

		//Check is Class or Method
		if ( stristr( $link, "/classes/" ) != false ) {
			$exp       = explode( "/classes/", $link );
			$exp_slash = array_filter( explode( "/", $exp[1] ) );
			if ( count( $exp_slash ) == 1 ) {
				return 'Class';
			} else {
				return 'Method';
			}
		}

		return 'Function';
	}

	/**
	 * Stripe Single Tag
	 * @param $str
	 * @param $tag
	 * @return null|string|string[]
	 */
	private static function strip_single_tag( $str, $tag ) {
		$str = preg_replace( '/<' . $tag . '[^>]*>/i', '', $str );
		$str = preg_replace( '/<\/' . $tag . '>/i', '', $str );
		return $str;
	}

	/**
	 * Join two filesystem paths together
	 *
	 * @see https://developer.wordpress.org/reference/functions/path_join/
	 * @param $base
	 * @param $path
	 * @return string
	 */
	public static function path_join( $base, $path ) {
		return rtrim( self::backslash_to_slash( $base ), '/' ) . '/' . ltrim( self::backslash_to_slash( $path ), '/' );
	}

	/**
	 * Convert all backslash to slash
	 *
	 * @param $string
	 * @return mixed
	 */
	public static function backslash_to_slash( $string ) {
		return str_replace( "\\", "/", $string );
	}

	/**
	 * Checks if a folder exist
	 *
	 * @param $folder
	 * @return bool
	 */
	public static function folder_exist( $folder ) {
		$path = $folder;
		if ( function_exists( "realpath" ) ) {
			$path = realpath( $folder );
		}

		// If it exist, check if it's a directory
		return ( $path !== false AND is_dir( $path ) ) ? true : false;
	}

	/**
	 * Remove Complete Folder
	 *
	 * @param $dir
	 * @param bool $remove_folder
	 * @return bool
	 */
	public static function remove_dir( $dir, $remove_folder = false ) {
		$di = new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS );
		$ri = new RecursiveIteratorIterator( $di, RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $ri as $file ) {
			$file->isDir() ? rmdir( $file ) : unlink( $file );
		}
		if ( $remove_folder ) {
			@rmdir( $dir );
		}

		return true;
	}

	/**
	 * Show Url in Browser
	 *
	 * @package wp-cli-application
	 * @version 1.0.0
	 * @param $url
	 * @throws \WP_CLI\ExitException
	 */
	public static function Browser( $url ) {

		//Check User Platform
		if ( preg_match( '/^darwin/i', PHP_OS ) ) {
			$cmd = 'open';
		} elseif ( preg_match( '/^win/i', PHP_OS ) ) {
			$cmd = 'start';
		} elseif ( preg_match( '/^linux/i', PHP_OS ) ) {
			$cmd = 'xdg-open';
		}

		/**
		 * escape Url sanitize
		 * @see https://ss64.com/nt/syntax-esc.html
		 */
		$sanitize_web_url = filter_var( $url, FILTER_SANITIZE_URL );
		if ( $sanitize_web_url !== false ) {
			$url = str_replace( "&", "^&", $sanitize_web_url );
		}

		//Run
		if ( isset( $cmd ) ) {
			$command = $cmd . " {$url}";
			self::exec( $command );
		}
	}

	/**
	 * Run System Command
	 *
	 * @param $cmd
	 * @throws \WP_CLI\ExitException
	 */
	public static function exec( $cmd ) {
		if ( function_exists( 'system' ) ) {
			system( $cmd );
		} elseif ( function_exists( 'exec' ) ) {
			exec( $cmd );
		} else {
			WP_CLI::error( "`system` php function does not support in your server." );
		}
	}

	/**
	 * Define STDIN
	 */
	public static function define_stdin() {
		if ( ! defined( "STDIN" ) ) {
			define( "STDIN", fopen( 'php://stdin', 'rb' ) );
		}
	}

	/**
	 * Parse Arg Array
	 *
	 * @see https://developer.wordpress.org/reference/functions/wp_parse_args/
	 * @param $args
	 * @param string $defaults
	 * @return array
	 */
	public static function parse_args( $args, $defaults = '' ) {
		$r = $args;
		if ( is_object( $args ) ) {
			$r = get_object_vars( $args );
		} elseif ( is_array( $args ) ) {
			$r =& $args;
		}
		if ( is_array( $defaults ) ) {
			return array_merge( $defaults, $r );
		}
		return $args;
	}

	/**
	 * Colorize Text
	 *
	 * @see https://make.wordpress.org/cli/handbook/internal-api/wp-cli-colorize/
	 * @param $text
	 * @param $color
	 * @return string
	 */
	public static function color( $text, $color ) {
		return WP_CLI::colorize( "%{$color}$text%n" );
	}

	/**
	 * Remove File
	 *
	 * @param $path
	 * @return bool
	 */
	public static function remove_file( $path ) {
		if ( @unlink( self::normalize_path( $path ) ) === true ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * please wait ...
	 *
	 * @param bool $new_line
	 * @param string $color
	 */
	public static function pl_wait_start( $new_line = false, $color = 'B' ) {

		# Create Global Pl Constant
		if ( ! defined( 'WP_CLI_PLEASE_WAIT_LOG' ) ) {
			define( 'WP_CLI_PLEASE_WAIT_LOG', true );
		}

		# Check in new Line
		if ( $new_line ) {
			echo "\n";
		}

		# Show in php Cli
		echo self::color( "Please wait ...", $color ) . "\r";
	}

	/**
	 * Remove please wait ..
	 */
	public static function pl_wait_end() {
		self::remove_space_character( 50 );
	}

	/**
	 * Sort Dir By Date
	 *
	 * @param $dir
	 * @param string $sort
	 * @param array $disagree_type
	 *
	 * @return array
	 */
	public static function sort_dir_by_date( $dir, $sort = "DESC", $disagree_type = array( ".php" ) ) {

		$ignored = array( '.', '..', '.svn', '.htaccess' );
		$files   = array();
		foreach ( scandir( $dir ) as $file ) {
			if ( in_array( $file, $ignored ) ) {
				continue;
			}
			if ( count( $disagree_type ) > 0 ) {
				foreach ( $disagree_type as $type ) {
					if ( strlen( stristr( $file, $type ) ) > 0 ) {
						continue 2;
					}
				}
			}
			$files[ $file ] = filemtime( $dir . '/' . $file );
		}

		if ( $sort == "DESC" ) {
			arsort( $files, SORT_NUMERIC );
		} else {
			asort( $files, SORT_NUMERIC );
		}

		$files = array_keys( $files );
		return $files;
	}

	/**
	 * Remove Space Character
	 *
	 * @param int $num
	 */
	public static function remove_space_character( $num = PHP_INT_MAX ) {
		echo str_repeat( " ", $num ) . "\r";
	}

	/**
	 * Create Json File
	 *
	 * @param $file_path
	 * @param $array
	 * @param bool $JSON_PRETTY
	 * @return bool
	 */
	public static function create_json_file( $file_path, $array, $JSON_PRETTY = true ) {

		//Prepare Data
		if ( $JSON_PRETTY ) {
			$data = json_encode( $array, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK );
		} else {
			$data = json_encode( $array, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK );
		}

		//Save To File
		if ( self::file_put_content( $file_path, $data ) ) {
			return true;
		}

		return false;
	}

	/**
	 * <br> in Text
	 *
	 * @param int $num
	 */
	public static function br( $num = 1 ) {
		echo str_repeat( "\n", $num );
	}

	/**
	 * create file with content, and create folder structure if doesn't exist
	 *
	 * @param $file_path
	 * @param $data
	 * @return bool
	 */
	public static function file_put_content( $file_path, $data ) {
		try {
			$isInFolder = preg_match( "/^(.*)\/([^\/]+)$/", $file_path, $file_path_match );
			if ( $isInFolder ) {
				$folderName = $file_path_match[1];
				//$fileName   = $file_path_match[2];
				if ( ! is_dir( $folderName ) ) {
					mkdir( $folderName, 0777, true );
				}
			}
			file_put_contents( $file_path, $data, LOCK_EX );
			return true;
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Normalize Path
	 *
	 * @see https://developer.wordpress.org/reference/functions/wp_normalize_path/
	 * @param $path
	 * @return mixed|null|string|string[]
	 */
	public static function normalize_path( $path ) {
		$path = self::backslash_to_slash( $path );
		$path = preg_replace( '|(?<=.)/+|', '/', $path );
		if ( ':' === substr( $path, 1, 1 ) ) {
			$path = ucfirst( $path );
		}
		return $path;
	}

	/**
	 * Read Json File
	 *
	 * @param $file_path
	 * @return bool|array
	 */
	public static function read_json_file( $file_path ) {

		//Check Exist File
		if ( ! file_exists( self::normalize_path( $file_path ) ) ) {
			return false;
		}

		//Check readable
		if ( ! is_readable( self::normalize_path( $file_path ) ) ) {
			return false;
		}

		//Read File
		$strJson = file_get_contents( $file_path );
		$array   = json_decode( $strJson, true );
		if ( $array === null ) {
			return false;
		}

		return $array;
	}

	/**
	 * Create Folder
	 *
	 * @param $name
	 * @param $path
	 * @param int $permission
	 */
	public static function create_dir( $name, $path, $permission = 0755 ) {
		mkdir( rtrim( $path, "/" ) . "/" . $name, $permission, true );
	}

	/**
	 * Get WP-CLI Cache dir
	 *
	 * @param string $path
	 * @return string
	 */
	public static function get_cache_dir( $path = '' ) {
		if ( getenv( 'WP_CLI_CACHE_DIR' ) ) {
			$cache = getenv( 'WP_CLI_CACHE_DIR' );
		} else {
			$cache = self::path_join( self::get_home_path(), "cache/" );
		}

		return self::path_join( $cache, $path );
	}

	/**
	 * Get WP-CLI home path dir
	 *
	 * @param string $path
	 * @return string
	 */
	public static function get_home_path( $path = '' ) {
		$home_path = rtrim( WP_CLI\Utils\get_home_dir(), "/" ) . "/.wp-cli/";
		return self::path_join( $home_path, $path );
	}

	/**
	 * Create Table View
	 *
	 * @param array $array
	 * @param boolean|array $title
	 * @param bool $id
	 */
	public static function create_table( $array = array(), $title = true, $id = false ) {
		$list = self::formatter( $array, $title, $id );
		WP_CLI\Utils\format_items( 'table', $list['list'], $list['topic'] );
	}

	/**
	 * Wp-Cli Formatter
	 *
	 * @see https://make.wordpress.org/cli/handbook/internal-api/wp-cli-utils-format-items/
	 * @param array $array
	 * @param boolean|array $title
	 * @param bool $id
	 * @return array
	 */
	public static function formatter( $array = array(), $title = true, $id = false ) {

		//Create Title List
		if ( is_array( $title ) ) {
			$topic = $title;
		} else {
			$topic = array_keys( $array[0] );
		}

		//Check if ID Col is exist
		if ( $id != false ) {
			for ( $i = 0; $i < count( $array ); $i ++ ) {
				$array[ $i ][ $id ] = $i + 1;
			}
			array_unshift( $topic, $id );
		}

		return array(
			"list"  => $array,
			"topic" => $topic,
		);
	}

}